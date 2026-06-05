<?php
// Debug test that writes output to a file
$debug_file = 'debug_results.txt';
$output = "";

try {
    $output .= "=== LOGIN DEBUG TEST RESULTS ===\n";
    $output .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Database connection
    $host = 'localhost';
    $dbname = 'rotc_db';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $output .= "✓ Database connection successful\n";
    
    // Check if test_admin exists
    $stmt = $pdo->prepare("SELECT id, username, email, password, role, failed_login_attempts, locked_until, status FROM users WHERE username = ? OR email = ?");
    $stmt->execute(['test_admin', 'test_admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $output .= "✓ User 'test_admin' found\n";
        $output .= "  - ID: " . $user['id'] . "\n";
        $output .= "  - Username: " . $user['username'] . "\n";
        $output .= "  - Email: " . $user['email'] . "\n";
        $output .= "  - Role: " . $user['role'] . "\n";
        $output .= "  - Status: " . ($user['status'] ?? 'NULL') . "\n";
        $output .= "  - Failed attempts: " . $user['failed_login_attempts'] . "\n";
        $output .= "  - Locked until: " . ($user['locked_until'] ?? 'NULL') . "\n";
        $output .= "  - Password hash: " . substr($user['password'], 0, 30) . "...\n";
        
        // Test password verification
        $test_password = 'admin123';
        $password_valid = password_verify($test_password, $user['password']);
        
        if ($password_valid) {
            $output .= "✓ Password verification SUCCESSFUL for 'admin123'\n";
            
            // Check status
            if (isset($user['status']) && $user['status'] !== 'active') {
                $output .= "✗ User status is not active: " . $user['status'] . "\n";
            } else {
                $output .= "✓ User status is active or not set\n";
            }
            
            // Check if locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $output .= "✗ Account is locked until: " . $user['locked_until'] . "\n";
            } else {
                $output .= "✓ Account is not locked\n";
            }
            
        } else {
            $output .= "✗ Password verification FAILED for 'admin123'\n";
            
            // Test with other passwords
            $test_passwords = ['password', '123456', 'admin', 'test', 'test123'];
            $output .= "Testing other passwords:\n";
            
            foreach ($test_passwords as $pwd) {
                if (password_verify($pwd, $user['password'])) {
                    $output .= "✓ Password '$pwd' works!\n";
                    break;
                } else {
                    $output .= "✗ Password '$pwd' failed\n";
                }
            }
        }
        
    } else {
        $output .= "✗ User 'test_admin' not found\n";
        
        // Check all users
        $stmt = $pdo->prepare("SELECT id, username, email, role, status FROM users ORDER BY id DESC LIMIT 5");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($users) {
            $output .= "Recent users in database:\n";
            foreach ($users as $u) {
                $output .= "  - ID: {$u['id']}, Username: {$u['username']}, Email: {$u['email']}, Role: {$u['role']}, Status: " . ($u['status'] ?? 'NULL') . "\n";
            }
        } else {
            $output .= "✗ No users found in database\n";
        }
    }
    
    // Test session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        $output .= "✓ Session started successfully\n";
    } else {
        $output .= "✓ Session already active\n";
    }
    
    $output .= "Session ID: " . session_id() . "\n";
    
    // Now test the actual login process simulation
    $output .= "\n=== SIMULATING LOGIN PROCESS ===\n";
    
    if ($user && $password_valid) {
        // Simulate what login.php should do
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        $output .= "✓ Session variables set:\n";
        $output .= "  - user_id: " . $_SESSION['user_id'] . "\n";
        $output .= "  - username: " . $_SESSION['username'] . "\n";
        $output .= "  - role: " . $_SESSION['role'] . "\n";
        $output .= "  - logged_in: " . ($_SESSION['logged_in'] ? 'true' : 'false') . "\n";
        
        $output .= "\n✓ LOGIN SHOULD WORK - All checks passed!\n";
    } else {
        $output .= "\n✗ LOGIN WOULD FAIL - Issues found above\n";
    }
    
} catch (Exception $e) {
    $output .= "\n✗ Exception: " . $e->getMessage() . "\n";
    $output .= "File: " . $e->getFile() . "\n";
    $output .= "Line: " . $e->getLine() . "\n";
}

// Write to file
file_put_contents($debug_file, $output);

// Also display on page
echo "<h1>Debug Test Complete</h1>";
echo "<p>Results written to: <a href='$debug_file' target='_blank'>$debug_file</a></p>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";
echo "<p><a href='login.php'>Test Login Page</a></p>";
?>