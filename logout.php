<?php
require_once __DIR__ . '/includes/config.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path']);
}
session_destroy();

header('Location: ' . BASE_URL . '/index.php');
exit;
