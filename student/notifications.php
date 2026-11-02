<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireStudentLogin();
$pdo = getDB();
expireStaleReservations($pdo);
$student = currentStudent();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$student['id']]);
$notifications = $stmt->fetchAll();

// Mark everything as read now that the student has opened this page —
// but we already captured which ones WERE unread above, so the page
// can still visually distinguish them for this one view (Gmail-style).
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE student_id = ?')->execute([$student['id']]);

$iconMap = [
    'confirmed' => 'seat', 'occupied' => 'check', 'expired' => 'clock', 'warning' => 'shield',
    'ban' => 'shield', 'complaint_update' => 'flag', 'feedback_request' => 'flag',
];

$activePage = 'notifications';
$pageTitle = 'Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Notifications · Smart Canteen</title>
<link rel="icon" href="<?= BASE_URL ?>/images/nmims-crest.png" type="image/png">
<?php include __DIR__ . '/../includes/pwa_head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
</head>
<body class="app-body photo-bg">
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="app-main">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main class="app-content">
      <div class="card">
        <div class="card-title">All Notifications</div>
        <div class="card-sub">Your last <?= count($notifications) ?> updates.</div>
        <?php if (!$notifications): ?>
          <div class="empty-state"><?= icon('bell') ?><p>You're all caught up.</p></div>
        <?php else: ?>
          <div>
            <?php foreach ($notifications as $n): ?>
              <div style="display:flex; gap:14px; padding:16px 4px; border-bottom:1px solid var(--slate-100); <?= $n['is_read'] ? '' : 'background:var(--red-50);' ?>">
                <span style="color:var(--red-600); flex-shrink:0;"><?= icon($iconMap[$n['type']] ?? 'bell') ?></span>
                <div style="flex:1;">
                  <p style="margin:0 0 4px; font-size:14.5px; color:var(--ink-900);"><?= e($n['message']) ?></p>
                  <span style="font-size:12px; color:var(--ink-600);"><?= formatDateTime($n['created_at']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
