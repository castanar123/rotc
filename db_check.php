<?php
require_once 'includes/db.php';

try {
    // Check total users
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM users');
    $total = $stmt->fetch()['total'];
    echo "Total users: $total\n";
    
    if ($total == 0) {
        echo "DATABASE IS EMPTY - NO USERS FOUND!\n";
        echo "This is why admin dashboard shows 0 counts.\n";
        echo "Solution: Register some users first.\n";
    } else {
        echo "\nFirst 10 users:\n";
        $stmt = $pdo->query('SELECT id, username, role, status FROM users LIMIT 10');
        while ($row = $stmt->fetch()) {
            echo $row['id'] . ' | ' . $row['username'] . ' | ' . $row['role'] . ' | ' . $row['status'] . "\n";
        }
        
        // Check specific query from admin dashboard
        echo "\nTesting admin dashboard query:\n";
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'basic_cadet' AND status = 'active'");
        $basic_cadets = $stmt->fetch()['total'];
        echo "Basic cadets (active): $basic_cadets\n";
        
        // Show all roles
        echo "\nAll roles in database:\n";
        $stmt = $pdo->query('SELECT role, COUNT(*) as count FROM users GROUP BY role');
        while ($row = $stmt->fetch()) {
            echo $row['role'] . ': ' . $row['count'] . "\n";
        }
        
        // Show all statuses
        echo "\nAll statuses in database:\n";
        $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM users GROUP BY status');
        while ($row = $stmt->fetch()) {
            echo $row['status'] . ': ' . $row['count'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>