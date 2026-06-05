<?php
// Test MySQL connection for ROTC system
echo "<h2>Testing MySQL Database Connection</h2>";

// Include the database connection
require_once 'includes/db.php';

try {
    echo "<p><strong>✓ Database connection successful!</strong></p>";
    echo "<p>Database Type: " . DB_TYPE . "</p>";
    echo "<p>Database Name: " . DB_NAME . "</p>";
    echo "<p>Server: " . DB_SERVER . "</p>";
    
    // Test query to count records in each table
    echo "<h3>Table Status:</h3>";
    
    $tables = ['users', 'items', 'borrowed_items', 'categories'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $result = $stmt->fetch();
            echo "<p>✓ Table '$table': {$result['count']} records</p>";
        } catch (Exception $e) {
            echo "<p>✗ Error with table '$table': " . $e->getMessage() . "</p>";
        }
    }
    
    // Test admin user
    echo "<h3>Admin User Test:</h3>";
    $stmt = $pdo->prepare("SELECT username, email, role FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p>✓ Admin user found: {$admin['username']} ({$admin['email']}) - Role: {$admin['role']}</p>";
    } else {
        echo "<p>✗ No admin user found</p>";
    }
    
    echo "<h3>Sample Items:</h3>";
    $stmt = $pdo->prepare("SELECT item_name, total_quantity, available_quantity FROM items LIMIT 3");
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    if ($items) {
        echo "<ul>";
        foreach ($items as $item) {
            echo "<li>{$item['item_name']} - Total: {$item['total_quantity']}, Available: {$item['available_quantity']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No items found</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>✗ Database connection failed:</strong> " . $e->getMessage() . "</p>";
}

echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>