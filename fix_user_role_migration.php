<?php
require 'includes/db.php';

echo "Starting user role migration...\n";

try {
    // First, let's see current role column definition
    echo "Current role column definition:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleInfo = $stmt->fetch();
    echo "Type: " . $roleInfo['Type'] . "\n";
    echo "Default: " . ($roleInfo['Default'] ?? 'NULL') . "\n\n";
    
    // Change role column from ENUM to VARCHAR and set default to 'basic-cadet'
    echo "Modifying role column...\n";
    $sql = "ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'basic-cadet'";
    $pdo->exec($sql);
    echo "✅ Role column modified successfully!\n";
    
    // Update any existing 'cadet' roles to 'basic-cadet'
    echo "Updating existing 'cadet' roles to 'basic-cadet'...\n";
    $stmt = $pdo->prepare("UPDATE users SET role = 'basic-cadet' WHERE role = 'cadet'");
    $stmt->execute();
    $updated = $stmt->rowCount();
    echo "✅ Updated {$updated} users from 'cadet' to 'basic-cadet'\n";
    
    // Also update 'basic' to 'basic-cadet' if any exist
    echo "Updating existing 'basic' roles to 'basic-cadet'...\n";
    $stmt = $pdo->prepare("UPDATE users SET role = 'basic-cadet' WHERE role = 'basic'");
    $stmt->execute();
    $updated2 = $stmt->rowCount();
    echo "✅ Updated {$updated2} users from 'basic' to 'basic-cadet'\n";
    
    // Show updated column definition
    echo "\nUpdated role column definition:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleInfo = $stmt->fetch();
    echo "Type: " . $roleInfo['Type'] . "\n";
    echo "Default: " . ($roleInfo['Default'] ?? 'NULL') . "\n";
    
    // Show role distribution
    echo "\n📊 Current role distribution:\n";
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY count DESC");
    while($row = $stmt->fetch()) {
        echo "- {$row['role']}: {$row['count']} users\n";
    }
    
    echo "\n🎉 User role migration completed successfully!\n";
    
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>