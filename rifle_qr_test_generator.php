<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'libs/phpqrcode/qrlib.php';

// Suppress any warnings from phpqrcode library
error_reporting(0);
ini_set('display_errors', 0);

// Simple access control
if (!isset($_SESSION['user_id'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit();
}

// Function to send clean JSON response
function sendCleanJsonResponse($data) {
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Send response
    echo json_encode($data);
    exit;
}

// Function to encrypt data in CryptoJS compatible format
function encryptForCryptoJS($data, $passphrase) {
    // Generate a random salt (8 bytes)
    $salt = openssl_random_pseudo_bytes(8);
    
    // Derive key and IV using EVP_BytesToKey equivalent
    $key_iv = '';
    $d = $d_i = '';
    while (strlen($key_iv) < 48) { // 32 bytes key + 16 bytes IV
        $d_i = md5($d_i . $passphrase . $salt, true);
        $key_iv .= $d_i;
    }
    
    $key = substr($key_iv, 0, 32); // 256-bit key
    $iv = substr($key_iv, 32, 16); // 128-bit IV
    
    // Encrypt the data
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    
    // Create the CryptoJS format: "Salted__" + salt + encrypted data
    $result = "Salted__" . $salt . $encrypted;
    
    // Return base64 encoded result
    return base64_encode($result);
}

// Function to generate test rifle QR
function generateTestRifleQR($rifle_id, $rifle_number) {
    // Use the same encryption key as the scanner
    $encryption_key = 'rifle-management-system-key-2024';
    
    // Create rifle data
    $rifle_data = [
        'type' => 'rifle',
        'id' => $rifle_id,
        'number' => $rifle_number,
        'generated_at' => date('Y-m-d H:i:s'),
        'system' => 'test_generator'
    ];
    
    // Convert to JSON
    $json_data = json_encode($rifle_data);
    
    // Encrypt the data in CryptoJS compatible format
    $encrypted_data = encryptForCryptoJS($json_data, $encryption_key);
    
    // Generate QR code
    $qr_file = 'temp/test_rifle_qr_' . $rifle_id . '_' . time() . '.png';
    
    // Ensure temp directory exists
    if (!file_exists('temp')) {
        mkdir('temp', 0777, true);
    }
    
    // Suppress errors from QRcode library
    $old_error_reporting = error_reporting(0);
    ob_start();
    
    try {
        QRcode::png($encrypted_data, $qr_file, QR_ECLEVEL_L, 8, 2);
        ob_clean();
        error_reporting($old_error_reporting);
        
        return [
            'success' => true,
            'qr_file' => $qr_file,
            'encrypted_data' => $encrypted_data,
            'original_data' => $rifle_data
        ];
    } catch (Exception $e) {
        ob_clean();
        error_reporting($old_error_reporting);
        
        return [
            'success' => false,
            'error' => 'QR generation failed: ' . $e->getMessage()
        ];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $rifle_id = trim($_POST['rifle_id'] ?? '');
    $rifle_number = trim($_POST['rifle_number'] ?? '');
    
    if (!$rifle_id || !$rifle_number) {
        sendCleanJsonResponse([
            'success' => false,
            'error' => 'Both Rifle ID and Rifle Number are required'
        ]);
    }
    
    $result = generateTestRifleQR($rifle_id, $rifle_number);
    sendCleanJsonResponse($result);
}

// Get existing rifles for reference
$stmt = $pdo->prepare("SELECT * FROM rifles ORDER BY rifle_number");
$stmt->execute();
$existing_rifles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifle QR Test Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .nav-links {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav-links a {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .nav-links a:hover {
            background-color: #0056b3;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-primary {
            background-color: #28a745;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .result-container {
            margin-top: 30px;
            padding: 20px;
            border-radius: 5px;
            display: none;
        }
        .result-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .result-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .qr-display {
            text-align: center;
            margin: 20px 0;
        }
        .qr-display img {
            max-width: 300px;
            border: 2px solid #ddd;
            border-radius: 10px;
        }
        .data-display {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .table tr:hover {
            background-color: #f5f5f5;
        }
        .quick-select {
            margin-top: 10px;
        }
        .quick-select button {
            margin: 2px;
            padding: 5px 10px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .quick-select button:hover {
            background-color: #5a6268;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Rifle QR Test Generator</h1>
                <p>Generate encrypted QR codes for rifle testing</p>
            </div>
            
            <div class="nav-links">
                <a href="simple_rifle_scanner.php">QR Scanner</a>
                <a href="rifle_qr_test_generator.php">QR Generator (Current)</a>
                <a href="rifle_test_page.php">Test Page</a>
            </div>
            
            <div class="form-grid">
                <div>
                    <h3>Generate Test QR Code</h3>
                    <form id="qr-form">
                        <div class="form-group">
                            <label for="rifle_id">Rifle ID:</label>
                            <input type="text" id="rifle_id" name="rifle_id" required 
                                   placeholder="Enter rifle ID (e.g., 1, 2, 3)">
                        </div>
                        
                        <div class="form-group">
                            <label for="rifle_number">Rifle Number:</label>
                            <input type="text" id="rifle_number" name="rifle_number" required 
                                   placeholder="Enter rifle number (e.g., R001, R002)">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Generate QR Code</button>
                        
                        <div class="quick-select">
                            <strong>Quick Select from Database:</strong><br>
                            <?php foreach ($existing_rifles as $rifle): ?>
                                <button type="button" onclick="selectRifle('<?php echo $rifle['id']; ?>', '<?php echo htmlspecialchars($rifle['rifle_number']); ?>')">
                                    ID: <?php echo $rifle['id']; ?> - #<?php echo htmlspecialchars($rifle['rifle_number']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                    
                    <div id="loading" class="loading">
                        <div class="spinner"></div>
                        <p>Generating QR code...</p>
                    </div>
                </div>
                
                <div>
                    <h3>Encryption Info</h3>
                    <p><strong>Encryption Key:</strong> rifle-management-system-key-2024</p>
                    <p><strong>Algorithm:</strong> AES-256-CBC (CryptoJS Compatible)</p>
                    <p><strong>Data Format:</strong> JSON</p>
                    <p><strong>Format:</strong> Salted__ + Salt + Encrypted Data (Base64)</p>
                    
                    <h4>Sample Data Structure:</h4>
                    <div class="data-display">
{
  "type": "rifle",
  "id": "1",
  "number": "R001",
  "generated_at": "2024-01-20 10:30:00",
  "system": "test_generator"
}
                    </div>
                </div>
            </div>
            
            <div id="result-container" class="result-container">
                <h3 id="result-title"></h3>
                <div id="result-content"></div>
            </div>
        </div>
        
        <!-- Existing Rifles Reference -->
        <?php if (!empty($existing_rifles)): ?>
        <div class="card">
            <h3>Existing Rifles in Database</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rifle Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existing_rifles as $rifle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rifle['id']); ?></td>
                            <td>#<?php echo htmlspecialchars($rifle['rifle_number']); ?></td>
                            <td>
                                <button onclick="selectRifle('<?php echo $rifle['id']; ?>', '<?php echo htmlspecialchars($rifle['rifle_number']); ?>')" 
                                        class="btn" style="padding: 5px 10px; font-size: 12px;">Select</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function selectRifle(id, number) {
            document.getElementById('rifle_id').value = id;
            document.getElementById('rifle_number').value = number;
        }
        
        document.getElementById('qr-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('rifle_id', document.getElementById('rifle_id').value);
            formData.append('rifle_number', document.getElementById('rifle_number').value);
            
            // Show loading
            document.getElementById('loading').style.display = 'block';
            document.getElementById('result-container').style.display = 'none';
            
            fetch('rifle_qr_test_generator.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                
                const container = document.getElementById('result-container');
                const title = document.getElementById('result-title');
                const content = document.getElementById('result-content');
                
                if (data.success) {
                    title.textContent = 'QR Code Generated Successfully';
                    container.className = 'result-container result-success';
                    
                    content.innerHTML = `
                        <div class="qr-display">
                            <img src="${data.qr_file}" alt="Generated QR Code">
                        </div>
                        <h4>Encrypted Data:</h4>
                        <div class="data-display">${data.encrypted_data}</div>
                        <h4>Original Data:</h4>
                        <div class="data-display">${JSON.stringify(data.original_data, null, 2)}</div>
                    `;
                } else {
                    title.textContent = 'Error Generating QR Code';
                    container.className = 'result-container result-error';
                    content.innerHTML = `<p>${data.error}</p>`;
                }
                
                container.style.display = 'block';
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                
                const container = document.getElementById('result-container');
                const title = document.getElementById('result-title');
                const content = document.getElementById('result-content');
                
                title.textContent = 'Network Error';
                container.className = 'result-container result-error';
                content.innerHTML = `<p>Failed to generate QR code: ${error.message}</p>`;
                container.style.display = 'block';
            });
        });
    </script>
</body>
</html>