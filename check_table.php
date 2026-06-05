<?php
// Force Cloudflare environment
$_SERVER['HTTP_CF_RAY'] = 'test-ray';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '127.0.0.1';
require_once 'includes/db.php';

echo "=== USER_SESSIONS TABLE STRUCTURE ===\n";
$stmt = $pdo->query('PRAGMA table_info(user_sessions)');
while($row = $stmt->fetch()) {
    echo $row['name'] . ' - ' . $row['type'] . "\n";
}

echo "\n=== AUDIT_LOGS TABLE STRUCTURE ===\n";
try {
    $stmt = $pdo->query('PRAGMA table_info(audit_logs)');
    while($row = $stmt->fetch()) {
        echo $row['name'] . ' - ' . $row['type'] . "\n";
    }
} catch (Exception $e) {
    echo "Table doesn't exist: " . $e->getMessage() . "\n";
}
?>