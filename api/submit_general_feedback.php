<?php
/** POST api/submit_general_feedback.php   body: cleanliness_rating, availability_rating, overall_rating, app_rating, comments, csrf */
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
$ratings = [];
foreach (['cleanliness_rating', 'availability_rating', 'overall_rating', 'app_rating'] as $field) {
    $val = (int) ($_POST[$field] ?? 0);
    if ($val < 1 || $val > 5) {
        jsonError('Please rate every category from 1 to 5 stars.');
    }
    $ratings[$field] = $val;
}
$comments = trim($_POST['comments'] ?? '') ?: null;

$stmt = $pdo->prepare(
    'INSERT INTO general_feedback (student_id, cleanliness_rating, availability_rating, overall_rating, app_rating, comments)
     VALUES (?,?,?,?,?,?)'
);
$stmt->execute([
    $studentId,
    $ratings['cleanliness_rating'],
    $ratings['availability_rating'],
    $ratings['overall_rating'],
    $ratings['app_rating'],
    $comments,
]);

jsonSuccess([], 'Thanks for your feedback!');
