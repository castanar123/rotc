<?php
require_once 'includes/db.php';

echo "<h2>Database Connection Test</h2>";

// Test MySQLi connection
if (isset($link) && $link) {
    echo "<p style='color: green;'>MySQLi connection successful!</p>";
} else {
    echo "<p style='color: red;'>MySQLi connection failed!</p>";
}

// Test PDO connection
if (isset($pdo) && $pdo) {
    echo "<p style='color: green;'>PDO connection successful!</p>";
} else {
    echo "<p style='color: red;'>PDO connection failed or not available!</p>";
}

echo "<h2>Current Test Accounts in Database</h2>";

try {
    // Check existing users using PDO
    $stmt = $pdo->prepare("SELECT id, email, username, role, created_at FROM users WHERE username IN ('admin_test', '2cl_officer_test', 'basic_cadet_test')");
    $stmt->execute();
    $result = $stmt->fetchAll();
    
    if (count($result) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Email</th><th>Username</th><th>Role</th><th>Created At</th></tr>";
        
        foreach ($result as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td>" . htmlspecialchars($row['role']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No test accounts found in the database.</p>";
    }
    
    echo "<h2>All Users in Database</h2>";
    $stmt = $pdo->prepare("SELECT id, email, username, role, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->fetchAll();
    
    if (count($result) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Email</th><th>Username</th><th>Role</th><th>Created At</th></tr>";
        
        foreach ($result as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
            echo "<td>" . htmlspecialchars($row['role']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found in the database.</p>";
    }
    
    echo "<h2>Database Tables Structure</h2>";
    $stmt = $pdo->prepare("SHOW TABLES");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_NUM);
    
    echo "<h3>Available Tables:</h3>";
    echo "<ul>";
    foreach ($result as $row) {
        echo "<li>" . htmlspecialchars($row[0]) . "</li>";
    }
    echo "</ul>";
    
    // Check users table structure
    echo "<h3>Users Table Structure:</h3>";
    $stmt = $pdo->prepare("DESCRIBE users");
    $stmt->execute();
    $result = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($result as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check cadet_profiles table structure
    echo "<h3>Cadet Profiles Table Structure:</h3>";
    $stmt = $pdo->prepare("DESCRIBE cadet_profiles");
    $stmt->execute();
    $result = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($result as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Actions</h2>";
echo "<p><a href='create_test_accounts.php'>Create Test Accounts</a></p>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>