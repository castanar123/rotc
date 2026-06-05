<?php
require_once 'includes/db.php';

// Test script to verify login process
echo "<h2>Login Process Test</h2>";

try {
    // Get the admin_test user
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = 'admin_test'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<h3>Testing passwords for user: {$user['username']}</h3>";
        
        // Test common passwords
        $testPasswords = ['admin', 'password', 'admin123', 'test', '123456', 'admin_test'];
        
        foreach ($testPasswords as $testPass) {
            $isValid = password_verify($testPass, $user['password']);
            $status = $isValid ? '<span style="color: green;">✓ VALID</span>' : '<span style="color: red;">✗ Invalid</span>';
            echo "<p>Testing password '{$testPass}': {$status}</p>";
            
            if ($isValid) {
                echo "<p style='background: lightgreen; padding: 10px;'><strong>SUCCESS!</strong> Use username: <strong>{$user['username']}</strong> with password: <strong>{$testPass}</strong></p>";
                break;
            }
        }
        
        // If no common password works, let's create a new test user with known password
        echo "<hr>";
        echo "<h3>Creating test user with known password</h3>";
        
        $testUsername = 'test_admin';
        $testPassword = 'admin123';
        $hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);
        
        // Check if test user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$testUsername]);
        
        if ($stmt->fetch()) {
            echo "<p>Test user '{$testUsername}' already exists.</p>";
        } else {
            // Create test user
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, status, created_at) 
                VALUES (?, ?, ?, 'admin', 'active', NOW())
            ");
            $stmt->execute([$testUsername, 'test@admin.com', $hashedPassword]);
            echo "<p style='background: lightblue; padding: 10px;'>Created test user: <strong>{$testUsername}</strong> with password: <strong>{$testPassword}</strong></p>";
        }
        
    } else {
        echo "<p>Admin user not found!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='login.php'>← Test Login Page</a>";
?>