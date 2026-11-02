<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireStudentLogin();
$pdo = getDB();
$student = currentStudent();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? null)) {
    $email = trim($_POST['email'] ?? '');
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pdo->prepare('UPDATE students SET email = ? WHERE id = ?')->execute([$email ?: null, $student['id']]);
        flash('profile_msg', 'Email updated.');
    } else {
        flash('profile_msg', 'Please enter a valid email address.');
    }
    header('Location: profile.php');
    exit;
}

$student = currentStudent(); // re-fetch in case just updated — currentStudent() caches, so query fresh:
$fresh = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$fresh->execute([$student['id']]);
$student = $fresh->fetch();

$completedCount = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE student_id = ? AND status = 'completed'");
$completedCount->execute([$student['id']]);
$completedCount = (int) $completedCount->fetchColumn();

$activeBan = getActiveBan($pdo, $student['id']);
$msg = flash('profile_msg');

$activePage = 'profile';
$pageTitle = 'Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Profile · Smart Canteen</title>
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

      <div class="grid-2">
        <div class="card">
          <div style="display:flex; align-items:center; gap:16px; margin-bottom:22px;">
            <span class="avatar" style="width:56px; height:56px; font-size:22px;"><?= e(strtoupper(substr($student['name'],0,1))) ?></span>
            <div>
              <div style="font-family:var(--font-display); font-weight:700; font-size:18px; color:var(--navy-900);"><?= e($student['name']) ?></div>
              <div class="mono" style="color:var(--ink-600); font-size:13.5px;">SAP ID: <?= e($student['sap_id']) ?></div>
            </div>
          </div>
          <div class="grid-2" style="margin-bottom:6px;">
            <div class="field"><label>Branch</label><div style="padding:13px 0; font-size:14.5px;"><?= e($student['branch']) ?></div></div>
            <div class="field"><label>Year</label><div style="padding:13px 0; font-size:14.5px;">Year <?= (int)$student['year'] ?></div></div>
          </div>
          <?php if ($msg): ?><div class="field-hint" style="margin-bottom:10px; color:var(--red-700);"><?= e($msg) ?></div><?php endif; ?>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <div class="field">
              <label for="email">Email</label>
              <input type="email" name="email" id="email" value="<?= e($student['email'] ?? '') ?>" placeholder="you@example.edu">
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
        </div>

        <div class="card">
          <div class="card-title">Your Stats</div>
          <div class="card-sub">A quick look at your canteen activity.</div>
          <div style="display:flex; flex-direction:column; gap:14px;">
            <div style="display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--slate-100);">
              <span style="color:var(--ink-600); font-size:14px;">Completed visits</span>
              <strong class="mono"><?= $completedCount ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--slate-100);">
              <span style="color:var(--ink-600); font-size:14px;">Recorded no-shows</span>
              <strong class="mono"><?= (int)$student['no_show_count'] ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding:12px 0;">
              <span style="color:var(--ink-600); font-size:14px;">Booking status</span>
              <?php if ($activeBan): ?>
                <span class="badge badge-occupied">Restricted until <?= date('d M', strtotime($activeBan['end_date'])) ?></span>
              <?php else: ?>
                <span class="badge badge-available">Clear to book</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
