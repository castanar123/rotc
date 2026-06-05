<?php
require_once 'includes/db.php';

echo "=== USERS TABLE STRUCTURE ===\n";
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo "Field: " . $column['Field'] . " | Type: " . $column['Type'] . "\n";
    }
    
    echo "\n=== SAMPLE USER DATA ===\n";
    $stmt = $pdo->query("SELECT * FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Available columns in users table:\n";
        foreach (array_keys($user) as $column) {
            echo "- " . $column . "\n";
        }
    }
    
    echo "\n=== CADET PROFILES TABLE ===\n";
    try {
        $stmt = $pdo->query("DESCRIBE cadet_profiles");
        $cadet_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($cadet_columns as $column) {
            echo "Field: " . $column['Field'] . " | Type: " . $column['Type'] . "\n";
        }
        
        echo "\n=== SAMPLE CADET PROFILE DATA ===\n";
        $stmt = $pdo->query("SELECT * FROM cadet_profiles LIMIT 1");
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($profile) {
            echo "Available columns in cadet_profiles table:\n";
            foreach (array_keys($profile) as $column) {
                echo "- " . $column . "\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "Cadet profiles table error: " . $e->getMessage() . "\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>