<?php
// Simple test of borrowed_items table query

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TESTING BORROWED_ITEMS TABLE DIRECT QUERY ===\n\n";
    
    // Check borrowed_items table structure
    echo "1. BORROWED_ITEMS TABLE STRUCTURE:\n";
    $stmt = $pdo->query("DESCRIBE borrowed_items");
    while ($row = $stmt->fetch()) {
        echo "   - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Default']}\n";
    }
    
    // Count total borrowed items
    echo "\n2. TOTAL BORROWED ITEMS:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM borrowed_items");
    $count = $stmt->fetch()['count'];
    echo "   Total records: $count\n";
    
    // Show sample data
    echo "\n3. SAMPLE BORROWED ITEMS DATA:\n";
    $stmt = $pdo->query("SELECT * FROM borrowed_items ORDER BY id DESC LIMIT 3");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   ID: {$row['id']}, Item ID: {$row['item_id']}, Borrower: {$row['borrower_name']}\n";
        echo "   Quantity: " . ($row['quantity_borrowed'] ?? $row['quantity'] ?? 'N/A') . ", Status: " . ($row['status'] ?? 'N/A') . "\n";
        echo "   Borrow Date: " . ($row['borrowed_date'] ?? $row['borrow_date'] ?? 'N/A') . "\n---\n";
    }
    
    // Test the direct query like in the API
    echo "\n4. TESTING API-STYLE QUERY:\n";
    $sql = "
        SELECT 
            bi.id as transaction_id,
            bi.borrower_name,
            i.item_name,
            bi.quantity_borrowed AS quantity_borrowed,
            bi.expected_return_date,
            bi.borrow_date as borrowed_date,
            bi.status
        FROM borrowed_items bi
        JOIN items i ON bi.item_id = i.id
        WHERE bi.status = 'borrowed'
        ORDER BY bi.borrow_date DESC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Query returned " . count($results) . " results:\n";
    foreach ($results as $row) {
        echo "   - {$row['borrower_name']}: {$row['item_name']} (Qty: {$row['quantity_borrowed']})\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
