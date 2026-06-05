<?php
require_once 'includes/db.php';

echo "<h2>Database Structure Test</h2>";

// Check attendance table structure
echo "<h3>Attendance Table Structure:</h3>";
try {
    $stmt = $pdo->query('DESCRIBE attendance');
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while($row = $stmt->fetch()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check sample attendance data
echo "<h3>Sample Attendance Data for cadet_id 18:</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE cadet_id = ? LIMIT 5");
    $stmt->execute([18]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($records) {
        echo "<table border='1'><tr>";
        foreach (array_keys($records[0]) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr>";
        
        foreach ($records as $record) {
            echo "<tr>";
            foreach ($record as $value) {
                echo "<td>$value</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No records found for cadet_id 18";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Test AJAX endpoints
echo "<h3>Testing AJAX Endpoints:</h3>";
echo "<button onclick='testStats()'>Test Stats</button>";
echo "<button onclick='testRecent()'>Test Recent Attendance</button>";
echo "<div id='results'></div>";

echo "<script>
function testStats() {
    fetch('cadet_attendance_new.php?ajax=true&action=get_stats')
        .then(response => response.json())
        .then(data => {
            document.getElementById('results').innerHTML = '<h4>Stats Result:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('results').innerHTML = '<h4>Stats Error:</h4>' + error;
        });
}

function testRecent() {
    fetch('cadet_attendance_new.php?ajax=true&action=get_recent_attendance')
        .then(response => response.json())
        .then(data => {
            document.getElementById('results').innerHTML = '<h4>Recent Attendance Result:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('results').innerHTML = '<h4>Recent Attendance Error:</h4>' + error;
        });
}
</script>";
?>