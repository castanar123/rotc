<?php
require_once 'config.php';
require_once 'includes/rifle_qr_functions.php';

echo "<h2>Rifle QR Generation Verification</h2>";

try {
    // Check all rifles
    $stmt = $pdo->query("SELECT id, rifle_number, qr_code_path FROM rifles ORDER BY rifle_number");
    $rifles = $stmt->fetchAll();
    
    echo "<h3>Total rifles in database: " . count($rifles) . "</h3>";
    
    $with_qr = 0;
    $without_qr = 0;
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Rifle Number</th><th>QR Code Path</th><th>File Exists</th><th>Action</th></tr>";
    
    foreach($rifles as $rifle) {
        $has_qr_path = !empty($rifle['qr_code_path']);
        $file_exists = $has_qr_path && file_exists($rifle['qr_code_path']);
        
        if ($has_qr_path && $file_exists) {
            $with_qr++;
            $status = "✓ Has QR";
            $action = "<a href='{$rifle['qr_code_path']}' target='_blank'>View QR</a>";
        } else {
            $without_qr++;
            $status = "✗ No QR";
            $action = "<button onclick='generateQR({$rifle['id']})'>Generate QR</button>";
        }
        
        echo "<tr>";
        echo "<td>{$rifle['id']}</td>";
        echo "<td>{$rifle['rifle_number']}</td>";
        echo "<td>" . ($rifle['qr_code_path'] ?: 'None') . "</td>";
        echo "<td>" . ($file_exists ? 'Yes' : 'No') . "</td>";
        echo "<td>{$action}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h3>Summary:</h3>";
    echo "<p>Rifles with QR codes: {$with_qr}</p>";
    echo "<p>Rifles without QR codes: {$without_qr}</p>";
    
    // Test single QR generation
    if ($without_qr > 0) {
        echo "<h3>Testing QR Generation:</h3>";
        
        // Find first rifle without QR
        foreach($rifles as $rifle) {
            if (empty($rifle['qr_code_path']) || !file_exists($rifle['qr_code_path'])) {
                echo "<p>Testing QR generation for rifle: {$rifle['rifle_number']}</p>";
                
                $qr_path = generateRifleQR($rifle['id'], $rifle['rifle_number']);
                
                if ($qr_path && file_exists($qr_path)) {
                    echo "<p style='color: green;'>✓ QR generated successfully: {$qr_path}</p>";
                    echo "<img src='{$qr_path}' alt='QR Code' style='max-width: 200px;'>";
                } else {
                    echo "<p style='color: red;'>✗ QR generation failed</p>";
                }
                break;
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<script>";
echo "function generateQR(rifleId) {";
echo "    fetch('rifle_management.php', {";
echo "        method: 'POST',";
echo "        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },";
echo "        body: 'action=generate_single_qr&rifle_id=' + rifleId";
echo "    })";
echo "    .then(response => response.json())";
echo "    .then(data => {";
echo "        if (data.success) {";
echo "            alert('QR generated successfully!');";
echo "            location.reload();";
echo "        } else {";
echo "            alert('Error: ' + data.message);";
echo "        }";
echo "    });";
echo "}";
echo "</script>";
?>