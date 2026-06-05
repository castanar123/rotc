<?php
// Script to update dashboard.php access controls for 1cl officers

// Update dashboard.php to allow 1cl officers
$dashboard_file = __DIR__ . '/rotc/attendance/dashboard.php';
$dashboard_content = file_get_contents($dashboard_file);

// Replace the role check in dashboard.php
$dashboard_content = str_replace(
    "if (!in_array(\$_SESSION['role'], ['admin', 'instructor']))",
    "if (!in_array(\$_SESSION['role'], ['admin', 'instructor', '1cl']))",
    $dashboard_content
);

file_put_contents($dashboard_file, $dashboard_content);
echo "Updated dashboard.php to allow 1cl officers access.<br>";

echo "<p>Dashboard access controls updated successfully!</p>";
echo "<p><a href='../login.php'>Go to login page</a></p>";
?>