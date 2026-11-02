<?php
/**
 * POST api/verify_qr.php   body: qr_data, csrf
 * qr_data is the raw string decoded from the bench's QR code, which
 * encodes nothing but that bench's secret qr_token (see benches.qr_token).
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
$qrData = trim($_POST['qr_data'] ?? '');

if ($qrData === '') {
    jsonError('No QR code detected. Please try again.');
}

$benchStmt = $pdo->prepare('SELECT id, bench_number FROM benches WHERE qr_token = ?');
$benchStmt->execute([$qrData]);
$bench = $benchStmt->fetch();

if (!$bench) {
    jsonError('Invalid QR. Please scan the QR code of your reserved bench.');
}

$reservation = getActiveReservation($pdo, $studentId);

if (!$reservation) {
    jsonError("You don't have an active reservation. Please reserve a seat first.");
}

if ((int) $reservation['bench_id'] !== (int) $bench['id']) {
    jsonError('Invalid QR. Please scan the QR code of your reserved bench.');
}

if ($reservation['status'] === 'occupied') {
    jsonError('This seat is already confirmed as occupied.');
}

if (strtotime($reservation['expires_at']) < time()) {
    // Extremely rare race: expired between expireStaleReservations() above and here.
    jsonError('This reservation has expired. Please book another available seat.');
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE reservations SET status = 'occupied', confirmed_at = NOW() WHERE id = ? AND status = 'reserved'")
        ->execute([$reservation['id']]);
    $pdo->prepare("UPDATE seats SET status = 'occupied' WHERE id = ?")
        ->execute([$reservation['seat_id']]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Something went wrong confirming your seat. Please try again.', 500);
}

createNotification($pdo, $studentId, 'occupied', 'Seat confirmed successfully!', $reservation['id']);

jsonSuccess([], 'Seat confirmed successfully!');
