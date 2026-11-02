<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireAdminLogin();
$pdo = getDB();
expireStaleReservations($pdo);

$totalStudents = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$stats = getCanteenStats($pdo);
$todayNoShows = (int) $pdo->query("SELECT COUNT(*) FROM no_shows WHERE DATE(occurred_at) = CURDATE()")->fetchColumn();
$activeBans = (int) $pdo->query("SELECT COUNT(*) FROM bans WHERE lifted_at IS NULL AND end_date > NOW()")->fetchColumn();
$openComplaints = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('submitted','under_review')")->fetchColumn();

$recentComplaints = $pdo->query(
    "SELECT c.id, c.complaint_type, c.status, c.created_at, s.name, b.bench_number
     FROM complaints c JOIN students s ON s.id = c.student_id LEFT JOIN benches b ON b.id = c.bench_id
     ORDER BY c.created_at DESC LIMIT 5"
)->fetchAll();

$recentActivity = $pdo->query(
    "SELECT r.status, r.reserved_at, r.confirmed_at, r.vacated_at, st.name, b.bench_number, s.seat_number
     FROM reservations r JOIN students st ON st.id = r.student_id
     JOIN benches b ON b.id = r.bench_id JOIN seats s ON s.id = r.seat_id
     ORDER BY r.id DESC LIMIT 8"
)->fetchAll();

$activePage = 'dashboard';
$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Admin Dashboard · Smart Canteen</title>
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

      <div class="admin-stat-grid">
        <div class="stat-card"><div class="stat-label"><?= icon('user','',15) ?> Total Students</div><div class="stat-value"><?= $totalStudents ?></div></div>
        <div class="stat-card"><div class="stat-label"><span class="stat-dot" style="background:var(--status-reserved)"></span>Active Reservations</div><div class="stat-value"><?= $stats['reserved'] ?></div></div>
        <div class="stat-card"><div class="stat-label"><span class="stat-dot" style="background:var(--status-occupied)"></span>Occupied Seats</div><div class="stat-value"><?= $stats['occupied'] ?></div></div>
        <div class="stat-card"><div class="stat-label"><span class="stat-dot" style="background:var(--status-available)"></span>Available Seats</div><div class="stat-value"><?= $stats['available'] ?></div></div>
        <div class="stat-card"><div class="stat-label"><?= icon('clock','',15) ?> Today's No-Shows</div><div class="stat-value"><?= $todayNoShows ?></div></div>
        <div class="stat-card"><div class="stat-label"><?= icon('shield','',15) ?> Active Bans</div><div class="stat-value"><?= $activeBans ?></div></div>
        <div class="stat-card"><div class="stat-label"><?= icon('flag','',15) ?> Open Complaints</div><div class="stat-value"><?= $openComplaints ?></div></div>
        <div class="stat-card"><div class="stat-label"><?= icon('chart','',15) ?> Occupancy</div><div class="stat-value"><?= $stats['occupancy'] ?>%</div></div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-title">Recent Complaints</div>
          <div class="card-sub">Latest 5 — see full list under Complaints.</div>
          <?php if (!$recentComplaints): ?>
            <div class="empty-state" style="padding:24px;"><?= icon('flag') ?><p>No complaints yet.</p></div>
          <?php else: foreach ($recentComplaints as $c):
            $badgeClass = match($c['status']) { 'resolved','verified' => 'badge-available', 'under_review' => 'badge-reserved', 'rejected' => 'badge-occupied', default => 'badge-neutral' };
          ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--slate-100);">
              <div>
                <div style="font-size:13.5px; font-weight:600; color:var(--navy-900);"><?= e(statusLabel($c['complaint_type'])) ?><?= $c['bench_number'] ? ' — Bench '.(int)$c['bench_number'] : '' ?></div>
                <div style="font-size:12px; color:var(--ink-600);">by <?= e($c['name']) ?> · <?= timeAgo($c['created_at']) ?></div>
              </div>
              <span class="badge <?= $badgeClass ?>"><?= statusLabel($c['status']) ?></span>
            </div>
          <?php endforeach; endif; ?>
          <a href="complaints.php" class="btn btn-outline btn-block" style="margin-top:16px;">Review Complaints</a>
        </div>

        <div class="card">
          <div class="card-title">Recent Activity</div>
          <div class="card-sub">Latest reservations across the canteen.</div>
          <?php foreach ($recentActivity as $a):
            $badgeClass = match($a['status']) { 'occupied' => 'badge-occupied', 'reserved' => 'badge-reserved', 'completed' => 'badge-available', 'expired' => 'badge-occupied', default => 'badge-unavailable' };
          ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:11px 0; border-bottom:1px solid var(--slate-100); font-size:13px;">
              <div><strong><?= e($a['name']) ?></strong> — Bench <?= (int)$a['bench_number'] ?>, Seat <?= (int)$a['seat_number'] ?></div>
              <span class="badge <?= $badgeClass ?>"><?= statusLabel($a['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
