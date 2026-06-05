<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/SecurityLogger.php';
check_login();

// Log profile access
if(isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $viewed_user_id = trim($_GET['id']);
    SecurityLogger::log('PROFILE_ACCESS', 'LOW', 'User accessed cadet profile', [
        'viewer_user_id' => $_SESSION['user_id'],
        'viewer_username' => $_SESSION['username'],
        'viewed_user_id' => $viewed_user_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
}

$cadet_info = null;
$user_info = null;
$attendance_stats = null;
$grade_stats = null;

if(isset($_GET['id']) && !empty(trim($_GET['id']))){
    $user_id = trim($_GET['id']);

    // Fetch cadet profile information
    $sql = "SELECT cp.*, u.username, u.email, u.role, u.created_at as registration_date 
            FROM cadet_profiles cp 
            JOIN users u ON cp.user_id = u.id 
            WHERE cp.user_id = ?";

    if($stmt = mysqli_prepare($link, $sql)){
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_num_rows($result) == 1){
                $cadet_info = mysqli_fetch_assoc($result);
                
                // Fetch attendance statistics
                $attendance_sql = "SELECT 
                    COUNT(*) as total_events,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                    FROM attendance WHERE cadet_id = ?";
                
                if($att_stmt = mysqli_prepare($link, $attendance_sql)){
                    mysqli_stmt_bind_param($att_stmt, "i", $user_id);
                    if(mysqli_stmt_execute($att_stmt)){
                        $att_result = mysqli_stmt_get_result($att_stmt);
                        $attendance_stats = mysqli_fetch_assoc($att_result);
                    }
                    mysqli_stmt_close($att_stmt);
                }
                
                // Fetch grade statistics
                $grade_sql = "SELECT 
                    COUNT(*) as total_grades,
                    AVG(CAST(grade AS DECIMAL(5,2))) as avg_grade,
                    MAX(CAST(grade AS DECIMAL(5,2))) as highest_grade,
                    MIN(CAST(grade AS DECIMAL(5,2))) as lowest_grade
                    FROM grades WHERE cadet_id = ? AND grade REGEXP '^[0-9]+\\.?[0-9]*$'";
                
                if($grade_stmt = mysqli_prepare($link, $grade_sql)){
                    mysqli_stmt_bind_param($grade_stmt, "i", $user_id);
                    if(mysqli_stmt_execute($grade_stmt)){
                        $grade_result = mysqli_stmt_get_result($grade_stmt);
                        $grade_stats = mysqli_fetch_assoc($grade_result);
                    }
                    mysqli_stmt_close($grade_stmt);
                }
                
            } else{
                $error = "No profile found.";
                SecurityLogger::log('PROFILE_ACCESS_FAILED', 'MEDIUM', 'Profile access failed - no profile found', [
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'requested_profile_id' => $user_id,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
            }
        } else{
            $error = "Error executing query.";
            SecurityLogger::log('DATABASE_ERROR', 'HIGH', 'Database error during profile access', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'requested_profile_id' => $user_id,
                'error' => mysqli_error($link),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Error preparing statement.";
    }
} else {
    $error = "No cadet ID provided.";
    SecurityLogger::log('INVALID_PROFILE_REQUEST', 'MEDIUM', 'Profile access attempted without cadet ID', [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
}

// Calculate attendance percentage
$attendance_percentage = 0;
if($attendance_stats && $attendance_stats['total_events'] > 0){
    $attendance_percentage = round(($attendance_stats['present_count'] / $attendance_stats['total_events']) * 100, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadet Profile - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-unified.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body data-role="<?php echo $_SESSION['role']; ?>">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-user-circle"></i></div>
                    <span>Cadet Profile</span>
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
                    <h1 class="page-title">Cadet Profile</h1>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                        <button class="action-btn" onclick="printProfile()" title="Print Profile">
                            <i class="fas fa-print"></i>
                        </button>
                        <button class="action-btn" onclick="exportProfile()" title="Export Profile">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="action-btn" onclick="editProfile()" title="Edit Profile">
                            <i class="fas fa-edit"></i>
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
                <?php if($cadet_info): ?>
                    <!-- Profile Header -->
                    <div class="profile-header">
                        <div class="profile-photo-container">
                            <img src="<?php echo htmlspecialchars($cadet_info['photo_path'] ?? 'uploads/photos/default.png'); ?>" 
                                 alt="Cadet Photo" class="profile-photo">
                            <div class="status-badge <?php echo strtolower($cadet_info['status']); ?>">
                                <i class="fas <?php echo ($cadet_info['status'] == 'Active') ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo htmlspecialchars($cadet_info['status']); ?>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h2 class="cadet-name">
                                <?php echo htmlspecialchars($cadet_info['first_name'] . ' ' . $cadet_info['last_name']); ?>
                            </h2>
                            <div class="cadet-details">
                                <div class="detail-item">
                                    <i class="fas fa-id-badge"></i>
                                    <span>ID: <?php echo htmlspecialchars($cadet_info['user_id']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <span>Platoon <?php echo htmlspecialchars($cadet_info['platoon'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-building"></i>
                                    <span>Company <?php echo htmlspecialchars($cadet_info['company'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Joined: <?php echo date('M j, Y', strtotime($cadet_info['registration_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="qr-code-container">
                            <div class="qr-code">
                                <img src="<?php echo htmlspecialchars($cadet_info['qr_code_path'] ?? 'uploads/qrcodes/default.png'); ?>" 
                                     alt="QR Code" class="qr-image">
                            </div>
                            <p class="qr-label">Scan for Quick Access</p>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $attendance_percentage; ?>%</div>
                                <div class="stat-label">Attendance Rate</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo round($grade_stats['avg_grade'] ?? 0, 1); ?>%</div>
                                <div class="stat-label">Average Grade</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo round($grade_stats['highest_grade'] ?? 0, 1); ?>%</div>
                                <div class="stat-label">Highest Grade</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $grade_stats['total_grades'] ?? 0; ?></div>
                                <div class="stat-label">Total Grades</div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Details -->
                    <div class="profile-content">
                        <!-- Personal Information -->
                        <div class="content-card">
                            <div class="card-header">
                                <h3><i class="fas fa-user"></i> Personal Information</h3>
                            </div>
                            <div class="card-content">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Full Name</label>
                                        <span><?php echo htmlspecialchars($cadet_info['first_name'] . ' ' . $cadet_info['last_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Email</label>
                                        <span><?php echo htmlspecialchars($cadet_info['email'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Phone</label>
                                        <span><?php echo htmlspecialchars($cadet_info['phone'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Date of Birth</label>
                                        <span><?php echo $cadet_info['date_of_birth'] ? date('M j, Y', strtotime($cadet_info['date_of_birth'])) : 'N/A'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Address</label>
                                        <span><?php echo htmlspecialchars($cadet_info['address'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Blood Type</label>
                                        <span><?php echo htmlspecialchars($cadet_info['blood_type'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Academic Information -->
                        <div class="content-card">
                            <div class="card-header">
                                <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                            </div>
                            <div class="card-content">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Company</label>
                                        <span><?php echo htmlspecialchars($cadet_info['company'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Platoon</label>
                                        <span><?php echo htmlspecialchars($cadet_info['platoon'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Year Level</label>
                                        <span><?php echo htmlspecialchars($cadet_info['year_level'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Course</label>
                                        <span><?php echo htmlspecialchars($cadet_info['course'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Status</label>
                                        <span class="status-text <?php echo strtolower($cadet_info['status']); ?>">
                                            <?php echo htmlspecialchars($cadet_info['status']); ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <label>Registration Date</label>
                                        <span><?php echo date('M j, Y', strtotime($cadet_info['registration_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="content-card">
                            <div class="card-header">
                                <h3><i class="fas fa-phone"></i> Emergency Contact</h3>
                            </div>
                            <div class="card-content">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <label>Contact Name</label>
                                        <span><?php echo htmlspecialchars($cadet_info['emergency_contact_name'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Relationship</label>
                                        <span><?php echo htmlspecialchars($cadet_info['emergency_contact_relationship'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Phone Number</label>
                                        <span><?php echo htmlspecialchars($cadet_info['emergency_contact_phone'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <label>Email</label>
                                        <span><?php echo htmlspecialchars($cadet_info['emergency_contact_email'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Summary -->
                        <?php if($attendance_stats): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <h3><i class="fas fa-calendar-check"></i> Attendance Summary</h3>
                            </div>
                            <div class="card-content">
                                <div class="attendance-summary">
                                    <div class="attendance-item present">
                                        <div class="attendance-count"><?php echo $attendance_stats['present_count']; ?></div>
                                        <div class="attendance-label">Present</div>
                                    </div>
                                    <div class="attendance-item late">
                                        <div class="attendance-count"><?php echo $attendance_stats['late_count']; ?></div>
                                        <div class="attendance-label">Late</div>
                                    </div>
                                    <div class="attendance-item absent">
                                        <div class="attendance-count"><?php echo $attendance_stats['absent_count']; ?></div>
                                        <div class="attendance-label">Absent</div>
                                    </div>
                                    <div class="attendance-item total">
                                        <div class="attendance-count"><?php echo $attendance_stats['total_events']; ?></div>
                                        <div class="attendance-label">Total Events</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="error-container">
                        <div class="error-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2>Profile Not Found</h2>
                        <p><?php echo htmlspecialchars($error ?? 'Could not retrieve cadet information.'); ?></p>
                        <button class="btn btn-primary" onclick="goBack()">
                            <i class="fas fa-arrow-left"></i> Go Back
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="js/dashboard-unified.js"></script>

    <script>
        function printProfile() {
            window.print();
        }

        function exportProfile() {
            // This would trigger a download of profile data
            alert('Export functionality coming soon!');
        }

        function editProfile() {
            <?php if($cadet_info): ?>
                window.location.href = 'edit_profile.php?id=<?php echo $cadet_info['user_id']; ?>';
            <?php endif; ?>
        }

        function goBack() {
            window.history.back();
        }
    </script>

    <style>
        .profile-header {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 2rem;
            padding: 2rem;
            background: var(--bg-primary);
            border-radius: 12px;
            border: 1px solid var(--border-primary);
            margin-bottom: 2rem;
            align-items: center;
        }

        .profile-photo-container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            position: absolute;
            bottom: 10px;
            right: 10px;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge.active {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid #22c55e;
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid #ef4444;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .cadet-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .cadet-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .detail-item i {
            color: var(--primary);
        }

        .qr-code-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .qr-code {
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border-primary);
        }

        .qr-image {
            width: 120px;
            height: 120px;
            display: block;
        }

        .qr-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: center;
            margin: 0;
        }

        .profile-content {
            display: grid;
            gap: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-item label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item span {
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .status-text.active {
            color: #22c55e;
        }

        .status-text.inactive {
            color: #ef4444;
        }

        .attendance-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }

        .attendance-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-primary);
        }

        .attendance-item.present {
            background: rgba(34, 197, 94, 0.05);
            border-color: #22c55e;
        }

        .attendance-item.late {
            background: rgba(251, 191, 36, 0.05);
            border-color: #28a745;
        }

        .attendance-item.absent {
            background: rgba(239, 68, 68, 0.05);
            border-color: #ef4444;
        }

        .attendance-item.total {
            background: rgba(59, 130, 246, 0.05);
            border-color: #3b82f6;
        }

        .attendance-count {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .attendance-item.present .attendance-count {
            color: #22c55e;
        }

        .attendance-item.late .attendance-count {
            color: #28a745;
        }

        .attendance-item.absent .attendance-count {
            color: #ef4444;
        }

        .attendance-item.total .attendance-count {
            color: #3b82f6;
        }

        .attendance-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
            text-align: center;
        }

        .error-icon {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .error-container h2 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .error-container p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .profile-header {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 1.5rem;
            }

            .cadet-details {
                justify-content: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .attendance-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media print {
            .sidebar,
            .header,
            .action-btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .profile-header {
                break-inside: avoid;
            }
        }
    </style>
</body>
</html>
