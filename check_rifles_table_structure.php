<?php
$host = 'localhost';
$dbname = 'rotc_db';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Checking rifles table structure</h2>";
    
    // Check if rifles table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'rifles'");
    if ($stmt->rowCount() > 0) {
        echo "<h3>✅ Rifles table exists</h3>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE rifles");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count existing records
        $stmt = $pdo->query("SELECT COUNT(*) FROM rifles");
        $count = $stmt->fetchColumn();
        echo "<h4>Current record count: $count</h4>";
        
        // Show sample data if any exists
        if ($count > 0) {
            $stmt = $pdo->query("SELECT * FROM rifles LIMIT 5");
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<h4>Sample data:</h4>";
            echo "<pre>" . print_r($samples, true) . "</pre>";
        }
        
    } else {
        echo "<h3 style='color: red;'>❌ Rifles table does not exist</h3>";
        
        // Show all tables
        echo "<h4>Available tables:</h4>";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>