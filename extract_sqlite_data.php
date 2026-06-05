<?php
// Extract data from SQLite database
try {
    $sqlite_db = new PDO('sqlite:data/rotc_db.sqlite');
    $sqlite_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== SQLITE DATABASE ANALYSIS ===\n\n";
    
    // Get all tables
    $tables_query = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
    $tables = $sqlite_db->query($tables_query)->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables found: " . count($tables) . "\n";
    foreach($tables as $table) {
        echo "- $table\n";
    }
    echo "\n";
    
    // For each table, show structure and data
    foreach($tables as $table) {
        echo "=== TABLE: $table ===\n";
        
        // Get table structure
        $structure = $sqlite_db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        echo "Structure:\n";
        foreach($structure as $column) {
            echo "  {$column['name']} ({$column['type']})\n";
        }
        
        // Get row count
        $count = $sqlite_db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "Records: $count\n";
        
        // Show sample data (first 5 rows)
        if($count > 0) {
            echo "Sample data:\n";
            $data = $sqlite_db->query("SELECT * FROM $table LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            foreach($data as $row) {
                echo "  " . json_encode($row) . "\n";
            }
        }
        echo "\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>