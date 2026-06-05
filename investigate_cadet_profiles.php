<?php
try {
    $pdo = new PDO('mysql:host=localhost:3306;dbname=rotc_db', 'root', ''); // Changed back to port 3306
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Investigating Cadet Profiles and User Relationships</h2>";
    
    // Check cadet_profiles for ID 11 or user_id 11
    echo "<h3>1. Cadet Profiles for ID 11 or User ID 11:</h3>";
    $stmt = $pdo->prepare('SELECT id, user_id, first_name, last_name, student_id FROM cadet_profiles WHERE id = 11 OR user_id = 11');
    $stmt->execute();
    $profiles = $stmt->fetchAll();
    
    if ($profiles) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>User ID</th><th>First Name</th><th>Last Name</th><th>Student ID</th></tr>";
        foreach ($profiles as $profile) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($profile['id']) . "</td>";
            echo "<td>" . htmlspecialchars($profile['user_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($profile['first_name']) . "</td>";
            echo "<td>" . htmlspecialchars($profile['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($profile['student_id']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No cadet profiles found for ID 11 or user_id 11</p>";
    }
    
    // Check all cadet_profiles
    echo "<h3>2. All Cadet Profiles:</h3>";
    $stmt = $pdo->query('SELECT id, user_id, first_name, last_name, student_id FROM cadet_profiles ORDER BY id');
    $allProfiles = $stmt->fetchAll();
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>User ID</th><th>First Name</th><th>Last Name</th><th>Student ID</th></tr>";
    foreach ($allProfiles as $profile) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($profile['id']) . "</td>";
        echo "<td>" . htmlspecialchars($profile['user_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($profile['first_name']) . "</td>";
        echo "<td>" . htmlspecialchars($profile['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($profile['student_id']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check attendance records for cadet_id 11
    echo "<h3>3. Attendance Records for cadet_id 11:</h3>";
    $stmt = $pdo->prepare('SELECT id, cadet_id, student_id, log_date, log_time, status FROM attendance WHERE cadet_id = 11 ORDER BY log_date DESC');
    $stmt->execute();
    $attendance = $stmt->fetchAll();
    
    if ($attendance) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Cadet ID</th><th>Student ID</th><th>Log Date</th><th>Log Time</th><th>Status</th></tr>";
        foreach ($attendance as $record) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($record['id']) . "</td>";
            echo "<td>" . htmlspecialchars($record['cadet_id']) . "</td>";
            echo "<td>" . htmlspecialchars($record['student_id']) . "</td>";
            echo "<td>" . htmlspecialchars($record['log_date']) . "</td>";
            echo "<td>" . htmlspecialchars($record['log_time']) . "</td>";
            echo "<td>" . htmlspecialchars($record['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No attendance records found for cadet_id 11</p>";
    }
    
    // Check users table for ID 11
    echo "<h3>4. Users Table for ID 11:</h3>";
    $stmt = $pdo->prepare('SELECT id, username, email, role, status FROM users WHERE id = 11');
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td>" . htmlspecialchars($user['status']) . "</td>";
        echo "</tr>";
        echo "</table>";
    } else {
        echo "<p>No user found with ID 11</p>";
    }
    
    // Summary and recommendations
    echo "<h3>5. Analysis Summary:</h3>";
    echo "<ul>";
    echo "<li>Total cadet_profiles records: " . count($allProfiles) . "</li>";
    echo "<li>Attendance records for cadet_id 11: " . count($attendance) . "</li>";
    echo "<li>User record for ID 11: " . ($user ? 'Found' : 'Not found') . "</li>";
    
    // Check if cadet_id 11 exists in cadet_profiles
    $cadetProfile11 = null;
    foreach ($allProfiles as $profile) {
        if ($profile['id'] == 11) {
            $cadetProfile11 = $profile;
            break;
        }
    }
    
    if ($cadetProfile11) {
        echo "<li>Cadet profile ID 11 exists with user_id: " . ($cadetProfile11['user_id'] ?? 'NULL') . "</li>";
    } else {
        echo "<li>Cadet profile ID 11 does NOT exist</li>";
    }
    
    echo "</ul>";
    
    echo "<h3>6. Problem Diagnosis:</h3>";
    if (count($attendance) > 0 && !$cadetProfile11) {
        echo "<p style='color: red;'><strong>CRITICAL ISSUE:</strong> Attendance records exist for cadet_id 11, but no cadet_profiles record exists with ID 11. This is a data integrity problem.</p>";
    } elseif ($cadetProfile11 && !$cadetProfile11['user_id']) {
        echo "<p style='color: orange;'><strong>WARNING:</strong> Cadet profile ID 11 exists but has NULL user_id. This breaks the relationship with users table.</p>";
    } elseif ($cadetProfile11 && $cadetProfile11['user_id'] && !$user) {
        echo "<p style='color: orange;'><strong>WARNING:</strong> Cadet profile ID 11 has user_id " . $cadetProfile11['user_id'] . " but no corresponding user record exists.</p>";
    } else {
        echo "<p style='color: green;'>Data relationships appear to be intact.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>