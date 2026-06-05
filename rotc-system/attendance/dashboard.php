<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Get attendance statistics
try {
    // Today's attendance count
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as count FROM attendance_logs WHERE DATE(timestamp) = CURDATE()");
    $today_count = $stmt->fetch()['count'];
    
    // This week's attendance
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as count FROM attendance_logs WHERE WEEK(timestamp) = WEEK(CURDATE())");
    $week_count = $stmt->fetch()['count'];
    
    // Recent attendance logs
    $stmt = $pdo->query("
        SELECT al.*, cp.first_name, cp.last_name, u.role 
        FROM attendance_logs al 
        JOIN users u ON al.user_id = u.id 
        JOIN cadet_profiles cp ON u.id = cp.user_id
        ORDER BY al.timestamp DESC 
        LIMIT 20
    ");
    $recent_logs = $stmt->fetchAll();
    
    // Attendance by hour today
    $stmt = $pdo->query("
        SELECT HOUR(timestamp) as hour, COUNT(*) as count 
        FROM attendance_logs 
        WHERE DATE(timestamp) = CURDATE() 
        GROUP BY HOUR(timestamp) 
        ORDER BY hour
    ");
    $hourly_data = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Attendance dashboard error: " . $e->getMessage());
    $today_count = $week_count = 0;
    $recent_logs = $hourly_data = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance Dashboard - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-unified.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body data-role="<?php echo $_SESSION['role']; ?>">
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <a href="../<?php echo $_SESSION['role']; ?>_dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <h1 class="page-title">QR Attendance System</h1>
            </div>
            
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                        <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $today_count; ?></div>
                        <div class="stat-label">Today's Attendance</div>
                        <div class="stat-trend positive">
                            <i class="fas fa-clock"></i>
                            <span>Live</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-calendar-week"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $week_count; ?></div>
                        <div class="stat-label">This Week</div>
                        <div class="stat-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+15%</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($recent_logs); ?></div>
                        <div class="stat-label">Recent Scans</div>
                        <div class="stat-trend neutral">
                            <i class="fas fa-refresh"></i>
                            <span>Live</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value">98%</div>
                        <div class="stat-label">Success Rate</div>
                        <div class="stat-trend positive">
                            <i class="fas fa-check"></i>
                            <span>Excellent</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main QR Scanner and Actions -->
            <div class="card-grid">
                <!-- QR Scanner -->
                <div class="card qr-scanner-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-camera"></i>
                            QR Code Scanner
                        </h3>
                        <div class="card-actions">
                            <button class="btn btn-success" id="start-scanner">
                                <i class="fas fa-play"></i>
                                Start
                            </button>
                            <button class="btn btn-danger" id="stop-scanner" style="display: none;">
                                <i class="fas fa-stop"></i>
                                Stop
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="scanner-container">
                            <div id="qr-reader" class="qr-reader"></div>
                            <div id="scan-status" class="scan-status">
                                <i class="fas fa-qrcode"></i>
                                <p>Click "Start" to begin scanning QR codes</p>
                            </div>
                        </div>
                        <div id="scan-result" class="scan-result"></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="action-grid">
                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'instructor'): ?>
                            <a href="generate_qr.php" class="action-btn">
                                <div class="action-icon bg-primary">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Generate QR</div>
                                    <div class="action-description">Create attendance QR codes</div>
                                </div>
                            </a>
                            <?php endif; ?>
                            
                            <a href="manual_attendance.php" class="action-btn">
                                <div class="action-icon bg-success">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Manual Entry</div>
                                    <div class="action-description">Record attendance manually</div>
                                </div>
                            </a>
                            
                            <a href="../reports/attendance_report.php" class="action-btn">
                                <div class="action-icon bg-info">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">View Reports</div>
                                    <div class="action-description">Attendance analytics</div>
                                </div>
                            </a>
                            
                            <button onclick="exportAttendance()" class="action-btn">
                                <div class="action-icon bg-warning">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="action-content">
                                    <div class="action-title">Export Data</div>
                                    <div class="action-description">Download attendance records</div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hourly Attendance Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Today's Attendance by Hour</h3>
                    <div class="card-actions">
                        <button class="btn btn-outline" onclick="refreshChart()">
                            <i class="fas fa-refresh"></i>
                            Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance Logs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Attendance Logs</h3>
                    <div class="card-actions">
                        <button class="btn btn-outline" onclick="refreshLogs()">
                            <i class="fas fa-refresh"></i>
                            Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table" id="attendance-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($log['first_name'], 0, 1) . substr($log['last_name'], 0, 1)); ?>
                                            </div>
                                            <span class="user-name"><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo ucfirst($log['role']); ?></span>
                                    </td>
                                    <td><?php echo date('g:i A', strtotime($log['timestamp'])); ?></td>
                                    <td>
                                        <span class="badge badge-success">
                                            <i class="fas fa-check"></i>
                                            Present
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            <i class="fas fa-qrcode"></i>
                                            QR Scan
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notification-container"></div>

    <script>
        // QR Scanner variables
        let qrScanner = null;
        let isScanning = false;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeChart();
            setupEventListeners();
        });

        function setupEventListeners() {
            const startBtn = document.getElementById('start-scanner');
            const stopBtn = document.getElementById('stop-scanner');

            startBtn.addEventListener('click', startScanner);
            stopBtn.addEventListener('click', stopScanner);
        }

        function startScanner() {
            if (isScanning) return;

            const startBtn = document.getElementById('start-scanner');
            const stopBtn = document.getElementById('stop-scanner');
            const statusDiv = document.getElementById('scan-status');
            const resultDiv = document.getElementById('scan-result');

            qrScanner = new Html5QrcodeScanner(
                'qr-reader',
                {
                    fps: 10,
                    qrbox: { width: 300, height: 300 },
                    aspectRatio: 1.0
                },
                false
            );

            qrScanner.render(
                function(decodedText, decodedResult) {
                    handleScanSuccess(decodedText, decodedResult);
                },
                function(error) {
                    // Ignore continuous scan errors
                    console.warn('QR scan error:', error);
                }
            );

            isScanning = true;
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';
            statusDiv.innerHTML = `
                <i class="fas fa-camera"></i>
                <p>Scanner active - Point camera at QR code</p>
            `;
            resultDiv.innerHTML = '';
        }

        function stopScanner() {
            if (!isScanning || !qrScanner) return;

            qrScanner.stop().then(() => {
                qrScanner.clear();
                qrScanner = null;
                isScanning = false;

                const startBtn = document.getElementById('start-scanner');
                const stopBtn = document.getElementById('stop-scanner');
                const statusDiv = document.getElementById('scan-status');

                startBtn.style.display = 'inline-block';
                stopBtn.style.display = 'none';
                statusDiv.innerHTML = `
                    <i class="fas fa-qrcode"></i>
                    <p>Click "Start" to begin scanning QR codes</p>
                `;
            }).catch(err => {
                console.error('Error stopping scanner:', err);
            });
        }

        function handleScanSuccess(decodedText, decodedResult) {
            console.log('QR Code scanned:', decodedText);
            
            const resultDiv = document.getElementById('scan-result');
            resultDiv.innerHTML = `
                <div class="scan-success">
                    <i class="fas fa-check-circle"></i>
                    <div class="scan-text">
                        <strong>Scan Successful!</strong><br>
                        Processing: ${decodedText}
                    </div>
                </div>
            `;

            // Process the attendance
            processAttendance(decodedText);

            // Auto-clear result after 3 seconds
            setTimeout(() => {
                resultDiv.innerHTML = '';
            }, 3000);
        }

        function processAttendance(qrData) {
            fetch('process_qr.php', {
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
                    refreshStats();
                    refreshLogs();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error processing attendance:', error);
                showNotification('Error processing attendance', 'error');
            });
        }

        function initializeChart() {
            const ctx = document.getElementById('hourlyChart');
            if (ctx) {
                const hourlyData = <?php echo json_encode($hourly_data); ?>;
                const hours = Array.from({length: 24}, (_, i) => i);
                const counts = hours.map(hour => {
                    const found = hourlyData.find(d => parseInt(d.hour) === hour);
                    return found ? parseInt(found.count) : 0;
                });

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: hours.map(h => h + ':00'),
                        datasets: [{
                            label: 'Attendance Count',
                            data: counts,
                            backgroundColor: 'rgba(40, 167, 69, 0.8)',
                            borderColor: '#28a745',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        }

        function showNotification(message, type = 'info') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

            container.appendChild(notification);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        function refreshStats() {
            // Refresh page statistics
            location.reload();
        }

        function refreshLogs() {
            // Refresh attendance logs table
            fetch('get_recent_logs.php')
                .then(response => response.json())
                .then(data => {
                    updateLogsTable(data);
                })
                .catch(error => {
                    console.error('Error refreshing logs:', error);
                });
        }

        function updateLogsTable(logs) {
            const tbody = document.querySelector('#attendance-table tbody');
            tbody.innerHTML = '';

            logs.forEach(log => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar">
                                ${log.first_name.charAt(0).toUpperCase()}${log.last_name.charAt(0).toUpperCase()}
                            </div>
                            <span class="user-name">${log.first_name} ${log.last_name}</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-primary">${log.role.charAt(0).toUpperCase() + log.role.slice(1)}</span>
                    </td>
                    <td>${new Date(log.timestamp).toLocaleTimeString()}</td>
                    <td>
                        <span class="badge badge-success">
                            <i class="fas fa-check"></i>
                            Present
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            <i class="fas fa-qrcode"></i>
                            QR Scan
                        </span>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function refreshChart() {
            location.reload();
        }

        function exportAttendance() {
            window.open('export_attendance.php?date=' + new Date().toISOString().split('T')[0], '_blank');
        }
    </script>
</body>
</html>