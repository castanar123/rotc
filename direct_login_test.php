<?php
// Direct login test without includes to isolate the issue
echo "<h1>Direct Login Authentication Test</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .debug{background:#f0f0f0;padding:10px;margin:10px 0;border:1px solid #ccc;} .error{background:#ffebee;color:#c62828;padding:10px;margin:10px 0;} .success{background:#e8f5e9;color:#2e7d32;padding:10px;margin:10px 0;}</style>";

try {
    echo "<div class='debug'><strong>Step 1:</strong> Testing database connection</div>";
    
    // Database connection
    $host = 'localhost';
    $dbname = 'rotc_db';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>Database connection successful</div>";
    
    echo "<div class='debug'><strong>Step 2:</strong> Looking for test_admin user</div>";
    
    // Check if test_admin exists
    $stmt = $pdo->prepare("SELECT id, username, email, password, role, failed_login_attempts, locked_until, status FROM users WHERE username = ? OR email = ?");
    $stmt->execute(['test_admin', 'test_admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<div class='success'>User found: " . htmlspecialchars($user['username']) . "</div>";
        echo "<div class='debug'>User details:<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Username: " . htmlspecialchars($user['username']) . "<br>";
        echo "Email: " . htmlspecialchars($user['email']) . "<br>";
        echo "Role: " . htmlspecialchars($user['role']) . "<br>";
        echo "Status: " . htmlspecialchars($user['status'] ?? 'N/A') . "<br>";
        echo "Failed attempts: " . $user['failed_login_attempts'] . "<br>";
        echo "Locked until: " . ($user['locked_until'] ?? 'Not locked') . "<br>";
        echo "Password hash: " . substr($user['password'], 0, 20) . "...<br>";
        echo "</div>";
        
        echo "<div class='debug'><strong>Step 3:</strong> Testing password verification</div>";
        
        $test_password = 'admin123';
        $password_valid = password_verify($test_password, $user['password']);
        
        if ($password_valid) {
            echo "<div class='success'>Password verification SUCCESSFUL for 'admin123'</div>";
            
            // Check if user status is active
            if (isset($user['status']) && $user['status'] !== 'active') {
                echo "<div class='error'>User status is not active: " . htmlspecialchars($user['status']) . "</div>";
            } else {
                echo "<div class='success'>User status is active or status field not set</div>";
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                echo "<div class='error'>Account is locked until: " . $user['locked_until'] . "</div>";
            } else {
                echo "<div class='success'>Account is not locked</div>";
            }
            
        } else {
            echo "<div class='error'>Password verification FAILED for 'admin123'</div>";
            
            // Test with other common passwords
            $test_passwords = ['password', '123456', 'admin', 'test', 'test123'];
            echo "<div class='debug'>Testing other common passwords:</div>";
            
            foreach ($test_passwords as $pwd) {
                if (password_verify($pwd, $user['password'])) {
                    echo "<div class='success'>Password '$pwd' works!</div>";
                    break;
                } else {
                    echo "<div class='debug'>Password '$pwd' failed</div>";
                }
            }
        }
        
    } else {
        echo "<div class='error'>User 'test_admin' not found</div>";
        
        echo "<div class='debug'><strong>Step 2b:</strong> Checking all users in database</div>";
        
        $stmt = $pdo->prepare("SELECT id, username, email, role, status FROM users ORDER BY id DESC LIMIT 10");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($users) {
            echo "<div class='debug'>Recent users in database:<br>";
            foreach ($users as $u) {
                echo "ID: {$u['id']}, Username: {$u['username']}, Email: {$u['email']}, Role: {$u['role']}, Status: " . ($u['status'] ?? 'N/A') . "<br>";
            }
            echo "</div>";
        } else {
            echo "<div class='error'>No users found in database</div>";
        }
    }
    
    echo "<div class='debug'><strong>Step 4:</strong> Testing session functionality</div>";
    
    // Test session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "<div class='success'>Session started successfully</div>";
    } else {
        echo "<div class='success'>Session already active</div>";
    }
    
    echo "<div class='debug'>Session ID: " . session_id() . "</div>";
    
    // Test setting session variables
    $_SESSION['test'] = 'working';
    if (isset($_SESSION['test']) && $_SESSION['test'] === 'working') {
        echo "<div class='success'>Session variables working correctly</div>";
    } else {
        echo "<div class='error'>Session variables not working</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Exception occurred: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='debug'>File: " . $e->getFile() . "<br>Line: " . $e->getLine() . "</div>";
}

echo "<hr>";
echo "<h3>Test Complete</h3>";
echo "<p><a href='login_debug.php'>Go to Login Debug Page</a> | <a href='login.php'>Go to Regular Login</a></p>";
?>