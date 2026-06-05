<?php
require_once 'includes/db.php';

echo "<h2>Checking Attendance Data in Database</h2>";

// Check attendance_logs table
echo "<h3>Attendance Logs Table:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance_logs");
    $count = $stmt->fetch()['count'];
    echo "<p>Total records in attendance_logs: $count</p>";
    
    if ($count > 0) {
        echo "<h4>Sample records:</h4>";
        $stmt = $pdo->query("SELECT * FROM attendance_logs LIMIT 5");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . json_encode($records, JSON_PRETTY_PRINT) . "</pre>";
        
        echo "<h4>Unique cadet_profile_ids:</h4>";
        $stmt = $pdo->query("SELECT DISTINCT cadet_profile_id, COUNT(*) as count FROM attendance_logs GROUP BY cadet_profile_id");
        $cadet_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . json_encode($cadet_ids, JSON_PRETTY_PRINT) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error with attendance_logs: " . $e->getMessage() . "</p>";
}

// Check attendance table
echo "<h3>Attendance Table:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance");
    $count = $stmt->fetch()['count'];
    echo "<p>Total records in attendance: $count</p>";
    
    if ($count > 0) {
        echo "<h4>Sample records:</h4>";
        $stmt = $pdo->query("SELECT * FROM attendance LIMIT 5");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . json_encode($records, JSON_PRETTY_PRINT) . "</pre>";
        
        echo "<h4>Unique cadet_ids:</h4>";
        $stmt = $pdo->query("SELECT DISTINCT cadet_id, COUNT(*) as count FROM attendance GROUP BY cadet_id");
        $cadet_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . json_encode($cadet_ids, JSON_PRETTY_PRINT) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error with attendance: " . $e->getMessage() . "</p>";
}

// Check cadet_profiles table
echo "<h3>Cadet Profiles Table:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cadet_profiles");
    $count = $stmt->fetch()['count'];
    echo "<p>Total records in cadet_profiles: $count</p>";
    
    if ($count > 0) {
        echo "<h4>Sample records:</h4>";
        $stmt = $pdo->query("SELECT id, user_id, student_id, first_name, last_name FROM cadet_profiles LIMIT 10");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . json_encode($records, JSON_PRETTY_PRINT) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error with cadet_profiles: " . $e->getMessage() . "</p>";
}

// Check for user_id 11 specifically
echo "<h3>Data for User ID 11:</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([11]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile) {
        echo "<p>Cadet profile found:</p>";
        echo "<pre>" . json_encode($profile, JSON_PRETTY_PRINT) . "</pre>";
        
        $cadet_id = $profile['id'];
        echo "<p>Checking attendance for cadet_id: $cadet_id</p>";
        
        // Check attendance_logs
        $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE cadet_profile_id = ?");
        $stmt->execute([$cadet_id]);
        $attendance_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Attendance logs count: " . count($attendance_logs) . "</p>";
        if (count($attendance_logs) > 0) {
            echo "<pre>" . json_encode($attendance_logs, JSON_PRETTY_PRINT) . "</pre>";
        }
        
        // Check attendance table
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE cadet_id = ?");
        $stmt->execute([$cadet_id]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Attendance table count: " . count($attendance) . "</p>";
        if (count($attendance) > 0) {
            echo "<pre>" . json_encode($attendance, JSON_PRETTY_PRINT) . "</pre>";
        }
    } else {
        echo "<p>No cadet profile found for user_id 11. Checking attendance with user_id as cadet_id:</p>";
        
        // Check attendance_logs with user_id as cadet_profile_id
        $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE cadet_profile_id = ?");
        $stmt->execute([11]);
        $attendance_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Attendance logs count (using user_id): " . count($attendance_logs) . "</p>";
        if (count($attendance_logs) > 0) {
            echo "<pre>" . json_encode($attendance_logs, JSON_PRETTY_PRINT) . "</pre>";
        }
        
        // Check attendance table with user_id as cadet_id
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE cadet_id = ?");
        $stmt->execute([11]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Attendance table count (using user_id): " . count($attendance) . "</p>";
        if (count($attendance) > 0) {
            echo "<pre>" . json_encode($attendance, JSON_PRETTY_PRINT) . "</pre>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking user_id 11: " . $e->getMessage() . "</p>";
}
?>