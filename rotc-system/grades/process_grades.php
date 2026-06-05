<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

check_login();

// Ensure only officers or higher can access
if(!in_array($_SESSION['role'], ['2cl', '1cl', 'commandant'])){
    die('Access Denied.');
}

$message = 'An unknown error occurred.';
$status = 'error';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cadet_id = $_POST['cadet_id'];
    $event_name = trim($_POST['event_name']);
    $grade = $_POST['grade'];
    $grade_date = $_POST['grade_date'];
    $recorded_by = $_SESSION['id'];

    if (empty($cadet_id) || empty($event_name) || !is_numeric($grade) || empty($grade_date)) {
        $message = 'Please fill in all fields correctly.';
    } else {
        $sql = "INSERT INTO grades (cadet_id, event_name, grade, grade_date, recorded_by) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "isdsi", $cadet_id, $event_name, $grade, $grade_date, $recorded_by);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Grade successfully recorded.';
                $status = 'success';
            } else {
                $message = 'Error: Could not execute the query.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = 'Error: Could not prepare the query.';
        }
    }
} else {
    $message = 'Invalid request method.';
}

mysqli_close($link);
header('Location: manage_grades.php?status=' . $status . '&message=' . urlencode($message));
exit();
?>
