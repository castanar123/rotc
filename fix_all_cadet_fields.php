<?php
require_once 'includes/db.php';

echo "Fixing all Cadet Profiles required fields...\n\n";

try {
    // Check current structure
    echo "Current cadet_profiles table structure:\n";
    $structure_query = "DESCRIBE cadet_profiles";
    $stmt = $pdo->prepare($structure_query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_fields = [];
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) - Null: {$column['Null']}, Default: '{$column['Default']}'\n";
        if ($column['Null'] === 'NO' && $column['Default'] === null && $column['Extra'] !== 'auto_increment') {
            $required_fields[] = $column['Field'];
        }
    }
    
    echo "\nRequired fields without defaults: " . implode(', ', $required_fields) . "\n";
    
    // Fix all required fields to allow NULL or have defaults
    $field_fixes = [
        'user_id' => 'INT(11) NULL DEFAULT NULL',
        'student_number' => 'VARCHAR(50) NULL DEFAULT NULL',
        'first_name' => 'VARCHAR(100) NOT NULL DEFAULT \"\"',
        'last_name' => 'VARCHAR(100) NOT NULL DEFAULT \"\"',
        'year_level' => 'VARCHAR(10) NOT NULL DEFAULT \"MS1\"',
        'gender' => 'VARCHAR(10) NOT NULL DEFAULT \"Male\"',
        'status' => 'VARCHAR(20) NOT NULL DEFAULT \"active\"'
    ];
    
    foreach ($field_fixes as $field => $definition) {
        if (in_array($field, $required_fields)) {
            echo "\nFixing field: {$field}\n";
            $alter_query = "ALTER TABLE cadet_profiles MODIFY COLUMN {$field} {$definition}";
            $pdo->exec($alter_query);
            echo "SUCCESS: Fixed {$field}\n";
        }
    }
    
    // Now try to add sample data
    echo "\nAdding sample cadet data...\n";
    
    $sample_cadets = [
        ['John', 'Doe', 'MS1', 'Male', '2024001', 'john.doe@example.com'],
        ['Jane', 'Smith', 'MS2', 'Female', '2024002', 'jane.smith@example.com'],
        ['Mike', 'Johnson', 'MS3', 'Male', '2024003', 'mike.johnson@example.com'],
        ['Sarah', 'Wilson', 'MS4', 'Female', '2024004', 'sarah.wilson@example.com'],
        ['David', 'Brown', 'MS1', 'Male', '2024005', 'david.brown@example.com']
    ];
    
    // Simple insert with minimal required fields
    $insert_query = "INSERT INTO cadet_profiles (first_name, last_name, year_level, gender, student_id, email, status, academic_year, semester) VALUES (?, ?, ?, ?, ?, ?, 'active', '2024-2025', 'First')";
    $stmt = $pdo->prepare($insert_query);
    
    foreach ($sample_cadets as $cadet) {
        try {
            $stmt->execute($cadet);
            echo "Added cadet: {$cadet[0]} {$cadet[1]} ({$cadet[2]})\n";
        } catch (PDOException $e) {
            echo "Error adding {$cadet[0]} {$cadet[1]}: {$e->getMessage()}\n";
        }
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
    
    echo "\n=== ALL CADET PROFILES ISSUES FIXED ===\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}
?>