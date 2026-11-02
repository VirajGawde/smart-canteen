<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireAdminLogin();
$pdo = getDB();
expireStaleReservations($pdo);

// ------------------------------------------------------------
// POST actions (classic form POST + redirect — these are occasional
// admin operations, not the kind of real-time interaction that needs AJAX)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_bench') {
        $benchNumber = (int) ($_POST['bench_number'] ?? 0);
        $location = trim($_POST['location'] ?? '') ?: null;
        if ($benchNumber <= 0) {
            flash('bench_msg', 'Please enter a valid bench number.');
        } else {
            try {
                $pdo->beginTransaction();
                $ins = $pdo->prepare('INSERT INTO benches (bench_number, location, qr_token) VALUES (?,?,?)');
                $ins->execute([$benchNumber, $location, 'SCB-' . bin2hex(random_bytes(8))]);
                $benchId = $pdo->lastInsertId();
                $seatIns = $pdo->prepare('INSERT INTO seats (bench_id, seat_number) VALUES (?,?)');
                for ($i = 1; $i <= 4; $i++) { $seatIns->execute([$benchId, $i]); }
                $pdo->commit();
                flash('bench_msg', "Bench $benchNumber added with 4 seats.");
            } catch (Exception $e) {
                $pdo->rollBack();
                flash('bench_msg', 'Could not add bench — that bench number may already exist.');
            }
        }
    }

    if ($action === 'remove_bench') {
        $benchId = (int) ($_POST['bench_id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM benches WHERE id = ?')->execute([$benchId]);
            flash('bench_msg', 'Bench removed.');
        } catch (Exception $e) {
            // Foreign key RESTRICT is doing its job here: a bench with
            // reservation history can't be silently deleted.
            flash('bench_msg', 'This bench has booking history and cannot be deleted — mark it Unavailable instead.');
        }
    }

    if ($action === 'toggle_bench_status') {
        $benchId = (int) ($_POST['bench_id'] ?? 0);
        $pdo->prepare("UPDATE benches SET status = IF(status='active','unavailable','active') WHERE id = ?")->execute([$benchId]);
        flash('bench_msg', 'Bench status updated.');
    }

    if ($action === 'toggle_seat_status') {
        $seatId = (int) ($_POST['seat_id'] ?? 0);
        // Guard in the WHERE clause: only flip between available <-> unavailable,
        // never touch a seat that's currently reserved/occupied by a student.
        $pdo->prepare("UPDATE seats SET status = IF(status='available','unavailable','available')
                       WHERE id = ? AND status IN ('available','unavailable')")->execute([$seatId]);
        flash('bench_msg', 'Seat status updated.');
    }

    if ($action === 'update_qr_token') {
        $benchId = (int) ($_POST['bench_id'] ?? 0);
        // Strip all whitespace rather than just trimming the ends — a manually
        // typed or pasted code is easy to get a stray space into, and a code
        // that differs only by whitespace is a confusing way to fail a scan.
        $newToken = preg_replace('/\s+/', '', (string) ($_POST['qr_token'] ?? ''));
        if ($newToken === '') {
            flash('bench_msg', 'QR code cannot be empty.');
        } elseif (strlen($newToken) > 64) {
            flash('bench_msg', 'QR code must be 64 characters or fewer.');
        } else {
            try {
                $pdo->prepare('UPDATE benches SET qr_token = ? WHERE id = ?')->execute([$newToken, $benchId]);
                flash('bench_msg', 'QR code updated. Students can now scan or manually enter this new code to confirm this bench.');
            } catch (Exception $e) {
                // UNIQUE constraint on qr_token is doing its job — two benches
                // can never accidentally confirm each other's reservations.
                flash('bench_msg', 'That code is already in use by another bench — please choose a different one.');
            }
        }
    }

    if ($action === 'reset_bench') {
        $benchId = (int) ($_POST['bench_id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $active = $pdo->prepare("SELECT id FROM reservations WHERE bench_id = ? AND status IN ('reserved','occupied') FOR UPDATE");
            $active->execute([$benchId]);
            $ids = array_column($active->fetchAll(), 'id');
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id IN ($in)")->execute($ids);
            }
            $pdo->prepare("UPDATE seats SET status = 'available' WHERE bench_id = ?")->execute([$benchId]);
            $pdo->commit();
            flash('bench_msg', 'Bench reset — all seats are now available.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('bench_msg', 'Could not reset this bench.');
        }
    }

    header('Location: benches.php');
    exit;
}

$benches = $pdo->query(
    "SELECT b.*, GROUP_CONCAT(CONCAT(s.id,':',s.seat_number,':',s.status) ORDER BY s.seat_number SEPARATOR '|') AS seat_data
     FROM benches b JOIN seats s ON s.bench_id = b.id
     GROUP BY b.id ORDER BY b.bench_number"
)->fetchAll();

$msg = flash('bench_msg');
$activePage = 'benches';
$pageTitle = 'Benches & Seats';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Benches & Seats · Admin</title>
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

      <?php if ($msg): ?><div class="field-hint" style="margin-bottom:16px; color:var(--red-700); font-size:14px;"><?= e($msg) ?></div><?php endif; ?>

      <div class="card section-gap">
        <div class="card-title">Add a Bench</div>
        <div class="card-sub">Every new bench automatically gets exactly 4 seats.</div>
        <form method="POST" class="form-grid-2" style="align-items:end;">
          <input type="hidden" name="action" value="add_bench">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <div class="field"><label>Bench Number</label><input type="number" name="bench_number" min="1" required></div>
          <div class="field"><label>Location (optional)</label><input type="text" name="location" placeholder="e.g. Near Entrance"></div>
          <button type="submit" class="btn btn-primary" style="height:48px;"><?= icon('plus') ?> Add Bench</button>
        </form>
        <div class="field-hint" style="margin-top:14px;">Benches 1–84 each have a fixed spot on the canteen floor plan (bench N = spot N), so a free number refills its spot. A higher number is shown under "Additional benches" below the plan.</div>
      </div>

      <div class="card">
        <div class="bench-list-head">
          <div>
            <div class="card-title">All Benches</div>
            <div class="card-sub" id="benchCount" data-total="<?= count($benches) ?>"><?= count($benches) ?> benches · <?= count($benches) * 4 ?> seats total</div>
          </div>
          <div class="field bench-filter">
            <input type="search" id="benchFilter" autocomplete="off" placeholder="Find a bench — 42, front left, closed…" aria-label="Find a bench by number, area or state">
          </div>
        </div>

        <?php foreach ($benches as $b):
          $seats = array_map(fn($s) => array_combine(['id','number','status'], explode(':', $s)), explode('|', $b['seat_data']));
          // Words the "find a bench" box matches against: number, area, open/closed,
          // and any seat state currently in use ("occupied" finds busy benches).
          $words = [(int)$b['bench_number'], $b['location'] ?: '', $b['status'] === 'active' ? 'active' : 'closed'];
          foreach (array_unique(array_column($seats, 'status')) as $st) {
              if (in_array($st, ['reserved', 'occupied'], true)) { $words[] = $st; }
          }
        ?>
          <div class="bench-manage-row" data-search="<?= e(strtolower(implode(' ', $words))) ?>">
            <div class="info">
              <div>
                <div class="mono" style="font-weight:700; color:var(--navy-900);">Bench <?= (int)$b['bench_number'] ?></div>
                <div style="font-size:12px; color:var(--ink-600);"><?= e($b['location'] ?: 'No location set') ?></div>
              </div>
              <div class="seat-pills">
                <?php foreach ($seats as $s):
                  $bg = match($s['status']) { 'available'=>'var(--status-available-bg)', 'reserved'=>'var(--status-reserved-bg)', 'occupied'=>'var(--status-occupied-bg)', default=>'var(--status-unavailable-bg)' };
                  $fg = match($s['status']) { 'available'=>'var(--status-available)', 'reserved'=>'var(--status-reserved)', 'occupied'=>'var(--status-occupied)', default=>'var(--status-unavailable)' };
                ?>
                  <form method="POST" title="Seat <?= $s['number'] ?> — <?= statusLabel($s['status']) ?><?= in_array($s['status'],['available','unavailable']) ? ' (click to toggle)' : '' ?>">
                    <input type="hidden" name="action" value="toggle_seat_status">
                    <input type="hidden" name="seat_id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="seat-pill" style="background:<?= $bg ?>; color:<?= $fg ?>; border:none; <?= in_array($s['status'],['reserved','occupied']) ? 'cursor:not-allowed;' : 'cursor:pointer;' ?>"><?= $s['number'] ?></button>
                  </form>
                <?php endforeach; ?>
              </div>
              <span class="badge <?= $b['status'] === 'active' ? 'badge-available' : 'badge-unavailable' ?>"><?= $b['status'] === 'active' ? 'Active' : 'Closed' ?></span>
            </div>
            <div class="actions">
              <button type="button" class="btn btn-outline btn-sm view-qr-btn" data-bench="<?= (int)$b['bench_number'] ?>" data-bench-id="<?= (int)$b['id'] ?>" data-token="<?= e($b['qr_token']) ?>"><?= icon('qr') ?> QR</button>
              <form method="POST"><input type="hidden" name="action" value="toggle_bench_status"><input type="hidden" name="bench_id" value="<?= $b['id'] ?>"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><button type="submit" class="btn btn-outline btn-sm"><?= $b['status'] === 'active' ? 'Mark Unavailable' : 'Mark Active' ?></button></form>
              <form method="POST" onsubmit="return confirm('Reset all 4 seats on this bench to Available? Any active reservation will be cancelled.');"><input type="hidden" name="action" value="reset_bench"><input type="hidden" name="bench_id" value="<?= $b['id'] ?>"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><button type="submit" class="btn btn-outline btn-sm"><?= icon('refresh') ?></button></form>
              <form method="POST" onsubmit="return confirm('Remove Bench <?= (int)$b['bench_number'] ?>? This only works if it has no booking history.');"><input type="hidden" name="action" value="remove_bench"><input type="hidden" name="bench_id" value="<?= $b['id'] ?>"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><button type="submit" class="btn btn-outline btn-sm" style="color:var(--status-occupied);"><?= icon('trash') ?></button></form>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="empty-state" id="benchFilterEmpty" style="display:none;">No bench matches that search.</div>
      </div>

    </main>
  </div>
</div>

<div class="modal-overlay" id="qrModalOverlay">
  <div class="modal" style="max-width:380px;">
    <div class="modal-header"><h3 id="qrModalTitle">Bench QR</h3><button class="modal-close" data-close-modal><?= icon('close') ?></button></div>
    <div class="modal-body qr-display">
      <canvas id="qrCanvas"></canvas>

      <form method="POST" id="qrEditForm" style="text-align:left; margin-top:16px;">
        <input type="hidden" name="action" value="update_qr_token">
        <input type="hidden" name="bench_id" id="qrBenchIdInput" value="">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <div class="field" style="margin-bottom:10px;">
          <label for="qrTokenInput">QR code value</label>
          <input type="text" name="qr_token" id="qrTokenInput" class="mono" maxlength="64" required>
          <div class="field-hint">This is what a scan (or a manually typed code) must match. Change it to anything memorable, or generate a new random one.</div>
        </div>
        <div style="display:flex; gap:8px;">
          <button type="submit" class="btn btn-primary btn-sm" style="flex:1;">Save Code</button>
          <button type="button" class="btn btn-outline btn-sm" id="regenQrBtn" style="flex:1;">Generate Random</button>
        </div>
      </form>

      <p style="font-size:12px; color:var(--ink-600); margin-top:14px;">No camera handy? Give this code to the student — the <strong>Scan QR</strong> page has an "Enter code manually" option that accepts it directly. Or download the image below and print it onto the physical bench.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-outline btn-block" id="downloadQrBtn" type="button"><?= icon('download') ?> Download PNG</button></div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/js/admin.js"></script>
</body>
</html>
