<?php
// Simple database connection test
echo "=== DATABASE CONNECTION TEST ===\n";

// Include database configuration
require_once 'includes/db.php';

echo "Database Name: " . DB_NAME . "\n";
echo "Database Host: " . DB_SERVER . "\n";
echo "Database User: " . DB_USERNAME . "\n";

try {
    // Test database connection
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USERNAME,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    echo "✓ Database connection successful!\n";
    
    // Test user lookup
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✓ Admin user found!\n";
        echo "User ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Password hash length: " . strlen($user['password']) . "\n";
        
        // Test password verification
        $testPassword = 'admin123';
        if (password_verify($testPassword, $user['password'])) {
            echo "✓ Password verification successful!\n";
        } else {
            echo "✗ Password verification failed\n";
            echo "Testing plain text comparison...\n";
            if ($user['password'] === $testPassword) {
                echo "✓ Plain text password match (not recommended!)\n";
            } else {
                echo "✗ Plain text password does not match either\n";
                echo "Stored password: " . $user['password'] . "\n";
                echo "Test password: " . $testPassword . "\n";
            }
        }
    } else {
        echo "✗ Admin user not found\n";
        
        // Show all users
        $stmt = $pdo->query("SELECT username, role FROM users LIMIT 5");
        $users = $stmt->fetchAll();
        echo "Available users:\n";
        foreach ($users as $u) {
            echo "- " . $u['username'] . " (" . $u['role'] . ")\n";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}
?>