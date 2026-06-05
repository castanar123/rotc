<?php
// Check original SQLite database for rifle data

try {
    $sqlite_db = new PDO('sqlite:data/rotc_db.sqlite');
    $sqlite_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Checking Original SQLite Database for Rifle Data</h2>";
    
    // Get all tables
    $stmt = $sqlite_db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Available Tables:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Check for rifles table
    if (in_array('rifles', $tables)) {
        echo "<h3>Rifles Table Found!</h3>";
        
        // Get rifle count
        $stmt = $sqlite_db->query("SELECT COUNT(*) as count FROM rifles");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p><strong>Total rifles in SQLite database: $count</strong></p>";
        
        if ($count > 0) {
            // Get all rifles
            $stmt = $sqlite_db->query("SELECT * FROM rifles ORDER BY rifle_number");
            $rifles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>All Rifles in SQLite Database:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Rifle Number</th><th>Serial Number</th><th>Model</th><th>Status</th><th>Condition</th><th>Location</th></tr>";
            
            foreach ($rifles as $rifle) {
                echo "<tr>";
                echo "<td>" . ($rifle['id'] ?? 'N/A') . "</td>";
                echo "<td>" . ($rifle['rifle_number'] ?? 'N/A') . "</td>";
                echo "<td>" . ($rifle['serial_number'] ?? 'N/A') . "</td>";
                echo "<td>" . ($rifle['model'] ?? 'N/A') . "</td>";
                echo "<td>" . ($rifle['status'] ?? 'N/A') . "</td>";
                echo "<td>" . ($rifle['condition'] ?? 'N/A') . "</td>";
                echo "<td>" . ($rifle['location'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check if we have 193 rifles
            if ($count == 193) {
                echo "<h3 style='color: green;'>✅ Found the original 193 rifles!</h3>";
                echo "<p>This appears to be the original rifle inventory before corruption.</p>";
            } else {
                echo "<h3 style='color: orange;'>⚠️ Found $count rifles (not 193)</h3>";
                echo "<p>This may be a partial backup or different dataset.</p>";
            }
        }
    } else {
        echo "<h3 style='color: red;'>❌ No 'rifles' table found in SQLite database</h3>";
        
        // Check for any table with 'rifle' in the name
        $rifle_tables = array_filter($tables, function($table) {
            return stripos($table, 'rifle') !== false;
        });
        
        if (!empty($rifle_tables)) {
            echo "<h4>Found rifle-related tables:</h4>";
            foreach ($rifle_tables as $table) {
                echo "<h5>Table: $table</h5>";
                $stmt = $sqlite_db->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "<p>Records: $count</p>";
                
                if ($count > 0 && $count <= 10) {
                    $stmt = $sqlite_db->query("SELECT * FROM `$table` LIMIT 10");
                    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo "<pre>" . print_r($records, true) . "</pre>";
                }
            }
        }
    }
    
    // Check for any table that might contain 193 records
    echo "<h3>Checking all tables for 193 records:</h3>";
    foreach ($tables as $table) {
        try {
            $stmt = $sqlite_db->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($count == 193) {
                echo "<p style='color: green;'><strong>Table '$table' has exactly 193 records!</strong></p>";
                
                // Show sample data
                $stmt = $sqlite_db->query("SELECT * FROM `$table` LIMIT 5");
                $sample = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<h4>Sample data from $table:</h4>";
                echo "<pre>" . print_r($sample, true) . "</pre>";
            } elseif ($count > 100) {
                echo "<p>Table '$table': $count records</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error checking table '$table': " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error connecting to SQLite database:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Make sure the file 'data/rotc_db.sqlite' exists and is readable.</p>";
}
?>