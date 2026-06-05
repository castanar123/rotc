<?php
/**
 * Test script to verify rifle types are working correctly
 */

require_once 'includes/db.php';
require_once 'includes/rifle_functions.php';

echo "🔫 Testing Rifle Type System\n";
echo "=================================\n\n";

// Test 1: Check database structure
echo "1. Checking database structure...\n";
try {
    $result = $pdo->query("DESCRIBE rifles");
    $columns = [];
    while ($row = $result->fetch()) {
        $columns[] = $row['Field'];
        if ($row['Field'] === 'rifle_type') {
            echo "✅ rifle_type column exists: {$row['Type']}\n";
        }
    }
    
    if (!in_array('rifle_type', $columns)) {
        echo "❌ rifle_type column not found!\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Error checking structure: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check rifle type distribution
echo "\n2. Checking rifle type distribution...\n";
try {
    $result = $pdo->query("SELECT rifle_type, COUNT(*) as count FROM rifles GROUP BY rifle_type");
    while ($row = $result->fetch()) {
        echo "   {$row['rifle_type']}: {$row['count']} rifles\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking distribution: " . $e->getMessage() . "\n";
}

// Test 3: Show sample rifles by type
echo "\n3. Sample rifles by type...\n";
try {
    $result = $pdo->query("SELECT rifle_number, rifle_type, status FROM rifles ORDER BY rifle_type, rifle_number LIMIT 10");
    while ($row = $result->fetch()) {
        echo "   {$row['rifle_number']} - {$row['rifle_type']} - {$row['status']}\n";
    }
} catch (Exception $e) {
    echo "❌ Error getting samples: " . $e->getMessage() . "\n";
}

// Test 4: Test getAllRifles function
echo "\n4. Testing getAllRifles function...\n";
try {
    if (function_exists('getAllRifles')) {
        $rifles = getAllRifles(1, 5); // Get first 5 rifles
        if (isset($rifles['rifles']) && count($rifles['rifles']) > 0) {
            echo "✅ getAllRifles function working\n";
            foreach ($rifles['rifles'] as $rifle) {
                if (isset($rifle['rifle_type'])) {
                    echo "   {$rifle['rifle_number']} - {$rifle['rifle_type']}\n";
                } else {
                    echo "❌ rifle_type field missing in getAllRifles result\n";
                }
            }
        } else {
            echo "❌ getAllRifles returned no data\n";
        }
    } else {
        echo "❌ getAllRifles function not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing getAllRifles: " . $e->getMessage() . "\n";
}

// Test 5: Test ENUM constraint
echo "\n5. Testing ENUM constraint...\n";
try {
    // Try to insert invalid rifle type (should fail)
    $stmt = $pdo->prepare("INSERT INTO rifles (rifle_number, rifle_type, status) VALUES (?, ?, 'available')");
    $test_number = 'TEST_ENUM_' . time();
    $invalid_type = 'invalid_type';
    
    try {
        $stmt->execute([$test_number, $invalid_type]);
        echo "❌ ENUM constraint not working - invalid type was accepted\n";
        // Clean up
        $pdo->exec("DELETE FROM rifles WHERE rifle_number = '$test_number'");
    } catch (Exception $e) {
        echo "✅ ENUM constraint working - invalid type rejected\n";
    }
    
    // Try to insert valid rifle type (should succeed)
    try {
        $stmt->execute([$test_number, 'mechanical rifle']);
        echo "✅ Valid rifle type accepted\n";
        // Clean up
        $pdo->exec("DELETE FROM rifles WHERE rifle_number = '$test_number'");
    } catch (Exception $e) {
        echo "❌ Valid rifle type rejected: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error testing ENUM: " . $e->getMessage() . "\n";
}

echo "\n🎉 Testing completed!\n";

?>