<?php
// Minimal session test without any includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "Session test successful";
echo "<br>Session ID: " . session_id();
echo "<br>Session status: " . session_status();
if (isset($_SESSION['user_id'])) {
    echo "<br>User ID: " . $_SESSION['user_id'];
}
?>