<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Check if user is logged in and is cadet
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['cadet', 'basic_cadet'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Get cadet's profile information
try {
    $stmt = $pdo->prepare("SELECT * FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cadet_profile = $stmt->fetch();
    
    // Get cadet's attendance statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            COUNT(CASE WHEN DATE(timestamp) = CURDATE() THEN 1 END) as today_present
        FROM attendance_logs 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $attendance_stats = $stmt->fetch();
    
    // Get attendance rate for current month
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT DATE(timestamp)) as present_days
        FROM attendance_logs 
        WHERE user_id = ? AND MONTH(timestamp) = MONTH(CURDATE()) AND YEAR(timestamp) = YEAR(CURDATE())
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $monthly_attendance = $stmt->fetch()['present_days'];
    
    // Calculate working days in current month (approximate)
    $working_days = date('t') - (date('t') - date('j')) - floor(date('t') / 7) * 2;
    $attendance_rate = $working_days > 0 ? round(($monthly_attendance / $working_days) * 100, 1) : 0;
    
    // Get cadet's grades
    $stmt = $pdo->prepare("
        SELECT g.*, s.subject_name 
        FROM grades g 
        LEFT JOIN subjects s ON g.subject_id = s.id 
        WHERE g.user_id = ? 
        ORDER BY g.date_recorded DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $grades = $stmt->fetchAll();
    
    // Calculate GPA
    $total_points = 0;
    $total_credits = 0;
    foreach ($grades as $grade) {
        $total_points += $grade['grade'];
        $total_credits++;
    }
    $gpa = $total_credits > 0 ? round($total_points / $total_credits, 2) : 0;
    
    // Get recent announcements
    $stmt = $pdo->query("
        SELECT * FROM announcements 
        WHERE status = 'active' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $announcements = $stmt->fetchAll();
    
    // Get upcoming training events
    $stmt = $pdo->query("
        SELECT * FROM training_events 
        WHERE event_date >= CURDATE() 
        ORDER BY event_date ASC 
        LIMIT 5
    ");
    $upcoming_events = $stmt->fetchAll();
    
    // Get weekly attendance data for chart
    $stmt = $pdo->prepare("
        SELECT DATE(timestamp) as date, COUNT(*) as count 
        FROM attendance_logs 
        WHERE user_id = ? AND timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY DATE(timestamp) 
        ORDER BY date
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $weekly_attendance = $stmt->fetchAll();
    
    // Get grade distribution for chart
    $grade_distribution = [
        'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0
    ];
    foreach ($grades as $grade) {
        if ($grade['grade'] >= 90) $grade_distribution['A']++;
        elseif ($grade['grade'] >= 80) $grade_distribution['B']++;
        elseif ($grade['grade'] >= 70) $grade_distribution['C']++;
        elseif ($grade['grade'] >= 60) $grade_distribution['D']++;
        else $grade_distribution['F']++;
    }
    
    // Get recent activities
    $stmt = $pdo->prepare("
        SELECT * FROM audit_logs 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_activities = $stmt->fetchAll();
    
    // Get rank/position in platoon (if applicable)
    $platoon_rank = 'N/A';
    if ($cadet_profile && $cadet_profile['platoon']) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) + 1 as rank
            FROM (
                SELECT u.id, AVG(g.grade) as avg_grade
                FROM users u
                LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
                LEFT JOIN grades g ON u.id = g.user_id
                WHERE cp.platoon = ? AND u.id != ?
                GROUP BY u.id
                HAVING avg_grade > ?
            ) as rankings
        ");
        $stmt->execute([$cadet_profile['platoon'], $_SESSION['user_id'], $gpa]);
        $rank_result = $stmt->fetch();
        $platoon_rank = $rank_result ? $rank_result['rank'] : 1;
    }
    
} catch (PDOException $e) {
    error_log("Cadet dashboard query error: " . $e->getMessage());
    $cadet_profile = null;
    $attendance_stats = ['total_days' => 0, 'today_present' => 0];
    $monthly_attendance = $attendance_rate = $gpa = 0;
    $grades = $announcements = $upcoming_events = $weekly_attendance = $recent_activities = [];
    $grade_distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
    $platoon_rank = 'N/A';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadet Dashboard - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-unified.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="js/dashboard-unified.js?v=20250921"></script>
</head>
<body data-role="cadet">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-user-graduate"></i></div>
                    <span>Cadet Portal</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-menu">
                    <!-- Navigation will be generated by JavaScript -->
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Cadet Portal</h1>
                    <?php if ($cadet_profile && $cadet_profile['platoon']): ?>
                        <span class="badge badge-primary" style="margin-left: 1rem;">Platoon: <?php echo htmlspecialchars($cadet_profile['platoon']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="header-center">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search activities..." class="search-input">
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                        <button class="action-btn" title="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="badge">1</span>
                        </button>
                        <div class="user-menu">
                            <div class="user-avatar">
                                <i class="fas fa-user-graduate"></i>
                                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Welcome Message -->
                <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-2xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?>!</h2>
                                <p class="opacity-90">Ready to excel in your ROTC journey today?</p>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-bold"><?php echo date('j'); ?></div>
                                <div class="text-sm opacity-75"><?php echo date('M Y'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card fade-in">
                        <div class="stat-header">
                            <div class="stat-title">Current GPA</div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $gpa; ?></div>
                        <div class="stat-change <?php echo $gpa >= 3.0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $gpa >= 3.0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <span><?php echo $gpa >= 3.0 ? 'Excellent' : 'Needs Improvement'; ?></span>
                        </div>
                    </div>

                    <div class="stat-card fade-in">
                        <div class="stat-header">
                            <div class="stat-title">Attendance Rate</div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-change <?php echo $attendance_rate >= 80 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $attendance_rate >= 80 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <span><?php echo $monthly_attendance; ?> days this month</span>
                        </div>
                    </div>

                    <div class="stat-card fade-in">
                        <div class="stat-header">
                            <div class="stat-title">Platoon Rank</div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="fas fa-trophy"></i>
                            </div>
                        </div>
                        <div class="stat-value">#<?php echo $platoon_rank; ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-star"></i>
                            <span>In your platoon</span>
                        </div>
                    </div>

                    <div class="stat-card fade-in">
                        <div class="stat-header">
                            <div class="stat-title">Total Grades</div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo count($grades); ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>Recorded grades</span>
                        </div>
                    </div>
                </div>

                <!-- Charts and Progress -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Attendance Trends -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">My Attendance Trends</h3>
                            <div class="flex gap-2">
                                <button class="btn btn-outline" onclick="exportChart('attendanceChart')">Export</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Grade Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Grade Distribution</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="gradeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions and Recent Grades -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-2 gap-3">
                                <a href="scanner.php" class="btn btn-primary">
                                    <i class="fas fa-qrcode"></i>
                                    Scan QR Code
                                </a>
                                <a href="my_profile.php" class="btn btn-success">
                                    <i class="fas fa-user-edit"></i>
                                    Update Profile
                                </a>

                                <a href="schedule.php" class="btn btn-secondary">
                                    <i class="fas fa-calendar"></i>
                                    My Schedule
                                </a>
                            </div>
                            
                            <!-- QR Scanner Widget -->
                            <div class="mt-4 p-4 bg-green-50 rounded-lg">
                                <h4 class="font-semibold mb-2 text-green-800">Quick Attendance Check-in</h4>
                                <div id="qr-scanner" style="width: 100%; max-width: 300px; margin: 0 auto;"></div>
                                <button id="start-scan" class="btn btn-success w-full mt-2">
                                    <i class="fas fa-camera"></i>
                                    Start Scanner
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Grades -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Grades</h3>
                            <a href="grades/view_grades.php" class="btn btn-outline">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($grades)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-graduation-cap text-gray-400 text-4xl mb-2"></i>
                                    <p class="text-gray-500">No grades recorded yet</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach (array_slice($grades, 0, 5) as $grade): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($grade['subject_name'] ?? 'Unknown Subject'); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($grade['date_recorded'])); ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="badge badge-<?php echo $grade['grade'] >= 80 ? 'success' : ($grade['grade'] >= 70 ? 'warning' : 'danger'); ?>">
                                                <?php echo $grade['grade']; ?>%
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Announcements and Upcoming Events -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Recent Announcements -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Announcements</h3>
                            <a href="announcements/view.php" class="btn btn-outline">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($announcements)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-bullhorn text-gray-400 text-4xl mb-2"></i>
                                    <p class="text-gray-500">No recent announcements</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($announcements as $announcement): ?>
                                    <div class="p-3 border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                                        <div class="flex items-start justify-between mb-2">
                                            <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($announcement['priority']); ?></span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . '...'; ?></p>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upcoming Training Events -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Training</h3>
                            <a href="training/schedule.php" class="btn btn-outline">View Schedule</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcoming_events)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-dumbbell text-gray-400 text-4xl mb-2"></i>
                                    <p class="text-gray-500">No upcoming training events</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($upcoming_events as $event): ?>
                                    <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                                        <div class="flex items-start justify-between mb-2">
                                            <h4 class="font-semibold text-blue-900"><?php echo htmlspecialchars($event['title']); ?></h4>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($event['type']); ?></span>
                                        </div>
                                        <p class="text-sm text-blue-700 mb-2"><?php echo htmlspecialchars($event['description']); ?></p>
                                        <div class="flex items-center gap-4 text-xs text-blue-600">
                                            <div class="flex items-center gap-1">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo date('M j, Y', strtotime($event['event_date'])); ?></span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo date('g:i A', strtotime($event['start_time'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracking -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">My Progress Overview</h3>
                        <div class="flex gap-2">
                            <button class="btn btn-outline" onclick="window.print()">Print Report</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Academic Progress -->
                            <div class="text-center">
                                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-book text-blue-600 text-2xl"></i>
                                </div>
                                <h4 class="font-semibold mb-2">Academic Performance</h4>
                                <div class="text-3xl font-bold text-blue-600 mb-1"><?php echo $gpa; ?></div>
                                <div class="text-sm text-gray-500">Current GPA</div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(($gpa / 4) * 100, 100); ?>%"></div>
                                </div>
                            </div>

                            <!-- Attendance Progress -->
                            <div class="text-center">
                                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-calendar-check text-green-600 text-2xl"></i>
                                </div>
                                <h4 class="font-semibold mb-2">Attendance Rate</h4>
                                <div class="text-3xl font-bold text-green-600 mb-1"><?php echo $attendance_rate; ?>%</div>
                                <div class="text-sm text-gray-500">This Month</div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $attendance_rate; ?>%"></div>
                                </div>
                            </div>

                            <!-- Overall Progress -->
                            <div class="text-center">
                                <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-purple-100 flex items-center justify-center">
                                    <i class="fas fa-trophy text-purple-600 text-2xl"></i>
                                </div>
                                <h4 class="font-semibold mb-2">Overall Standing</h4>
                                <div class="text-3xl font-bold text-purple-600 mb-1">#<?php echo $platoon_rank; ?></div>
                                <div class="text-sm text-gray-500">Platoon Rank</div>
                                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                    <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo max(100 - ($platoon_rank * 10), 10); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize unified dashboard
        document.addEventListener('DOMContentLoaded', function() {
            if (window.UnifiedDashboard) {
                window.dashboard = new UnifiedDashboard('cadet');
            }
        });
    </script>
    <script>
        // Initialize charts with real data
        document.addEventListener('DOMContentLoaded', function() {
            // Attendance Chart
            const attendanceCtx = document.getElementById('attendanceChart');
            if (attendanceCtx) {
                new Chart(attendanceCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_map(function($item) { return date('M j', strtotime($item['date'])); }, $weekly_attendance)); ?>,
                        datasets: [{
                            label: 'My Attendance',
                            data: <?php echo json_encode(array_column($weekly_attendance, 'count')); ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#10b981',
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
                                borderColor: '#10b981',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    color: '#6b7280'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#6b7280'
                                }
                            }
                        }
                    }
                });
            }

            // Grade Distribution Chart
            const gradeCtx = document.getElementById('gradeChart');
            if (gradeCtx) {
                new Chart(gradeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['A (90-100)', 'B (80-89)', 'C (70-79)', 'D (60-69)', 'F (0-59)'],
                        datasets: [{
                            data: <?php echo json_encode(array_values($grade_distribution)); ?>,
                            backgroundColor: [
                                '#10b981',
                                '#3b82f6',
                                '#28a745',
                                '#ef4444',
                                '#6b7280'
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    color: '#6b7280',
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff'
                            }
                        }
                    }
                });
            }

            // QR Scanner functionality
            const startScanBtn = document.getElementById('start-scan');
            const scannerDiv = document.getElementById('qr-scanner');
            let scanner = null;

            if (startScanBtn) {
                startScanBtn.addEventListener('click', function() {
                    if (scanner) {
                        scanner.stop();
                        scanner = null;
                        startScanBtn.innerHTML = '<i class="fas fa-camera"></i> Start Scanner';
                        scannerDiv.innerHTML = '';
                    } else {
                        scanner = new Html5QrcodeScanner(
                            'qr-scanner',
                            { fps: 10, qrbox: 200 },
                            false
                        );

                        scanner.render(
                            function(decodedText, decodedResult) {
                                // Handle successful scan
                                window.dashboard.handleScanResult(decodedText, decodedResult);
                                scanner.stop();
                                scanner = null;
                                startScanBtn.innerHTML = '<i class="fas fa-camera"></i> Start Scanner';
                            },
                            function(error) {
                                console.warn('QR scan error:', error);
                            }
                        );

                        startScanBtn.innerHTML = '<i class="fas fa-stop"></i> Stop Scanner';
                    }
                });
            }
        });

        // Export chart function
        function exportChart(chartId) {
            const canvas = document.getElementById(chartId);
            const url = canvas.toDataURL('image/png');
            const a = document.createElement('a');
            a.href = url;
            a.download = chartId + '_' + new Date().toISOString().split('T')[0] + '.png';
            a.click();
        }
    </script>
</body>
</html>