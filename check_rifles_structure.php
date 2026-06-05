<?php
require_once 'includes/db.php';

echo "=== RIFLES TABLE STRUCTURE ===\n";
$result = $link->query('DESCRIBE rifles');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - Default: ' . ($row['Default'] ?? 'NULL') . "\n";
    }
} else {
    echo "Error: " . $link->error . "\n";
}

echo "\n=== SAMPLE RIFLES DATA ===\n";
$result = $link->query('SELECT * FROM rifles LIMIT 3');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Serial: " . $row['serial_number'] . ", Type: " . ($row['rifle_type'] ?? 'NULL') . ", Assigned: " . ($row['assigned_to'] ?? 'NULL') . "\n";
    }
} else {
    echo "Error: " . $link->error . "\n";
}

echo "\n=== USERS TABLE ROLES ===\n";
$result = $link->query('SELECT role, COUNT(*) as count FROM users GROUP BY role');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo "Role: " . $row['role'] . " - Count: " . $row['count'] . "\n";
    }
} else {
    echo "Error: " . $link->error . "\n";
}

$link->close();
?>