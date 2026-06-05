<?php
// Check complete database structure for inventory system

try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING ALL TABLES IN ROTC_DB ===\n";
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "Table: $table\n";
    }
    
    echo "\n=== CHECKING REQUIRED TABLES FOR INVENTORY SYSTEM ===\n";
    $required_tables = ['officers', 'inventory', 'borrowers', 'transactions', 'transaction_items'];
    
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ $table table EXISTS\n";
        } else {
            echo "✗ $table table MISSING\n";
        }
    }
    
    echo "\n=== TESTING DASHBOARD QUERY ===\n";
    try {
        $officers_stmt = $pdo->query("SELECT * FROM officers WHERE status = 'active' ORDER BY rank_position, id");
        $officers = $officers_stmt->fetchAll();
        echo "✓ Officers query successful - Found " . count($officers) . " officers\n";
    } catch (Exception $e) {
        echo "✗ Officers query failed: " . $e->getMessage() . "\n";
    }
    
    try {
        $stats_stmt = $pdo->query("SELECT 
            COUNT(*) as total_items,
            SUM(available_quantity) as available_items,
            SUM(borrowed_quantity) as borrowed_items
            FROM inventory");
        $stats = $stats_stmt->fetch();
        echo "✓ Inventory stats query successful\n";
    } catch (Exception $e) {
        echo "✗ Inventory stats query failed: " . $e->getMessage() . "\n";
    }
    
    try {
        $recent_stmt = $pdo->query("SELECT t.*, COALESCE(o.rank_position, CONCAT(IFNULL(o.rank, ''), ' ', IFNULL(o.position, ''))) as officer_name 
            FROM transactions t 
            LEFT JOIN officers o ON t.duty_officer_id = o.id 
            ORDER BY t.created_at DESC LIMIT 5");
        $recent_transactions = $recent_stmt->fetchAll();
        echo "✓ Recent transactions query successful\n";
    } catch (Exception $e) {
        echo "✗ Recent transactions query failed: " . $e->getMessage() . "\n";
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>