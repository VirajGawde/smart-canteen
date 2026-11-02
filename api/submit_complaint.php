<?php
/**
 * POST api/submit_complaint.php (multipart/form-data)
 * body: complaint_type, bench_id?, seat_number?, description, image?, csrf
 *
 * If bench_id is given, every student CURRENTLY occupying that bench
 * gets a complaint_feedback row + a "Feedback Required" notification
 * (the complainant themself is excluded, in case they happen to be
 * sitting there too). This is what lets an admin corroborate a report
 * instead of acting on a single unverified complaint.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireStudentLogin();
$pdo = getDB();
expireStaleReservations($pdo);

if (!verifyCsrf($_POST['csrf'] ?? null)) {
    jsonError('Your session looks stale — please refresh the page and try again.', 419);
}

$studentId = currentStudentId();
$validTypes = ['seat_misuse', 'reserved_seat_occupied', 'broken_furniture', 'cleanliness', 'other'];
$complaintType = $_POST['complaint_type'] ?? '';
$description = trim($_POST['description'] ?? '');
$benchId = !empty($_POST['bench_id']) ? (int) $_POST['bench_id'] : null;
$seatNumber = !empty($_POST['seat_number']) ? (int) $_POST['seat_number'] : null;

if (!in_array($complaintType, $validTypes, true)) {
    jsonError('Please choose a valid complaint type.');
}
if ($description === '' || mb_strlen($description) < 5) {
    jsonError('Please describe the issue in a little more detail.');
}

// Resolve (bench_id, seat_number) -> a real seat_id, if both were given.
$seatId = null;
if ($benchId && $seatNumber) {
    $seatLookup = $pdo->prepare('SELECT id FROM seats WHERE bench_id = ? AND seat_number = ?');
    $seatLookup->execute([$benchId, $seatNumber]);
    $seatId = $seatLookup->fetchColumn() ?: null;
}

// ---- Optional image upload ----
$imagePath = null;
if (!empty($_FILES['image']['name'])) {
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonError('There was a problem uploading the image.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        jsonError('Image must be under 5MB.');
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!$imageInfo || !isset($allowedMime[$imageInfo['mime']])) {
        jsonError('Only JPG or PNG images are allowed.');
    }
    $ext = $allowedMime[$imageInfo['mime']];
    $filename = 'complaint_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        jsonError('Could not save the uploaded image.', 500);
    }
    $imagePath = $filename;
}

$pdo->beginTransaction();
try {
    $ins = $pdo->prepare(
        'INSERT INTO complaints (student_id, complaint_type, bench_id, seat_id, description, image_path)
         VALUES (?,?,?,?,?,?)'
    );
    $ins->execute([$studentId, $complaintType, $benchId, $seatId, $description, $imagePath]);
    $complaintId = (int) $pdo->lastInsertId();

    $feedbackTargets = [];
    if ($benchId) {
        $occ = $pdo->prepare(
            "SELECT DISTINCT r.student_id FROM reservations r
             WHERE r.bench_id = ? AND r.status = 'occupied' AND r.student_id != ?"
        );
        $occ->execute([$benchId, $studentId]);
        $feedbackTargets = array_column($occ->fetchAll(), 'student_id');

        $feedbackIns = $pdo->prepare('INSERT INTO complaint_feedback (complaint_id, student_id) VALUES (?,?)');
        foreach ($feedbackTargets as $targetId) {
            $feedbackIns->execute([$complaintId, $targetId]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Could not submit the complaint. Please try again.', 500);
}

if (!empty($feedbackTargets)) {
    $benchNumStmt = $pdo->prepare('SELECT bench_number FROM benches WHERE id = ?');
    $benchNumStmt->execute([$benchId]);
    $benchNumber = $benchNumStmt->fetchColumn();
    foreach ($feedbackTargets as $targetId) {
        createNotification($pdo, $targetId, 'feedback_request', "You have received a complaint feedback request for Bench {$benchNumber}.", $complaintId);
    }
}

jsonSuccess(['complaint_id' => $complaintId], 'Complaint submitted successfully.');
