<?php
require_once 'includes/rifle_qr_functions.php';
require_once 'includes/db.php';

// Test rifle QR generation
echo "Testing Rifle QR Generation...\n";

// Check if we have any rifles in the database
$sql = "SELECT id, rifle_number FROM rifles LIMIT 1";
$result = $link->query($sql);

if ($result && $result->num_rows > 0) {
    $rifle = $result->fetch_assoc();
    echo "Found rifle: ID {$rifle['id']}, Number {$rifle['rifle_number']}\n";
    
    // Try to generate QR code
    $qr_path = generateRifleQR($rifle['id'], $rifle['rifle_number']);
    
    if ($qr_path) {
        echo "QR code generated successfully: $qr_path\n";
        
        // Check if file actually exists
        if (file_exists($qr_path)) {
            echo "QR code file exists and is " . filesize($qr_path) . " bytes\n";
        } else {
            echo "ERROR: QR code file was not created\n";
        }
    } else {
        echo "ERROR: QR code generation failed\n";
    }
} else {
    echo "No rifles found in database\n";
}

// Test batch generation
echo "\nTesting Batch QR Generation...\n";
$batch_result = batchGenerateRifleQRs();
echo "Batch result: " . print_r($batch_result, true) . "\n";
?>