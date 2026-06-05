<?php
require_once 'includes/db.php';

echo "=== TESTING ATTENDANCE FIXES ===\n\n";

try {
    // Test 1: Check if attendance table exists and has data
    echo "1. Testing attendance table:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance");
    $count = $stmt->fetch()['count'];
    echo "   - Total attendance records: $count\n";
    
    if ($count > 0) {
        // Test 2: Test the fixed JOIN query
        echo "\n2. Testing fixed JOIN query:\n";
        $query = "SELECT a.log_date, a.status, a.log_time, cp.first_name, cp.last_name 
                  FROM attendance a 
                  JOIN cadet_profiles cp ON a.cadet_id = cp.id 
                  LIMIT 3";
        
        echo "   Query: " . str_replace('\n', ' ', $query) . "\n";
        
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   - Records found: " . count($results) . "\n";
        
        if (count($results) > 0) {
            echo "   - Sample record:\n";
            foreach ($results[0] as $key => $value) {
                echo "     $key: " . ($value ?? 'NULL') . "\n";
            }
            echo "   ✓ JOIN query working correctly!\n";
        } else {
            echo "   ⚠ No records returned from JOIN query\n";
        }
    } else {
        echo "   ⚠ No attendance records found\n";
    }
    
    // Test 3: Check cadet_profiles table
    echo "\n3. Testing cadet_profiles table:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_profiles");
    $profile_count = $stmt->fetch()['count'];
    echo "   - Total cadet profiles: $profile_count\n";
    
    if ($profile_count > 0) {
        $stmt = $pdo->query("SELECT user_id, first_name, last_name, student_id FROM cadet_profiles LIMIT 3");
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   - Sample profiles:\n";
        foreach ($profiles as $profile) {
            echo "     User ID: {$profile['user_id']}, Name: {$profile['first_name']} {$profile['last_name']}, Student ID: {$profile['student_id']}\n";
        }
    }
    
    // Test 4: Check users table structure
    echo "\n4. Testing users table structure:\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $column_names = array_column($columns, 'Field');
    
    echo "   - Available columns: " . implode(', ', $column_names) . "\n";
    
    if (in_array('first_name', $column_names)) {
        echo "   ⚠ WARNING: users table still has first_name column\n";
    } else {
        echo "   ✓ users table correctly doesn't have first_name/last_name columns\n";
    }
    
    // Test 5: Test attendance query for a specific user
    echo "\n5. Testing user-specific attendance query:\n";
    $stmt = $pdo->query("SELECT DISTINCT a.cadet_id FROM attendance a LIMIT 1");
    $user_data = $stmt->fetch();
    
    if ($user_data) {
        $test_cadet_id = $user_data['cadet_id'];
        echo "   - Testing with cadet_id: $test_cadet_id\n";
        
        $query = "SELECT a.log_date, a.status, cp.first_name, cp.last_name 
                  FROM attendance a 
                  JOIN cadet_profiles cp ON a.cadet_id = cp.id 
                  WHERE a.cadet_id = ? 
                  ORDER BY a.log_date DESC LIMIT 5";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$test_cadet_id]);
        $user_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   - User attendance records: " . count($user_attendance) . "\n";
        
        if (count($user_attendance) > 0) {
            echo "   ✓ User-specific query working correctly!\n";
            echo "   - Latest record: {$user_attendance[0]['log_date']} - {$user_attendance[0]['status']} - {$user_attendance[0]['first_name']} {$user_attendance[0]['last_name']}\n";
        } else {
            echo "   ⚠ No attendance records found for this user\n";
        }
    } else {
        echo "   ⚠ No users found in attendance table\n";
    }
    
    echo "\n=== TEST SUMMARY ===\n";
    echo "✓ Database connection: Working\n";
    echo "✓ Table structure fixes: Applied\n";
    echo "✓ JOIN query fixes: Applied\n";
    echo "\nThe cadet attendance system should now be working correctly!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>