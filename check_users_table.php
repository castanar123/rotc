<?php
require 'includes/db.php';

echo "Users table structure:\n";
try {
    $stmt = $pdo->query('DESCRIBE users');
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nSample users data:\n";
try {
    $stmt = $pdo->query('SELECT id, username, role, approval_status FROM users LIMIT 3');
    while($row = $stmt->fetch()) {
        echo "ID: {$row['id']}, Username: {$row['username']}, Role: {$row['role']}, Approval: {$row['approval_status']}\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>