<?php
// Final test to verify login functionality after fixing database column issues
echo "<h1>Final Login Test</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .debug{background:#f0f0f0;padding:10px;margin:10px 0;border:1px solid #ccc;} .error{background:#ffebee;color:#c62828;padding:10px;margin:10px 0;} .success{background:#e8f5e9;color:#2e7d32;padding:10px;margin:10px 0;}</style>";

echo "<div class='debug'>Testing login with credentials: test_admin / admin123</div>";

// Simulate POST request to login.php
$_POST['username'] = 'test_admin';
$_POST['password'] = 'admin123';
$_POST['login'] = 'Login';

// Start output buffering to capture any output from login.php
ob_start();

// Set up environment to simulate a real login request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Test Browser';

try {
    // Include the login.php file to test the authentication
    include 'login.php';
    
    // Get any output from login.php
    $output = ob_get_contents();
    
    // Check if login was successful by examining session variables
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        echo "<div class='success'>✓ LOGIN SUCCESSFUL!</div>";
        echo "<div class='debug'>Session variables set:<br>";
        echo "- loggedin: " . ($_SESSION['loggedin'] ? 'true' : 'false') . "<br>";
        echo "- user_id: " . ($_SESSION['user_id'] ?? 'not set') . "<br>";
        echo "- username: " . ($_SESSION['username'] ?? 'not set') . "<br>";
        echo "- role: " . ($_SESSION['role'] ?? 'not set') . "<br>";
        echo "</div>";
        
        echo "<div class='success'>The login system is now working correctly!</div>";
        
    } else {
        echo "<div class='error'>✗ Login failed - session not established</div>";
        
        // Check if there were any errors in the output
        if (strpos($output, 'Authentication Failed') !== false) {
            echo "<div class='error'>Authentication Failed message detected in output</div>";
        }
        
        if (strpos($output, 'Invalid username') !== false) {
            echo "<div class='error'>Invalid username/password message detected</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Exception during login test: " . htmlspecialchars($e->getMessage()) . "</div>";
} finally {
    ob_end_clean();
}

echo "<hr>";
echo "<h3>Test Summary</h3>";
echo "<p>This test simulates a POST request to login.php with the test_admin credentials.</p>";
echo "<p>If successful, the user should be logged in and session variables should be set.</p>";
echo "<p><a href='login.php'>Go to Login Page</a> | <a href='admin/dashboard.php'>Go to Dashboard</a></p>";
?>