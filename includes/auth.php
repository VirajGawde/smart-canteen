<?php
/**
 * auth.php — session-based auth for two completely separate roles.
 * A student session can never touch admin/* pages and vice versa;
 * every protected page starts with requireStudentLogin() or
 * requireAdminLogin().
 */

// =====================================================
// STUDENT AUTH
// =====================================================

function isStudentLoggedIn(): bool
{
    return isset($_SESSION['student_id']);
}

function currentStudentId(): ?int
{
    return $_SESSION['student_id'] ?? null;
}

/** Fetches the full student row for the logged-in student (used by header, profile, etc). */
function currentStudent(): ?array
{
    if (!isStudentLoggedIn()) return null;
    static $cached = null;
    if ($cached !== null) return $cached;

    $stmt = getDB()->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([currentStudentId()]);
    $cached = $stmt->fetch() ?: null;
    return $cached;
}

function requireStudentLogin(): void
{
    if (!isStudentLoggedIn()) {
        $isApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
        if ($isApi) {
            jsonError('Your session has expired. Please log in again.', 401);
        }
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Login checks two things: an 11-digit SAP ID that matches a row in
 * `students`, AND the password for that account. The `no_account` flag
 * on failure lets the login page point someone straight at "Create an
 * account" instead of just saying "wrong details" — see register.php.
 */
function attemptStudentLogin(string $sapId, string $password): array
{
    if (!isValidSapId($sapId)) {
        return ['ok' => false, 'message' => 'SAP ID must contain exactly 11 digits.'];
    }
    if ($password === '') {
        return ['ok' => false, 'message' => 'Please enter your password.'];
    }

    $stmt = getDB()->prepare('SELECT * FROM students WHERE sap_id = ?');
    $stmt->execute([$sapId]);
    $student = $stmt->fetch();

    if (!$student) {
        return ['ok' => false, 'no_account' => true, 'message' => 'No account found for this SAP ID.'];
    }

    if (!password_verify($password, $student['password_hash'])) {
        return ['ok' => false, 'message' => 'Incorrect password. Please try again.'];
    }

    session_regenerate_id(true);
    $_SESSION['student_id'] = (int) $student['id'];
    $_SESSION['role'] = 'student';
    return ['ok' => true];
}

/**
 * Self-service account creation (see register.php). Also used internally
 * by admin/students.php's "Add Student" form, which is why this doesn't
 * log the new account in itself — the caller decides what happens next.
 */
function registerStudent(string $sapId, string $password, string $name, string $branch, int $year, ?string $email): array
{
    if (!isValidSapId($sapId)) {
        return ['ok' => false, 'message' => 'SAP ID must contain exactly 11 digits.'];
    }
    if (!isValidPassword($password)) {
        return ['ok' => false, 'message' => 'Password must be at least 6 characters.'];
    }
    if (trim($name) === '' || trim($branch) === '') {
        return ['ok' => false, 'message' => 'Please fill in your name and branch.'];
    }
    if ($year < 1 || $year > 4) {
        return ['ok' => false, 'message' => 'Please choose a valid year (1–4).'];
    }
    if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address, or leave it blank.'];
    }

    $exists = getDB()->prepare('SELECT id FROM students WHERE sap_id = ?');
    $exists->execute([$sapId]);
    if ($exists->fetch()) {
        return ['ok' => false, 'message' => 'An account already exists for this SAP ID. Try logging in instead.'];
    }

    $stmt = getDB()->prepare(
        'INSERT INTO students (sap_id, password_hash, name, branch, year, email) VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$sapId, password_hash($password, PASSWORD_DEFAULT), trim($name), trim($branch), $year, $email ?: null]);

    return ['ok' => true, 'student_id' => (int) getDB()->lastInsertId()];
}

// =====================================================
// ADMIN AUTH
// =====================================================

function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_id']);
}

function currentAdminId(): ?int
{
    return $_SESSION['admin_id'] ?? null;
}

function currentAdmin(): ?array
{
    if (!isAdminLoggedIn()) return null;
    $stmt = getDB()->prepare('SELECT id, username, name, created_at FROM admins WHERE id = ?');
    $stmt->execute([currentAdminId()]);
    return $stmt->fetch() ?: null;
}

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        $isApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
        if ($isApi) {
            jsonError('Admin session expired. Please log in again.', 401);
        }
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

function attemptAdminLogin(string $username, string $password): array
{
    $stmt = getDB()->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return ['ok' => false, 'message' => 'Invalid username or password.'];
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['role'] = 'admin';
    return ['ok' => true];
}
