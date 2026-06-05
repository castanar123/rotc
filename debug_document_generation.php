<?php
require_once 'includes/db.php';

echo "=== DOCUMENT GENERATION DEBUG ===\n\n";

try {
    // 1. Check database connection
    echo "1. Testing database connection...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    echo "   ✓ Database connected. Total users: {$total_users}\n\n";
    
    // 2. Check role distribution
    echo "2. Checking user roles...\n";
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC");
    $roles = $stmt->fetchAll();
    foreach ($roles as $role) {
        echo "   {$role['role']}: {$role['count']} users\n";
    }
    echo "\n";
    
    // 3. Check basic-cadet users specifically
    echo "3. Checking basic-cadet users...\n";
    $stmt = $pdo->query("
        SELECT 
            approval_status, 
            status, 
            COUNT(*) as count 
        FROM users 
        WHERE role = 'basic-cadet' 
        GROUP BY approval_status, status
    ");
    $basic_cadets = $stmt->fetchAll();
    foreach ($basic_cadets as $bc) {
        echo "   basic-cadet - {$bc['approval_status']}/{$bc['status']}: {$bc['count']} users\n";
    }
    echo "\n";
    
    // 4. Check year_level values
    echo "4. Checking year_level values in users table...\n";
    $stmt = $pdo->query("
        SELECT 
            year_level, 
            COUNT(*) as count 
        FROM users 
        WHERE role = 'basic-cadet' 
        GROUP BY year_level 
        ORDER BY count DESC
    ");
    $year_levels = $stmt->fetchAll();
    foreach ($year_levels as $yl) {
        $level = $yl['year_level'] ?: 'NULL';
        echo "   Year Level '{$level}': {$yl['count']} users\n";
    }
    echo "\n";
    
    // 5. Check cadet_profiles table
    echo "5. Checking cadet_profiles table...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cadet_profiles");
    $total_profiles = $stmt->fetch()['total'];
    echo "   Total cadet profiles: {$total_profiles}\n";
    
    $stmt = $pdo->query("
        SELECT 
            cp.status, 
            COUNT(*) as count 
        FROM cadet_profiles cp 
        GROUP BY cp.status
    ");
    $profile_statuses = $stmt->fetchAll();
    foreach ($profile_statuses as $ps) {
        $status = $ps['status'] ?: 'NULL';
        echo "   Profile status '{$status}': {$ps['count']} profiles\n";
    }
    echo "\n";
    
    // 6. Check gender distribution in cadet_profiles
    echo "6. Checking gender distribution...\n";
    $stmt = $pdo->query("
        SELECT 
            gender, 
            COUNT(*) as count 
        FROM cadet_profiles 
        WHERE gender IS NOT NULL 
        GROUP BY gender
    ");
    $genders = $stmt->fetchAll();
    foreach ($genders as $g) {
        echo "   Gender '{$g['gender']}': {$g['count']} profiles\n";
    }
    echo "\n";
    
    // 7. Test the exact query from generate_document.php
    echo "7. Testing document generation query...\n";
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
    
    echo "   Query returned " . count($results) . " rows:\n";
    foreach ($results as $row) {
        echo "   {$row['ms_level']} {$row['gender']}: {$row['count']} cadets\n";
    }
    
    if (empty($results)) {
        echo "   ❌ NO DATA RETURNED - This is why documents are empty!\n\n";
        
        // 8. Debug why no data is returned
        echo "8. Debugging why no data is returned...\n";
        
        // Check each condition separately
        $conditions = [
            "u.role = 'basic-cadet'" => "SELECT COUNT(*) as count FROM users u WHERE u.role = 'basic-cadet'",
            "u.status = 'active'" => "SELECT COUNT(*) as count FROM users u WHERE u.role = 'basic-cadet' AND u.status = 'active'",
            "u.approval_status = 'approved'" => "SELECT COUNT(*) as count FROM users u WHERE u.role = 'basic-cadet' AND u.status = 'active' AND u.approval_status = 'approved'",
            "cp.status = 'Active'" => "SELECT COUNT(*) as count FROM users u LEFT JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role = 'basic-cadet' AND u.status = 'active' AND u.approval_status = 'approved' AND cp.status = 'Active'",
            "cp.gender IS NOT NULL" => "SELECT COUNT(*) as count FROM users u LEFT JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.role = 'basic-cadet' AND u.status = 'active' AND u.approval_status = 'approved' AND cp.status = 'Active' AND cp.gender IS NOT NULL"
        ];
        
        foreach ($conditions as $desc => $query) {
            $stmt = $pdo->query($query);
            $count = $stmt->fetch()['count'];
            echo "   {$desc}: {$count} records\n";
        }
        
        // 9. Show sample data that might be causing issues
        echo "\n9. Sample basic-cadet data:\n";
        $stmt = $pdo->query("
            SELECT 
                u.id, u.username, u.role, u.status, u.approval_status, u.year_level,
                cp.first_name, cp.last_name, cp.status as profile_status, cp.gender
            FROM users u 
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.role = 'basic-cadet' 
            LIMIT 5
        ");
        $samples = $stmt->fetchAll();
        
        foreach ($samples as $sample) {
            echo "   User ID {$sample['id']}: {$sample['username']} | Role: {$sample['role']} | Status: {$sample['status']} | Approval: {$sample['approval_status']} | Year: " . ($sample['year_level'] ?: 'NULL') . "\n";
            echo "     Profile: " . ($sample['first_name'] ? "{$sample['first_name']} {$sample['last_name']}" : 'No profile') . " | Profile Status: " . ($sample['profile_status'] ?: 'NULL') . " | Gender: " . ($sample['gender'] ?: 'NULL') . "\n";
        }
    } else {
        echo "   ✓ Query working correctly!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
