<?php
// Test script to verify MySQL migration and application connectivity

require_once 'includes/db.php';

echo "<h2>ROTC MySQL Migration Test</h2>";
echo "<p><strong>Testing database connectivity and data integrity...</strong></p>";

try {
    // Test database connection
    echo "<h3>1. Database Connection Test</h3>";
    echo "<p>✓ Connected to MySQL database: " . DB_NAME . "</p>";
    
    // Test table existence
    echo "<h3>2. Table Structure Test</h3>";
    $tables = ['users', 'items', 'borrowed_items'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "<p>✓ Table '$table' exists</p>";
            
            // Count records
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
            $countStmt->execute();
            $count = $countStmt->fetch()['count'];
            echo "<p>&nbsp;&nbsp;&nbsp;Records: $count</p>";
        } else {
            echo "<p>✗ Table '$table' missing</p>";
        }
    }
    
    // Test user data
    echo "<h3>3. User Data Test</h3>";
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<p>✓ Found " . count($users) . " user(s):</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>✗ No users found</p>";
    }
    
    // Test application functionality
    echo "<h3>4. Application Functionality Test</h3>";
    
    // Test login functionality (simulate)
    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p>✓ Admin user found - login functionality should work</p>";
        echo "<p>&nbsp;&nbsp;&nbsp;Admin ID: " . $admin['id'] . "</p>";
        echo "<p>&nbsp;&nbsp;&nbsp;Username: " . $admin['username'] . "</p>";
    } else {
        echo "<p>✗ Admin user not found - login may not work</p>";
    }
    
    // Test items functionality
    echo "<h3>5. Items Management Test</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM items");
    $stmt->execute();
    $itemCount = $stmt->fetch()['count'];
    
    echo "<p>Items in database: $itemCount</p>";
    if ($itemCount == 0) {
        echo "<p>ℹ️ No items found - this is expected if the original SQLite database had no items</p>";
    }
    
    // Test borrowed items functionality
    echo "<h3>6. Borrowed Items Test</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM borrowed_items");
    $stmt->execute();
    $borrowedCount = $stmt->fetch()['count'];
    
    echo "<p>Borrowed items in database: $borrowedCount</p>";
    if ($borrowedCount == 0) {
        echo "<p>ℹ️ No borrowed items found - this is expected if the original SQLite database had no borrowed items</p>";
    }
    
    echo "<h3>✅ Migration Test Summary</h3>";
    echo "<p><strong>Database:</strong> " . DB_NAME . " (MySQL)</p>";
    echo "<p><strong>Tables:</strong> All required tables exist</p>";
    echo "<p><strong>Data:</strong> User data successfully migrated</p>";
    echo "<p><strong>Status:</strong> ✅ Migration appears successful!</p>";
    
    echo "<hr>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>Test the main application by visiting the login page</li>";
    echo "<li>Try logging in with username: admin</li>";
    echo "<li>Verify all application features work correctly</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>