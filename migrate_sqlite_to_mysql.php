<?php
// Script to migrate data from SQLite to MySQL

require_once 'includes/db.php';

try {
    // Connect to SQLite database
    $sqliteDb = new PDO('sqlite:data/rotc_db.sqlite');
    $sqliteDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to SQLite database successfully.\n";
    
    // Connect to MySQL database
    $mysqlDb = new PDO("mysql:host=localhost:3306;dbname=rotc_system;charset=utf8mb4", 'root', 'root');
    $mysqlDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL database successfully.\n";
    
    // Get all tables from SQLite
    $tables = $sqliteDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found tables in SQLite: " . implode(', ', $tables) . "\n";
    
    foreach ($tables as $table) {
        echo "\nProcessing table: $table\n";
        
        // Get table structure from SQLite
        $structure = $sqliteDb->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        echo "Table $table has " . count($structure) . " columns\n";
        
        // Get all data from SQLite table
        $data = $sqliteDb->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
        echo "Table $table has " . count($data) . " rows\n";
        
        if (count($data) > 0) {
            // Clear existing data in MySQL table
            $mysqlDb->exec("DELETE FROM $table");
            $mysqlDb->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
            
            // Prepare insert statement
            $columns = array_keys($data[0]);
            $placeholders = ':' . implode(', :', $columns);
            $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ($placeholders)";
            
            $stmt = $mysqlDb->prepare($sql);
            
            // Insert each row
            $insertedCount = 0;
            foreach ($data as $row) {
                try {
                    $stmt->execute($row);
                    $insertedCount++;
                } catch (Exception $e) {
                    echo "Error inserting row: " . $e->getMessage() . "\n";
                    print_r($row);
                }
            }
            
            echo "Inserted $insertedCount rows into MySQL table $table\n";
        } else {
            echo "No data to migrate for table $table\n";
        }
    }
    
    echo "\n=== Migration Summary ===\n";
    
    // Show final counts in MySQL
    foreach ($tables as $table) {
        $count = $mysqlDb->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "MySQL table $table now has $count rows\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>