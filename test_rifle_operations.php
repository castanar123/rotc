<?php
/**
 * Test Rifle Management Operations
 * Tests insert, update, delete, and assignment operations
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/rifle_functions.php';

echo "=== TESTING RIFLE MANAGEMENT OPERATIONS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Insert new rifle
echo "1. TESTING RIFLE INSERT\n";
echo "======================\n";
try {
    $test_rifle_number = 'TEST-' . time();
    $stmt = $link->prepare("INSERT INTO rifles (rifle_number, rifle_type, status, created_at) VALUES (?, 'mechanical rifle', 'available', NOW())");
    $stmt->bind_param("s", $test_rifle_number);
    $stmt->execute();
    $new_rifle_id = $link->insert_id;
    echo "✓ Successfully inserted rifle: {$test_rifle_number} (ID: {$new_rifle_id})\n";
} catch (Exception $e) {
    echo "❌ Rifle insert error: " . $e->getMessage() . "\n";
}

// Test 2: Update rifle
echo "\n2. TESTING RIFLE UPDATE\n";
echo "=======================\n";
try {
    if (isset($new_rifle_id)) {
        $stmt = $link->prepare("UPDATE rifles SET rifle_type = 'wooden rifle', status = 'maintenance' WHERE id = ?");
        $stmt->bind_param("i", $new_rifle_id);
        $stmt->execute();
        echo "✓ Successfully updated rifle ID {$new_rifle_id}\n";
        
        // Verify update
        $stmt = $link->prepare("SELECT rifle_number, rifle_type, status FROM rifles WHERE id = ?");
        $stmt->bind_param("i", $new_rifle_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rifle = $result->fetch_assoc();
        echo "   Updated rifle: {$rifle['rifle_number']} - Type: {$rifle['rifle_type']} - Status: {$rifle['status']}\n";
    }
} catch (Exception $e) {
    echo "❌ Rifle update error: " . $e->getMessage() . "\n";
}

// Test 3: Test rifle assignment
echo "\n3. TESTING RIFLE ASSIGNMENT\n";
echo "===========================\n";
try {
    // Get an available rifle
    $stmt = $link->prepare("SELECT id, rifle_number FROM rifles WHERE status = 'available' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rifle = $result->fetch_assoc();
        
        // Get an approved cadet
        $stmt = $link->prepare("
            SELECT cp.id, cp.first_name, cp.last_name 
            FROM cadet_profiles cp 
            JOIN users u ON cp.user_id = u.id 
            WHERE u.approval_status = 'approved' 
            AND u.role = 'basic-cadet' 
            LIMIT 1
        ");
        $stmt->execute();
        $cadet_result = $stmt->get_result();
        
        if ($cadet_result->num_rows > 0) {
            $cadet = $cadet_result->fetch_assoc();
            
            // Test assignment function
            if (function_exists('assignRifle')) {
                $assignment_result = assignRifle($rifle['id'], $cadet['id'], 1); // Admin user ID = 1
                if ($assignment_result['success']) {
                    echo "✓ Successfully assigned rifle {$rifle['rifle_number']} to {$cadet['first_name']} {$cadet['last_name']}\n";
                } else {
                    echo "❌ Assignment failed: {$assignment_result['message']}\n";
                }
            } else {
                echo "❌ assignRifle function not found\n";
            }
        } else {
            echo "⚠️ No approved cadets found for assignment test\n";
        }
    } else {
        echo "⚠️ No available rifles found for assignment test\n";
    }
} catch (Exception $e) {
    echo "❌ Rifle assignment error: " . $e->getMessage() . "\n";
}

// Test 4: Test rifle search/filter
echo "\n4. TESTING RIFLE SEARCH/FILTER\n";
echo "==============================\n";
try {
    // Test search by rifle_type
    $stmt = $link->prepare("SELECT COUNT(*) as count FROM rifles WHERE rifle_type = 'mechanical rifle'");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc();
    echo "✓ Mechanical rifles count: {$count['count']}\n";
    
    $stmt = $link->prepare("SELECT COUNT(*) as count FROM rifles WHERE rifle_type = 'wooden rifle'");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc();
    echo "✓ Wooden rifles count: {$count['count']}\n";
    
    // Test complex query with rifle_type
    $stmt = $link->prepare("
        SELECT r.rifle_number, r.rifle_type, r.status, 
               CASE WHEN ra.id IS NOT NULL THEN 'Assigned' ELSE 'Available' END as assignment_status
        FROM rifles r
        LEFT JOIN rifle_assignments ra ON r.id = ra.rifle_id AND ra.status = 'active'
        WHERE r.rifle_type IN ('mechanical rifle', 'wooden rifle')
        ORDER BY r.rifle_type, r.rifle_number
        LIMIT 5
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "✓ Complex rifle query successful:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   {$row['rifle_number']} - {$row['rifle_type']} - {$row['status']} - {$row['assignment_status']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Rifle search/filter error: " . $e->getMessage() . "\n";
}

// Test 5: Test document generation with actual data
echo "\n5. TESTING DOCUMENT GENERATION QUERY\n";
echo "====================================\n";
try {
    $doc_query = "
        SELECT 
            u.id as user_id,
            u.username,
            u.email,
            u.approval_status,
            cp.first_name,
            cp.middle_name,
            cp.last_name,
            cp.beneficiary_address,
            cp.region,
            cp.beneficiary_relationship,
            cp.platoon,
            cp.contact,
            cp.emergency_contact
        FROM users u
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' 
        AND u.approval_status = 'approved'
        AND cp.first_name IS NOT NULL
        AND cp.beneficiary_address IS NOT NULL
        AND cp.region IS NOT NULL
        ORDER BY cp.last_name, cp.first_name
    ";
    
    $stmt = $link->prepare($doc_query);
    $stmt->execute();
    $doc_result = $stmt->get_result();
    
    echo "✓ Document generation query successful: {$doc_result->num_rows} records\n";
    
    $count = 0;
    while ($row = $doc_result->fetch_assoc() && $count < 3) {
        echo "   User {$row['user_id']}: {$row['first_name']} {$row['middle_name']} {$row['last_name']}\n";
        echo "     Address: {$row['beneficiary_address']}\n";
        echo "     Region: {$row['region']}\n";
        echo "     Relationship: {$row['beneficiary_relationship']}\n";
        echo "     Status: {$row['approval_status']}\n";
        $count++;
    }
    
} catch (Exception $e) {
    echo "❌ Document generation query error: " . $e->getMessage() . "\n";
}

// Test 6: Clean up test data
echo "\n6. CLEANING UP TEST DATA\n";
echo "========================\n";
try {
    if (isset($new_rifle_id)) {
        $stmt = $link->prepare("DELETE FROM rifles WHERE id = ?");
        $stmt->bind_param("i", $new_rifle_id);
        $stmt->execute();
        echo "✓ Cleaned up test rifle ID {$new_rifle_id}\n";
    }
} catch (Exception $e) {
    echo "❌ Cleanup error: " . $e->getMessage() . "\n";
}

echo "\n=== RIFLE OPERATIONS TEST COMPLETED ===\n";
?>