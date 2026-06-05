<?php
// Database setup script for ROTC system

$host = 'localhost';
$username = 'root';
$password = 'root';
$port = 3306;

try {
    // Connect to MySQL server (without database)
    $pdo = new PDO("mysql:host=$host;port=$port", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server successfully!\n";
    
    // Create a fresh database with timestamp to avoid conflicts
    $dbName = 'rotc_inventory_' . date('YmdHis');
    $pdo->exec("CREATE DATABASE $dbName");
    echo "Created fresh database: $dbName\n";
    
    // Use the new database
    $pdo->exec("USE $dbName");
    
    // No need to drop tables in fresh database
    
    // Create users table
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        full_name VARCHAR(255),
        role VARCHAR(20) DEFAULT 'user',
        two_factor_enabled BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Created users table\n";
    
    // Create items table
    $sql = "
    CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        description TEXT,
        total_quantity INT NOT NULL DEFAULT 0,
        available_quantity INT NOT NULL DEFAULT 0,
        borrowed_quantity INT NOT NULL DEFAULT 0,
        unit VARCHAR(20) DEFAULT 'pcs',
        location VARCHAR(100),
        condition_status VARCHAR(20) DEFAULT 'good',
        qr_code VARCHAR(255) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Created items table\n";
    
    // Create borrowed_items table
    $sql = "
    CREATE TABLE IF NOT EXISTS borrowed_items (
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
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Created borrowed_items table\n";
    
    // Create categories table
    $sql = "
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Created categories table\n";
    
    // Insert default admin user
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $hashedPassword, 'admin@rotc.mil', 'System Administrator', 'admin']);
    echo "Inserted default admin user (username: admin, password: admin123)\n";
    
    // Insert sample categories
    $categories = [
        ['Weapons', 'Military weapons and firearms'],
        ['Equipment', 'Military equipment and gear'],
        ['Supplies', 'General supplies and materials'],
        ['Uniforms', 'Military uniforms and clothing']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    foreach ($categories as $category) {
        $stmt->execute($category);
    }
    echo "Inserted sample categories\n";
    
    // Insert sample items
    $items = [
        ['M16 Rifle', 'Standard issue rifle for training', 50, 45, 5, 'pcs', 'Armory A', 'good'],
        ['Combat Boots', 'Standard military boots', 100, 85, 15, 'pairs', 'Supply Room B', 'good'],
        ['Field Pack', 'Military backpack for field exercises', 75, 70, 5, 'pcs', 'Supply Room A', 'good'],
        ['Helmet', 'Protective military helmet', 60, 55, 5, 'pcs', 'Equipment Room', 'good'],
        ['Uniform Set', 'Complete military uniform', 120, 100, 20, 'sets', 'Uniform Storage', 'good']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO items (item_name, description, total_quantity, available_quantity, borrowed_quantity, unit, location, condition_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $stmt->execute($item);
    }
    echo "Inserted sample items\n";
    
    echo "\nDatabase setup completed successfully!\n";
    echo "Database name: $dbName\n";
    echo "You can now use the ROTC system with MySQL database.\n";
    echo "Admin login: username=admin, password=admin123\n";
    echo "\nIMPORTANT: Update your database connection files to use database: $dbName\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>