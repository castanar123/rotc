<?php
require_once 'includes/db.php';

try {
    echo "=== CADET_PROFILE TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll();
    
    echo "Current columns in cadet_profiles table:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']}) - {$column['Null']} - Default: {$column['Default']}\n";
    }
    
    echo "\n=== CHECKING FOR MISSING COLUMNS ===\n";
    $required_columns = [
        'beneficiary_address',
        'region',
        'beneficiary_name',
        'beneficiary_relationship',
        'emergency_contact',
        'emergency_phone'
    ];
    
    $existing_columns = array_column($columns, 'Field');
    $missing_columns = array_diff($required_columns, $existing_columns);
    
    if (empty($missing_columns)) {
        echo "All required columns exist!\n";
    } else {
        echo "Missing columns:\n";
        foreach ($missing_columns as $missing) {
            echo "- $missing\n";
        }
    }
    
    echo "\n=== SAMPLE DATA ===\n";
    $stmt = $pdo->query("SELECT * FROM cadet_profiles LIMIT 3");
    $sample_data = $stmt->fetchAll();
    
    if (!empty($sample_data)) {
        echo "Sample cadet profile data:\n";
        foreach ($sample_data as $row) {
            echo "ID: {$row['id']}, Name: {$row['first_name']} {$row['last_name']}\n";
        }
    } else {
        echo "No sample data found.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>