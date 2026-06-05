<?php
require_once 'includes/db.php';
require_once 'includes/session.php';

// Check if user is logged in and is a cadet
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && !in_array($_SESSION['role'], ['cadet', 'basic_cadet']))) {
    // Allow access if user_id is set, even if role is not defined (for legacy compatibility)
    if (!isset($_SESSION['user_id'])) {
        header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
        exit();
    }
}

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json');
    error_log("AJAX request received - Action: " . ($_GET['action'] ?? 'none') . ", User ID: " . $_SESSION['user_id']);
    
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_stats':
                error_log("Processing get_stats for user_id: " . $_SESSION['user_id']);
                $stats = getCadetAttendanceStats($_SESSION['user_id']);
                error_log("Stats result: " . json_encode($stats));
                echo json_encode(['success' => true, 'data' => $stats]);
                break;
                
            case 'get_recent_attendance':
                error_log("Processing get_recent_attendance for user_id: " . $_SESSION['user_id']);
                $recent = getCadetRecentAttendance($_SESSION['user_id'], 15);
                error_log("Recent attendance result count: " . count($recent));
                echo json_encode(['success' => true, 'data' => $recent]);
                break;
                
            case 'get_monthly':
                $monthly = getCadetMonthlyAttendance($_SESSION['user_id']);
                echo json_encode(['success' => true, 'data' => $monthly]);
                break;
                
            default:
                error_log("Invalid AJAX action: " . $action);
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// API Functions
function getCadetAttendanceStats($user_id) {
    global $pdo;
    error_log("getCadetAttendanceStats called for user_id: $user_id");
    
    // Get cadet_id from user_id
    error_log("Querying cadet_profiles for user_id: $user_id");
    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cadet = $stmt->fetch();
    error_log("Cadet profile result: " . json_encode($cadet));
    
    if (!$cadet) {
        // Fallback to user_id as cadet_id
        $cadet_id = $user_id;
        error_log("No cadet profile found, using user_id as cadet_id: $cadet_id");
    } else {
        $cadet_id = $cadet['id'];
        error_log("Found cadet profile, using cadet_id: $cadet_id");
    }
    
    // Check which attendance table exists and has data
    $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
    $attendance_logs_exists = $table_check->rowCount() > 0;
    
    $use_attendance_logs = false;
    $result = null;
    
    // If attendance_logs exists, check if it has data for this cadet
    if ($attendance_logs_exists) {
        error_log("Checking attendance_logs for data for cadet_id: $cadet_id");
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE cadet_profile_id = ?");
        $count_stmt->execute([$cadet_id]);
        $count_result = $count_stmt->fetch();
        
        if ($count_result['count'] > 0) {
            $use_attendance_logs = true;
            error_log("Found {$count_result['count']} records in attendance_logs, using attendance_logs table");
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present,
                    COUNT(CASE WHEN status IN ('Absent', 'absent') THEN 1 END) as absent,
                    COUNT(CASE WHEN status IN ('Late', 'late') THEN 1 END) as late
                FROM attendance_logs 
                WHERE cadet_profile_id = ?
            ");
            $stmt->execute([$cadet_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Attendance_logs result: " . json_encode($result));
        } else {
            error_log("No records found in attendance_logs for cadet_id: $cadet_id, falling back to attendance table");
        }
    }
    
    // If attendance_logs doesn't exist or has no data, use attendance table
    if (!$use_attendance_logs) {
        error_log("Querying attendance table for cadet_id: $cadet_id");
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('Present', 'present') THEN 1 END) as present,
                COUNT(CASE WHEN status IN ('Absent', 'absent') THEN 1 END) as absent,
                COUNT(CASE WHEN status IN ('Late', 'late') THEN 1 END) as late
            FROM attendance 
            WHERE cadet_id = ?
        ");
        $stmt->execute([$cadet_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Attendance table result: " . json_encode($result));
    }
    
    error_log("getCadetAttendanceStats returning: " . json_encode($result ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0]));
    return $result ?: ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
}

function getCadetRecentAttendance($user_id, $limit = 10) {
    global $pdo;
    
    // Get cadet_id from user_id
    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cadet = $stmt->fetch();
    
    if (!$cadet) {
        // Fallback to user_id as cadet_id
        $cadet_id = $user_id;
    } else {
        $cadet_id = $cadet['id'];
    }
    
    // Check which attendance table exists and has data
    $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
    $attendance_logs_exists = $table_check->rowCount() > 0;
    
    $use_attendance_logs = false;
    
    // If attendance_logs exists, check if it has data for this cadet
    if ($attendance_logs_exists) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE cadet_profile_id = ?");
        $count_stmt->execute([$cadet_id]);
        $count_result = $count_stmt->fetch();
        
        if ($count_result['count'] > 0) {
            $use_attendance_logs = true;
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(event_date, DATE(created_at)) as event_date,
                    COALESCE(status, 'present') as status,
                    COALESCE(time_in, TIME(created_at)) as time_in,
                    COALESCE(event_name, 'Training') as event_name
                FROM attendance_logs 
                WHERE cadet_profile_id = ?
                ORDER BY COALESCE(event_date, DATE(created_at)) DESC, created_at DESC
                LIMIT " . intval($limit) . "
            ");
            $stmt->execute([$cadet_id]);
        }
    }
    
    // If attendance_logs doesn't exist or has no data, use attendance table
    if (!$use_attendance_logs) {
        $stmt = $pdo->prepare("
            SELECT 
                a.log_date as event_date,
                a.status,
                a.log_time as time_in,
                a.training_day as event_name
            FROM attendance a
            WHERE a.cadet_id = ?
            ORDER BY a.log_date DESC, a.created_at DESC
            LIMIT " . intval($limit) . "
        ");
        $stmt->execute([$cadet_id]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getCadetMonthlyAttendance($user_id) {
    global $pdo;
    
    // Get cadet_id from user_id
    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cadet = $stmt->fetch();
    
    if (!$cadet) {
        // Fallback to user_id as cadet_id
        $cadet_id = $user_id;
    } else {
        $cadet_id = $cadet['id'];
    }
    
    // Check which attendance table exists and has data
    $table_check = $pdo->query("SHOW TABLES LIKE 'attendance_logs'");
    $attendance_logs_exists = $table_check->rowCount() > 0;
    
    $use_attendance_logs = false;
    
    // If attendance_logs exists, check if it has data for this cadet
    if ($attendance_logs_exists) {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE cadet_profile_id = ?");
        $count_stmt->execute([$cadet_id]);
        $count_result = $count_stmt->fetch();
        
        if ($count_result['count'] > 0) {
            $use_attendance_logs = true;
            $stmt = $pdo->prepare("
                SELECT 
                    MONTH(COALESCE(event_date, DATE(created_at))) as month,
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days
                FROM attendance_logs 
                WHERE cadet_profile_id = ? AND YEAR(COALESCE(event_date, DATE(created_at))) = YEAR(CURDATE())
                GROUP BY MONTH(COALESCE(event_date, DATE(created_at)))
                ORDER BY month
            ");
            $stmt->execute([$cadet_id]);
        }
    }
    
    // If attendance_logs doesn't exist or has no data, use attendance table
    if (!$use_attendance_logs) {
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
        $stmt->execute([$cadet_id]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Removed duplicate AJAX handling section - using the action-based handler above

// Get initial data for page load
$cadet_profile = null;
$stats = ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'attendance_rate' => 0];
$recent_attendance = [];

try {
    // Get cadet profile info
    $stmt = $pdo->prepare("SELECT id, student_id, CONCAT(first_name, ' ', last_name) as full_name, platoon FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $profile_data = $stmt->fetch();
    
    if (!$profile_data) {
        // Use user_id as cadet_id for users without cadet_profiles record (fallback for legacy data)
        $cadet_profile = [
            'id' => $_SESSION['user_id'],
            'student_id' => $_SESSION['username'] ?? 'Unknown',
            'first_name' => $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown',
            'last_name' => 'User',
            'platoon' => $_SESSION['platoon'] ?? 'Unassigned'
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
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $cadet_profile = [
        'id' => $_SESSION['user_id'],
        'student_id' => $_SESSION['username'] ?? 'Unknown',
        'first_name' => $_SESSION['full_name'] ?? 'Unknown',
        'last_name' => 'User',
        'platoon' => 'Unassigned'
    ];
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎖️</text></svg>">
    <style>
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--spacing-md);
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: var(--spacing-md);
            text-align: left;
            border-bottom: 1px solid var(--border-primary);
        }
        
        .attendance-table th {
            background: var(--bg-primary);
            color: var(--text-accent);
            font-weight: 600;
        }
        
        .attendance-table tr:hover {
            background: var(--bg-hover);
        }
        
        .status-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-present {
            background: var(--success-bg);
            color: var(--success-text);
        }
        
        .status-absent {
            background: var(--error-bg);
            color: var(--error-text);
        }
        
        .status-late {
            background: var(--warning-bg);
            color: var(--warning-text);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: var(--spacing-lg) 0;
        }
        
        .loading {
            text-align: center;
            padding: var(--spacing-xl);
            color: var(--text-secondary);
        }
        
        .error-message {
            background: var(--error-bg);
            color: var(--error-text);
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            margin: var(--spacing-md) 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-medal"></i></div>
                    <span class="logo-text">Cadet Portal</span>
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
                    <?php if ($_SESSION['role'] === 'basic_cadet'): ?>
                    <li class="nav-item">
                        <a href="file_missing_id.php" class="nav-link">
                            <i class="fas fa-id-card-alt"></i>
                            <span>File Missing ID</span>
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a href="QR/scanner.html" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Check-in</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="cadet_attendance_new.php" class="nav-link active">
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
                        <h1 class="header-title">My Attendance</h1>
                        <p class="header-subtitle"><?php echo htmlspecialchars($cadet_profile['first_name'] . ' ' . $cadet_profile['last_name']); ?> - <?php echo htmlspecialchars($cadet_profile['platoon']); ?> Platoon</p>
                    </div>
                    <div class="header-actions">
                        <button class="qr-integration-btn" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid fade-in" id="statsGrid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Days</span>
                        <i class="fas fa-calendar stat-icon"></i>
                    </div>
                    <div class="stat-value" id="totalDays">Loading...</div>
                    <div class="stat-change neutral">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Recorded</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Present Days</span>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                    <div class="stat-value" id="presentDays">Loading...</div>
                    <div class="stat-change positive">
                        <i class="fas fa-check"></i>
                        <span>Attended</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Absent Days</span>
                        <i class="fas fa-times-circle stat-icon"></i>
                    </div>
                    <div class="stat-value" id="absentDays">Loading...</div>
                    <div class="stat-change negative">
                        <i class="fas fa-times"></i>
                        <span>Missed</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Attendance Rate</span>
                        <i class="fas fa-percentage stat-icon"></i>
                    </div>
                    <div class="stat-value" id="attendanceRate">Loading...</div>
                    <div class="stat-change" id="attendanceRateChange">
                        <i class="fas fa-chart-line"></i>
                        <span>Overall</span>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid fade-in">
                <!-- Monthly Attendance Chart -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Attendance Trend</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>

                <!-- Recent Attendance Records -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Attendance Records</h3>
                        <span class="qr-action-btn" style="padding: var(--spacing-sm) var(--spacing-md); font-size: 0.9rem; cursor: default;">
                             <i class="fas fa-history"></i>
                             Last 15 Records
                         </span>
                    </div>
                    <div id="attendanceTableContainer">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="attendance-records">
                                <tr>
                                    <td colspan="5" class="text-center">Loading attendance records...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let attendanceChart = null;
        
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

        // Load attendance data
        function loadAttendanceData() {
            console.log('Loading attendance data...');
            
            fetch('cadet_attendance_new.php?ajax=true&action=get_stats')
                .then(response => {
                    console.log('Stats response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Stats data received:', data);
                    if (data.success) {
                        updateStats(data.data);
                    } else {
                        console.error('Error loading stats:', data.error);
                        showError('Failed to load statistics: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Stats fetch error:', error);
                    showError('Failed to load statistics');
                });
            
            fetch('cadet_attendance_new.php?ajax=true&action=get_recent_attendance')
                .then(response => {
                    console.log('Recent attendance response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Recent attendance data received:', data);
                    if (data.success) {
                        updateAttendanceTable(data.data);
                    } else {
                        console.error('Error loading attendance:', data.error);
                        showError('Failed to load attendance records: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Recent attendance fetch error:', error);
                    showError('Failed to load attendance records');
                });
        }

        // Update statistics cards
        function updateStats(stats) {
            console.log('Updating stats with:', stats);
            document.getElementById('totalDays').textContent = stats.total || 0;
            document.getElementById('presentDays').textContent = stats.present || 0;
            document.getElementById('absentDays').textContent = stats.absent || 0;
            
            const total = parseInt(stats.total) || 0;
            const present = parseInt(stats.present) || 0;
            const rate = total > 0 ? Math.round((present / total) * 100) : 0;
            document.getElementById('attendanceRate').textContent = rate + '%';
            
            // Update attendance rate color
            const rateElement = document.getElementById('attendanceRateChange');
            rateElement.className = 'stat-change ' + (rate >= 90 ? 'positive' : rate >= 75 ? 'neutral' : 'negative');
        }

        // Update attendance table
        function updateAttendanceTable(records) {
            const container = document.getElementById('attendanceTableContainer');
            
            if (!records || records.length === 0) {
                container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: var(--spacing-xl);">No attendance records found.</p>';
                return;
            }

            let tableHTML = `
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            records.forEach(record => {
                console.log('Processing record:', record);
                const statusClass = record.status.toLowerCase() === 'present' ? 'status-present' : 
                                  record.status.toLowerCase() === 'absent' ? 'status-absent' : 'status-late';
                
                // Use log_date from database instead of event_date
                const dateField = record.log_date || record.event_date || record.date;
                
                tableHTML += `
                    <tr>
                        <td>${formatDate(dateField)}</td>
                        <td><span class="status-badge ${statusClass}">${record.status}</span></td>
                        <td>${record.time_in || 'N/A'}</td>
                        <td>${record.time_out || 'N/A'}</td>
                        <td>${record.event_name || record.training_day || 'Training'}</td>
                    </tr>
                `;
            });

            tableHTML += '</tbody></table>';
            container.innerHTML = tableHTML;
        }

        // Update monthly chart
        function updateChart(monthlyData) {
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (attendanceChart) {
                attendanceChart.destroy();
            }
            
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const data = new Array(12).fill(0);
            
            // Fill data from database
            if (monthlyData) {
                monthlyData.forEach(item => {
                    if (item.month >= 1 && item.month <= 12) {
                        data[item.month - 1] = parseInt(item.present_count) || 0;
                    }
                });
            }
            
            attendanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Days Present',
                        data: data,
                        borderColor: 'var(--accent-primary)',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: 'var(--text-primary)'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: 'var(--text-secondary)'
                            },
                            grid: {
                                color: 'var(--border-primary)'
                            }
                        },
                        x: {
                            ticks: {
                                color: 'var(--text-secondary)'
                            },
                            grid: {
                                color: 'var(--border-primary)'
                            }
                        }
                    }
                }
            });
        }

        // Format date for display in Aug-18-25 format
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            
            try {
                console.log('Formatting date:', dateString);
                
                // Handle YYYY-MM-DD format (from database)
                if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    const [year, month, day] = dateString.split('-');
                    const date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
                    if (isNaN(date.getTime())) {
                        console.error('Invalid date created from:', dateString);
                        return 'Invalid Date';
                    }
                    
                    // Format as Aug-18-25
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                                       'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const monthName = monthNames[date.getMonth()];
                    const dayFormatted = String(date.getDate()).padStart(2, '0');
                    const yearFormatted = String(date.getFullYear()).slice(-2);
                    
                    const formatted = `${monthName}-${dayFormatted}-${yearFormatted}`;
                    console.log('Formatted date:', formatted);
                    return formatted;
                }
                
                // Handle other date formats
                let date;
                if (dateString.includes('-')) {
                    // Handle YYYY-MM-DD format
                    date = new Date(dateString + 'T00:00:00');
                } else {
                    date = new Date(dateString);
                }
                
                // Check if date is valid
                if (isNaN(date.getTime())) {
                    console.error('Invalid date:', dateString);
                    return 'Invalid Date';
                }
                
                // Format as Aug-18-25
                const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                                   'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const monthName = monthNames[date.getMonth()];
                const dayFormatted = String(date.getDate()).padStart(2, '0');
                const yearFormatted = String(date.getFullYear()).slice(-2);
                
                return `${monthName}-${dayFormatted}-${yearFormatted}`;
            } catch (error) {
                console.error('Date formatting error:', error, 'for date:', dateString);
                return 'N/A';
            }
        }

        // Show error message
        function showError(message) {
            const container = document.getElementById('attendanceTableContainer');
            container.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-triangle"></i> ${message}</div>`;
        }

        // Refresh data
        function refreshData() {
            document.getElementById('attendanceTableContainer').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading attendance records...</div>';
            fetchAttendanceStats();
            fetchRecentAttendance();
            fetchMonthlyAttendance();
        }

        // Load data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Cadet Attendance page loaded');
            
            // Add fade-in animation to elements
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Load attendance data
            loadAttendanceData();
            
            // Add a small delay then try again if needed
            setTimeout(() => {
                console.log('Secondary data fetch attempt...');
                fetchAttendanceStats();
                fetchRecentAttendance();
                fetchMonthlyAttendance();
            }, 1000);
            
            // Refresh button functionality
            const refreshBtn = document.querySelector('.btn-refresh');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    fetchAttendanceStats();
                    fetchRecentAttendance();
                    fetchMonthlyAttendance();
                });
            }
        });
        
        // Fetch attendance statistics
        function fetchAttendanceStats() {
            fetch('cadet_attendance_new.php?ajax=true&action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStats(data.data);
                    } else {
                        console.error('Error fetching stats:', data.error);
                        showError('Failed to load attendance statistics');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to load attendance statistics');
                });
        }
        
        // Fetch recent attendance records
        function fetchRecentAttendance() {
            fetch('cadet_attendance_new.php?ajax=true&action=get_recent_attendance')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateAttendanceTable(data.data);
                    } else {
                        console.error('Error fetching attendance:', data.error);
                        showError('Failed to load attendance records');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to load attendance records');
                });
        }
        
        // Fetch monthly attendance data
        function fetchMonthlyAttendance() {
            fetch('cadet_attendance_new.php?ajax=true&action=get_monthly')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateChart(data.data);
                    } else {
                        console.error('Error fetching monthly attendance:', data.error);
                        showError('Failed to load monthly attendance data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to load monthly attendance data');
                });
        }
    </script>
</body>
</html>