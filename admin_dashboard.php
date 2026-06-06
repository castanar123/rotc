<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/term_enrollment.php';

// Initialize SecurityLogger
$securityLogger = new SecurityLogger();

// Check if user is logged in and is admin
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['admin'])) {
    $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access admin dashboard', [], 'high');
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

// Log admin dashboard access
$securityLogger->logSecurityEvent($_SESSION['user_id'], 'ADMIN_ACCESS', 'Admin accessed dashboard', [], 'low');

// Check for registration success message
$registration_success = false;
if (isset($_GET['registration_success']) && $_GET['registration_success'] == '1') {
    $registration_success = true;
}

ensure_term_enrollment_schema();
$__terms = get_all_terms();
$__activeTerm = get_active_term();

// Handle AJAX requests for approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_approval') {
    header('Content-Type: application/json');
    
    try {
        $user_id = $_POST['user_id'];
        $status = $_POST['status'];
        
        if ($user_id === 'all' && $status === 'approved') {
            // Approve all pending registrations (use approval_status)
            $pendingIdsStmt = $pdo->prepare("SELECT id FROM users WHERE approval_status = 'pending'");
            $pendingIdsStmt->execute();
            $pendingIds = $pendingIdsStmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', status = 'active' WHERE approval_status = 'pending'");
            $stmt->execute();
            $affected = $stmt->rowCount();
            // Set cadet profiles to Active for newly approved users
            $stmtCp = $pdo->prepare("UPDATE cadet_profiles cp JOIN users u ON cp.user_id = u.id SET cp.status = 'Active' WHERE u.approval_status = 'approved' AND u.status = 'active'");
            $stmtCp->execute();

            if (!empty($pendingIds)) {
                foreach ($pendingIds as $__uid) {
                    try { enroll_user_into_current_term((int)$__uid, (int)($_SESSION['user_id'] ?? 0), 'registration_approval'); } catch (Throwable $e) {}
                }
            }
            
            // Log bulk approval action
            $securityLogger->logSecurityEvent($_SESSION['user_id'], 'BULK_APPROVAL', "Admin approved {$affected} pending registrations", [], 'medium');
            
            echo json_encode([
                'success' => true,
                'message' => "Successfully approved {$affected} pending registrations"
            ]);
        } else {
            // Update single user
            if ($status === 'approved') {
                $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', status = 'active' WHERE id = ? AND approval_status = 'pending'");
                $stmt->execute([$user_id]);
                // Update cadet profile status
                $stmtCp = $pdo->prepare("UPDATE cadet_profiles SET status = 'Active' WHERE user_id = ?");
                $stmtCp->execute([$user_id]);

                try { enroll_user_into_current_term((int)$user_id, (int)($_SESSION['user_id'] ?? 0), 'registration_approval'); } catch (Throwable $e) {}
            } else {
                $stmt = $pdo->prepare("UPDATE users SET approval_status = 'rejected', status = 'inactive' WHERE id = ? AND approval_status = 'pending'");
                $stmt->execute([$user_id]);
            }
            
            if ($stmt->rowCount() > 0) {
                $action_text = $status === 'approved' ? 'approved' : 'rejected';
                
                // Log individual approval/rejection action
                $securityLogger->logSecurityEvent($_SESSION['user_id'], 'USER_STATUS_CHANGE', "Admin {$action_text} registration for user ID: {$user_id}", [], 'medium');
                
                echo json_encode([
                    'success' => true,
                    'message' => "Registration {$action_text} successfully"
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found or already processed'
                ]);
            }
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Get dashboard statistics (term-aware where applicable)
try {
    $term = get_active_term();
    $dashSy = $term['school_year'] ?? '';
    $dashSem = $term['semester'] ?? '';
    // Debug: Check if we have any users at all
    $debug_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $debug_total = $debug_stmt->fetch()['total'];
    error_log("DEBUG: Total users in database: " . $debug_total);
    
    // Debug: Check roles
    $debug_roles = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $roles_debug = $debug_roles->fetchAll();
    foreach ($roles_debug as $role_info) {
        error_log("DEBUG: Role '{$role_info['role']}': {$role_info['count']} users");
    }
    
    // Total users count (registered users, 2cl, basic cadets) - TERM-AWARE when active term is set
    if ($dashSy !== '' && $dashSem !== '') {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ce.cadet_profile_id) AS total
            FROM cadet_enrollments ce
            JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
            JOIN users u ON cp.user_id = u.id
            WHERE ce.school_year = ?
              AND ce.semester = ?
              AND ce.enrollment_status = 'enrolled'
              AND u.role IN ('basic-cadet','basic_cadet','2cl','1cl')
              AND u.approval_status = 'approved'
              AND u.status = 'active'
              AND (cp.status IN ('Active','active') OR cp.status IS NULL)
        ");
        $stmt->execute([$dashSy, $dashSem]);
        $total_users = (int)($stmt->fetch()['total'] ?? 0);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role IN ('basic-cadet','basic_cadet','2cl','1cl') AND approval_status = 'approved' AND status = 'active'");
        $total_users = (int)($stmt->fetch()['total'] ?? 0);
    }
    
    // Total strength count (exclude 2cl) - TERM-AWARE when active term is set
    if ($dashSy !== '' && $dashSem !== '') {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ce.cadet_profile_id) AS total
            FROM cadet_enrollments ce
            JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
            JOIN users u ON cp.user_id = u.id
            WHERE ce.school_year = ?
              AND ce.semester = ?
              AND ce.enrollment_status = 'enrolled'
              AND u.role IN ('basic-cadet','basic_cadet','1cl')
              AND u.approval_status = 'approved'
              AND u.status = 'active'
              AND (cp.status IN ('Active','active') OR cp.status IS NULL)
        ");
        $stmt->execute([$dashSy, $dashSem]);
        $total_strength = (int)($stmt->fetch()['total'] ?? 0);
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM users u 
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.role IN ('basic-cadet','basic_cadet','1cl') 
            AND u.approval_status = 'approved'
            AND u.status = 'active'
            AND (cp.status IN ('Active','active') OR cp.status IS NULL)
        ");
        $total_strength = (int)($stmt->fetch()['total'] ?? 0);
    }
    
    // 2CL count (separate) - TERM-AWARE when active term is set
    if ($dashSy !== '' && $dashSem !== '') {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ce.cadet_profile_id) AS total
            FROM cadet_enrollments ce
            JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
            JOIN users u ON cp.user_id = u.id
            WHERE ce.school_year = ?
              AND ce.semester = ?
              AND ce.enrollment_status = 'enrolled'
              AND u.role = '2cl'
              AND u.approval_status = 'approved'
              AND u.status = 'active'
              AND (cp.status IN ('Active','active') OR cp.status IS NULL)
        ");
        $stmt->execute([$dashSy, $dashSem]);
        $cl2_count = (int)($stmt->fetch()['total'] ?? 0);
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM users u 
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.role = '2cl' 
            AND u.approval_status = 'approved'
            AND u.status = 'active'
            AND (cp.status IN ('Active','active') OR cp.status IS NULL)
        ");
        $cl2_count = (int)($stmt->fetch()['total'] ?? 0);
    }
    
    // Basic cadets count - TERM-AWARE when active term is set
    if ($dashSy !== '' && $dashSem !== '') {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ce.cadet_profile_id) AS total
            FROM cadet_enrollments ce
            JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
            JOIN users u ON cp.user_id = u.id
            WHERE ce.school_year = ?
              AND ce.semester = ?
              AND ce.enrollment_status = 'enrolled'
              AND u.role IN ('basic-cadet','basic_cadet')
              AND u.approval_status = 'approved'
              AND u.status = 'active'
              AND (cp.status IN ('Active','active') OR cp.status IS NULL)
        ");
        $stmt->execute([$dashSy, $dashSem]);
        $basic_cadets = (int)($stmt->fetch()['total'] ?? 0);
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM users u 
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.role IN ('basic-cadet','basic_cadet') 
            AND u.approval_status = 'approved'
            AND u.status = 'active'
            AND (cp.status IN ('Active','active') OR cp.status IS NULL)
        ");
        $basic_cadets = (int)($stmt->fetch()['total'] ?? 0);
    }
    
    // Debug output
    error_log("DEBUG: Dashboard counts - Total: $total_users, Basic: $basic_cadets, 2CL: $cl2_count, Strength: $total_strength");
    
    // Officers count (1cl and commandant) - only approved and active officers
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role IN ('1cl', 'commandant') 
        AND u.approval_status = 'approved'
        AND u.status = 'active'
    ");
    $officers_count = $stmt->fetch()['total'];
    
    // Command staff count (admin and commandant)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'commandant')");
    $command_staff = $stmt->fetch()['total'];
    
    // Pending registrations count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE approval_status = 'pending'");
    $pending_registrations = $stmt->fetch()['total'];
    
    // Advance ROTC applicants count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM advance_rotc_signups");
    $advance_rotc_count = $stmt->fetch()['total'];
    
    // Count active missing ID requests
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM missing_id_requests WHERE status = 'active' AND expiry_date > NOW()");
    $active_missing_ids = $stmt->fetch()['total'];
    
    // Get pending registrations with cadet profile details
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.role, u.created_at,
               cp.first_name, cp.last_name, cp.middle_name, cp.student_id, 
               cp.course, cp.section, cp.contact_number
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.approval_status = 'pending' 
        ORDER BY u.created_at DESC 
        LIMIT 10
    ");
    $pending_users = $stmt->fetchAll();
    
    // Today's attendance from QR system (attendance_records), filtered by active academic term if available
    if ($dashSy !== '' && $dashSem !== '') {
        $stmt = $pdo->prepare("\n            SELECT COUNT(DISTINCT ar.cadet_id) AS present\n            FROM attendance_records ar\n            WHERE ar.school_year = ?\n              AND ar.semester = ?\n              AND ar.event_date = CURDATE()\n        ");
        $stmt->execute([$dashSy, $dashSem]);
        $today_attendance = (int)($stmt->fetch()['present'] ?? 0);
    } else {
        // Fallback: any term
        $stmt = $pdo->query("\n            SELECT COUNT(DISTINCT ar.cadet_id) AS present\n            FROM attendance_records ar\n            WHERE ar.event_date = CURDATE()\n        ");
        $today_attendance = (int)($stmt->fetch()['present'] ?? 0);
    }
    
    // Total students denominator for attendance rate: only enrolled cadets in active term when available
    try {
        if ($dashSy !== '' && $dashSem !== '') {
            $stmt = $pdo->prepare("\n                SELECT COUNT(DISTINCT ce.cadet_profile_id) AS total\n                FROM cadet_enrollments ce\n                JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id\n                JOIN users u ON cp.user_id = u.id\n                WHERE ce.school_year = ?\n                  AND ce.semester = ?\n                  AND ce.enrollment_status = 'enrolled'\n                  AND u.approval_status = 'approved'\n                  AND u.status = 'active'\n            ");
            $stmt->execute([$dashSy, $dashSem]);
            $total_students = (int)($stmt->fetch()['total'] ?? 0);
        } else {
            $stmt = $pdo->query("\n                SELECT COUNT(*) AS total\n                FROM cadet_profiles cp\n                JOIN users u ON cp.user_id = u.id\n                WHERE u.approval_status = 'approved'\n                  AND u.status = 'active'\n                  AND (cp.status IN ('Active','active') OR cp.status IS NULL)\n            ");
            $total_students = (int)($stmt->fetch()['total'] ?? 0);
        }
    } catch (PDOException $e) {
        // Fallback to users with approved/active status if join fails
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE role IN ('basic-cadet','basic_cadet','2cl','1cl') AND approval_status='approved' AND status='active'");
        $total_students = (int)($stmt->fetch()['total'] ?? 0);
    }
    
    // Attendance rate calculation
    $attendance_rate = $total_students > 0 ? round(($today_attendance / $total_students) * 100, 1) : 0;
    
    // Recent activities (try audit_logs, fallback to attendance)
    try {
        $stmt = $pdo->query("
            SELECT al.*, CONCAT(cp.first_name, ' ', cp.last_name) as full_name,
                   u.username, cp.first_name, cp.last_name
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
            ORDER BY al.created_at DESC 
            LIMIT 10
        ");
        $recent_activities = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Fallback to attendance records with cadet_profiles
        try {
            $stmt = $pdo->query("
                SELECT a.timestamp as created_at, 
                       CONCAT(cp.first_name, ' ', cp.last_name) as full_name, 
                       'Attendance Scan' as action,
                       cp.first_name, cp.last_name
                FROM attendance a 
                LEFT JOIN cadet_profiles cp ON a.student_id = cp.student_id
                WHERE cp.first_name IS NOT NULL
                ORDER BY a.timestamp DESC 
                LIMIT 10
            ");
            $recent_activities = $stmt->fetchAll();
        } catch (PDOException $e2) {
            // Final fallback to user registrations
            $stmt = $pdo->query("
                SELECT u.created_at, 
                       CONCAT(cp.first_name, ' ', cp.last_name) as full_name, 
                       'User Registration' as action,
                       cp.first_name, cp.last_name
                FROM users u 
                LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
                WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY u.created_at DESC 
                LIMIT 10
            ");
            $recent_activities = $stmt->fetchAll();
        }
    }
    
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    error_log("Dashboard error stack trace: " . $e->getTraceAsString());
    
    // Set default values for all statistics
    $total_users = $total_strength = $cl2_count = $basic_cadets = $officers_count = $command_staff = 0;
    $today_attendance = $total_students = $advance_rotc_count = $pending_registrations = 0;
    $attendance_rate = 0;
    $recent_activities = [];
    $pending_users = [];
    
    // Log specific error for debugging
    $error_message = "Admin Dashboard Error: " . $e->getMessage() . " at " . date('Y-m-d H:i:s');
    error_log($error_message);
    
    // Optional: Store error in database for admin review
    try {
        $error_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, created_at) VALUES (?, 'dashboard_error', ?, NOW())");
        $error_stmt->execute([$_SESSION['user_id'] ?? 0, $error_message]);
    } catch (Exception $log_error) {
        // If logging fails, just continue
        error_log("Failed to log dashboard error to database: " . $log_error->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Command Center - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <!-- Fixed Sidebar Toggle Button -->
     <button class="sidebar-toggle-fixed" id="sidebarToggle">
         <i class="fas fa-bars"></i>
     </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            // Centralized Admin Navigation
            $NAV_BASE = '';
            include __DIR__ . '/includes/admin_nav.php';
        ?>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Command Center</h1>
                        <p class="header-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
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
                        <span class="stat-title">Total Users</span>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>Registered</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Basic Cadets</span>
                        <i class="fas fa-user-graduate stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $basic_cadets; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>Active</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">2CL Cadets</span>
                        <i class="fas fa-star stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $cl2_count; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-chevron-up"></i>
                        <span>Second Class</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Officers</span>
                        <i class="fas fa-medal stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $officers_count; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-shield-alt"></i>
                        <span>Leadership</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Command Staff</span>
                        <i class="fas fa-crown stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $command_staff; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-star"></i>
                        <span>Command</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Pending Registrations</span>
                        <i class="fas fa-user-clock stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $pending_registrations; ?></div>
                    <div class="stat-change <?php echo $pending_registrations > 0 ? 'warning' : 'positive'; ?>">
                        <i class="fas fa-<?php echo $pending_registrations > 0 ? 'clock' : 'check'; ?>"></i>
                        <span><?php echo $pending_registrations > 0 ? 'Awaiting Approval' : 'All Approved'; ?></span>
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
                        <span><?php echo $today_attendance; ?>/<?php echo $total_students; ?> Present</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Advance ROTC</span>
                        <i class="fas fa-star-of-life stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $advance_rotc_count; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-user-graduate"></i>
                        <span>Elite Applicants</span>
                    </div>
                </div>
            </div>

            <!-- QR System Integration Section -->
            <div class="qr-scanner-section fade-in">
                <div class="qr-scanner-header">
                    <h2 class="qr-scanner-title">QR Management System</h2>
                </div>
                <div class="qr-scanner-content">
                    <div class="qr-scanner-info">
                        <h3 style="color: var(--text-accent); margin-bottom: var(--spacing-md);">Integrated QR Management</h3>
                        <p>Our comprehensive QR system provides real-time tracking and secure management for attendance and rifle inventory in all ROTC activities.</p>
                        <ul style="margin: var(--spacing-md) 0; padding-left: var(--spacing-lg);">
                            <li>Real-time attendance scanning</li>
                            <li>Rifle inventory QR tracking</li>
                            <li>Encrypted QR codes for security</li>
                            <li>Instant verification and validation</li>
                            <li>Comprehensive reports and analytics</li>
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
                            Attendance Dashboard
                        </a>
                        <a href="QR/rifle_generator.html" class="qr-action-btn secondary">
                            <i class="fas fa-gun"></i>
                            Single Rifle QR
                        </a>
                        <a href="QR/rifle_batch_generator.html" class="qr-action-btn secondary">
                            <i class="fas fa-layer-group"></i>
                            Batch Rifle QR
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid fade-in">
                <!-- Recent Activities -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activities</h3>
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
                                        <?php 
                                        $timestamp = $activity['timestamp'] ?? $activity['created_at'] ?? '';
                                        echo $timestamp ? date('M j, H:i', strtotime($timestamp)) : 'N/A';
                                        ?>
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
                        <a href="user_management.php" class="qr-action-btn">
                            <i class="fas fa-user-plus"></i>
                            Manage Users
                        </a>
                        <a href="announcements/create.php" class="qr-action-btn secondary">
                            <i class="fas fa-bullhorn"></i>
                            Create Announcement
                        </a>
                        <a href="grades/manage_grades.php" class="qr-action-btn secondary">
                            <i class="fas fa-graduation-cap"></i>
                            Manage Grades
                        </a>
                        <a href="reports/generate_report.php" class="qr-action-btn secondary">
                            <i class="fas fa-file-alt"></i>
                            Generate Report
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Registration Approvals Section -->
            <?php if ($pending_registrations > 0): ?>
            <div class="registration-approvals-section fade-in" style="margin-top: var(--spacing-xl);">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-check"></i>
                            Pending Registration Approvals
                            <span class="badge" style="margin-left: var(--spacing-sm);"><?php echo $pending_registrations; ?></span>
                        </h3>
                        <div style="display: flex; gap: var(--spacing-sm);">
                            <button onclick="approveAllPending()" class="qr-action-btn" style="padding: var(--spacing-sm) var(--spacing-md); font-size: 0.9rem;">
                                <i class="fas fa-check-double"></i>
                                Approve All
                            </button>
                        </div>
                    </div>
                    <div class="pending-registrations-list" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($pending_users as $user): ?>
                            <div class="pending-user-item" style="padding: var(--spacing-md); border-bottom: 1px solid var(--border-primary); display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: var(--spacing-md);">
                                        <div>
                                            <strong style="color: var(--text-accent); font-size: 1.1rem;">
                                                <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') ?: $user['username']); ?>
                                            </strong>
                                            <?php if ($user['student_id']): ?>
                                                <span style="color: var(--text-secondary); margin-left: var(--spacing-sm); font-size: 0.9rem;">
                                                    (<?php echo htmlspecialchars($user['student_id']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="margin-top: var(--spacing-xs); color: var(--text-secondary); font-size: 0.9rem;">
                                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                                        <?php if ($user['course'] && $user['section']): ?>
                                            <span style="margin-left: var(--spacing-md);"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($user['course'] . ' - ' . $user['section']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($user['contact_number']): ?>
                                            <span style="margin-left: var(--spacing-md);"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['contact_number']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-top: var(--spacing-xs); color: var(--text-muted); font-size: 0.85rem;">
                                        <i class="fas fa-clock"></i> Registered: <?php echo date('M j, Y H:i', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: var(--spacing-sm);">
                                    <button onclick="approveUser(<?php echo $user['id']; ?>)" class="qr-action-btn" style="padding: var(--spacing-sm) var(--spacing-md); font-size: 0.85rem; background: var(--success);">
                                        <i class="fas fa-check"></i>
                                        Approve
                                    </button>
                                    <button onclick="rejectUser(<?php echo $user['id']; ?>)" class="qr-action-btn" style="padding: var(--spacing-sm) var(--spacing-md); font-size: 0.85rem; background: var(--danger);">
                                        <i class="fas fa-times"></i>
                                        Reject
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Manual Attendance Modal Functions - keeping only non-conflicting functionality

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

        // Manual Attendance Modal Functions
        function openManualAttendanceModal() {
            document.getElementById('manualAttendanceModal').style.display = 'block';
            loadStudentsForAttendance();
        }

        function closeManualAttendanceModal() {
            document.getElementById('manualAttendanceModal').style.display = 'none';
            document.getElementById('manualAttendanceForm').reset();
        }

        function loadStudentsForAttendance() {
            fetch('get_students.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    const select = document.getElementById('studentSelect');
                    select.innerHTML = '<option value="">Select a student...</option>';
                    
                    if (data.success && data.students && Array.isArray(data.students)) {
                        if (data.students.length === 0) {
                            select.innerHTML = '<option value="">No students found</option>';
                            return;
                        }
                        
                        data.students.forEach(student => {
                            const option = document.createElement('option');
                            option.value = student.student_id;
                            option.textContent = `${student.name} (${student.student_id})`;
                            select.appendChild(option);
                        });
                    } else {
                        select.innerHTML = '<option value="">Error loading students</option>';
                        console.error('Invalid data structure:', data);
                        alert(data.message || 'Error loading students data');
                    }
                })
                .catch(error => {
                    console.error('Error loading students:', error);
                    const select = document.getElementById('studentSelect');
                    select.innerHTML = '<option value="">Error loading students</option>';
                    alert('Error loading students. Please check console for details.');
                });
        }

        function submitManualAttendance() {
            const form = document.getElementById('manualAttendanceForm');
            const formData = new FormData(form);

            fetch('submit_manual_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Attendance recorded successfully!');
                    closeManualAttendanceModal();
                    // Refresh the page to update statistics
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error submitting attendance:', error);
                alert('Error submitting attendance. Please try again.');
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('manualAttendanceModal');
            if (event.target == modal) {
                closeManualAttendanceModal();
            }
        }
        
        // Registration Approval Functions
        function approveUser(userId) {
            if (confirm('Are you sure you want to approve this registration?')) {
                updateUserApproval(userId, 'approved');
            }
        }
        
        function rejectUser(userId) {
            if (confirm('Are you sure you want to reject this registration?')) {
                updateUserApproval(userId, 'rejected');
            }
        }
        
        function approveAllPending() {
            if (confirm('Are you sure you want to approve ALL pending registrations?')) {
                updateUserApproval('all', 'approved');
            }
        }
        
        function updateUserApproval(userId, status) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('status', status);
            formData.append('action', 'update_approval');
            
            fetch('admin_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showToast(data.message, 'success');
                    // Refresh the page to update the display
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Error updating approval status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating approval status', 'error');
            });
        }
        
        function showToast(message, type) {
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'}"></i>
                <span>${message}</span>
            `;
            
            // Add to page
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            container.appendChild(toast);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>

    <!-- Manual Attendance Modal -->
    <div id="manualAttendanceModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Manual Attendance Entry</h2>
                <span class="close" onclick="closeManualAttendanceModal()">&times;</span>
            </div>
            <form id="manualAttendanceForm" onsubmit="event.preventDefault(); submitManualAttendance();">
                <div class="form-group">
                    <label for="studentSelect">Student:</label>
                    <select id="studentSelect" name="student_id" required>
                        <option value="">Loading students...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="attendanceDate">Date:</label>
                    <input type="date" id="attendanceDate" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="tdSelect">Training Day:</label>
                    <select id="tdSelect" name="td" required>
                        <option value="">Select TD...</option>
                        <?php for($i = 1; $i <= 15; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?><?php echo ($i == 1) ? 'st' : (($i == 2) ? 'nd' : (($i == 3) ? 'rd' : 'th')); ?> TD</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="semesterSelect">Semester:</label>
                    <select id="semesterSelect" name="semester" required>
                        <option value="">Select Semester...</option>
                        <option value="1">1st Semester</option>
                        <option value="2">2nd Semester</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Notes (Optional):</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Additional notes about this attendance entry..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeManualAttendanceModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Attendance</button>
                </div>
            </form>
        </div>
    </div>

    <style>
    .manual-attendance-btn {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: 10px;
    }

    .manual-attendance-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
    }

    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .toast {
        padding: 12px 16px;
        border-radius: 8px;
        color: white;
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 250px;
        animation: slideIn 0.3s ease-out;
    }
    
    .toast.success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }
    
    .toast.error {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 0;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from { opacity: 0; transform: translateY(-50px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .modal-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        padding: 20px;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.3s ease;
    }

    .close:hover {
        color: #ccc;
    }

    .form-group {
        margin-bottom: 20px;
        padding: 0 20px;
    }

    .form-group:first-of-type {
        margin-top: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }

    .form-group select,
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
        box-sizing: border-box;
        color: #333;
        background-color: #fff;
    }

    .form-group select option {
        color: #333;
        background-color: #fff;
    }

    .form-group select:focus,
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    .form-actions {
        padding: 20px;
        border-top: 1px solid #e1e5e9;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,123,255,0.3);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }
    </style>
    
    <!-- Include mobile navigation -->
    <script src="js/mobile-navigation.js"></script>
    
    <script>
        // Handle scrolling to registration approvals section when URL contains hash
        document.addEventListener('DOMContentLoaded', function() {
            // Check if URL contains #registration-approvals
            if (window.location.hash === '#registration-approvals') {
                const registrationSection = document.getElementById('registration-approvals');
                if (registrationSection) {
                    // Smooth scroll to the section
                    registrationSection.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Add a highlight effect
                    registrationSection.style.transition = 'background-color 0.5s ease';
                    registrationSection.style.backgroundColor = 'rgba(0, 123, 255, 0.1)';
                    
                    // Remove highlight after 2 seconds
                    setTimeout(() => {
                        registrationSection.style.backgroundColor = '';
                    }, 2000);
                }
            }
        });
    </script>
</body>
</html>
