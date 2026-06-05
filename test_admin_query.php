<?php
require_once 'includes/db.php';

echo "Testing Admin Dashboard Queries\n";
echo "================================\n\n";

try {
    // Test 1: Simple query (what we know works)
    echo "Test 1: Simple query\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'basic_cadet' AND status = 'active'");
    $simple_result = $stmt->fetch()['total'];
    echo "Result: $simple_result basic cadets\n\n";
    
    // Test 2: Admin dashboard query with LEFT JOIN
    echo "Test 2: Admin dashboard query (with LEFT JOIN)\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic_cadet' 
        AND u.status = 'active'
    ");
    $admin_result = $stmt->fetch()['total'];
    echo "Result: $admin_result basic cadets\n\n";
    
    // Test 3: Check if cadet_profiles table exists and has data
    echo "Test 3: Checking cadet_profiles table\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM cadet_profiles");
        $cp_count = $stmt->fetch()['total'];
        echo "Cadet profiles count: $cp_count\n";
        
        if ($cp_count > 0) {
            $stmt = $pdo->query("SELECT user_id, first_name, last_name FROM cadet_profiles LIMIT 5");
            echo "Sample cadet profiles:\n";
            while ($row = $stmt->fetch()) {
                echo "- User ID: {$row['user_id']}, Name: {$row['first_name']} {$row['last_name']}\n";
            }
        }
    } catch (Exception $e) {
        echo "Error accessing cadet_profiles: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 4: Check which users have cadet profiles
    echo "Test 4: Users with/without cadet profiles\n";
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.role, u.status, 
               CASE WHEN cp.user_id IS NOT NULL THEN 'Yes' ELSE 'No' END as has_profile
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic_cadet' AND u.status = 'active'
    ");
    
    echo "Basic cadets (active) and their profile status:\n";
    while ($row = $stmt->fetch()) {
        echo "- ID: {$row['id']}, Username: {$row['username']}, Has Profile: {$row['has_profile']}\n";
    }
    echo "\n";
    
    // Analysis
    echo "ANALYSIS:\n";
    echo "=========\n";
    if ($simple_result != $admin_result) {
        echo "❌ PROBLEM: Simple query returns $simple_result, but admin query returns $admin_result\n";
        echo "This suggests the LEFT JOIN with cadet_profiles is causing issues.\n";
        echo "\nSOLUTION: The admin dashboard should use the simple query without LEFT JOIN.\n";
    } else {
        echo "✅ Both queries return the same result ($simple_result).\n";
        echo "The issue might be elsewhere in the admin dashboard code.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>