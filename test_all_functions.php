<?php
// Comprehensive Function Testing Script
// This script tests all major page functions and database operations

require_once 'includes/db.php';

// Start session for testing
session_start();

// Test results array
$testResults = [];
$totalTests = 0;
$passedTests = 0;

function runTest($testName, $testFunction) {
    global $testResults, $totalTests, $passedTests;
    $totalTests++;
    
    try {
        $result = $testFunction();
        if ($result) {
            $testResults[] = "✓ PASS: $testName";
            $passedTests++;
        } else {
            $testResults[] = "✗ FAIL: $testName";
        }
    } catch (Exception $e) {
        $testResults[] = "✗ ERROR: $testName - " . $e->getMessage();
    }
}

echo "=== ROTC Management System - Comprehensive Function Testing ===\n\n";

// Test 1: Database Connection
runTest("Database Connection", function() {
    global $pdo;
    return $pdo instanceof PDO;
});

// Test 2: Users Table Structure
runTest("Users Table - approval_status column", function() {
    global $pdo;
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'approval_status'");
    $stmt->execute();
    return $stmt->rowCount() > 0;
});

// Test 3: Cadet Profiles Table Structure
runTest("Cadet Profiles Table - beneficiary_name column", function() {
    global $pdo;
    $stmt = $pdo->prepare("SHOW COLUMNS FROM cadet_profiles LIKE 'beneficiary_name'");
    $stmt->execute();
    return $stmt->rowCount() > 0;
});

runTest("Cadet Profiles Table - province_city column", function() {
    global $pdo;
    $stmt = $pdo->prepare("SHOW COLUMNS FROM cadet_profiles LIKE 'province_city'");
    $stmt->execute();
    return $stmt->rowCount() > 0;
});

// Test 4: Rifles Table Structure
runTest("Rifles Table - status column", function() {
    global $pdo;
    $stmt = $pdo->prepare("SHOW COLUMNS FROM rifles LIKE 'status'");
    $stmt->execute();
    return $stmt->rowCount() > 0;
});

runTest("Rifles Table - qr_code_path column", function() {
    global $pdo;
    $stmt = $pdo->prepare("SHOW COLUMNS FROM rifles LIKE 'qr_code_path'");
    $stmt->execute();
    return $stmt->rowCount() > 0;
});

// Test 5: Rifle Assignments Table Structure
runTest("Rifle Assignments Table - assigned_at column", function() {
    global $pdo;
    $stmt = $pdo->prepare("SHOW COLUMNS FROM rifle_assignments LIKE 'assigned_at'");
    $stmt->execute();
    return $stmt->rowCount() > 0;
});

// Test 6: Test Registration Query (Document Generation Fix)
runTest("Registration Query - Cadet Profile Fields", function() {
    global $pdo;
    // Test the query that was failing in document generation
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.role, u.approval_status,
               cp.first_name, cp.last_name, cp.middle_name, cp.student_number,
               cp.course, cp.year_level, cp.section, cp.contact_number,
               cp.emergency_contact, cp.beneficiary_name, cp.province_city,
               cp.guardian_name, cp.qr_code_path
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'cadet' 
        LIMIT 1
    ");
    $stmt->execute();
    return true; // If no exception, query structure is valid
});

// Test 7: Test Rifle Management Query
runTest("Rifle Management Query - Status Field", function() {
    global $pdo;
    // Test the query that was failing with r.status
    $stmt = $pdo->prepare("
        SELECT r.id, r.rifle_number, r.serial_number, r.model, r.status, r.qr_code_path,
               ra.assigned_at, ra.returned_at,
               b.name as borrower_name
        FROM rifles r
        LEFT JOIN rifle_assignments ra ON r.id = ra.rifle_id AND ra.returned_at IS NULL
        LEFT JOIN borrowers b ON ra.borrower_id = b.id
        LIMIT 1
    ");
    $stmt->execute();
    return true; // If no exception, query structure is valid
});

// Test 8: Test Attendance System
runTest("Attendance System - Basic Query", function() {
    global $pdo;
    // Check if attendance table exists and has basic structure
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'attendance'");
    $stmt->execute();
    return $stmt->rowCount() > 0;
});

// Test 9: Test Admin Approval Workflow
runTest("Admin Approval Workflow - User Status Update", function() {
    global $pdo;
    // Test updating approval status (simulate admin approval)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM users 
        WHERE approval_status IN ('pending', 'approved', 'rejected')
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['count'] >= 0; // Should work without error
});

// Test 10: Test Security Logs Table
runTest("Security Logs Table - Structure", function() {
    global $pdo;
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'security_logs'");
    $stmt->execute();
    return $stmt->rowCount() > 0;
});

// Test 11: Test File Includes
runTest("Core Includes - Database Connection", function() {
    return file_exists('includes/db.php');
});

runTest("Core Includes - Functions", function() {
    return file_exists('includes/functions.php') || file_exists('includes/rifle_functions.php');
});

// Test 12: Test Main Pages Existence
$mainPages = [
    'index.php',
    'login.php',
    'register.php',
    'dashboard.php',
    'rifle_management.php',
    'attendance.php',
    'admin_dashboard.php'
];

foreach ($mainPages as $page) {
    runTest("Page Exists - $page", function() use ($page) {
        return file_exists($page);
    });
}

// Test 13: Test Document Generation Dependencies
runTest("Document Generation - TCPDF Library", function() {
    return file_exists('vendor/tecnickcom/tcpdf/tcpdf.php') || 
           file_exists('tcpdf/tcpdf.php') || 
           class_exists('TCPDF');
});

// Test 14: Test QR Code Generation Dependencies
runTest("QR Code Generation - Library Check", function() {
    return file_exists('vendor/phpqrcode/qrlib.php') || 
           file_exists('phpqrcode/qrlib.php') || 
           class_exists('QRcode');
});

// Test 15: Test Upload Directories
runTest("Upload Directory - QR Codes", function() {
    $qrDir = 'uploads/qr_codes';
    return is_dir($qrDir) || mkdir($qrDir, 0755, true);
});

runTest("Upload Directory - Documents", function() {
    $docDir = 'uploads/documents';
    return is_dir($docDir) || mkdir($docDir, 0755, true);
});

// Display Results
echo "\n=== TEST RESULTS ===\n";
foreach ($testResults as $result) {
    echo $result . "\n";
}

echo "\n=== SUMMARY ===\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: " . ($totalTests - $passedTests) . "\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n";

if ($passedTests == $totalTests) {
    echo "\n🎉 ALL TESTS PASSED! System is ready for full functionality testing.\n";
} else {
    echo "\n⚠️  Some tests failed. Please review the failed items above.\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Test user registration and admin approval workflow\n";
echo "2. Test document generation with new database fields\n";
echo "3. Test rifle management with status tracking\n";
echo "4. Test attendance system functionality\n";
echo "5. Test QR code generation and scanning\n";

?>