<?php
require_once 'includes/db.php';

echo "=== FIXING USER APPROVALS ===\n\n";

try {
    // 1. Check current user status
    echo "1. Current user distribution:\n";
    $stmt = $pdo->query("
        SELECT 
            role, 
            approval_status, 
            status,
            COUNT(*) as count 
        FROM users 
        GROUP BY role, approval_status, status 
        ORDER BY role, approval_status
    ");
    $users = $stmt->fetchAll();
    foreach ($users as $user) {
        echo "   {$user['role']} - {$user['approval_status']}/{$user['status']}: {$user['count']} users\n";
    }
    echo "\n";
    
    // 2. Find basic-cadet users that need approval
    echo "2. Basic-cadet users needing approval:\n";
    $stmt = $pdo->query("
        SELECT 
            u.id, 
            u.username, 
            u.first_name, 
            u.last_name, 
            u.approval_status, 
            u.status,
            cp.first_name as cp_first, 
            cp.last_name as cp_last,
            cp.status as cp_status
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' 
        ORDER BY u.approval_status, u.id
        LIMIT 10
    ");
    $basic_cadets = $stmt->fetchAll();
    
    foreach ($basic_cadets as $user) {
        $profile_name = $user['cp_first'] ? "{$user['cp_first']} {$user['cp_last']}" : "No profile";
        echo "   ID {$user['id']}: {$user['username']} ({$user['first_name']} {$user['last_name']}) - {$user['approval_status']}/{$user['status']}\n";
        echo "     Profile: {$profile_name} - Status: " . ($user['cp_status'] ?: 'NULL') . "\n";
    }
    echo "\n";
    
    // 3. Auto-approve basic-cadet users that are pending
    echo "3. Auto-approving pending basic-cadet users...\n";
    $stmt = $pdo->prepare("
        UPDATE users 
        SET approval_status = 'approved', status = 'active' 
        WHERE role = 'basic-cadet' 
        AND approval_status = 'pending'
    ");
    $stmt->execute();
    $approved_count = $stmt->rowCount();
    echo "   ✓ Approved {$approved_count} basic-cadet users\n\n";
    
    // 4. Fix cadet profile statuses
    echo "4. Fixing cadet profile statuses...\n";
    $stmt = $pdo->prepare("
        UPDATE cadet_profiles cp
        JOIN users u ON cp.user_id = u.id
        SET cp.status = 'Active'
        WHERE u.role = 'basic-cadet' 
        AND u.approval_status = 'approved'
        AND (cp.status IS NULL OR cp.status != 'Active')
    ");
    $stmt->execute();
    $profile_count = $stmt->rowCount();
    echo "   ✓ Fixed {$profile_count} cadet profile statuses\n\n";
    
    // 5. Check results
    echo "5. Updated user distribution:\n";
    $stmt = $pdo->query("
        SELECT 
            role, 
            approval_status, 
            status,
            COUNT(*) as count 
        FROM users 
        WHERE role = 'basic-cadet'
        GROUP BY role, approval_status, status 
        ORDER BY approval_status
    ");
    $updated_users = $stmt->fetchAll();
    foreach ($updated_users as $user) {
        echo "   {$user['role']} - {$user['approval_status']}/{$user['status']}: {$user['count']} users\n";
    }
    echo "\n";
    
    // 6. Test document generation query again
    echo "6. Testing document generation query after fixes...\n";
    $sql = "SELECT 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'Other'
                END as ms_level,
                cp.gender,
                COUNT(*) as count
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.role = 'basic-cadet'
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status = 'Active'
                AND cp.gender IS NOT NULL
            GROUP BY 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'Other'
                END, 
                cp.gender
            ORDER BY ms_level, cp.gender";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Query now returns " . count($results) . " rows:\n";
    foreach ($results as $row) {
        echo "   {$row['ms_level']} {$row['gender']}: {$row['count']} cadets\n";
    }
    
    if (empty($results)) {
        echo "   Still no data - checking year_level values...\n";
        $stmt = $pdo->query("
            SELECT DISTINCT year_level, COUNT(*) as count
            FROM users u
            JOIN cadet_profiles cp ON u.id = cp.user_id
            WHERE u.role = 'basic-cadet' 
            AND u.approval_status = 'approved'
            AND cp.status = 'Active'
            GROUP BY year_level
        ");
        $year_levels = $stmt->fetchAll();
        echo "   Available year_level values:\n";
        foreach ($year_levels as $yl) {
            $level = $yl['year_level'] ?: 'NULL';
            echo "     '{$level}': {$yl['count']} users\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== APPROVAL FIX COMPLETE ===\n";
?>
