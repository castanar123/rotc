<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Set proper session for testing
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 7;
$_SESSION['cadet_profile_id'] = 4;
$_SESSION['role'] = 'cadet';
$_SESSION['username'] = 'test_cadet';

echo "<h2>Session Status:</h2>";
echo "<ul>";
echo "<li>loggedin: " . ($_SESSION['loggedin'] ? 'true' : 'false') . "</li>";
echo "<li>user_id: " . $_SESSION['user_id'] . "</li>";
echo "<li>cadet_profile_id: " . $_SESSION['cadet_profile_id'] . "</li>";
echo "<li>role: " . $_SESSION['role'] . "</li>";
echo "</ul>";

// Now capture the dashboard output
ob_start();
include 'cadet_dashboard.php';
$dashboard_content = ob_get_clean();

// Extract statistics from the dashboard
preg_match_all('/<div class="stat-number"[^>]*>([^<]+)<\/div>/', $dashboard_content, $stat_matches);
preg_match_all('/<!-- DEBUG: ([^>]+) -->/', $dashboard_content, $debug_matches);

echo "<h2>Dashboard Statistics Found:</h2>";
if (!empty($stat_matches[1])) {
    echo "<ul>";
    foreach ($stat_matches[1] as $stat) {
        echo "<li>" . trim($stat) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No statistics found in dashboard output.</p>";
}

echo "<h2>Debug Output:</h2>";
if (!empty($debug_matches[1])) {
    echo "<ul>";
    foreach ($debug_matches[1] as $debug) {
        echo "<li>" . htmlspecialchars($debug) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No debug output found.</p>";
}

// Check if dashboard redirected to login
if (strpos($dashboard_content, 'login.php') !== false || strpos($dashboard_content, 'Login') !== false) {
    echo "<h2 style='color: red;'>WARNING: Dashboard appears to be redirecting to login!</h2>";
}

echo "<h2>Dashboard Content Length:</h2>";
echo "<p>" . strlen($dashboard_content) . " characters</p>";

// Show first 500 characters of dashboard
echo "<h2>Dashboard Content Preview:</h2>";
echo "<pre>" . htmlspecialchars(substr($dashboard_content, 0, 500)) . "...</pre>";
?>