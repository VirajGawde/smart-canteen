<?php
/**
 * config.php
 * -----------------------------------------------------------
 * Every single page includes this FIRST (before any output),
 * because it starts the session. Change DB_* here if your
 * XAMPP MySQL uses a different user/password/port.
 * -----------------------------------------------------------
 */

// ---- Database (XAMPP defaults: root user, empty password) ----
define('DB_HOST', 'Host Name');
define('DB_NAME', 'Database Name');
define('DB_USER', 'UserName');
define('DB_PASS', 'Password');

// ---- App-wide constants ----
define('APP_NAME', 'Smart Canteen');
define('COLLEGE_NAME', 'Mukesh Patel School of Technology and Management Engineering');

// Base URL of the project as seen by the browser. If you place the
// project folder somewhere other than /smart-canteen under htdocs,
// update this to match (no trailing slash).
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

// Where uploaded complaint images are stored, relative to project root
define('UPLOAD_DIR', __DIR__ . '/../uploads/complaints/');
define('UPLOAD_URL', BASE_URL . '/uploads/complaints/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB

// ---- Sessions ----
// Must happen before ANY output. Cookie is locked to HTTP-only so
// client-side JS can never read the session cookie (XSS mitigation).
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Show errors while learning / demoing. Turn this OFF (0) before
// deploying anywhere real students can see a stack trace.
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Kolkata');
