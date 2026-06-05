<?php
require_once 'includes/db.php';

// Simple test report without authentication
echo "<h1>Test Report - Cadet Data</h1>";

try {
    // Test cadet_summary query
    $sql = "SELECT p.first_name, p.last_name, p.platoon, p.student_id, p.course, p.year_level, p.contact_number, p.email, u.username
            FROM cadet_profiles p
            JOIN users u ON p.user_id = u.id
            WHERE p.year_level = 'MS2'
            ORDER BY p.platoon, p.last_name, p.first_name";
    
    if($stmt = mysqli_prepare($link, $sql)){
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            $count = 0;
            echo "<h2>MS-2 Basic Cadets:</h2>";
            echo "<table border='1'>";
            echo "<tr><th>Name</th><th>Student ID</th><th>Platoon</th><th>Course</th><th>Contact</th><th>Email</th></tr>";
            
            while($row = mysqli_fetch_assoc($result)){
                $count++;
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['student_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['platoon']) . "</td>";
                echo "<td>" . htmlspecialchars($row['course']) . "</td>";
                echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<p><strong>Total MS-2 Cadets: $count</strong></p>";
        } else {
            echo "<p>Error executing query: " . mysqli_error($link) . "</p>";
        }
        mysqli_stmt_close($stmt);
    } else {
        echo "<p>Error preparing query: " . mysqli_error($link) . "</p>";
    }
    
    // Test if tables exist
    echo "<h2>Database Tables Check:</h2>";
    $tables_result = mysqli_query($link, "SHOW TABLES");
    echo "<ul>";
    while($table = mysqli_fetch_array($tables_result)) {
        echo "<li>" . $table[0] . "</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

mysqli_close($link);
?>