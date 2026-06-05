<?php
/**
 * Direct Migration Execution Script
 * Runs the rifle_type column migration directly
 */

require_once 'includes/db.php';

echo "Starting rifle_type migration...\n";

try {
    // Check if rifle_type column already exists
    $result = $pdo->query("DESCRIBE rifles");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('rifle_type', $columns)) {
        echo "✅ rifle_type column already exists!\n";
    } else {
        echo "Adding rifle_type column...\n";
        
        // Add rifle_type column
        $sql = "ALTER TABLE rifles ADD COLUMN rifle_type ENUM('mechanical rifle', 'wooden rifle') NOT NULL DEFAULT 'mechanical rifle' AFTER rifle_number";
        $pdo->exec($sql);
        echo "✅ rifle_type column added successfully!\n";
        
        // Add index for rifle_type
        $sql = "ALTER TABLE rifles ADD INDEX idx_rifle_type (rifle_type)";
        $pdo->exec($sql);
        echo "✅ Index added for rifle_type column!\n";
    }
    
    // Update existing rifles with proper types
    echo "Updating existing rifle types...\n";
    
    // Set wooden rifle for numeric rifle numbers
    $sql = "UPDATE rifles SET rifle_type = 'wooden rifle' WHERE rifle_number REGEXP '^[0-9]+$'";
    $result = $pdo->exec($sql);
    echo "✅ Updated $result rifles to 'wooden rifle' type\n";
    
    // Set mechanical rifle for R-prefixed rifle numbers
    $sql = "UPDATE rifles SET rifle_type = 'mechanical rifle' WHERE rifle_number REGEXP '^R[0-9]+$'";
    $result = $pdo->exec($sql);
    echo "✅ Updated $result rifles to 'mechanical rifle' type (R-prefix)\n";
    
    // Set mechanical rifle for TEST-prefixed rifle numbers
    $sql = "UPDATE rifles SET rifle_type = 'mechanical rifle' WHERE rifle_number LIKE 'TEST%'";
    $result = $pdo->exec($sql);
    echo "✅ Updated $result rifles to 'mechanical rifle' type (TEST-prefix)\n";
    
    // Show final statistics
    echo "\n📊 Final Statistics:\n";
    $result = $pdo->query("SELECT rifle_type, COUNT(*) as count FROM rifles GROUP BY rifle_type");
    while ($row = $result->fetch()) {
        echo "- {$row['rifle_type']}: {$row['count']} rifles\n";
    }
    
    echo "\n🎉 Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>