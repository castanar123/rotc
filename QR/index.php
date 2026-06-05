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
    <title>Student Attendance QR Generator</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Include qrcode.js library for generating QR codes -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- Include crypto-js for encryption -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <!-- Include system configuration -->
    <script src="config.js"></script>
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
         <i class="fas fa-bars"></i>
     </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                    <span class="logo-text">Admin Command</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../admin_dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="home.php" class="nav-link active">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Attendance Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../rifle_management.php" class="nav-link">
                            <i class="fas fa-crosshairs"></i>
                            <span>Rifle Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../rifle_scanner.php" class="nav-link">
                            <i class="fas fa-search"></i>
                            <span>QR Scanner</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../user_management.php" class="nav-link">
                            <i class="fas fa-users-cog"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../file_missing_id.php" class="nav-link">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Missing IDs</span>
                            <span class="badge badge-danger">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/registration_approvals.php" class="nav-link">
                            <i class="fas fa-user-check"></i>
                            <span>Registration Approvals</span>
                            <?php if ($pending_registrations > 0): ?>
                                <span class="badge badge-warning"><?php echo $pending_registrations; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../advance_rotc_management.php" class="nav-link">
                            <i class="fas fa-user-graduate"></i>
                            <span>Advance Officer Respondents</span>
                            <span class="badge badge-success">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../reports/view_report.php" class="nav-link">
                            <i class="fas fa-chart-line"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../announcements/view.php" class="nav-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../grades/manage_grades.php" class="nav-link">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Grades</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="setup.php" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>System Setup</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="https_test.php" class="nav-link">
                            <i class="fas fa-lock"></i>
                            <span>HTTPS Setup</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../settings.php" class="nav-link">
                            <i class="fas fa-cogs"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>
        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-qrcode"></i> Student Attendance QR Generator</h1>
            </div>
        
            <div class="content-body">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-plus-circle"></i> Generate Student QR Code</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="student-id">Student ID:</label>
                            <input type="text" id="student-id" placeholder="Enter student ID (e.g., 20230001)">
                        </div>
                        
                        <div class="form-group">
                            <label for="student-name">Student Name:</label>
                            <input type="text" id="student-name" placeholder="Enter student name">
                        </div>
                        
                        <div class="form-group">
                            <label for="valid-until">Valid Until:</label>
                            <input type="date" id="valid-until">
                        </div>
                        
                        <div class="form-group">
                            <label for="secret-key">Secret Key (for encryption):</label>
                            <input type="text" id="secret-key" placeholder="System managed encryption key" readonly>
                            <small style="display: block; margin-top: 5px; color: #666;">The encryption key is managed by the system for security</small>
                        </div>
                        
                        <button id="generate-btn" class="btn btn-primary">
                            <i class="fas fa-qrcode"></i> Generate QR Code
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-download"></i> Generated QR Code</h2>
                    </div>
                    <div class="card-body">
                        <div id="qrcode"></div>
                        <div id="qr-data"></div>
                        <button id="download-btn" class="btn btn-success" style="display: none;">
                            <i class="fas fa-download"></i> Download QR Code
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
    </script>
    <script src="script.js"></script>
</body>
</html>