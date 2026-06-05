<?php
session_start();
require_once 'includes/db_connection.php';

// Simple access control - you can modify this as needed
if (!isset($_SESSION['user_id'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Rifle Scanner</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .mode-selector {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .mode-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        .mode-btn.active {
            background-color: #007bff;
            color: white;
        }
        .mode-btn:not(.active) {
            background-color: #e9ecef;
            color: #6c757d;
        }
        .scanner-container {
            text-align: center;
            margin-bottom: 30px;
        }
        #reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border: 2px solid #ddd;
            border-radius: 10px;
        }
        .controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
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
        .btn-danger {
            background-color: #dc3545;
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
        .debug-panel {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        .debug-toggle {
            margin-top: 10px;
            padding: 5px 10px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-ready { background-color: #28a745; }
        .status-scanning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Simple Rifle Scanner</h1>
            <p>Scan QR codes to assign or return rifles</p>
        </div>

        <div class="mode-selector">
            <button class="mode-btn active" data-mode="assign">Assign Rifle</button>
            <button class="mode-btn" data-mode="return">Return Rifle</button>
        </div>

        <div class="scanner-container">
            <div id="reader"></div>
            <div class="controls">
                <button id="start-btn" class="btn btn-primary">
                    <span class="status-indicator status-ready"></span>
                    Start Scanner
                </button>
                <button id="stop-btn" class="btn btn-danger" style="display: none;">
                    <span class="status-indicator status-error"></span>
                    Stop Scanner
                </button>
            </div>
        </div>

        <div id="result-container" class="result-container">
            <h3 id="result-title"></h3>
            <div id="result-content"></div>
        </div>

        <button class="debug-toggle" onclick="toggleDebug()">Toggle Debug Panel</button>
        <div id="debug-panel" class="debug-panel"></div>
    </div>

    <!-- Include QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <!-- Include Crypto-JS for decryption -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>

    <script>
        // Global variables
        let html5QrCode;
        let isScanning = false;
        let currentMode = 'assign';
        
        // Encryption key - must match the QR generation key
        const RIFLE_ENCRYPTION_KEY = 'rifle-management-system-key-2024';
        
        // Debug logging
        function debugLog(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const debugPanel = document.getElementById('debug-panel');
            const logEntry = `[${timestamp}] [${type.toUpperCase()}] ${message}\n`;
            debugPanel.textContent += logEntry;
            debugPanel.scrollTop = debugPanel.scrollHeight;
            console.log(`[Rifle Scanner] ${logEntry}`);
        }
        
        function toggleDebug() {
            const debugPanel = document.getElementById('debug-panel');
            debugPanel.style.display = debugPanel.style.display === 'none' ? 'block' : 'none';
        }
        
        // Mode switching
        document.querySelectorAll('.mode-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentMode = this.dataset.mode;
                debugLog(`Mode switched to: ${currentMode}`);
                showResult('', `Mode changed to: ${currentMode.toUpperCase()}`, 'success');
            });
        });
        
        // Initialize scanner
        function initScanner() {
            html5QrCode = new Html5Qrcode("reader");
            debugLog('Scanner initialized');
        }
        
        // Start scanning
        function startScanning() {
            if (isScanning) return;
            
            const config = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0
            };
            
            html5QrCode.start(
                { facingMode: "environment" },
                config,
                onScanSuccess,
                onScanFailure
            ).then(() => {
                isScanning = true;
                updateScannerUI();
                debugLog('Scanner started successfully');
            }).catch(err => {
                debugLog(`Failed to start scanner: ${err}`, 'error');
                showResult('Error', `Failed to start scanner: ${err}`, 'error');
            });
        }
        
        // Stop scanning
        function stopScanning() {
            if (!isScanning) return;
            
            html5QrCode.stop().then(() => {
                isScanning = false;
                updateScannerUI();
                debugLog('Scanner stopped');
            }).catch(err => {
                debugLog(`Failed to stop scanner: ${err}`, 'error');
            });
        }
        
        // Update UI based on scanner state
        function updateScannerUI() {
            const startBtn = document.getElementById('start-btn');
            const stopBtn = document.getElementById('stop-btn');
            
            if (isScanning) {
                startBtn.style.display = 'none';
                stopBtn.style.display = 'inline-block';
                startBtn.querySelector('.status-indicator').className = 'status-indicator status-scanning';
            } else {
                startBtn.style.display = 'inline-block';
                stopBtn.style.display = 'none';
                startBtn.querySelector('.status-indicator').className = 'status-indicator status-ready';
            }
        }
        
        // Handle successful scan
        function onScanSuccess(decodedText, decodedResult) {
            debugLog(`Raw QR data: ${decodedText}`);
            
            try {
                // Try to decrypt the QR data
                const decryptedData = decryptQRData(decodedText);
                debugLog(`Decrypted data: ${JSON.stringify(decryptedData)}`);
                
                if (decryptedData && decryptedData.type === 'rifle') {
                    processRifleQR(decryptedData);
                } else {
                    throw new Error('Invalid rifle QR code format');
                }
            } catch (error) {
                debugLog(`QR processing error: ${error.message}`, 'error');
                showResult('Error', `Invalid QR code: ${error.message}`, 'error');
            }
        }
        
        // Handle scan failure
        function onScanFailure(error) {
            // Don't log every scan failure as it's normal
        }
        
        // Decrypt QR data
        function decryptQRData(encryptedData) {
            try {
                debugLog(`Attempting decryption with key: ${RIFLE_ENCRYPTION_KEY}`);
                
                const bytes = CryptoJS.AES.decrypt(encryptedData, RIFLE_ENCRYPTION_KEY);
                const decryptedText = bytes.toString(CryptoJS.enc.Utf8);
                
                if (!decryptedText) {
                    throw new Error('Decryption failed - empty result');
                }
                
                debugLog(`Decrypted text: ${decryptedText}`);
                return JSON.parse(decryptedText);
            } catch (error) {
                debugLog(`Decryption error: ${error.message}`, 'error');
                throw new Error(`Decryption failed: ${error.message}`);
            }
        }
        
        // Process rifle QR code
        function processRifleQR(data) {
            debugLog(`Processing rifle QR in ${currentMode} mode`);
            
            const rifleId = data.id || data.rifle_id;
            const rifleNumber = data.number || data.rifle_number;
            
            if (!rifleId) {
                throw new Error('Missing rifle ID in QR data');
            }
            
            // Send to server for processing
            fetch('rifle_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: currentMode,
                    rifle_id: rifleId,
                    rifle_number: rifleNumber,
                    qr_data: data
                })
            })
            .then(response => response.json())
            .then(result => {
                debugLog(`Server response: ${JSON.stringify(result)}`);
                
                if (result.success) {
                    showResult('Success', result.message, 'success');
                } else {
                    showResult('Error', result.message || 'Operation failed', 'error');
                }
            })
            .catch(error => {
                debugLog(`Server request error: ${error.message}`, 'error');
                showResult('Error', `Server error: ${error.message}`, 'error');
            });
        }
        
        // Show result
        function showResult(title, message, type) {
            const container = document.getElementById('result-container');
            const titleEl = document.getElementById('result-title');
            const contentEl = document.getElementById('result-content');
            
            titleEl.textContent = title;
            contentEl.textContent = message;
            
            container.className = `result-container result-${type}`;
            container.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                container.style.display = 'none';
            }, 5000);
        }
        
        // Event listeners
        document.getElementById('start-btn').addEventListener('click', startScanning);
        document.getElementById('stop-btn').addEventListener('click', stopScanning);
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initScanner();
            debugLog('Simple Rifle Scanner initialized');
        });
    </script>
</body>
</html>