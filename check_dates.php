<?php
$conn = new mysqli('localhost', 'root', '', 'rotc_db', 3306); // Changed back to port 3306

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Checking for non-NULL date_of_birth records:\n";
$result = $conn->query('SELECT id, first_name, last_name, date_of_birth FROM cadet_profiles WHERE date_of_birth IS NOT NULL LIMIT 10');
$count = 0;
while ($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ', Name: ' . $row['first_name'] . ' ' . $row['last_name'] . ', DOB: ' . $row['date_of_birth'] . "\n";
    $count++;
}
echo "Total records with non-NULL date_of_birth: $count\n\n";

echo "Checking for any date-related columns:\n";
$result = $conn->query('DESCRIBE cadet_profiles');
while ($row = $result->fetch_assoc()) {
    if (strpos(strtolower($row['Field']), 'date') !== false || strpos(strtolower($row['Field']), 'birth') !== false) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
}

echo "\nChecking total records in cadet_profiles:\n";
$result = $conn->query('SELECT COUNT(*) as total FROM cadet_profiles');
$row = $result->fetch_assoc();
echo "Total records: " . $row['total'] . "\n";

echo "\nChecking active records:\n";
$result = $conn->query('SELECT COUNT(*) as total FROM cadet_profiles WHERE status = "active"');
$row = $result->fetch_assoc();
echo "Active records: " . $row['total'] . "\n";

echo "\nChecking birthdate column data:\n";
$result = $conn->query('SELECT id, first_name, last_name, birthdate, date_of_birth FROM cadet_profiles WHERE status = "active" LIMIT 5');
while ($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ', Name: ' . $row['first_name'] . ' ' . $row['last_name'] . "\n";
    echo '  birthdate: ' . var_export($row['birthdate'], true) . "\n";
    echo '  date_of_birth: ' . var_export($row['date_of_birth'], true) . "\n";
    echo "\n";
}

$conn->close();
?>