<?php
// Test supply quantity insertion

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TESTING SUPPLY QUANTITY INSERTION ===\n\n";
    
    // Simulate the supply data that would come from the frontend
    $testData = [
        'item_name' => 'Test Item ' . date('H:i:s'),
        'category' => 'supplies',
        'unit' => 'pcs',
        'quantity' => 15,
        'can_be_returned' => 'returnable'
    ];
    
    echo "Test data: " . json_encode($testData) . "\n\n";
    
    // Check what columns exist in items table
    echo "1. ITEMS TABLE QUANTITY COLUMNS:\n";
    $stmt = $pdo->query("DESCRIBE items");
    while ($row = $stmt->fetch()) {
        if (strpos($row['Field'], 'quantity') !== false || in_array($row['Field'], ['stock', 'total', 'available', 'qty'])) {
            echo "   - {$row['Field']}: {$row['Type']} {$row['Null']} {$row['Default']}\n";
        }
    }
    
    // Test the column detection functions like in supply.php
    function colExists($pdo, $table, $col) {
        try { 
            $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); 
            $s->execute([$col]); 
            return (bool)$s->fetch(); 
        } catch (Exception $e) { 
            return false; 
        }
    }
    
    function pickCol($pdo, $table, $candidates) {
        foreach ($candidates as $col) {
            if (colExists($pdo, $table, $col)) return $col;
        }
        return null;
    }
    
    $colTotal = pickCol($pdo, 'items', ['total_quantity','quantity_total','total','quantity','stock']);
    $colAvail = pickCol($pdo, 'items', ['available_quantity','quantity_available','qty_available','available','qty']);
    
    echo "\n2. COLUMN DETECTION RESULTS:\n";
    echo "   colTotal: " . ($colTotal ?: 'NULL') . "\n";
    echo "   colAvail: " . ($colAvail ?: 'NULL') . "\n";
    
    // Test insertion
    echo "\n3. TESTING INSERTION:\n";
    $quantity = (int)$testData['quantity'];
    echo "   Quantity as int: $quantity\n";
    
    $cols = [];
    $vals = [];
    $phs  = [];
    
    $cols[] = 'item_name'; $vals[] = $testData['item_name']; $phs[] = '?';
    if ($colTotal) { $cols[] = $colTotal; $vals[] = $quantity; $phs[] = '?'; }
    if ($colAvail) { $cols[] = $colAvail; $vals[] = $quantity; $phs[] = '?'; }
    if (colExists($pdo, 'items', 'category')) { $cols[] = 'category'; $vals[] = $testData['category']; $phs[] = '?'; }
    if (colExists($pdo, 'items', 'unit')) { $cols[] = 'unit'; $vals[] = $testData['unit']; $phs[] = '?'; }
    if (colExists($pdo, 'items', 'can_be_returned')) { $cols[] = 'can_be_returned'; $vals[] = $testData['can_be_returned']; $phs[] = '?'; }
    if (colExists($pdo, 'items', 'borrowed_quantity')) { $cols[] = 'borrowed_quantity'; $vals[] = 0; $phs[] = '?'; }
    
    $sql = 'INSERT INTO items (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $phs) . ')';
    echo "   SQL: $sql\n";
    echo "   Values: " . json_encode($vals) . "\n";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
    
    $newItemId = $pdo->lastInsertId();
    echo "   New Item ID: $newItemId\n";
    
    // Check what was actually inserted
    echo "\n4. VERIFICATION - WHAT WAS INSERTED:\n";
    $qtyExpr = $colTotal ?: $colAvail ?: '0';
    $checkSql = "SELECT item_name, $qtyExpr as quantity, " . ($colTotal ? "$colTotal as total_qty, " : "") . ($colAvail ? "$colAvail as avail_qty" : "") . " FROM items WHERE id = ?";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute([$newItemId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   Result: " . json_encode($result) . "\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
