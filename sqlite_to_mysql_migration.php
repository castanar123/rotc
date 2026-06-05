<?php
// SQLite to MySQL Data Migration Script

try {
    // Connect to SQLite database
    $sqlite_db = new PDO('sqlite:data/rotc_db.sqlite');
    $sqlite_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Connect to MySQL database
    $mysql_host = 'localhost';
    $mysql_db = 'rotc_system';
    $mysql_user = 'root';
    $mysql_pass = 'root';
    
    $mysql_db_conn = new PDO("mysql:host=$mysql_host;dbname=$mysql_db", $mysql_user, $mysql_pass);
    $mysql_db_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to both databases successfully!\n";
    
    // Get all tables from SQLite
    $tables_query = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
    $tables_result = $sqlite_db->query($tables_query);
    $tables = $tables_result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found tables: " . implode(', ', $tables) . "\n";
    
    // Migrate data for each table
    foreach ($tables as $table) {
        echo "\nMigrating table: $table\n";
        
        // Get all data from SQLite table
        $sqlite_data = $sqlite_db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($sqlite_data)) {
            echo "No data found in table $table\n";
            continue;
        }
        
        echo "Found " . count($sqlite_data) . " records in $table\n";
        
        // Clear existing data in MySQL table
        $mysql_db_conn->exec("DELETE FROM $table");
        echo "Cleared existing data from MySQL table $table\n";
        
        // Insert data into MySQL
        foreach ($sqlite_data as $row) {
            $columns = array_keys($row);
            $placeholders = ':' . implode(', :', $columns);
            $columns_str = implode(', ', $columns);
            
            $insert_sql = "INSERT INTO $table ($columns_str) VALUES ($placeholders)";
            $stmt = $mysql_db_conn->prepare($insert_sql);
            
            try {
                $stmt->execute($row);
            } catch (PDOException $e) {
                echo "Error inserting row into $table: " . $e->getMessage() . "\n";
                echo "Row data: " . json_encode($row) . "\n";
            }
        }
        
        // Verify migration
        $mysql_count = $mysql_db_conn->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "Successfully migrated $mysql_count records to MySQL table $table\n";
    }
    
    echo "\n=== Migration Summary ===\n";
    
    // Show final counts for all tables
    foreach ($tables as $table) {
        $sqlite_count = $sqlite_db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        $mysql_count = $mysql_db_conn->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "$table: SQLite=$sqlite_count, MySQL=$mysql_count\n";
    }
    
    echo "\nData migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}
?>