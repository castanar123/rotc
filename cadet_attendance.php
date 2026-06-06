<?php
// Start session only if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    require_once 'includes/session.php';
}

// Always include database connection
require_once 'includes/db.php';

// Check if this is an AJAX request for JSON data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json');
    
    // Check if user is logged in and is cadet
    if (!isset($_SESSION['loggedin']) || !rotc_role_in(['cadet', 'basic_cadet', 'basic-cadet', 'basic'])) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Return JSON response for AJAX requests
    try {
        // Get cadet profile info
    $stmt = $pdo->prepare("SELECT id, student_id, CONCAT(first_name, ' ', last_name) as full_name, platoon FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile_data = $stmt->fetch();
    
    // If no cadet_profiles record found, use user_id as cadet_id (fallback for legacy data)
    if (!$profile_data) {
        $profile_data = [
            'id' => $_SESSION['user_id'], // Use user_id as cadet_id for attendance lookup
            'student_id' => $_SESSION['username'] ?? 'Unknown',
            'full_name' => $_SESSION['full_name'] ?? 'Unknown User',
            'platoon' => 'Unassigned'
        ];
    }
        
        // Check which attendance table exists and get statistics
        $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
        $use_attendance_logs = $table_check->rowCount() > 0;
        
        if ($use_attendance_logs) {
            // Use attendance_logs table structure
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
                FROM attendance_logs 
                WHERE cadet_profile_id = ?
            ");
            $stmt->execute([$profile_data['id']]);
            $stats = $stmt->fetch();
            
            // Get recent attendance records
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(event_date, DATE(created_at)) as date,
                    COALESCE(status, 'present') as status,
                    COALESCE(time_in, TIME(created_at)) as time_in,
                    NULL as time_out,
                    COALESCE(event_name, 'Training') as remarks
                FROM attendance_logs 
                WHERE cadet_profile_id = ?
                ORDER BY COALESCE(event_date, DATE(created_at)) DESC, created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$profile_data['id']]);
            $recent_attendance = $stmt->fetchAll();
        } else {
            // Use attendance table structure
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present_days,
                    COUNT(CASE WHEN status IN ('Absent', 'absent') THEN 1 END) as absent_days,
                    ROUND((COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
                FROM attendance 
                WHERE cadet_id = ?
            ");
            $stmt->execute([$profile_data['id']]);
            $stats = $stmt->fetch();
            
            // Get recent attendance records
            $stmt = $pdo->prepare("
                SELECT 
                    a.log_date as date,
                    a.status,
                    a.log_time as time_in,
                    NULL as time_out,
                    a.training_day as remarks
                FROM attendance a
                WHERE a.cadet_id = ?
                ORDER BY a.log_date DESC, a.created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$profile_data['id']]);
            $recent_attendance = $stmt->fetchAll();
        }
        
        // Ensure stats has default values if query returned null
        if (!$stats || $stats['total_days'] === null) {
            $stats = ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'attendance_rate' => 0];
        }
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'recent_attendance' => $recent_attendance ?: [],
            'profile' => [
                'student_id' => $profile_data['student_id'],
                'full_name' => $profile_data['full_name'],
                'platoon' => $profile_data['platoon'] ?? 'Unassigned'
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Cadet attendance AJAX error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch attendance data',
            'stats' => ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'attendance_rate' => 0],
            'recent_attendance' => []
        ]);
    }
    exit;
}

