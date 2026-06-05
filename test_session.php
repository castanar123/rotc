<?php
// Simple test session setup for testing rifle scanner components
session_start();

// Set up a test session to bypass login
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test_user';
$_SESSION['email'] = 'test@example.com';
$_SESSION['role'] = 'admin';

echo "<h2>Test Session Created</h2>";
echo "<p>Session variables set for testing:</p>";
echo "<ul>";
echo "<li>User ID: " . $_SESSION['user_id'] . "</li>";
echo "<li>Username: " . $_SESSION['username'] . "</li>";
echo "<li>Role: " . $_SESSION['role'] . "</li>";
echo "</ul>";
echo "<p><a href='rifle_qr_test_generator.php'>Go to Rifle QR Generator</a></p>";
echo "<p><a href='simple_rifle_scanner.php'>Go to Simple Rifle Scanner</a></p>";
echo "<p><a href='rifle_test_page.php'>Go to Rifle Test Page</a></p>";
?>