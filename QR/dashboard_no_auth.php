<?php
// Dashboard without authentication for testing
require_once '../includes/db.php';

// Skip session check - just set fake session for testing
$_SESSION['loggedin'] = true;
$_SESSION['role'] = 'admin';

// Pending registrations count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_registrations = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
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
                        <a href="home.php" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard_no_auth.php" class="nav-link active">
                            <i class="fas fa-chart-bar"></i>
                            <span>Attendance Dashboard (No Auth)</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Attendance Dashboard (Testing - No Auth Required)</h1>
                        <p class="header-subtitle">Real-time attendance monitoring and analytics</p>
                    </div>
                    <div class="header-actions">
                        <select id="td-selector" class="qr-integration-btn" style="background: rgba(15, 20, 25, 0.95); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 10px; border-radius: 8px; margin-right: 10px;">
                            <!-- Options will be populated by JavaScript -->
                        </select>
                        <select id="semester-selector" class="qr-integration-btn" style="background: rgba(15, 20, 25, 0.95); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 10px; border-radius: 8px; margin-right: 10px;">
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                        </select>
                        <input type="date" id="date-selector" class="qr-integration-btn" style="background: rgba(15, 20, 25, 0.95); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 10px; border-radius: 8px; margin-right: 10px;">
                        <button id="refresh-btn" class="qr-integration-btn">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div id="loading-state" class="loading" style="display: block;">
                <div class="loading-spinner"></div>
                <p>Loading attendance data...</p>
            </div>
            
            <!-- No Data State -->
            <div id="no-data-state" class="no-data" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <p>No attendance data found for the selected date.</p>
            </div>

            <!-- Dashboard Content -->
            <div id="dashboard-content" style="display: none;">
                <!-- Stats Grid -->
                <div class="stats-grid fade-in">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Total Strength</span>
                            <i class="fas fa-users stat-icon"></i>
                        </div>
                        <div class="stat-value" id="total-strength">0</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>Registered</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Present</span>
                            <i class="fas fa-user-check stat-icon"></i>
                        </div>
                        <div class="stat-value" id="total-present">0</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>Active</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Absent</span>
                            <i class="fas fa-user-times stat-icon"></i>
                        </div>
                        <div class="stat-value" id="total-absent">0</div>
                        <div class="stat-change negative">
                            <i class="fas fa-arrow-down"></i>
                            <span>Missing</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Attendance Rate</span>
                            <i class="fas fa-percentage stat-icon"></i>
                        </div>
                        <div class="stat-value" id="attendance-rate">0%</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>Rate</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="../js/dashboard.js"></script>
    <script src="dashboard.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard loaded - no auth version');
        });
    </script>
</body>
</html>