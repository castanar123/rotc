<?php
require_once 'includes/db.php';

echo "<h2>Database Schema Check</h2>";

try {
    global $pdo;
    
    echo "<h3>Current Users Table Schema:</h3>";
    $result = $pdo->query('PRAGMA table_info(users)');
    echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Not Null</th><th>Default</th></tr>";
    while($row = $result->fetch()) {
        echo "<tr><td>" . $row['name'] . "</td><td>" . $row['type'] . "</td><td>" . $row['notnull'] . "</td><td>" . $row['dflt_value'] . "</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>Adding Missing Columns:</h3>";
    
    // Check if is_active column exists
    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    $columnNames = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    
    $result = $pdo->query('PRAGMA table_info(users)');
    $existingColumns = [];
    while($row = $result->fetch()) {
        $existingColumns[] = $row['name'];
    }
    
    // Add missing columns
    if (!in_array('is_active', $existingColumns)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE');
        echo "✓ Added is_active column<br>";
    } else {
        echo "✓ is_active column already exists<br>";
    }
    
    if (!in_array('two_factor_enabled', $existingColumns)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN two_factor_enabled BOOLEAN DEFAULT FALSE');
        echo "✓ Added two_factor_enabled column<br>";
    } else {
        echo "✓ two_factor_enabled column already exists<br>";
    }
    
    if (!in_array('two_factor_secret', $existingColumns)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(32)');
        echo "✓ Added two_factor_secret column<br>";
    } else {
        echo "✓ two_factor_secret column already exists<br>";
    }
    
    echo "<h3>Updated Users Table Schema:</h3>";
    $result = $pdo->query('PRAGMA table_info(users)');
    echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Not Null</th><th>Default</th></tr>";
    while($row = $result->fetch()) {
        echo "<tr><td>" . $row['name'] . "</td><td>" . $row['type'] . "</td><td>" . $row['notnull'] . "</td><td>" . $row['dflt_value'] . "</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>