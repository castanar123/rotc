<?php
// Simple test to verify AJAX endpoints work
require_once 'includes/db.php';
require_once 'includes/session.php';

echo "<h2>Testing Cadet Attendance AJAX Endpoints</h2>";

// Check session
echo "<p><strong>Session Status:</strong></p>";
echo "<ul>";
echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</li>";
echo "<li>Role: " . ($_SESSION['role'] ?? 'Not set') . "</li>";
echo "<li>Logged in: " . (isset($_SESSION['user_id']) ? 'Yes' : 'No') . "</li>";
echo "</ul>";

// Test if user is cadet
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['cadet', 'basic_cadet'])) {
    echo "<p style='color: red;'>❌ User is not logged in as cadet</p>";
    exit();
}

echo "<p style='color: green;'>✅ User is logged in as cadet</p>";

// Test database connection
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit();
}

// Test cadet profile lookup
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cadet = $stmt->fetch();
    
    if ($cadet) {
        echo "<p style='color: green;'>✅ Cadet profile found: " . $cadet['first_name'] . " " . $cadet['last_name'] . " (ID: " . $cadet['id'] . ")</p>";
        $cadet_id = $cadet['id'];
    } else {
        echo "<p style='color: red;'>❌ No cadet profile found for user ID: " . $_SESSION['user_id'] . "</p>";
        exit();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error fetching cadet profile: " . $e->getMessage() . "</p>";
    exit();
}

// Test attendance data
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE cadet_id = ?");
    $stmt->execute([$cadet_id]);
    $total = $stmt->fetchColumn();
    
    echo "<p style='color: green;'>✅ Total attendance records: " . $total . "</p>";
    
    if ($total > 0) {
        // Get sample records
        $stmt = $pdo->prepare("SELECT event_date, status, time_in FROM attendance WHERE cadet_id = ? ORDER BY event_date DESC LIMIT 3");
        $stmt->execute([$cadet_id]);
        $records = $stmt->fetchAll();
        
        echo "<p><strong>Sample attendance records:</strong></p>";
        echo "<ul>";
        foreach ($records as $record) {
            echo "<li>" . $record['event_date'] . " - " . $record['status'] . " (" . ($record['time_in'] ?? 'N/A') . ")</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error fetching attendance data: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Test AJAX Endpoints</h3>";
echo "<button onclick='testStatsEndpoint()'>Test Stats Endpoint</button>";
echo "<button onclick='testAttendanceEndpoint()'>Test Attendance Endpoint</button>";
echo "<div id='ajax-results'></div>";

echo "<script>
function testStatsEndpoint() {
    fetch('cadet_attendance_new.php?ajax=true&action=get_stats')
        .then(response => response.json())
        .then(data => {
            document.getElementById('ajax-results').innerHTML = '<h4>Stats Endpoint Result:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('ajax-results').innerHTML = '<h4>Stats Endpoint Error:</h4><pre>' + error.message + '</pre>';
        });
}

function testAttendanceEndpoint() {
    fetch('cadet_attendance_new.php?ajax=true&action=get_recent_attendance')
        .then(response => response.json())
        .then(data => {
            document.getElementById('ajax-results').innerHTML = '<h4>Attendance Endpoint Result:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('ajax-results').innerHTML = '<h4>Attendance Endpoint Error:</h4><pre>' + error.message + '</pre>';
        });
}
</script>";
?>