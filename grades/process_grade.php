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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cadet_id'], $_POST['event_name'], $_POST['grade'])) {
    $cadet_user_id = $_POST['cadet_id'];
    $event_name = trim($_POST['event_name']);
    $grade = trim($_POST['grade']);
    $comments = trim($_POST['comments']);
    $recorded_by = $_SESSION['id'];

    // Basic validation
    if (empty($cadet_user_id) || empty($event_name) || empty($grade)) {
        $message = 'Please fill in all required fields.';
    } else {
        // Get cadet_profiles.id from users.id
        $sql_get_profile_id = "SELECT id FROM cadet_profiles WHERE user_id = ?";
        $cadet_profile_id = null;
        if($stmt_get = mysqli_prepare($link, $sql_get_profile_id)){
            mysqli_stmt_bind_param($stmt_get, "i", $cadet_user_id);
            mysqli_stmt_execute($stmt_get);
            $result = mysqli_stmt_get_result($stmt_get);
            if($row = mysqli_fetch_assoc($result)){
                $cadet_profile_id = $row['id'];
            }
            mysqli_stmt_close($stmt_get);
        }

        if ($cadet_profile_id) {
            $sql_insert = "INSERT INTO grades (cadet_id, event_name, grade, comments, recorded_by) VALUES (?, ?, ?, ?, ?)";
            if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                mysqli_stmt_bind_param($stmt_insert, "isssi", $cadet_profile_id, $event_name, $grade, $comments, $recorded_by);
                if (mysqli_stmt_execute($stmt_insert)) {
                    $message = 'Grade added successfully!';
                    $status = 'success';
                } else {
                    $message = 'Failed to add grade. Database error.';
                }
                mysqli_stmt_close($stmt_insert);
            } else {
                $message = 'Database error (prepare insert).';
            }
        } else {
            $message = 'Invalid Cadet ID selected.';
        }
    }
} else {
    $message = 'Invalid request.';
}

mysqli_close($link);
header('Location: add_grade.php?status=' . $status . '&message=' . urlencode($message));
exit();

?>
