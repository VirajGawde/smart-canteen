<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = attemptAdminLogin($username, $password);
    if ($result['ok']) {
        header('Location: dashboard.php');
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login · Smart Canteen</title>
<link rel="icon" href="../images/nmims-crest.png" type="image/png">
<?php include __DIR__ . '/../includes/pwa_head.php'; ?>
<link rel="stylesheet" href="../css/style.css">
</head>
<body style="background:radial-gradient(120% 140% at 15% 0%, #15355C 0%, #0A1F38 60%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;">
  <div class="login-card" style="margin-top:0;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:18px;">
      <img src="../images/nmims-crest.png" width="32" height="32" alt="">
      <strong style="font-family:var(--font-display); color:var(--navy-900);">Smart Canteen — Admin</strong>
    </div>
    <h2>Administrator Login</h2>
    <p class="sub">This is a separate login from student accounts.</p>

    <?php if ($error): ?>
      <div class="field-error" style="display:block; background:var(--status-occupied-bg); padding:10px 14px; border-radius:10px; margin-bottom:16px;"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <div class="field-hint">Demo credentials: admin / Admin@123</div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log In <?= icon('arrow-right') ?></button>
    </form>
    <p style="text-align:center; margin-top:18px;"><a href="../index.php" style="color:var(--ink-600); font-size:13.5px;">&larr; Back to student login</a></p>
  </div>
</body>
</html>
