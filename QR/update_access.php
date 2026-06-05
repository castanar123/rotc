<?php
// Script to update access controls for 1cl and 2cl officers

// Update scan.php to allow 1cl and 2cl officers
$scan_file = __DIR__ . '/rotc/attendance/scan.php';
$scan_content = file_get_contents($scan_file);

// Replace the role check in scan.php
$scan_content = str_replace(
    "if (!in_array(\$_SESSION['role'], ['admin', 'instructor']))",
    "if (!in_array(\$_SESSION['role'], ['admin', 'instructor', '1cl', '2cl']))",
    $scan_content
);

file_put_contents($scan_file, $scan_content);
echo "Updated scan.php to allow 1cl and 2cl officers access.<br>";

// Update logs.php to allow 1cl officers only (not 2cl)
$logs_file = __DIR__ . '/rotc/attendance/logs.php';
$logs_content = file_get_contents($logs_file);

// Replace the role check in logs.php
$logs_content = str_replace(
    "if (!in_array(\$_SESSION['role'], ['admin', 'instructor']))",
    "if (!in_array(\$_SESSION['role'], ['admin', 'instructor', '1cl']))",
    $logs_content
);

file_put_contents($logs_file, $logs_content);
echo "Updated logs.php to allow 1cl officers access (2cl excluded).<br>";

echo "<p>Access controls updated successfully!</p>";
echo "<p><a href='../login.php'>Go to login page</a></p>";
?>