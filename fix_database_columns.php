<?php
// Fix missing database columns
require_once 'includes/db.php';

echo "<h2>Fixing Database Column Issues</h2>";

try {
    // 1. Add rifle_number column to rifles table
    echo "<h3>1. Adding rifle_number column to rifles table...</h3>";
    
    // Check if rifle_number column exists
    $checkRifleNumber = $link->query("SHOW COLUMNS FROM rifles LIKE 'rifle_number'");
    
    if ($checkRifleNumber->num_rows == 0) {
        $addRifleNumber = "ALTER TABLE rifles ADD COLUMN rifle_number VARCHAR(50) UNIQUE AFTER id";
        if ($link->query($addRifleNumber)) {
            echo "<p>✅ rifle_number column added to rifles table</p>";
        } else {
            echo "<p>❌ Failed to add rifle_number column: " . $link->error . "</p>";
        }
    } else {
        echo "<p>✅ rifle_number column already exists in rifles table</p>";
    }
    
    // 2. Add middle_name column to cadet_profiles table
    echo "<h3>2. Adding middle_name column to cadet_profiles table...</h3>";
    
    // Check if middle_name column exists
    $checkMiddleName = $link->query("SHOW COLUMNS FROM cadet_profiles LIKE 'middle_name'");
    
    if ($checkMiddleName->num_rows == 0) {
        $addMiddleName = "ALTER TABLE cadet_profiles ADD COLUMN middle_name VARCHAR(50) AFTER first_name";
        if ($link->query($addMiddleName)) {
            echo "<p>✅ middle_name column added to cadet_profiles table</p>";
        } else {
            echo "<p>❌ Failed to add middle_name column: " . $link->error . "</p>";
        }
    } else {
        echo "<p>✅ middle_name column already exists in cadet_profiles table</p>";
    }
    
    // 3. Fix full_name column default value in users table
    echo "<h3>3. Fixing full_name column default value in users table...</h3>";
    
    // Check current full_name column definition
    $checkFullName = $link->query("SHOW COLUMNS FROM users LIKE 'full_name'");
    $fullNameInfo = $checkFullName->fetch_assoc();
    
    if ($fullNameInfo && $fullNameInfo['Null'] == 'NO' && $fullNameInfo['Default'] === null) {
        // Modify full_name to allow NULL or set default
        $modifyFullName = "ALTER TABLE users MODIFY COLUMN full_name VARCHAR(100) NULL";
        if ($link->query($modifyFullName)) {
            echo "<p>✅ full_name column modified to allow NULL values</p>";
        } else {
            echo "<p>❌ Failed to modify full_name column: " . $link->error . "</p>";
        }
    } else {
        echo "<p>✅ full_name column already allows NULL or has default value</p>";
    }
    
    // 4. Check if full_name column exists in cadet_profiles and fix if needed
    echo "<h3>4. Checking full_name column in cadet_profiles table...</h3>";
    
    $checkCadetFullName = $link->query("SHOW COLUMNS FROM cadet_profiles LIKE 'full_name'");
    $cadetFullNameInfo = $checkCadetFullName->fetch_assoc();
    
    if ($cadetFullNameInfo && $cadetFullNameInfo['Null'] == 'NO' && $cadetFullNameInfo['Default'] === null) {
        // Modify full_name to allow NULL or set default
        $modifyCadetFullName = "ALTER TABLE cadet_profiles MODIFY COLUMN full_name VARCHAR(100) NULL";
        if ($link->query($modifyCadetFullName)) {
            echo "<p>✅ full_name column in cadet_profiles modified to allow NULL values</p>";
        } else {
            echo "<p>❌ Failed to modify full_name column in cadet_profiles: " . $link->error . "</p>";
        }
    } else {
        echo "<p>✅ full_name column in cadet_profiles already allows NULL or has default value</p>";
    }
    
    // 5. Update existing rifles with rifle_number if they don't have one
    echo "<h3>5. Updating existing rifles with rifle_number...</h3>";
    
    $riflesWithoutNumber = $link->query("SELECT id FROM rifles WHERE rifle_number IS NULL OR rifle_number = ''");
    $count = 0;
    
    while ($rifle = $riflesWithoutNumber->fetch_assoc()) {
        $rifleNumber = 'R' . str_pad($rifle['id'], 4, '0', STR_PAD_LEFT);
        $updateRifle = $link->prepare("UPDATE rifles SET rifle_number = ? WHERE id = ?");
        $updateRifle->bind_param('si', $rifleNumber, $rifle['id']);
        
        if ($updateRifle->execute()) {
            $count++;
        }
    }
    
    echo "<p>✅ Updated $count rifles with rifle_number</p>";
    
    echo "<h3>Database Column Fixes Complete!</h3>";
    echo "<p>All missing columns have been added and configured properly.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
?>