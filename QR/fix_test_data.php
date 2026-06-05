<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'attendance_system';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Clear existing data
    $pdo->exec('DELETE FROM attendance');
    $pdo->exec('DELETE FROM students');
    
    // Insert sample students with lowercase gender
    $students = [
        ['student_id' => 'S001', 'name' => 'John Doe', 'gender' => 'male', 'platoon' => 'Alpha'],
        ['student_id' => 'S002', 'name' => 'Jane Smith', 'gender' => 'female', 'platoon' => 'Alpha'],
        ['student_id' => 'S003', 'name' => 'Mike Johnson', 'gender' => 'male', 'platoon' => 'Bravo'],
        ['student_id' => 'S004', 'name' => 'Sarah Wilson', 'gender' => 'female', 'platoon' => 'Bravo'],
        ['student_id' => 'S005', 'name' => 'David Brown', 'gender' => 'male', 'platoon' => 'Charlie']
    ];
    
    foreach ($students as $student) {
        $stmt = $pdo->prepare('INSERT INTO students (student_id, name, gender, platoon) VALUES (?, ?, ?, ?)');
        $stmt->execute([$student['student_id'], $student['name'], $student['gender'], $student['platoon']]);
    }
    
    // Insert attendance for today
    $attendanceData = [
        ['student_id' => 'S001', 'td' => 'TD1', 'semester' => 'Fall2024'],
        ['student_id' => 'S002', 'td' => 'TD1', 'semester' => 'Fall2024'],
        ['student_id' => 'S003', 'td' => 'TD1', 'semester' => 'Fall2024']
    ];
    
    foreach ($attendanceData as $attendance) {
        $stmt = $pdo->prepare('INSERT INTO attendance (student_id, td, semester, timestamp) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$attendance['student_id'], $attendance['td'], $attendance['semester']]);
    }
    
    echo "Test data inserted successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>