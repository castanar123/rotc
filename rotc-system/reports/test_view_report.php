<?php
// Test version without authentication
require_once '../includes/db.php';

$report_type = $_GET['report_type'] ?? 'cadet_summary';
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

if($report_type == 'cadet_summary'){
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
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report_title); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .stats { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .no-data { text-align: center; color: #666; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars($report_title); ?></h1>
        <p>Generated on: <?php echo date('F j, Y'); ?></p>
    </div>
    
    <?php if($report_stats): ?>
    <div class="stats">
        <h3>Report Statistics</h3>
        <?php if($report_type == 'cadet_summary'): ?>
            <p><strong>Total Cadets:</strong> <?php echo $report_stats['total_cadets']; ?></p>
            <p><strong>Total Platoons:</strong> <?php echo $report_stats['total_platoons']; ?></p>
            <p><strong>Total Courses:</strong> <?php echo $report_stats['total_courses']; ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if(!empty($report_data)): ?>
        <table>
            <thead>
                <tr>
                    <?php if($report_type == 'cadet_summary'): ?>
                        <th>Name</th>
                        <th>Student ID</th>
                        <th>Platoon</th>
                        <th>Course</th>
                        <th>Year Level</th>
                        <th>Contact Number</th>
                        <th>Email</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach($report_data as $row): ?>
                    <tr>
                        <?php if($report_type == 'cadet_summary'): ?>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['platoon']); ?></td>
                            <td><?php echo htmlspecialchars($row['course']); ?></td>
                            <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">No data found for the selected criteria.</p>
    <?php endif; ?>
</body>
</html>