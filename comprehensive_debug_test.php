<?php
/**
 * Comprehensive Debug Test Script
 * Tests all major functions and identifies specific errors
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';

echo "=== COMPREHENSIVE SYSTEM DEBUG TEST ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Check rifle_type column and rifle management
echo "1. TESTING RIFLE MANAGEMENT\n";
echo "================================\n";
try {
    // Check rifle table structure
    $result = $link->query("DESCRIBE rifles");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    echo "✓ Rifle table columns: " . implode(', ', $columns) . "\n";
    
    // Test rifle_type column specifically
    if (in_array('rifle_type', $columns)) {
        echo "✓ rifle_type column exists\n";
        
        // Test rifle query with rifle_type
        $stmt = $link->prepare("SELECT id, rifle_number, rifle_type, status FROM rifles LIMIT 3");
        $stmt->execute();
        $rifles = $stmt->get_result();
        echo "✓ Rifle query with rifle_type successful\n";
        
        while ($rifle = $rifles->fetch_assoc()) {
            echo "   Rifle: {$rifle['rifle_number']} - Type: {$rifle['rifle_type']} - Status: {$rifle['status']}\n";
        }
    } else {
        echo "❌ rifle_type column missing!\n";
    }
} catch (Exception $e) {
    echo "❌ Rifle management error: " . $e->getMessage() . "\n";
}

// Test 2: Check cadet_profiles table for document generation
echo "\n2. TESTING CADET PROFILES FOR DOCUMENT GENERATION\n";
echo "================================================\n";
try {
    // Check cadet_profiles structure
    $result = $link->query("DESCRIBE cadet_profiles");
    $profile_columns = [];
    while ($row = $result->fetch_assoc()) {
        $profile_columns[] = $row['Field'];
    }
    echo "✓ Cadet profiles columns: " . implode(', ', $profile_columns) . "\n";
    
    // Check for required document generation columns
    $required_cols = ['beneficiary_address', 'region', 'beneficiary_relationship'];
    $missing_cols = [];
    foreach ($required_cols as $col) {
        if (!in_array($col, $profile_columns)) {
            $missing_cols[] = $col;
        }
    }
    
    if (empty($missing_cols)) {
        echo "✓ All required document generation columns exist\n";
        
        // Test document generation query
        $doc_query = "
            SELECT 
                u.id as user_id,
                u.username,
                u.email,
                cp.first_name,
                cp.middle_name,
                cp.last_name,
                cp.beneficiary_address,
                cp.region,
                cp.beneficiary_relationship,
                cp.platoon
            FROM users u
            JOIN cadet_profiles cp ON u.id = cp.user_id
            WHERE u.role = 'basic-cadet' 
            AND u.approval_status = 'approved'
            AND cp.first_name IS NOT NULL
            LIMIT 3
        ";
        
        $stmt = $link->prepare($doc_query);
        $stmt->execute();
        $doc_result = $stmt->get_result();
        
        if ($doc_result->num_rows > 0) {
            echo "✓ Document generation query successful\n";
            while ($row = $doc_result->fetch_assoc()) {
                echo "   User: {$row['first_name']} {$row['last_name']} - Region: {$row['region']} - Address: {$row['beneficiary_address']}\n";
            }
        } else {
            echo "⚠️ No approved cadets with complete profiles found for document generation\n";
        }
    } else {
        echo "❌ Missing document generation columns: " . implode(', ', $missing_cols) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Document generation test error: " . $e->getMessage() . "\n";
}

// Test 3: Check attendance system and approval workflow
echo "\n3. TESTING ATTENDANCE SYSTEM & APPROVAL WORKFLOW\n";
echo "===============================================\n";
try {
    // Check users table structure
    $result = $link->query("DESCRIBE users");
    $user_columns = [];
    while ($row = $result->fetch_assoc()) {
        $user_columns[] = $row['Field'];
    }
    echo "✓ Users table columns: " . implode(', ', $user_columns) . "\n";
    
    // Check approval status distribution
    $stmt = $link->prepare("SELECT approval_status, COUNT(*) as count FROM users WHERE role = 'basic-cadet' GROUP BY approval_status");
    $stmt->execute();
    $approval_stats = $stmt->get_result();
    
    echo "✓ Approval status distribution for basic-cadets:\n";
    while ($row = $approval_stats->fetch_assoc()) {
        echo "   {$row['approval_status']}: {$row['count']} users\n";
    }
    
    // Test attendance query (should only show approved users)
    $attendance_query = "
        SELECT 
            u.id,
            u.username,
            u.approval_status,
            cp.first_name,
            cp.last_name,
            cp.platoon
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role = 'basic-cadet' 
        AND u.approval_status = 'approved'
        ORDER BY cp.platoon, cp.last_name
    ";
    
    $stmt = $link->prepare($attendance_query);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    
    echo "✓ Attendance system query (approved users only): {$attendance_result->num_rows} users\n";
    while ($row = $attendance_result->fetch_assoc()) {
        echo "   {$row['first_name']} {$row['last_name']} - Platoon: {$row['platoon']} - Status: {$row['approval_status']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Attendance system test error: " . $e->getMessage() . "\n";
}

// Test 4: Check QR generation functionality
echo "\n4. TESTING QR GENERATION\n";
echo "========================\n";
try {
    // Check if QR generation files exist
    $qr_files = ['generate_qr.php', 'batch_generate_qr.php'];
    foreach ($qr_files as $file) {
        if (file_exists($file)) {
            echo "✓ {$file} exists\n";
        } else {
            echo "❌ {$file} missing\n";
        }
    }
    
    // Check QR library
    if (file_exists('libs/phpqrcode/qrlib.php')) {
        echo "✓ QR library exists\n";
    } else {
        echo "❌ QR library missing\n";
    }
    
    // Check rifles without QR codes
    $stmt = $link->prepare("SELECT COUNT(*) as count FROM rifles WHERE qr_code_path IS NULL OR qr_code_path = ''");
    $stmt->execute();
    $result = $stmt->get_result();
    $qr_count = $result->fetch_assoc();
    echo "✓ Rifles without QR codes: {$qr_count['count']}\n";
    
} catch (Exception $e) {
    echo "❌ QR generation test error: " . $e->getMessage() . "\n";
}

// Test 5: Check user registration and default role
echo "\n5. TESTING USER REGISTRATION & DEFAULT ROLE\n";
echo "==========================================\n";
try {
    // Check users table default role
    $result = $link->query("SHOW CREATE TABLE users");
    $create_table = $result->fetch_assoc();
    echo "✓ Users table structure checked\n";
    
    if (strpos($create_table['Create Table'], "DEFAULT 'basic-cadet'") !== false) {
        echo "✓ Default role is set to 'basic-cadet'\n";
    } else {
        echo "⚠️ Default role may not be 'basic-cadet'\n";
    }
    
    // Check role distribution
    $stmt = $link->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY role");
    $stmt->execute();
    $roles = $stmt->get_result();
    
    echo "✓ Current role distribution:\n";
    while ($row = $roles->fetch_assoc()) {
        echo "   {$row['role']}: {$row['count']} users\n";
    }
    
} catch (Exception $e) {
    echo "❌ User registration test error: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG TEST COMPLETED ===\n";
echo "Please review the results above to identify specific issues.\n";
?>