<?php
require_once 'includes/db.php';

echo "<h1>Database Upgrade to v2: School Year & Semester</h1>";

$columns_to_add = [
    'school_year' => 'VARCHAR(20) NOT NULL',
    'semester' => "ENUM('1st', '2nd') NOT NULL"
];

$all_successful = true;

foreach ($columns_to_add as $column => $definition) {
    // Check if the column already exists
    $check_column_sql = "SHOW COLUMNS FROM `attendance_logs` LIKE ?";
    $stmt = $link->prepare($check_column_sql);
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // Column does not exist, so add it
        $alter_sql = "ALTER TABLE `attendance_logs` ADD COLUMN `$column` $definition AFTER `cadet_profile_id`";
        if ($link->query($alter_sql) === TRUE) {
            echo "<p style='color:green;'>Column `$column` added successfully.</p>";
        } else {
            echo "<p style='color:red;'>Error adding column `$column`: " . $link->error . "</p>";
            $all_successful = false;
        }
    } else {
        echo "<p style='color:blue;'>Column `$column` already exists. No action taken.</p>";
    }
    $stmt->close();
}

if ($all_successful) {
    echo "<hr>";
    echo "<h2>Database upgrade complete.</h2>";
    echo "<p>You can now safely delete this file (upgrade_v2.php).</p>";
} else {
    echo "<hr>";
    echo "<h2>Database upgrade encountered errors. Please review the messages above.</h2>";
}

$link->close();
?>
