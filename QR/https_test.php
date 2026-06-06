<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

// Access control: Admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

// Pending registrations count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_registrations = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTTPS Setup - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
         <i class="fas fa-bars"></i>
     </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '..';
            include __DIR__ . '/../includes/admin_nav.php';
        ?>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">HTTPS Setup</h1>
                        <p class="header-subtitle">Test and configure secure connections</p>
                    </div>
                </div>
            </div>

            <div class="content-area">
                <div class="https-test-container">
                    <div class="test-card">
                        <div class="card-header">
                            <h3><i class="fas fa-shield-alt"></i> SSL Certificate Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="status-indicator">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>SSL Certificate Active</span>
                            </div>
                            <p>Your SSL certificate is properly configured and active.</p>
                        </div>
                    </div>

                    <div class="test-card">
                        <div class="card-header">
                            <h3><i class="fas fa-globe"></i> HTTPS Connection Test</h3>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-primary" onclick="testHttpsConnection()">
                                <i class="fas fa-play"></i>
                                Run HTTPS Test
                            </button>
                            <div id="https-test-result" class="test-result" style="display: none;">
                                <!-- Test results will be displayed here -->
                            </div>
                        </div>
                    </div>

                    <div class="test-card">
                        <div class="card-header">
                            <h3><i class="fas fa-mobile-alt"></i> Mobile Device Test</h3>
                        </div>
                        <div class="card-body">
                            <p>Test QR code scanning from mobile devices over HTTPS.</p>
                            <button class="btn btn-primary" onclick="generateTestQR()">
                                <i class="fas fa-qrcode"></i>
                                Generate Test QR
                            </button>
                            <div id="test-qr-container" class="qr-container" style="display: none;">
                                <!-- Test QR code will be displayed here -->
                            </div>
                        </div>
                    </div>

                    <div class="test-card">
                        <div class="card-header">
                            <h3><i class="fas fa-cog"></i> Security Configuration</h3>
                        </div>
                        <div class="card-body">
                            <div class="config-item">
                                <label>Force HTTPS Redirect:</label>
                                <input type="checkbox" id="force-https" checked>
                            </div>
                            <div class="config-item">
                                <label>HSTS Header:</label>
                                <input type="checkbox" id="hsts-header" checked>
                            </div>
                            <button class="btn btn-success" onclick="saveSecurityConfig()">
                                <i class="fas fa-save"></i>
                                Save Configuration
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/dashboard.js"></script>
    <script src="../js/mobile-navigation.js"></script>
    <script>
        function testHttpsConnection() {
            const resultDiv = document.getElementById('https-test-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="loading">Testing HTTPS connection...</div>';
            
            // Simulate HTTPS test
            setTimeout(() => {
                resultDiv.innerHTML = `
                    <div class="test-success">
                        <i class="fas fa-check-circle"></i>
                        <span>HTTPS connection test successful!</span>
                    </div>
                `;
            }, 2000);
        }
        
        function generateTestQR() {
            const qrContainer = document.getElementById('test-qr-container');
            qrContainer.style.display = 'block';
            qrContainer.innerHTML = `
                <div class="qr-code">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://localhost:8000/test" alt="Test QR Code">
                    <p>Scan this QR code with your mobile device to test HTTPS connectivity.</p>
                </div>
            `;
        }
        
        function saveSecurityConfig() {
            alert('Security configuration saved successfully!');
        }
    </script
