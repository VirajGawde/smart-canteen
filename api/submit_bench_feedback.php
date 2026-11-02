<?php
/** POST api/submit_bench_feedback.php   body: feedback_id, response, comments, csrf */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudentLogin();
$pdo = getDB();

if (!verifyCsrf($_POST['csrf'] ?? null)) {
    jsonError('Your session looks stale — please refresh the page and try again.', 419);
}

$studentId = currentStudentId();
$feedbackId = (int) ($_POST['feedback_id'] ?? 0);
$response = $_POST['response'] ?? '';
$comments = trim($_POST['comments'] ?? '');

if (!in_array($response, ['yes', 'no', 'not_sure'], true)) {
    jsonError('Please choose Yes, No, or Not sure.');
}

// The WHERE clause doubles as the ownership + "still pending" check.
$stmt = $pdo->prepare(
    "UPDATE complaint_feedback SET response = ?, comments = ?, status = 'responded', responded_at = NOW()
     WHERE id = ? AND student_id = ? AND status = 'pending'"
);
$stmt->execute([$response, $comments ?: null, $feedbackId, $studentId]);

if ($stmt->rowCount() === 0) {
    jsonError('This feedback request was not found, or you already responded to it.');
}

jsonSuccess([], 'Thanks — your response was recorded.');
