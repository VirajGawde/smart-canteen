<?php
/** POST api/vacate_seat.php   body: csrf */
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

// Ownership check happens right in the WHERE clause: a student can only
// ever match a row that is both theirs AND currently occupied.
$stmt = $pdo->prepare("SELECT id, seat_id FROM reservations WHERE student_id = ? AND status = 'occupied' ORDER BY id DESC LIMIT 1");
$stmt->execute([$studentId]);
$reservation = $stmt->fetch();

if (!$reservation) {
    jsonError('You do not have an occupied seat to vacate.');
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE reservations SET status = 'completed', vacated_at = NOW() WHERE id = ? AND status = 'occupied'")
        ->execute([$reservation['id']]);
    $pdo->prepare("UPDATE seats SET status = 'available' WHERE id = ?")
        ->execute([$reservation['seat_id']]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Could not vacate the seat. Please try again.', 500);
}

jsonSuccess([], 'Seat vacated successfully.');
