<?php
require_once 'includes/db.php';

echo "=== CADET_PROFILES TABLE STRUCTURE ===\n";

$result = $link->query('DESCRIBE cadet_profiles');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . "\n";
}

echo "\n=== DONE ===\n";
?>