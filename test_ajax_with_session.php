<?php
require_once 'includes/db.php';
require_once 'includes/session.php';

// Simulate a logged-in cadet session for testing
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = 11; // Using user_id 11 from the logs
$_SESSION['role'] = 'cadet';
$_SESSION['username'] = 'test_cadet';

echo "<h2>Testing AJAX Endpoints with Session</h2>";

// Test get_stats function directly
echo "<h3>Testing getCadetAttendanceStats function:</h3>";
try {
    // Include the function from cadet_attendance_new.php
    function getCadetAttendanceStats($user_id) {
        global $pdo;
        
        // Get cadet_id from user_id
        $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $cadet = $stmt->fetch();
        
        if (!$cadet) {
            $cadet_id = $user_id;
        } else {
            $cadet_id = $cadet['id'];
        }
        
        // Check which attendance table exists
        $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
        $use_attendance_logs = $table_check->rowCount() > 0;
        
        if ($use_attendance_logs) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late
                FROM attendance_logs 
                WHERE cadet_profile_id = ?
            ");
            $stmt->execute([$cadet_id]);
        } else {
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
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
    }
    
    $stats = getCadetAttendanceStats($_SESSION['user_id']);
    echo "<pre>" . json_encode($stats, JSON_PRETTY_PRINT) . "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test AJAX endpoint simulation
echo "<h3>Testing AJAX endpoint simulation:</h3>";
$_GET['ajax'] = 'true';
$_GET['action'] = 'get_stats';

ob_start();
include 'cadet_attendance_new.php';
$ajax_output = ob_get_clean();

echo "<p>AJAX Output:</p>";
echo "<pre>" . htmlspecialchars($ajax_output) . "</pre>";
?>