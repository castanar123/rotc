<?php
require_once 'includes/db.php';

echo "<h2>Adding Missing Columns to cadet_profiles Table</h2>";

try {
    // Check current table structure
    $result = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $result->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    echo "<p>Current columns in cadet_profiles table:</p>";
    echo "<ul>";
    foreach ($columnNames as $col) {
        echo "<li>$col</li>";
    }
    echo "</ul>";
    
    // Add missing birthdate column
    if (!in_array('birthdate', $columnNames)) {
        echo "<p>Adding birthdate column...</p>";
        $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN birthdate DATE AFTER date_of_birth");
        echo "<p>✅ Added birthdate column</p>";
    } else {
        echo "<p>✅ birthdate column already exists</p>";
    }
    
    // Add missing facebook_profile column
    if (!in_array('facebook_profile', $columnNames)) {
        echo "<p>Adding facebook_profile column...</p>";
        $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN facebook_profile VARCHAR(255) DEFAULT NULL AFTER phone");
        echo "<p>✅ Added facebook_profile column</p>";
    } else {
        echo "<p>✅ facebook_profile column already exists</p>";
    }
    
    // Verify the additions
    echo "<h3>Verification - Checking for critical columns:</h3>";
    $result = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $result->fetchAll();
    $columnNames = array_column($columns, 'Field');
    
    $requiredColumns = ['birthdate', 'facebook_profile', 'middle_name'];
    foreach ($requiredColumns as $column) {
        $exists = in_array($column, $columnNames);
        $status = $exists ? '✅' : '❌';
        echo "<p>$status <strong>$column</strong> - " . ($exists ? 'EXISTS' : 'MISSING') . "</p>";
    }
    
    echo "<h3>Final Status:</h3>";
    if (in_array('birthdate', $columnNames) && in_array('facebook_profile', $columnNames)) {
        echo "<p style='color: green;'>🎉 SUCCESS! All required columns are now present in cadet_profiles table!</p>";
        echo "<p>The following database errors should now be resolved:</p>";
        echo "<ul>";
        echo "<li>✅ Unknown column 'cp.birthdate' in 'field list' - FIXED</li>";
        echo "<li>✅ Unknown column 'cp.facebook_profile' in 'field list' - FIXED</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ Some columns are still missing. Manual intervention may be required.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>