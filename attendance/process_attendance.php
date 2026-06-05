<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// --- Helper function for sending JSON responses ---
function send_json_response($status, $message, $short_message = '', $cadet_name = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'short_message' => $short_message ?: ucfirst($status),
        'cadet_name' => $cadet_name
    ]);
    exit;
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method.');
}

check_login();
if (!in_array($_SESSION['role'], ['admin', 'instructor'])) {
    send_json_response('error', 'Unauthorized access.');
}

// Validate inputs (event_name optional)
if ((empty($_POST['cadet_id']) && empty($_POST['student_id'])) || empty($_POST['school_year']) || empty($_POST['semester'])) {
    send_json_response('error', 'Incomplete data provided.');
}

$submitted_id = isset($_POST['cadet_id']) ? trim($_POST['cadet_id']) : '';
$event_name = isset($_POST['event_name']) ? trim($_POST['event_name']) : '';
$school_year = trim($_POST['school_year']);
$semester = trim($_POST['semester']);
$user_id = 0;

// Check if the submitted ID is numeric (user_id) or a string (username)
// Resolve user_id from cadet_id (user_id or username) or from student_id
if (!empty($submitted_id)) {
    if (is_numeric($submitted_id)) {
        $user_id = (int)$submitted_id;
    } else {
        // If it's not numeric, assume it's a username and find the corresponding user ID
        $stmt = $link->prepare("SELECT id FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $submitted_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user_id = $user['id'];
            }
            $stmt->close();
        }
    }
}

// Fallback: accept student_id and map to user_id
if ($user_id === 0 && !empty($_POST['student_id'])) {
    $student_id = trim($_POST['student_id']);
    $stmt = $link->prepare("SELECT u.id FROM users u JOIN cadet_profiles cp ON cp.user_id = u.id WHERE cp.student_id = ? AND u.approval_status='approved' AND u.status='active' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_id = (int)$row['id'];
        }
        $stmt->close();
    }
}

// Fallback: accept profile_id and map to user_id
if ($user_id === 0 && !empty($_POST['profile_id'])) {
    $profile_id = (int)$_POST['profile_id'];
    $stmt = $link->prepare("SELECT u.id FROM users u JOIN cadet_profiles cp ON cp.user_id = u.id WHERE cp.id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_id = (int)$row['id'];
        }
        $stmt->close();
    }
}

if ($user_id === 0) {
    $ident = !empty($submitted_id) ? $submitted_id : ($_POST['student_id'] ?? 'unknown');
    send_json_response('error', "Cadet not found (identifier: '{$ident}').", 'Not Found');
}

// Get cadet's name for the response
$cadet_name = 'Unknown';
$stmt = $link->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM cadet_profiles WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $cadet_name = $row['full_name'];
    }
    $stmt->close();
}

// Default event name if omitted
if ($event_name === '') {
    $event_name = 'Daily Attendance';
}

// Check for duplicate attendance for the same event on the same day for the specific term
$stmt = $link->prepare("SELECT id FROM attendance_logs WHERE user_id = ? AND event_name = ? AND attendance_date = CURDATE() AND school_year = ? AND semester = ?");
if (!$stmt) {
    send_json_response('error', 'Database error: ' . $link->error);
}
$stmt->bind_param("isss", $user_id, $event_name, $school_year, $semester);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    send_json_response('error', 'Attendance already recorded for this cadet, for this event, for today.', 'Duplicate', $cadet_name);
}
$stmt->close();

// Insert new attendance log
$stmt = $link->prepare("INSERT INTO attendance_logs (user_id, event_name, school_year, semester, logged_by_user_id) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    send_json_response('error', 'Database error: ' . $link->error);
}

$logged_by = $_SESSION['user_id'];
$stmt->bind_param("isssi", $user_id, $event_name, $school_year, $semester, $logged_by);

if ($stmt->execute()) {
    send_json_response('success', 'Attendance recorded successfully.', 'Success', $cadet_name);
} else {
    send_json_response('error', 'Failed to record attendance.', 'Failed', $cadet_name);
}

$stmt->close();
$link->close();

$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Cadet already marked present for this event today.',
        'short_message' => 'Duplicate',
        'cadet_name' => $cadet_name
    ]);
    $check_stmt->close();
    $link->close();
    exit;
}
$check_stmt->close();

// --- Insert New Record ---
$insert_sql = "INSERT INTO attendance_logs (cadet_profile_id, school_year, semester, event_name, event_date, time_in, status, logged_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$insert_stmt = $link->prepare($insert_sql);
$insert_stmt->bind_param("issssssi", $cadet_profile_id, $school_year, $semester, $event_name, $event_date, $time_in, $status, $logged_by_user_id);

if ($insert_stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Attendance recorded successfully!',
        'short_message' => 'Logged',
        'cadet_name' => $cadet_name
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: Could not log attendance.',
        'short_message' => 'DB Error',
        'cadet_name' => $cadet_name
    ]);
}

$insert_stmt->close();
$link->close();
?>
