<?php
// Fix inventory system integration with main rotc_db database

// Database connection
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "rotc_db";

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create mysqli connection for compatibility
    $link = new mysqli($servername, $username, $password, $dbname);
    $link->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "=== ROTC Inventory System Integration Fix ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Create missing tables for inventory system
    echo "1. Creating missing inventory tables...\n";
    
    // Drop and recreate borrowers table with correct structure
    $link->query("SET FOREIGN_KEY_CHECKS = 0");
    $link->query("DROP TABLE IF EXISTS borrowers");
    $link->query("SET FOREIGN_KEY_CHECKS = 1");
    
    $sql_borrowers = "
    CREATE TABLE borrowers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        contact VARCHAR(100),
        email VARCHAR(100),
        pin VARCHAR(6),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $link->query($sql_borrowers);
    echo "✓ Borrowers table created\n";
    
    // Create inventory table (using existing inventory_items structure)
    $sql_inventory = "
    CREATE TABLE IF NOT EXISTS inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_code VARCHAR(50) UNIQUE NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        description TEXT,
        category VARCHAR(50) NOT NULL,
        total_quantity INT NOT NULL DEFAULT 0,
        available_quantity INT NOT NULL DEFAULT 0,
        borrowed_quantity INT NOT NULL DEFAULT 0,
        unit VARCHAR(20) DEFAULT 'pcs',
        location VARCHAR(100),
        condition_status ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_condition (condition_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    $link->query($sql_inventory);
    echo "✓ Inventory table created\n";
    
    // 2. Fix officers table to include missing columns
    echo "\n2. Fixing officers table structure...\n";
    
    // Add missing columns to officers table
    $alter_queries = [
        "ALTER TABLE officers ADD COLUMN IF NOT EXISTS rank_position VARCHAR(100) AFTER position",
        "ALTER TABLE officers ADD COLUMN IF NOT EXISTS platoon VARCHAR(50) AFTER rank_position",
        "ALTER TABLE officers ADD COLUMN IF NOT EXISTS contact_number VARCHAR(20) AFTER platoon",
        "ALTER TABLE officers ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER contact_number"
    ];
    
    foreach ($alter_queries as $query) {
        try {
            $link->query($query);
            echo "✓ Column added successfully\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "- Column already exists\n";
            } else {
                echo "- Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 3. Update existing officers data to populate new columns
    echo "\n3. Updating officers data...\n";
    
    // Update rank_position by combining rank and position
    $update_rank_position = "
    UPDATE officers 
    SET rank_position = CONCAT(IFNULL(rank, ''), ' ', IFNULL(position, ''))
    WHERE rank_position IS NULL OR rank_position = ''
    ";
    $link->query($update_rank_position);
    echo "✓ Updated rank_position column\n";
    
    // Set default platoon for existing officers
    $update_platoon = "
    UPDATE officers 
    SET platoon = CASE 
        WHEN id % 4 = 1 THEN 'Alpha'
        WHEN id % 4 = 2 THEN 'Bravo'
        WHEN id % 4 = 3 THEN 'Charlie'
        ELSE 'Delta'
    END
    WHERE platoon IS NULL OR platoon = ''
    ";
    $link->query($update_platoon);
    echo "✓ Updated platoon assignments\n";
    
    // 4. Migrate existing inventory_items to inventory table
    echo "\n4. Migrating inventory data...\n";
    
    // Drop and recreate inventory table to fix collation
    $link->query("DROP TABLE IF EXISTS inventory");
    $link->query($sql_inventory);
    
    $migrate_inventory = "
    INSERT INTO inventory 
    (item_code, item_name, description, category, total_quantity, available_quantity, 
     borrowed_quantity, unit, location, condition_status, created_at, updated_at)
    SELECT 
        item_code,
        item_name,
        description,
        category,
        total_quantity,
        available_quantity,
        borrowed_quantity,
        unit,
        location,
        condition_status,
        created_at,
        updated_at
    FROM inventory_items
    ";
    
    $result = $link->query($migrate_inventory);
    echo "✓ Migrated " . $link->affected_rows . " items to inventory table\n";
    
    // 5. Insert sample borrowers
    echo "\n5. Adding sample borrowers...\n";
    
    $sample_borrowers_sql = "
    INSERT IGNORE INTO borrowers (name, contact, email, pin) VALUES 
    ('Cadet John Doe', '09123456789', 'john.doe@rotc.edu', '123456'),
    ('Cadet Jane Smith', '09987654321', 'jane.smith@rotc.edu', '654321'),
    ('Cadet Mike Johnson', '09555123456', 'mike.johnson@rotc.edu', '111111')
    ";
    
    $link->query($sample_borrowers_sql);
    echo "✓ Added sample borrowers\n";
    
    // 6. Create borrowed_items table for tracking
    echo "\n6. Creating borrowed_items tracking table...\n";
    
    $sql_borrowed_items = "
    CREATE TABLE IF NOT EXISTS borrowed_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inventory_id INT NOT NULL,
        borrower_id INT NOT NULL,
        officer_id INT NOT NULL,
        quantity_borrowed INT NOT NULL,
        borrow_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        expected_return_date DATE,
        actual_return_date DATETIME NULL,
        status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (inventory_id) REFERENCES inventory(id),
        FOREIGN KEY (borrower_id) REFERENCES borrowers(id),
        FOREIGN KEY (officer_id) REFERENCES officers(id)
    )";
    
    $link->query($sql_borrowed_items);
    echo "✓ Borrowed items tracking table created\n";
    
    // 7. Test database connections
    echo "\n7. Testing database connections...\n";
    
    $test_queries = [
        "SELECT COUNT(*) as count FROM inventory" => "Inventory items",
        "SELECT COUNT(*) as count FROM borrowers" => "Borrowers",
        "SELECT COUNT(*) as count FROM officers WHERE rank_position IS NOT NULL" => "Officers with rank_position",
        "SELECT COUNT(*) as count FROM borrowed_items" => "Borrowed items records"
    ];
    
    foreach ($test_queries as $query => $description) {
        $result = $link->query($query);
        $row = $result->fetch_assoc();
        echo "✓ {$description}: {$row['count']} records\n";
    }
    
    echo "\n=== Integration Fix Completed Successfully ===\n";
    echo "\nNext steps:\n";
    echo "1. Update rotc-qr-inventory/dashboard.php to use correct column names\n";
    echo "2. Test inventory functions\n";
    echo "3. Verify all database connections\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "\n=== DONE ===\n";