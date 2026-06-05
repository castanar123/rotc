<?php
require_once 'includes/db.php';

echo "Starting cadet_profiles table migration...\n";

try {
    // Check current table structure
    echo "\n=== CURRENT TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $existing_columns = array_column($stmt->fetchAll(), 'Field');
    
    // Define missing columns to add
    $columns_to_add = [
        'beneficiary_address' => 'TEXT NULL COMMENT "Address of beneficiary"',
        'region' => 'VARCHAR(100) NULL COMMENT "Region/Province"',
        'beneficiary_relationship' => 'VARCHAR(100) NULL COMMENT "Relationship to beneficiary"'
    ];
    
    $added_columns = [];
    
    foreach ($columns_to_add as $column_name => $column_definition) {
        if (!in_array($column_name, $existing_columns)) {
            echo "Adding column: $column_name...\n";
            $sql = "ALTER TABLE cadet_profiles ADD COLUMN $column_name $column_definition";
            $pdo->exec($sql);
            $added_columns[] = $column_name;
            echo "✅ Column '$column_name' added successfully!\n";
        } else {
            echo "⚠️ Column '$column_name' already exists, skipping...\n";
        }
    }
    
    if (!empty($added_columns)) {
        echo "\n=== MIGRATION COMPLETED ===\n";
        echo "Added columns: " . implode(', ', $added_columns) . "\n";
    } else {
        echo "\n=== NO CHANGES NEEDED ===\n";
        echo "All required columns already exist.\n";
    }
    
    // Verify the changes
    echo "\n=== UPDATED TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll();
    
    echo "Current columns in cadet_profiles table:\n";
    foreach ($columns as $column) {
        if (in_array($column['Field'], array_keys($columns_to_add))) {
            echo "✅ {$column['Field']} ({$column['Type']}) - {$column['Null']}\n";
        }
    }
    
    echo "\n🎉 Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>