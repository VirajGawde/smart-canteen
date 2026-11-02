<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireAdminLogin();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? null)) {
    $complaintId = (int) ($_POST['complaint_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $notes = trim($_POST['admin_notes'] ?? '');
    $validStatuses = ['submitted', 'under_review', 'verified', 'rejected', 'resolved'];

    if (in_array($newStatus, $validStatuses, true)) {
        $pdo->prepare('UPDATE complaints SET status = ?, admin_notes = ? WHERE id = ?')
            ->execute([$newStatus, $notes ?: null, $complaintId]);

        $studentStmt = $pdo->prepare('SELECT student_id FROM complaints WHERE id = ?');
        $studentStmt->execute([$complaintId]);
        if ($studentId = $studentStmt->fetchColumn()) {
            createNotification($pdo, $studentId, 'complaint_update', 'Your complaint status has been updated to ' . statusLabel($newStatus) . '.', $complaintId);
        }
        flash('complaint_msg', 'Complaint updated.');
    }
    header('Location: complaints.php' . (!empty($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit;
}

$filter = $_GET['status'] ?? 'all';
$sql = "SELECT c.*, s.name AS complainant, s.sap_id, b.bench_number, seat.seat_number
        FROM complaints c
        JOIN students s ON s.id = c.student_id
        LEFT JOIN benches b ON b.id = c.bench_id
        LEFT JOIN seats seat ON seat.id = c.seat_id";
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE c.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY c.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Feedback responses for every complaint shown, grouped by complaint_id
$feedbackByComplaint = [];
if ($complaints) {
    $ids = array_column($complaints, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $fbStmt = $pdo->prepare(
        "SELECT cf.*, st.name FROM complaint_feedback cf JOIN students st ON st.id = cf.student_id
         WHERE cf.complaint_id IN ($in) ORDER BY cf.requested_at"
    );
    $fbStmt->execute($ids);
    foreach ($fbStmt->fetchAll() as $fb) {
        $feedbackByComplaint[$fb['complaint_id']][] = $fb;
    }
}

$msg = flash('complaint_msg');
$activePage = 'complaints';
$pageTitle = 'Complaints';
$filters = ['all' => 'All', 'submitted' => 'Submitted', 'under_review' => 'Under Review', 'verified' => 'Verified', 'resolved' => 'Resolved', 'rejected' => 'Rejected'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Complaints · Admin</title>
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

      <div class="filter-bar">
        <?php foreach ($filters as $key => $label): ?>
          <a href="?status=<?= $key ?>" class="filter-chip <?= $filter === $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (!$complaints): ?>
        <div class="card empty-state"><?= icon('flag') ?><p>No complaints in this filter.</p></div>
      <?php endif; ?>

      <?php foreach ($complaints as $c):
        $badgeClass = match($c['status']) { 'resolved','verified' => 'badge-available', 'under_review' => 'badge-reserved', 'rejected' => 'badge-occupied', default => 'badge-neutral' };
        $feedback = $feedbackByComplaint[$c['id']] ?? [];
        $yes = count(array_filter($feedback, fn($f) => $f['response'] === 'yes'));
        $no = count(array_filter($feedback, fn($f) => $f['response'] === 'no'));
      ?>
        <div class="card section-gap">
          <div class="complaint-detail-grid">
            <div>
              <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                <div>
                  <span class="badge <?= $badgeClass ?>"><?= statusLabel($c['status']) ?></span>
                  <span style="margin-left:8px; font-size:12.5px; color:var(--ink-600);">#<?= $c['id'] ?> · <?= formatDateTime($c['created_at']) ?></span>
                </div>
              </div>
              <h3 style="font-size:16px; margin-bottom:6px;"><?= e(statusLabel($c['complaint_type'])) ?><?= $c['bench_number'] ? ' — Bench ' . (int)$c['bench_number'] : '' ?><?= $c['seat_number'] ? ', Seat ' . (int)$c['seat_number'] : '' ?></h3>
              <p style="color:var(--ink-900); font-size:14px; margin-bottom:10px;"><?= e($c['description']) ?></p>
              <p style="font-size:12.5px; color:var(--ink-600); margin-bottom:14px;">Filed by <?= e($c['complainant']) ?> (<span class="mono"><?= e($c['sap_id']) ?></span>)</p>
              <?php if ($c['image_path']): ?>
                <img src="<?= UPLOAD_URL . e($c['image_path']) ?>" alt="Complaint attachment" style="max-width:220px; border-radius:10px; border:1px solid var(--slate-200); margin-bottom:14px;">
              <?php endif; ?>

              <?php if ($feedback): ?>
                <div style="background:var(--paper); border-radius:12px; padding:14px 16px; margin-top:10px;">
                  <strong style="font-size:13px;">Bench Feedback (<?= $yes ?> yes · <?= $no ?> no · <?= count($feedback) - $yes - $no ?> pending/unsure)</strong>
                  <?php foreach ($feedback as $fb): ?>
                    <div class="feedback-mini">
                      <strong><?= e($fb['name']) ?>:</strong>
                      <?php if ($fb['status'] === 'pending'): ?>
                        <span style="color:var(--ink-600);">awaiting response</span>
                      <?php else: ?>
                        <span class="badge <?= $fb['response'] === 'yes' ? 'badge-occupied' : ($fb['response'] === 'no' ? 'badge-available' : 'badge-unavailable') ?>" style="margin:0 6px;"><?= ucfirst(str_replace('_',' ',$fb['response'])) ?></span>
                        <?php if ($fb['comments']): ?>— <?= e($fb['comments']) ?><?php endif; ?>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div>
              <form method="POST">
                <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <div class="field">
                  <label>Update Status</label>
                  <select name="status">
                    <?php foreach (['submitted','under_review','verified','rejected','resolved'] as $st): ?>
                      <option value="<?= $st ?>" <?= $c['status'] === $st ? 'selected' : '' ?>><?= statusLabel($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label>Admin Notes</label>
                  <textarea name="admin_notes" rows="3" placeholder="Internal notes / action taken…"><?= e($c['admin_notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Save</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
