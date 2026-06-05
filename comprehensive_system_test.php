<?php
require_once 'includes/db.php';

echo "=== Comprehensive System Function Test ===\n\n";

try {
    // 1. Test Database Structure
    echo "1. Testing Database Structure...\n";
    
    // Check users table
    $stmt = $pdo->query("DESCRIBE users");
    $user_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_user_cols = ['id', 'first_name', 'last_name', 'role', 'approval_status', 'status'];
    
    foreach ($required_user_cols as $col) {
        if (in_array($col, $user_columns)) {
            echo "   ✓ users.{$col} exists\n";
        } else {
            echo "   ✗ users.{$col} missing!\n";
        }
    }
    
    // Check cadet_profiles table
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $profile_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_profile_cols = ['id', 'user_id', 'first_name', 'middle_name', 'last_name', 'student_number', 'beneficiary_address', 'region'];
    
    foreach ($required_profile_cols as $col) {
        if (in_array($col, $profile_columns)) {
            echo "   ✓ cadet_profiles.{$col} exists\n";
        } else {
            echo "   ✗ cadet_profiles.{$col} missing!\n";
        }
    }
    
    // Check rifles table
    $stmt = $pdo->query("DESCRIBE rifles");
    $rifle_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_rifle_cols = ['id', 'serial_number', 'rifle_type', 'assigned_to'];
    
    foreach ($required_rifle_cols as $col) {
        if (in_array($col, $rifle_columns)) {
            echo "   ✓ rifles.{$col} exists\n";
        } else {
            echo "   ✗ rifles.{$col} missing!\n";
        }
    }
    
    // Check attendance_logs table
    $stmt = $pdo->query("DESCRIBE attendance_logs");
    $attendance_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required_attendance_cols = ['id', 'cadet_profile_id', 'event_name', 'created_at'];
    
    foreach ($required_attendance_cols as $col) {
        if (in_array($col, $attendance_columns)) {
            echo "   ✓ attendance_logs.{$col} exists\n";
        } else {
            echo "   ✗ attendance_logs.{$col} missing!\n";
        }
    }
    
    // 2. Test User Registration and Approval Workflow
    echo "\n2. Testing User Registration and Approval Workflow...\n";
    
    // Count users by approval status
    $stmt = $pdo->query("SELECT approval_status, COUNT(*) as count FROM users GROUP BY approval_status");
    $approval_stats = $stmt->fetchAll();
    
    echo "   User approval statistics:\n";
    foreach ($approval_stats as $stat) {
        echo "     - {$stat['approval_status']}: {$stat['count']} users\n";
    }
    
    // Check if admin dashboard approval function exists
    if (file_exists('admin_dashboard.php')) {
        echo "   ✓ admin_dashboard.php exists for user approval\n";
    } else {
        echo "   ✗ admin_dashboard.php missing!\n";
    }
    
    // 3. Test QR Generation
    echo "\n3. Testing QR Generation...\n";
    
    // Check if QR generation files exist
    $qr_files = ['generate_qr.php', 'qr_generator.php', 'generate_user_qr.php'];
    $qr_found = false;
    
    foreach ($qr_files as $file) {
        if (file_exists($file)) {
            echo "   ✓ {$file} exists\n";
            $qr_found = true;
        }
    }
    
    if (!$qr_found) {
        echo "   ✗ No QR generation files found!\n";
    }
    
    // 4. Test Rifle Management
    echo "\n4. Testing Rifle Management...\n";
    
    // Count rifles
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rifles");
    $rifle_count = $stmt->fetch()['count'];
    echo "   Total rifles in database: {$rifle_count}\n";
    
    // Check rifle types
    $stmt = $pdo->query("SELECT DISTINCT rifle_type FROM rifles WHERE rifle_type IS NOT NULL");
    $rifle_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   Rifle types: " . (empty($rifle_types) ? 'None' : implode(', ', $rifle_types)) . "\n";
    
    // Check if rifle management files exist
    $rifle_files = ['rifle_management.php', 'rifles.php', 'manage_rifles.php'];
    $rifle_mgmt_found = false;
    
    foreach ($rifle_files as $file) {
        if (file_exists($file)) {
            echo "   ✓ {$file} exists\n";
            $rifle_mgmt_found = true;
        }
    }
    
    if (!$rifle_mgmt_found) {
        echo "   ✗ No rifle management files found!\n";
    }
    
    // 5. Test Document Generation
    echo "\n5. Testing Document Generation...\n";
    
    // Check if document generation files exist
    $doc_files = ['generate_document.php', 'document_generator.php', 'documents.php'];
    $doc_found = false;
    
    foreach ($doc_files as $file) {
        if (file_exists($file)) {
            echo "   ✓ {$file} exists\n";
            $doc_found = true;
        }
    }
    
    if (!$doc_found) {
        echo "   ✗ No document generation files found!\n";
    }
    
    // Test document generation query
    $stmt = $pdo->query("
        SELECT u.first_name, u.last_name, 
               CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) as full_name,
               cp.student_number, cp.beneficiary_address, cp.region, cp.beneficiary_relationship
        FROM users u 
        JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' 
        LIMIT 1
    ");
    $sample_cadet = $stmt->fetch();
    
    if ($sample_cadet) {
        echo "   ✓ Document generation query works\n";
        echo "     Sample data: {$sample_cadet['full_name']} - Address: {$sample_cadet['beneficiary_address']}, Region: {$sample_cadet['region']}\n";
    } else {
        echo "   ⚠ No approved cadets with complete profile data for document generation\n";
    }
    
    // 6. Test Attendance System
    echo "\n6. Testing Attendance System...\n";
    
    // Check attendance files
    $attendance_files = ['attendance/dashboard.php', 'attendance/process_qr.php', 'attendance/manual_attendance.php'];
    
    foreach ($attendance_files as $file) {
        if (file_exists($file)) {
            echo "   ✓ {$file} exists\n";
        } else {
            echo "   ✗ {$file} missing!\n";
        }
    }
    
    // Test attendance query with approval filtering
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as approved_count
        FROM users u 
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' AND u.status = 'active'
    ");
    $approved_count = $stmt->fetch()['approved_count'];
    echo "   Approved cadets eligible for attendance: {$approved_count}\n";
    
    // 7. Test Role Consistency
    echo "\n7. Testing Role Consistency...\n";
    
    $stmt = $pdo->query("SELECT DISTINCT role FROM users ORDER BY role");
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   User roles in system: " . implode(', ', $roles) . "\n";
    
    // Check for old 'cadet' role vs new 'basic-cadet'
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'cadet'");
    $old_cadet_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'basic-cadet'");
    $new_cadet_count = $stmt->fetch()['count'];
    
    echo "   Old 'cadet' role users: {$old_cadet_count}\n";
    echo "   New 'basic-cadet' role users: {$new_cadet_count}\n";
    
    if ($old_cadet_count > 0) {
        echo "   ⚠ Warning: Found users with old 'cadet' role - should be 'basic-cadet'\n";
    }
    
    echo "\n=== Comprehensive System Test Complete ===\n";
    echo "\nSUMMARY:\n";
    echo "- Database structure: Check individual components above\n";
    echo "- User approval workflow: {$approved_count} approved cadets ready\n";
    echo "- Role consistency: " . ($old_cadet_count > 0 ? "Needs attention" : "Good") . "\n";
    echo "- System files: Check individual components above\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>