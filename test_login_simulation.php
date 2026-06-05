<?php
// Test login simulation
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/TwoFactorAuth.php';
require_once 'includes/SecurityLogger.php';

echo "<h2>Login Simulation Test</h2>";

// Simulate POST data
$_POST['username'] = 'admin';
$_POST['password'] = 'admin123';

echo "<h3>Testing Login Process</h3>";
echo "Username: " . $_POST['username'] . "<br>";
echo "Password: [hidden]<br><br>";

try {
    // Get database connection (using global $pdo from db.php)
    global $pdo;
    if (!$pdo) {
        throw new Exception("Database connection not available");
    }
    echo "✓ Database connection successful<br>";
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username, password, role, is_active, two_factor_enabled FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✓ User found: " . $user['username'] . " (Role: " . $user['role'] . ")<br>";
        
        // Verify password
        if (password_verify($_POST['password'], $user['password'])) {
            echo "✓ Password verification successful<br>";
            
            // Check if account is active
            if ($user['is_active']) {
                echo "✓ Account is active<br>";
                
                // Start session (if not already started)
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                echo "✓ Session variables set successfully<br>";
                echo "Session ID: " . session_id() . "<br>";
                echo "User ID: " . $_SESSION['user_id'] . "<br>";
                echo "Username: " . $_SESSION['username'] . "<br>";
                echo "Role: " . $_SESSION['role'] . "<br>";
                
                // Check 2FA requirement
                if ($user['two_factor_enabled']) {
                    echo "⚠ Two-factor authentication required<br>";
                } else {
                    echo "✓ No 2FA required - login complete<br>";
                    echo "<br><strong style='color: green;'>LOGIN SUCCESSFUL!</strong><br>";
                    echo "<a href='dashboard.php'>Go to Dashboard</a><br>";
                }
                
            } else {
                echo "✗ Account is inactive<br>";
            }
        } else {
            echo "✗ Password verification failed<br>";
        }
    } else {
        echo "✗ User not found<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

echo "<br><h3>Session Information</h3>";
echo "Session Status: " . session_status() . "<br>";
echo "Session Variables:<br>";
if (isset($_SESSION)) {
    foreach ($_SESSION as $key => $value) {
        echo "- $key: $value<br>";
    }
} else {
    echo "No session variables set<br>";
}

echo "<br><a href='login.php'>Back to Login Page</a>";
?>