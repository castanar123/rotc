<?php
require_once 'includes/db.php';

echo "=== FIXING USER ROLES ===\n";

// Check current role distribution
echo "Current role distribution:\n";
$result = $link->query('SELECT role, COUNT(*) as count FROM users GROUP BY role');
while($row = $result->fetch_assoc()) {
    echo "  {$row['role']}: {$row['count']} users\n";
}

// Update 'basic_cadet' to 'basic-cadet' for consistency
echo "\nUpdating 'basic_cadet' to 'basic-cadet'...\n";
$update_sql = "UPDATE users SET role = 'basic-cadet' WHERE role = 'basic_cadet'";
if ($link->query($update_sql)) {
    echo "✓ Updated users with 'basic_cadet' role to 'basic-cadet'\n";
    echo "Affected rows: " . $link->affected_rows . "\n";
} else {
    echo "✗ Error updating roles: " . $link->error . "\n";
}

// Check the users table structure to see if there's an ENUM constraint
echo "\n=== USERS TABLE ROLE COLUMN STRUCTURE ===\n";
$result = $link->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($result && $row = $result->fetch_assoc()) {
    echo "Role column type: " . $row['Type'] . "\n";
    echo "Default: " . ($row['Default'] ?? 'NULL') . "\n";
    
    // If it's an ENUM, we need to modify it
    if (strpos($row['Type'], 'enum') !== false) {
        echo "\nDetected ENUM constraint. Modifying to allow 'basic-cadet' as default...\n";
        
        // Modify the ENUM to include all necessary roles and set basic-cadet as default
        $alter_sql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'instructor', 'commandant', '1cl', '2cl', 'basic-cadet') DEFAULT 'basic-cadet'";
        if ($link->query($alter_sql)) {
            echo "✓ Successfully modified role column ENUM\n";
        } else {
            echo "✗ Error modifying ENUM: " . $link->error . "\n";
        }
    }
}

// Final role distribution check
echo "\n=== FINAL ROLE DISTRIBUTION ===\n";
$result = $link->query('SELECT role, COUNT(*) as count FROM users GROUP BY role');
while($row = $result->fetch_assoc()) {
    echo "  {$row['role']}: {$row['count']} users\n";
}

// Test creating a new user to verify default role
echo "\n=== TESTING DEFAULT ROLE ===\n";
$test_sql = "INSERT INTO users (username, email, password_hash, role) VALUES ('test_default_role', 'test@example.com', 'dummy_hash', DEFAULT)";
if ($link->query($test_sql)) {
    $test_id = $link->insert_id;
    $check_sql = "SELECT role FROM users WHERE id = $test_id";
    $result = $link->query($check_sql);
    $test_user = $result->fetch_assoc();
    echo "✓ Test user created with default role: " . $test_user['role'] . "\n";
    
    // Clean up test user
    $link->query("DELETE FROM users WHERE id = $test_id");
    echo "✓ Test user cleaned up\n";
} else {
    echo "✗ Error creating test user: " . $link->error . "\n";
}

$link->close();
echo "\n=== ROLE FIXING COMPLETE ===\n";
?>