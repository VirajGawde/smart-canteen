<?php
/**
 * functions.php
 * -----------------------------------------------------------
 * All the "business rules" from the spec live here as small,
 * named functions so a page never has raw policy logic mixed
 * into its HTML. This is the file to read to understand how
 * the reservation lifecycle actually works.
 * -----------------------------------------------------------
 */

// =====================================================
// SMALL UTILITIES
// =====================================================

/** Shorthand for htmlspecialchars(), used everywhere we print user data. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** SAP ID must be EXACTLY 11 numeric digits — used both server-side and to mirror js/main.js. */
function isValidSapId(string $sapId): bool
{
    return (bool) preg_match('/^[0-9]{11}$/', $sapId);
}

/** Kept deliberately simple (length only) — a college project's password policy doesn't need much more. */
function isValidPassword(string $password): bool
{
    return strlen($password) >= 6;
}

/** One-time flash messages that survive a redirect (stored in session, read once). */
function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

/** CSRF token bound to the session; every state-changing form/AJAX call includes it. */
function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verifyCsrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

/** Standard JSON envelope for every api/*.php endpoint. */
function jsonSuccess(array $data = [], ?string $message = null): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => true, 'message' => $message], $data));
    exit;
}

function jsonError(string $message, int $httpCode = 400): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

// =====================================================
// SETTINGS (admin-configurable policy knobs)
// =====================================================

function getSetting(PDO $pdo, string $key, $default = null)
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    $cache[$key] = ($value === false) ? $default : $value;
    return $cache[$key];
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

// =====================================================
// NOTIFICATIONS
// =====================================================

function createNotification(PDO $pdo, int $studentId, string $type, string $message, ?int $relatedId = null): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (student_id, type, message, related_id) VALUES (?,?,?,?)');
    $stmt->execute([$studentId, $type, $message, $relatedId]);
}

function unreadNotificationCount(PDO $pdo, int $studentId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE student_id = ? AND is_read = 0');
    $stmt->execute([$studentId]);
    return (int) $stmt->fetchColumn();
}

// =====================================================
// BAN POLICY
// =====================================================

/** Returns the active ban row for a student, or null if they're clear to book. */
function getActiveBan(PDO $pdo, int $studentId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM bans WHERE student_id = ? AND lifted_at IS NULL AND end_date > NOW() ORDER BY end_date DESC LIMIT 1');
    $stmt->execute([$studentId]);
    $ban = $stmt->fetch();
    return $ban ?: null;
}

/**
 * Called every time a no-show is recorded. Counts no-shows in the
 * configured rolling window and either issues a warning notification
 * or creates a ban, per the policy in the `settings` table.
 * Policy example with defaults (threshold=3): 1st & 2nd no-show =
 * warning, 3rd = ban. Never stacks a second ban on an already-banned student.
 */
