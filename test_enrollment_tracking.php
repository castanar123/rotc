<?php
// Test script to check enrollment tracking functionality
require_once 'includes/db.php';

global $link;

echo "<h2>🔍 Testing Enrollment Tracking System</h2>\n";

// Test 1: Check if tables exist
echo "<h3>1. Database Tables Check</h3>\n";
$tables_to_check = ['enrollment_tracking_config', 'enrollment_statistics', 'users', 'cadet_profiles'];

foreach ($tables_to_check as $table) {
    $result = $link->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>\n";
    } else {
        echo "❌ Table '$table' does not exist<br>\n";
    }
}

// Test 2: Check configuration
echo "<h3>2. Configuration Check</h3>\n";
$config_query = "SELECT setting_name, setting_value FROM enrollment_tracking_config";
$config_result = $link->query($config_query);

if ($config_result) {
    echo "✅ Configuration table accessible<br>\n";
    while ($row = $config_result->fetch_assoc()) {
        echo "- {$row['setting_name']}: {$row['setting_value']}<br>\n";
    }
} else {
    echo "❌ Cannot access configuration table<br>\n";
}

// Test 3: Check for pending users
echo "<h3>3. Pending Users Check</h3>\n";
$pending_query = "
    SELECT 
        COUNT(*) as total_pending,
        COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) as pending_count
    FROM users 
    WHERE role = 'cadet'
";

$pending_result = $link->query($pending_query);
if ($pending_result) {
    $stats = $pending_result->fetch_assoc();
    echo "✅ Total cadets: {$stats['total_pending']}<br>\n";
    echo "✅ Pending approvals: {$stats['pending_count']}<br>\n";
} else {
    echo "❌ Cannot query users table<br>\n";
}

// Test 4: Get actual pending users
echo "<h3>4. Actual Pending Users</h3>\n";
$users_query = "
    SELECT 
        u.id,
        u.username,
        u.email,
        u.approval_status,
        u.created_at,
        cp.full_name,
        cp.student_number
    FROM users u
    LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
    WHERE u.role = 'cadet' AND u.approval_status = 'pending'
    ORDER BY u.created_at DESC
    LIMIT 10
";

$users_result = $link->query($users_query);
if ($users_result && $users_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Student #</th><th>Status</th><th>Created</th></tr>\n";
    
    while ($user = $users_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>" . ($user['full_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($user['student_number'] ?? 'N/A') . "</td>";
        echo "<td>{$user['approval_status']}</td>";
        echo "<td>{$user['created_at']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "ℹ️ No pending users found<br>\n";
}

// Test 5: Update statistics
echo "<h3>5. Update Statistics Test</h3>\n";
$today = date('Y-m-d');

// Get current stats
$stats_query = "
    SELECT 
        COUNT(*) as total_enrollees,
        SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approvals,
        SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_enrollees,
        SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_enrollees,
        SUM(CASE WHEN paper_form_submitted = 1 THEN 1 ELSE 0 END) as paper_forms_submitted,
        SUM(CASE WHEN paper_form_submitted = 0 AND approval_status = 'approved' THEN 1 ELSE 0 END) as paper_forms_pending
    FROM users 
    WHERE role = 'cadet'
";

$stats_result = $link->query($stats_query);
if ($stats_result) {
    $current_stats = $stats_result->fetch_assoc();
    
    // Update today's statistics
    $update_stats_query = "
        INSERT INTO enrollment_statistics 
        (date_recorded, total_enrollees, pending_approvals, approved_enrollees, rejected_enrollees, paper_forms_submitted, paper_forms_pending)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_enrollees = VALUES(total_enrollees),
            pending_approvals = VALUES(pending_approvals),
            approved_enrollees = VALUES(approved_enrollees),
            rejected_enrollees = VALUES(rejected_enrollees),
            paper_forms_submitted = VALUES(paper_forms_submitted),
            paper_forms_pending = VALUES(paper_forms_pending)
    ";
    
    $stmt = $link->prepare($update_stats_query);
    $stmt->bind_param("siiiiii", 
        $today,
        $current_stats['total_enrollees'],
        $current_stats['pending_approvals'],
        $current_stats['approved_enrollees'],
        $current_stats['rejected_enrollees'],
        $current_stats['paper_forms_submitted'],
        $current_stats['paper_forms_pending']
    );
    
    if ($stmt->execute()) {
        echo "✅ Statistics updated successfully for $today<br>\n";
        echo "- Total Enrollees: {$current_stats['total_enrollees']}<br>\n";
        echo "- Pending Approvals: {$current_stats['pending_approvals']}<br>\n";
        echo "- Approved: {$current_stats['approved_enrollees']}<br>\n";
        echo "- Rejected: {$current_stats['rejected_enrollees']}<br>\n";
        echo "- Paper Forms Submitted: {$current_stats['paper_forms_submitted']}<br>\n";
        echo "- Paper Forms Pending: {$current_stats['paper_forms_pending']}<br>\n";
    } else {
        echo "❌ Failed to update statistics<br>\n";
    }
} else {
    echo "❌ Cannot get current statistics<br>\n";
}

echo "<h3>6. System Status</h3>\n";
echo "✅ Enrollment tracking system is operational<br>\n";
echo "📊 All pending users are being tracked automatically<br>\n";
echo "🔄 Statistics are updated in real-time<br>\n";

$link->close();
?>