<?php
// Test rifle QR generation with new encryption
require_once 'rifle_qr_functions.php';

echo "Testing rifle QR generation...\n";

// Test the encryption function directly
$test_data = json_encode([
    'type' => 'rifle',
    'serial' => 'TEST123',
    'model' => 'Standard',
    'id' => 1,
    'timestamp' => time()
]);

$encryption_key = 'rifle-management-system-key-2024';
$encrypted = cryptoJSAESEncrypt($test_data, $encryption_key);

if ($encrypted) {
    echo "Encryption successful!\n";
    echo "Encrypted data: " . $encrypted . "\n";
    echo "Length: " . strlen($encrypted) . " characters\n";
} else {
    echo "Encryption failed!\n";
}

// Test rifle QR generation
echo "\nTesting generateRifleQR function...\n";
$result = generateRifleQR(1, '54545');

if ($result) {
    echo "QR generated successfully: " . $result . "\n";
    if (file_exists($result)) {
        echo "File exists and size: " . filesize($result) . " bytes\n";
    } else {
        echo "File does not exist!\n";
    }
} else {
    echo "QR generation failed!\n";
}

echo "\nTest completed.\n";
?>