// Check if user is logged in and is cadet (for regular HTML page)
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['cadet', 'basic_cadet', 'basic-cadet', 'basic'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

// Get cadet's attendance records
$cadet_profile = null;
try {
    // Get cadet profile info
    $stmt = $pdo->prepare("SELECT id, student_id, CONCAT(first_name, ' ', last_name) as full_name, platoon FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile_data = $stmt->fetch();
    
    if (!$profile_data) {
        // Use user_id as cadet_id for users without cadet_profiles record (fallback for legacy data)
        $cadet_profile = [
            'id' => $_SESSION['user_id'], // Use user_id as cadet_id for attendance lookup
            'student_id' => $_SESSION['username'] ?? 'Unknown',
            'first_name' => $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown',
            'last_name' => 'User',
            'platoon' => 'Unassigned'
        ];
    } else {
        // Parse full name into first and last name
        $name_parts = explode(' ', $profile_data['full_name']);
        $cadet_profile = [
            'id' => $profile_data['id'],
            'student_id' => $profile_data['student_id'],
            'first_name' => $name_parts[0] ?? 'Unknown',
            'last_name' => isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : 'User',
            'platoon' => $profile_data['platoon'] ?? 'Unassigned'
        ];
    }
    
    // Check which attendance table exists and get statistics
    $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
    $use_attendance_logs = $table_check->rowCount() > 0;
    
    if ($use_attendance_logs && $cadet_profile['id']) {
        // Use attendance_logs table structure
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                ROUND((COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
            FROM attendance_logs 
            WHERE cadet_profile_id = ?
        ");
        $stmt->execute([$cadet_profile['id']]);
        $stats = $stmt->fetch();
        
        // Get recent attendance records
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(event_date, DATE(created_at)) as date,
                COALESCE(status, 'present') as status,
                COALESCE(time_in, TIME(created_at)) as time_in,
                NULL as time_out,
                COALESCE(event_name, 'Training') as remarks
            FROM attendance_logs 
            WHERE cadet_profile_id = ?
            ORDER BY COALESCE(event_date, DATE(created_at)) DESC, created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$cadet_profile['id']]);
        $recent_attendance = $stmt->fetchAll();
        
        // Get monthly attendance for current year
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(COALESCE(event_date, DATE(created_at))) as month,
                COUNT(*) as total_days,
                COUNT(CASE WHEN COALESCE(status, 'present') = 'present' THEN 1 END) as present_days
            FROM attendance_logs 
            WHERE cadet_profile_id = ? AND YEAR(COALESCE(event_date, DATE(created_at))) = YEAR(CURDATE())
            GROUP BY MONTH(COALESCE(event_date, DATE(created_at)))
            ORDER BY month
        ");
        $stmt->execute([$cadet_profile['id']]);
        $monthly_stats = $stmt->fetchAll();
    } else if ($cadet_profile['id']) {
        // Use attendance table structure
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present_days,
                COUNT(CASE WHEN status IN ('Absent', 'absent') THEN 1 END) as absent_days,
                ROUND((COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 2) as attendance_rate
            FROM attendance 
            WHERE cadet_id = ?
        ");
        $stmt->execute([$cadet_profile['id']]);
        $stats = $stmt->fetch();
        
        // Get recent attendance records
        $stmt = $pdo->prepare("
            SELECT 
                a.log_date as date,
                a.status,
                a.log_time as time_in,
                NULL as time_out,
                a.training_day as remarks,
                cp.first_name,
                cp.last_name
            FROM attendance a
            LEFT JOIN cadet_profiles cp ON a.cadet_id = cp.id
            WHERE cp.user_id = ?
            ORDER BY a.log_date DESC, a.created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recent_attendance = $stmt->fetchAll();
        
        // Get monthly attendance for current year
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(log_date) as month,
                COUNT(*) as total_days,
                COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present_days
            FROM attendance 
            WHERE cadet_id = ? AND YEAR(log_date) = YEAR(CURDATE())
            GROUP BY MONTH(log_date)
            ORDER BY month
        ");
        $stmt->execute([$cadet_profile['id']]);
        $monthly_stats = $stmt->fetchAll();
    } else {
        // No profile found, set empty stats
        $stats = ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'attendance_rate' => 0];
        $recent_attendance = [];
        $monthly_stats = [];
    }
    
    // Ensure stats has default values if query returned null
    if (!$stats || $stats['total_days'] === null) {
        $stats = ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'attendance_rate' => 0];
    }
    
} catch (Exception $e) {
    error_log("Cadet attendance error: " . $e->getMessage());
    
    // Ensure cadet_profile is initialized if not already set
    if (!$cadet_profile) {
        $cadet_profile = [
            'student_id' => $_SESSION['username'] ?? 'Unknown',
            'first_name' => $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown',
            'last_name' => 'User',
            'td' => 'N/A',
            'semester' => 'N/A',
            'platoon' => null
        ];
    }
    
    $stats = ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'attendance_rate' => 0];
    $recent_attendance = [];
    $monthly_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-medal"></i></div>
                    <span class="logo-text">My Attendance</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="cadet_dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="QR/scanner.html" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Check-in</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="cadet_attendance.php" class="nav-link active">
                             <i class="fas fa-calendar-check"></i>
                             <span>My Attendance</span>
                         </a>
                    </li>
                    <li class="nav-item">
                        <a href="grades/view_grades.php" class="nav-link">
                            <i class="fas fa-graduation-cap"></i>
                            <span>My Grades</span>
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
                        <h1 class="header-title">My Attendance Record</h1>
                        <p class="header-subtitle"><?php echo htmlspecialchars($cadet_profile['first_name'] . ' ' . $cadet_profile['last_name']); ?> - <?php echo htmlspecialchars($cadet_profile['platoon'] ?? 'Unassigned'); ?> Platoon</p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Days</span>
                        <i class="fas fa-calendar stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_days']; ?></div>
                    <div class="stat-change neutral">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Training Days</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Present</span>
                        <i class="fas fa-user-check stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['present_days']; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-check-circle"></i>
                        <span>Days Present</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Absent</span>
                        <i class="fas fa-user-times stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['absent_days']; ?></div>
                    <div class="stat-change <?php echo $stats['absent_days'] <= 3 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-<?php echo $stats['absent_days'] <= 3 ? 'check' : 'exclamation-triangle'; ?>"></i>
                        <span>Days Absent</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Attendance Rate</span>
                        <i class="fas fa-percentage stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['attendance_rate']; ?>%</div>
                    <div class="stat-change <?php echo $stats['attendance_rate'] >= 90 ? 'positive' : ($stats['attendance_rate'] >= 75 ? 'neutral' : 'negative'); ?>">
                        <i class="fas fa-<?php echo $stats['attendance_rate'] >= 90 ? 'arrow-up' : ($stats['attendance_rate'] >= 75 ? 'minus' : 'arrow-down'); ?>"></i>
                        <span><?php echo $stats['attendance_rate'] >= 90 ? 'Excellent' : ($stats['attendance_rate'] >= 75 ? 'Good' : 'Needs Improvement'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance Table -->
            <div class="qr-card fade-in">
                <h2 style="font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--text-accent); text-transform: uppercase; letter-spacing: 2px; margin-bottom: var(--spacing-lg); font-size: 1.5rem; text-align: center;">
                    <i class="fas fa-clock"></i> Recent Attendance Records
                </h2>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; background: rgba(15, 20, 25, 0.5); border-radius: var(--radius-md); overflow: hidden;">
                        <thead>
                            <tr style="background: var(--military-green);">
                                <th style="padding: var(--spacing-md); color: var(--text-primary); text-align: left; font-weight: 600;">Date</th>
                                <th style="padding: var(--spacing-md); color: var(--text-primary); text-align: left; font-weight: 600;">Status</th>
                                <th style="padding: var(--spacing-md); color: var(--text-primary); text-align: left; font-weight: 600;">Time In</th>
                                <th style="padding: var(--spacing-md); color: var(--text-primary); text-align: left; font-weight: 600;">Time Out</th>
                                <th style="padding: var(--spacing-md); color: var(--text-primary); text-align: left; font-weight: 600;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody style="color: var(--text-secondary);">
                            <?php if (empty($recent_attendance)): ?>
                                <tr>
                                    <td colspan="5" style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                                        <i class="fas fa-info-circle"></i> No attendance records found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_attendance as $record): ?>
                                    <tr style="border-bottom: 1px solid var(--border-primary);">
                                        <td style="padding: var(--spacing-md);"><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                        <td style="padding: var(--spacing-md);">
                                            <span class="status-badge <?php echo $record['status']; ?>">
                                                <i class="fas fa-<?php echo $record['status'] === 'present' ? 'check' : 'times'; ?>"></i>
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: var(--spacing-md);"><?php echo $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-'; ?></td>
                                        <td style="padding: var(--spacing-md);"><?php echo $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-'; ?></td>
                                        <td style="padding: var(--spacing-md);"><?php echo htmlspecialchars($record['remarks'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <style>
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .status-badge.present {
        background: rgba(40, 167, 69, 0.2);
        color: var(--military-green);
        border: 1px solid var(--military-green);
    }
    
    .status-badge.absent {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
        border: 1px solid #dc3545;
    }
    </style>

    <script src="js/dashboard-modern.js"></script>
</body>
</html>
