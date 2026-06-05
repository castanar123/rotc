<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
check_login();

// Access control: Admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Pending registrations count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_registrations = $stmt->fetch()['total'];

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$platoon_filter = $_GET['platoon'] ?? '';
$company_filter = $_GET['company'] ?? '';

// Build the WHERE clause for filtering
$where_conditions = [];
$params = [];

if ($date_from) {
    $where_conditions[] = "DATE(a.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(a.created_at) <= ?";
    $params[] = $date_to;
}

if ($platoon_filter) {
    $where_conditions[] = "cp.platoon = ?";
    $params[] = $platoon_filter;
}

if ($company_filter) {
    $where_conditions[] = "cp.company = ?";
    $params[] = $company_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get attendance statistics
$attendance_sql = "SELECT 
    COUNT(DISTINCT a.cadet_id) as total_attendees,
    COUNT(a.id) as total_scans,
    DATE(a.created_at) as attendance_date,
    cp.platoon,
    'A' as company
    FROM attendance a 
    JOIN cadet_profiles cp ON a.cadet_id = cp.id 
    $where_clause
    GROUP BY DATE(a.created_at), cp.platoon
    ORDER BY attendance_date DESC";

$stmt = $pdo->prepare($attendance_sql);
$stmt->execute($params);
$attendance_data = $stmt->fetchAll();

// Get summary statistics
$summary_sql = "SELECT 
    COUNT(DISTINCT a.cadet_id) as unique_attendees,
    COUNT(a.id) as total_scans,
    COUNT(DISTINCT DATE(a.created_at)) as active_days
    FROM attendance a 
    JOIN cadet_profiles cp ON a.cadet_id = cp.id 
    $where_clause";

$stmt = $pdo->prepare($summary_sql);
$stmt->execute($params);
$summary = $stmt->fetch();

// Get platoon breakdown
$platoon_sql = "SELECT 
    cp.platoon,
    COUNT(DISTINCT a.cadet_id) as attendees,
    COUNT(a.id) as scans
    FROM attendance a 
    JOIN cadet_profiles cp ON a.cadet_id = cp.id 
    $where_clause
    GROUP BY cp.platoon
    ORDER BY cp.platoon";

$stmt = $pdo->prepare($platoon_sql);
$stmt->execute($params);
$platoon_data = $stmt->fetchAll();

// Get recent attendance records
$recent_sql = "SELECT 
    a.created_at as timestamp,
    CONCAT(cp.first_name, ' ', cp.last_name) as full_name,
    cp.platoon,
    'A' as company
    FROM attendance a 
    JOIN cadet_profiles cp ON a.cadet_id = cp.id 
    $where_clause
    ORDER BY a.created_at DESC 
    LIMIT 50";

$stmt = $pdo->prepare($recent_sql);
$stmt->execute($params);
$recent_attendance = $stmt->fetchAll();

$report_title = "Attendance Report";
if ($date_from && $date_to) {
    $report_title .= " (" . date('M j, Y', strtotime($date_from)) . " - " . date('M j, Y', strtotime($date_to)) . ")";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report_title); ?> - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
   <button class="sidebar-toggle-fixed" id="sidebarToggle">
         <i class="fas fa-bars"></i>
     </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '..';
            include __DIR__ . '/../includes/admin_nav.php';
        ?>
        

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="header-left">
                    <h1 class="page-title"><?php echo htmlspecialchars($report_title); ?></h1>
                    <p class="page-subtitle">Comprehensive attendance analytics and insights</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="exportReport()">
                        <i class="fas fa-download"></i> Export Report
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Report Filters</h2>
                </div>
                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="date_from">From Date:</label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_to">To Date:</label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="form-group">
                            <label for="platoon">Platoon:</label>
                            <select name="platoon" id="platoon">
                                <option value="">All Platoons</option>
                                <option value="1" <?php echo $platoon_filter == '1' ? 'selected' : ''; ?>>Platoon 1</option>
                                <option value="2" <?php echo $platoon_filter == '2' ? 'selected' : ''; ?>>Platoon 2</option>
                                <option value="3" <?php echo $platoon_filter == '3' ? 'selected' : ''; ?>>Platoon 3</option>
                                <option value="4" <?php echo $platoon_filter == '4' ? 'selected' : ''; ?>>Platoon 4</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="company">Company:</label>
                            <select name="company" id="company">
                                <option value="">All Companies</option>
                                <option value="A" <?php echo $company_filter == 'A' ? 'selected' : ''; ?>>Company A</option>
                                <option value="B" <?php echo $company_filter == 'B' ? 'selected' : ''; ?>>Company B</option>
                                <option value="C" <?php echo $company_filter == 'C' ? 'selected' : ''; ?>>Company C</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="view_report.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $summary['unique_attendees'] ?? 0; ?></h3>
                        <p>Unique Attendees</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $summary['total_scans'] ?? 0; ?></h3>
                        <p>Total Scans</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $summary['active_days'] ?? 0; ?></h3>
                        <p>Active Days</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $summary['total_scans'] > 0 ? round($summary['total_scans'] / max($summary['active_days'], 1), 1) : 0; ?></h3>
                        <p>Avg Scans/Day</p>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h2>Platoon Attendance</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="platoonChart"></canvas>
                    </div>
                </div>
                <div class="content-card">
                    <div class="card-header">
                        <h2>Daily Attendance Trend</h2>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance Table -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Recent Attendance Records</h2>
                    <div class="card-actions">
                        <input type="text" id="attendanceSearch" placeholder="Search records..." class="search-input">
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cadet Name</th>
                                <th>Platoon</th>
                                <th>Company</th>
                                <th>Date</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <?php foreach($recent_attendance as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['platoon']); ?></td>
                                <td><?php echo htmlspecialchars($record['company']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($record['timestamp'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($record['timestamp'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-primary);
        border-radius: 8px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        background: var(--military-green);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .stat-content h3 {
        font-size: 2rem;
        font-weight: bold;
        color: var(--text-primary);
        margin: 0;
    }

    .stat-content p {
        color: var(--text-secondary);
        margin: 0;
        font-size: 0.9rem;
    }

    .filter-form {
        padding: 1.5rem;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        font-weight: 500;
        color: var(--text-primary);
    }

    .form-group input,
    .form-group select {
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border-primary);
        border-radius: 4px;
        color: var(--text-primary);
        font-size: 1rem;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--military-green);
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
    }

    .filter-actions {
        display: flex;
        gap: 1rem;
    }

    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-container {
        padding: 1.5rem;
        height: 300px;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        .charts-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-actions {
            flex-direction: column;
        }
    }
    </style>

    <script>
    // Search functionality
    document.getElementById('attendanceSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#attendanceTableBody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    // Export functionality
    function exportReport() {
        alert('Export functionality to be implemented');
    }

    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        // Platoon Chart
        const platoonCtx = document.getElementById('platoonChart').getContext('2d');
        const platoonData = <?php echo json_encode($platoon_data); ?>;
        
        new Chart(platoonCtx, {
            type: 'bar',
            data: {
                labels: platoonData.map(item => 'Platoon ' + item.platoon),
                datasets: [{
                    label: 'Attendees',
                    data: platoonData.map(item => item.attendees),
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });

        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const attendanceData = <?php echo json_encode($attendance_data); ?>;
        
        // Group by date
        const dailyData = {};
        attendanceData.forEach(item => {
            if (!dailyData[item.attendance_date]) {
                dailyData[item.attendance_date] = 0;
            }
            dailyData[item.attendance_date] += parseInt(item.total_attendees);
        });
        
        const sortedDates = Object.keys(dailyData).sort();
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: sortedDates.map(date => new Date(date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Attendance',
                    data: sortedDates.map(date => dailyData[date]),
                    borderColor: 'rgba(40, 167, 69, 1)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#ffffff'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
    });
    </script>

    <!-- Include mobile navigation -->
    <script src="../js/mobile-navigation.js"></script>
</body>
</html>
