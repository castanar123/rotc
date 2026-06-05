<?php
// Simple check for SQLite table counts

try {
    $sqlite_db = new PDO('sqlite:data/rotc_db.sqlite');
    $sqlite_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "SQLite Database Table Counts:\n";
    echo "============================\n";
    
    // Get all tables
    $stmt = $sqlite_db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        try {
            $stmt = $sqlite_db->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "Table '$table': $count records\n";
            
            if ($count == 193) {
                echo "*** FOUND 193 RECORDS IN TABLE '$table' ***\n";
                
                // Show sample data
                $stmt = $sqlite_db->query("SELECT * FROM `$table` LIMIT 3");
                $sample = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "Sample data:\n";
                print_r($sample);
            }
        } catch (Exception $e) {
            echo "Error checking table '$table': " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>