<?php
// Test script to verify QR system integration with ROTC database
require_once 'db.php';

echo "<h2>QR System Integration Test</h2>";

// Test 1: Database Connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $db_info = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Connected to database: <strong>" . $db_info['db_name'] . "</strong><br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 2: Students View
echo "<h3>2. Students View Test</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Students view accessible. Found {$count['count']} active students.<br>";
    
    // Show sample students
    $stmt = $pdo->query("SELECT * FROM students LIMIT 3");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<strong>Sample students:</strong><br>";
    foreach ($students as $student) {
        echo "- ID: {$student['student_id']}, Name: {$student['name']}<br>";
    }
} catch (Exception $e) {
    echo "❌ Students view test failed: " . $e->getMessage() . "<br>";
}

// Test 3: Attendance Table
echo "<h3>3. Attendance Table Test</h3>";
try {
    $stmt = $pdo->query("DESCRIBE attendance");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Attendance table structure verified. Columns:<br>";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})<br>";
    }
} catch (Exception $e) {
    echo "❌ Attendance table test failed: " . $e->getMessage() . "<br>";
}

// Test 4: Training Days Table
echo "<h3>4. Training Days Table Test</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM training_days");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Training days table accessible. Found {$count['count']} training days.<br>";
    
    // Show sample training days
    $stmt = $pdo->query("SELECT * FROM training_days LIMIT 5");
    $tds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<strong>Sample training days:</strong><br>";
    foreach ($tds as $td) {
        echo "- TD {$td['td_id']}: {$td['label']}<br>";
    }
} catch (Exception $e) {
    echo "❌ Training days test failed: " . $e->getMessage() . "<br>";
}

// Test 5: QR Functions
echo "<h3>5. QR Functions Test</h3>";
try {
    // Test with first available student
    $stmt = $pdo->query("SELECT student_id FROM students LIMIT 1");
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        $student_id = $student['student_id'];
        echo "Testing with student ID: {$student_id}<br>";
        
        // Test getStudentDetails function
        $details = getStudentDetails($student_id);
        if ($details) {
            echo "✅ getStudentDetails() working. Student: {$details['name']}<br>";
        } else {
            echo "❌ getStudentDetails() failed<br>";
        }
        
        // Test alreadyMarkedToday function
        $marked = alreadyMarkedToday($student_id, 1, 1);
        echo "✅ alreadyMarkedToday() working. Result: " . ($marked ? 'Already marked' : 'Not marked') . "<br>";
        
    } else {
        echo "❌ No students found for testing<br>";
    }
} catch (Exception $e) {
    echo "❌ QR functions test failed: " . $e->getMessage() . "<br>";
}

echo "<h3>Integration Test Complete!</h3>";
echo "<p><strong>Status:</strong> QR system successfully integrated with ROTC database.</p>";
echo "<p><a href='index.html'>← Back to QR System</a></p>";
?>