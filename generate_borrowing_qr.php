<?php
/**
 * Generate dummy QR codes for rifle borrowing
 */

require_once 'includes/db.php';
require_once 'libs/phpqrcode/qrlib.php';
require_once 'includes/encryption.php';

/**
 * Generate QR code for borrowing
 * @param string $qr_code_id The QR code identifier
 * @param string $description Description for the QR code
 * @return string|false The QR code file path or false on failure
 */
function generateBorrowingQR($qr_code_id, $description) {
    // Create QR data for borrowing
    $qr_data = array(
        'type' => 'borrowing',
        'qr_code_id' => $qr_code_id,
        'description' => $description,
        'generated_at' => date('Y-m-d H:i:s'),
        'system' => 'rotc_rifle_borrowing'
    );
    
    // Convert to JSON and encrypt
    $json_data = json_encode($qr_data);
    $encryption_key = 'rifle-management-system-key-2024';
    
    // Encrypt using CryptoJS-compatible format
    $encrypted_data = encryptForCryptoJS($json_data, $encryption_key);
    
    // Define QR code path
    $qr_directory = 'uploads/borrowing_qrcodes/';
    $qr_filename = $qr_code_id . '.png';
    $qr_code_path = $qr_directory . $qr_filename;
    
    // Ensure directory exists
    if (!is_dir($qr_directory)) {
        mkdir($qr_directory, 0755, true);
    }
    
    try {
        // Generate QR code with medium error correction
        QRcode::png($encrypted_data, $qr_code_path, QR_ECLEVEL_M, 16, 4);
        
        return $qr_code_path;
    } catch (Exception $e) {
        error_log("Borrowing QR generation failed: " . $e->getMessage());
        return false;
    }
}

try {
    echo "<h2>Generating Borrowing QR Codes</h2>";
    
    // Get dummy QR codes from database
    $stmt = $pdo->query("SELECT * FROM dummy_qr_codes WHERE is_active = 1");
    $dummy_qr_codes = $stmt->fetchAll();
    
    if (empty($dummy_qr_codes)) {
        echo "<p style='color: red;'>No dummy QR codes found in database. Please run the migration first.</p>";
        exit;
    }
    
    $generated_count = 0;
    $errors = [];
    
    foreach ($dummy_qr_codes as $qr_code) {
        echo "<h3>Generating QR Code: {$qr_code['qr_code_id']}</h3>";
        
        $qr_path = generateBorrowingQR($qr_code['qr_code_id'], $qr_code['description']);
        
        if ($qr_path) {
            // Update database with QR code path
            $update_stmt = $pdo->prepare("UPDATE dummy_qr_codes SET qr_code_path = ? WHERE id = ?");
            $update_stmt->execute([$qr_path, $qr_code['id']]);
            
            echo "<p style='color: green;'>✓ Generated: {$qr_path}</p>";
            
            // Display the QR code
            if (file_exists($qr_path)) {
                echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0; display: inline-block;'>";
                echo "<h4>{$qr_code['description']}</h4>";
                echo "<img src='{$qr_path}' alt='QR Code {$qr_code['qr_code_id']}' style='max-width: 200px;'>";
                echo "<p><strong>QR ID:</strong> {$qr_code['qr_code_id']}</p>";
                echo "<p><strong>File:</strong> {$qr_path}</p>";
                echo "</div>";
            }
            
            $generated_count++;
        } else {
            $error_msg = "Failed to generate QR code for {$qr_code['qr_code_id']}";
            $errors[] = $error_msg;
            echo "<p style='color: red;'>✗ {$error_msg}</p>";
        }
    }
    
    echo "<h3 style='color: blue;'>Generation Summary</h3>";
    echo "<p>Successfully generated: {$generated_count} QR codes</p>";
    
    if (!empty($errors)) {
        echo "<p style='color: red;'>Errors: " . count($errors) . "</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li style='color: red;'>{$error}</li>";
        }
        echo "</ul>";
    }
    
    // Show updated dummy QR codes table
    echo "<h3>Updated Dummy QR Codes:</h3>";
    $stmt = $pdo->query("SELECT * FROM dummy_qr_codes");
    $updated_qr_codes = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>QR Code ID</th><th>Description</th><th>QR Path</th><th>Active</th><th>Created</th></tr>";
    foreach ($updated_qr_codes as $qr) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($qr['id']) . "</td>";
        echo "<td>" . htmlspecialchars($qr['qr_code_id']) . "</td>";
        echo "<td>" . htmlspecialchars($qr['description']) . "</td>";
        echo "<td>" . htmlspecialchars($qr['qr_code_path'] ?? 'Not generated') . "</td>";
        echo "<td>" . ($qr['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>" . htmlspecialchars($qr['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3 style='color: green;'>✓ Borrowing QR codes generation completed!</h3>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}

table {
    background-color: white;
    margin: 10px 0;
}

th, td {
    padding: 8px;
    text-align: left;
}

th {
    background-color: #f0f0f0;
}

.qr-display {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin: 20px 0;
}

.qr-card {
    border: 1px solid #ddd;
    padding: 15px;
    background-color: white;
    border-radius: 5px;
    text-align: center;
}
</style>