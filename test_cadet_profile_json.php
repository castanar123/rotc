<?php
// Test script to check what generate_document.php actually outputs for cadet profile

// Capture all output including errors
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate the POST request for cadet profile generation
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];

// Create a temporary input stream
$input_data = json_encode([
    'document_type' => 'aer',
    'sub_document' => 'cadet_profile'
]);

// Create a temporary file to simulate php://input
$temp_file = tempnam(sys_get_temp_dir(), 'test_input');
file_put_contents($temp_file, $input_data);

// Override the file_get_contents function for php://input
function file_get_contents_override($filename) {
    global $temp_file;
    if ($filename === 'php://input') {
        return file_get_contents($temp_file);
    }
    return file_get_contents($filename);
}

// Replace file_get_contents with our override
if (!function_exists('file_get_contents_original')) {
    function file_get_contents_original($filename) {
        return file_get_contents($filename);
    }
}

// Start session to avoid session errors
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

echo "=== TESTING CADET PROFILE GENERATION ===\n";
echo "Input data: " . $input_data . "\n";
echo "=== OUTPUT START ===\n";

// Include the generate_document.php file
include 'generate_document.php';

echo "\n=== OUTPUT END ===\n";

$output = ob_get_clean();
echo $output;