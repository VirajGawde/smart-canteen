<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$sapId = trim($_POST['sap_id'] ?? '');
$password = $_POST['password'] ?? '';
$result = attemptStudentLogin($sapId, $password);

if (!$result['ok']) {
    flash('login_error', $result['message']);
    flash('login_sap', $sapId);
    if (!empty($result['no_account'])) {
        flash('login_no_account', '1');
    }
    header('Location: ' . BASE_URL . '/index.php#login');
    exit;
}

header('Location: ' . BASE_URL . '/student/dashboard.php');
exit;
