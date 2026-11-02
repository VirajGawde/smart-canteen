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
$stats = getCanteenStats($pdo);
$activeReservation = getActiveReservation($pdo, $student['id']);
$activeBan = getActiveBan($pdo, $student['id']);

$activePage = 'dashboard';
$pageTitle = 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Home · Smart Canteen</title>
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

      <div class="welcome-banner">
        <div>
          <h2>Welcome back, <?= e(explode(' ', $student['name'])[0]) ?> 👋</h2>
          <p>Book your seat before you reach the canteen.</p>
        </div>
        <a href="canteen.php" class="btn btn-white">Reserve a Seat <?= icon('arrow-right') ?></a>
      </div>

      <?php if ($activeBan): ?>
        <div class="restriction-banner">
          <?= icon('shield') ?>
          <div>
            <h3>Booking Restricted</h3>
            <p>Your account is temporarily restricted from making seat reservations due to repeated no-shows. Restriction lifts on <?= formatDateTime($activeBan['end_date']) ?>.</p>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($activeReservation): ?>
        <div class="card reservation-card <?= $activeReservation['status'] === 'occupied' ? 'state-occupied' : '' ?> section-gap">
          <div class="reservation-head">
            <div>
              <div class="badge <?= $activeReservation['status'] === 'occupied' ? 'badge-occupied' : 'badge-reserved' ?>"><?= statusLabel($activeReservation['status']) ?></div>
              <div class="reservation-seat-label">Bench <?= (int)$activeReservation['bench_number'] ?> · Seat <?= (int)$activeReservation['seat_number'] ?></div>
              <div class="reservation-seat-sub"><?= e($activeReservation['location'] ?? '') ?></div>
            </div>
            <?php if ($activeReservation['status'] === 'reserved'): ?>
              <div class="countdown-block">
                <div class="countdown-label">Time Remaining</div>
                <div class="countdown mono" id="dashCountdown" data-expires="<?= (new DateTime($activeReservation['expires_at']))->format(DATE_ATOM) ?>">--:--</div>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($activeReservation['status'] === 'reserved'): ?>
            <div class="reservation-note">Reach the canteen and scan the QR code within 10 minutes to confirm your seat.</div>
            <div class="reservation-actions">
              <a href="scan.php" class="btn btn-primary"><?= icon('camera') ?> Scan QR</a>
              <a href="my_bookings.php" class="btn btn-outline">View Booking</a>
            </div>
          <?php else: ?>
            <div class="reservation-note">Enjoy your meal! Don't forget to vacate the seat once you're done.</div>
            <div class="reservation-actions">
              <a href="my_bookings.php" class="btn btn-danger">Vacate Seat</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label"><span class="stat-dot" style="background:var(--status-available)"></span>Available Seats</div>
          <div class="stat-value"><?= $stats['available'] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label"><span class="stat-dot" style="background:var(--status-reserved)"></span>Reserved Seats</div>
          <div class="stat-value"><?= $stats['reserved'] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label"><span class="stat-dot" style="background:var(--status-occupied)"></span>Occupied Seats</div>
          <div class="stat-value"><?= $stats['occupied'] ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label"><span class="stat-dot" style="background:var(--red-600)"></span>Canteen Occupancy</div>
          <div class="stat-value"><?= $stats['occupancy'] ?>%</div>
        </div>
      </div>

      <div class="quick-actions">
        <a href="canteen.php" class="quick-action"><?= icon('grid') ?><span>Reserve a Seat</span></a>
        <a href="my_bookings.php" class="quick-action"><?= icon('seat') ?><span>My Booking</span></a>
        <a href="scan.php" class="quick-action"><?= icon('qr') ?><span>Scan QR</span></a>
        <a href="my_bookings.php" class="quick-action"><?= icon('logout') ?><span>Vacate Seat</span></a>
        <a href="complaints.php" class="quick-action"><?= icon('flag') ?><span>Complaints</span></a>
        <a href="feedback.php" class="quick-action"><?= icon('star') ?><span>Feedback</span></a>
      </div>

      <div class="grid-2 section-gap">
        <div class="card">
          <div class="card-title">Ready to grab a seat?</div>
          <div class="card-sub">The floor plan shows exactly which benches are free right now — tap one to book it.</div>
          <div style="display:flex; align-items:center; gap:14px; background:var(--status-available-bg); border-radius:var(--radius-md); padding:16px 18px; margin-bottom:16px;">
            <span style="color:var(--status-available);"><?= icon('seat', '', 28) ?></span>
            <div>
              <div style="font-family:var(--font-mono); font-weight:700; font-size:22px; color:var(--status-available); line-height:1;"><?= $stats['available'] ?></div>
              <div style="font-size:12.5px; color:var(--ink-600); margin-top:2px;">seats free across the hall</div>
            </div>
          </div>
          <a href="canteen.php" class="btn btn-primary btn-block">Open Canteen Floor Plan <?= icon('arrow-right') ?></a>
        </div>

        <div class="card">
          <div class="card-title">Canteen Rules</div>
          <div class="card-sub">Keep it fair for everyone.</div>
          <ul class="rules-grid-light" style="grid-template-columns:1fr;">
            <li><span class="n">01</span> One student can reserve only one seat.</li>
            <li><span class="n">02</span> Reservation is valid for 10 minutes.</li>
            <li><span class="n">03</span> Scan the QR code at your assigned bench.</li>
            <li><span class="n">04</span> Unscanned reservations expire automatically.</li>
            <li><span class="n">05</span> Repeated no-shows can lead to a booking ban.</li>
            <li><span class="n">06</span> Vacate your seat after finishing.</li>
          </ul>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
  // Live countdown for the active-reservation card, if present.
  const cd = document.getElementById('dashCountdown');
  if (cd) {
    const expires = new Date(cd.dataset.expires).getTime();
    function tick() {
      const secs = Math.max(0, Math.round((expires - Date.now()) / 1000));
      const m = String(Math.floor(secs / 60)).padStart(2, '0');
      const s = String(secs % 60).padStart(2, '0');
      cd.textContent = `${m}:${s}`;
      cd.classList.toggle('urgent', secs <= 120);
      if (secs <= 0) { clearInterval(timer); setTimeout(() => location.reload(), 1500); }
    }
    tick();
    const timer = setInterval(tick, 1000);
  }
</script>
</body>
</html>
