<?php
// Test script to verify JSON responses are working correctly
ob_start();

// Suppress any potential warnings or notices that could interfere with JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_PARSE);

require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/rifle_functions.php';
require_once 'includes/rifle_qr_functions.php';

// Restore error reporting for development (but keep display_errors off)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Function to ensure clean JSON output
function sendCleanJsonResponse($data) {
    // Clean all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffer
    ob_start();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Output JSON and exit
    echo json_encode($data);
    exit;
}

// Test the JSON response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean any output buffer content before processing
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    switch ($_POST['action']) {
        case 'test_json':
            sendCleanJsonResponse([
                'success' => true,
                'message' => 'JSON response is working correctly',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'get_rifle_stats':
            // Test actual rifle stats
            try {
                $stats = getRifleStats();
                sendCleanJsonResponse([
                    'success' => true,
                    'data' => $stats
                ]);
            } catch (Exception $e) {
                sendCleanJsonResponse([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ]);
            }
            break;
            
        default:
            sendCleanJsonResponse([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
}

// If not a POST request, show a simple test form
?>
<!DOCTYPE html>
<html>
<head>
    <title>JSON Response Test</title>
    <script>
        function testJson() {
            fetch('test_json_fix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=test_json'
            })
            .then(response => response.text())
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON:', data);
                    document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    document.getElementById('result').innerHTML = '<div style="color: red;">JSON Parse Error: ' + e.message + '<br>Raw response: ' + text + '</div>';
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                document.getElementById('result').innerHTML = '<div style="color: red;">Fetch Error: ' + error.message + '</div>';
            });
        }
        
        function testRifleStats() {
            fetch('test_json_fix.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_rifle_stats'
            })
            .then(response => response.text())
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON:', data);
                    document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    document.getElementById('result').innerHTML = '<div style="color: red;">JSON Parse Error: ' + e.message + '<br>Raw response: ' + text + '</div>';
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                document.getElementById('result').innerHTML = '<div style="color: red;">Fetch Error: ' + error.message + '</div>';
            });
        }
    </script>
</head>
<body>
    <h1>JSON Response Test</h1>
    <button onclick="testJson()">Test JSON Response</button>
    <button onclick="testRifleStats()">Test Rifle Stats</button>
    <div id="result"></div>
</body>
</html>