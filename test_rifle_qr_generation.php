<?php
// Suppress any warnings from phpqrcode library
ob_start();
error_reporting(0); // Suppress all errors and warnings
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// Include required files
require_once 'includes/db.php';
require_once 'libs/phpqrcode/qrlib.php';
require_once 'includes/rifle_qr_functions.php';

// Clean any buffered output
ob_clean();

// Function to send clean JSON response
function sendCleanJsonResponse($data) {
    // Clean any buffered output
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Set proper JSON headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Send JSON response
    echo json_encode($data);
    exit;
}

// Function to generate test rifle QR code
function generateTestRifleQR($rifle_id, $rifle_number, $type = 'M16A2') {
    $encryption_key = 'rifle-management-system-key-2024';
    
    // Create test rifle data
    $rifle_data = [
        'rifle_id' => $rifle_id,
        'rifle_number' => $rifle_number,
        'type' => $type,
        'status' => 'available',
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    // Convert to JSON
    $json_data = json_encode($rifle_data);
    
    // Encrypt using AES-256-CBC (same as rifle_qr_functions.php)
    $encrypted_data = openssl_encrypt($json_data, 'AES-256-CBC', $encryption_key, 0);
    
    return [
        'encrypted_data' => $encrypted_data,
        'original_data' => $rifle_data,
        'json_data' => $json_data
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_test_qr') {
        $rifle_id = $_POST['rifle_id'] ?? rand(1000, 9999);
        $rifle_number = $_POST['rifle_number'] ?? 'TEST-' . rand(100, 999);
        $type = $_POST['type'] ?? 'M16A2';
        
        try {
            $qr_result = generateTestRifleQR($rifle_id, $rifle_number, $type);
            
            // Check if QRcode class exists
            if (!class_exists('QRcode')) {
                throw new Exception('QRcode class not found. Please check phpqrcode library installation.');
            }
            
            // Generate QR code image with error suppression
            ob_start();
            // Suppress all warnings and notices from phpqrcode
            $old_error_reporting = error_reporting(0);
            QRcode::png($qr_result['encrypted_data'], false, QR_ECLEVEL_M, 8, 2);
            error_reporting($old_error_reporting);
            $qr_image = ob_get_contents();
            ob_end_clean();
            
            if (empty($qr_image)) {
                throw new Exception('Failed to generate QR code image.');
            }
            
            $qr_base64 = base64_encode($qr_image);
            
            sendCleanJsonResponse([
                'success' => true,
                'qr_image' => 'data:image/png;base64,' . $qr_base64,
                'encrypted_data' => $qr_result['encrypted_data'],
                'original_data' => $qr_result['original_data'],
                'json_data' => $qr_result['json_data']
            ]);
        } catch (Exception $e) {
            sendCleanJsonResponse([
                'success' => false,
                'error' => 'Failed to generate test QR: ' . $e->getMessage()
            ]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Rifle QR Generation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .qr-container {
            text-align: center;
            margin: 20px 0;
        }
        .qr-image {
            max-width: 300px;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .data-section {
            margin-top: 20px;
        }
        .data-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .data-content {
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-family: monospace;
            font-size: 14px;
            word-break: break-all;
        }
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Rifle QR Code Generator</h1>
        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Generate test QR codes using the same encryption method as the rifle management system
        </p>
        
        <form id="qrForm">
            <div class="form-group">
                <label for="rifle_id">Rifle ID:</label>
                <input type="number" id="rifle_id" name="rifle_id" placeholder="Enter rifle ID (or leave empty for random)">
            </div>
            
            <div class="form-group">
                <label for="rifle_number">Rifle Number:</label>
                <input type="text" id="rifle_number" name="rifle_number" placeholder="Enter rifle number (or leave empty for random)">
            </div>
            
            <div class="form-group">
                <label for="type">Rifle Type:</label>
                <select id="type" name="type">
                    <option value="M16A2">M16A2</option>
                    <option value="M4A1">M4A1</option>
                    <option value="M16A4">M16A4</option>
                    <option value="AR-15">AR-15</option>
                </select>
            </div>
            
            <button type="submit">Generate Test QR Code</button>
        </form>
        
        <div id="result" class="result" style="display: none;"></div>
    </div>

    <script>
        document.getElementById('qrForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'generate_test_qr');
            formData.append('rifle_id', document.getElementById('rifle_id').value);
            formData.append('rifle_number', document.getElementById('rifle_number').value);
            formData.append('type', document.getElementById('type').value);
            
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div style="text-align: center;">Generating QR code...</div>';
            resultDiv.style.display = 'block';
            
            fetch('test_rifle_qr_generation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="success">QR Code generated successfully!</div>
                        
                        <div class="qr-container">
                            <img src="${data.qr_image}" alt="Test Rifle QR Code" class="qr-image">
                        </div>
                        
                        <div class="data-section">
                            <div class="data-title">Encrypted Data (for scanning):</div>
                            <div class="data-content">${data.encrypted_data}</div>
                        </div>
                        
                        <div class="data-section">
                            <div class="data-title">Original Rifle Data:</div>
                            <div class="data-content">${JSON.stringify(data.original_data, null, 2)}</div>
                        </div>
                        
                        <div class="data-section">
                            <div class="data-title">JSON Data (before encryption):</div>
                            <div class="data-content">${data.json_data}</div>
                        </div>
                        
                        <div style="margin-top: 20px; padding: 15px; background-color: #e7f3ff; border-radius: 5px; border: 1px solid #b3d9ff;">
                            <strong>Testing Instructions:</strong><br>
                            1. Use the rifle scanner to scan the generated QR code above<br>
                            2. The scanner should decrypt and display the rifle information<br>
                            3. Check the browser console for any decryption errors
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="error">Error: ${data.error}</div>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="error">Network error: ${error.message}</div>`;
            });
        });
    </script>
</body>
</html>