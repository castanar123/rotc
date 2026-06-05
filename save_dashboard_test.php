<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Set proper session for testing
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 7;
$_SESSION['cadet_profile_id'] = 4;
$_SESSION['role'] = 'cadet';
$_SESSION['username'] = 'test_cadet';

$output = "Session Status:\n";
$output .= "loggedin: " . ($_SESSION['loggedin'] ? 'true' : 'false') . "\n";
$output .= "user_id: " . $_SESSION['user_id'] . "\n";
$output .= "cadet_profile_id: " . $_SESSION['cadet_profile_id'] . "\n";
$output .= "role: " . $_SESSION['role'] . "\n\n";

// Now capture the dashboard output
ob_start();
include 'cadet_dashboard.php';
$dashboard_content = ob_get_clean();

// Extract statistics from the dashboard
preg_match_all('/<div class="stat-number"[^>]*>([^<]+)<\/div>/', $dashboard_content, $stat_matches);
preg_match_all('/<!-- DEBUG: ([^>]+) -->/', $dashboard_content, $debug_matches);

$output .= "Dashboard Statistics Found:\n";
if (!empty($stat_matches[1])) {
    foreach ($stat_matches[1] as $stat) {
        $output .= "- " . trim($stat) . "\n";
    }
} else {
    $output .= "No statistics found in dashboard output.\n";
}

$output .= "\nDebug Output:\n";
if (!empty($debug_matches[1])) {
    foreach ($debug_matches[1] as $debug) {
        $output .= "- " . $debug . "\n";
    }
} else {
    $output .= "No debug output found.\n";
}

// Check if dashboard redirected to login
if (strpos($dashboard_content, 'login.php') !== false || strpos($dashboard_content, 'Login') !== false) {
    $output .= "\nWARNING: Dashboard appears to be redirecting to login!\n";
}

$output .= "\nDashboard Content Length: " . strlen($dashboard_content) . " characters\n";

// Show first 1000 characters of dashboard
$output .= "\nDashboard Content Preview:\n";
$output .= substr($dashboard_content, 0, 1000) . "...\n";

// Save to file
file_put_contents('dashboard_test_results.txt', $output);
echo "Test results saved to dashboard_test_results.txt";
?>