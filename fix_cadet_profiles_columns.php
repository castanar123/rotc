<?php
require_once 'includes/db.php';

echo "=== Adding Missing Columns to Cadet Profiles ===\n\n";

try {
    // 1. Check current cadet_profiles table structure
    echo "1. Current cadet_profiles table structure:\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll();
    
    $column_names = array_column($columns, 'Field');
    
    foreach ($columns as $column) {
        echo "   {$column['Field']} - {$column['Type']}\n";
    }
    
    // 2. Add missing columns
    $missing_columns = [];
    
    if (!in_array('beneficiary_address', $column_names)) {
        echo "\n2. Adding 'beneficiary_address' column...\n";
        $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN beneficiary_address TEXT NULL");
        echo "   ✓ Added beneficiary_address column\n";
        $missing_columns[] = 'beneficiary_address';
    }
    
    if (!in_array('region', $column_names)) {
        echo "\n3. Adding 'region' column...\n";
        $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN region VARCHAR(100) NULL");
        echo "   ✓ Added region column\n";
        $missing_columns[] = 'region';
    }
    
    if (!in_array('beneficiary_relationship', $column_names)) {
        echo "\n4. Adding 'beneficiary_relationship' column...\n";
        $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN beneficiary_relationship VARCHAR(50) NULL");
        echo "   ✓ Added beneficiary_relationship column\n";
        $missing_columns[] = 'beneficiary_relationship';
    }
    
    if (empty($missing_columns)) {
        echo "\n2. All required columns already exist\n";
    }
    
    // 3. Show final structure
    echo "\n5. Final cadet_profiles table structure:\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $final_columns = $stmt->fetchAll();
    
    foreach ($final_columns as $column) {
        echo "   {$column['Field']} - {$column['Type']}\n";
    }
    
    // 4. Add sample data for testing
    echo "\n6. Adding sample data for approved users...\n";
    $stmt = $pdo->query("
        SELECT cp.id, cp.user_id, cp.first_name, cp.last_name, cp.beneficiary_address, cp.region
        FROM cadet_profiles cp
        JOIN users u ON cp.user_id = u.id
        WHERE u.approval_status = 'approved'
        LIMIT 3
    ");
    $approved_profiles = $stmt->fetchAll();
    
    foreach ($approved_profiles as $profile) {
        if (empty($profile['beneficiary_address']) || empty($profile['region'])) {
            $sample_address = "123 Sample Street, Barangay Sample, Sample City";
            $sample_region = "Region XII";
            $sample_relationship = "Parent";
            
            $update_stmt = $pdo->prepare("
                UPDATE cadet_profiles 
                SET beneficiary_address = ?, region = ?, beneficiary_relationship = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$sample_address, $sample_region, $sample_relationship, $profile['id']]);
            
            echo "   ✓ Updated profile for {$profile['first_name']} {$profile['last_name']} with sample data\n";
        }
    }
    
    // 5. Test document generation query
    echo "\n7. Testing document generation query...\n";
    $stmt = $pdo->query("
        SELECT u.first_name, u.last_name, 
               CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) as full_name,
               cp.student_number, cp.beneficiary_address, cp.region, cp.beneficiary_relationship
        FROM users u 
        JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' 
        LIMIT 3
    ");
    $test_data = $stmt->fetchAll();
    
    if (empty($test_data)) {
        echo "   ⚠ No approved basic-cadets found for testing\n";
    } else {
        foreach ($test_data as $data) {
            echo "   ✓ {$data['full_name']} - Student: {$data['student_number']} - Address: {$data['beneficiary_address']} - Region: {$data['region']}\n";
        }
    }
    
    echo "\n=== Cadet Profiles Columns Fix Complete ===\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>