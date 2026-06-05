<?php
// Backup File Download Handler
// Provides secure download of backup files from admin dashboard

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

if (!isset($_GET['file'])) {
    http_response_code(400);
    die('No file specified.');
}

$filename = $_GET['file'];

// Security: Only allow specific backup file patterns
if (!preg_match('/^rotc_db_(backup_|manual_backup_)[\w\-]+\.(sql|zip)$/', $filename)) {
    http_response_code(400);
    die('Invalid file name.');
}

// Search for the file in backup directories
$backup_dirs = [
    'backups/hourly',
    'backups/daily', 
    'backups'
];

$file_path = null;
foreach ($backup_dirs as $dir) {
    $potential_path = $dir . '/' . $filename;
    if (file_exists($potential_path)) {
        $file_path = $potential_path;
        break;
    }
}

if (!$file_path) {
    http_response_code(404);
    die('File not found.');
}

// Get file info
$file_size = filesize($file_path);
$file_extension = pathinfo($filename, PATHINFO_EXTENSION);

// Set appropriate headers
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Output file
readfile($file_path);
exit;
?>