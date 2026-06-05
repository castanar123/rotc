<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$platoon_filter = $_GET['platoon'] ?? '';
$role_filter = $_GET['role'] ?? '';

try {
    // Build the query with filters
    $where_conditions = ["DATE(al.timestamp) BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    
    if (!empty($platoon_filter)) {
        $where_conditions[] = "cp.platoon = ?";
        $params[] = $platoon_filter;
    }
    
    if (!empty($role_filter)) {
        $where_conditions[] = "u.role = ?";
        $params[] = $role_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get attendance data
    $stmt = $pdo->prepare("
        SELECT 
            al.id,
            al.timestamp,
            cp.first_name,
            cp.last_name,
            cp.platoon,
            u.role,
            u.username,
            DATE(al.timestamp) as attendance_date,
            TIME(al.timestamp) as attendance_time
        FROM attendance_logs al
        JOIN users u ON al.user_id = u.id
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE $where_clause
        ORDER BY al.timestamp DESC
    ");
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
    
    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT al.user_id) as unique_attendees,
            COUNT(*) as total_records,
            COUNT(DISTINCT DATE(al.timestamp)) as days_with_attendance
        FROM attendance_logs al
        JOIN users u ON al.user_id = u.id
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE $where_clause
    ");
    $stmt->execute($params);
    $summary = $stmt->fetch();
    
    // Get attendance by platoon
    $stmt = $pdo->prepare("
        SELECT 
            cp.platoon,
            COUNT(DISTINCT al.user_id) as unique_attendees,
            COUNT(*) as total_records
        FROM attendance_logs al
        JOIN users u ON al.user_id = u.id
        JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE $where_clause
        GROUP BY cp.platoon
        ORDER BY cp.platoon
    ");
    $stmt->execute($params);
    $platoon_stats = $stmt->fetchAll();
    
    // Get all platoons for filter dropdown
    $stmt = $pdo->query("SELECT DISTINCT platoon FROM cadet_profiles WHERE platoon IS NOT NULL ORDER BY platoon");
    $all_platoons = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all roles for filter dropdown
    $stmt = $pdo->query("SELECT DISTINCT role FROM users ORDER BY role");
    $all_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log("Attendance report error: " . $e->getMessage());
    $attendance_records = [];
    $summary = ['unique_attendees' => 0, 'total_records' => 0, 'days_with_attendance' => 0];
    $platoon_stats = [];
    $all_platoons = [];
    $all_roles = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-unified.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <a href="../attendance/dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Attendance
                </a>
                <h1 class="page-title">Attendance Report</h1>
            </div>
            
            <div class="header-right">
                <button onclick="exportReport()" class="btn btn-primary">
                    <i class="fas fa-download"></i>
                    Export Report
                </button>
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

        <!-- Main Content -->
        <main class="main-content">
            <div class="content">
                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-filter"></i>
                            Report Filters
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="filter-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($start_date); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" id="end_date" name="end_date" class="form-input" value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="platoon" class="form-label">Platoon</label>
                                    <select id="platoon" name="platoon" class="form-select">
                                        <option value="">All Platoons</option>
                                        <?php foreach ($all_platoons as $platoon): ?>
                                            <option value="<?php echo htmlspecialchars($platoon); ?>" <?php echo $platoon_filter === $platoon ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($platoon); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="role" class="form-label">Role</label>
                                    <select id="role" name="role" class="form-select">
                                        <option value="">All Roles</option>
                                        <?php foreach ($all_roles as $role): ?>
                                            <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $role_filter === $role ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucfirst($role)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                        Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Unique Attendees</span>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($summary['unique_attendees']); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Total Records</span>
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($summary['total_records']); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Days with Attendance</span>
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?php echo number_format($summary['days_with_attendance']); ?></div>
                    </div>
                </div>

                <!-- Platoon Statistics Chart -->
                <?php if (!empty($platoon_stats)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-bar"></i>
                            Attendance by Platoon
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="platoonChart" width="400" height="200"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attendance Records Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-table"></i>
                            Attendance Records (<?php echo count($attendance_records); ?> records)
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($attendance_records)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Platoon</th>
                                            <th>Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                                <td><?php echo date('g:i A', strtotime($record['attendance_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['username']); ?></td>
                                                <td><?php echo htmlspecialchars($record['platoon'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($record['role'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <h4>No attendance records found</h4>
                                <p>Try adjusting your filter criteria or date range.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .filter-form .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-primary);
        }
        
        .table th {
            background: var(--bg-tertiary);
            font-weight: 600;
            color: var(--text-accent);
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr:hover {
            background: var(--bg-tertiary);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--text-muted);
        }
    </style>

    <script>
        // Initialize platoon chart if data exists
        <?php if (!empty($platoon_stats)): ?>
        const ctx = document.getElementById('platoonChart').getContext('2d');
        const platoonChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($platoon_stats, 'platoon')); ?>,
                datasets: [{
                    label: 'Unique Attendees',
                    data: <?php echo json_encode(array_column($platoon_stats, 'unique_attendees')); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }, {
                    label: 'Total Records',
                    data: <?php echo json_encode(array_column($platoon_stats, 'total_records')); ?>,
                    backgroundColor: 'rgba(0, 191, 255, 0.8)',
                    borderColor: 'rgba(0, 191, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        labels: {
                            color: '#e8eaed'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#9aa0a6'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#9aa0a6'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        function exportReport() {
            // Create CSV content
            const records = <?php echo json_encode($attendance_records); ?>;
            let csv = 'Date,Time,Name,Username,Platoon,Role\n';
            
            records.forEach(record => {
                const date = new Date(record.attendance_date).toLocaleDateString();
                const time = new Date('1970-01-01T' + record.attendance_time + 'Z').toLocaleTimeString();
                csv += `"${date}","${time}","${record.first_name} ${record.last_name}","${record.username}","${record.platoon || 'N/A'}","${record.role}"\n`;
            });
            
            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `attendance_report_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>