<?php
/** POST api/cancel_reservation.php   body: csrf */
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
$reservation = getActiveReservation($pdo, $studentId);

if (!$reservation) {
    jsonError('You do not have an active reservation to cancel.');
}
if ($reservation['status'] !== 'reserved') {
    jsonError('This seat is already occupied — use "Vacate Seat" instead of cancelling.');
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND status = 'reserved'")
        ->execute([$reservation['id']]);
    $pdo->prepare("UPDATE seats SET status = 'available' WHERE id = ? AND status = 'reserved'")
        ->execute([$reservation['seat_id']]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Could not cancel the reservation. Please try again.', 500);
}

jsonSuccess([], 'Reservation cancelled.');
