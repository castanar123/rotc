<?php
// Script to update generate_report.php access controls for 1cl officers

// Update generate_report.php to allow 1cl officers
$generate_report_file = __DIR__ . '/rotc/reports/generate_report.php';
$generate_report_content = file_get_contents($generate_report_file);

// Replace the role check in generate_report.php
$generate_report_content = str_replace(
    "if(\$_SESSION['role'] !== 'admin'){",
    "if(!in_array(\$_SESSION['role'], ['admin', '1cl'])){",
    $generate_report_content
);

file_put_contents($generate_report_file, $generate_report_content);
echo "Updated generate_report.php to allow 1cl officers access.<br>";

echo "<p>Generate Reports access controls updated successfully!</p>";
echo "<p><a href='../login.php'>Go to login page</a></p>";
?>