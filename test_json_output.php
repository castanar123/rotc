<?php
// Test script to check what's being output before JSON
ob_start();

require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/rifle_functions.php';
require_once 'includes/rifle_qr_functions.php';

// Capture any output from includes
$captured_output = ob_get_contents();
ob_clean();

echo "=== CAPTURED OUTPUT FROM INCLUDES ===\n";
echo "Length: " . strlen($captured_output) . "\n";
echo "Content: " . var_export($captured_output, true) . "\n";
echo "=== END CAPTURED OUTPUT ===\n";

// Test a simple JSON response
header('Content-Type: application/json');
echo json_encode(['test' => 'success', 'message' => 'This is a test JSON response']);
?>