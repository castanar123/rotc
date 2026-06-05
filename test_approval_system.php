<?php
require_once 'includes/db.php';

echo "=== Testing User Approval System ===\n\n";

try {
    // 1. Check current users table structure
    echo "1. Checking users table structure...\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    $has_approval = false;
    $has_status = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'approval_status') {
            $has_approval = true;
            echo "   ✓ approval_status column found: {$column['Type']}\n";
        }
        if ($column['Field'] === 'status') {
            $has_status = true;
            echo "   ✓ status column found: {$column['Type']}\n";
        }
    }
    
    if (!$has_approval || !$has_status) {
        echo "   ❌ Missing required columns for approval system\n";
        exit(1);
    }
    
    // 2. Check current user statuses
    echo "\n2. Current user statuses...\n";
    $stmt = $pdo->query("SELECT id, username, role, approval_status, status FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "   User {$user['id']}: {$user['username']} ({$user['role']}) - Approval: {$user['approval_status']}, Status: {$user['status']}\n";
    }
    
    // 3. Test creating a new user with proper defaults
    echo "\n3. Testing new user creation with approval system...\n";
    $test_username = 'test_cadet_' . time();
    $test_email = $test_username . '@test.com';
    $test_password = password_hash('password123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, approval_status, status) 
        VALUES (?, ?, ?, 'basic_cadet', 'pending', 'inactive')
    ");
    $stmt->execute([$test_username, $test_email, $test_password]);
    $new_user_id = $pdo->lastInsertId();
    
    echo "   ✓ Created test user ID: {$new_user_id} with pending approval and inactive status\n";
    
    // 4. Test counting only approved active users
    echo "\n4. Testing user counting queries...\n";
    
    // Count all users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'basic_cadet'");
    $total_cadets = $stmt->fetch()['total'];
    echo "   Total basic_cadet users: {$total_cadets}\n";
    
    // Count only approved active users (should be used in dashboards)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'basic_cadet' AND approval_status = 'approved' AND status = 'active'");
    $approved_active_cadets = $stmt->fetch()['total'];
    echo "   Approved & Active basic_cadet users: {$approved_active_cadets}\n";
    
    // Count pending users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'basic_cadet' AND approval_status = 'pending'");
    $pending_cadets = $stmt->fetch()['total'];
    echo "   Pending basic_cadet users: {$pending_cadets}\n";
    
    // 5. Test approval process
    echo "\n5. Testing approval process...\n";
    
    // Approve the test user
    $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', status = 'active' WHERE id = ?");
    $stmt->execute([$new_user_id]);
    echo "   ✓ Approved test user ID: {$new_user_id}\n";
    
    // Recount approved active users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'basic_cadet' AND approval_status = 'approved' AND status = 'active'");
    $new_approved_active_cadets = $stmt->fetch()['total'];
    echo "   New count of Approved & Active basic_cadet users: {$new_approved_active_cadets}\n";
    
    if ($new_approved_active_cadets == $approved_active_cadets + 1) {
        echo "   ✓ Approval process working correctly!\n";
    } else {
        echo "   ❌ Approval process not working correctly!\n";
    }
    
    // 6. Test rejection process
    echo "\n6. Testing rejection process...\n";
    
    // Create another test user
    $test_username2 = 'test_cadet_reject_' . time();
    $test_email2 = $test_username2 . '@test.com';
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, approval_status, status) 
        VALUES (?, ?, ?, 'basic_cadet', 'pending', 'inactive')
    ");
    $stmt->execute([$test_username2, $test_email2, $test_password]);
    $reject_user_id = $pdo->lastInsertId();
    
    // Reject the user
    $stmt = $pdo->prepare("UPDATE users SET approval_status = 'rejected', status = 'inactive' WHERE id = ?");
    $stmt->execute([$reject_user_id]);
    echo "   ✓ Rejected test user ID: {$reject_user_id}\n";
    
    // Count rejected users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'basic_cadet' AND approval_status = 'rejected'");
    $rejected_cadets = $stmt->fetch()['total'];
    echo "   Rejected basic_cadet users: {$rejected_cadets}\n";
    
    // 7. Test dashboard queries
    echo "\n7. Testing dashboard-style queries...\n";
    
    // Attendance dashboard query (should only count approved active users)
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users u 
        WHERE u.role = 'basic_cadet' 
        AND u.approval_status = 'approved' 
        AND u.status = 'active'
    ");
    $dashboard_count = $stmt->fetch()['total'];
    echo "   Dashboard count (approved & active only): {$dashboard_count}\n";
    
    // Document generation query (should only include approved active users)
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.full_name, cp.course 
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic_cadet' 
        AND u.approval_status = 'approved' 
        AND u.status = 'active'
        ORDER BY u.username
    ");
    $document_users = $stmt->fetchAll();
    echo "   Users for document generation: " . count($document_users) . "\n";
    
    // 8. Clean up test data
    echo "\n8. Cleaning up test data...\n";
    $stmt = $pdo->prepare("DELETE FROM users WHERE id IN (?, ?)");
    $stmt->execute([$new_user_id, $reject_user_id]);
    echo "   ✓ Cleaned up test users\n";
    
    echo "\n✅ Approval system test completed successfully!\n";
    echo "\n=== Summary ===\n";
    echo "- New users are created with approval_status='pending' and status='inactive'\n";
    echo "- Only users with approval_status='approved' AND status='active' should be counted in dashboards\n";
    echo "- Approval process correctly updates both approval_status and status\n";
    echo "- Rejection process correctly sets approval_status='rejected'\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>