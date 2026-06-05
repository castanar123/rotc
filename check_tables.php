<?php
// Check and fix table structures in rotc_db database

try {
    $pdo = new PDO("mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CHECKING TABLE STRUCTURES ===\n";
    
    // Check inventory_items structure
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_items'");
    if ($stmt->rowCount() > 0) {
        echo "\n✓ inventory_items table exists\n";
        echo "Columns in inventory_items:\n";
        $stmt = $pdo->query("DESCRIBE inventory_items");
        while ($row = $stmt->fetch()) {
            echo "  - {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Default']}\n";
        }
    }
    
    // Check items structure
    $stmt = $pdo->query("SHOW TABLES LIKE 'items'");
    if ($stmt->rowCount() > 0) {
        echo "\n✓ items table exists\n";
        echo "Columns in items:\n";
        $stmt = $pdo->query("DESCRIBE items");
        while ($row = $stmt->fetch()) {
            echo "  - {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Default']}\n";
        }
    }
    
    echo "\n=== FIXING ITEMS TABLE STRUCTURE ===\n";
    
    // Create items table with complete structure if it doesn't exist, or alter it if it does
    $createItemsSQL = "
    CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_code VARCHAR(50) UNIQUE,
        qr_code VARCHAR(100) UNIQUE,
        item_name VARCHAR(255) NOT NULL,
        category ENUM('Consumable', 'Non-consumable', 'Semi-expendable', 'Capital', 'Disposable') DEFAULT 'Consumable',
        description TEXT,
        total_quantity INT DEFAULT 0,
        available_quantity INT DEFAULT 0,
        borrowed_quantity INT DEFAULT 0,
        unit VARCHAR(50) DEFAULT 'pcs',
        location VARCHAR(255),
        condition_status ENUM('excellent', 'good', 'fair', 'poor', 'damaged') DEFAULT 'good',
        status ENUM('active', 'inactive', 'retired') DEFAULT 'active',
        can_be_returned TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_item_code (item_code),
        INDEX idx_qr_code (qr_code),
        INDEX idx_category (category),
        INDEX idx_status (status)
    )";
    
    $pdo->exec($createItemsSQL);
    echo "✓ Items table created/updated with complete structure\n";
    
    // Add missing columns if they don't exist
    $alterQueries = [
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'retired') DEFAULT 'active'",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS can_be_returned TINYINT(1) DEFAULT 1",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS category ENUM('Consumable', 'Non-consumable', 'Semi-expendable', 'Capital', 'Disposable') DEFAULT 'Consumable'",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS condition_status ENUM('excellent', 'good', 'fair', 'poor', 'damaged') DEFAULT 'good'",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS item_code VARCHAR(50) UNIQUE",
        "ALTER TABLE items ADD COLUMN IF NOT EXISTS qr_code VARCHAR(100) UNIQUE"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
            echo "✓ Added column: " . substr($query, strpos($query, 'ADD COLUMN') + 11, 20) . "...\n";
        } catch (Exception $e) {
            echo "- Column already exists or error: " . substr($query, strpos($query, 'ADD COLUMN') + 11, 20) . "...\n";
        }
    }
    
    echo "\n=== FINAL ITEMS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE items");
    while ($row = $stmt->fetch()) {
        echo "  - {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Default']}\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>