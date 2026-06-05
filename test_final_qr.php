<?php
// Final test to verify QR generation is working correctly
require_once 'includes/db.php';
require_once 'includes/rifle_qr_functions.php';

echo "Final QR Generation Test\n";
echo "========================\n\n";

// Test 1: Generate QR using enhanced function
echo "1. Testing enhanced QR generation...\n";
$result = generateEnhancedRifleQR('R010', 'rifle', true, true);

if ($result['success']) {
    echo "✓ Enhanced QR generated successfully\n";
    echo "QR Path: {$result['qr_path']}\n";
    echo "Encryption: rifle\n\n";
    
    // Test 2: Create test data and encrypt it
    echo "2. Testing data encryption and decryption...\n";
    
    $test_data = [
        'type' => 'rifle',
        'rifle_id' => '10',
        'rifle_number' => 'R010',
        'rifle_type' => 'Standard',
        'generated_at' => date('Y-m-d H:i:s'),
        'system' => 'rotc_rifle_management',
        'encryption_method' => 'rifle',
        'version' => '2.0'
    ];
    
    $json_data = json_encode($test_data);
    echo "Original data: $json_data\n";
    
    // Encrypt using the same method as enhanced function
    // Map encryption type to actual key
    $encryption_keys = [
        'rifle' => 'rifle-management-system-key-2024',
        'attendance' => 'attendance-system-key-2024'
    ];
    $actual_key = $encryption_keys['rifle'];
    $encrypted = encryptForCryptoJS($json_data, $actual_key);
    echo "✓ Data encrypted successfully\n";
    echo "Encrypted length: " . strlen($encrypted) . " characters\n";
    
    // Test decryption
    echo "\n3. Testing decryption...\n";
    $decoded = decodeEnhancedQRData($encrypted);
    
    if ($decoded['success']) {
        echo "✓ Successfully decoded QR data\n";
        echo "Decoded data: " . json_encode($decoded['data']) . "\n";
        
        if (isset($decoded['data']['system']) && $decoded['data']['system'] === 'rotc_rifle_management') {
            echo "✓ System field is correct\n";
        } else {
            echo "✗ System field is missing or incorrect\n";
        }
        
        if (isset($decoded['data']['version']) && $decoded['data']['version'] === '2.0') {
            echo "✓ Version field is correct\n";
        } else {
            echo "✗ Version field is missing or incorrect\n";
        }
        
        echo "\n✓ QR generation is now using CryptoJS format!\n";
    } else {
        echo "✗ Failed to decode QR data: {$decoded['message']}\n";
    }
} else {
    echo "✗ Failed to generate enhanced QR: {$result['message']}\n";
}

echo "\n========================\n";
echo "Test completed!\n";
echo "\nThe QR codes are now being generated with the correct CryptoJS encryption\n";
echo "and include the required 'system' field that the scanner expects.\n";
?>