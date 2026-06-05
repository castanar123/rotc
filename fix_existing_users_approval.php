<?php
require_once 'includes/db.php';

echo "=== Fixing Existing Users Approval Status ===\n\n";

try {
    // 1. Check current status of all users
    echo "1. Current user statuses...\n";
    $stmt = $pdo->query("SELECT id, username, role, approval_status, status FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    $pending_count = 0;
    $approved_count = 0;
    
    foreach ($users as $user) {
        echo "   User {$user['id']}: {$user['username']} ({$user['role']}) - Approval: {$user['approval_status']}, Status: {$user['status']}\n";
        if ($user['approval_status'] === 'pending') {
            $pending_count++;
        } elseif ($user['approval_status'] === 'approved') {
            $approved_count++;
        }
    }
    
    echo "\n   Summary: {$pending_count} pending, {$approved_count} approved\n";
    
    // 2. Auto-approve admin and instructor accounts
    echo "\n2. Auto-approving admin and instructor accounts...\n";
    $stmt = $pdo->prepare("
        UPDATE users 
        SET approval_status = 'approved', status = 'active' 
        WHERE role IN ('admin', 'instructor', 'commandant') 
        AND approval_status = 'pending'
    ");
    $affected = $stmt->execute();
    $admin_approved = $stmt->rowCount();
    echo "   ✓ Auto-approved {$admin_approved} admin/instructor accounts\n";
    
    // 3. Approve some basic cadets for testing (first 5)
    echo "\n3. Approving first 5 basic-cadet users for testing...\n";
    $stmt = $pdo->query("
        SELECT id, username 
        FROM users 
        WHERE role IN ('basic_cadet', 'cadet') 
        AND approval_status = 'pending' 
        ORDER BY id 
        LIMIT 5
    ");
    $cadets_to_approve = $stmt->fetchAll();
    
    $cadet_approved = 0;
    foreach ($cadets_to_approve as $cadet) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET approval_status = 'approved', status = 'active' 
            WHERE id = ?
        ");
        $stmt->execute([$cadet['id']]);
        echo "   ✓ Approved cadet: {$cadet['username']} (ID: {$cadet['id']})\n";
        $cadet_approved++;
    }
    
    echo "   Total cadets approved: {$cadet_approved}\n";
    
    // 4. Show updated status
    echo "\n4. Updated user statuses...\n";
    $stmt = $pdo->query("
        SELECT 
            approval_status,
            status,
            role,
            COUNT(*) as count
        FROM users 
        GROUP BY approval_status, status, role
        ORDER BY role, approval_status
    ");
    $status_summary = $stmt->fetchAll();
    
    foreach ($status_summary as $summary) {
        echo "   {$summary['role']}: {$summary['approval_status']}/{$summary['status']} = {$summary['count']} users\n";
    }
    
    // 5. Test dashboard counting
    echo "\n5. Testing dashboard counts...\n";
    
    // Count for attendance dashboard
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE role IN ('basic_cadet', 'cadet') 
        AND approval_status = 'approved' 
        AND status = 'active'
    ");
    $dashboard_cadets = $stmt->fetch()['total'];
    echo "   Cadets for attendance dashboard: {$dashboard_cadets}\n";
    
    // Count for document generation
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users u
        WHERE u.role IN ('basic_cadet', 'cadet') 
        AND u.approval_status = 'approved' 
        AND u.status = 'active'
    ");
    $document_cadets = $stmt->fetch()['total'];
    echo "   Cadets for document generation: {$document_cadets}\n";
    
    // Count by course (for document generation)
    $stmt = $pdo->query("
        SELECT 
            COALESCE(u.course, cp.course, 'Unknown') as course,
            COUNT(*) as count
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.role IN ('basic_cadet', 'cadet') 
        AND u.approval_status = 'approved' 
        AND u.status = 'active'
        GROUP BY COALESCE(u.course, cp.course)
        ORDER BY count DESC
    ");
    $course_counts = $stmt->fetchAll();
    
    echo "   Cadets by course:\n";
    foreach ($course_counts as $course) {
        echo "     - {$course['course']}: {$course['count']}\n";
    }
    
    echo "\n✅ User approval status fixed successfully!\n";
    echo "\n=== Important Notes ===\n";
    echo "- All admin/instructor accounts are now approved and active\n";
    echo "- First 5 cadet accounts are approved for testing\n";
    echo "- Remaining cadet accounts are still pending approval\n";
    echo "- Dashboard queries should now only count approved & active users\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>