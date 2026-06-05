<?php
require_once 'includes/db.php';

try {
    echo "=== TESTING MISSING IDS QUERY ===\n";
    
    // Test the exact query from missing_ids.php
    $stmt = $pdo->query("
        SELECT mir.*, 
               cp.first_name, cp.last_name, cp.student_id, cp.year_level, cp.section, cp.facebook_profile,
               u.username
        FROM missing_id_requests mir
        JOIN cadet_profiles cp ON mir.cadet_id = cp.id
        JOIN users u ON cp.user_id = u.id
        ORDER BY mir.created_at DESC
        LIMIT 5
    ");
    
    $results = $stmt->fetchAll();
    echo "✅ Query executed successfully!\n";
    echo "Found " . count($results) . " records\n";
    
    if (count($results) > 0) {
        echo "\nSample record columns:\n";
        foreach (array_keys($results[0]) as $column) {
            echo "  - $column\n";
        }
    }
    
} catch(Exception $e) {
    echo "❌ Query failed: " . $e->getMessage() . "\n";
    
    // Let's check if the tables exist
    echo "\n=== CHECKING TABLES ===\n";
    
    try {
        $stmt = $pdo->query("SELECT 1 FROM missing_id_requests LIMIT 1");
        echo "✅ missing_id_requests table exists\n";
    } catch(Exception $e) {
        echo "❌ missing_id_requests table: " . $e->getMessage() . "\n";
    }
    
    try {
        $stmt = $pdo->query("SELECT 1 FROM cadet_profiles LIMIT 1");
        echo "✅ cadet_profiles table exists\n";
    } catch(Exception $e) {
        echo "❌ cadet_profiles table: " . $e->getMessage() . "\n";
    }
    
    try {
        $stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
        echo "✅ users table exists\n";
    } catch(Exception $e) {
        echo "❌ users table: " . $e->getMessage() . "\n";
    }
}
?>