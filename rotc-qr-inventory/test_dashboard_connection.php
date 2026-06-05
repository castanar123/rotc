<?php
// Dashboard database connection fix
require_once "includes/db.php";

// Test database connection and required tables
function testDatabaseConnection($pdo) {
    try {
        // Test officers table
        $stmt = $pdo->query("SELECT COUNT(*) FROM officers");
        $officers_count = $stmt->fetchColumn();
        
        // Test inventory table
        $stmt = $pdo->query("SELECT COUNT(*) FROM items");
        $inventory_count = $stmt->fetchColumn();
        
        // Test transactions table
        $stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
        $transactions_count = $stmt->fetchColumn();
        
        return [
            "success" => true,
            "officers" => $officers_count,
            "inventory" => $inventory_count,
            "transactions" => $transactions_count
        ];
        
    } catch (Exception $e) {
        return [
            "success" => false,
            "error" => $e->getMessage()
        ];
    }
}

$db_test = testDatabaseConnection($pdo);
if (!$db_test["success"]) {
    die("Database Error: " . $db_test["error"]);
}

echo "Database connection successful:\n";
echo "Officers: {$db_test["officers"]}\n";
echo "Inventory items: {$db_test["inventory"]}\n";
echo "Transactions: {$db_test["transactions"]}\n";
?>