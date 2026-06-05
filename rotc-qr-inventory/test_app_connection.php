<?php
// Test the ROTC application's database connection
require_once 'includes/db.php';

echo "<h2>ROTC Application Database Connection Test</h2>";
echo "<p><strong>Testing actual application database connection...</strong></p>";

// Test MySQLi connection from includes/db.php
if ($link && $link->ping()) {
    echo "<p style='color: green; font-weight: bold;'>✓ MySQLi Connection: SUCCESS!</p>";
    
    // Test a simple query
    $result = $link->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'rotc_qr_inventory'");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Number of tables in database: <strong>" . $row['table_count'] . "</strong></p>";
    }
    
    // Test accessing a specific table
    try {
        $result = $link->query("SELECT COUNT(*) as item_count FROM items");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p>Items table accessible: <strong>YES</strong> (" . $row['item_count'] . " items)</p>";
        } else {
            echo "<p style='color: orange;'>Items table test: " . $link->error . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>Items table test error: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ MySQLi Connection: FAILED!</p>";
    if ($link) {
        echo "<p>Error: " . $link->error . "</p>";
    }
}

// Test PDO connection from includes/db.php
if ($pdo) {
    echo "<p style='color: green; font-weight: bold;'>✓ PDO Connection: SUCCESS!</p>";
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as officer_count FROM officers LIMIT 1");
        $row = $stmt->fetch();
        echo "<p>Officers table accessible: <strong>YES</strong> (" . $row['officer_count'] . " officers)</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>Officers table test: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ PDO Connection: FAILED!</p>";
}

echo "<hr>";
echo "<p><em>Application connection test completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>