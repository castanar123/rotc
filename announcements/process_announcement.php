<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

check_login();

// Ensure only authorized users can process
if(!in_array($_SESSION['role'], ['admin', 'instructor', 'officer'])){
    die('Access Denied.');
}

$message = 'An unknown error occurred.';
$status = 'error';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['title'], $_POST['content'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $priority = $_POST['priority'] ?? 'normal';
    $category = $_POST['category'] ?? 'general';
    $author_id = $_SESSION['id'];

    if (empty($title) || empty($content)) {
        $message = 'Title and content cannot be empty.';
    } else {
        $sql = "INSERT INTO announcements (title, content, priority, category, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssi", $title, $content, $priority, $category, $author_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Announcement posted successfully!';
                $status = 'success';
            } else {
                $message = 'Failed to post announcement. Database error: ' . mysqli_error($link);
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = 'Database error (prepare insert): ' . mysqli_error($link);
        }
    }
} else {
    $message = 'Invalid request.';
}

mysqli_close($link);
header('Location: view.php?status=' . $status . '&message=' . urlencode($message));
exit();
?>
