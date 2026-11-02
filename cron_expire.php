<?php
/**
 * cron_expire.php
 * -----------------------------------------------------------
 * Not required for the demo — expireStaleReservations() already runs
 * at the top of every page and API call, so reservations never look
 * stale to anyone actively using the site.
 *
 * For a REAL deployment, wire this up as a scheduled task so seats
 * free up even at 2am when nobody is browsing:
 *
 *   Linux/macOS (crontab -e):
 *     * * * * * /usr/bin/php /path/to/smart-canteen/cron_expire.php >> /var/log/smart_canteen_cron.log 2>&1
 *
 *   Windows (XAMPP) — Task Scheduler, action:
 *     Program: C:\xampp\php\php.exe
 *     Arguments: "C:\xampp\htdocs\smart-canteen\cron_expire.php"
 *     Trigger: repeat every 1 minute
 * -----------------------------------------------------------
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDB();
expireStaleReservations($pdo);

echo '[' . date('Y-m-d H:i:s') . "] Expiry check complete.\n";
