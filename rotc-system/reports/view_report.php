<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

if(!in_array($_SESSION['role'], ['admin', 'instructor', 'officer', '1cl', '2cl', 'commandant'])){
    die('Access Denied.');
}

$report_type = $_GET['report_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$platoon_filter = $_GET['platoon'] ?? '';
$company_filter = $_GET['company'] ?? '';

$report_data = [];
$report_title = 'Invalid Report';
$report_stats = [];

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$param_types = '';

if($date_from && $date_to){
    if($report_type == 'attendance'){
        $where_conditions[] = "a.log_date BETWEEN ? AND ?";
    } else {
        $where_conditions[] = "g.created_at BETWEEN ? AND ?";
    }
    $params[] = $date_from;
    $params[] = $date_to;
    $param_types .= 'ss';
}

if($platoon_filter){
    $where_conditions[] = "p.platoon = ?";
    $params[] = $platoon_filter;
    $param_types .= 's';
}

if($company_filter){
    $where_conditions[] = "p.company = ?";
    $params[] = $company_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

if($report_type == 'attendance'){
    $report_title = 'Attendance Report';
    
    // Get attendance data
    $sql = "SELECT p.first_name, p.last_name, p.company, p.platoon, a.log_date, a.status, a.event_name, u.username as recorded_by
            FROM attendance a
            JOIN cadet_profiles p ON a.cadet_id = p.user_id
            JOIN users u ON a.recorded_by = u.id
            $where_clause
            ORDER BY p.last_name, p.first_name, a.log_date";
    
    if($stmt = mysqli_prepare($link, $sql)){
        if(!empty($params)){
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
        }
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($result)){
                $report_data[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get attendance statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        COUNT(DISTINCT a.cadet_id) as unique_cadets,
        COUNT(DISTINCT a.event_name) as unique_events
        FROM attendance a
        JOIN cadet_profiles p ON a.cadet_id = p.user_id
        $where_clause";
    
    if($stats_stmt = mysqli_prepare($link, $stats_sql)){
        if(!empty($params)){
            mysqli_stmt_bind_param($stats_stmt, $param_types, ...$params);
        }
        if(mysqli_stmt_execute($stats_stmt)){
            $stats_result = mysqli_stmt_get_result($stats_stmt);
            $report_stats = mysqli_fetch_assoc($stats_result);
        }
        mysqli_stmt_close($stats_stmt);
    }
    
} elseif($report_type == 'cadet_summary'){
    $report_title = 'AER LIST OF BENEFICIARIES - 2nd Semester S.Y. 2024-25';
    
    // Get cadet profiles data for MS-2 basic cadets
    $sql = "SELECT p.first_name, p.last_name, p.platoon, p.student_id, p.course, p.year_level, p.contact_number, p.email, u.username
            FROM cadet_profiles p
            JOIN users u ON p.user_id = u.id
            WHERE p.year_level = 'MS2'
            $where_clause
            ORDER BY p.platoon, p.last_name, p.first_name";
    
    if($stmt = mysqli_prepare($link, $sql)){
        if(!empty($params)){
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
        }
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($result)){
                $report_data[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get cadet statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_cadets,
        COUNT(DISTINCT p.platoon) as total_platoons,
        COUNT(DISTINCT p.course) as total_courses
        FROM cadet_profiles p
        JOIN users u ON p.user_id = u.id
        WHERE p.year_level = 'MS2'
        $where_clause";
    
    if($stats_stmt = mysqli_prepare($link, $stats_sql)){
        if(!empty($params)){
            mysqli_stmt_bind_param($stats_stmt, $param_types, ...$params);
        }
        if(mysqli_stmt_execute($stats_stmt)){
            $stats_result = mysqli_stmt_get_result($stats_stmt);
            $report_stats = mysqli_fetch_assoc($stats_result);
        }
        mysqli_stmt_close($stats_stmt);
    }
    
} elseif($report_type == 'grades'){
    $report_title = 'Grades Report';
    
    // Get grades data
    $sql = "SELECT p.first_name, p.last_name, p.company, p.platoon, g.event_name, g.grade, g.comments, g.created_at, u.username as recorded_by
            FROM grades g
            JOIN cadet_profiles p ON g.cadet_id = p.user_id
            JOIN users u ON g.recorded_by = u.id
            $where_clause
            ORDER BY p.last_name, p.first_name, g.created_at";
    
    if($stmt = mysqli_prepare($link, $sql)){
        if(!empty($params)){
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
        }
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_assoc($result)){
                $report_data[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get grade statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_grades,
        AVG(CAST(g.grade AS DECIMAL(5,2))) as avg_grade,
        MAX(CAST(g.grade AS DECIMAL(5,2))) as highest_grade,
        MIN(CAST(g.grade AS DECIMAL(5,2))) as lowest_grade,
        COUNT(DISTINCT g.cadet_id) as unique_cadets,
        COUNT(DISTINCT g.event_name) as unique_events
        FROM grades g
        JOIN cadet_profiles p ON g.cadet_id = p.user_id
        $where_clause
        AND g.grade REGEXP '^[0-9]+\\.?[0-9]*$'";
    
    if($stats_stmt = mysqli_prepare($link, $stats_sql)){
        if(!empty($params)){
            mysqli_stmt_bind_param($stats_stmt, $param_types, ...$params);
        }
        if(mysqli_stmt_execute($stats_stmt)){
            $stats_result = mysqli_stmt_get_result($stats_stmt);
            $report_stats = mysqli_fetch_assoc($stats_result);
        }
        mysqli_stmt_close($stats_stmt);
    }
}

// Calculate additional statistics
if($report_type == 'attendance' && $report_stats){
    $report_stats['attendance_rate'] = $report_stats['total_records'] > 0 ? 
        round(($report_stats['present_count'] / $report_stats['total_records']) * 100, 1) : 0;
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report_title); ?> - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-unified.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body data-role="<?php echo $_SESSION['role']; ?>">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-file-alt"></i></div>
                    <span>Report Viewer</span>
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
                    <h1 class="page-title"><?php echo htmlspecialchars($report_title); ?></h1>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                        <button class="action-btn no-print" onclick="printReport()" title="Print Report">
                            <i class="fas fa-print"></i>
                        </button>
                        <button class="action-btn no-print" onclick="exportReport()" title="Export Report">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="action-btn no-print" onclick="shareReport()" title="Share Report">
                            <i class="fas fa-share"></i>
                        </button>
                        <button class="action-btn no-print" onclick="goBack()" title="Back to Reports">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="user-menu">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Report Header -->
                <div class="report-header">
                    <div class="report-info">
                        <h2><?php echo htmlspecialchars($report_title); ?></h2>
                        <div class="report-meta">
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Generated: <?php echo date('M j, Y \a\t g:i A'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span>By: <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            </div>
                            <?php if($date_from && $date_to): ?>
                            <div class="meta-item">
                                <i class="fas fa-filter"></i>
                                <span>Period: <?php echo date('M j, Y', strtotime($date_from)) . ' - ' . date('M j, Y', strtotime($date_to)); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if($platoon_filter): ?>
                            <div class="meta-item">
                                <i class="fas fa-users"></i>
                                <span>Platoon: <?php echo htmlspecialchars($platoon_filter); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="report-logo">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <?php if($report_stats): ?>
                <div class="stats-grid">
                    <?php if($report_type == 'attendance'): ?>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($report_stats['total_records']); ?></div>
                                <div class="stat-label">Total Records</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $report_stats['attendance_rate']; ?>%</div>
                                <div class="stat-label">Attendance Rate</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($report_stats['unique_cadets']); ?></div>
                                <div class="stat-label">Cadets</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($report_stats['unique_events']); ?></div>
                                <div class="stat-label">Events</div>
                            </div>
                        </div>
                    <?php elseif($report_type == 'grades'): ?>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($report_stats['total_grades']); ?></div>
                                <div class="stat-label">Total Grades</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo round($report_stats['avg_grade'] ?? 0, 1); ?>%</div>
                                <div class="stat-label">Average Grade</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo round($report_stats['highest_grade'] ?? 0, 1); ?>%</div>
                                <div class="stat-label">Highest Grade</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($report_stats['unique_cadets']); ?></div>
                                <div class="stat-label">Cadets</div>
                            </div>
                        </div>
                    <?php elseif($report_type == 'cadet_summary'): ?>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($report_stats['total_cadets']); ?></div>
                                <div class="stat-label">Total Cadets</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($report_stats['total_courses']); ?></div>
                                <div class="stat-label">Courses</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo number_format($report_stats['total_platoons']); ?></div>
                                <div class="stat-label">Platoons</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Report Data -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>
                            <i class="fas <?php echo $report_type == 'attendance' ? 'fa-calendar-check' : ($report_type == 'cadet_summary' ? 'fa-users' : 'fa-graduation-cap'); ?>"></i>
                            <?php echo htmlspecialchars($report_title); ?> Data
                        </h3>
                        <div class="card-actions no-print">
                            <button class="btn btn-sm" onclick="toggleFilters()">
                                <i class="fas fa-filter"></i> Filters
                            </button>
                            <button class="btn btn-sm" onclick="exportTableData()">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-content">
                        <?php if(!empty($report_data)): ?>
                            <div class="table-container">
                                <table class="data-table" id="reportTable">
                                    <thead>
                                        <?php if($report_type == 'attendance'): ?>
                                            <tr>
                                                <th>Cadet Name</th>
                                                <th>Company</th>
                                                <th>Platoon</th>
                                                <th>Event</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Recorded By</th>
                                            </tr>
                                        <?php elseif($report_type == 'grades'): ?>
                                            <tr>
                                                <th>Cadet Name</th>
                                                <th>Company</th>
                                                <th>Platoon</th>
                                                <th>Event</th>
                                                <th>Grade</th>
                                                <th>Comments</th>
                                                <th>Date Recorded</th>
                                                <th>Recorded By</th>
                                            </tr>
                                        <?php elseif($report_type == 'cadet_summary'): ?>
                                            <tr>
                                                <th>Cadet Name</th>
                                                <th>Student ID</th>
                                                <th>Platoon</th>
                                                <th>Year Level</th>
                                                <th>Course</th>
                                                <th>Contact Number</th>
                                                <th>Email</th>
                                                <th>Username</th>
                                            </tr>
                                        <?php endif; ?>
                                    </thead>
                                    <tbody>
                                        <?php foreach($report_data as $row): ?>
                                            <tr>
                                            <?php if($report_type == 'attendance'): ?>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['company'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['platoon'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['event_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($row['log_date'])); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower($row['status']); ?>">
                                                        <?php echo htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['recorded_by']); ?></td>
                                            <?php elseif($report_type == 'grades'): ?>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['company'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['platoon'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['event_name']); ?></td>
                                                <td>
                                                    <span class="grade-badge">
                                                        <?php echo htmlspecialchars($row['grade']); ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['comments'] ?? '-'); ?></td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($row['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['recorded_by']); ?></td>
                                            <?php elseif($report_type == 'cadet_summary'): ?>
                                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['student_id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['platoon'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['year_level'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['course'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['username'] ?? 'N/A'); ?></td>
                                            <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <h3>No Data Available</h3>
                                <p>No data found for the selected criteria. Try adjusting your filters or date range.</p>
                                <button class="btn btn-primary" onclick="goBack()">
                                    <i class="fas fa-arrow-left"></i> Back to Reports
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../js/dashboard-unified.js"></script>

    <script>
        function printReport() {
            window.print();
        }

        function exportReport() {
            // This would trigger a download of the full report
            alert('Full report export functionality coming soon!');
        }

        function shareReport() {
            // This would open a share dialog
            alert('Share functionality coming soon!');
        }

        function goBack() {
            window.location.href = 'generate_report.php';
        }

        function toggleFilters() {
            alert('Advanced filtering coming soon!');
        }

        function exportTableData() {
            // Convert table to CSV
            const table = document.getElementById('reportTable');
            let csv = [];
            
            // Get headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent.trim());
            });
            csv.push(headers.join(','));
            
            // Get data rows
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = [];
                tr.querySelectorAll('td').forEach(td => {
                    row.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
                });
                csv.push(row.join(','));
            });
            
            // Download CSV
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = '<?php echo strtolower(str_replace(' ', '_', $report_title)); ?>_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>

    <style>
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            background: var(--bg-primary);
            border-radius: 12px;
            border: 1px solid var(--border-primary);
            margin-bottom: 2rem;
        }

        .report-info h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0 0 1rem 0;
        }

        .report-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .meta-item i {
            color: var(--primary);
        }

        .report-logo {
            font-size: 3rem;
            color: var(--primary);
            opacity: 0.1;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-primary);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-primary);
        }

        .data-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .data-table tbody tr:hover {
            background: var(--bg-hover);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.present {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }

        .status-badge.late {
            background: rgba(251, 191, 36, 0.1);
            color: #28a745;
        }

        .status-badge.absent {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .grade-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
            text-align: center;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .report-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .report-meta {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media print {
            .sidebar,
            .header,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .report-header {
                break-inside: avoid;
            }

            .data-table {
                font-size: 0.75rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }
        }
    </style>
</body>
</html>
