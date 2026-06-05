<?php
// Test login functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';

echo "Testing login functionality...\n";
echo "Database connection: " . (isset($pdo) ? "OK" : "FAILED") . "\n";

// Test user lookup
try {
    $username = 'admin';
    $stmt = $pdo->prepare("SELECT id, username, email, password, role, is_active FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "User lookup result: " . ($user ? "Found" : "Not found") . "\n";
    
    if ($user) {
        echo "User ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Password hash: " . substr($user['password'], 0, 20) . "...\n";
        
        // Test password verification
        $test_password = 'admin123';
        $password_valid = password_verify($test_password, $user['password']);
        echo "Password verification for 'admin123': " . ($password_valid ? "SUCCESS" : "FAILED") . "\n";
        
        // Test with different passwords
        $test_passwords = ['admin', 'password', '123456', 'admin123'];
        foreach ($test_passwords as $pwd) {
            $valid = password_verify($pwd, $user['password']);
            echo "Testing password '$pwd': " . ($valid ? "VALID" : "INVALID") . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>