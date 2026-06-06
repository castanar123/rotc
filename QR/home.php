<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

// Access control: Admin only
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['admin'])) {
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
    <title>QR Attendance System - ROTC Management</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <style>
        .qr-attendance-container {
            margin-left: 280px;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            padding: var(--spacing-xl);
        }
        
        .qr-header {
            background: rgba(15, 20, 25, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            text-align: center;
        }
        
        .qr-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-weight: 900;
            font-size: 2.5rem;
            color: var(--text-accent);
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: var(--spacing-sm);
            text-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
        }
        
        .qr-header p {
            color: var(--text-secondary);
            font-size: 1.2rem;
            font-weight: 400;
        }
        
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-xl);
            margin-top: var(--spacing-xl);
        }
        
        .qr-card {
            background: rgba(15, 20, 25, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            text-align: center;
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }
        
        .qr-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(40, 167, 69, 0.1), transparent);
            transition: left var(--transition-slow);
        }
        
        .qr-card:hover::before {
            left: 100%;
        }
        
        .qr-card:hover {
            transform: translateY(-10px);
            border-color: var(--military-green);
            box-shadow: var(--shadow-accent);
        }
        
        .qr-card-icon {
            font-size: 3rem;
            color: var(--military-green);
            margin-bottom: var(--spacing-lg);
            display: block;
        }
        
        .qr-card h2 {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            color: var(--text-accent);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: var(--spacing-md);
            font-size: 1.3rem;
        }
        
        .qr-card p {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
            line-height: 1.6;
        }
        
        .qr-btn {
            display: inline-block;
            background: linear-gradient(45deg, var(--military-green), var(--alpha-secondary));
            color: var(--text-primary);
            text-decoration: none;
            padding: var(--spacing-md) var(--spacing-xl);
            border-radius: var(--radius-md);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all var(--transition-normal);
            border: 1px solid var(--military-green);
            position: relative;
            overflow: hidden;
        }
        
        .qr-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left var(--transition-normal);
        }
        
        .qr-btn:hover::before {
            left: 100%;
        }
        
        .qr-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }
        
        .back-btn {
            position: fixed;
            top: var(--spacing-xl);
            right: var(--spacing-xl);
            background: rgba(15, 20, 25, 0.95);
            border: 1px solid var(--border-primary);
            color: var(--text-secondary);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all var(--transition-fast);
            z-index: 1000;
        }
        
        .back-btn:hover {
            background: var(--military-green);
            color: var(--text-primary);
            border-color: var(--military-green);
        }
    </style>
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
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
            <div class="qr-header">
                <h1>QR Attendance System</h1>
                <p>Streamlined attendance tracking for ROTC operations</p>
            </div>
            
            <div class="qr-grid">
                <div class="qr-card">
                    <i class="fas fa-qrcode qr-card-icon"></i>
                    <h2>Generate QR Code</h2>
                    <p>Create QR codes for attendance tracking sessions</p>
                    <a href="index.php" class="qr-btn">
                        <i class="fas fa-plus"></i>
                        Generate
                    </a>
                </div>
                
                <div class="qr-card">
                    <i class="fas fa-chart-bar qr-card-icon"></i>
                    <h2>View Dashboard</h2>
                    <p>Monitor real-time attendance data and analytics</p>
                    <a href="dashboard.php" class="qr-btn">
                        <i class="fas fa-eye"></i>
                        View
                    </a>
                </div>
                
                <div class="qr-card">
                    <i class="fas fa-cog qr-card-icon"></i>
                    <h2>System Setup</h2>
                    <p>Configure QR attendance system settings</p>
                    <a href="setup.php" class="qr-btn">
                        <i class="fas fa-wrench"></i>
                        Setup
                    </a>
                </div>
                
                <div class="qr-card">
                    <i class="fas fa-lock qr-card-icon"></i>
                    <h2>HTTPS Test</h2>
                    <p>Test and configure secure connections</p>
                    <a href="https_test.php" class="qr-btn">
                        <i class="fas fa-shield-alt"></i>
                        Test
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../js/dashboard.js"></script>
    <script src="../js/mobile-navigation.js"></script>
</body>
</html>
