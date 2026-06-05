<?php
// Summary of all access control updates for 1cl and 2cl officers

// Function to update access controls in a file
function update_access_control($file_path, $search_pattern, $replace_pattern) {
    if (!file_exists($file_path)) {
        return "Error: File not found - {$file_path}";
    }
    
    $content = file_get_contents($file_path);
    $updated_content = str_replace($search_pattern, $replace_pattern, $content);
    
    if ($content === $updated_content) {
        return "No changes needed for {$file_path}";
    }
    
    if (file_put_contents($file_path, $updated_content)) {
        return "Successfully updated {$file_path}";
    } else {
        return "Error updating {$file_path}";
    }
}

// Define the files and patterns to update
$updates = [
    // Allow 1cl and 2cl officers to access the QR scanner
    [
        'file' => __DIR__ . '/rotc/attendance/scan.php',
        'search' => "if (!in_array(\$_SESSION['role'], ['admin', 'instructor']))",
        'replace' => "if (!in_array(\$_SESSION['role'], ['admin', 'instructor', '1cl', '2cl']))"
    ],
    
    // Allow only 1cl officers (not 2cl) to access attendance logs
    [
        'file' => __DIR__ . '/rotc/attendance/logs.php',
        'search' => "if (!in_array(\$_SESSION['role'], ['admin', 'instructor']))",
        'replace' => "if (!in_array(\$_SESSION['role'], ['admin', 'instructor', '1cl']))"
    ],
    
    // Allow 1cl officers to access the attendance dashboard
    [
        'file' => __DIR__ . '/rotc/attendance/dashboard.php',
        'search' => "if (!in_array(\$_SESSION['role'], ['admin', 'instructor']))",
        'replace' => "if (!in_array(\$_SESSION['role'], ['admin', 'instructor', '1cl']))"
    ],
    
    // Allow 1cl officers to view reports
    [
        'file' => __DIR__ . '/rotc/reports/view_report.php',
        'search' => "if(\$_SESSION['role'] !== 'admin'){",
        'replace' => "if(!in_array(\$_SESSION['role'], ['admin', '1cl'])){"
    ],
    
    // Allow 1cl officers to generate reports
    [
        'file' => __DIR__ . '/rotc/reports/generate_report.php',
        'search' => "if(\$_SESSION['role'] !== 'admin'){",
        'replace' => "if(!in_array(\$_SESSION['role'], ['admin', '1cl'])){"
    ]
];

// Apply all updates and collect results
$results = [];
foreach ($updates as $update) {
    $results[] = update_access_control($update['file'], $update['search'], $update['replace']);
}

// Display results
echo "<h2>Access Control Updates</h2>";
echo "<ul>";
foreach ($results as $result) {
    echo "<li>{$result}</li>";
}
echo "</ul>";

echo "<p>All access control updates have been applied.</p>";
echo "<p>Summary:</p>";
echo "<ul>";
echo "<li>1cl officers now have access to: QR scanner, attendance logs, attendance dashboard, view reports, and generate reports</li>";
echo "<li>2cl officers now have access to: QR scanner only</li>";
echo "</ul>";

echo "<p><a href='../login.php'>Go to login page</a></p>";
?>