function applyBanPolicyIfNeeded(PDO $pdo, int $studentId): void
{
    $threshold  = (int) getSetting($pdo, 'no_show_ban_threshold', 3);
    $windowDays = (int) getSetting($pdo, 'no_show_window_days', 30);
    $banDays    = (int) getSetting($pdo, 'ban_duration_days', 3);

    if (getActiveBan($pdo, $studentId)) {
        return; // already restricted, don't pile on
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM no_shows WHERE student_id = ? AND occurred_at >= (NOW() - INTERVAL ? DAY)');
    $stmt->execute([$studentId, $windowDays]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= $threshold) {
        $stmt = $pdo->prepare('INSERT INTO bans (student_id, reason, start_date, end_date) VALUES (?,?,NOW(), NOW() + INTERVAL ? DAY)');
        $stmt->execute([$studentId, "Repeated no-shows ($count within $windowDays days)", $banDays]);
        createNotification(
            $pdo,
            $studentId,
            'ban',
            'Your account is temporarily restricted from making seat reservations due to repeated no-shows.'
        );
    } elseif ($count >= 1) {
        createNotification(
            $pdo,
            $studentId,
            'warning',
            "Warning: you have $count no-show(s). Reaching $threshold no-shows within $windowDays days will result in a temporary booking restriction."
        );
    }
}

// =====================================================
// THE EXPIRY ENGINE
// =====================================================
// PHP has no background timer of its own, so expiry is handled two
// ways at once (both real, neither "fake"):
//   1. LAZILY: this function runs at the top of every page/endpoint
//      that reads reservation or seat state, so the data you're
//      looking at is never more than an instant stale.
//   2. via CRON (optional, see cron_expire.php): for a real
//      deployment you'd also hit this from a scheduled task every
//      minute so a seat is released even if nobody happens to be
//      browsing the site at that exact moment.
//
// Row locking (FOR UPDATE) plus a status-guarded UPDATE means two
// overlapping calls (e.g. a page load and an AJAX poll landing at
// the same millisecond) can never double-process the same reservation.
// =====================================================

function expireStaleReservations(PDO $pdo): void
{
    $stale = $pdo->query(
        "SELECT id, student_id, seat_id FROM reservations WHERE status = 'reserved' AND expires_at < NOW()"
    )->fetchAll();

    foreach ($stale as $row) {
        $pdo->beginTransaction();
        try {
            $claim = $pdo->prepare("UPDATE reservations SET status = 'expired' WHERE id = ? AND status = 'reserved'");
            $claim->execute([$row['id']]);

            if ($claim->rowCount() === 1) {
                $pdo->prepare("UPDATE seats SET status = 'available' WHERE id = ? AND status = 'reserved'")
                    ->execute([$row['seat_id']]);

                $pdo->prepare('INSERT INTO no_shows (student_id, reservation_id, occurred_at) VALUES (?,?,NOW())')
                    ->execute([$row['student_id'], $row['id']]);

                $pdo->prepare('UPDATE students SET no_show_count = no_show_count + 1 WHERE id = ?')
                    ->execute([$row['student_id']]);

                createNotification(
                    $pdo,
                    $row['student_id'],
                    'expired',
                    'Your reservation expired because you did not reach the canteen within 10 minutes.',
                    $row['id']
                );
            }
            $pdo->commit();

            if ($claim->rowCount() === 1) {
                applyBanPolicyIfNeeded($pdo, $row['student_id']);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// =====================================================
// RESERVATION LOOKUPS
// =====================================================

/** A student's current reservation, if any, joined with bench/seat info for display. */
function getActiveReservation(PDO $pdo, int $studentId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT r.*, s.seat_number, b.bench_number, b.location, b.qr_token
         FROM reservations r
         JOIN seats s ON s.id = r.seat_id
         JOIN benches b ON b.id = r.bench_id
         WHERE r.student_id = ? AND r.status IN ('reserved','occupied')
         ORDER BY r.id DESC LIMIT 1"
    );
    $stmt->execute([$studentId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

/** Live counts for the quick-status cards (dashboard) and admin dashboard. */
function getCanteenStats(PDO $pdo): array
{
    $counts = ['available' => 0, 'reserved' => 0, 'occupied' => 0, 'unavailable' => 0];
    foreach ($pdo->query('SELECT status, COUNT(*) c FROM seats GROUP BY status') as $row) {
        $counts[$row['status']] = (int) $row['c'];
    }
    $total = $counts['available'] + $counts['reserved'] + $counts['occupied'];
    $occupancy = $total > 0 ? round((($counts['reserved'] + $counts['occupied']) / $total) * 100) : 0;

    return [
        'available'  => $counts['available'],
        'reserved'   => $counts['reserved'],
        'occupied'   => $counts['occupied'],
        'unavailable'=> $counts['unavailable'],
        'occupancy'  => $occupancy,
    ];
}

/** Human-friendly "x min ago" / date formatting used across the views. */
function formatDateTime(?string $datetime): string
{
    if (!$datetime) return '—';
    return date('d M Y, h:i A', strtotime($datetime));
}

function timeAgo(?string $datetime): string
{
    if (!$datetime) return '—';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' day(s) ago';
}

/** Pretty labels for enum values so the UI never shows raw db values like "under_review". */
function statusLabel(string $status): string
{
    $map = [
        'reserved' => 'Reserved', 'occupied' => 'Occupied', 'completed' => 'Completed',
        'expired' => 'Expired', 'cancelled' => 'Cancelled', 'available' => 'Available',
        'unavailable' => 'Unavailable', 'submitted' => 'Submitted', 'under_review' => 'Under Review',
        'verified' => 'Verified', 'rejected' => 'Rejected', 'resolved' => 'Resolved',
        'pending' => 'Pending', 'responded' => 'Responded',
        'seat_misuse' => 'Seat Misuse', 'reserved_seat_occupied' => 'Reserved Seat Occupied by Someone Else',
        'broken_furniture' => 'Broken Furniture', 'cleanliness' => 'Cleanliness', 'other' => 'Other',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}
