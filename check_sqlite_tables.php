<?php
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/data/rotc_db.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>SQLite Database Tables</h2>";
    
    // Get all tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
    
    echo "<h3>Tables found:</h3>";
    foreach($tables as $table) {
        echo "- " . $table['name'] . "<br>";
    }
    
    // Check users table specifically
    echo "<h3>Users table structure:</h3>";
    try {
        $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll();
        if (empty($columns)) {
            echo "Users table does not exist.<br>";
        } else {
            echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Not Null</th><th>Default</th><th>Primary Key</th></tr>";
            foreach($columns as $col) {
                echo "<tr><td>{$col['name']}</td><td>{$col['type']}</td><td>{$col['notnull']}</td><td>{$col['dflt_value']}</td><td>{$col['pk']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "Error checking users table: " . $e->getMessage() . "<br>";
    }
    
    // Check if there are any users
    echo "<h3>Users in database:</h3>";
    try {
        $users = $pdo->query("SELECT id, username, role, created_at FROM users")->fetchAll();
        if (empty($users)) {
            echo "No users found in database.<br>";
        } else {
            echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Role</th><th>Created At</th></tr>";
            foreach($users as $user) {
                echo "<tr><td>{$user['id']}</td><td>{$user['username']}</td><td>{$user['role']}</td><td>{$user['created_at']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "Error checking users: " . $e->getMessage() . "<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>