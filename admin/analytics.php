<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireAdminLogin();
$pdo = getDB();

// ---- Occupancy by hour of day (all-time pattern) ----
$byHour = array_fill(0, 24, 0);
foreach ($pdo->query('SELECT HOUR(reserved_at) hr, COUNT(*) c FROM reservations GROUP BY hr') as $row) {
    $byHour[(int) $row['hr']] = (int) $row['c'];
}

// ---- Most used benches (top 8) ----
$topBenches = $pdo->query(
    "SELECT b.bench_number, COUNT(*) c FROM reservations r JOIN benches b ON b.id = r.bench_id
     GROUP BY b.id ORDER BY c DESC LIMIT 8"
)->fetchAll();

// ---- No-shows, last 7 days (filled so every day shows, even at 0) ----
$noShowRows = $pdo->query(
    "SELECT DATE(occurred_at) d, COUNT(*) c FROM no_shows WHERE occurred_at >= NOW() - INTERVAL 6 DAY GROUP BY d"
)->fetchAll(PDO::FETCH_KEY_PAIR);
$last7 = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $last7[date('D', strtotime($day))] = (int) ($noShowRows[$day] ?? 0);
}

// ---- Complaints by status ----
$complaintStatus = array_fill_keys(['submitted','under_review','verified','rejected','resolved'], 0);
foreach ($pdo->query('SELECT status, COUNT(*) c FROM complaints GROUP BY status') as $row) {
    $complaintStatus[$row['status']] = (int) $row['c'];
}

// ---- Key stat callouts ----
$avgDuration = $pdo->query(
    "SELECT AVG(TIMESTAMPDIFF(MINUTE, confirmed_at, vacated_at)) FROM reservations
     WHERE status = 'completed' AND confirmed_at IS NOT NULL AND vacated_at IS NOT NULL"
)->fetchColumn();
$totalNoShows = (int) $pdo->query('SELECT COUNT(*) FROM no_shows')->fetchColumn();
$totalComplaints = (int) $pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
$avgRating = $pdo->query('SELECT AVG(overall_rating) FROM general_feedback')->fetchColumn();

$activePage = 'analytics';
$pageTitle = 'Analytics';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics · Admin</title>
<link rel="icon" href="<?= BASE_URL ?>/images/nmims-crest.png" type="image/png">
<?php include __DIR__ . '/../includes/pwa_head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/css/admin.css">
</head>
<body class="app-body">
<div class="app-shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <div class="app-main">
    <?php include __DIR__ . '/../includes/admin_header.php'; ?>
    <main class="app-content">

      <div class="admin-stat-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card"><div class="stat-label">Avg. Reservation Duration</div><div class="stat-value"><?= $avgDuration ? round($avgDuration) : '—' ?><span style="font-size:15px;"> min</span></div></div>
        <div class="stat-card"><div class="stat-label">Total No-Shows</div><div class="stat-value"><?= $totalNoShows ?></div></div>
        <div class="stat-card"><div class="stat-label">Total Complaints</div><div class="stat-value"><?= $totalComplaints ?></div></div>
        <div class="stat-card"><div class="stat-label">Avg. Feedback Rating</div><div class="stat-value"><?= $avgRating ? round($avgRating, 1) : '—' ?><span style="font-size:15px;">/5</span></div></div>
      </div>

      <div class="grid-2 section-gap">
        <div class="chart-card"><h3 class="card-title">Canteen Occupancy by Hour</h3><canvas id="hourChart"></canvas></div>
        <div class="chart-card"><h3 class="card-title">Most Used Benches</h3><canvas id="benchChart"></canvas></div>
      </div>
      <div class="grid-2">
        <div class="chart-card"><h3 class="card-title">No-Shows — Last 7 Days</h3><canvas id="noShowChart"></canvas></div>
        <div class="chart-card"><h3 class="card-title">Complaints by Status</h3><canvas id="complaintChart"></canvas></div>
      </div>

    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
  const analyticsData = {
    hours: <?= json_encode(array_values($byHour)) ?>,
    benchLabels: <?= json_encode(array_map(fn($b) => 'Bench ' . $b['bench_number'], $topBenches)) ?>,
    benchCounts: <?= json_encode(array_map(fn($b) => (int)$b['c'], $topBenches)) ?>,
    noShowLabels: <?= json_encode(array_keys($last7)) ?>,
    noShowCounts: <?= json_encode(array_values($last7)) ?>,
    complaintLabels: <?= json_encode(array_map('statusLabel', array_keys($complaintStatus))) ?>,
    complaintCounts: <?= json_encode(array_values($complaintStatus)) ?>,
  };
</script>
<script src="<?= BASE_URL ?>/js/admin.js"></script>
</body>
</html>
