<?php
require_once 'includes/db.php';

echo "=== Creating Test Cadet Profiles ===\n\n";

try {
    // 1. Find approved basic-cadet users without profiles
    echo "1. Finding approved basic-cadet users without profiles:\n";
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' AND cp.id IS NULL
        LIMIT 5
    ");
    $users_without_profiles = $stmt->fetchAll();
    
    if (empty($users_without_profiles)) {
        echo "   No approved users without profiles found\n";
    } else {
        foreach ($users_without_profiles as $user) {
            echo "   ID: {$user['id']} - {$user['first_name']} {$user['last_name']} - {$user['email']}\n";
        }
        
        // 2. Create profiles for these users
        echo "\n2. Creating cadet profiles...\n";
        $counter = 1;
        
        foreach ($users_without_profiles as $user) {
            $student_number = "2024-" . str_pad($counter, 4, '0', STR_PAD_LEFT);
            $first_name = $user['first_name'] ?: "Test";
            $last_name = $user['last_name'] ?: "Cadet{$counter}";
            
            $insert_stmt = $pdo->prepare("
                INSERT INTO cadet_profiles (
                    user_id, first_name, middle_name, last_name, student_number,
                    beneficiary_address, region, beneficiary_relationship,
                    phone, date_of_birth, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $insert_stmt->execute([
                $user['id'],
                $first_name,
                'M.',
                $last_name,
                $student_number,
                "123 Sample Street, Barangay Test, Sample City",
                "Region XII",
                "Parent",
                "09123456789",
                "2000-01-01"
            ]);
            
            echo "   ✓ Created profile for {$first_name} {$last_name} - Student: {$student_number}\n";
            $counter++;
        }
    }
    
    // 3. Check existing profiles and update missing data
    echo "\n3. Updating existing profiles with missing data...\n";
    $stmt = $pdo->query("
        SELECT cp.id, cp.user_id, cp.first_name, cp.last_name, cp.student_number,
               cp.beneficiary_address, cp.region
        FROM cadet_profiles cp
        JOIN users u ON cp.user_id = u.id
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved'
    ");
    $existing_profiles = $stmt->fetchAll();
    
    foreach ($existing_profiles as $profile) {
        $needs_update = false;
        $updates = [];
        $params = [];
        
        if (empty($profile['beneficiary_address'])) {
            $updates[] = "beneficiary_address = ?";
            $params[] = "456 Updated Street, Barangay Sample, Test City";
            $needs_update = true;
        }
        
        if (empty($profile['region'])) {
            $updates[] = "region = ?";
            $params[] = "Region XI";
            $needs_update = true;
        }
        
        if (empty($profile['student_number'])) {
            $updates[] = "student_number = ?";
            $params[] = "2024-" . str_pad($profile['id'], 4, '0', STR_PAD_LEFT);
            $needs_update = true;
        }
        
        if ($needs_update) {
            $params[] = $profile['id'];
            $update_sql = "UPDATE cadet_profiles SET " . implode(', ', $updates) . " WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute($params);
            
            echo "   ✓ Updated profile for {$profile['first_name']} {$profile['last_name']}\n";
        }
    }
    
    // 4. Test final result
    echo "\n4. Final approved basic-cadets with complete profiles:\n";
    $stmt = $pdo->query("
        SELECT u.id, u.first_name as u_first, u.last_name as u_last,
               CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) as full_name,
               cp.student_number, cp.beneficiary_address, cp.region, cp.beneficiary_relationship
        FROM users u
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved'
        ORDER BY cp.student_number
    ");
    $final_profiles = $stmt->fetchAll();
    
    if (empty($final_profiles)) {
        echo "   No approved basic-cadets with profiles found\n";
    } else {
        foreach ($final_profiles as $profile) {
            echo "   ✓ {$profile['full_name']} (Student: {$profile['student_number']})\n";
            echo "     Address: {$profile['beneficiary_address']}\n";
            echo "     Region: {$profile['region']} - Relationship: {$profile['beneficiary_relationship']}\n\n";
        }
    }
    
    echo "=== Test Profiles Creation Complete ===\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>