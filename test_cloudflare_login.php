<?php
// Test script to simulate Cloudflare environment and test login

// Simulate Cloudflare environment headers
$_SERVER['HTTP_CF_RAY'] = 'test-ray-id';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '192.168.1.100';
$_SERVER['HTTP_HOST'] = 'rotc.lspulbrotcunit.online';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/generate%20qr/login.php';

// Simulate POST data for login
$_POST['username'] = 'admin';
$_POST['password'] = 'admin123';
$_POST['login'] = 'Login';

// Start output buffering to capture any output
ob_start();

// Capture any errors
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

echo "=== CLOUDFLARE LOGIN TEST ===\n";
echo "Testing login with admin/admin123\n";
echo "Simulating Cloudflare environment...\n\n";

try {
    // Include the database file to test SQLite initialization
    require_once 'includes/db.php';
    
    echo "Database connection: SUCCESS\n";
    echo "Database type: " . DB_TYPE . "\n";
    
    if (DB_TYPE === 'sqlite') {
        echo "SQLite database path: " . DB_PATH . "\n";
        
        // Test if users table exists and has data
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $result = $stmt->fetch();
        echo "Users in database: " . $result['count'] . "\n";
        
        // Test admin user lookup
        $stmt = $pdo->prepare("SELECT username, role, two_factor_enabled FROM users WHERE username = ?");
        $stmt->execute(['admin']);
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "Admin user found: " . $admin['username'] . " (Role: " . $admin['role'] . ", 2FA: " . ($admin['two_factor_enabled'] ? 'Yes' : 'No') . ")\n";
        } else {
            echo "ERROR: Admin user not found!\n";
        }
    }
    
    echo "\n=== Testing Login Process ===\n";
    
    // Now test the actual login
    session_start();
    
    // Include login logic (but capture output)
    ob_start();
    include 'login.php';
    $login_output = ob_get_clean();
    
    echo "Login process completed.\n";
    echo "Session data: " . print_r($_SESSION, true) . "\n";
    
    if (isset($_SESSION['user_id'])) {
        echo "LOGIN SUCCESS: User ID " . $_SESSION['user_id'] . " logged in\n";
        echo "User role: " . ($_SESSION['role'] ?? 'unknown') . "\n";
    } else {
        echo "LOGIN FAILED: No session created\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Get any buffered output
$output = ob_get_clean();
echo $output;

echo "\n=== Test Complete ===\n";
?>