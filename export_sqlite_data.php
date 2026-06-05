<?php
// Script to export SQLite data to SQL format

try {
    $sqlite_path = 'data/rotc_db.sqlite';
    
    if (!file_exists($sqlite_path)) {
        echo "SQLite database not found at: $sqlite_path\n";
        exit(1);
    }
    
    // Connect to SQLite database
    $pdo = new PDO("sqlite:$sqlite_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "-- SQLite Data Export from ROTC Database\n";
    echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Get all tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "-- ========================================\n";
        echo "-- Table: $table\n";
        echo "-- ========================================\n\n";
        
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "-- Records in table: $count\n\n";
        
        if ($count > 0) {
            // Get all data
            $stmt = $pdo->query("SELECT * FROM $table");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                // Get column names
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                echo "-- Data for table: $table\n";
                echo "INSERT INTO `$table` ($columnList) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } else {
                            $rowValues[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $rowValues) . ')';
                }
                
                echo implode(",\n", $values) . ";\n\n";
                
                // Also show readable format
                echo "-- Readable format for table: $table\n";
                foreach ($rows as $i => $row) {
                    echo "-- Record " . ($i + 1) . ":\n";
                    foreach ($row as $col => $value) {
                        $displayValue = strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value;
                        echo "--   $col: $displayValue\n";
                    }
                    echo "--\n";
                }
                echo "\n";
            }
        } else {
            echo "-- No data in table: $table\n\n";
        }
    }
    
    echo "-- ========================================\n";
    echo "-- Export Summary\n";
    echo "-- ========================================\n";
    echo "-- Total tables: " . count($tables) . "\n";
    
    $total_records = 0;
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $table_count = $stmt->fetchColumn();
        $total_records += $table_count;
        echo "-- $table: $table_count records\n";
    }
    echo "-- Total records: $total_records\n";
    
} catch (Exception $e) {
    echo "-- Error: " . $e->getMessage() . "\n";
}
?>