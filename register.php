<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

if (isStudentLoggedIn()) {
    header('Location: ' . BASE_URL . '/student/dashboard.php');
    exit;
}

$error = null;
$old = ['sap_id' => '', 'name' => '', 'branch' => '', 'year' => '2', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'sap_id' => trim($_POST['sap_id'] ?? ''),
        'name'   => trim($_POST['name'] ?? ''),
        'branch' => trim($_POST['branch'] ?? ''),
        'year'   => $_POST['year'] ?? '2',
        'email'  => trim($_POST['email'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $result = registerStudent($old['sap_id'], $password, $old['name'], $old['branch'], (int) $old['year'], $old['email']);
        if ($result['ok']) {
            // Auto-login right after creating the account — no need to make them log in twice.
            attemptStudentLogin($old['sap_id'], $password);
            header('Location: ' . BASE_URL . '/student/dashboard.php');
            exit;
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account · Smart Canteen</title>
<link rel="icon" href="images/nmims-crest.png" type="image/png">
<?php include __DIR__ . '/includes/pwa_head.php'; ?>
<link rel="stylesheet" href="css/style.css">
</head>
<body style="background:radial-gradient(120% 140% at 15% 0%, #15355C 0%, #0A1F38 60%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;">
  <div class="login-card" style="margin-top:0; max-width:440px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:18px;">
      <img src="images/nmims-crest.png" width="32" height="32" alt="">
      <strong style="font-family:var(--font-display); color:var(--navy-900);">Smart Canteen</strong>
    </div>
    <h2>Create Your Account</h2>
    <p class="sub">Set up your SAP ID and a password to start reserving seats.</p>

    <?php if ($error): ?>
      <div class="field-error" style="display:block; background:var(--status-occupied-bg); padding:10px 14px; border-radius:10px; margin-bottom:16px;"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="registerForm" novalidate>
      <div class="field" id="sapField">
        <label for="sap_id">SAP ID</label>
        <input type="text" inputmode="numeric" autocomplete="off" id="sap_id" name="sap_id"
               class="mono" placeholder="11-digit SAP ID" maxlength="11" value="<?= e($old['sap_id']) ?>">
        <div class="field-error" id="sapError">SAP ID must contain exactly 11 digits.</div>
      </div>

      <div class="field">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" required value="<?= e($old['name']) ?>">
      </div>

      <div class="form-grid-2">
        <div class="field">
          <label for="branch">Branch</label>
          <input type="text" id="branch" name="branch" required placeholder="e.g. Computer Engineering" value="<?= e($old['branch']) ?>">
        </div>
        <div class="field">
          <label for="year">Year</label>
          <select id="year" name="year">
            <?php foreach ([1,2,3,4] as $y): ?>
              <option value="<?= $y ?>" <?= $old['year'] == $y ? 'selected' : '' ?>>Year <?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="email">Email (optional)</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>">
      </div>

      <div class="field" id="pwField">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="new-password">
        <div class="field-error" id="pwError">Password must be at least 6 characters.</div>
      </div>

      <div class="field" id="confirmField">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
        <div class="field-error" id="confirmError">Passwords do not match.</div>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Create Account <?= icon('arrow-right') ?></button>
    </form>

    <p style="text-align:center; margin-top:18px;">
      <a href="index.php" style="color:var(--ink-600); font-size:13.5px;">Already have an account? Log in</a>
    </p>
  </div>

<script>
  const form = document.getElementById('registerForm');
  const sapInput = document.getElementById('sap_id');
  const sapField = document.getElementById('sapField');
  const pwInput = document.getElementById('password');
  const pwField = document.getElementById('pwField');
  const confirmInput = document.getElementById('confirm_password');
  const confirmField = document.getElementById('confirmField');

  sapInput.addEventListener('input', () => {
    sapInput.value = sapInput.value.replace(/\D/g, '').slice(0, 11);
    sapField.classList.remove('has-error');
  });

  form.addEventListener('submit', (e) => {
    let ok = true;
    if (!/^[0-9]{11}$/.test(sapInput.value.trim())) { sapField.classList.add('has-error'); ok = false; }
    else sapField.classList.remove('has-error');

    if (pwInput.value.length < 6) { pwField.classList.add('has-error'); ok = false; }
    else pwField.classList.remove('has-error');

    if (confirmInput.value !== pwInput.value) { confirmField.classList.add('has-error'); ok = false; }
    else confirmField.classList.remove('has-error');

    if (!ok) e.preventDefault();
  });
</script>
</body>
</html>
