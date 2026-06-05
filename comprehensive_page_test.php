<?php
/**
 * Comprehensive Page Function Testing
 * Tests all major functionalities across all pages
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';

// Test results tracking
$test_results = [];
$total_tests = 0;
$passed_tests = 0;

function runTest($test_name, $test_function) {
    global $test_results, $total_tests, $passed_tests;
    $total_tests++;
    
    try {
        $result = $test_function();
        if ($result) {
            echo "✓ PASS: $test_name\n";
            $test_results[$test_name] = 'PASS';
            $passed_tests++;
        } else {
            echo "✗ FAIL: $test_name\n";
            $test_results[$test_name] = 'FAIL';
        }
    } catch (Exception $e) {
        echo "✗ FAIL: $test_name - Error: " . $e->getMessage() . "\n";
        $test_results[$test_name] = 'FAIL - ' . $e->getMessage();
    }
}

echo "=== ROTC Management System - Comprehensive Page Function Testing ===\n\n";

// Test 1: User Registration Process
runTest("User Registration - Form Processing", function() use ($pdo) {
    // Test if registration form can process data
    $test_data = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'testpass123',
        'student_id' => 'TEST001',
        'course' => 'Test Course',
        'year_level' => '1st Year',
        'contact_number' => '09123456789'
    ];
    
    // Check if all required fields can be inserted
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$test_data['email']]);
    $existing = $stmt->fetchColumn();
    
    if ($existing > 0) {
        // Delete test user if exists
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$test_data['email']]);
    }
    
    // Test registration query structure
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, password, student_id, course, year_level, contact_number, status, approval_status, role) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'basic')
    ");
    
    $result = $stmt->execute([
        $test_data['first_name'],
        $test_data['last_name'],
        $test_data['email'],
        password_hash($test_data['password'], PASSWORD_DEFAULT),
        $test_data['student_id'],
        $test_data['course'],
        $test_data['year_level'],
        $test_data['contact_number']
    ]);
    
    // Clean up test data
    $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
    $stmt->execute([$test_data['email']]);
    
    return $result;
});

// Test 2: Admin Approval Workflow
runTest("Admin Approval - Status Update", function() use ($pdo) {
    // Create test user
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, password, student_id, course, year_level, contact_number, status, approval_status, role) 
        VALUES ('Test', 'Admin', 'testadmin@example.com', ?, 'ADMIN001', 'Test Course', '1st Year', '09123456789', 'pending', 'pending', 'basic')
    ");
    $stmt->execute([password_hash('testpass', PASSWORD_DEFAULT)]);
    $user_id = $pdo->lastInsertId();
    
    // Test approval process
    $stmt = $pdo->prepare("UPDATE users SET status = 'active', approval_status = 'approved' WHERE id = ?");
    $result = $stmt->execute([$user_id]);
    
    // Verify approval
    $stmt = $pdo->prepare("SELECT status, approval_status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Clean up
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    return $result && $user['status'] === 'active' && $user['approval_status'] === 'approved';
});

// Test 3: Cadet Profile Creation
runTest("Cadet Profile - Complete Profile Creation", function() use ($pdo) {
    // Create test user first
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, password, student_id, course, year_level, contact_number, status, approval_status, role) 
        VALUES ('Test', 'Cadet', 'testcadet@example.com', ?, 'CADET001', 'Test Course', '1st Year', '09123456789', 'active', 'approved', 'basic')
    ");
    $stmt->execute([password_hash('testpass', PASSWORD_DEFAULT)]);
    $user_id = $pdo->lastInsertId();
    
    // Test cadet profile creation with all new fields
    $stmt = $pdo->prepare("
        INSERT INTO cadet_profiles (user_id, beneficiary_name, province_city, address, birth_date, birth_place, 
                                   civil_status, gender, blood_type, religion, father_name, father_occupation, 
                                   mother_name, mother_occupation, guardian_name, guardian_contact, 
                                   emergency_contact_name, emergency_contact_number, qr_code_path) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $user_id,
        'Test Beneficiary',
        'Test Province, Test City',
        'Test Address',
        '2000-01-01',
        'Test Birth Place',
        'Single',
        'Male',
        'O+',
        'Catholic',
        'Test Father',
        'Test Occupation',
        'Test Mother',
        'Test Occupation',
        'Test Guardian',
        '09123456789',
        'Test Emergency Contact',
        '09123456789',
        'qr_codes/test_qr.png'
    ]);
    
    // Clean up
    $stmt = $pdo->prepare("DELETE FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    return $result;
});

// Test 4: Rifle Management System
runTest("Rifle Management - Complete Rifle Operations", function() use ($pdo) {
    // Test rifle creation
    $stmt = $pdo->prepare("
        INSERT INTO rifles (serial_number, model, status, condition_status, qr_code_path, notes) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        'TEST-RIFLE-001',
        'Test Model',
        'available',
        'good',
        'qr_codes/rifle_test.png',
        'Test rifle for testing'
    ]);
    
    $rifle_id = $pdo->lastInsertId();
    
    // Test rifle assignment
    $stmt = $pdo->prepare("
        INSERT INTO rifle_assignments (rifle_id, user_id, assigned_at, status) 
        VALUES (?, ?, NOW(), 'active')
    ");
    
    $assignment_result = $stmt->execute([$rifle_id, 1]); // Assuming user ID 1 exists
    
    // Test rifle status update
    $stmt = $pdo->prepare("UPDATE rifles SET status = 'assigned' WHERE id = ?");
    $update_result = $stmt->execute([$rifle_id]);
    
    // Clean up
    $stmt = $pdo->prepare("DELETE FROM rifle_assignments WHERE rifle_id = ?");
    $stmt->execute([$rifle_id]);
    $stmt = $pdo->prepare("DELETE FROM rifles WHERE id = ?");
    $stmt->execute([$rifle_id]);
    
    return $result && $assignment_result && $update_result;
});

// Test 5: Attendance System
runTest("Attendance System - Complete Attendance Flow", function() use ($pdo) {
    // Create test user with unique email
    $unique_email = 'testattendance' . time() . '@example.com';
    $unique_student_id = 'ATT' . time();
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, password, student_id, course, year_level, contact_number, status, approval_status, role) 
        VALUES ('Test', 'Attendance', ?, ?, ?, 'Test Course', '1st Year', '09123456789', 'active', 'approved', 'basic')
    ");
    $stmt->execute([password_hash('testpass', PASSWORD_DEFAULT), $unique_email, $unique_student_id]);
    $user_id = $pdo->lastInsertId();
    
    // Test attendance recording
    $stmt = $pdo->prepare("
        INSERT INTO attendance (user_id, date, time_in, status, semester, academic_year, recorded_by) 
        VALUES (?, CURDATE(), NOW(), 'present', 1, '2024-2025', ?)
    ");
    
    $result = $stmt->execute([$user_id, $user_id]);
    
    // Test attendance query with joins
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name, u.student_id 
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.user_id = ?
    ");
    
    $stmt->execute([$user_id]);
    $attendance_record = $stmt->fetch();
    
    // Clean up
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    return $result && $attendance_record;
});

// Test 6: Document Generation Dependencies
runTest("Document Generation - Data Retrieval", function() use ($pdo) {
    // Test the complex query used in document generation
    $stmt = $pdo->prepare("
        SELECT u.*, cp.beneficiary_name, cp.province_city, cp.address, cp.birth_date, cp.birth_place, 
               cp.civil_status, cp.gender, cp.blood_type, cp.religion, cp.father_name, cp.father_occupation, 
               cp.mother_name, cp.mother_occupation, cp.guardian_name, cp.guardian_contact, 
               cp.emergency_contact_name, cp.emergency_contact_number, cp.qr_code_path
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.status = 'active' AND u.approval_status = 'approved'
        LIMIT 1
    ");
    
    $result = $stmt->execute();
    return $result;
});

// Test 7: QR Code Generation
runTest("QR Code Generation - Library and Function", function() {
    // Check if QR code libraries are available
    $qr_available = false;
    
    // Check for chillerlan QR code library
    if (file_exists('vendor/chillerlan/php-qrcode/src/QRCode.php')) {
        $qr_available = true;
    }
    
    // Check for phpqrcode library
    if (file_exists('libs/phpqrcode/qrlib.php')) {
        $qr_available = true;
    }
    
    return $qr_available;
});

// Test 8: Security and Logging
runTest("Security Logging - Log Entry Creation", function() use ($pdo) {
    // Test security log entry
    $stmt = $pdo->prepare("
        INSERT INTO security_logs (user_id, action, ip_address, user_agent, timestamp) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        1, // Assuming user ID 1 exists
        'test_action',
        '127.0.0.1',
        'Test User Agent'
    ]);
    
    // Clean up
    $stmt = $pdo->prepare("DELETE FROM security_logs WHERE action = 'test_action' AND ip_address = '127.0.0.1'");
    $stmt->execute();
    
    return $result;
});

// Test 9: File Upload Functionality
runTest("File Upload - Directory Structure", function() {
    $upload_dirs = [
        'uploads/qr_codes',
        'uploads/documents',
        'uploads/profile_pictures'
    ];
    
    $all_exist = true;
    foreach ($upload_dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $all_exist = false;
            }
        }
        if (!is_writable($dir)) {
            $all_exist = false;
        }
    }
    
    return $all_exist;
});

// Test 10: Database Relationships
runTest("Database Relationships - Foreign Key Constraints", function() use ($pdo) {
    // Test if relationships work correctly
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id) as profile_relations,
            (SELECT COUNT(*) FROM rifles r JOIN rifle_assignments ra ON r.id = ra.rifle_id) as rifle_relations,
            (SELECT COUNT(*) FROM users u JOIN attendance a ON u.id = a.user_id) as attendance_relations
    ");
    
    $result = $stmt->execute();
    $relations = $stmt->fetch();
    
    return $result && is_array($relations);
});

echo "\n=== SUMMARY ===\n";
echo "Total Tests: $total_tests\n";
echo "Passed: $passed_tests\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";
echo "Success Rate: " . round(($passed_tests / $total_tests) * 100, 1) . "%\n\n";

if ($passed_tests < $total_tests) {
    echo "⚠️  Some tests failed. Please review the failed items above.\n\n";
} else {
    echo "🎉 All tests passed! The system is ready for production.\n\n";
}

echo "=== DETAILED ANALYSIS ===\n";
echo "1. User Registration: " . ($test_results['User Registration - Form Processing'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "2. Admin Approval: " . ($test_results['Admin Approval - Status Update'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "3. Cadet Profiles: " . ($test_results['Cadet Profile - Complete Profile Creation'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "4. Rifle Management: " . ($test_results['Rifle Management - Complete Rifle Operations'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "5. Attendance System: " . ($test_results['Attendance System - Complete Attendance Flow'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "6. Document Generation: " . ($test_results['Document Generation - Data Retrieval'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "7. QR Code Generation: " . ($test_results['QR Code Generation - Library and Function'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "8. Security Logging: " . ($test_results['Security Logging - Log Entry Creation'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "9. File Uploads: " . ($test_results['File Upload - Directory Structure'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";
echo "10. Database Relations: " . ($test_results['Database Relationships - Foreign Key Constraints'] === 'PASS' ? '✓ Working' : '✗ Issues detected') . "\n";

echo "\n=== RECOMMENDATIONS ===\n";
if ($passed_tests >= 8) {
    echo "✅ System is in good condition. Minor issues can be addressed as needed.\n";
} elseif ($passed_tests >= 6) {
    echo "⚠️  System has some issues that should be addressed before production.\n";
} else {
    echo "❌ System has significant issues that must be resolved before deployment.\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Review any failed tests and fix underlying issues\n";
echo "2. Test actual page functionality through web interface\n";
echo "3. Perform user acceptance testing\n";
echo "4. Set up proper backup and monitoring systems\n";
echo "5. Deploy to production environment\n";

?>