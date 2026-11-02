<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/floorplan.php';

requireStudentLogin();
$pdo = getDB();
expireStaleReservations($pdo);

$student = currentStudent();
$activeReservation = getActiveReservation($pdo, $student['id']);
$activeBan = getActiveBan($pdo, $student['id']);

// Every bench with its four seat states, in one query. The map itself (84
// benches in their areas) is drawn by includes/floorplan.php — the same
// renderer the public landing page uses, just clickable here.
$benches = floorplanFetchBenches($pdo);

// Banned, or already holding a seat: the map is look-only, and the banner
// above it says why. Otherwise every bench with a free seat is clickable.
$mapLocked = (bool)($activeBan || $activeReservation);

// Seats still free on benches that are open — the headline number.
$seatsTotal = 0;
$seatsFree = 0;
foreach ($benches as $b) {
    $seatsTotal += count($b['seats']);
    if ($b['status'] === 'active') {
        $seatsFree += count(array_keys($b['seats'], 'available', true));
    }
}

$activePage = 'canteen';
$pageTitle = 'Canteen Seat';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Canteen Seat · Smart Canteen</title>
<link rel="icon" href="<?= BASE_URL ?>/images/nmims-crest.png" type="image/png">
<?php include __DIR__ . '/../includes/pwa_head.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/css/floorplan.css">
</head>
<body class="app-body photo-bg">
<div class="app-shell">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="app-main">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main class="app-content">

      <?php if ($activeBan): ?>
        <div class="restriction-banner">
          <?= icon('shield') ?>
          <div>
            <h3>Booking Restricted</h3>
            <p>You can browse the canteen map, but reservations are disabled until <?= formatDateTime($activeBan['end_date']) ?> due to repeated no-shows.</p>
          </div>
        </div>
      <?php elseif ($activeReservation): ?>
        <div class="restriction-banner" style="background:var(--red-50); border-color:var(--red-600);">
          <span style="color:var(--red-600);"><?= icon('seat') ?></span>
          <div>
            <h3 style="color:var(--navy-900);">You already have an active seat</h3>
            <p style="color:var(--ink-600);">Bench <?= (int)$activeReservation['bench_number'] ?>, Seat <?= (int)$activeReservation['seat_number'] ?> — head to <a href="my_bookings.php" style="color:var(--red-600); font-weight:600;">My Booking</a> to manage it. You can only hold one seat at a time.</p>
          </div>
        </div>
      <?php endif; ?>

      <div class="card" id="benchGrid">
        <div class="map-head">
          <div>
            <div class="card-title">Canteen Floor Plan</div>
            <div class="card-sub">
              <?= $mapLocked
                  ? 'Look-only right now — the map still shows what is free.'
                  : 'Tap a green bench to choose one of its 4 seats.' ?>
              <strong class="mono"><?= $seatsFree ?></strong> of <span class="mono"><?= $seatsTotal ?></span> seats free across <?= count($benches) ?> benches.
            </div>
          </div>
          <button class="icon-btn" id="refreshMapBtn" title="Refresh map" aria-label="Refresh map"><?= icon('refresh') ?></button>
        </div>

        <?= renderFloorplan($benches, ['interactive' => true, 'locked' => $mapLocked]) ?>
      </div>

    </main>
  </div>
</div>

<!-- Seat selection modal -->
<div class="modal-overlay" id="seatModalOverlay">
  <div class="modal">
    <div class="modal-header">
      <h3 id="seatModalTitle">Bench — Select Your Seat</h3>
      <button class="modal-close" data-close-modal><?= icon('close') ?></button>
    </div>
    <div class="modal-body">
      <div class="seat-grid" id="seatGrid">
        <div class="empty-state" style="grid-column:1/-1;">Loading seats…</div>
      </div>
      <div class="selection-summary" id="selectionSummary" style="display:none;">
        <span>Your pick: <strong id="selectionLabel"></strong></span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-close-modal>Cancel</button>
      <button class="btn btn-primary" id="confirmReservationBtn" disabled>Confirm Reservation</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= BASE_URL ?>/js/canteen.js"></script>
</body>
</html>
