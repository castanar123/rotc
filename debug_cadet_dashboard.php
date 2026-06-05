<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

echo "<h2>Cadet Dashboard Debug Script</h2>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba; } .error { border-left-color: #dc3545; } .success { border-left-color: #28a745; }</style>";

// Check session
echo "<div class='debug'>";
echo "<h3>Session Information:</h3>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "Username: " . ($_SESSION['username'] ?? 'Not set') . "<br>";
echo "Full Name: " . ($_SESSION['full_name'] ?? 'Not set') . "<br>";
echo "</div>";

if (!isset($_SESSION['user_id'])) {
    echo "<div class='debug error'><strong>ERROR:</strong> No user session found. Please login first.</div>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Check cadet profile
echo "<div class='debug'>";
echo "<h3>Cadet Profile Check:</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cadet_profile = $stmt->fetch();
    
    if ($cadet_profile) {
        echo "<div class='success'>Cadet profile found!</div>";
        echo "Profile ID: " . $cadet_profile['id'] . "<br>";
        echo "Student ID: " . ($cadet_profile['student_id'] ?? 'Not set') . "<br>";
        echo "First Name: " . ($cadet_profile['first_name'] ?? 'Not set') . "<br>";
        echo "Last Name: " . ($cadet_profile['last_name'] ?? 'Not set') . "<br>";
        $cadet_profile_id = $cadet_profile['id'];
    } else {
        echo "<div class='error'>No cadet profile found for user ID: $user_id</div>";
        $cadet_profile_id = null;
    }
} catch (PDOException $e) {
    echo "<div class='error'>Profile query error: " . $e->getMessage() . "</div>";
    $cadet_profile_id = null;
}
echo "</div>";

