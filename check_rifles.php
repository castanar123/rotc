<?php
require_once 'config.php';

try {
    // Check rifles without QR codes
    $stmt = $pdo->query("SELECT id, rifle_number, qr_code_path FROM rifles WHERE qr_code_path IS NULL OR qr_code_path = ''");
    $rifles = $stmt->fetchAll();
    
    echo "Rifles without QR codes: " . count($rifles) . "\n";
    
    foreach($rifles as $rifle) {
        echo "ID: " . $rifle['id'] . ", Number: " . $rifle['rifle_number'] . ", QR Path: " . ($rifle['qr_code_path'] ?: 'NULL') . "\n";
    }
    
    // Check all rifles
    $stmt2 = $pdo->query("SELECT id, rifle_number, qr_code_path FROM rifles");
    $allRifles = $stmt2->fetchAll();
    
    echo "\nAll rifles in database: " . count($allRifles) . "\n";
    
    foreach($allRifles as $rifle) {
        echo "ID: " . $rifle['id'] . ", Number: " . $rifle['rifle_number'] . ", QR Path: " . ($rifle['qr_code_path'] ?: 'NULL') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>