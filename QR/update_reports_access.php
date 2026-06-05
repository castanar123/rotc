<?php
// Script to update view_report.php access controls for 1cl officers

// Update view_report.php to allow 1cl officers
$report_file = __DIR__ . '/rotc/reports/view_report.php';
$report_content = file_get_contents($report_file);

// Replace the role check in view_report.php
$report_content = str_replace(
    "if(\$_SESSION['role'] !== 'admin'){",
    "if(!in_array(\$_SESSION['role'], ['admin', '1cl'])){",
    $report_content
);

file_put_contents($report_file, $report_content);
echo "Updated view_report.php to allow 1cl officers access.<br>";

echo "<p>Reports access controls updated successfully!</p>";
echo "<p><a href='../login.php'>Go to login page</a></p>";
?>