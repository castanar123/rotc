<?php
// Check inventory and transactions tables in rotc_db

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING INVENTORY TABLES IN ROTC_DB ===\n";
    
    // Check all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    
    echo "Available tables in rotc_db:\n";
    foreach ($tables as $table) {
        echo "- {$table[0]}\n";
    }
    
    echo "\n=== CHECKING REQUIRED TABLES ===\n";
    
    $required_tables = ['officers', 'inventory', 'transactions', 'borrowers'];
    
    foreach ($required_tables as $table_name) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table_name'");
        if ($stmt->rowCount() > 0) {
            echo "✓ $table_name table exists\n";
            
            // Show table structure
            $stmt = $pdo->query("DESCRIBE $table_name");
            $columns = $stmt->fetchAll();
            echo "  Columns: ";
            foreach ($columns as $column) {
                echo $column['Field'] . " ";
            }
            echo "\n";
            
            // Show count
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table_name");
            $count = $stmt->fetch();
            echo "  Records: {$count['count']}\n\n";
            
        } else {
            echo "✗ $table_name table MISSING\n";
        }
    }
    
    echo "\n=== TESTING DASHBOARD QUERIES ===\n";
    
    // Test officers query from dashboard.php line 24
    try {
        $officers_stmt = $pdo->query("SELECT * FROM officers WHERE status = 'active' ORDER BY rank_position, id");
        $officers = $officers_stmt->fetchAll();
        echo "✓ Officers query successful - found " . count($officers) . " officers\n";
    } catch (Exception $e) {
        echo "✗ Officers query failed: " . $e->getMessage() . "\n";
    }
    
    // Test inventory query
    try {
        $stats_stmt = $pdo->query("SELECT 
            COUNT(*) as total_items,
            SUM(available_quantity) as available_items,
            SUM(borrowed_quantity) as borrowed_items
            FROM inventory");
        $stats = $stats_stmt->fetch();
        echo "✓ Inventory query successful\n";
    } catch (Exception $e) {
        echo "✗ Inventory query failed: " . $e->getMessage() . "\n";
    }
    
    // Test transactions query
    try {
        $recent_stmt = $pdo->query("SELECT t.*, COALESCE(o.rank_position, CONCAT(IFNULL(o.rank, ''), ' ', IFNULL(o.position, ''))) as officer_name 
            FROM transactions t 
            LEFT JOIN officers o ON t.duty_officer_id = o.id 
            ORDER BY t.created_at DESC LIMIT 5");
        $recent_transactions = $recent_stmt->fetchAll();
        echo "✓ Transactions query successful\n";
    } catch (Exception $e) {
        echo "✗ Transactions query failed: " . $e->getMessage() . "\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>