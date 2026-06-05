<?php
// Test file to verify cadet attendance functionality with authentication
require_once 'includes/db.php';
require_once 'includes/session.php';

// Simulate being logged in as user_id 28 (from our previous tests)
$_SESSION['user_id'] = 28;
$_SESSION['role'] = 'cadet';

echo "<h2>Testing Cadet Attendance with Authentication</h2>";
echo "<p>Simulated login as user_id: 28</p>";

// Include the functions from cadet_attendance_new.php
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
    
    // Use attendance table (we know it has data)
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
    
    return $result ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
}

function getCadetRecentAttendance($user_id, $limit = 10) {
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
    
    $stmt = $pdo->prepare("
        SELECT 
            a.log_date,
            a.status,
            a.log_time,
            a.training_day
        FROM attendance a
        WHERE a.cadet_id = ?
        ORDER BY a.log_date DESC, a.created_at DESC
        LIMIT " . intval($limit) . "
    ");
    $stmt->execute([$cadet_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Test the functions
echo "<h3>Testing Statistics:</h3>";
$stats = getCadetAttendanceStats(28);
echo "<pre>" . json_encode($stats, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Testing Recent Attendance:</h3>";
$recent = getCadetRecentAttendance(28, 5);
echo "<pre>" . json_encode($recent, JSON_PRETTY_PRINT) . "</pre>";

// Test AJAX endpoints
echo "<h3>Testing AJAX Endpoints:</h3>";
echo "<button onclick='testAjax()'>Test AJAX Calls</button>";
echo "<div id='ajax-results'></div>";

echo "<script>
function testAjax() {
    // Test stats
    fetch('cadet_attendance_new.php?ajax=true&action=get_stats')
        .then(response => response.json())
        .then(data => {
            document.getElementById('ajax-results').innerHTML += '<h4>Stats AJAX Result:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('ajax-results').innerHTML += '<h4>Stats AJAX Error:</h4>' + error;
        });
    
    // Test recent attendance
    fetch('cadet_attendance_new.php?ajax=true&action=get_recent_attendance')
        .then(response => response.json())
        .then(data => {
            document.getElementById('ajax-results').innerHTML += '<h4>Recent Attendance AJAX Result:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('ajax-results').innerHTML += '<h4>Recent Attendance AJAX Error:</h4>' + error;
        });
}
</script>";

echo "<p><strong>Note:</strong> To test the actual cadet_attendance_new.php page, you need to:</p>";
echo "<ol>";
echo "<li>Log in to the system first</li>";
echo "<li>Navigate to the cadet attendance page</li>";
echo "<li>The page should then display the correct data</li>";
echo "</ol>";
?>