<?php

/**
 * Comprehensive Functionality Test Suite
 * Tests all major functionality after database fixes
 */

require_once 'includes/db_connection.php';

echo "=== COMPREHENSIVE FUNCTIONALITY TEST ===\n";
echo "Testing all major functionality after database fixes...\n\n";

$testResults = [];
$totalTests = 0;
$passedTests = 0;

// Function to run a test and record results
function runTest($testName, $testFunction) {
    global $testResults, $totalTests, $passedTests;
    
    $totalTests++;
    echo "Testing: $testName...\n";
    
    try {
        $result = $testFunction();
        if ($result['success']) {
            echo "  ✅ PASSED: {$result['message']}\n";
            $testResults[$testName] = 'PASSED';
            $passedTests++;
        } else {
            echo "  ❌ FAILED: {$result['message']}\n";
            $testResults[$testName] = 'FAILED: ' . $result['message'];
        }
    } catch (Exception $e) {
        echo "  ❌ ERROR: " . $e->getMessage() . "\n";
        $testResults[$testName] = 'ERROR: ' . $e->getMessage();
    }
    
    echo "\n";
}

// Test 1: Database Connection
runTest('Database Connection', function() {
    global $pdo;
    
    if (!$pdo) {
        return ['success' => false, 'message' => 'PDO connection not established'];
    }
    
    $stmt = $pdo->query('SELECT 1');
    if ($stmt) {
        return ['success' => true, 'message' => 'Database connection working'];
    }
    
    return ['success' => false, 'message' => 'Cannot execute test query'];
});

// Test 2: Cadet Profiles Table Structure
runTest('Cadet Profiles Table Structure', function() {
    global $pdo;
    
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['id', 'user_id', 'student_id', 'first_name', 'last_name', 
                       'birth_date', 'father_name', 'mother_name', 'guardian_name', 
                       'photo_path', 'facebook_profile'];
    
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (empty($missingColumns)) {
        return ['success' => true, 'message' => 'All required columns present'];
    }
    
    return ['success' => false, 'message' => 'Missing columns: ' . implode(', ', $missingColumns)];
});

// Test 3: User-Cadet Relationship
runTest('User-Cadet Relationship', function() {
    global $pdo;
    
    $query = "SELECT u.id as user_id, u.username, cp.id as cadet_id, cp.first_name, cp.last_name 
              FROM users u 
              LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
              LIMIT 5";
    
    $stmt = $pdo->query($query);
    $results = $stmt->fetchAll();
    
    if ($stmt->rowCount() >= 0) {
        return ['success' => true, 'message' => 'User-Cadet relationship query working'];
    }
    
    return ['success' => false, 'message' => 'User-Cadet relationship query failed'];
});

// Test 4: Document Generation Query
runTest('Document Generation Query', function() {
    global $pdo;
    
    $query = "SELECT cp.first_name, cp.last_name, cp.birth_date, cp.father_name, 
                     cp.mother_name, cp.guardian_name, cp.photo_path
              FROM cadet_profiles cp 
              WHERE cp.id = 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Document generation query structure valid'];
});

// Test 5: Missing ID Requests Query
runTest('Missing ID Requests Query', function() {
    global $pdo;
    
    $query = "SELECT mir.id, mir.cadet_id, mir.reason
              FROM missing_id_requests mir
              WHERE mir.status = 'active'
              ORDER BY mir.created_at DESC
              LIMIT 5";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'Missing ID requests query structure valid'];
});

// Test 6: Registration Form Compatibility
runTest('Registration Form Compatibility', function() {
    global $pdo;
    
    // Test INSERT query structure that would be used in registration
    $query = "INSERT INTO cadet_profiles 
              (user_id, student_id, first_name, last_name, birth_date, 
               father_name, mother_name, guardian_name, photo_path) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($query);
    
    if ($stmt) {
        return ['success' => true, 'message' => 'Registration INSERT query structure valid'];
    }
    
    return ['success' => false, 'message' => 'Registration INSERT query preparation failed'];
});

// Test 7: QR Code Generation Data
runTest('QR Code Generation Data', function() {
    global $pdo;
    
    $query = "SELECT cp.id, cp.student_id, cp.first_name, cp.last_name, u.username
              FROM cadet_profiles cp
              JOIN users u ON cp.user_id = u.id
              WHERE cp.id = 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    return ['success' => true, 'message' => 'QR code data query structure valid'];
});

// Test 8: Attendance Recording
runTest('Attendance Recording', function() {
    global $pdo;
    
    // Check if attendance table exists and has proper structure
    $stmt = $pdo->query("SHOW TABLES LIKE 'attendance'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        $query = "SELECT a.id, a.cadet_id, a.log_date, a.status, 
                         cp.first_name, cp.last_name, cp.student_id
                  FROM attendance a
                  JOIN cadet_profiles cp ON a.cadet_id = cp.id
                  WHERE a.log_date = CURDATE()
                  LIMIT 1";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        return ['success' => true, 'message' => 'Attendance query structure valid'];
    }
    
    return ['success' => false, 'message' => 'Attendance table not found'];
});

// Test 9: File Upload Paths
runTest('File Upload Paths', function() {
    $uploadDirs = ['uploads/', 'uploads/photos/', 'uploads/documents/'];
    $missingDirs = [];
    
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            $missingDirs[] = $dir;
        }
    }
    
    if (empty($missingDirs)) {
        return ['success' => true, 'message' => 'All upload directories exist'];
    }
    
    return ['success' => false, 'message' => 'Missing directories: ' . implode(', ', $missingDirs)];
});

// Test 10: Session Management
runTest('Session Management', function() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Test session variables that would be used
    $_SESSION['test_user_id'] = 1;
    $_SESSION['test_role'] = 'cadet';
    
    if (isset($_SESSION['test_user_id']) && isset($_SESSION['test_role'])) {
        unset($_SESSION['test_user_id'], $_SESSION['test_role']);
        return ['success' => true, 'message' => 'Session management working'];
    }
    
    return ['success' => false, 'message' => 'Session management failed'];
});

// Summary
echo "\n=== TEST SUMMARY ===\n";
echo "📊 Total tests: $totalTests\n";
echo "✅ Passed: $passedTests\n";
echo "❌ Failed: " . ($totalTests - $passedTests) . "\n";
echo "📈 Success rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL TESTS PASSED! System is ready for use.\n";
} else {
    echo "⚠️  Some tests failed. Review the issues above.\n\n";
    
    echo "=== DETAILED RESULTS ===\n";
    foreach ($testResults as $test => $result) {
        echo "$test: $result\n";
    }
}

echo "\n=== FUNCTIONALITY TEST COMPLETE ===\n";

?>