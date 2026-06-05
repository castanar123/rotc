<?php
// Test login via web request simulation
// This script simulates a proper web request without interfering with sessions

// Simulate POST data
$_POST['username'] = 'admin';
$_POST['password'] = 'admin123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Test Script';

// Capture any output from login.php
ob_start();

// Include login.php
include 'login.php';

// Get the output
$output = ob_get_contents();
ob_end_clean();

echo "=== LOGIN TEST RESULTS ===\n";
echo "Output length: " . strlen($output) . " characters\n";

// Check session status
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']) {
    echo "✓ LOGIN SUCCESSFUL!\n";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
    echo "Username: " . ($_SESSION['username'] ?? 'Not set') . "\n";
    echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
} else {
    echo "✗ LOGIN FAILED\n";
}

// Check for debug log
if (file_exists('login_debug.log')) {
    echo "\n=== DEBUG LOG ===\n";
    $debugLog = file_get_contents('login_debug.log');
    $lines = explode("\n", $debugLog);
    // Show only the last 10 lines
    $lastLines = array_slice($lines, -10);
    echo implode("\n", $lastLines);
} else {
    echo "\nNo debug log found\n";
}

// Check for error log
if (file_exists('login_errors.log')) {
    echo "\n=== ERROR LOG ===\n";
    $errorLog = file_get_contents('login_errors.log');
    $lines = explode("\n", $errorLog);
    // Show only the last 5 lines
    $lastLines = array_slice($lines, -5);
    echo implode("\n", $lastLines);
} else {
    echo "\nNo error log found\n";
}
?>