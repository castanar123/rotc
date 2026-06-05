<?php
// Simple database creation script
$servername = "localhost:3307";
$username = "root";
$password = "root";

try {
    // Create connection without specifying database
    $pdo = new PDO("mysql:host=$servername", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS rotc_qr_inventory");
    echo "Database created successfully<br>";
    
    // Use the database
    $pdo->exec("USE rotc_qr_inventory");
    
    // Create officers table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS officers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            officer_id VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            rank_position VARCHAR(50) NOT NULL,
            platoon VARCHAR(20) NOT NULL,
            contact_number VARCHAR(15),
            email VARCHAR(100),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "Officers table created<br>";
    
    // Create inventory_items table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_code VARCHAR(50) UNIQUE NOT NULL,
            item_name VARCHAR(100) NOT NULL,
            category VARCHAR(50) NOT NULL,
            description TEXT,
            total_quantity INT NOT NULL DEFAULT 0,
            available_quantity INT NOT NULL DEFAULT 0,
            borrowed_quantity INT NOT NULL DEFAULT 0,
            unit VARCHAR(20) DEFAULT 'pcs',
            location VARCHAR(100),
            condition_status ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
            minimum_stock INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "Inventory items table created<br>";
    
    // Create transactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(50) UNIQUE NOT NULL,
            type ENUM('borrow', 'return', 'supply') NOT NULL,
            duty_officer_id INT NOT NULL,
            borrower_name VARCHAR(100),
            borrower_id VARCHAR(50),
            borrower_contact VARCHAR(15),
            purpose TEXT,
            expected_return_date DATE,
            actual_return_date DATE NULL,
            status ENUM('pending', 'approved', 'completed', 'overdue', 'cancelled') DEFAULT 'pending',
            notes TEXT,
            digital_signature TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "Transactions table created<br>";
    
    // Insert sample data
    $pdo->exec("
        INSERT IGNORE INTO officers (officer_id, name, rank_position, platoon, contact_number, email) VALUES
        ('OFF001', 'John Doe', 'Cadet Captain', 'Alpha', '09123456789', 'john.doe@rotc.edu'),
        ('OFF002', 'Jane Smith', 'Cadet Lieutenant', 'Bravo', '09987654321', 'jane.smith@rotc.edu'),
        ('OFF003', 'Mike Johnson', 'Cadet Sergeant', 'Charlie', '09111222333', 'mike.johnson@rotc.edu')
    ");
    echo "Sample officers inserted<br>";
    
    $pdo->exec("
        INSERT IGNORE INTO inventory_items (item_code, item_name, category, description, total_quantity, available_quantity, unit, location) VALUES
        ('RIFLE001', 'M16 Rifle', 'Weapons', 'Standard issue rifle for training', 50, 45, 'pcs', 'Armory A'),
        ('UNIFORM001', 'Combat Uniform Set', 'Clothing', 'Complete combat uniform with accessories', 100, 85, 'sets', 'Supply Room B'),
        ('BOOTS001', 'Combat Boots', 'Footwear', 'Standard issue combat boots', 80, 70, 'pairs', 'Supply Room B'),
        ('HELMET001', 'Combat Helmet', 'Protection', 'Protective helmet for field training', 60, 55, 'pcs', 'Armory A'),
        ('VEST001', 'Tactical Vest', 'Protection', 'Tactical vest with pouches', 40, 35, 'pcs', 'Armory A')
    ");
    echo "Sample inventory items inserted<br>";
    
    echo "<br><strong>Database setup completed successfully!</strong>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>