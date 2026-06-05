<?php
// Create a fresh MySQL database for ROTC system

try {
    // Connect to MySQL server
    $pdo = new PDO('mysql:host=localhost', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server successfully!\n";
    
    // Create a fresh database
    $dbName = 'rotc_system';
    $pdo->exec("DROP DATABASE IF EXISTS $dbName");
    $pdo->exec("CREATE DATABASE $dbName");
    echo "Created fresh database: $dbName\n";
    
    // Connect to the new database
    $pdo = new PDO("mysql:host=localhost;dbname=$dbName", 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table (matching SQLite structure)
    $sql = "
    CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        full_name VARCHAR(255),
        role VARCHAR(50) DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        two_factor_enabled TINYINT(1) DEFAULT 0,
        two_factor_secret VARCHAR(255)
    )";
    
    $pdo->exec($sql);
    echo "Created users table\n";
    
    // Create items table
    $sql = "
    CREATE TABLE items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        category VARCHAR(100),
        quantity INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Created items table\n";
    
    // Create borrowed_items table
    $sql = "
    CREATE TABLE borrowed_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT,
        user_id INT,
        quantity INT DEFAULT 1,
        borrowed_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        return_date DATETIME,
        status VARCHAR(50) DEFAULT 'borrowed',
        notes TEXT,
        INDEX idx_item_id (item_id),
        INDEX idx_user_id (user_id)
    )";
    
    $pdo->exec($sql);
    echo "Created borrowed_items table\n";
    
    // Verify tables
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nTables in database $dbName:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\nFresh MySQL database setup complete!\n";
    echo "Database name: $dbName\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>