<?php
/**
 * GET api/check_status.php
 * Polled every ~20s by js/main.js (badge) and every ~20s by
 * my_bookings.php (countdown re-sync + auto-detect expiry/QR
 * confirmation from another tab/device). Cheap on purpose.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudentLogin();
$pdo = getDB();
expireStaleReservations($pdo);

$studentId = currentStudentId();
$reservation = getActiveReservation($pdo, $studentId);
$ban = getActiveBan($pdo, $studentId);

jsonSuccess([
    'unread_count' => unreadNotificationCount($pdo, $studentId),
    'reservation' => $reservation ? [
        'id' => (int) $reservation['id'],
        'status' => $reservation['status'],
        'bench_number' => (int) $reservation['bench_number'],
        'seat_number' => (int) $reservation['seat_number'],
        'expires_at' => (new DateTime($reservation['expires_at']))->format(DATE_ATOM),
    ] : null,
    'banned' => $ban ? true : false,
]);
