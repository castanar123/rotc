<?php
// Check table structure to identify the status column issue
require_once 'includes/db_connection.php';

try {
    // Check cadet_profiles table structure
    echo "=== CADET_PROFILES TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] === 'status') {
            echo "Status column: {$column['Type']}\n";
            echo "Default: {$column['Default']}\n";
        }
    }
    
    // Check attendance table structure
    echo "\n=== ATTENDANCE TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE attendance");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] === 'status') {
            echo "Status column: {$column['Type']}\n";
            echo "Default: {$column['Default']}\n";
        }
    }
    
    // Check registration_requests table structure
    echo "\n=== REGISTRATION_REQUESTS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE registration_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] === 'status') {
            echo "Status column: {$column['Type']}\n";
            echo "Default: {$column['Default']}\n";
        }
    }
    
    // Check users table structure
    echo "\n=== USERS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] === 'status') {
            echo "Status column: {$column['Type']}\n";
            echo "Default: {$column['Default']}\n";
        }
    }
    
    // Check rifles table structure if it exists
    echo "\n=== RIFLES TABLE STRUCTURE ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE rifles");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ($column['Field'] === 'status') {
                echo "Status column: {$column['Type']}\n";
                echo "Default: {$column['Default']}\n";
            }
        }
    } catch (Exception $e) {
        echo "Rifles table doesn't exist or error: " . $e->getMessage() . "\n";
    }
    
    // Check rifle_assignments table structure if it exists
    echo "\n=== RIFLE_ASSIGNMENTS TABLE STRUCTURE ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE rifle_assignments");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ($column['Field'] === 'status') {
                echo "Status column: {$column['Type']}\n";
                echo "Default: {$column['Default']}\n";
            }
        }
    } catch (Exception $e) {
        echo "Rifle_assignments table doesn't exist or error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>