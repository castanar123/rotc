<?php
require_once 'includes/db.php';

echo "=== Approving Test Users for System Testing ===\n\n";

try {
    // 1. Check current user status
    echo "1. Current user approval status:\n";
    $stmt = $pdo->query("
        SELECT role, approval_status, COUNT(*) as count
        FROM users 
        GROUP BY role, approval_status
        ORDER BY role, approval_status
    ");
    $status_counts = $stmt->fetchAll();
    
    foreach ($status_counts as $status) {
        echo "   {$status['role']} - {$status['approval_status']}: {$status['count']} users\n";
    }
    
    // 2. Find basic-cadet users that need approval
    echo "\n2. Basic-cadet users needing approval:\n";
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.approval_status, 
               cp.first_name as cp_first, cp.last_name as cp_last
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'pending'
        LIMIT 5
    ");
    $pending_users = $stmt->fetchAll();
    
    if (empty($pending_users)) {
        echo "   No pending basic-cadet users found\n";
    } else {
        foreach ($pending_users as $user) {
            $profile_name = $user['cp_first'] ? "{$user['cp_first']} {$user['cp_last']}" : "No profile";
            echo "   ID: {$user['id']} - {$user['first_name']} {$user['last_name']} - Profile: {$profile_name}\n";
        }
        
        // 3. Approve first 3 users for testing
        echo "\n3. Approving first 3 basic-cadet users for testing...\n";
        $users_to_approve = array_slice($pending_users, 0, 3);
        
        foreach ($users_to_approve as $user) {
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET approval_status = 'approved', status = 'active'
                WHERE id = ?
            ");
            $update_stmt->execute([$user['id']]);
            
            // Also update cadet profile status if exists
            $profile_stmt = $pdo->prepare("
                UPDATE cadet_profiles 
                SET status = 'active'
                WHERE user_id = ?
            ");
            $profile_stmt->execute([$user['id']]);
            
            echo "   ✓ Approved user ID {$user['id']}: {$user['first_name']} {$user['last_name']}\n";
        }
    }
    
    // 4. Check final approval status
    echo "\n4. Updated user approval status:\n";
    $stmt = $pdo->query("
        SELECT role, approval_status, COUNT(*) as count
        FROM users 
        GROUP BY role, approval_status
        ORDER BY role, approval_status
    ");
    $final_status = $stmt->fetchAll();
    
    foreach ($final_status as $status) {
        echo "   {$status['role']} - {$status['approval_status']}: {$status['count']} users\n";
    }
    
    // 5. Test approved basic-cadets with profiles
    echo "\n5. Approved basic-cadets with complete profiles:\n";
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.approval_status,
               CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) as full_name,
               cp.student_number, cp.beneficiary_address, cp.region
        FROM users u
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved'
        LIMIT 5
    ");
    $approved_cadets = $stmt->fetchAll();
    
    if (empty($approved_cadets)) {
        echo "   No approved basic-cadets with profiles found\n";
    } else {
        foreach ($approved_cadets as $cadet) {
            $address = $cadet['beneficiary_address'] ?: 'No address';
            $region = $cadet['region'] ?: 'No region';
            echo "   ✓ {$cadet['full_name']} (Student: {$cadet['student_number']}) - {$address}, {$region}\n";
        }
    }
    
    echo "\n=== User Approval Complete ===\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>