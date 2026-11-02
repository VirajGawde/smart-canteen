<?php
/**
 * db.php — one PDO connection per request, reused everywhere via getDB().
 * Prepared statements are used for every single query in this project;
 * there is no place in the codebase where user input is concatenated
 * into SQL.
 */

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements (not client-side emulation) —
                // required for the row-locking transactions in
                // api/reserve_seat.php to behave correctly.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Critical: MySQL's own NOW()/CURDATE() run in whatever timezone
            // the DB SERVER's OS is set to (often UTC), while PHP's naive
            // DateTime/strtotime() calls interpret the same "no timezone"
            // strings using date_default_timezone_set() (Asia/Kolkata, see
            // config.php). Left unaligned, a reservation's expires_at can
            // come back looking hours in the past the instant it's created.
            // Pinning the SESSION timezone here makes MySQL's clock agree
            // with PHP's, independent of how the underlying server is set up.
            $pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Check includes/config.php and make sure MySQL is running in XAMPP. (' . htmlspecialchars($e->getMessage()) . ')');
        }
    }

    return $pdo;
}
