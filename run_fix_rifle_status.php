<?php
// Run the rifle status ENUM fix
require_once 'includes/db_connection.php';

try {
    echo "Fixing rifle status ENUM...\n";
    
    // Update rifles table status ENUM to include 'assigned'
    $sql = "ALTER TABLE rifles 
            MODIFY COLUMN status ENUM('available','assigned','borrowed','maintenance','lost','damaged') 
            NOT NULL DEFAULT 'available'";
    
    $pdo->exec($sql);
    echo "✓ Rifle status ENUM updated successfully!\n";
    
    // Update any existing 'borrowed' status to 'assigned' for consistency
    $sql2 = "UPDATE rifles SET status = 'assigned' WHERE status = 'borrowed'";
    $stmt = $pdo->prepare($sql2);
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "✓ Updated {$affected} rifles from 'borrowed' to 'assigned' status\n";
    
    // Verify the fix by checking the new ENUM definition
    echo "\nVerifying fix...\n";
    $stmt = $pdo->query("DESCRIBE rifles");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] === 'status') {
            echo "New status column: {$column['Type']}\n";
            echo "Default: {$column['Default']}\n";
        }
    }
    
    echo "\n✅ Fix completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>