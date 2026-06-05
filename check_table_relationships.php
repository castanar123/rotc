<?php
require_once 'includes/db.php';

echo "<h2>Analyzing Table Relationships and Foreign Keys</h2>";

try {
    // Check users table structure
    echo "<h3>Users Table Structure:</h3>";
    $result = $pdo->query("DESCRIBE users");
    $userColumns = $result->fetchAll();
    echo "<ul>";
    foreach ($userColumns as $column) {
        echo "<li><strong>{$column['Field']}</strong> ({$column['Type']})";
        if ($column['Key'] === 'PRI') echo " PRIMARY KEY";
        if ($column['Key'] === 'UNI') echo " UNIQUE";
        if ($column['Null'] === 'NO') echo " NOT NULL";
        echo "</li>";
    }
    echo "</ul>";
    
    // Check cadet_profiles table structure
    echo "<h3>Cadet_Profiles Table Structure:</h3>";
    $result = $pdo->query("DESCRIBE cadet_profiles");
    $cadetColumns = $result->fetchAll();
    echo "<ul>";
    foreach ($cadetColumns as $column) {
        echo "<li><strong>{$column['Field']}</strong> ({$column['Type']})";
        if ($column['Key'] === 'PRI') echo " PRIMARY KEY";
        if ($column['Key'] === 'UNI') echo " UNIQUE";
        if ($column['Key'] === 'MUL') echo " FOREIGN KEY";
        if ($column['Null'] === 'NO') echo " NOT NULL";
        echo "</li>";
    }
    echo "</ul>";
    
    // Check foreign key constraints
    echo "<h3>Foreign Key Constraints:</h3>";
    $result = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE 
            REFERENCED_TABLE_SCHEMA = 'rotc_db'
            AND TABLE_NAME IN ('cadet_profiles', 'users')
    ");
    
    $foreignKeys = $result->fetchAll();
    if (empty($foreignKeys)) {
        echo "<p style='color: orange;'>⚠️ No foreign key constraints found!</p>";
    } else {
        echo "<ul>";
        foreach ($foreignKeys as $fk) {
            echo "<li><strong>{$fk['CONSTRAINT_NAME']}</strong>: ";
            echo "{$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → ";
            echo "{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</li>";
        }
        echo "</ul>";
    }
    
    // Check if user_id exists in cadet_profiles and if it references users.id
    echo "<h3>Relationship Analysis:</h3>";
    
    $cadetColumnNames = array_column($cadetColumns, 'Field');
    $userColumnNames = array_column($userColumns, 'Field');
    
    // Check if user_id exists in cadet_profiles
    if (in_array('user_id', $cadetColumnNames)) {
        echo "<p>✅ <strong>user_id</strong> column exists in cadet_profiles table</p>";
    } else {
        echo "<p>❌ <strong>user_id</strong> column is missing in cadet_profiles table</p>";
    }
    
    // Check if id exists in users table
    if (in_array('id', $userColumnNames)) {
        echo "<p>✅ <strong>id</strong> column exists in users table (primary key)</p>";
    } else {
        echo "<p>❌ <strong>id</strong> column is missing in users table</p>";
    }
    
    // Test the relationship with sample data
    echo "<h3>Testing Relationship:</h3>";
    
    // Count users
    $userCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    echo "<p>Total users: <strong>$userCount</strong></p>";
    
    // Count cadet profiles
    $cadetCount = $pdo->query("SELECT COUNT(*) as count FROM cadet_profiles")->fetch()['count'];
    echo "<p>Total cadet profiles: <strong>$cadetCount</strong></p>";
    
    // Check for orphaned cadet profiles (cadet_profiles without corresponding users)
    if (in_array('user_id', $cadetColumnNames)) {
        $orphanedQuery = "
            SELECT COUNT(*) as count 
            FROM cadet_profiles cp 
            LEFT JOIN users u ON cp.user_id = u.id 
            WHERE u.id IS NULL
        ";
        $orphanedCount = $pdo->query($orphanedQuery)->fetch()['count'];
        
        if ($orphanedCount > 0) {
            echo "<p style='color: red;'>❌ Found <strong>$orphanedCount</strong> orphaned cadet profiles (no corresponding user)</p>";
        } else {
            echo "<p style='color: green;'>✅ No orphaned cadet profiles found</p>";
        }
    }
    
    // Check for users without cadet profiles
    if (in_array('user_id', $cadetColumnNames)) {
        $usersWithoutProfilesQuery = "
            SELECT COUNT(*) as count 
            FROM users u 
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE cp.user_id IS NULL
        ";
        $usersWithoutProfiles = $pdo->query($usersWithoutProfilesQuery)->fetch()['count'];
        
        if ($usersWithoutProfiles > 0) {
            echo "<p style='color: orange;'>⚠️ Found <strong>$usersWithoutProfiles</strong> users without cadet profiles</p>";
        } else {
            echo "<p style='color: green;'>✅ All users have corresponding cadet profiles</p>";
        }
    }
    
    echo "<h3>Recommendations:</h3>";
    if (empty($foreignKeys) && in_array('user_id', $cadetColumnNames)) {
        echo "<p style='color: orange;'>⚠️ Consider adding a foreign key constraint:</p>";
        echo "<code>ALTER TABLE cadet_profiles ADD CONSTRAINT fk_cadet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;</code>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>