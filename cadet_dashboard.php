<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/term_enrollment.php';

// Check for registration success message
$registration_success = false;
if (isset($_GET['registration_success']) && $_GET['registration_success'] == '1') {
    $registration_success = true;
}

ensure_term_enrollment_schema();
$__terms = get_all_terms();
$__activeTerm = get_active_term();

// Check if user is logged in and is a cadet
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && !in_array($_SESSION['role'], ['cadet', 'basic_cadet']))) {
    // Allow access if user_id is set, even if role is not defined (for legacy compatibility)
    if (!isset($_SESSION['user_id'])) {
        header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
        exit;
    }
}

// Get cadet's profile information for QR generation
$cadet_profile = null;
if ($_SESSION['role'] === 'basic_cadet') {
    try {
        $stmt = $pdo->prepare("SELECT student_id, first_name, last_name FROM cadet_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cadet_profile = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Profile query error: " . $e->getMessage());
    }
}

// Get cadet profile ID with fallback
$cadet_profile_id = null;
if (isset($_SESSION['cadet_profile_id'])) {
    $cadet_profile_id = $_SESSION['cadet_profile_id'];
} else {
    // Fallback: get cadet_profile_id from cadet_profiles table using user_id
    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    if ($result) {
        $cadet_profile_id = $result['id'];
        $_SESSION['cadet_profile_id'] = $cadet_profile_id; // Store for future use
    }
}



