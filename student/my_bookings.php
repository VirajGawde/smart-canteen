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

$historyStmt = $pdo->prepare(
    "SELECT r.*, s.seat_number, b.bench_number
     FROM reservations r
     JOIN seats s ON s.id = r.seat_id
     JOIN benches b ON b.id = r.bench_id
     WHERE r.student_id = ?
     ORDER BY r.created_at DESC LIMIT 30"
);
$historyStmt->execute([$student['id']]);
$history = $historyStmt->fetchAll();

$activePage = 'my_bookings';
$pageTitle = 'My Bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>My Bookings · Smart Canteen</title>
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
        <div class="card empty-state section-gap">
          <?= icon('seat') ?>
          <h3 style="margin:10px 0 6px;">No active booking</h3>
          <p style="margin-bottom:18px;">You don't have a reserved or occupied seat right now.</p>
          <a href="canteen.php" class="btn btn-primary">Reserve a Seat</a>
        </div>
      <?php else: ?>
        <div class="card reservation-card <?= $activeReservation['status'] === 'occupied' ? 'state-occupied' : '' ?> section-gap" id="activeCard">
          <div class="reservation-head">
            <div>
              <div class="badge <?= $activeReservation['status'] === 'occupied' ? 'badge-occupied' : 'badge-reserved' ?>" id="statusBadge"><?= statusLabel($activeReservation['status']) ?></div>
              <div class="reservation-seat-label">Bench <?= (int)$activeReservation['bench_number'] ?> · Seat <?= (int)$activeReservation['seat_number'] ?></div>
              <div class="reservation-seat-sub"><?= e($activeReservation['location'] ?? '') ?> &middot; booked <?= timeAgo($activeReservation['reserved_at']) ?></div>
            </div>
            <?php if ($activeReservation['status'] === 'reserved'): ?>
              <div class="countdown-block">
                <div class="countdown-label">Time Remaining</div>
                <div class="countdown mono" id="countdown" data-expires="<?= (new DateTime($activeReservation['expires_at']))->format(DATE_ATOM) ?>">--:--</div>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($activeReservation['status'] === 'reserved'): ?>
            <div class="reservation-note">Reach the canteen and scan the QR code within 10 minutes to confirm your seat.</div>
            <div class="reservation-actions">
              <a href="scan.php" class="btn btn-primary"><?= icon('camera') ?> Scan QR</a>
              <button class="btn btn-outline" id="cancelBtn">Cancel Reservation</button>
            </div>
          <?php else: ?>
            <div class="reservation-note">Confirmed at <?= formatDateTime($activeReservation['confirmed_at']) ?>. Enjoy your meal!</div>
            <div class="reservation-actions">
              <button class="btn btn-danger" id="vacateBtn"><?= icon('logout') ?> Vacate Seat</button>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-title">Booking History</div>
        <div class="card-sub">Your last <?= count($history) ?> bookings.</div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>Date</th><th>Bench</th><th>Seat</th><th>Booking Time</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if (!$history): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--ink-600);">No bookings yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= date('d M Y', strtotime($h['created_at'])) ?></td>
                  <td>Bench <?= (int)$h['bench_number'] ?></td>
                  <td>Seat <?= (int)$h['seat_number'] ?></td>
                  <td><?= date('h:i A', strtotime($h['reserved_at'])) ?></td>
                  <td>
                    <?php
                      $badgeClass = match($h['status']) {
                        'completed' => 'badge-available',
                        'occupied' => 'badge-occupied',
                        'reserved' => 'badge-reserved',
                        'expired' => 'badge-occupied',
                        'cancelled' => 'badge-unavailable',
                        default => 'badge-neutral',
                      };
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= statusLabel($h['status']) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Vacate confirmation modal -->
<div class="modal-overlay" id="vacateModalOverlay">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><h3>Vacate Seat</h3><button class="modal-close" data-close-modal><?= icon('close') ?></button></div>
    <div class="modal-body"><p style="margin:0;">Are you sure you have finished using this seat?</p></div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-close-modal>Cancel</button>
      <button class="btn btn-danger" id="confirmVacateBtn">Vacate Seat</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
  const cd = document.getElementById('countdown');
  if (cd) {
    const expires = new Date(cd.dataset.expires).getTime();
    function tick() {
      const secs = Math.max(0, Math.round((expires - Date.now()) / 1000));
      const m = String(Math.floor(secs / 60)).padStart(2, '0');
      const s = String(secs % 60).padStart(2, '0');
      cd.textContent = `${m}:${s}`;
      cd.classList.toggle('urgent', secs <= 120);
      if (secs <= 0) { clearInterval(timer); toast('Your reservation has expired.', 'error'); setTimeout(() => location.reload(), 1500); }
    }
    tick();
    const timer = setInterval(tick, 1000);
  }

  document.getElementById('cancelBtn')?.addEventListener('click', async () => {
    const data = await api('api/cancel_reservation.php', { method: 'POST', body: { csrf: csrfToken() } });
    if (data.ok) { toast('Reservation cancelled.', 'success'); setTimeout(() => location.reload(), 800); }
    else toast(data.message, 'error');
  });

  document.getElementById('vacateBtn')?.addEventListener('click', () => openModal('vacateModalOverlay'));
  document.getElementById('confirmVacateBtn')?.addEventListener('click', async () => {
    const data = await api('api/vacate_seat.php', { method: 'POST', body: { csrf: csrfToken() } });
    if (data.ok) { toast('Seat vacated successfully.', 'success'); setTimeout(() => location.reload(), 800); }
    else { toast(data.message, 'error'); closeModal('vacateModalOverlay'); }
  });

  // Poll in case the reservation state changes elsewhere (another tab scans the QR, or it expires).
  setInterval(async () => {
    if (document.hidden) return;
    const data = await api('api/check_status.php');
    if (data.ok && !document.getElementById('countdown') && data.reservation) location.reload();
  }, 45000);
</script>
</body>
</html>
