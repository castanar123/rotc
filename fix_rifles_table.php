<?php
require_once 'includes/db.php';

echo "=== Fixing Rifles Table Structure ===\n\n";

try {
    // 1. Check current rifles table structure
    echo "1. Current rifles table structure:\n";
    $stmt = $pdo->query("DESCRIBE rifles");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "   {$column['Field']} - {$column['Type']} - Default: {$column['Default']}\n";
    }
    
    // 2. Check if assigned_to column exists
    $column_names = array_column($columns, 'Field');
    
    if (!in_array('assigned_to', $column_names)) {
        echo "\n2. Adding missing 'assigned_to' column...\n";
        $pdo->exec("ALTER TABLE rifles ADD COLUMN assigned_to INT NULL AFTER rifle_type");
        echo "   ✓ Added assigned_to column\n";
        
        // Add foreign key constraint
        try {
            $pdo->exec("ALTER TABLE rifles ADD CONSTRAINT fk_rifles_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL");
            echo "   ✓ Added foreign key constraint for assigned_to\n";
        } catch (PDOException $e) {
            echo "   ⚠ Could not add foreign key constraint: " . $e->getMessage() . "\n";
        }
    } else {
        echo "\n2. assigned_to column already exists\n";
    }
    
    // 3. Show final structure
    echo "\n3. Final rifles table structure:\n";
    $stmt = $pdo->query("DESCRIBE rifles");
    $final_columns = $stmt->fetchAll();
    
    foreach ($final_columns as $column) {
        echo "   {$column['Field']} - {$column['Type']} - Default: {$column['Default']}\n";
    }
    
    // 4. Test sample data
    echo "\n4. Sample rifles data:\n";
    $stmt = $pdo->query("
        SELECT r.id, r.serial_number, r.rifle_type, r.assigned_to,
               CONCAT(IFNULL(cp.first_name, ''), ' ', IFNULL(cp.last_name, '')) as assigned_name
        FROM rifles r
        LEFT JOIN users u ON r.assigned_to = u.id
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        LIMIT 5
    ");
    $samples = $stmt->fetchAll();
    
    if (empty($samples)) {
        echo "   No rifles found\n";
    } else {
        foreach ($samples as $sample) {
            $assigned = $sample['assigned_to'] ? "Assigned to: {$sample['assigned_name']} (ID: {$sample['assigned_to']})" : "Unassigned";
            echo "   ID: {$sample['id']} - Serial: {$sample['serial_number']} - Type: {$sample['rifle_type']} - {$assigned}\n";
        }
    }
    
    echo "\n=== Rifles Table Fix Complete ===\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>