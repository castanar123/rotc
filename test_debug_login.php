<?php
// Test script to simulate login POST request to debug page

// Simulate POST data
$_POST = [
    'username' => 'test_admin',
    'password' => 'admin123'
];

// Set request method
$_SERVER['REQUEST_METHOD'] = 'POST';

// Include the debug login page
echo "<h1>Testing Login Debug with test_admin / admin123</h1>";
echo "<hr>";

// Capture output
ob_start();
include 'login_debug.php';
$output = ob_get_clean();

echo $output;
?>