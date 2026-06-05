<?php
require_once 'includes/db.php';

echo "Fixing Cadet Profiles user_id issue...\n\n";

try {
    // Check current structure
    echo "Checking cadet_profiles table structure:\n";
    $structure_query = "DESCRIBE cadet_profiles";
    $stmt = $pdo->prepare($structure_query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_user_id = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'user_id') {
            $has_user_id = true;
            echo "Found user_id column: {$column['Type']}, Null: {$column['Null']}, Default: {$column['Default']}\n";
            break;
        }
    }
    
    if ($has_user_id) {
        echo "\nUpdating user_id column to allow NULL or have default value...\n";
        $pdo->exec("ALTER TABLE cadet_profiles MODIFY COLUMN user_id INT(11) NULL DEFAULT NULL");
        echo "SUCCESS: Updated user_id column to allow NULL\n";
    }
    
    // Test adding sample data again
    $count_query = "SELECT COUNT(*) as total FROM cadet_profiles";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute();
    $total_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\nCurrent cadet count: {$total_result['total']}\n";
    
    if ($total_result['total'] == 0) {
        echo "\nAdding sample cadet data...\n";
        
        $sample_cadets = [
            ['John', 'Doe', 'MS1', 'Male', '2024001', 'john.doe@example.com'],
            ['Jane', 'Smith', 'MS2', 'Female', '2024002', 'jane.smith@example.com'],
            ['Mike', 'Johnson', 'MS3', 'Male', '2024003', 'mike.johnson@example.com'],
            ['Sarah', 'Wilson', 'MS4', 'Female', '2024004', 'sarah.wilson@example.com'],
            ['David', 'Brown', 'MS1', 'Male', '2024005', 'david.brown@example.com']
        ];
        
        $insert_query = "INSERT INTO cadet_profiles (first_name, last_name, year_level, gender, student_id, email, status, academic_year, semester, user_id) VALUES (?, ?, ?, ?, ?, ?, 'active', '2024-2025', 'First', NULL)";
        $stmt = $pdo->prepare($insert_query);
        
        foreach ($sample_cadets as $cadet) {
            $stmt->execute($cadet);
            echo "Added cadet: {$cadet[0]} {$cadet[1]} ({$cadet[2]})\n";
        }
        
        echo "SUCCESS: Added sample cadet data\n";
    }
    
    // Test the document generation query
    echo "\nTesting document generation query...\n";
    $stats_query = "SELECT 
        COUNT(*) as total_cadets,
        SUM(CASE WHEN year_level = 'MS1' THEN 1 ELSE 0 END) as ms1_count,
        SUM(CASE WHEN year_level = 'MS2' THEN 1 ELSE 0 END) as ms2_count,
        SUM(CASE WHEN year_level = 'MS3' THEN 1 ELSE 0 END) as ms3_count,
        SUM(CASE WHEN year_level = 'MS4' THEN 1 ELSE 0 END) as ms4_count,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count
        FROM cadet_profiles WHERE status = 'active'";
    
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute();
    $cadet_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "SUCCESS: Document generation query works!\n";
    echo "Statistics: " . json_encode($cadet_stats, JSON_PRETTY_PRINT) . "\n";
    
    echo "\n=== DOCUMENT GENERATION DATABASE ISSUE FIXED ===\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
?>