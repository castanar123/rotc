<?php
// Test script to check cadet summary report generation
// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Simulate the cadet_summary report logic
$report_type = 'cadet_summary';
$report_title = 'AER LIST OF BENEFICIARIES - 2nd Semester S.Y. 2024-25';
$report_data = [];
$report_stats = [];

// Get cadet profiles data for MS2 cadets
$sql = "SELECT p.first_name, p.last_name, p.platoon, p.student_id, p.course, p.year_level, p.contact_number, p.email, u.username
        FROM cadet_profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.year_level = 'MS2'
        ORDER BY p.platoon, p.last_name, p.first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cadet statistics
$stats_sql = "SELECT 
    COUNT(*) as total_cadets,
    COUNT(DISTINCT p.platoon) as total_platoons,
    COUNT(DISTINCT p.course) as total_courses
    FROM cadet_profiles p
    JOIN users u ON p.user_id = u.id
    WHERE p.year_level = 'MS2'";

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$report_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$pdo = null;

echo "<h1>" . htmlspecialchars($report_title) . "</h1>";
echo "<p>Generated on: " . date('F j, Y') . "</p>";
echo "<h2>Statistics:</h2>";
echo "<p>Total Cadets: " . ($report_stats['total_cadets'] ?? 0) . "</p>";
echo "<p>Total Platoons: " . ($report_stats['total_platoons'] ?? 0) . "</p>";
echo "<p>Total Courses: " . ($report_stats['total_courses'] ?? 0) . "</p>";

echo "<h2>Cadet Data:</h2>";
if(!empty($report_data)){
    echo "<table border='1'>";
    echo "<tr><th>Name</th><th>Student ID</th><th>Platoon</th><th>Year Level</th><th>Course</th><th>Contact</th><th>Email</th><th>Username</th></tr>";
    foreach($report_data as $row){
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_id'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['platoon'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['year_level'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['course'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['contact_number'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['email'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['username'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No MS2 cadets found.</p>";
}
?>