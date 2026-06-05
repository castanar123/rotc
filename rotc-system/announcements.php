<?php
require_once 'includes/session.php';

// All logged-in users access
check_login();

$page_title = 'Announcements';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="form-container">
        <h2 class="display-5">Announcements</h2>
        <p>This page will display important announcements.</p>
        <hr>
        <div class="alert alert-info">Coming Soon: A full announcement system will be available here.</div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
