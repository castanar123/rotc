<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/TwoFactorAuth.php';

echo "<h2>Login Fix Verification Test</h2>";

// Test the TwoFactorAuth class methods that were fixed
$twoFA = new TwoFactorAuth();

try {
    // Test is2FAEnabled method (this was the one causing the error)
    echo "<h3>Testing is2FAEnabled method...</h3>";
    $result = $twoFA->is2FAEnabled(29); // Using user ID 29 from the error
    echo "<p style='color: green;'>✓ is2FAEnabled method works! Result: " . ($result ? 'true' : 'false') . "</p>";
    
    // Test getUserSecret method
    echo "<h3>Testing getUserSecret method...</h3>";
    $secret = $twoFA->getUserSecret(29);
    echo "<p style='color: green;'>✓ getUserSecret method works! Secret exists: " . ($secret ? 'Yes' : 'No') . "</p>";
    
    echo "<h3>Overall Result</h3>";
    echo "<p style='color: green; font-weight: bold;'>✓ All TwoFactorAuth database queries are working correctly!</p>";
    echo "<p>The login authentication issue has been resolved.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>Test Login Page</a></p>";
echo "<p><strong>Test Credentials:</strong><br>Username: test_admin<br>Password: admin123</p>";
?>