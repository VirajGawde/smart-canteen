<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/icons.php';

requireStudentLogin();
$pdo = getDB();
$student = currentStudent();

$recentStmt = $pdo->prepare('SELECT * FROM general_feedback WHERE student_id = ? ORDER BY created_at DESC LIMIT 5');
$recentStmt->execute([$student['id']]);
$recent = $recentStmt->fetchAll();

$activePage = 'feedback';
$pageTitle = 'Feedback';

$categories = [
    'cleanliness_rating'  => 'Canteen Cleanliness',
    'availability_rating' => 'Seating Availability',
    'overall_rating'      => 'Overall Experience',
    'app_rating'          => 'App Experience',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrfToken()) ?>">
<title>Feedback · Smart Canteen</title>
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

      <div class="grid-2">
        <div class="card">
          <div class="card-title">Rate Your Experience</div>
          <div class="card-sub">Help us improve Smart Canteen.</div>
          <form id="feedbackForm">
            <?php foreach ($categories as $key => $label): ?>
              <div class="field">
                <label><?= e($label) ?></label>
                <div class="star-rating" data-field="<?= $key ?>">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" data-value="<?= $i ?>"><?= icon('star') ?></button>
                  <?php endfor; ?>
                </div>
                <input type="hidden" name="<?= $key ?>" value="0">
              </div>
            <?php endforeach; ?>
            <div class="field">
              <label for="comments">Comments (optional)</label>
              <textarea name="comments" id="comments" rows="3" placeholder="Anything you'd like us to know?"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="submitFeedbackBtn">Submit Feedback</button>
          </form>
        </div>

        <div class="card">
          <div class="card-title">Your Recent Feedback</div>
          <div class="card-sub">Your last <?= count($recent) ?> submissions.</div>
          <?php if (!$recent): ?>
            <div class="empty-state"><?= icon('star') ?><p>No feedback submitted yet.</p></div>
          <?php else: foreach ($recent as $r): ?>
            <div style="padding:14px 0; border-bottom:1px solid var(--slate-100);">
              <div style="font-size:12.5px; color:var(--ink-600); margin-bottom:6px;"><?= formatDateTime($r['created_at']) ?></div>
              <div style="display:flex; gap:16px; flex-wrap:wrap; font-size:13px; margin-bottom:6px;">
                <span>Cleanliness: <strong><?= (int)$r['cleanliness_rating'] ?>/5</strong></span>
                <span>Availability: <strong><?= (int)$r['availability_rating'] ?>/5</strong></span>
                <span>Overall: <strong><?= (int)$r['overall_rating'] ?>/5</strong></span>
                <span>App: <strong><?= (int)$r['app_rating'] ?>/5</strong></span>
              </div>
              <?php if ($r['comments']): ?><p style="margin:0; font-size:13.5px; color:var(--ink-900);"><?= e($r['comments']) ?></p><?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </main>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
  document.querySelectorAll('.star-rating').forEach((group) => {
    const input = group.nextElementSibling;
    group.querySelectorAll('button').forEach((btn) => {
      btn.addEventListener('click', () => {
        const value = parseInt(btn.dataset.value, 10);
        input.value = value;
        group.querySelectorAll('button').forEach((b) => b.classList.toggle('filled', parseInt(b.dataset.value, 10) <= value));
      });
    });
  });

  document.getElementById('feedbackForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const payload = Object.fromEntries(formData.entries());
    payload.csrf = csrfToken();

    for (const key of ['cleanliness_rating','availability_rating','overall_rating','app_rating']) {
      if (!payload[key] || payload[key] === '0') { toast('Please rate every category before submitting.', 'error'); return; }
    }

    const btn = document.getElementById('submitFeedbackBtn');
    btn.disabled = true; btn.textContent = 'Submitting…';
    const data = await api('api/submit_general_feedback.php', { method: 'POST', body: payload });
    if (data.ok) { toast('Thanks for your feedback!', 'success'); setTimeout(() => location.reload(), 800); }
    else { toast(data.message || 'Could not submit feedback.', 'error'); btn.disabled = false; btn.textContent = 'Submit Feedback'; }
  });
</script>
</body>
</html>
