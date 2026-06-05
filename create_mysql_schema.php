<?php
// Create MySQL database and tables for ROTC system

try {
    // Connect to MySQL server
    $pdo = new PDO('mysql:host=localhost', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server successfully!\n";
    
    // Create database if it doesn't exist
    $pdo->exec('CREATE DATABASE IF NOT EXISTS rotc_db');
    echo "Database 'rotc_db' ready\n";
    
    // Connect to the database
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Drop existing tables first
    $tables = ['grades', 'attendance', 'announcements', 'cadet_profiles', 'users'];
    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo "Dropped table: $table\n";
        } catch (PDOException $e) {
            echo "Warning dropping $table: " . $e->getMessage() . "\n";
        }
    }
    
    // Read and execute SQL schema
    $sql = file_get_contents('db/rotc_db.sql');
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
            try {
                $pdo->exec($statement);
                echo "Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\nDatabase schema created successfully!\n";
    
    // Verify tables were created
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nTables created:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>