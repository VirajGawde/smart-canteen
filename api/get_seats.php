<?php
/**
 * GET api/get_seats.php?bench_id=N
 * Returns the 4 seats belonging to a bench with fresh status
 * (expiry is checked first so nothing stale is shown).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudentLogin();
$pdo = getDB();
expireStaleReservations($pdo);

$benchId = (int) ($_GET['bench_id'] ?? 0);
if ($benchId <= 0) {
    jsonError('Missing or invalid bench.');
}

$bench = $pdo->prepare('SELECT * FROM benches WHERE id = ?');
$bench->execute([$benchId]);
$bench = $bench->fetch();
if (!$bench) {
    jsonError('This bench does not exist.', 404);
}
if ($bench['status'] === 'unavailable') {
    jsonError('This bench is currently closed for maintenance.');
}

$stmt = $pdo->prepare('SELECT id, seat_number, status FROM seats WHERE bench_id = ? ORDER BY seat_number');
$stmt->execute([$benchId]);
$seats = array_map(function ($s) {
    $s['status_label'] = statusLabel($s['status']);
    return $s;
}, $stmt->fetchAll());

jsonSuccess([
    'bench' => ['id' => $bench['id'], 'bench_number' => $bench['bench_number'], 'location' => $bench['location']],
    'seats' => $seats,
]);
