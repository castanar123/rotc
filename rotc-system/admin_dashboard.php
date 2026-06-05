<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Handle inline approval actions from the dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approval_action'], $_POST['user_id'])) {
    $approval_action = $_POST['approval_action'];
    $user_id_action = (int)$_POST['user_id'];
    try {
        $pdo->beginTransaction();
        if ($approval_action === 'approve') {
            // Approve user and activate cadet profile
            $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', status = 'active' WHERE id = ?");
            $stmt->execute([$user_id_action]);
            $stmt = $pdo->prepare("UPDATE cadet_profiles SET status = 'Active' WHERE user_id = ?");
            $stmt->execute([$user_id_action]);
        } elseif ($approval_action === 'reject') {
            // Reject user and set inactive
            $stmt = $pdo->prepare("UPDATE users SET approval_status = 'rejected', status = 'inactive' WHERE id = ?");
            $stmt->execute([$user_id_action]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Admin dashboard approval action failed: ' . $e->getMessage());
    }
}

// Get dashboard statistics
try {
    // Total users count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    
    // Active cadets count - require approved and active on users, and Active on cadet_profiles
    $stmt = $pdo->query("SELECT COUNT(*) as total
                         FROM users u
                         JOIN cadet_profiles cp ON cp.user_id = u.id
                         WHERE u.role IN ('cadet', 'basic-cadet')
                           AND u.approval_status = 'approved'
                           AND u.status = 'active'
                           AND cp.status = 'Active'");
    $active_cadets = $stmt->fetch()['total'];
    
    // Officers count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role IN ('officer', 'instructor')");
    $officers_count = $stmt->fetch()['total'];
    
    // Today's attendance
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as present FROM attendance_logs WHERE DATE(timestamp) = CURDATE()");
    $today_attendance = $stmt->fetch()['present'];
    
    // Attendance rate calculation
    $attendance_rate = $active_cadets > 0 ? round(($today_attendance / $active_cadets) * 100, 1) : 0;
    
    // Recent activities
    $stmt = $pdo->query("
        SELECT al.*, cp.first_name, cp.last_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        ORDER BY al.timestamp DESC 
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll();
    
    // Weekly attendance data for chart
    $stmt = $pdo->query("
        SELECT DATE(timestamp) as date, COUNT(DISTINCT user_id) as count 
        FROM attendance_logs 
        WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY DATE(timestamp) 
        ORDER BY date
    ");
    $weekly_attendance = $stmt->fetchAll();
    
    // Recent registrations
    $stmt = $pdo->query("
        SELECT * FROM users 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recent_registrations = $stmt->fetchAll();

    // Pending approvals (basic-cadets awaiting admin approval)
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.role, u.created_at, u.approval_status, u.status,
               cp.first_name, cp.last_name, cp.platoon
        FROM users u
        LEFT JOIN cadet_profiles cp ON cp.user_id = u.id
        WHERE u.role = 'basic-cadet' AND u.approval_status = 'pending'
        ORDER BY u.created_at ASC
        LIMIT 50
    ");
    $pending_approvals = $stmt->fetchAll();
    
    // Performance data
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN attendance_rate >= 90 THEN 'Excellent'
                WHEN attendance_rate >= 80 THEN 'Good'
                WHEN attendance_rate >= 70 THEN 'Average'
                ELSE 'Needs Improvement'
            END as performance,
            COUNT(*) as count
        FROM (
            SELECT user_id, 
                   (COUNT(*) * 100.0 / 30) as attendance_rate
            FROM attendance_logs 
            WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY user_id
        ) as user_attendance
        GROUP BY performance
    ");
    $performance_data = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $total_users = $active_cadets = $officers_count = $today_attendance = 0;
    $attendance_rate = 0;
    $recent_activities = $weekly_attendance = $recent_registrations = $performance_data = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Command Center - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-unified.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body data-role="admin">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                    <span class="logo-text">Admin Command</span>
                </div>

                <!-- Pending Approvals (lowest section) -->
                <div class="card" id="pending-approvals-bottom">
                    <div class="card-header">
                        <h3 class="card-title">Pending Approvals</h3>
                        <a href="user_management.php#registration-approvals" class="btn btn-outline">Open Management</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_approvals)): ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <p>No pending approvals.</p>
                            </div>
                        <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Platoon</th>
                                        <th>Registered</th>
                                        <th>Approval</th>
                                        <th>User Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_approvals as $pa): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr(($pa['first_name'] ?? 'U'), 0, 1) . substr(($pa['last_name'] ?? 'N'), 0, 1)); ?>
                                                </div>
                                                <span class="user-name"><?php echo htmlspecialchars(($pa['first_name'] ?? $pa['username']) . ' ' . ($pa['last_name'] ?? '')); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($pa['platoon'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($pa['created_at'])); ?></td>
                                        <td><span class="badge badge-warning"><?php echo htmlspecialchars($pa['approval_status']); ?></span></td>
                                        <td><span class="badge badge-<?php echo ($pa['status'] === 'active' ? 'success' : 'secondary'); ?>"><?php echo htmlspecialchars($pa['status']); ?></span></td>
                                        <td>
                                            <form method="post" style="display:inline-block">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$pa['id']; ?>">
                                                <input type="hidden" name="approval_action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="post" style="display:inline-block" onsubmit="return confirm('Reject this registration?');">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$pa['id']; ?>">
                                                <input type="hidden" name="approval_action" value="reject">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-title">MAIN</div>
                    <a href="admin_dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="attendance/dashboard.php" class="nav-link">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Attendance</span>
                    </a>
                    <a href="user_management.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-title">OPERATIONS</div>
                    <a href="reports/" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="announcements/" class="nav-link">
                        <i class="fas fa-bullhorn"></i>
                        <span>Announcements</span>
                    </a>
                    <a href="grades/" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Grades</span>
                    </a>
                    <a href="advance_rotc_management.php" class="nav-link">
                        <i class="fas fa-medal"></i>
                        <span>Advance ROTC</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-title">SYSTEM</div>
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle mobile-only" id="mobileSidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Admin Command Center</h1>
                </div>
                
                <div class="header-right">
                    <div class="search-container">
                        <input type="text" class="search-input" placeholder="Search..." id="globalSearch">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                    
                    <div class="user-menu">
                        <div class="user-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                            <span class="user-role">Administrator</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-content">
                        <h2>Welcome back, <span class="text-accent"><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'] ?? $_SESSION['username'])[0]); ?></span></h2>
                        <p>Command and control your ROTC operations from this central hub.</p>
                    </div>
                    <div class="welcome-actions">
                        <button class="btn btn-primary" onclick="openQRScanner()">
                            <i class="fas fa-qrcode"></i>
                            Quick Scan
                        </button>
                        <a href="register.php" class="btn btn-secondary">
                            <i class="fas fa-user-plus"></i>
                            Add User
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_users); ?></div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i>
                                <span>+5.2%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($active_cadets); ?></div>
                            <div class="stat-label">Active Cadets</div>
                            <div class="stat-trend positive">
                                <i class="fas fa-arrow-up"></i>
                                <span>+2.1%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-info">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($today_attendance); ?></div>
                            <div class="stat-label">Today's Attendance</div>
                            <div class="stat-trend <?php echo $attendance_rate >= 80 ? 'positive' : 'negative'; ?>">
                                <i class="fas fa-<?php echo $attendance_rate >= 80 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <span><?php echo $attendance_rate; ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-warning">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($officers_count); ?></div>
                            <div class="stat-label">Officers</div>
                            <div class="stat-trend neutral">
                                <i class="fas fa-minus"></i>
                                <span>0%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup System Monitoring -->
                <div class="card backup-monitoring-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-database"></i>
                            Database Backup System
                        </h3>
                        <div class="card-actions">
                            <button class="btn btn-primary" onclick="createManualBackup()">
                                <i class="fas fa-download"></i>
                                Manual Backup
                            </button>
                            <button class="btn btn-success" id="backup-service-toggle" onclick="toggleBackupService()">
                                <i class="fas fa-play"></i>
                                Start Service
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="backup-status-grid">
                            <div class="backup-status-item">
                                <div class="status-icon" id="service-status-icon">
                                    <i class="fas fa-circle text-danger"></i>
                                </div>
                                <div class="status-content">
                                    <div class="status-label">Service Status</div>
                                    <div class="status-value" id="service-status-text">Stopped</div>
                                </div>
                            </div>
                            <div class="backup-status-item">
                                <div class="status-icon">
                                    <i class="fas fa-clock text-info"></i>
                                </div>
                                <div class="status-content">
                                    <div class="status-label">Next Hourly</div>
                                    <div class="status-value" id="next-hourly">--:--</div>
                                </div>
                            </div>
                            <div class="backup-status-item">
                                <div class="status-icon">
                                    <i class="fas fa-calendar text-warning"></i>
                                </div>
                                <div class="status-content">
                                    <div class="status-label">Next Daily</div>
                                    <div class="status-value" id="next-daily">--:--</div>
                                </div>
                            </div>
                            <div class="backup-status-item">
                                <div class="status-icon">
                                    <i class="fas fa-heart text-success"></i>
                                </div>
                                <div class="status-content">
                                    <div class="status-label">Health</div>
                                    <div class="status-value" id="backup-health">Unknown</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="backup-counts">
                            <div class="count-item">
                                <span class="count-label">Hourly Backups:</span>
                                <span class="count-value" id="hourly-count">0</span>
                            </div>
                            <div class="count-item">
                                <span class="count-label">Daily Backups:</span>
                                <span class="count-value" id="daily-count">0</span>
                            </div>
                            <div class="count-item">
                                <span class="count-label">Manual Backups:</span>
                                <span class="count-value" id="manual-count">0</span>
                            </div>
                        </div>
                        
                        <div class="recent-backups">
                            <h4>Recent Backups</h4>
                            <div class="backup-list" id="recent-backup-list">
                                <div class="backup-item loading">Loading backup history...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Attendance Integration -->
                <div class="card-grid">
                    <div class="card qr-attendance-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-qrcode"></i>
                                QR Attendance System
                            </h3>
                            <div class="card-actions">
                                <button class="btn btn-primary" onclick="generateQRCode()">
                                    <i class="fas fa-plus"></i>
                                    Generate QR
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="qr-scanner-container">
                                <div id="qr-scanner" class="qr-scanner"></div>
                                <div class="scanner-controls">
                                    <button id="start-scan" class="btn btn-success">
                                        <i class="fas fa-camera"></i>
                                        Start Scanner
                                    </button>
                                    <button id="stop-scan" class="btn btn-danger" style="display: none;">
                                        <i class="fas fa-stop"></i>
                                        Stop Scanner
                                    </button>
                                </div>
                                <div id="scan-result" class="scan-result"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Weekly Attendance Trends</h3>
                            <div class="card-actions">
                                <button class="btn btn-outline" onclick="exportChart('attendanceChart')">
                                    <i class="fas fa-download"></i>
                                    Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities and Quick Actions -->
                <div class="card-grid">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Activities</h3>
                            <a href="audit_logs.php" class="btn btn-outline">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="activity-list">
                                <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-<?php echo $activity['action'] === 'login' ? 'sign-in-alt' : 'user-edit'; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                        </div>
                                        <div class="activity-description">
                                            <?php echo htmlspecialchars($activity['action']); ?> - <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="action-grid">
                                <a href="register.php" class="action-btn">
                                    <div class="action-icon bg-primary">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="action-content">
                                        <div class="action-title">Add User</div>
                                        <div class="action-description">Register new cadet or officer</div>
                                    </div>
                                </a>
                                
                                <a href="attendance/scan.php" class="action-btn">
                                    <div class="action-icon bg-success">
                                        <i class="fas fa-qrcode"></i>
                                    </div>
                                    <div class="action-content">
                                        <div class="action-title">QR Attendance</div>
                                        <div class="action-description">Scan QR codes for attendance</div>
                                    </div>
                                </a>
                                
                                <a href="reports/generate_report.php" class="action-btn">
                                    <div class="action-icon bg-info">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div class="action-content">
                                        <div class="action-title">Generate Report</div>
                                        <div class="action-description">Create attendance reports</div>
                                    </div>
                                </a>
                                
                                <a href="announcements/create.php" class="action-btn">
                                    <div class="action-icon bg-warning">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                    <div class="action-content">
                                        <div class="action-title">Announce</div>
                                        <div class="action-description">Send announcements</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Registrations -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Registrations</h3>
                        <a href="user_management.php" class="btn btn-outline">Manage All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Platoon</th>
                                        <th>Registered</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_registrations as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                </div>
                                                <span class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['platoon'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_profile.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary" title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- QR Scanner Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>QR Code Scanner</h3>
                <button class="modal-close" onclick="closeQRModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="modal-qr-scanner"></div>
                <div id="modal-scan-result"></div>
            </div>
        </div>
    </div>

    <script src="js/dashboard-unified.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            initializeCharts();
            initializeQRScanner();
        });

        // Dashboard initialization
        function initializeDashboard() {
            // Sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
            const sidebar = document.getElementById('sidebar');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }

            if (mobileSidebarToggle) {
                mobileSidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                });
            }

            // Load saved sidebar state
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
            }

            // Global search functionality
            const globalSearch = document.getElementById('globalSearch');
            if (globalSearch) {
                globalSearch.addEventListener('input', function(e) {
                    const query = e.target.value.toLowerCase();
                    // Implement search functionality here
                    console.log('Searching for:', query);
                });
            }
        }

        // Initialize charts
        function initializeCharts() {
            // Attendance Chart
            const attendanceCtx = document.getElementById('attendanceChart');
            if (attendanceCtx) {
                new Chart(attendanceCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_map(function($item) { return date('M j', strtotime($item['date'])); }, $weekly_attendance)); ?>,
                        datasets: [{
                            label: 'Daily Attendance',
                            data: <?php echo json_encode(array_column($weekly_attendance, 'count')); ?>,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#28a745',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#28a745',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)'
                                },
                                ticks: {
                                    color: '#9aa0a6'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#9aa0a6'
                                }
                            }
                        }
                    }
                });
            }
        }

        // QR Scanner functionality
        let qrScanner = null;
        let isScanning = false;

        function initializeQRScanner() {
            const startScanBtn = document.getElementById('start-scan');
            const stopScanBtn = document.getElementById('stop-scan');
            const scannerDiv = document.getElementById('qr-scanner');
            const scanResult = document.getElementById('scan-result');

            if (startScanBtn) {
                startScanBtn.addEventListener('click', function() {
                    startQRScanner();
                });
            }

            if (stopScanBtn) {
                stopScanBtn.addEventListener('click', function() {
                    stopQRScanner();
                });
            }
        }

        function startQRScanner() {
            if (isScanning) return;

            const scannerDiv = document.getElementById('qr-scanner');
            const startBtn = document.getElementById('start-scan');
            const stopBtn = document.getElementById('stop-scan');
            const scanResult = document.getElementById('scan-result');

            qrScanner = new Html5QrcodeScanner(
                'qr-scanner',
                { 
                    fps: 10, 
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                false
            );

            qrScanner.render(
                function(decodedText, decodedResult) {
                    // Handle successful scan
                    handleScanResult(decodedText, decodedResult);
                },
                function(error) {
                    // Handle scan error (can be ignored for continuous scanning)
                    console.warn('QR scan error:', error);
                }
            );

            isScanning = true;
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';
            scanResult.innerHTML = '<div class="scan-status">Scanner active - Point camera at QR code</div>';
        }

        function stopQRScanner() {
            if (!isScanning || !qrScanner) return;

            qrScanner.stop().then(() => {
                qrScanner.clear();
                qrScanner = null;
                isScanning = false;

                const startBtn = document.getElementById('start-scan');
                const stopBtn = document.getElementById('stop-scan');
                const scanResult = document.getElementById('scan-result');

                startBtn.style.display = 'inline-block';
                stopBtn.style.display = 'none';
                scanResult.innerHTML = '';
            }).catch(err => {
                console.error('Error stopping scanner:', err);
            });
        }

        function handleScanResult(decodedText, decodedResult) {
            console.log('QR Code scanned:', decodedText);
            
            const scanResult = document.getElementById('scan-result');
            scanResult.innerHTML = `
                <div class="scan-success">
                    <i class="fas fa-check-circle"></i>
                    <div class="scan-text">
                        <strong>Scan Successful!</strong><br>
                        Processing attendance for: ${decodedText}
                    </div>
                </div>
            `;

            // Process attendance
            processAttendance(decodedText);

            // Auto-stop scanner after successful scan
            setTimeout(() => {
                stopQRScanner();
            }, 2000);
        }

        function processAttendance(qrData) {
            // Send attendance data to server
            fetch('attendance/process_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    qr_data: qrData,
                    timestamp: new Date().toISOString()
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Attendance recorded successfully!', 'success');
                    // Refresh attendance statistics
                    refreshStats();
                } else {
                    showNotification('Error recording attendance: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error processing attendance:', error);
                showNotification('Error processing attendance', 'error');
            });
        }

        function generateQRCode() {
            // Redirect to QR generation page
            window.location.href = 'attendance/generate_qr.php';
        }

        function openQRScanner() {
            const modal = document.getElementById('qrModal');
            modal.style.display = 'block';
            
            // Initialize modal scanner
            setTimeout(() => {
                initializeModalScanner();
            }, 100);
        }

        function closeQRModal() {
            const modal = document.getElementById('qrModal');
            modal.style.display = 'none';
            
            // Stop modal scanner if running
            if (window.modalScanner) {
                window.modalScanner.stop();
                window.modalScanner = null;
            }
        }

        function initializeModalScanner() {
            if (window.modalScanner) return;

            window.modalScanner = new Html5QrcodeScanner(
                'modal-qr-scanner',
                { fps: 10, qrbox: 200 },
                false
            );

            window.modalScanner.render(
                function(decodedText, decodedResult) {
                    handleModalScanResult(decodedText, decodedResult);
                },
                function(error) {
                    console.warn('Modal QR scan error:', error);
                }
            );
        }

        function handleModalScanResult(decodedText, decodedResult) {
            const modalScanResult = document.getElementById('modal-scan-result');
            modalScanResult.innerHTML = `
                <div class="scan-success">
                    <i class="fas fa-check-circle"></i>
                    <div>Scan successful: ${decodedText}</div>
                </div>
            `;

            processAttendance(decodedText);

            setTimeout(() => {
                closeQRModal();
            }, 2000);
        }

        function exportChart(chartId) {
            const canvas = document.getElementById(chartId);
            if (canvas) {
                const url = canvas.toDataURL('image/png');
                const a = document.createElement('a');
                a.href = url;
                a.download = chartId + '_' + new Date().toISOString().split('T')[0] + '.png';
                a.click();
            }
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

            // Add to page
            document.body.appendChild(notification);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        function refreshStats() {
            // Refresh dashboard statistics
            fetch('api/dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update stat cards with new data
                    updateStatCards(data);
                })
                .catch(error => {
                    console.error('Error refreshing stats:', error);
                });
        }

        function updateStatCards(data) {
            // Update individual stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                const valueElement = card.querySelector('.stat-value');
                if (valueElement && data[index]) {
                    valueElement.textContent = data[index].value;
                }
            });
        }

        // Backup monitoring functions
        function checkBackupStatus() {
            fetch('backup_monitor.php?action=status')
                .then(response => response.json())
                .then(data => {
                    updateBackupStatus(data);
                })
                .catch(error => {
                    console.error('Error checking backup status:', error);
                    showNotification('Error checking backup status', 'error');
                });
        }

        function updateBackupStatus(data) {
            const statusElement = document.getElementById('backup-service-status');
            const statusText = document.getElementById('backup-status-text');
            const toggleBtn = document.getElementById('toggle-backup-service');
            const manualBtn = document.getElementById('manual-backup-btn');
            
            if (data.service_running) {
                statusElement.className = 'status-indicator status-running';
                statusText.textContent = 'Running';
                toggleBtn.textContent = 'Stop Service';
                toggleBtn.className = 'btn btn-danger btn-sm';
            } else {
                statusElement.className = 'status-indicator status-stopped';
                statusText.textContent = 'Stopped';
                toggleBtn.textContent = 'Start Service';
                toggleBtn.className = 'btn btn-success btn-sm';
            }
            
            // Update backup counts
            document.getElementById('total-backups').textContent = data.total_backups || 0;
            document.getElementById('today-backups').textContent = data.today_backups || 0;
            
            // Update recent backups
            const recentList = document.getElementById('recent-backups-list');
            recentList.innerHTML = '';
            
            if (data.recent_backups && data.recent_backups.length > 0) {
                data.recent_backups.forEach(backup => {
                    const li = document.createElement('li');
                    li.innerHTML = `
                        <span>${backup.filename}</span>
                        <small>${backup.date}</small>
                        <a href="download_backup.php?file=${encodeURIComponent(backup.filename)}" class="btn btn-sm btn-outline-primary">Download</a>
                    `;
                    recentList.appendChild(li);
                });
            } else {
                recentList.innerHTML = '<li>No recent backups found</li>';
            }
        }

        function toggleBackupService() {
            const statusText = document.getElementById('backup-status-text').textContent;
            const action = statusText === 'Running' ? 'stop' : 'start';
            
            fetch(`backup_monitor.php?action=${action}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`Backup service ${action}ed successfully`, 'success');
                        checkBackupStatus();
                    } else {
                        showNotification(`Error ${action}ing backup service: ${data.message}`, 'error');
                    }
                })
                .catch(error => {
                    console.error(`Error ${action}ing backup service:`, error);
                    showNotification(`Error ${action}ing backup service`, 'error');
                });
        }

        function createManualBackup() {
            const btn = document.getElementById('manual-backup-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            
            fetch('backup_monitor.php?action=manual_backup')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Manual backup created successfully', 'success');
                        checkBackupStatus();
                        
                        // Offer download
                        if (data.filename) {
                            setTimeout(() => {
                                const download = confirm('Backup created successfully. Download now?');
                                if (download) {
                                    window.location.href = `download_backup.php?file=${encodeURIComponent(data.filename)}`;
                                }
                            }, 1000);
                        }
                    } else {
                        showNotification(`Error creating backup: ${data.message}`, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error creating manual backup:', error);
                    showNotification('Error creating manual backup', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-download"></i> Create Manual Backup';
                });
        }

        // Initialize backup monitoring on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkBackupStatus();
            
            // Check backup status every 30 seconds
            setInterval(checkBackupStatus, 30000);
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('qrModal');
            if (event.target === modal) {
                closeQRModal();
            }
        }
    </script>
</body>
</html>