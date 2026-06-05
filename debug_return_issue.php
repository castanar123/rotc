<?php
// Debug the return issue by checking database structure and sample data

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUGGING RETURN ISSUE ===\n\n";
    
    // Check what tables exist
    echo "1. AVAILABLE TABLES:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = [];
    while ($row = $stmt->fetch()) {
        $tables[] = $row[0];
        echo "   - {$row[0]}\n";
    }
    
    // Check transactions table structure
    if (in_array('transactions', $tables)) {
        echo "\n2. TRANSACTIONS TABLE STRUCTURE:\n";
        $stmt = $pdo->query("DESCRIBE transactions");
        while ($row = $stmt->fetch()) {
            echo "   - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Default']}\n";
        }
        
        echo "\n3. RECENT TRANSACTIONS (last 3):\n";
        $stmt = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 3");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "   ID: {$row['id']}, TXN_ID: " . ($row['transaction_id'] ?? 'NULL') . ", Type: " . ($row['type'] ?? 'NULL') . "\n";
            echo "   Borrower: " . ($row['borrower_name'] ?? $row['borrower_id'] ?? 'NULL') . "\n";
            echo "   Status: " . ($row['status'] ?? 'NULL') . "\n---\n";
        }
    }
    
    // Check borrowed_items table
    if (in_array('borrowed_items', $tables)) {
        echo "\n4. BORROWED_ITEMS TABLE STRUCTURE:\n";
        $stmt = $pdo->query("DESCRIBE borrowed_items");
        while ($row = $stmt->fetch()) {
            echo "   - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Default']}\n";
        }
        
        echo "\n5. BORROWED_ITEMS SAMPLE DATA:\n";
        $stmt = $pdo->query("SELECT * FROM borrowed_items ORDER BY id DESC LIMIT 3");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "   " . json_encode($row) . "\n";
        }
    }
    
    // Check transaction_items table
    if (in_array('transaction_items', $tables)) {
        echo "\n6. TRANSACTION_ITEMS TABLE STRUCTURE:\n";
        $stmt = $pdo->query("DESCRIBE transaction_items");
        while ($row = $stmt->fetch()) {
            echo "   - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Default']}\n";
        }
        
        echo "\n7. TRANSACTION_ITEMS SAMPLE DATA:\n";
        $stmt = $pdo->query("SELECT * FROM transaction_items ORDER BY id DESC LIMIT 3");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "   " . json_encode($row) . "\n";
        }
    }
    
    // Check items table
    if (in_array('items', $tables)) {
        echo "\n8. ITEMS TABLE - RETURNABLE FIELD:\n";
        $stmt = $pdo->query("SELECT item_name, can_be_returned FROM items WHERE can_be_returned IS NOT NULL LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "   {$row['item_name']}: {$row['can_be_returned']}\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
