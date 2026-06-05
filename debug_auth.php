<?php
session_start();
require_once 'includes/db.php';

// Simulate cadet user session (user ID 11)
$_SESSION['user_id'] = 11;
$_SESSION['username'] = 'cadet11';
$_SESSION['full_name'] = 'Test Cadet';
$_SESSION['role'] = 'cadet';
$_SESSION['loggedin'] = true;

echo "<h2>Debug Authorization Logic</h2>";
echo "<h3>Session Data:</h3>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

echo "<h3>Authorization Checks:</h3>";
echo "<p>isset(\$_SESSION['loggedin']): " . (isset($_SESSION['loggedin']) ? 'true' : 'false') . "</p>";
echo "<p>\$_SESSION['loggedin'] value: " . ($_SESSION['loggedin'] ? 'true' : 'false') . "</p>";
echo "<p>\$_SESSION['role']: " . $_SESSION['role'] . "</p>";
echo "<p>in_array(\$_SESSION['role'], ['cadet', 'basic_cadet']): " . (in_array($_SESSION['role'], ['cadet', 'basic_cadet']) ? 'true' : 'false') . "</p>";

// Test the exact condition from cadet_attendance.php
$auth_check = isset($_SESSION['loggedin']) && in_array($_SESSION['role'], ['cadet', 'basic_cadet']);
echo "<p><strong>Combined auth check result: " . ($auth_check ? 'AUTHORIZED' : 'UNAUTHORIZED') . "</strong></p>";

if (!$auth_check) {
    echo "<p style='color: red;'>This would trigger the Unauthorized response</p>";
} else {
    echo "<p style='color: green;'>Authorization should pass</p>";
}

// Test AJAX parameter
echo "<h3>AJAX Parameter Test:</h3>";
$_GET['ajax'] = 'true';
echo "<p>\$_GET['ajax']: " . $_GET['ajax'] . "</p>";
echo "<p>isset(\$_GET['ajax']) && \$_GET['ajax'] === 'true': " . ((isset($_GET['ajax']) && $_GET['ajax'] === 'true') ? 'true' : 'false') . "</p>";

echo "<hr>";
echo "<h3>Now testing actual cadet_attendance.php logic:</h3>";

// Capture the exact logic from cadet_attendance.php
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json');
    
    // Check if user is logged in and is cadet
    if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['cadet', 'basic_cadet'])) {
        echo json_encode(['error' => 'Unauthorized']);
        echo "<br><p style='color: red;'>FAILED: Authorization check failed</p>";
    } else {
        echo json_encode(['success' => 'Authorization passed']);
        echo "<br><p style='color: green;'>SUCCESS: Authorization passed</p>";
    }
}
?>