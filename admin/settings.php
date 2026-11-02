<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireAdminLogin();
$pdo = getDB();

$fields = [
    'reservation_window_minutes' => ['label' => 'Reservation Window (minutes)', 'hint' => 'How long a student has to scan the QR code before it expires.'],
    'no_show_ban_threshold'      => ['label' => 'No-Shows Before Restriction', 'hint' => 'e.g. 3 means the 1st and 2nd no-show are warnings, the 3rd triggers a restriction.'],
    'ban_duration_days'          => ['label' => 'Restriction Duration (days)', 'hint' => 'How long a booking restriction lasts once triggered.'],
    'no_show_window_days'        => ['label' => 'No-Show Rolling Window (days)', 'hint' => 'No-shows older than this many days no longer count toward a restriction.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? null)) {
    foreach (array_keys($fields) as $key) {
        $val = (int) ($_POST[$key] ?? 0);
        if ($val > 0) {
            setSetting($pdo, $key, (string) $val);
        }
    }
    flash('settings_msg', 'Settings saved.');
    header('Location: settings.php');
    exit;
}

$current = [];
foreach (array_keys($fields) as $key) {
    $current[$key] = getSetting($pdo, $key, '');
}
$msg = flash('settings_msg');
$activePage = 'settings';
$pageTitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings · Admin</title>
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
      <div class="card" style="max-width:560px;">
        <div class="card-title">Reservation & No-Show Policy</div>
        <div class="card-sub">Changes apply to new reservations and future no-show checks immediately.</div>
        <?php if ($msg): ?><div class="field-hint" style="margin-bottom:16px; color:var(--red-700); font-size:14px;"><?= e($msg) ?></div><?php endif; ?>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <?php foreach ($fields as $key => $meta): ?>
            <div class="field">
              <label for="<?= $key ?>"><?= e($meta['label']) ?></label>
              <input type="number" min="1" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($current[$key]) ?>" required>
              <div class="field-hint"><?= e($meta['hint']) ?></div>
            </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
