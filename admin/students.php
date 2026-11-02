<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireAdminLogin();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_student') {
        $result = registerStudent(
            trim($_POST['sap_id'] ?? ''),
            $_POST['password'] ?? '',
            trim($_POST['name'] ?? ''),
            trim($_POST['branch'] ?? ''),
            (int) ($_POST['year'] ?? 0),
            trim($_POST['email'] ?? '') ?: null
        );
        flash('student_msg', $result['ok'] ? "Student account created for " . trim($_POST['name'] ?? '') . "." : $result['message']);
    }

    if ($action === 'lift_ban') {
        $banId = (int) ($_POST['ban_id'] ?? 0);
        $pdo->prepare('UPDATE bans SET lifted_at = NOW() WHERE id = ?')->execute([$banId]);
        flash('student_msg', 'Ban lifted.');
    }

    if ($action === 'manual_ban') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $days = max(1, (int) ($_POST['days'] ?? 3));
        $reason = trim($_POST['reason'] ?? '') ?: 'Manual restriction by admin';
        if (!getActiveBan($pdo, $studentId)) {
            $pdo->prepare('INSERT INTO bans (student_id, reason, start_date, end_date) VALUES (?,?,NOW(), NOW() + INTERVAL ? DAY)')
                ->execute([$studentId, $reason, $days]);
            createNotification($pdo, $studentId, 'ban', 'Your account is temporarily restricted from making seat reservations due to repeated no-shows.');
            flash('student_msg', 'Student restricted.');
        } else {
            flash('student_msg', 'This student already has an active restriction.');
        }
    }

    header('Location: students.php' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT s.*, b.id AS ban_id, b.end_date AS ban_end
        FROM students s
        LEFT JOIN bans b ON b.student_id = s.id AND b.lifted_at IS NULL AND b.end_date > NOW()";
$params = [];
if ($q !== '') {
    $sql .= ' WHERE s.name LIKE ? OR s.sap_id LIKE ?';
    $params = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY s.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$msg = flash('student_msg');
$activePage = 'students';
$pageTitle = 'Students & Bans';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Students & Bans · Admin</title>
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
        <div class="card-title">Add Student Account</div>
        <div class="card-sub">Students can also create their own account from the login page — use this for manual/bulk setup instead.</div>
        <form method="POST" class="form-grid-2">
          <input type="hidden" name="action" value="add_student">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <div class="field"><label>SAP ID</label><input type="text" name="sap_id" class="mono" maxlength="11" placeholder="11 digits" required></div>
          <div class="field"><label>Full Name</label><input type="text" name="name" required></div>
          <div class="field"><label>Branch</label><input type="text" name="branch" required></div>
          <div class="field">
            <label>Year</label>
            <select name="year" required><option value="1">Year 1</option><option value="2">Year 2</option><option value="3">Year 3</option><option value="4">Year 4</option></select>
          </div>
          <div class="field"><label>Temporary Password</label><input type="text" name="password" placeholder="at least 6 characters" required></div>
          <div class="field"><label>Email (optional)</label><input type="email" name="email"></div>
          <button type="submit" class="btn btn-primary" style="grid-column:1/-1;"><?= icon('plus') ?> Create Account</button>
        </form>
      </div>

      <div class="card">
        <div class="card-title">All Students</div>
        <form method="GET" style="margin-bottom:18px;">
          <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name or SAP ID…" style="width:100%; max-width:320px; padding:11px 14px; border-radius:10px; border:1.5px solid var(--slate-200);">
        </form>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>SAP ID</th><th>Name</th><th>Branch</th><th>Year</th><th>No-shows</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($students as $s): ?>
                <tr>
                  <td class="mono"><?= e($s['sap_id']) ?></td>
                  <td><?= e($s['name']) ?></td>
                  <td><?= e($s['branch']) ?></td>
                  <td><?= (int)$s['year'] ?></td>
                  <td><?= (int)$s['no_show_count'] ?></td>
                  <td>
                    <?php if ($s['ban_id']): ?>
                      <span class="badge badge-occupied">Restricted till <?= date('d M', strtotime($s['ban_end'])) ?></span>
                    <?php else: ?>
                      <span class="badge badge-available">Clear</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($s['ban_id']): ?>
                      <form method="POST"><input type="hidden" name="action" value="lift_ban"><input type="hidden" name="ban_id" value="<?= $s['ban_id'] ?>"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><button type="submit" class="btn btn-outline btn-sm">Lift Ban</button></form>
                    <?php else: ?>
                      <button type="button" class="btn btn-outline btn-sm ban-btn" data-student="<?= $s['id'] ?>" data-name="<?= e($s['name']) ?>">Restrict</button>
                    <?php endif; ?>
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

<div class="modal-overlay" id="banModalOverlay">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header"><h3>Restrict <span id="banStudentName"></span></h3><button class="modal-close" data-close-modal><?= icon('close') ?></button></div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="action" value="manual_ban">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="student_id" id="banStudentId">
        <div class="field"><label>Duration (days)</label><input type="number" name="days" min="1" value="3" required></div>
        <div class="field"><label>Reason</label><input type="text" name="reason" placeholder="Manual restriction by admin"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" data-close-modal>Cancel</button><button type="submit" class="btn btn-danger">Apply Restriction</button></div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
  document.querySelectorAll('.ban-btn').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('banStudentId').value = btn.dataset.student;
    document.getElementById('banStudentName').textContent = btn.dataset.name;
    openModal('banModalOverlay');
  }));
</script>
</body>
</html>
