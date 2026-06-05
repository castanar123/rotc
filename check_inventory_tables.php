<?php
// Check and create inventory tables in rotc_db

try {
    $pdo = new PDO('mysql:host=localhost:3306;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Checking Inventory Tables in rotc_db ===\n\n";
    
    // Check if inventory tables exist
    $tables = ['items', 'officers', 'transactions', 'transaction_items', 'borrowed_items'];
    $existing_tables = [];
    
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            $existing_tables[] = $table;
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' does not exist\n";
        }
    }
    
    if (count($existing_tables) === count($tables)) {
        echo "\n✅ All inventory tables already exist!\n";
        
        // Show items table structure
        echo "\n=== Items Table Structure ===\n";
        $result = $pdo->query('DESCRIBE items');
        while ($row = $result->fetch()) {
            echo $row['Field'] . ' - ' . $row['Type'] . "\n";
        }
        
        // Show sample data
        echo "\n=== Sample Items Data ===\n";
        $result = $pdo->query('SELECT * FROM items LIMIT 5');
        $items = $result->fetchAll();
        if (count($items) > 0) {
            foreach ($items as $item) {
                echo "ID: {$item['id']}, Name: {$item['item_name']}, Available: {$item['quantity_available']}\n";
            }
        } else {
            echo "No items found in database\n";
        }
        
    } else {
        echo "\n=== Creating Missing Inventory Tables ===\n";
        
        // Create items table
        if (!in_array('items', $existing_tables)) {
            $sql = "CREATE TABLE items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_name VARCHAR(255) NOT NULL,
                description TEXT,
                total_quantity INT NOT NULL DEFAULT 0,
                quantity_available INT NOT NULL DEFAULT 0,
                borrowed_quantity INT NOT NULL DEFAULT 0,
                unit VARCHAR(20) DEFAULT 'pcs',
                location VARCHAR(100),
                condition_status VARCHAR(20) DEFAULT 'good',
                qr_code VARCHAR(255) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
            echo "✓ Created 'items' table\n";
        }
        
        // Create officers table
        if (!in_array('officers', $existing_tables)) {
            $sql = "CREATE TABLE officers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                rank_position VARCHAR(100),
                platoon VARCHAR(50),
                contact VARCHAR(100),
                email VARCHAR(100),
                status VARCHAR(20) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $pdo->exec($sql);
            echo "✓ Created 'officers' table\n";
        }
        
        // Create transactions table
        if (!in_array('transactions', $existing_tables)) {
            $sql = "CREATE TABLE transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transaction_type VARCHAR(20) NOT NULL,
                duty_officer_id INT,
                total_items INT DEFAULT 0,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (duty_officer_id) REFERENCES officers(id)
            )";
            $pdo->exec($sql);
            echo "✓ Created 'transactions' table\n";
        }
        
        // Create transaction_items table
        if (!in_array('transaction_items', $existing_tables)) {
            $sql = "CREATE TABLE transaction_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transaction_id INT NOT NULL,
                item_id INT NOT NULL,
                quantity INT NOT NULL,
                action VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (transaction_id) REFERENCES transactions(id),
                FOREIGN KEY (item_id) REFERENCES items(id)
            )";
            $pdo->exec($sql);
            echo "✓ Created 'transaction_items' table\n";
        }
        
        // Create borrowed_items table
        if (!in_array('borrowed_items', $existing_tables)) {
            $sql = "CREATE TABLE borrowed_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                borrower_name VARCHAR(255) NOT NULL,
                borrower_contact VARCHAR(255),
                quantity_borrowed INT NOT NULL,
                borrow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expected_return_date DATE,
                actual_return_date TIMESTAMP NULL,
                status VARCHAR(20) DEFAULT 'borrowed',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id)
            )";
            $pdo->exec($sql);
            echo "✓ Created 'borrowed_items' table\n";
        }
        
        // Insert sample data
        echo "\n=== Inserting Sample Data ===\n";
        
        // Sample officers
        $pdo->exec("INSERT INTO officers (name, rank_position, platoon, contact, email) VALUES 
            ('John Doe', 'Sergeant', 'Alpha', '09123456789', 'john.doe@rotc.edu'),
            ('Jane Smith', 'Lieutenant', 'Bravo', '09987654321', 'jane.smith@rotc.edu'),
            ('Mike Johnson', 'Captain', 'Charlie', '09555666777', 'mike.johnson@rotc.edu')
        ");
        echo "✓ Inserted sample officers\n";
        
        // Sample items
        $pdo->exec("INSERT INTO items (item_name, description, total_quantity, quantity_available, unit, location, qr_code) VALUES 
            ('M16 Rifle', 'Standard issue rifle for training', 50, 45, 'pcs', 'Armory A1', 'QR_M16_001'),
            ('Combat Boots', 'Standard combat boots size 9', 100, 85, 'pairs', 'Supply Room B2', 'QR_BOOTS_002'),
            ('Field Pack', 'Military field backpack', 75, 70, 'pcs', 'Supply Room B1', 'QR_PACK_003'),
            ('Helmet', 'Combat helmet with chin strap', 60, 55, 'pcs', 'Armory A2', 'QR_HELMET_004'),
            ('Uniform Set', 'Complete BDU uniform set', 120, 100, 'sets', 'Supply Room C1', 'QR_UNIFORM_005')
        ");
        echo "✓ Inserted sample items\n";
        
        echo "\n✅ Inventory system successfully integrated into rotc_db!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>