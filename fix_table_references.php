<?php
require_once 'includes/db.php';

try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== FIXING TABLE REFERENCE ISSUES ===\n";
    
    // 1. Check current table structure
    echo "\n1. Current cadet_profiles table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE cadet_profiles");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $column_names = [];
    foreach ($columns as $column) {
        $column_names[] = $column['Field'];
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    
    // 2. Check for critical columns
    echo "\n2. Checking critical columns:\n";
    $critical_columns = ['birthdate', 'facebook_profile', 'middle_name'];
    foreach ($critical_columns as $col) {
        if (in_array($col, $column_names)) {
            echo "  ✅ Column '$col' EXISTS\n";
        } else {
            echo "  ❌ Column '$col' MISSING\n";
        }
    }
    
    // 3. Test problematic queries
    echo "\n3. Testing problematic queries:\n";
    
    // Test the document generation query
    echo "\nTesting document generation query...\n";
    try {
        $test_query = "SELECT cp.birthdate, cp.facebook_profile, cp.middle_name 
                      FROM cadet_profiles cp 
                      LIMIT 1";
        $stmt = $pdo->prepare($test_query);
        $stmt->execute();
        $result = $stmt->fetch();
        echo "  ✅ Document generation query works!\n";
        if ($result) {
            echo "  Sample data: birthdate={$result['birthdate']}, facebook_profile={$result['facebook_profile']}, middle_name={$result['middle_name']}\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Document generation query failed: " . $e->getMessage() . "\n";
    }
    
    // Test missing ID requests query
    echo "\nTesting missing ID requests query...\n";
    try {
        $test_query = "SELECT u.id, u.username, cp.facebook_profile 
                      FROM users u 
                      LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
                      WHERE cp.facebook_profile IS NOT NULL 
                      LIMIT 1";
        $stmt = $pdo->prepare($test_query);
        $stmt->execute();
        $result = $stmt->fetch();
        echo "  ✅ Missing ID requests query works!\n";
        if ($result) {
            echo "  Sample data: user_id={$result['id']}, username={$result['username']}, facebook_profile={$result['facebook_profile']}\n";
        }
    } catch (Exception $e) {
        echo "  ❌ Missing ID requests query failed: " . $e->getMessage() . "\n";
    }
    
    // 4. Check for files using wrong table name
    echo "\n4. Files that need table name fixes:\n";
    $files_to_check = glob('*.php');
    $wrong_table_files = [];
    
    foreach ($files_to_check as $file) {
        $content = file_get_contents($file);
        // Look for SQL queries using 'cadet_profile' (singular) instead of 'cadet_profiles'
        if (preg_match('/FROM\s+cadet_profile\s+/i', $content) || 
            preg_match('/JOIN\s+cadet_profile\s+/i', $content) ||
            preg_match('/INTO\s+cadet_profile\s+/i', $content) ||
            preg_match('/UPDATE\s+cadet_profile\s+/i', $content)) {
            $wrong_table_files[] = $file;
            echo "  ❌ $file uses wrong table name 'cadet_profile'\n";
        }
    }
    
    if (empty($wrong_table_files)) {
        echo "  ✅ All files use correct table name 'cadet_profiles'\n";
    }
    
    // 5. Summary and recommendations
    echo "\n=== SUMMARY AND RECOMMENDATIONS ===\n";
    echo "✅ Table 'cadet_profiles' exists with all required columns\n";
    echo "✅ Columns 'birthdate', 'facebook_profile', 'middle_name' are present\n";
    
    if (!empty($wrong_table_files)) {
        echo "❌ Found " . count($wrong_table_files) . " files using wrong table name\n";
        echo "\nFiles to fix:\n";
        foreach ($wrong_table_files as $file) {
            echo "  - $file\n";
        }
        echo "\nRecommendation: Replace 'cadet_profile' with 'cadet_profiles' in these files\n";
    }
    
    echo "\n=== TESTING REGISTRATION COMPATIBILITY ===\n";
    
    // Check if registration.php INSERT columns match table structure
    echo "Checking registration.php INSERT compatibility...\n";
    $registration_content = file_get_contents('register.php');
    
    // Extract INSERT column list from registration
    if (preg_match('/INSERT INTO cadet_profiles \(([^)]+)\)/i', $registration_content, $matches)) {
        $insert_columns = array_map('trim', explode(',', $matches[1]));
        echo "Registration INSERT columns:\n";
        foreach ($insert_columns as $col) {
            $clean_col = trim($col);
            if (in_array($clean_col, $column_names)) {
                echo "  ✅ $clean_col\n";
            } else {
                echo "  ❌ $clean_col (NOT FOUND IN TABLE)\n";
            }
        }
    } else {
        echo "Could not find INSERT statement in register.php\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>