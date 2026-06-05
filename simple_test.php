<?php
// Simple test for Cloudflare login
$_SERVER['HTTP_CF_RAY'] = 'test-ray';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Test Agent';

require_once 'includes/db.php';

echo "Database type: " . (isset($pdo) ? 'Connected' : 'Failed') . "\n";

// Test user lookup
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute(['admin']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "User found: " . $user['username'] . "\n";
    echo "Password hash: " . substr($user['password'], 0, 20) . "...\n";
    
    // Test password verification
    $passwordValid = password_verify('admin123', $user['password']);
    echo "Password valid: " . ($passwordValid ? 'YES' : 'NO') . "\n";
    
    if ($passwordValid) {
        // Test session creation
        try {
            $sessionToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $stmt = $pdo->prepare("
                INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $user['id'],
                $sessionToken,
                '127.0.0.1',
                'Test Agent',
                $expiresAt
            ]);
            
            echo "Session created: " . ($result ? 'YES' : 'NO') . "\n";
            
            // Test last login update
            $currentTime = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
            $updateResult = $stmt->execute([$currentTime, $user['id']]);
            
            echo "Last login updated: " . ($updateResult ? 'YES' : 'NO') . "\n";
            echo "LOGIN TEST: SUCCESS\n";
            
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            echo "LOGIN TEST: FAILED\n";
        }
    } else {
        echo "LOGIN TEST: FAILED - Invalid password\n";
    }
} else {
    echo "LOGIN TEST: FAILED - User not found\n";
}
?>