// Check attendance tables
echo "<div class='debug'>";
echo "<h3>Attendance Tables Check:</h3>";
try {
    // Check if attendance_logs table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
    $use_attendance_logs = $table_check->rowCount() > 0;
    
    echo "attendance_logs table exists: " . ($use_attendance_logs ? 'YES' : 'NO') . "<br>";
    
    // Check if attendance table exists
    $table_check2 = $pdo->query("SHOW TABLES LIKE 'attendance'");
    $use_attendance = $table_check2->rowCount() > 0;
    
    echo "attendance table exists: " . ($use_attendance ? 'YES' : 'NO') . "<br>";
    
    if ($cadet_profile_id) {
        if ($use_attendance_logs) {
            // Test attendance_logs queries
            echo "<h4>Testing attendance_logs queries:</h4>";
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance_logs WHERE cadet_profile_id = ?");
            $stmt->execute([$cadet_profile_id]);
            $total_attendance = $stmt->fetch()['total'];
            echo "Total attendance from attendance_logs: $total_attendance<br>";
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as present FROM attendance_logs WHERE cadet_profile_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
            $stmt->execute([$cadet_profile_id]);
            $month_attendance = $stmt->fetch()['present'];
            echo "This month attendance from attendance_logs: $month_attendance<br>";
            
            // Show recent records
            $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE cadet_profile_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$cadet_profile_id]);
            $recent = $stmt->fetchAll();
            echo "Recent attendance_logs records: " . count($recent) . "<br>";
            foreach ($recent as $record) {
                echo "- Event: " . ($record['event_name'] ?? 'N/A') . ", Date: " . $record['created_at'] . "<br>";
            }
        }
        
        if ($use_attendance) {
            // Test attendance queries
            echo "<h4>Testing attendance queries:</h4>";
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE cadet_id = ?");
            $stmt->execute([$cadet_profile_id]);
            $total_attendance = $stmt->fetch()['total'];
            echo "Total attendance from attendance: $total_attendance<br>";
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as present FROM attendance WHERE cadet_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
            $stmt->execute([$cadet_profile_id]);
            $month_attendance = $stmt->fetch()['present'];
            echo "This month attendance from attendance: $month_attendance<br>";
            
            // Show recent records
            $stmt = $pdo->prepare("SELECT * FROM attendance WHERE cadet_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$cadet_profile_id]);
            $recent = $stmt->fetchAll();
            echo "Recent attendance records: " . count($recent) . "<br>";
            foreach ($recent as $record) {
                echo "- Training: " . ($record['training_day'] ?? 'N/A') . ", Date: " . $record['created_at'] . "<br>";
            }
        }
    }
} catch (PDOException $e) {
    echo "<div class='error'>Attendance query error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Check grades table
echo "<div class='debug'>";
echo "<h3>Grades Table Check:</h3>";
try {
    if ($cadet_profile_id) {
        // Check grades table structure
        $grade_check = $pdo->query("SHOW COLUMNS FROM grades LIKE 'grade'");
        $grade_info = $grade_check->fetch();
        
        if ($grade_info) {
            echo "Grade column type: " . $grade_info['Type'] . "<br>";
            
            // Test grades query
            $stmt = $pdo->prepare("SELECT * FROM grades WHERE cadet_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$cadet_profile_id]);
            $grades = $stmt->fetchAll();
            echo "Recent grades records: " . count($grades) . "<br>";
            
            foreach ($grades as $grade) {
                echo "- Grade: " . ($grade['grade'] ?? 'N/A') . ", Subject: " . ($grade['subject'] ?? 'N/A') . ", Date: " . ($grade['created_at'] ?? 'N/A') . "<br>";
            }
            
            // Test average calculation
            if (strpos($grade_info['Type'], 'decimal') !== false || strpos($grade_info['Type'], 'float') !== false || strpos($grade_info['Type'], 'int') !== false) {
                $stmt = $pdo->prepare("SELECT AVG(CAST(grade AS DECIMAL(5,2))) as avg_grade FROM grades WHERE cadet_id = ? AND grade IS NOT NULL AND grade != ''");
                $stmt->execute([$cadet_profile_id]);
                $avg_grade = $stmt->fetch()['avg_grade'];
                echo "Average grade (numeric): " . ($avg_grade ? round($avg_grade, 1) : 0) . "<br>";
            } else {
                $stmt = $pdo->prepare("SELECT AVG(CASE WHEN UPPER(grade) = 'A' THEN 95 WHEN UPPER(grade) = 'B' THEN 85 WHEN UPPER(grade) = 'C' THEN 75 WHEN UPPER(grade) = 'D' THEN 65 WHEN UPPER(grade) = 'F' THEN 50 WHEN grade REGEXP '^[0-9]+$' THEN CAST(grade AS DECIMAL(5,2)) ELSE NULL END) as avg_grade FROM grades WHERE cadet_id = ? AND grade IS NOT NULL AND grade != ''");
                $stmt->execute([$cadet_profile_id]);
                $avg_grade = $stmt->fetch()['avg_grade'];
                echo "Average grade (text converted): " . ($avg_grade ? round($avg_grade, 1) : 0) . "<br>";
            }
        } else {
            echo "<div class='error'>No grade column found in grades table</div>";
        }
    }
} catch (PDOException $e) {
    echo "<div class='error'>Grades query error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Check announcements
echo "<div class='debug'>";
echo "<h3>Announcements Check:</h3>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM announcements WHERE status = 'active' AND created_at >= CURDATE()");
    $stmt->execute();
    $upcoming_events = $stmt->fetch()['count'];
    echo "Upcoming events count: $upcoming_events<br>";
    
    $stmt = $pdo->prepare("SELECT title, created_at FROM announcements WHERE status = 'active' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    echo "Recent announcements: " . count($announcements) . "<br>";
    
    foreach ($announcements as $announcement) {
        echo "- " . $announcement['title'] . " (" . $announcement['created_at'] . ")<br>";
    }
} catch (PDOException $e) {
    echo "<div class='error'>Announcements query error: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='debug success'><h3>Debug Complete</h3>If all queries show 0 results, the issue is likely that there's no data in the database for this cadet profile.</div>";
?>