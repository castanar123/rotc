<?php
require_once 'includes/db.php';

// Check what data exists in the database
echo "<h2>Database Data Check</h2>";

// Check users table
echo "<h3>Users Table:</h3>";
try {
    $stmt = $pdo->query("SELECT id, username, role FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr><td>{$user['id']}</td><td>{$user['username']}</td><td>{$user['role']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check cadet_profiles table
echo "<h3>Cadet Profiles Table:</h3>";
try {
    $stmt = $pdo->query("SELECT id, user_id, first_name, last_name FROM cadet_profiles LIMIT 5");
    $profiles = $stmt->fetchAll();
    echo "<table border='1'><tr><th>ID</th><th>User ID</th><th>First Name</th><th>Last Name</th></tr>";
    foreach ($profiles as $profile) {
        echo "<tr><td>{$profile['id']}</td><td>{$profile['user_id']}</td><td>{$profile['first_name']}</td><td>{$profile['last_name']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check attendance table
echo "<h3>Attendance Table:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attendance");
    $count = $stmt->fetch()['total'];
    echo "<p>Total attendance records: $count</p>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT * FROM attendance LIMIT 5");
        $attendance = $stmt->fetchAll();
        echo "<table border='1'><tr>";
        foreach (array_keys($attendance[0]) as $column) {
            echo "<th>$column</th>";
        }
        echo "</tr>";
        foreach ($attendance as $record) {
            echo "<tr>";
            foreach ($record as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check attendance_logs table
echo "<h3>Attendance Logs Table:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM attendance_logs");
    $count = $stmt->fetch()['total'];
    echo "<p>Total attendance_logs records: $count</p>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT * FROM attendance_logs LIMIT 5");
        $logs = $stmt->fetchAll();
        echo "<table border='1'><tr>";
        foreach (array_keys($logs[0]) as $column) {
            echo "<th>$column</th>";
        }
        echo "</tr>";
        foreach ($logs as $record) {
            echo "<tr>";
            foreach ($record as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check grades table
echo "<h3>Grades Table:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM grades");
    $count = $stmt->fetch()['total'];
    echo "<p>Total grades records: $count</p>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT * FROM grades LIMIT 5");
        $grades = $stmt->fetchAll();
        echo "<table border='1'><tr>";
        foreach (array_keys($grades[0]) as $column) {
            echo "<th>$column</th>";
        }
        echo "</tr>";
        foreach ($grades as $record) {
            echo "<tr>";
            foreach ($record as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check announcements table
echo "<h3>Announcements Table:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM announcements");
    $count = $stmt->fetch()['total'];
    echo "<p>Total announcements records: $count</p>";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT * FROM announcements LIMIT 3");
        $announcements = $stmt->fetchAll();
        echo "<table border='1'><tr>";
        foreach (array_keys($announcements[0]) as $column) {
            echo "<th>$column</th>";
        }
        echo "</tr>";
        foreach ($announcements as $record) {
            echo "<tr>";
            foreach ($record as $value) {
                echo "<td>" . htmlspecialchars(substr($value, 0, 50)) . (strlen($value) > 50 ? '...' : '') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>