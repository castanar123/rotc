<?php
/**
 * Test script to insert sample attendance data for testing dashboard filtering
 */

require_once 'db.php';

try {
    // First, ensure we have the updated database structure
    echo "Updating database structure...\n";
    
    // Add gender and platoon columns if they don't exist
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS gender ENUM('Male', 'Female') NOT NULL DEFAULT 'Male'");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS platoon VARCHAR(20) NOT NULL DEFAULT 'Alpha'");
    
    // Update existing students with sample data
    $students = [
        ['20230001', 'John Doe', 'Male', 'Alpha'],
        ['20230002', 'Jane Smith', 'Female', 'Bravo'],
        ['20230003', 'Michael Johnson', 'Male', 'Charlie'],
        ['20230004', 'Sarah Johnson', 'Female', 'Alpha'],
        ['20230005', 'Robert Williams', 'Male', 'Bravo'],
        ['20230006', 'Emily Davis', 'Female', 'Charlie'],
        ['20230007', 'James Brown', 'Male', 'Delta'],
        ['20230008', 'Jessica Miller', 'Female', 'Delta'],
        ['20230009', 'David Wilson', 'Male', 'Echo'],
        ['20230010', 'Jennifer Moore', 'Female', 'Echo']
    ];
    
    echo "Inserting/updating student data...\n";
    foreach ($students as $student) {
        $stmt = $pdo->prepare("INSERT INTO students (student_id, name, gender, platoon) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), gender = VALUES(gender), platoon = VALUES(platoon)");
        $stmt->execute($student);
    }
    
    // Insert sample attendance data for different TDs, semesters, and dates
    echo "Inserting sample attendance data...\n";
    
    // Clear existing attendance data
    $pdo->exec("DELETE FROM attendance");
    
    $attendanceData = [
        // TD1, Semester 1, Today
        ['20230001', 1, 1, date('Y-m-d')],
        ['20230002', 1, 1, date('Y-m-d')],
        ['20230004', 1, 1, date('Y-m-d')],
        ['20230005', 1, 1, date('Y-m-d')],
        ['20230007', 1, 1, date('Y-m-d')],
        
        // TD2, Semester 1, Today
        ['20230001', 2, 1, date('Y-m-d')],
        ['20230003', 2, 1, date('Y-m-d')],
        ['20230006', 2, 1, date('Y-m-d')],
        ['20230008', 2, 1, date('Y-m-d')],
        
        // TD1, Semester 2, Today
        ['20230002', 1, 2, date('Y-m-d')],
        ['20230004', 1, 2, date('Y-m-d')],
        ['20230006', 1, 2, date('Y-m-d')],
        ['20230009', 1, 2, date('Y-m-d')],
        ['20230010', 1, 2, date('Y-m-d')],
        
        // TD1, Semester 1, Yesterday
        ['20230001', 1, 1, date('Y-m-d', strtotime('-1 day'))],
        ['20230003', 1, 1, date('Y-m-d', strtotime('-1 day'))],
        ['20230005', 1, 1, date('Y-m-d', strtotime('-1 day'))],
        ['20230007', 1, 1, date('Y-m-d', strtotime('-1 day'))],
        ['20230009', 1, 1, date('Y-m-d', strtotime('-1 day'))],
    ];
    
    foreach ($attendanceData as $record) {
        $stmt = $pdo->prepare("INSERT INTO attendance (student_id, td, semester, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->execute($record);
    }
    
    echo "Sample data inserted successfully!\n";
    echo "\nSample data summary:\n";
    echo "- 10 students with gender and platoon data\n";
    echo "- Attendance records for TD1 and TD2\n";
    echo "- Records for Semester 1 and 2\n";
    echo "- Records for today and yesterday\n";
    echo "\nYou can now test the dashboard filtering functionality.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>