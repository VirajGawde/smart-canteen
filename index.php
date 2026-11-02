<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/floorplan.php';

if (isStudentLoggedIn()) {
    header('Location: ' . BASE_URL . '/student/dashboard.php');
    exit;
}

$loginError = flash('login_error');
$loginNoAccount = flash('login_no_account');
$prefillSap = flash('login_sap') ?? '';

// Live bench data for the public floor-plan preview below. The page already
// connects to the database for the login check above, so this reuses that
// connection instead of showing a sample illustration. The map itself is drawn
// by includes/floorplan.php — the same renderer the booking page uses.
$pdo = getDB();
expireStaleReservations($pdo);
$previewBenches = floorplanFetchBenches($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Canteen · <?= e(COLLEGE_NAME) ?></title>
<meta name="description" content="Fair, real-time canteen seat reservations for MPSTME students.">
<link rel="icon" href="images/nmims-crest.png" type="image/png">
<?php include __DIR__ . '/includes/pwa_head.php'; ?>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/floorplan.css">
</head>
<body>

<nav class="landing-nav">
  <div class="container">
    <div class="brand"><img class="brand-mark" src="images/nmims-crest.png" alt=""> Smart Canteen</div>
    <div class="landing-nav-links">
      <a href="#how-it-works">How it works</a>
      <a href="#preview">Canteen Preview</a>
      <a href="#rules">Rules</a>
      <a href="admin/login.php">Admin</a>
    </div>
  </div>
</nav>

<section class="hero" id="login">
  <div class="container">
    <div>
      <div class="eyebrow"><img class="eyebrow-mark" src="images/nmims-crest.png" alt=""> <?= e(COLLEGE_NAME) ?></div>
      <h1>Smart Canteen</h1>
      <p class="subtitle">Fair Seats. Less Crowd. Better Breaks.</p>
      <p class="lede">Reserve one seat before you even leave class, reach the canteen in 10 minutes, scan the bench's QR code, and it's yours — no more circling the hall during break with a full tray.</p>
      <div class="hero-stats">
        <div><strong class="mono">4</strong><span>seats per bench</span></div>
        <div><strong class="mono">10:00</strong><span>minutes to arrive</span></div>
        <div><strong class="mono">1</strong><span>active seat per student</span></div>
      </div>
    </div>
    <div>
      <div class="login-card hero-login-card">
        <h2>Log in with your SAP ID</h2>
        <p class="sub">Enter your SAP ID and password.</p>

        <?php if ($loginError): ?>
          <div class="field-error" style="display:block; background:var(--status-occupied-bg); padding:10px 14px; border-radius:10px; margin-bottom:16px;">
            <?= e($loginError) ?>
            <?php if ($loginNoAccount): ?> <a href="register.php" style="color:var(--red-700); font-weight:700;">Create one &rarr;</a><?php endif; ?>
          </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm" novalidate>
          <div class="field" id="sapField">
            <label for="sap_id">SAP ID</label>
            <input type="text" inputmode="numeric" autocomplete="off" id="sap_id" name="sap_id"
                   class="mono" placeholder="e.g. 70012345671" maxlength="11"
                   value="<?= e($prefillSap) ?>">
            <div class="field-error" id="sapError">SAP ID must contain exactly 11 digits.</div>
          </div>
          <div class="field" id="pwField">
            <label for="password">Password</label>
            <input type="password" autocomplete="current-password" id="password" name="password">
            <div class="field-hint">Demo accounts: 70012345671 – 70012345680, password <span class="mono">Student@123</span></div>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Log In <?= icon('arrow-right') ?></button>
        </form>
        <p style="text-align:center; margin-top:16px;">
          <a href="register.php" style="color:var(--red-600); font-weight:600; font-size:13.5px;">New here? Create an account &rarr;</a>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="section section-alt" id="how-it-works">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">How it works</div>
      <h2>From classroom to seat in four steps</h2>
      <p>The whole point is that you commit to a seat before you're standing in a crowded hall holding a tray.</p>
    </div>
    <div class="steps">
      <div class="step-card">
        <div class="step-num">01</div>
        <h3>Reserve a seat</h3>
        <p>Pick an open bench, choose one of its 4 seats, and confirm — before you even leave class.</p>
      </div>
      <div class="step-card">
        <div class="step-num">02</div>
        <h3>Reach in 10 minutes</h3>
        <p>A countdown starts the moment you reserve. Walk over — the seat is held just for you.</p>
      </div>
      <div class="step-card">
        <div class="step-num">03</div>
        <h3>Scan &amp; sit</h3>
        <p>Scan the QR code on your bench. Your seat flips to occupied and it's officially yours.</p>
      </div>
      <div class="step-card">
        <div class="step-num">04</div>
        <h3>Vacate when done</h3>
        <p>One tap frees your seat immediately for the next student in line.</p>
      </div>
    </div>
  </div>
</section>

<section class="section" id="preview">
  <div class="container preview-grid">
    <div class="preview-art">
      <?= renderFloorplan($previewBenches, ['legend' => false]) ?>
    </div>
    <div class="preview-copy">
      <div class="eyebrow">Canteen Preview</div>
      <h2>Check out the seating area before you reserve</h2>
      <p style="color:var(--ink-600); font-size:15.5px;">All <?= count($previewBenches) ?> benches are on the map, and every bench seats 4. It updates live, so what you see is what's actually free right now — not a stale headcount from this morning. A green box is a bench with at least one free seat.</p>
      <div class="legend">
        <div><span class="legend-dot" style="background:var(--status-available)"></span> Available</div>
        <div><span class="legend-dot" style="background:var(--status-reserved)"></span> Reserved</div>
        <div><span class="legend-dot" style="background:var(--status-occupied)"></span> Occupied</div>
        <div><span class="legend-dot" style="background:var(--status-unavailable)"></span> Unavailable</div>
      </div>
      <a href="#login" class="btn btn-primary">Log in to reserve <?= icon('arrow-right') ?></a>
    </div>
  </div>
</section>

<section class="section section-alt" id="rules">
  <div class="container">
    <div class="rules-panel">
      <div class="section-head">
        <div class="eyebrow" style="background:rgba(255,255,255,.08); color:#EEC1BF;">Fair use</div>
        <h2>The rules that keep it fair</h2>
        <p>Six rules. That's the whole system — no fine print.</p>
      </div>
      <ul class="rules-list">
        <li><span class="n">01</span> One student can reserve only one seat at a time.</li>
        <li><span class="n">02</span> A reservation stays valid for 10 minutes.</li>
        <li><span class="n">03</span> You must scan the QR code at your assigned bench.</li>
        <li><span class="n">04</span> Reservations that aren't scanned in time expire automatically.</li>
        <li><span class="n">05</span> Repeated no-shows lead to a temporary booking restriction.</li>
        <li><span class="n">06</span> Vacate your seat as soon as you're done eating.</li>
      </ul>
    </div>
  </div>
</section>

<footer class="landing-footer">
  <div class="container">
    <span>© <?= date('Y') ?> Smart Canteen — an academic prototype for <?= e(COLLEGE_NAME) ?></span>
    <span>Not an official college communication</span>
  </div>
</footer>

<script>
  const sapInput = document.getElementById('sap_id');
  const sapField = document.getElementById('sapField');
  const pwInput = document.getElementById('password');
  const pwField = document.getElementById('pwField');
  const form = document.getElementById('loginForm');

  function sapIsValid(v) { return /^[0-9]{11}$/.test(v); }

  sapInput.addEventListener('input', () => {
    sapInput.value = sapInput.value.replace(/\D/g, '').slice(0, 11);
    sapField.classList.remove('has-error');
  });

  form.addEventListener('submit', (e) => {
    let ok = true;
    if (!sapIsValid(sapInput.value.trim())) { sapField.classList.add('has-error'); ok = false; }
    if (pwInput.value.length === 0) { pwField.classList.add('has-error'); ok = false; } else { pwField.classList.remove('has-error'); }
    if (!ok) { e.preventDefault(); (sapField.classList.contains('has-error') ? sapInput : pwInput).focus(); }
  });
</script>
</body>
</html>
