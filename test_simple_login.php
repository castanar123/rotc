<?php
// Simple login test without including login.php
echo "=== SIMPLE LOGIN TEST ===\n";

// Start session
session_start();

// Include required files
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Test credentials
$username = 'admin';
$password = 'admin123';

echo "Testing login for: $username\n";

try {
    // Look up user
    $stmt = $pdo->prepare("SELECT id, username, password, role, is_active, failed_login_attempts, locked_until FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "✗ User not found\n";
        exit;
    }
    
    echo "✓ User found: {$user['username']} (Role: {$user['role']})\n";
    
    // Check if account is active
    if (!$user['is_active']) {
        echo "✗ Account is inactive\n";
        exit;
    }
    
    echo "✓ Account is active\n";
    
    // Check if account is locked
    if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
        echo "✗ Account is locked until: {$user['locked_until']}\n";
        exit;
    }
    
    echo "✓ Account is not locked\n";
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        echo "✓ Password verification successful\n";
        
        // Set session variables
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        echo "✓ Session variables set\n";
        echo "Session ID: " . session_id() . "\n";
        echo "User ID: " . $_SESSION['user_id'] . "\n";
        echo "Username: " . $_SESSION['username'] . "\n";
        echo "Role: " . $_SESSION['role'] . "\n";
        
        // Update last login
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), failed_login_attempts = 0 WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        echo "✓ Last login updated\n";
        echo "\n=== LOGIN SUCCESSFUL! ===\n";
        
    } else {
        echo "✗ Password verification failed\n";
        echo "Stored hash: {$user['password']}\n";
        echo "Test password: $password\n";
        
        // Increment failed login attempts
        $newAttempts = $user['failed_login_attempts'] + 1;
        $updateStmt = $pdo->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
        $updateStmt->execute([$newAttempts, $user['id']]);
        
        echo "Failed login attempts: $newAttempts\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>