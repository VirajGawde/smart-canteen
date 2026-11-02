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
$activeReservation = getActiveReservation($pdo, $student['id']);

$activePage = 'canteen';
$pageTitle = 'Scan QR';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Scan QR · Smart Canteen</title>
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

      <?php if (!$activeReservation): ?>
        <div class="card empty-state">
          <?= icon('qr') ?>
          <h3 style="margin:10px 0 6px;">Nothing to scan yet</h3>
          <p style="margin-bottom:18px;">You don't have an active reservation. Reserve a seat first, then come back here once you're at the canteen.</p>
          <a href="canteen.php" class="btn btn-primary">Reserve a Seat</a>
        </div>
      <?php elseif ($activeReservation['status'] === 'occupied'): ?>
        <div class="card empty-state">
          <?= icon('check') ?>
          <h3 style="margin:10px 0 6px;">Already confirmed</h3>
          <p style="margin-bottom:18px;">Bench <?= (int)$activeReservation['bench_number'] ?>, Seat <?= (int)$activeReservation['seat_number'] ?> is already yours — no need to scan again.</p>
          <a href="my_bookings.php" class="btn btn-primary">View My Booking</a>
        </div>
      <?php else: ?>
        <div class="card" style="max-width:520px; margin:0 auto; text-align:center;">
          <div class="card-title">Scan the QR code at Bench <?= (int)$activeReservation['bench_number'] ?></div>
          <div class="card-sub">Point your camera at the code printed on your reserved bench.</div>

          <div class="qr-reader-wrap"><div id="qr-reader"></div></div>
          <div id="qrResultBanner"></div>
          <p style="font-size:12.5px; color:var(--ink-600); margin-top:12px;">
            Camera access requires this page to be served from <span class="mono">localhost</span> or HTTPS.
          </p>

          <div style="text-align:center; color:var(--slate-400); font-size:12px; margin:18px 0 14px; text-transform:uppercase; letter-spacing:.04em;">or enter the code yourself</div>

          <div id="manualEntryBox" style="text-align:left;">
            <div class="field">
              <label for="manualCode">Bench QR code</label>
              <input type="text" id="manualCode" class="mono" placeholder="SCB-xxxxxxxx" autocomplete="off">
              <div class="field-hint">Ask the canteen admin for Bench <?= (int)$activeReservation['bench_number'] ?>'s code if it isn't printed on the bench yet.</div>
            </div>
            <button class="btn btn-primary btn-block" id="manualSubmitBtn" type="button">Submit Code</button>
          </div>
        </div>
      <?php endif; ?>

    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php if ($activeReservation && $activeReservation['status'] === 'reserved'): ?>
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode/html5-qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/js/qr-scan.js"></script>
<?php endif; ?>
</body>
</html>
