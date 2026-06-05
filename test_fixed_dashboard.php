<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Set proper session for testing
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 7;
$_SESSION['cadet_profile_id'] = 4;
$_SESSION['role'] = 'cadet';
$_SESSION['username'] = 'test_cadet';

$output = "Testing Fixed Dashboard Variables\n";
$output .= "================================\n";

// Copy the exact logic from cadet_dashboard.php to test variables
$cadet_profile_id = null;
if (isset($_SESSION['cadet_profile_id'])) {
    $cadet_profile_id = $_SESSION['cadet_profile_id'];
} else {
    // Fallback: get cadet_profile_id from cadet_profiles table using user_id
    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    if ($result) {
        $cadet_profile_id = $result['id'];
        $_SESSION['cadet_profile_id'] = $cadet_profile_id; // Store for future use
    }
}

$output .= "Cadet Profile ID: " . var_export($cadet_profile_id, true) . "\n\n";

// Get dashboard statistics
try {
    // My attendance count (using cadet_profile_id with robust table checking)
    if ($cadet_profile_id) {
        $output .= "Fetching attendance stats for cadet_profile_id: $cadet_profile_id\n";
        
        // Check if attendance_logs table exists and has data
        $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
        $use_attendance_logs = $table_check->rowCount() > 0;
        
        if ($use_attendance_logs) {
            // Check if there's data in attendance_logs for this cadet
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE cadet_profile_id = ?");
            $stmt->execute([$cadet_profile_id]);
            $logs_count = $stmt->fetch()['count'];
            
            if ($logs_count > 0) {
                $output .= "Using attendance_logs table with $logs_count records\n";
                // Use attendance_logs table structure
                $my_attendance = $logs_count;
                
                // This month's attendance
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as present 
                    FROM attendance_logs 
                    WHERE cadet_profile_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                ");
                $stmt->execute([$cadet_profile_id]);
                $month_attendance = $stmt->fetch()['present'];
            } else {
                $use_attendance_logs = false; // Fall back to attendance table
            }
        }
        
        if (!$use_attendance_logs) {
            $output .= "Using attendance table as fallback\n";
            // Use attendance table structure
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE cadet_id = ?");
            $stmt->execute([$cadet_profile_id]);
            $my_attendance = $stmt->fetch()['total'];
            $output .= "Found $my_attendance attendance records in attendance table\n";
            
            // This month's attendance
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as present 
                FROM attendance 
                WHERE cadet_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
            ");
            $stmt->execute([$cadet_profile_id]);
            $month_attendance = $stmt->fetch()['present'];
        }
        
        // Get average grade
        $stmt = $pdo->prepare("
            SELECT AVG(
                CASE 
                    WHEN total_grade IS NOT NULL AND total_grade > 0 THEN total_grade
                    WHEN drill_grade IS NOT NULL AND conduct_grade IS NOT NULL AND academics_grade IS NOT NULL 
                    THEN (drill_grade + conduct_grade + academics_grade) / 3
                    ELSE NULL
                END
            ) as avg_grade 
            FROM grades 
            WHERE cadet_id = ?
        ");
        $stmt->execute([$cadet_profile_id]);
        $avg_grade_result = $stmt->fetch()['avg_grade'];
        $avg_grade = $avg_grade_result ? round($avg_grade_result, 1) : 0;
    } else {
        $my_attendance = $month_attendance = 0;
        $avg_grade = 0;
    }
    
    // Get upcoming events from announcements (FIXED QUERY)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM announcements 
        WHERE expires_at > NOW()
    ");
    $stmt->execute();
    $upcoming_events = $stmt->fetch()['count'];
    
    $output .= "\nQUERY SUCCESS! No SQL errors.\n";
    
} catch (PDOException $e) {
    $output .= "Dashboard query error: " . $e->getMessage() . "\n";
    $my_attendance = $month_attendance = $upcoming_events = 0;
    $avg_grade = 0;
}

$output .= "\nFinal Variable Values:\n";
$output .= "======================\n";
$output .= "my_attendance: " . var_export($my_attendance, true) . "\n";
$output .= "month_attendance: " . var_export($month_attendance, true) . "\n";
$output .= "avg_grade: " . var_export($avg_grade, true) . "\n";
$output .= "upcoming_events: " . var_export($upcoming_events, true) . "\n";

$output .= "\nHTML Output Test:\n";
$output .= "=================\n";
$output .= "<div class='stat-value'>" . $my_attendance . "</div>\n";
$output .= "<div class='stat-value'>" . $month_attendance . "</div>\n";
$output .= "<div class='stat-value'>" . $avg_grade . "%</div>\n";
$output .= "<div class='stat-value'>" . $upcoming_events . "</div>\n";

// Save to file
file_put_contents('fixed_dashboard_test_results.txt', $output);
echo "Test completed. Results saved to fixed_dashboard_test_results.txt";
?>