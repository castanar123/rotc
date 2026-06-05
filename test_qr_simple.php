<?php
/**
 * Simple QR Generation Test with File Output
 */

require_once 'includes/db.php';
require_once 'generate_id_card.php';

$output = [];
$output[] = "Starting QR generation test...";

try {
    // Test database connection
    if (!$link) {
        throw new Exception('Database connection failed');
    }
    $output[] = "Database connection: OK";
    
    // Test QR generation
    $temp_id = 'TEST_' . date('YmdHis');
    $output[] = "Generated temp ID: " . $temp_id;
    
    // Test ID card generation
    $card_result = generateROTCIDCard($temp_id);
    
    if ($card_result['success']) {
        $output[] = "ID card generation: SUCCESS";
        $output[] = "QR path: " . $card_result['qr_path'];
        $output[] = "Front path: " . $card_result['front_path'];
        $output[] = "Back path: " . $card_result['back_path'];
    } else {
        $output[] = "ID card generation: FAILED";
        $output[] = "Error: " . $card_result['error'];
    }
    
    // Test database insert
    $stmt = $link->prepare("INSERT INTO borrower_temp_ids (temp_id, status, created_at) VALUES (?, 'available', NOW())");
    $stmt->bind_param('s', $temp_id);
    
    if ($stmt->execute()) {
        $output[] = "Database insert: SUCCESS";
        $output[] = "Insert ID: " . $link->insert_id;
    } else {
        $output[] = "Database insert: FAILED";
        $output[] = "Error: " . $stmt->error;
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $output[] = "ERROR: " . $e->getMessage();
    $output[] = "File: " . $e->getFile();
    $output[] = "Line: " . $e->getLine();
}

// Write output to file
file_put_contents('qr_test_output.txt', implode("\n", $output));

// Also echo for web output
echo "<pre>" . implode("\n", $output) . "</pre>";
?>