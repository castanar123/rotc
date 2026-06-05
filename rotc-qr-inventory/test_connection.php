<?php
// Test ROTC Database Connection
require_once 'includes/db.php';

echo "<h2>ROTC Database Connection Test</h2>";

echo "<p><strong>Configuration:</strong></p>";
echo "<ul>";
echo "<li>Server: " . DB_SERVER . "</li>";
echo "<li>Username: " . DB_USERNAME . "</li>";
echo "<li>Password: " . (DB_PASSWORD ? 'Set' : 'Empty') . "</li>";
echo "<li>Database: " . DB_NAME . "</li>";
echo "</ul>";

if (isset($link) && $link instanceof mysqli) {
    echo "<p style='color: green; font-weight: bold;'>✓ MySQLi Connection: SUCCESS!</p>";
    
    // Test a simple query
    $result = $link->query("SELECT DATABASE() as current_db");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Current database: <strong>" . $row['current_db'] . "</strong></p>";
    }
    
    // Show tables in the database
    $result = $link->query("SHOW TABLES");
    if ($result) {
        echo "<p><strong>Tables in database:</strong></p>";
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ MySQLi Connection: FAILED!</p>";
}

if (isset($pdo) && $pdo instanceof PDO) {
    echo "<p style='color: green; font-weight: bold;'>✓ PDO Connection: SUCCESS!</p>";
    
    // Test PDO query
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        $row = $stmt->fetch();
        echo "<p>Number of tables: <strong>" . $row['table_count'] . "</strong></p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>PDO Query Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ PDO Connection: FAILED!</p>";
}

echo "<hr>";
echo "<p><em>Test completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>