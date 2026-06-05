<?php
require_once 'includes/db.php';

echo "<h2>Testing Sample Assignment Data</h2>";

try {
    // First, check if we have any cadet profiles
    $stmt = $pdo->query("SELECT id, user_id, student_id, first_name, last_name, platoon FROM cadet_profiles LIMIT 5");
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Cadet Profiles:</h3>";
    if (count($cadets) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Student ID</th><th>Name</th><th>Platoon</th></tr>";
        foreach ($cadets as $cadet) {
            echo "<tr>";
            echo "<td>{$cadet['id']}</td>";
            echo "<td>{$cadet['user_id']}</td>";
            echo "<td>{$cadet['student_id']}</td>";
            echo "<td>{$cadet['first_name']} {$cadet['last_name']}</td>";
            echo "<td>{$cadet['platoon']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No cadet profiles found. Creating sample cadet profiles...</p>";
        
        // Create sample cadet profiles
        $sample_cadets = [
            ['user_id' => 1, 'student_id' => 'BC001', 'first_name' => 'John', 'last_name' => 'Doe', 'platoon' => 'Alpha'],
            ['user_id' => 2, 'student_id' => 'BC002', 'first_name' => 'Jane', 'last_name' => 'Smith', 'platoon' => 'Bravo'],
            ['user_id' => 3, 'student_id' => 'BC003', 'first_name' => 'Mike', 'last_name' => 'Johnson', 'platoon' => 'Charlie']
        ];
        
        foreach ($sample_cadets as $cadet) {
            $stmt = $pdo->prepare("INSERT INTO cadet_profiles (user_id, student_id, first_name, last_name, platoon, email, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$cadet['user_id'], $cadet['student_id'], $cadet['first_name'], $cadet['last_name'], $cadet['platoon'], $cadet['student_id'] . '@example.com']);
        }
        echo "<p>Sample cadet profiles created!</p>";
        
        // Refresh the cadet list
        $stmt = $pdo->query("SELECT id, user_id, student_id, first_name, last_name, platoon FROM cadet_profiles LIMIT 5");
        $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Check current rifle assignments
    echo "<h3>Current Rifle Assignments:</h3>";
    $stmt = $pdo->query("
        SELECT ra.*, 
               CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) as cadet_name,
               cp.platoon,
               u.username as assigned_by_username
        FROM rifle_assignments ra
        LEFT JOIN cadet_profiles cp ON ra.cadet_profile_id = cp.id
        LEFT JOIN users u ON ra.assigned_by = u.id
        WHERE ra.status = 'active'
        ORDER BY ra.assigned_at DESC
    ");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($assignments) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Rifle Number</th><th>Cadet Name</th><th>Platoon</th><th>Course</th><th>Assigned At</th><th>Assigned By</th></tr>";
        foreach ($assignments as $assignment) {
            echo "<tr>";
            echo "<td>{$assignment['rifle_number']}</td>";
            echo "<td>{$assignment['cadet_name']}</td>";
            echo "<td>{$assignment['platoon']}</td>";
            echo "<td>{$assignment['course']}</td>";
            echo "<td>{$assignment['assigned_at']}</td>";
            echo "<td>{$assignment['assigned_by_username']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No current assignments found. Creating sample assignments...</p>";
        
        if (count($cadets) > 0) {
            // Create sample rifle assignments
            $sample_assignments = [
                ['rifle_number' => 'R001', 'cadet_profile_id' => $cadets[0]['id'], 'course' => 'Basic Military Training', 'assigned_by' => 1],
                ['rifle_number' => 'R002', 'cadet_profile_id' => $cadets[1]['id'], 'course' => 'Advanced Training', 'assigned_by' => 1],
            ];
            
            foreach ($sample_assignments as $assignment) {
                $stmt = $pdo->prepare("INSERT INTO rifle_assignments (rifle_number, cadet_profile_id, course, assigned_by, assigned_at, status) VALUES (?, ?, ?, ?, NOW(), 'active')");
                $stmt->execute([$assignment['rifle_number'], $assignment['cadet_profile_id'], $assignment['course'], $assignment['assigned_by']]);
            }
            echo "<p>Sample assignments created!</p>";
            
            // Show the new assignments
            $stmt = $pdo->query("
                SELECT ra.*, 
                       CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) as cadet_name,
                       cp.platoon,
                       u.username as assigned_by_username
                FROM rifle_assignments ra
                LEFT JOIN cadet_profiles cp ON ra.cadet_profile_id = cp.id
                LEFT JOIN users u ON ra.assigned_by = u.id
                WHERE ra.status = 'active'
                ORDER BY ra.assigned_at DESC
            ");
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1'>";
            echo "<tr><th>Rifle Number</th><th>Cadet Name</th><th>Platoon</th><th>Course</th><th>Assigned At</th><th>Assigned By</th></tr>";
            foreach ($assignments as $assignment) {
                echo "<tr>";
                echo "<td>{$assignment['rifle_number']}</td>";
                echo "<td>{$assignment['cadet_name']}</td>";
                echo "<td>{$assignment['platoon']}</td>";
                echo "<td>{$assignment['course']}</td>";
                echo "<td>{$assignment['assigned_at']}</td>";
                echo "<td>{$assignment['assigned_by_username']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>