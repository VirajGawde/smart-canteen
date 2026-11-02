<?php
/** POST api/mark_notifications_read.php   body: csrf */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudentLogin();
$pdo = getDB();

if (!verifyCsrf($_POST['csrf'] ?? null)) {
    jsonError('Your session looks stale — please refresh the page and try again.', 419);
}

$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE student_id = ?')->execute([currentStudentId()]);
jsonSuccess([], 'All notifications marked as read.');
