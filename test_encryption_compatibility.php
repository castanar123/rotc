<?php
require_once 'rifle_qr_test_generator.php';

// Test encryption compatibility
$test_data = json_encode([
    'type' => 'rifle',
    'id' => '999',
    'number' => 'TEST001',
    'generated_at' => date('Y-m-d H:i:s'),
    'system' => 'compatibility_test'
]);

$encryption_key = 'rifle-management-system-key-2024';
$encrypted = encryptForCryptoJS($test_data, $encryption_key);

echo "<h2>Encryption Compatibility Test</h2>";
echo "<p><strong>Original Data:</strong></p>";
echo "<pre>" . htmlspecialchars($test_data) . "</pre>";
echo "<p><strong>Encrypted Data (CryptoJS Compatible):</strong></p>";
echo "<pre>" . htmlspecialchars($encrypted) . "</pre>";
echo "<p><strong>Encryption Key:</strong> " . htmlspecialchars($encryption_key) . "</p>";
echo "<p><strong>Format Check:</strong> " . (strpos(base64_decode($encrypted), 'Salted__') === 0 ? 'PASS - Contains Salted__ prefix' : 'FAIL - Missing Salted__ prefix') . "</p>";

// Generate a test QR code
echo "<h3>Test QR Code Generation</h3>";
$qr_result = generateTestRifleQR('999', 'TEST001');
if ($qr_result['success']) {
    echo "<p><strong>QR Generation:</strong> SUCCESS</p>";
    echo "<p><strong>QR File:</strong> " . htmlspecialchars($qr_result['qr_file']) . "</p>";
    echo "<img src='" . htmlspecialchars($qr_result['qr_file']) . "' alt='Test QR Code' style='max-width: 200px;'>";
} else {
    echo "<p><strong>QR Generation:</strong> FAILED - " . htmlspecialchars($qr_result['error']) . "</p>";
}

echo "<p><a href='simple_rifle_scanner.php'>Test with Scanner</a> | <a href='rifle_qr_test_generator.php'>Back to Generator</a></p>";
?>