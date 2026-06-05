<?php
require_once 'includes/db.php';

echo "<h2>Creating Test Data for Dashboard</h2>";

// Create attendance records for cadet_id 4 (the logged-in user's cadet profile)
$cadet_id = 4;
$student_id = '12345';

try {
    // Create some attendance records for this month
    $dates = [
        '2025-08-01',
        '2025-08-05', 
        '2025-08-08',
        '2025-08-12',
        '2025-08-15'
    ];
    
    echo "<h3>Creating Attendance Records:</h3>";
    foreach ($dates as $date) {
        $stmt = $pdo->prepare("INSERT INTO attendance (cadet_id, student_id, log_date, log_time, td, semester, status, timestamp, created_at) VALUES (?, ?, ?, '08:00:00', 1, 1, 'Present', ?, ?)");
        $timestamp = $date . ' 08:00:00';
        $stmt->execute([$cadet_id, $student_id, $date, $timestamp, $timestamp]);
        echo "<p>Added attendance for $date</p>";
    }
    
    // Create some grades
    echo "<h3>Creating Grade Records:</h3>";
    $grades = [
        ['semester' => '1st Semester 2025', 'drill_grade' => 85.5, 'conduct_grade' => 92.0, 'academics_grade' => 88.5, 'total_grade' => 88.7],
        ['semester' => '2nd Semester 2025', 'drill_grade' => 90.0, 'conduct_grade' => 89.5, 'academics_grade' => 91.0, 'total_grade' => 90.2]
    ];
    
    foreach ($grades as $grade) {
        $stmt = $pdo->prepare("INSERT INTO grades (cadet_id, semester, drill_grade, conduct_grade, academics_grade, total_grade, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$cadet_id, $grade['semester'], $grade['drill_grade'], $grade['conduct_grade'], $grade['academics_grade'], $grade['total_grade']]);
        echo "<p>Added grade: {$grade['semester']} - Total: {$grade['total_grade']}</p>";
    }
    
    // Create some announcements
    echo "<h3>Creating Announcements:</h3>";
    $announcements = [
        ['title' => 'Upcoming Field Training Exercise', 'content' => 'FTX scheduled for next weekend. Bring proper gear.', 'expires_at' => '2025-08-25 23:59:59'],
        ['title' => 'Uniform Inspection', 'content' => 'Class A uniform inspection this Friday at 0800.', 'expires_at' => '2025-08-22 23:59:59'],
        ['title' => 'Leadership Seminar', 'content' => 'Guest speaker from active duty will present on leadership principles.', 'expires_at' => '2025-08-30 23:59:59']
    ];
    
    foreach ($announcements as $announcement) {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, expires_at, created_by, priority, target_audience, created_at) VALUES (?, ?, ?, 1, 'normal', 'cadets', NOW())");
        $stmt->execute([$announcement['title'], $announcement['content'], $announcement['expires_at']]);
        echo "<p>Added announcement: {$announcement['title']}</p>";
    }
    
    echo "<h3>Test Data Created Successfully!</h3>";
    echo "<p><a href='test_dashboard_auth.php?login=1'>Login and Test Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>