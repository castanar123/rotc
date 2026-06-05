<?php
echo "<h2>Actual Login Test</h2>";

// Test the actual login process by simulating form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Processing Login...</h3>";
    
    // Capture the login attempt
    $_POST['username'] = 'admin';
    $_POST['password'] = 'admin123';
    
    // Start output buffering to capture any redirects or output
    ob_start();
    
    // Include the login processing
    try {
        include 'login.php';
        $output = ob_get_contents();
        ob_end_clean();
        
        echo "<h4>Login Processing Output:</h4>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
        
        // Check session status
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        echo "<h4>Session Status After Login:</h4>";
        echo "Session ID: " . session_id() . "<br>";
        echo "Session Variables:<br>";
        foreach ($_SESSION as $key => $value) {
            echo "- $key: $value<br>";
        }
        
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
            echo "<h3 style='color: green;'>✓ LOGIN SUCCESSFUL!</h3>";
            echo "User: " . $_SESSION['username'] . " (" . $_SESSION['role'] . ")<br>";
        } else {
            echo "<h3 style='color: red;'>✗ LOGIN FAILED</h3>";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "<h4 style='color: red;'>Error during login:</h4>";
        echo $e->getMessage();
    }
    
} else {
    // Show the test form
    echo "<h3>Test Login Form</h3>";
    echo "<p>This will test the actual login.php processing with admin credentials.</p>";
    echo "<form method='POST' action='test_actual_login.php'>";
    echo "<input type='submit' value='Test Login Process' style='padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer;'>";
    echo "</form>";
    
    echo "<h3>Current Session Status:</h3>";
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    echo "Session ID: " . session_id() . "<br>";
    echo "Session Variables:<br>";
    if (empty($_SESSION)) {
        echo "- No session variables set<br>";
    } else {
        foreach ($_SESSION as $key => $value) {
            echo "- $key: $value<br>";
        }
    }
}

echo "<br><a href='login.php'>Go to Login Page</a> | <a href='dashboard.php'>Go to Dashboard</a>";
?>