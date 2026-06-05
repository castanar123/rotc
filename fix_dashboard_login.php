<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Simulate proper login for User ID 7 (maps to Cadet Profile ID 4)
$user_id = 7;
$cadet_profile_id = 4;

// Set all required session variables
$_SESSION['loggedin'] = true;  // This is what session.php checks for
$_SESSION['user_id'] = $user_id;
$_SESSION['cadet_profile_id'] = $cadet_profile_id;
$_SESSION['role'] = 'cadet';
$_SESSION['username'] = 'test_cadet';

echo "<h2>Login Fixed - Session Variables Set:</h2>";
echo "<ul>";
echo "<li>loggedin: " . ($_SESSION['loggedin'] ? 'true' : 'false') . "</li>";
echo "<li>user_id: " . $_SESSION['user_id'] . "</li>";
echo "<li>cadet_profile_id: " . $_SESSION['cadet_profile_id'] . "</li>";
echo "<li>role: " . $_SESSION['role'] . "</li>";
echo "<li>username: " . $_SESSION['username'] . "</li>";
echo "</ul>";

echo "<p><a href='cadet_dashboard.php'>Go to Cadet Dashboard</a></p>";
?>