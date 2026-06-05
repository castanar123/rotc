<?php
// Test AJAX functionality directly without HTTP requests
require_once 'includes/db.php';
session_start();
$_SESSION['user_id'] = 28; // Simulate logged in user

// Simulate AJAX parameters
$_GET['ajax'] = 'true';
$_GET['action'] = 'get_stats';

echo "<h3>Testing get_stats action directly:</h3>";

// Include the functions from cadet_attendance_new.php
function getCadetAttendanceStats($user_id) {
    global $pdo;
    error_log("getCadetAttendanceStats called for user_id: $user_id");
    
    // Get cadet_id from user_id
    error_log("Querying cadet_profiles for user_id: $user_id");
    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cadet = $stmt->fetch();
    error_log("Cadet profile result: " . json_encode($cadet));
    
    if (!$cadet) {
        // Fallback to user_id as cadet_id
        $cadet_id = $user_id;
        error_log("No cadet profile found, using user_id as cadet_id: $cadet_id");
    } else {
        $cadet_id = $cadet['id'];
        error_log("Found cadet profile, using cadet_id: $cadet_id");
    }
    
    // Check which attendance table exists and has data
    $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
    $attendance_logs_exists = $table_check->rowCount() > 0;
    
    $use_attendance_logs = false;
    $result = null;
    
    // If attendance_logs exists, check if it has data for this cadet
    if ($attendance_logs_exists) {
        error_log("Checking attendance_logs for data for cadet_id: $cadet_id");
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE cadet_profile_id = ?");
        $count_stmt->execute([$cadet_id]);
        $count_result = $count_stmt->fetch();
        
        if ($count_result['count'] > 0) {
            $use_attendance_logs = true;
            error_log("Found {$count_result['count']} records in attendance_logs, using attendance_logs table");
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present,
                    COUNT(CASE WHEN status IN ('Absent', 'absent') THEN 1 END) as absent,
                    COUNT(CASE WHEN status IN ('Late', 'late') THEN 1 END) as late
                FROM attendance_logs 
                WHERE cadet_profile_id = ?
            ");
            $stmt->execute([$cadet_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Attendance_logs result: " . json_encode($result));
        } else {
            error_log("No records found in attendance_logs for cadet_id: $cadet_id, falling back to attendance table");
        }
    }
    
    // If attendance_logs doesn't exist or has no data, use attendance table
    if (!$use_attendance_logs) {
        error_log("Querying attendance table for cadet_id: $cadet_id");
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present,
                COUNT(CASE WHEN status IN ('Absent', 'absent') THEN 1 END) as absent,
                COUNT(CASE WHEN status IN ('Late', 'late') THEN 1 END) as late
            FROM attendance 
            WHERE cadet_id = ?
        ");
        $stmt->execute([$cadet_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Attendance table result: " . json_encode($result));
    }
    
    error_log("getCadetAttendanceStats returning: " . json_encode($result ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0]));
    return $result ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
}

// Test the function directly
$stats = getCadetAttendanceStats($_SESSION['user_id']);
echo "<pre>" . print_r($stats, true) . "</pre>";

// Test JSON output
echo "<h3>JSON Output:</h3>";
echo "<pre>" . json_encode(['success' => true, 'data' => $stats]) . "</pre>";

echo "<h3>Testing page access without HTTP request:</h3>";
echo "<p>Session user_id: " . $_SESSION['user_id'] . "</p>";
echo "<p>AJAX parameter: " . $_GET['ajax'] . "</p>";
echo "<p>Action parameter: " . $_GET['action'] . "</p>";
?>