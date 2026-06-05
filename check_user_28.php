<?php
require_once 'includes/db.php';

// Check if user 28 has a cadet_profiles record
$stmt = $pdo->prepare('SELECT * FROM cadet_profiles WHERE user_id = ?');
$stmt->execute([28]);
$result = $stmt->fetch();

if ($result) {
    echo "Found cadet_profiles record for user_id 28:\n";
    print_r($result);
} else {
    echo "No cadet_profiles record found for user_id 28\n\n";
    
    // Show all cadet_profiles records
    echo "All cadet_profiles records:\n";
    $stmt = $pdo->prepare('SELECT id, user_id, first_name, last_name, student_id FROM cadet_profiles');
    $stmt->execute();
    $all = $stmt->fetchAll();
    foreach ($all as $record) {
        echo "ID: {$record['id']}, User ID: {$record['user_id']}, Name: {$record['first_name']} {$record['last_name']}, Student ID: {$record['student_id']}\n";
    }
    
    // Check attendance data for different cadet_ids
    echo "\nAttendance data by cadet_id:\n";
    $stmt = $pdo->prepare('SELECT cadet_id, COUNT(*) as count FROM attendance GROUP BY cadet_id');
    $stmt->execute();
    $attendance_counts = $stmt->fetchAll();
    foreach ($attendance_counts as $count) {
        echo "Cadet ID: {$count['cadet_id']}, Records: {$count['count']}\n";
    }
}
?>