<?php
try {
    $db = new PDO('sqlite:data/rotc_db.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== SQLite Database Analysis ===\n\n";
    
    // Get all tables
    $tables = $db->query('SELECT name FROM sqlite_master WHERE type="table"')->fetchAll(PDO::FETCH_COLUMN);
    
    foreach($tables as $table) {
        echo "Table: $table\n";
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "Records: $count\n";
        
        // Show first few records for each table
        $stmt = $db->query("SELECT * FROM $table LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            echo "Sample data:\n";
            foreach($rows as $row) {
                echo "  " . json_encode($row) . "\n";
            }
        }
        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
    
    // Special focus on items table (which might contain rifles)
    echo "\n=== DETAILED ITEMS TABLE ANALYSIS ===\n";
    $stmt = $db->query("SELECT * FROM items");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total items in database: " . count($items) . "\n\n";
    
    foreach($items as $item) {
        echo "Item ID: {$item['id']}\n";
        echo "Name: {$item['item_name']}\n";
        echo "Description: {$item['description']}\n";
        echo "Total Quantity: {$item['total_quantity']}\n";
        echo "Available: {$item['available_quantity']}\n";
        echo "Location: {$item['location']}\n";
        echo "QR Code: {$item['qr_code']}\n";
        echo "\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>