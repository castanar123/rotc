<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/term_enrollment.php';

// Check if user is logged in and is officer
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'officer') {
    SecurityLogger::log('UNAUTHORIZED_ACCESS', 'HIGH', 'Non-officer attempted to access officer dashboard', [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'anonymous',
        'role' => $_SESSION['role'] ?? 'none',
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Log successful officer dashboard access
SecurityLogger::log('OFFICER_ACCESS', 'LOW', 'Officer accessed dashboard', [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'platoon' => $_SESSION['platoon'],
    'ip_address' => $_SERVER['REMOTE_ADDR']
]);

// Check for registration success message
$registration_success = false;
if (isset($_GET['registration_success']) && $_GET['registration_success'] == '1') {
    $registration_success = true;
}

ensure_term_enrollment_schema();
$__terms = get_all_terms();
$__activeTerm = get_active_term();

// Get dashboard statistics
try {
    // Cadets under supervision
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role IN ('cadet', 'basic_cadet') AND platoon = ? AND status = 'active'");
    $stmt->execute([$_SESSION['platoon']]);
    $my_cadets = $stmt->fetch()['total'];
    
    // Today's attendance for my platoon
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT al.user_id) as present 
        FROM attendance_logs al 
        JOIN users u ON al.user_id = u.id 
        WHERE DATE(al.timestamp) = CURDATE() AND u.platoon = ?
    ");
    $stmt->execute([$_SESSION['platoon']]);
    $today_attendance = $stmt->fetch()['present'];
    
    // Attendance rate calculation
    $attendance_rate = $my_cadets > 0 ? round(($today_attendance / $my_cadets) * 100, 1) : 0;
    
    // Recent activities for my platoon
    $stmt = $pdo->prepare("
        SELECT al.*, u.first_name, u.last_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE u.platoon = ? OR al.user_id = ?
        ORDER BY al.timestamp DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['platoon'], $_SESSION['user_id']]);
    $recent_activities = $stmt->fetchAll();
    
    // Pending tasks count
    $pending_tasks = 3; // Placeholder
    
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $my_cadets = $today_attendance = $pending_tasks = 0;
    $attendance_rate = 0;
    $recent_activities = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Command Center - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                    <span class="logo-text">Officer Command</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="officer_dashboard.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="QR/scanner.html" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Scanner</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="attendance/dashboard.php" class="nav-link">
                            <i class="fas fa-chart-line"></i>
                            <span>Attendance Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="grades/manage_grades.php" class="nav-link">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Grades</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="announcements/view.php" class="nav-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my_profile.php" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Officer Command Center</h1>
                        <p class="header-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?> - <?php echo htmlspecialchars($_SESSION['platoon']); ?> Platoon</p>
                    </div>
                    <div class="header-actions">
                        <form method="POST" action="set_active_term.php" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                            <select name="term_key" onchange="this.form.submit()" style="background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.18); border-radius: 10px; padding: 10px 12px; min-width: 220px; outline: none;">
                                <?php foreach (($__terms ?? []) as $__t): $key = ($__t['school_year'] ?? '') . '|' . ($__t['semester'] ?? ''); $label = ($__t['school_year'] ?? '') . ' ' . ($__t['semester'] ?? ''); $selected = (($__activeTerm['school_year'] ?? '') === ($__t['school_year'] ?? '') && ($__activeTerm['semester'] ?? '') === ($__t['semester'] ?? '')) ? 'selected' : ''; ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $selected; ?> style="color:#111;"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="qr-integration-btn">Set Term</button></noscript>
                        </form>
                        <button class="qr-integration-btn" onclick="window.location.href='rifle_scanner.php'">
                            <i class="fas fa-qrcode"></i>
                            Quick QR Scan
                        </button>
                        <button class="manual-attendance-btn" onclick="openManualAttendanceModal()">
                            <i class="fas fa-edit"></i>
                            Manual Attendance
                        </button>
                    </div>
                </div>
            </div>

            <!-- Registration Success Message -->
            <?php if ($registration_success): ?>
            <div class="alert alert-success fade-in" style="margin-bottom: var(--spacing-lg); padding: var(--spacing-md); background: linear-gradient(135deg, #28a745, #20c997); color: white; border-radius: var(--border-radius); box-shadow: var(--shadow-md); display: flex; align-items: center; gap: var(--spacing-sm);">
                <i class="fas fa-check-circle" style="font-size: 1.2em;"></i>
                <div>
                    <strong>Registration Successful!</strong>
                    <p style="margin: 0; opacity: 0.9;">Welcome to the ROTC Management System. Your account has been created successfully and you are now logged in.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">My Cadets</span>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $my_cadets; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-shield-alt"></i>
                        <span><?php echo htmlspecialchars($_SESSION['platoon']); ?> Platoon</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Today's Attendance</span>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-change <?php echo $attendance_rate >= 80 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-<?php echo $attendance_rate >= 80 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                        <span><?php echo $today_attendance; ?>/<?php echo $my_cadets; ?> Present</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Pending Tasks</span>
                        <i class="fas fa-tasks stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $pending_tasks; ?></div>
                    <div class="stat-change neutral">
                        <i class="fas fa-clock"></i>
                        <span>Action Required</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Platoon Status</span>
                        <i class="fas fa-flag stat-icon"></i>
                    </div>
                    <div class="stat-value">Active</div>
                    <div class="stat-change positive">
                        <i class="fas fa-check-circle"></i>
                        <span>Operational</span>
                    </div>
                </div>
            </div>

            <!-- QR System Integration Section -->
            <div class="qr-scanner-section fade-in">
                <div class="qr-scanner-header">
                    <h2 class="qr-scanner-title">QR Attendance Management</h2>
                </div>
                <div class="qr-scanner-content">
                    <div class="qr-scanner-info">
                        <h3 style="color: var(--text-accent); margin-bottom: var(--spacing-md);">Platoon Attendance Control</h3>
                        <p>Manage attendance for your platoon with our integrated QR system. Track cadet participation and maintain accurate records.</p>
                        <ul style="margin: var(--spacing-md) 0; padding-left: var(--spacing-lg);">
                            <li>Scan QR codes for instant attendance</li>
                            <li>Monitor platoon attendance rates</li>
                            <li>Generate attendance reports</li>
                            <li>Track individual cadet progress</li>
                        </ul>
                    </div>
                    <div class="qr-scanner-actions">
                        <a href="rifle_scanner.php" class="qr-action-btn">
                            <i class="fas fa-camera"></i>
                            Launch QR Scanner
                        </a>
                        <a href="QR/index.html" class="qr-action-btn secondary">
                            <i class="fas fa-qrcode"></i>
                            Generate QR Codes
                        </a>
                        <a href="QR/dashboard.html" class="qr-action-btn secondary">
                            <i class="fas fa-chart-line"></i>
                            Platoon Reports
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid fade-in">
                <!-- Recent Activities -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Platoon Activities</h3>
                        <a href="reports/view_report.php" class="qr-action-btn" style="padding: var(--spacing-sm) var(--spacing-md); font-size: 0.9rem;">
                            <i class="fas fa-external-link-alt"></i>
                            View All
                        </a>
                    </div>
                    <div class="activity-list">
                        <?php if (empty($recent_activities)): ?>
                            <p style="color: var(--text-secondary); text-align: center; padding: var(--spacing-xl);">No recent activities found.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($recent_activities, 0, 5) as $activity): ?>
                                <div class="activity-item" style="padding: var(--spacing-md); border-bottom: 1px solid var(--border-primary); display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: var(--text-accent);"><?php echo htmlspecialchars($activity['action']); ?></strong>
                                        <p style="color: var(--text-secondary); margin: var(--spacing-xs) 0 0 0; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                        </p>
                                    </div>
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">
                                        <?php echo date('M j, H:i', strtotime($activity['timestamp'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="quick-actions" style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                        <a href="attendance/scan.php" class="qr-action-btn">
                            <i class="fas fa-qrcode"></i>
                            Take Attendance
                        </a>
                        <a href="grades/manage_grades.php" class="qr-action-btn secondary">
                            <i class="fas fa-graduation-cap"></i>
                            Manage Grades
                        </a>
                        <a href="announcements/create.php" class="qr-action-btn secondary">
                            <i class="fas fa-bullhorn"></i>
                            Create Announcement
                        </a>
                        <a href="reports/generate_report.php" class="qr-action-btn secondary">
                            <i class="fas fa-file-alt"></i>
                            Generate Report
                        </a>
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

        // Mobile sidebar toggle
        if (window.innerWidth <= 768) {
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('active');
            });
        }

        // Add fade-in animation to elements
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Initialize fade-in elements
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease-out';
        });
    </script>
</body>
</html>