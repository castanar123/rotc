<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

// Access control: Admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
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
    <title>System Setup - ROTC Management System</title>
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
                        <h1 class="header-title">System Setup</h1>
                        <p class="header-subtitle">Configure QR attendance system settings</p>
                    </div>
                </div>
            </div>

            <div class="content-area">
                <div class="setup-grid">
                    <div class="setup-card">
                        <div class="card-header">
                            <h3><i class="fas fa-database"></i> Database Configuration</h3>
                        </div>
                        <div class="card-body">
                            <p>Configure database connection settings for the QR attendance system.</p>
                            <button class="btn btn-primary">
                                <i class="fas fa-cog"></i>
                                Configure Database
                            </button>
                        </div>
                    </div>

                    <div class="setup-card">
                        <div class="card-header">
                            <h3><i class="fas fa-qrcode"></i> QR Code Settings</h3>
                        </div>
                        <div class="card-body">
                            <p>Customize QR code generation and validation parameters.</p>
                            <button class="btn btn-primary">
                                <i class="fas fa-edit"></i>
                                Edit QR Settings
                            </button>
                        </div>
                    </div>

                    <div class="setup-card">
                        <div class="card-header">
                            <h3><i class="fas fa-clock"></i> Session Management</h3>
                        </div>
                        <div class="card-body">
                            <p>Configure attendance session duration and timeout settings.</p>
                            <button class="btn btn-primary">
                                <i class="fas fa-clock"></i>
                                Manage Sessions
                            </button>
                        </div>
                    </div>

                    <div class="setup-card">
                        <div class="card-header">
                            <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
                        </div>
                        <div class="card-body">
                            <p>Configure security parameters and access controls.</p>
                            <button class="btn btn-primary">
                                <i class="fas fa-shield-alt"></i>
                                Security Config
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/dashboard.js"></script>
    <script src="../js/mobile-navigation.js"></script>
</body>
</html>