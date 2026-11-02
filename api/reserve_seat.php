<?php
/**
 * POST api/reserve_seat.php   body: seat_id, csrf
 *
 * This is the one place in the whole app where correctness under
 * concurrency actually matters: two students could tap "confirm" on
 * the same seat within the same millisecond. SELECT ... FOR UPDATE
 * inside a transaction makes the second request wait for the first
 * to finish, then see the now-updated seat status and reject cleanly
 * — instead of both writes racing and both students believing they
 * got the seat.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudentLogin();
$pdo = getDB();
expireStaleReservations($pdo);

if (!verifyCsrf($_POST['csrf'] ?? null)) {
    jsonError('Your session looks stale — please refresh the page and try again.', 419);
}

$studentId = currentStudentId();
$seatId = (int) ($_POST['seat_id'] ?? 0);
if ($seatId <= 0) {
    jsonError('Please choose a seat first.');
}

if ($ban = getActiveBan($pdo, $studentId)) {
    jsonError('Booking Restricted: your account is temporarily restricted from making seat reservations due to repeated no-shows. Restriction lifts on ' . formatDateTime($ban['end_date']) . '.');
}

$pdo->beginTransaction();
try {
    // Rule: only one active reservation (reserved OR occupied) per student.
    $existing = $pdo->prepare("SELECT id FROM reservations WHERE student_id = ? AND status IN ('reserved','occupied') FOR UPDATE");
    $existing->execute([$studentId]);
    if ($existing->fetch()) {
        $pdo->rollBack();
        jsonError('You already have an active reservation or seat. Vacate or finish it before booking another.');
    }

    // Lock the seat row so a concurrent request can't grab it between our check and our write.
    $seatStmt = $pdo->prepare('SELECT s.id, s.status, s.bench_id, b.status AS bench_status FROM seats s JOIN benches b ON b.id = s.bench_id WHERE s.id = ? FOR UPDATE');
    $seatStmt->execute([$seatId]);
    $seat = $seatStmt->fetch();

    if (!$seat || $seat['bench_status'] === 'unavailable' || $seat['status'] !== 'available') {
        $pdo->rollBack();
        jsonError('This seat is no longer available. Please choose another seat.');
    }

    $windowMinutes = (int) getSetting($pdo, 'reservation_window_minutes', 10);

    $ins = $pdo->prepare(
        "INSERT INTO reservations (student_id, seat_id, bench_id, status, reserved_at, expires_at)
         VALUES (?, ?, ?, 'reserved', NOW(), NOW() + INTERVAL ? MINUTE)"
    );
    $ins->execute([$studentId, $seatId, $seat['bench_id'], $windowMinutes]);
    $reservationId = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE seats SET status = 'reserved' WHERE id = ?")->execute([$seatId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Something went wrong while reserving the seat. Please try again.', 500);
}

createNotification(
    $pdo,
    $studentId,
    'confirmed',
    "Your seat has been reserved. Reach the canteen and scan the QR code within {$windowMinutes} minutes to confirm your seat.",
    $reservationId
);

jsonSuccess(['reservation_id' => $reservationId], 'Seat reserved successfully.');
