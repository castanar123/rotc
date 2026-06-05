<?php
require_once 'includes/db.php';

echo "=== Testing Attendance System with Approval Filtering ===\n\n";

try {
    // Check users table structure
    echo "1. Checking users table structure...\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    $has_approval = false;
    $has_status = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'approval_status') {
            $has_approval = true;
            echo "   ✓ approval_status column found: {$column['Type']}\n";
        }
        if ($column['Field'] === 'status') {
            $has_status = true;
            echo "   ✓ status column found: {$column['Type']}\n";
        }
    }
    
    if (!$has_approval) {
        echo "   ✗ approval_status column missing!\n";
    }
    if (!$has_status) {
        echo "   ✗ status column missing!\n";
    }
    
    // Check current users and their approval status
    echo "\n2. Checking users and their approval status...\n";
    $stmt = $pdo->query("SELECT id, first_name, last_name, role, approval_status, status FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        $approval = $user['approval_status'] ?? 'NULL';
        $status = $user['status'] ?? 'NULL';
        echo "   User {$user['id']}: {$user['first_name']} {$user['last_name']} ({$user['role']}) - Approval: {$approval}, Status: {$status}\n";
    }
    
    // Test attendance queries with approval filtering
    echo "\n3. Testing attendance queries with approval filtering...\n";
    
    // Test today's attendance count
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT al.cadet_profile_id) as count 
        FROM attendance_logs al 
        JOIN cadet_profiles cp ON al.cadet_profile_id = cp.id
        JOIN users u ON cp.user_id = u.id 
        WHERE DATE(al.created_at) = CURDATE() AND u.approval_status = 'approved' AND u.status = 'active'
    ");
    $today_approved = $stmt->fetch()['count'];
    
    // Test today's attendance count without filtering
    $stmt = $pdo->query("SELECT COUNT(DISTINCT cadet_profile_id) as count FROM attendance_logs WHERE DATE(created_at) = CURDATE()");
    $today_all = $stmt->fetch()['count'];
    
    echo "   Today's attendance (approved only): {$today_approved}\n";
    echo "   Today's attendance (all users): {$today_all}\n";
    
    // Test recent logs with approval filtering
    $stmt = $pdo->query("
        SELECT al.*, u.first_name, u.last_name, u.role, u.approval_status, u.status, cp.full_name as cadet_name
        FROM attendance_logs al 
        JOIN cadet_profiles cp ON al.cadet_profile_id = cp.id
        JOIN users u ON cp.user_id = u.id 
        WHERE u.approval_status = 'approved' AND u.status = 'active'
        ORDER BY al.created_at DESC 
        LIMIT 5
    ");
    $approved_logs = $stmt->fetchAll();
    
    echo "\n   Recent attendance logs (approved users only):";
    if (empty($approved_logs)) {
        echo " No approved attendance logs found\n";
    } else {
        echo "\n";
        foreach ($approved_logs as $log) {
            echo "     - {$log['cadet_name']} ({$log['role']}) at {$log['created_at']}\n";
        }
    }
    
    // Test recent logs without filtering
    $stmt = $pdo->query("
        SELECT al.*, u.first_name, u.last_name, u.role, u.approval_status, u.status, cp.full_name as cadet_name
        FROM attendance_logs al 
        JOIN cadet_profiles cp ON al.cadet_profile_id = cp.id
        JOIN users u ON cp.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 5
    ");
    $all_logs = $stmt->fetchAll();
    
    echo "\n   Recent attendance logs (all users):";
    if (empty($all_logs)) {
        echo " No attendance logs found\n";
    } else {
        echo "\n";
        foreach ($all_logs as $log) {
            $approval = $log['approval_status'] ?? 'NULL';
            $status = $log['status'] ?? 'NULL';
            echo "     - {$log['cadet_name']} ({$log['role']}) - Approval: {$approval}, Status: {$status} at {$log['created_at']}\n";
        }
    }
    
    // Test manual attendance dropdown query
    echo "\n4. Testing manual attendance dropdown query...\n";
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, cp.student_number, cp.full_name
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' AND u.status = 'active' 
        ORDER BY u.last_name, u.first_name
    ");
    $approved_cadets = $stmt->fetchAll();
    
    echo "   Approved basic-cadets for manual attendance: " . count($approved_cadets) . "\n";
    foreach ($approved_cadets as $cadet) {
        $student_num = $cadet['student_number'] ?? 'N/A';
        $full_name = $cadet['full_name'] ?? ($cadet['first_name'] . ' ' . $cadet['last_name']);
        echo "     - {$full_name} (ID: {$cadet['id']}, Student#: {$student_num})\n";
    }
    
    // Check if there are any unapproved basic-cadets
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, approval_status, status 
        FROM users 
        WHERE role = 'basic-cadet' AND (approval_status != 'approved' OR status != 'active')
        ORDER BY last_name, first_name
    ");
    $unapproved_cadets = $stmt->fetchAll();
    
    echo "\n   Unapproved/inactive basic-cadets (should NOT appear in attendance): " . count($unapproved_cadets) . "\n";
    foreach ($unapproved_cadets as $cadet) {
        $approval = $cadet['approval_status'] ?? 'NULL';
        $status = $cadet['status'] ?? 'NULL';
        echo "     - {$cadet['first_name']} {$cadet['last_name']} (ID: {$cadet['id']}) - Approval: {$approval}, Status: {$status}\n";
    }
    
    echo "\n=== Attendance System Test Complete ===\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>