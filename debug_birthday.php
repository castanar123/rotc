<?php
$conn = new mysqli('localhost', 'root', '', 'rotc_db', 3306); // Changed back to port 3306

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Table structure:\n";
$result = $conn->query('DESCRIBE cadet_profiles');
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\nSample data:\n";
$result = $conn->query('SELECT id, first_name, last_name, date_of_birth FROM cadet_profiles WHERE status = "active" LIMIT 5');
while ($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ', Name: ' . $row['first_name'] . ' ' . $row['last_name'] . "\n";
    echo '  DOB: ' . var_export($row['date_of_birth'], true) . "\n";
    echo '  DOB type: ' . gettype($row['date_of_birth']) . "\n";
    echo '  DOB length: ' . strlen($row['date_of_birth']) . "\n";
    echo '  strtotime result: ' . var_export(strtotime($row['date_of_birth']), true) . "\n";
    echo '  formatted date: ' . date('d-M-y', strtotime($row['date_of_birth'])) . "\n";
    echo "\n";
}

$conn->close();
?>