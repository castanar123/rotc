<?php
require_once 'includes/db.php';

echo "Officers table structure:\n";
$stmt = $pdo->query('DESCRIBE officers');
while($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\nSample officer data:\n";
$stmt = $pdo->query('SELECT * FROM officers LIMIT 2');
while($row = $stmt->fetch()) {
    print_r($row);
}
?>