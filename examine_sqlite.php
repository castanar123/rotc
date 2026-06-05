<?php
// Script to examine the original SQLite database

try {
    $sqlite_path = 'data/rotc_db.sqlite';
    
    if (!file_exists($sqlite_path)) {
        echo "SQLite database not found at: $sqlite_path\n";
        exit(1);
    }
    
    echo "<h2>Examining Original ROTC SQLite Database</h2>\n";
    echo "<p>Database file: $sqlite_path</p>\n";
    echo "<p>File size: " . number_format(filesize($sqlite_path)) . " bytes</p>\n";
    
    // Connect to SQLite database
    $pdo = new PDO("sqlite:$sqlite_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>Database Tables:</h3>\n";
    
    // Get all tables
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "<h4>Table: $table</h4>\n";
        
        // Get table structure
        $stmt = $pdo->query("PRAGMA table_info($table)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Structure:</strong></p>\n";
        echo "<ul>\n";
        foreach ($columns as $col) {
            echo "<li>{$col['name']} ({$col['type']})" . ($col['pk'] ? ' - PRIMARY KEY' : '') . "</li>\n";
        }
        echo "</ul>\n";
        
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p><strong>Records:</strong> $count</p>\n";
        
        // Show sample data if records exist
        if ($count > 0) {
            $stmt = $pdo->query("SELECT * FROM $table LIMIT 5");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                echo "<p><strong>Sample Data (first 5 records):</strong></p>\n";
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
                
                // Header
                echo "<tr>\n";
                foreach (array_keys($rows[0]) as $header) {
                    echo "<th style='padding: 5px; background: #f0f0f0;'>$header</th>\n";
                }
                echo "</tr>\n";
                
                // Data rows
                foreach ($rows as $row) {
                    echo "<tr>\n";
                    foreach ($row as $value) {
                        $display_value = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                        echo "<td style='padding: 5px;'>" . htmlspecialchars($display_value) . "</td>\n";
                    }
                    echo "</tr>\n";
                }
                echo "</table>\n";
            }
        }
        
        echo "<hr>\n";
    }
    
    echo "<h3>Summary</h3>\n";
    echo "<p>Total tables found: " . count($tables) . "</p>\n";
    
    // Calculate total records
    $total_records = 0;
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $total_records += $stmt->fetchColumn();
    }
    echo "<p>Total records across all tables: $total_records</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}
?>