// Get dashboard statistics
try {
    // My attendance count (using cadet_profile_id with robust table checking)
    if ($cadet_profile_id) {
        error_log("Fetching attendance stats for cadet_profile_id: $cadet_profile_id");
        
        // Check if attendance_logs table exists and has data
        $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
        $use_attendance_logs = $table_check->rowCount() > 0;
        
        if ($use_attendance_logs) {
            // Check if there's data in attendance_logs for this cadet
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE cadet_profile_id = ?");
            $stmt->execute([$cadet_profile_id]);
            $logs_count = $stmt->fetch()['count'];
            
            if ($logs_count > 0) {
                error_log("Using attendance_logs table with $logs_count records");
                // Use attendance_logs table structure
                $my_attendance = $logs_count;
                
                // This month's attendance
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as present 
                    FROM attendance_logs 
                    WHERE cadet_profile_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
                ");
                $stmt->execute([$cadet_profile_id]);
                $month_attendance = $stmt->fetch()['present'];
                
                // Recent attendance activities
                $stmt = $pdo->prepare("
                    SELECT CONCAT('Attendance: ', COALESCE(event_name, 'Training')) as action, created_at as timestamp 
                    FROM attendance_logs 
                    WHERE cadet_profile_id = ?
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                $stmt->execute([$cadet_profile_id]);
                $recent_activities = $stmt->fetchAll();
            } else {
                $use_attendance_logs = false; // Fall back to attendance table
            }
        }
        
        if (!$use_attendance_logs) {
            error_log("Using attendance table as fallback");
            // Use attendance table structure
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE cadet_id = ?");
            $stmt->execute([$cadet_profile_id]);
            $my_attendance = $stmt->fetch()['total'];
            error_log("Found $my_attendance attendance records in attendance table");
            
            // This month's attendance
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as present 
                FROM attendance 
                WHERE cadet_id = ? AND MONTH(log_date) = MONTH(CURDATE()) AND YEAR(log_date) = YEAR(CURDATE())
            ");
            $stmt->execute([$cadet_profile_id]);
            $month_attendance = $stmt->fetch()['present'];
            
            // Recent attendance activities
            $stmt = $pdo->prepare("
                SELECT CONCAT('Attendance: ', COALESCE(training_day, 'Training')) as action, timestamp 
                FROM attendance 
                WHERE cadet_id = ?
                ORDER BY timestamp DESC 
                LIMIT 10
            ");
            $stmt->execute([$cadet_profile_id]);
            $recent_activities = $stmt->fetchAll();
        }
        
        // My average grade (using cadet_profile_id) - check if grades table exists
        $table_check = $pdo->query("SHOW TABLES LIKE 'grades'");
        if ($table_check->rowCount() > 0) {
            $grade_check = $pdo->query("SHOW COLUMNS FROM grades LIKE 'grade'");
            $grade_info = $grade_check->fetch();
            
            if ($grade_info && (strpos($grade_info['Type'], 'decimal') !== false || strpos($grade_info['Type'], 'float') !== false || strpos($grade_info['Type'], 'int') !== false)) {
                // Numeric grade field
                $stmt = $pdo->prepare("
                    SELECT AVG(CAST(grade AS DECIMAL(5,2))) as avg_grade 
                    FROM grades 
                    WHERE cadet_id = ? AND grade IS NOT NULL AND grade != ''
                ");
                $stmt->execute([$cadet_profile_id]);
                $avg_grade_result = $stmt->fetch()['avg_grade'];
                $avg_grade = $avg_grade_result ? round($avg_grade_result, 1) : 0;
            } else {
                // Text grade field - convert common grades to numbers
                $stmt = $pdo->prepare("
                    SELECT AVG(
                        CASE 
                            WHEN UPPER(grade) = 'A' THEN 95
                            WHEN UPPER(grade) = 'B' THEN 85
                            WHEN UPPER(grade) = 'C' THEN 75
                            WHEN UPPER(grade) = 'D' THEN 65
                            WHEN UPPER(grade) = 'F' THEN 50
                            WHEN grade REGEXP '^[0-9]+$' THEN CAST(grade AS DECIMAL(5,2))
                            ELSE NULL
                        END
                    ) as avg_grade 
                    FROM grades 
                    WHERE cadet_id = ? AND grade IS NOT NULL AND grade != ''
                ");
                $stmt->execute([$cadet_profile_id]);
                $avg_grade_result = $stmt->fetch()['avg_grade'];
                $avg_grade = $avg_grade_result ? round($avg_grade_result, 1) : 0;
            }
        } else {
            // No grades table - simulate grade based on attendance
            if ($my_attendance > 0) {
                $total_training_days = 20; // Assume 20 training days per semester
                $attendance_percentage = min(1.0, $my_attendance / $total_training_days);
                $avg_grade = 85 + ($attendance_percentage * 15); // 85-100 range based on attendance
            } else {
                $avg_grade = 0;
            }
        }
    } else {
        $my_attendance = $month_attendance = 0;
        $avg_grade = 0;
        $recent_activities = [];
    }
    
    // Get upcoming events from announcements
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM announcements 
        WHERE expires_at > NOW()
    ");
    $stmt->execute();
    $upcoming_events = $stmt->fetch()['count'];
    
    // Get recent announcements for cadet dashboard
    $stmt = $pdo->prepare("
        SELECT title, content, created_at, priority 
        FROM announcements 
        WHERE expires_at > NOW() 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_announcements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $my_attendance = $month_attendance = $upcoming_events = 0;
    $avg_grade = 0;
    $recent_activities = [];
    $recent_announcements = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadet Portal - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎖️</text></svg>">
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
                    <div class="logo-icon"><i class="fas fa-medal"></i></div>
                    <span class="logo-text">Cadet Portal</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="cadet_dashboard.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="admin/missing_ids.php" class="nav-link">
                                <i class="fas fa-id-card-alt"></i>
                                <span>Manage Missing IDs</span>
                            </a>
                        <?php else: ?>
                            <a href="file_missing_id.php" class="nav-link">
                                <i class="fas fa-id-card-alt"></i>
                                <span>File Missing ID</span>
                            </a>
                        <?php endif; ?>
                    </li>

                    <li class="nav-item">
                        <a href="cadet_attendance_new.php" class="nav-link">
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
                        <h1 class="header-title">Cadet Portal</h1>
                        <p class="header-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?> - <?php echo htmlspecialchars($_SESSION['platoon'] ?? 'Unassigned'); ?> Platoon</p>
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
                        <?php if (($_SESSION['role'] ?? '') !== 'basic_cadet'): ?>
                            <button class="qr-integration-btn" onclick="window.location.href='QR/scanner.html'">
                                <i class="fas fa-qrcode"></i>
                                Quick Check-in
                            </button>
                        <?php endif; ?>
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
                        <span class="stat-title">Total Attendance</span>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $my_attendance; ?></div>
                    <!-- Debug: my_attendance = <?php echo var_export($my_attendance, true); ?> -->
                    <div class="stat-change positive">
                        <i class="fas fa-check-circle"></i>
                        <span>Days Present</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">This Month</span>
                        <i class="fas fa-calendar-alt stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $month_attendance; ?></div>
                    <!-- Debug: month_attendance = <?php echo var_export($month_attendance, true); ?> -->
                    <div class="stat-change <?php echo $month_attendance >= 15 ? 'positive' : 'neutral'; ?>">
                        <i class="fas fa-<?php echo $month_attendance >= 15 ? 'arrow-up' : 'clock'; ?>"></i>
                        <span>Days This Month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Average Grade</span>
                        <i class="fas fa-graduation-cap stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $avg_grade; ?>%</div>
                    <!-- Debug: avg_grade = <?php echo var_export($avg_grade, true); ?> -->
                    <div class="stat-change <?php echo $avg_grade >= 80 ? 'positive' : ($avg_grade >= 70 ? 'neutral' : 'negative'); ?>">
                        <i class="fas fa-<?php echo $avg_grade >= 80 ? 'arrow-up' : ($avg_grade >= 70 ? 'minus' : 'arrow-down'); ?>"></i>
                        <span><?php echo $avg_grade >= 80 ? 'Excellent' : ($avg_grade >= 70 ? 'Good' : 'Needs Improvement'); ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Upcoming Events</span>
                        <i class="fas fa-calendar-plus stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $upcoming_events; ?></div>
                    <!-- Debug: upcoming_events = <?php echo var_export($upcoming_events, true); ?> -->
                    <div class="stat-change neutral">
                        <i class="fas fa-clock"></i>
                        <span>Scheduled</span>
                    </div>
                </div>
            </div>

            <!-- ID Management Section -->
            <div class="qr-scanner-section fade-in">
                <div class="qr-scanner-header">
                    <h2 class="qr-scanner-title">ID Management System</h2>
                </div>
                <div class="qr-scanner-content">
                    <div class="qr-scanner-info">
                        <h3 style="color: var(--text-accent); margin-bottom: var(--spacing-md);">ID Management Services</h3>
                        <p>Access ID management services for filing missing ID requests and tracking your participation.</p>
                        <ul style="margin: var(--spacing-md) 0; padding-left: var(--spacing-lg);">
                            <li>File missing ID requests</li>
                            <li>Track your participation</li>
                        </ul>
                    </div>
                    <div class="qr-scanner-actions">
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <a href="admin/missing_ids.php" class="qr-action-btn">
                                <i class="fas fa-id-card"></i>
                                Manage Missing IDs
                            </a>
                        <?php else: ?>
                            <a href="file_missing_id.php" class="qr-action-btn">
                                <i class="fas fa-id-card"></i>
                                Fill Missing ID
                            </a>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid fade-in">
                <!-- Recent Activities -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">My Recent Activities</h3>

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
                                            Personal Activity
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

                <!-- Recent Announcements -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Announcements</h3>
                        <a href="announcements/view.php" class="qr-action-btn" style="padding: var(--spacing-sm) var(--spacing-md); font-size: 0.9rem;">
                             <i class="fas fa-external-link-alt"></i>
                             View All
                         </a>
                    </div>
                    <div class="activity-list">
                        <?php if (empty($recent_announcements)): ?>
                            <p style="color: var(--text-secondary); text-align: center; padding: var(--spacing-xl);">No announcements available.</p>
                        <?php else: ?>
                            <?php foreach ($recent_announcements as $announcement): ?>
                                <div class="activity-item" style="padding: var(--spacing-md); border-bottom: 1px solid var(--border-primary);">
                                    <div>
                                        <strong style="color: var(--text-accent);"><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                        <p style="color: var(--text-secondary); margin: var(--spacing-xs) 0 0 0; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?>
                                        </p>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: var(--spacing-xs);">
                                            <span class="stat-change <?php echo strtolower($announcement['priority']) === 'high' ? 'negative' : (strtolower($announcement['priority']) === 'medium' ? 'neutral' : 'positive'); ?>" style="font-size: 0.8rem;">
                                                <i class="fas fa-flag"></i>
                                                <?php echo ucfirst($announcement['priority']); ?> Priority
                                            </span>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;">
                                                <?php echo date('M j, H:i', strtotime($announcement['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
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
                        <?php if ($_SESSION['role'] !== 'basic_cadet'): ?>
                        <a href="QR/scanner.html" class="qr-action-btn">
                             <i class="fas fa-qrcode"></i>
                             Check-in Now
                         </a>
                        <?php endif; ?>
                        <a href="grades/view_grades.php" class="qr-action-btn secondary">
                            <i class="fas fa-graduation-cap"></i>
                            View My Grades
                        </a>
                        <a href="announcements/view.php" class="qr-action-btn secondary">
                            <i class="fas fa-bullhorn"></i>
                            View Announcements
                        </a>
                        <a href="my_profile.php" class="qr-action-btn secondary">
                            <i class="fas fa-user-edit"></i>
                            Update Profile
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