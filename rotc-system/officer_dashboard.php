<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Check if user is logged in and is officer
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['officer', 'instructor', '1cl', '2cl', 'commandant'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Get officer's platoon information
try {
    $stmt = $pdo->prepare("SELECT platoon FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $officer_platoon = $stmt->fetch()['platoon'] ?? 'All';
    
    // Get cadets under this officer's command
    $platoon_condition = $officer_platoon !== 'All' ? "AND cp.platoon = ?" : "";
    $params = $officer_platoon !== 'All' ? [$officer_platoon] : [];
    
    $stmt = $pdo->prepare("
        SELECT u.*, cp.platoon, cp.profile_status 
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role IN ('cadet', 'basic_cadet') $platoon_condition
        ORDER BY cp.platoon, u.last_name, u.first_name
    ");
    $stmt->execute($params);
    $my_cadets = $stmt->fetchAll();
    
    // Get today's attendance for my cadets
    $cadet_ids = array_column($my_cadets, 'id');
    $today_present = 0;
    if (!empty($cadet_ids)) {
        $placeholders = str_repeat('?,', count($cadet_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as present 
            FROM attendance_logs 
            WHERE DATE(timestamp) = CURDATE() AND user_id IN ($placeholders)
        ");
        $stmt->execute($cadet_ids);
        $today_present = $stmt->fetch()['present'];
    }
    
    // Calculate attendance rate
    $total_cadets = count($my_cadets);
    $attendance_rate = $total_cadets > 0 ? round(($today_present / $total_cadets) * 100, 1) : 0;
    
    // Get recent activities for my cadets
    if (!empty($cadet_ids)) {
        $stmt = $pdo->prepare("
            SELECT al.*, u.first_name, u.last_name 
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            WHERE al.user_id IN ($placeholders)
            ORDER BY al.timestamp DESC 
            LIMIT 10
        ");
        $stmt->execute($cadet_ids);
        $recent_activities = $stmt->fetchAll();
    } else {
        $recent_activities = [];
    }
    
    // Get weekly attendance data for my cadets
    if (!empty($cadet_ids)) {
        $stmt = $pdo->prepare("
            SELECT DATE(timestamp) as date, COUNT(DISTINCT user_id) as count 
            FROM attendance_logs 
            WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
            AND user_id IN ($placeholders)
            GROUP BY DATE(timestamp) 
            ORDER BY date
        ");
        $stmt->execute($cadet_ids);
        $weekly_attendance = $stmt->fetchAll();
    } else {
        $weekly_attendance = [];
    }
    
    // Get performance data for my cadets
    if (!empty($cadet_ids)) {
        $stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN avg_grade >= 90 THEN 'Excellent'
                    WHEN avg_grade >= 80 THEN 'Good'
                    WHEN avg_grade >= 70 THEN 'Average'
                    ELSE 'Needs Improvement'
                END as performance,
                COUNT(*) as count
            FROM (
                SELECT user_id, AVG(grade) as avg_grade
                FROM grades
                WHERE user_id IN ($placeholders)
                GROUP BY user_id
            ) as user_grades
            GROUP BY performance
        ");
        $stmt->execute($cadet_ids);
        $performance_data = $stmt->fetchAll();
    } else {
        $performance_data = [];
    }
    
    // Get upcoming training events
    $stmt = $pdo->query("
        SELECT * FROM training_events 
        WHERE event_date >= CURDATE() 
        ORDER BY event_date ASC 
        LIMIT 5
    ");
    $upcoming_events = $stmt->fetchAll();
    
    // Get low performers who need attention
    if (!empty($cadet_ids)) {
        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, u.id, AVG(g.grade) as avg_grade
            FROM users u
            LEFT JOIN grades g ON u.id = g.user_id
            WHERE u.id IN ($placeholders)
            GROUP BY u.id
            HAVING avg_grade < 75 OR avg_grade IS NULL
            ORDER BY avg_grade ASC
            LIMIT 5
        ");
        $stmt->execute($cadet_ids);
        $low_performers = $stmt->fetchAll();
    } else {
        $low_performers = [];
    }
    
} catch (PDOException $e) {
    error_log("Officer dashboard query error: " . $e->getMessage());
    $my_cadets = $recent_activities = $weekly_attendance = $performance_data = [];
    $upcoming_events = $low_performers = [];
    $today_present = $total_cadets = $attendance_rate = 0;
    $officer_platoon = 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-unified.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="js/dashboard-unified.js"></script>
</head>
<body data-role="officer">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-star"></i></div>
                    <span>Officer Command Panel</span>
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
                    <h1 class="page-title">Officer Command Panel</h1>
                    <span class="badge badge-primary" style="margin-left: 1rem;">Platoon: <?php echo htmlspecialchars($officer_platoon); ?></span>
                </div>
                
                <div class="header-center">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" placeholder="Search cadets..." class="search-input">
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                        <button class="action-btn" title="Messages">
                            <i class="fas fa-envelope"></i>
                            <span class="badge">2</span>
                        </button>
                        <div class="user-menu">
                            <div class="user-avatar">
                                <i class="fas fa-user-tie"></i>
                                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card fade-in">
                        <div class="stat-header">
                            <div class="stat-title">My Cadets</div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $total_cadets; ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>Active cadets under command</span>
                        </div>
                    </div>

                    <div class="stat-card fade-in">
                        <div class="stat-header">
                            <div class="stat-title">Today's Attendance</div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo $today_present; ?></div>
                        <div class="stat-change <?php echo $attendance_rate >= 80 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-<?php echo $attendance_rate >= 80 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            <span><?php echo $attendance_rate; ?>% attendance rate</span>
                        </div>
                    </div>

                    <div class="stat-card fade-in">
                        <div class="stat-header">
                            <div class="stat-title">Needs Attention</div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo count($low_performers); ?></div>
                        <div class="stat-change negative">
                            <i class="fas fa-arrow-down"></i>
                            <span>Low performing cadets</span>
                        </div>
                    </div>

                    <div class="stat-card fade-in">
                        <div class="stat-header">
                            <div class="stat-title">Upcoming Events</div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo count($upcoming_events); ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>Training events scheduled</span>
                        </div>
                    </div>
                </div>

                <!-- Charts and Analytics -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Attendance Trends -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Platoon Attendance Trends</h3>
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

                    <!-- Performance Distribution -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Cadet Performance</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Items and Quick Tools -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Cadets Needing Attention -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Cadets Needing Attention</h3>
                            <a href="my_platoons.php" class="btn btn-outline">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($low_performers)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-check-circle text-green-500 text-4xl mb-2"></i>
                                    <p class="text-gray-500">All cadets performing well!</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($low_performers as $cadet): ?>
                                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                                <i class="fas fa-user text-red-600"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-red-600">
                                                    Average: <?php echo $cadet['avg_grade'] ? round($cadet['avg_grade'], 1) . '%' : 'No grades'; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="view_profile.php?id=<?php echo $cadet['id']; ?>" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-2 gap-3">
                                <a href="attendance/scan.php" class="btn btn-primary">
                                    <i class="fas fa-qrcode"></i>
                                    Take Attendance
                                </a>
                                <a href="grades/add_grade.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i>
                                    Add Grade
                                </a>
                                <a href="announcements/create.php" class="btn btn-success">
                                    <i class="fas fa-bullhorn"></i>
                                    Announce
                                </a>
                                <a href="reports/platoon_report.php" class="btn btn-secondary">
                                    <i class="fas fa-file-export"></i>
                                    Generate Report
                                </a>
                            </div>
                            
                            <!-- QR Scanner Widget -->
                            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                                <h4 class="font-semibold mb-2 text-blue-800">Quick Attendance Scanner</h4>
                                <div id="qr-scanner" style="width: 100%; max-width: 300px; margin: 0 auto;"></div>
                                <button id="start-scan" class="btn btn-primary w-full mt-2">
                                    <i class="fas fa-camera"></i>
                                    Start Scanner
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Training Events -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">Upcoming Training Events</h3>
                        <a href="training/schedule.php" class="btn btn-outline">View Schedule</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_events)): ?>
                            <div class="text-center p-4">
                                <i class="fas fa-calendar-plus text-gray-400 text-4xl mb-2"></i>
                                <p class="text-gray-500">No upcoming training events scheduled</p>
                                <a href="training/create.php" class="btn btn-primary mt-2">
                                    <i class="fas fa-plus"></i>
                                    Schedule Training
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($upcoming_events as $event): ?>
                                <div class="p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                                    <div class="flex items-start justify-between mb-2">
                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($event['title']); ?></h4>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($event['type']); ?></span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($event['description']); ?></p>
                                    <div class="flex items-center gap-2 text-sm text-gray-500">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('M j, Y', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo date('g:i A', strtotime($event['start_time'])); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Cadets Overview -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">My Cadets Overview</h3>
                        <a href="my_platoons.php" class="btn btn-outline">Manage All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Platoon</th>
                                        <th>Status</th>
                                        <th>Last Attendance</th>
                                        <th>Performance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($my_cadets, 0, 10) as $cadet): ?>
                                    <?php
                                    // Get last attendance
                                    $stmt = $pdo->prepare("SELECT MAX(timestamp) as last_attendance FROM attendance_logs WHERE user_id = ?");
                                    $stmt->execute([$cadet['id']]);
                                    $last_attendance = $stmt->fetch()['last_attendance'];
                                    
                                    // Get average grade
                                    $stmt = $pdo->prepare("SELECT AVG(grade) as avg_grade FROM grades WHERE user_id = ?");
                                    $stmt->execute([$cadet['id']]);
                                    $avg_grade = $stmt->fetch()['avg_grade'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-medium">
                                                    <?php echo strtoupper(substr($cadet['first_name'], 0, 1) . substr($cadet['last_name'], 0, 1)); ?>
                                                </div>
                                                <span class="font-medium"><?php echo htmlspecialchars($cadet['first_name'] . ' ' . $cadet['last_name']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($cadet['platoon'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $cadet['profile_status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($cadet['profile_status'] ?? 'pending'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($last_attendance): ?>
                                                <?php echo date('M j, Y', strtotime($last_attendance)); ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($avg_grade): ?>
                                                <span class="badge badge-<?php echo $avg_grade >= 80 ? 'success' : ($avg_grade >= 70 ? 'warning' : 'danger'); ?>">
                                                    <?php echo round($avg_grade, 1); ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">No grades</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex gap-2">
                                                <a href="view_profile.php?id=<?php echo $cadet['id']; ?>" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="grades/add_grade.php?user_id=<?php echo $cadet['id']; ?>" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                                    <i class="fas fa-plus"></i>
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

    <script src="js/dashboard-unified.js"></script>
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
                            label: 'Platoon Attendance',
                            data: <?php echo json_encode(array_column($weekly_attendance, 'count')); ?>,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3b82f6',
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
                                borderColor: '#3b82f6',
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

            // Performance Chart
            const performanceCtx = document.getElementById('performanceChart');
            if (performanceCtx) {
                new Chart(performanceCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_column($performance_data, 'performance')); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($performance_data, 'count')); ?>,
                            backgroundColor: [
                                '#10b981',
                                '#3b82f6',
                                '#28a745',
                                '#ef4444'
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
                                    padding: 20,
                                    usePointStyle: true,
                                    color: '#6b7280'
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