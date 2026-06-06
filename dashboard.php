<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

// Access control: Admin and basic users
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['admin', 'basic', 'basic_cadet', 'basic-cadet', 'cadet'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

$is_admin = rotc_role_in(['admin']);
$current_user_id = $_SESSION['user_id'];

// Pending registrations count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_registrations = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - ROTC Management System</title>
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
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                    <span class="logo-text">Admin Command</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="../admin_dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="home.php" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="fas fa-chart-bar"></i>
                            <span>Attendance Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../rifle_management.php" class="nav-link">
                            <i class="fas fa-gun"></i>
                            <span>Rifle Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../rifle_scanner.php" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Scanner</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../user_management.php" class="nav-link">
                            <i class="fas fa-users-cog"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin/missing_ids.php" class="nav-link">
                            <i class="fas fa-id-card-alt"></i>
                            <span>Missing IDs</span>
                            <?php if (isset($active_missing_ids) && $active_missing_ids > 0): ?>
                                <span class="badge badge-danger"><?php echo $active_missing_ids; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin_dashboard.php#registration-approvals" class="nav-link">
                            <i class="fas fa-user-check"></i>
                            <span>Registration Approvals</span>
                            <?php if (isset($pending_registrations) && $pending_registrations > 0): ?>
                                <span class="badge badge-warning"><?php echo $pending_registrations; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../advance_rotc_management.php" class="nav-link">
                            <i class="fas fa-user-graduate"></i>
                            <span>Advance Officer Respondents</span>
                            <?php if (isset($advance_officer_count) && $advance_officer_count > 0): ?>
                                <span class="badge badge-success"><?php echo $advance_officer_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../reports/view_report.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../announcements/view.php" class="nav-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../grades/manage_grades.php" class="nav-link">
                            <i class="fas fa-graduation-cap"></i>
                            <span>Grades</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="setup.php" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>System Setup</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="https_test.php" class="nav-link">
                            <i class="fas fa-lock"></i>
                            <span>HTTPS Setup</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../settings.php" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../user_settings.php" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            <span>User Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Attendance Dashboard</h1>
                        <p class="header-subtitle">Real-time attendance monitoring and analytics</p>
                    </div>
                    <div class="header-actions">
                        <select id="td-selector" class="qr-integration-btn" style="background: rgba(15, 20, 25, 0.95); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 10px; border-radius: 8px; margin-right: 10px;">
                            <!-- Options will be populated by JavaScript -->
                        </select>
                        <select id="semesterFilter" class="qr-integration-btn" style="background: rgba(15, 20, 25, 0.95); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 10px; border-radius: 8px; margin-right: 10px;">
                            <option value="1">1st Semester</option>
                            <option value="2">2nd Semester</option>
                        </select>
                        <input type="date" id="dateFilter" class="qr-integration-btn" style="background: rgba(15, 20, 25, 0.95); border: 1px solid var(--border-primary); color: var(--text-primary); padding: 10px; border-radius: 8px; margin-right: 10px;">
                        <button id="refresh-btn" class="qr-integration-btn">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div id="loading-state" class="loading" style="display: block;">
                <div class="loading-spinner"></div>
                <p>Loading attendance data...</p>
            </div>
            
            <!-- No Data State -->
            <div id="no-data-state" class="no-data" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <p>No attendance data found for the selected date.</p>
            </div>

            <!-- Dashboard Content -->
            <div id="dashboard-content" style="display: none;">
                <!-- Stats Grid -->
                <div class="stats-grid fade-in">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Total Strength</span>
                            <i class="fas fa-users stat-icon"></i>
                        </div>
                        <div class="stat-value" id="total-strength">0</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>Registered</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Present</span>
                            <i class="fas fa-user-check stat-icon"></i>
                        </div>
                        <div class="stat-value" id="total-present">0</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>Active</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Absent</span>
                            <i class="fas fa-user-times stat-icon"></i>
                        </div>
                        <div class="stat-value" id="total-absent">0</div>
                        <div class="stat-change negative">
                            <i class="fas fa-arrow-down"></i>
                            <span>Missing</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Attendance Rate</span>
                            <i class="fas fa-percentage stat-icon"></i>
                        </div>
                        <div class="stat-value" id="attendance-rate">0%</div>
                        <div class="stat-change positive">
                            <i class="fas fa-chart-line"></i>
                            <span>Overall</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Male Students</span>
                            <i class="fas fa-male stat-icon"></i>
                        </div>
                        <div class="stat-value" id="male-present">0</div>
                        <div class="stat-change positive">
                            <i class="fas fa-users"></i>
                            <span id="male-percentage">0%</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Female Students</span>
                            <i class="fas fa-female stat-icon"></i>
                        </div>
                        <div class="stat-value" id="female-present">0</div>
                        <div class="stat-change positive">
                            <i class="fas fa-users"></i>
                            <span id="female-percentage">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Analytics Dashboard Section -->
                <div class="analytics-dashboard fade-in">
                    <div class="analytics-header">
                        <h2 class="analytics-title">
                            <i class="fas fa-chart-line"></i>
                            Attendance Analytics
                        </h2>
                        <p class="analytics-subtitle">Comprehensive attendance monitoring and reporting system</p>
                    </div>
                    
                    <div class="analytics-grid">
                        <!-- Gender Distribution Card -->
                        <div class="analytics-card gender-card">
                            <div class="analytics-card-header">
                                <div class="card-title-section">
                                    <i class="fas fa-venus-mars card-icon"></i>
                                    <h3 class="card-title">Gender Distribution</h3>
                                </div>
                                <div class="card-badge">Live</div>
                            </div>
                            <div class="analytics-card-body">
                                <div class="gender-overview">
                                    <div class="gender-chart">
                                        <div class="gender-visual">
                                            <div class="gender-bar male-bar">
                                                <div class="bar-fill" id="male-bar-fill"></div>
                                                <span class="bar-label">Male</span>
                                            </div>
                                            <div class="gender-bar female-bar">
                                                <div class="bar-fill" id="female-bar-fill"></div>
                                                <span class="bar-label">Female</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="gender-stats-grid">
                                        <div class="stat-item">
                                            <span class="stat-label">Male Present</span>
                                            <span class="stat-value male-color" id="male-present-stat">0</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Female Present</span>
                                            <span class="stat-value female-color" id="female-present-stat">0</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Male Total</span>
                                            <span class="stat-value" id="male-strength">0</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Female Total</span>
                                            <span class="stat-value" id="female-strength">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Platoon Statistics Card -->
                        <div class="analytics-card platoon-card">
                            <div class="analytics-card-header">
                                <div class="card-title-section">
                                    <i class="fas fa-users-cog card-icon"></i>
                                    <h3 class="card-title">Platoon Performance</h3>
                                </div>
                                <div class="card-badge success">Active</div>
                            </div>
                            <div class="analytics-card-body">
                                <div class="platoon-overview">
                                    <div id="platoon-stats-container" class="platoon-stats-modern">
                                        <!-- Will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity Card -->
                        <div class="analytics-card activity-card full-width">
                            <div class="analytics-card-header">
                                <div class="card-title-section">
                                    <i class="fas fa-history card-icon"></i>
                                    <h3 class="card-title">Recent Activity</h3>
                                </div>
                                <div class="card-actions">
                                    <button class="action-btn" onclick="fetchAttendanceData()">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <div class="card-badge info">Real-time</div>
                                </div>
                            </div>
                            <div class="analytics-card-body">
                                <div class="activity-table-wrapper">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-clock"></i> Time</th>
                                                <th><i class="fas fa-id-card"></i> Student ID</th>
                                                <th><i class="fas fa-user"></i> Name</th>
                                                <th><i class="fas fa-users"></i> Platoon</th>
                                                <th><i class="fas fa-venus-mars"></i> Gender</th>
                                                <th><i class="fas fa-check-circle"></i> Status</th>
                                                <th><i class="fas fa-cog"></i> Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recent-attendance-table">
                                            <!-- Will be populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Attendance Lookup Card -->
                        <div class="analytics-card activity-card full-width">
                            <div class="analytics-card-header">
                                <div class="card-title-section">
                                    <i class="fas fa-search card-icon"></i>
                                    <h3 class="card-title">Look Up Attendance</h3>
                                </div>
                                <div class="card-actions">
                                    <button class="action-btn" onclick="clearLookupResults()">
                                        <i class="fas fa-times"></i> Clear
                                    </button>
                                    <div class="card-badge info">Search Records</div>
                                </div>
                            </div>
                            <div class="analytics-card-body">
                                <div class="lookup-search-section">
                                    <div class="search-form">
                                        <div class="search-inputs">
                                            <div class="input-group">
                                                <label for="searchName">Search by Name:</label>
                                                <input type="text" id="searchName" placeholder="Enter first name, last name, or full name" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="searchStudentId">Search by Student ID:</label>
                                                <input type="text" id="searchStudentId" placeholder="Enter student ID" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterDateFrom">Date From:</label>
                                                <input type="date" id="filterDateFrom" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterDateTo">Date To:</label>
                                                <input type="date" id="filterDateTo" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterTimeFrom">Time From:</label>
                                                <input type="time" id="filterTimeFrom" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterTimeTo">Time To:</label>
                                                <input type="time" id="filterTimeTo" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterTd">TD:</label>
                                                <input type="text" id="filterTd" placeholder="e.g., 1, 2, 3" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterSemester">Semester:</label>
                                                <select id="filterSemester" class="search-input">
                                                    <option value="">Any</option>
                                                    <option value="1">1st Semester</option>
                                                    <option value="2">2nd Semester</option>
                                                </select>
                                            </div>
                                            <div class="input-group">
                                                <label for="filterStatus">Status:</label>
                                                <select id="filterStatus" class="search-input">
                                                    <option value="">Any</option>
                                                    <option value="present">Present</option>
                                                    <option value="late">Late</option>
                                                    <option value="absent">Absent</option>
                                                </select>
                                            </div>
                                            <button class="lookup-btn" onclick="lookupAttendance()">
                                                <i class="fas fa-search"></i> Look Up Attendance
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="lookup-results" class="lookup-results" style="display: none;">
                                    <div class="results-header">
                                        <h4 id="results-title">Attendance Records</h4>
                                        <?php if ($is_admin): ?>
                                        <button class="add-btn" onclick="showAddAttendanceModal()">
                                            <i class="fas fa-plus"></i> Add Record
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-table-wrapper">
                                        <table class="modern-table">
                                            <thead>
                                                <tr>
                                                    <th><i class="fas fa-calendar"></i> Date</th>
                                                    <th><i class="fas fa-tag"></i> Event</th>
                                                    <th><i class="fas fa-user"></i> Cadet</th>
                                                    <th><i class="fas fa-clock"></i> Time</th>
                                                    <th><i class="fas fa-graduation-cap"></i> TD</th>
                                                    <th><i class="fas fa-calendar-alt"></i> Semester</th>
                                                    <th><i class="fas fa-check-circle"></i> Status</th>
                                                    <?php if ($is_admin): ?>
                                                    <th><i class="fas fa-cog"></i> Actions</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody id="lookup-attendance-table">
                                                <!-- Will be populated by JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Edit Attendance Modal -->
    <div id="editModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Edit Attendance Record</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="editAttendanceId">
                    <input type="hidden" id="editCadetId">
                    
                    <div class="form-group" id="cadetInfoGroup" style="display: none;">
                        <label>Cadet Information:</label>
                        <div class="cadet-info-display">
                            <span id="cadetNameDisplay"></span> (<span id="studentIdDisplay"></span>)
                        </div>
                    </div>
                    
                    <div class="form-group" id="cadetSelectGroup" style="display: none;">
                        <label for="editCadetSelect">Select Cadet:</label>
                        <select id="editCadetSelect" onchange="updateCadetId()">
                            <option value="">-- Select a Cadet --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editEventName">Event Name:</label>
                        <input type="text" id="editEventName" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editDate">Date:</label>
                        <input type="date" id="editDate" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editTimeIn">Time:</label>
                        <input type="time" id="editTimeIn" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editTd">Training Day (TD):</label>
                        <input type="text" id="editTd" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editSemester">Semester:</label>
                        <input type="text" id="editSemester" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editStatus">Status:</label>
                        <select id="editStatus" required>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="excused">Excused</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
            </div>
        </div>
    </div>
    
    <style>
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-primary);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.2rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-primary);
            border-radius: 6px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px rgba(var(--accent-primary-rgb), 0.2);
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-primary);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--accent-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--accent-secondary);
        }
        
        .btn-secondary {
            background: var(--card-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-primary);
        }
        
        .btn-secondary:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn-small {
            padding: 5px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .edit-btn {
            background: var(--accent-primary);
            color: white;
        }
        
        .edit-btn:hover {
            background: var(--accent-secondary);
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        /* Lookup Interface Styles */
        .lookup-search-section {
            margin-bottom: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .search-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .search-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .input-group label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }

        .search-input {
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .lookup-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            height: fit-content;
        }

        .lookup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .lookup-results {
            margin-top: 20px;
            border-top: 2px solid #e9ecef;
            padding-top: 20px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .results-header h4 {
            margin: 0;
            color: #495057;
            font-size: 18px;
        }

        .add-btn {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.4);
        }

        @media (max-width: 768px) {
            .search-inputs {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }

        /* Session Attendance Styles */
        .session-info {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .session-details {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .session-label {
            font-weight: 600;
            color: var(--text-primary);
        }

        .session-value {
            color: var(--primary-color);
            font-weight: 500;
            background: var(--primary-light);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }

        .session-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }

        .session-stat {
            text-align: center;
            padding: 10px;
            background: var(--bg-secondary);
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .session-stat-value {
            display: block;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 4px;
        }

        .session-stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
    
    <script src="dashboard.js"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
            } else {
                sidebar.classList.remove('collapsed');
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !sidebarToggle.contains(event.target) &&
                sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });
        
        function saveEdit() {
            const id = document.getElementById('editAttendanceId').value;
            const cadetId = document.getElementById('editCadetId').value;
            const eventName = document.getElementById('editEventName').value;
            const date = document.getElementById('editDate').value;
            const timeIn = document.getElementById('editTimeIn').value;
            const td = document.getElementById('editTd').value;
            const semester = document.getElementById('editSemester').value;
            const status = document.getElementById('editStatus').value;
            
            if (!eventName || !date || !timeIn || !td || !semester || !status) {
                alert('Please fill in all fields');
                return;
            }
            
            const formData = new FormData();
            
            if (id) {
                // Editing existing record
                formData.append('action', 'edit');
                formData.append('id', id);
            } else {
                // Adding new record
                formData.append('action', 'add');
                if (!cadetId) {
                    alert('Please select a cadet for the new attendance record.');
                    return;
                }
                formData.append('cadet_id', cadetId);
            }
            
            formData.append('event_name', eventName);
            formData.append('date', date);
            formData.append('time_in', timeIn);
            formData.append('td', td);
            formData.append('semester', semester);
            formData.append('status', status);
            
            fetch('../attendance/edit_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(id ? 'Attendance record updated successfully' : 'Attendance record added successfully');
                    closeEditModal();
                    
                    // Refresh the lookup results if we're in lookup mode
                    const lookupResults = document.getElementById('lookup-results');
                    if (lookupResults.style.display === 'block') {
                        lookupAttendance();
                    }
                    
                    // Refresh admin records if admin
                    const isAdmin = <?php echo json_encode($is_admin); ?>;
                    if (isAdmin) {
                        loadAttendanceRecords();
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error saving attendance:', error);
                alert('An error occurred while saving the record');
            });
        }
        
        // Lookup attendance functionality
        function lookupAttendance() {
            console.log('=== LOOKUP ATTENDANCE DEBUG START ===');
            
            const searchNameElement = document.getElementById('searchName');
            const searchStudentIdElement = document.getElementById('searchStudentId');
            
            console.log('Search name element:', searchNameElement);
            console.log('Search student ID element:', searchStudentIdElement);
            
            if (!searchNameElement || !searchStudentIdElement) {
                console.error('ERROR: Search input elements not found!');
                console.error('Looking for elements with IDs: searchName, searchStudentId');
                alert('Error: Search input elements not found. Please refresh the page.');
                return;
            }
            
            const searchName = searchNameElement.value.trim();
            const searchStudentId = searchStudentIdElement.value.trim();
            
            console.log('Search name value:', searchName);
            console.log('Search student ID value:', searchStudentId);
            
            // Allow global filter-only searches
            const df = document.getElementById('filterDateFrom').value;
            const dt = document.getElementById('filterDateTo').value;
            const tf = document.getElementById('filterTimeFrom').value;
            const tt = document.getElementById('filterTimeTo').value;
            const td = document.getElementById('filterTd').value;
            const sem = document.getElementById('filterSemester').value;
            const status = document.getElementById('filterStatus').value;
            if (!searchName && !searchStudentId && !df && !dt && !tf && !tt && !td && !sem && !status) {
                console.log('No criteria provided');
                alert('Please enter a name, student ID, or select at least one filter.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'lookup');
            if (searchName) formData.append('search_name', searchName);
            if (searchStudentId) formData.append('search_student_id', searchStudentId);
            if (df) formData.append('date_from', df);
            if (dt) formData.append('date_to', dt);
            if (tf) formData.append('time_from', tf);
            if (tt) formData.append('time_to', tt);
            if (td) formData.append('td', td);
            if (sem) formData.append('semester', sem);
            if (status) formData.append('status', status);
            
            console.log('FormData created:', {
                action: 'lookup',
                search_name: searchName,
                search_student_id: searchStudentId
            });
            
            console.log('Making fetch request to ../attendance/lookup_attendance.php');
            
            fetch('../attendance/lookup_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received:', response);
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return response.text();
            })
            .then(text => {
                console.log('Raw response text:', text);
                console.log('Response length:', text.length);
                
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON data:', data);
                    
                    if (data.success) {
                        console.log('Lookup successful, displaying results');
                        console.log('Records count:', data.records ? data.records.length : 'undefined');
                        console.log('Cadet info:', data.cadet_info);
                        displayLookupResults(data.records, data.cadet_info);
                    } else {
                        console.error('Lookup failed:', data.message);
                        alert(data.message || 'Error looking up attendance records.');
                        document.getElementById('lookup-results').style.display = 'none';
                    }
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Raw text that failed to parse:', text);
                    console.error('First 500 chars of response:', text.substring(0, 500));
                    alert('Error: Invalid response from server. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                console.error('Error details:', {
                    name: error.name,
                    message: error.message,
                    stack: error.stack
                });
                alert('Error looking up attendance records. Check console for details.');
            })
            .finally(() => {
                console.log('=== LOOKUP ATTENDANCE DEBUG END ===');
            });
        }
        
        function displayLookupResults(records, cadetInfo) {
            const resultsDiv = document.getElementById('lookup-results');
            const resultsTitle = document.getElementById('results-title');
            const tableBody = document.getElementById('lookup-attendance-table');
            
            if (cadetInfo && cadetInfo.full_name) {
                resultsTitle.textContent = `Attendance Records for ${cadetInfo.full_name} (${cadetInfo.student_id})`;
            } else {
                resultsTitle.textContent = 'Attendance Records';
            }
            
            if (records.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #6c757d;">No attendance records found.</td></tr>';
            } else {
                tableBody.innerHTML = records.map(record => {
                    const isAdmin = <?php echo json_encode($is_admin); ?>;
                    const actionsColumn = isAdmin ? `
                        <td>
                            <button class="edit-btn" onclick="editLookupAttendance(${record.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="delete-btn" onclick="deleteLookupAttendance(${record.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    ` : '';
                    
                    return `
                        <tr>
                            <td>${record.date}</td>
                            <td>${record.event_name}</td>
                            <td>${record.cadet_name || ''}</td>
                            <td>${record.time}</td>
                            <td>${record.td}</td>
                            <td>${record.semester}</td>
                            <td><span class="status-badge ${record.status.toLowerCase()}">${record.status}</span></td>
                            ${actionsColumn}
                        </tr>
                    `;
                }).join('');
            }
            
            resultsDiv.style.display = 'block';
        }
        
        function clearLookupResults() {
            document.getElementById('searchName').value = '';
            document.getElementById('searchStudentId').value = '';
            document.getElementById('lookup-results').style.display = 'none';
            document.getElementById('lookup-attendance-table').innerHTML = '';
        }
        
        function editLookupAttendance(attendanceId) {
            // Fetch attendance record details
            fetch('../attendance/get_attendance_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${attendanceId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate the edit modal with the record data
                    document.getElementById('editAttendanceId').value = data.record.id;
                    document.getElementById('editCadetId').value = data.record.cadet_id;
                    document.getElementById('editEventName').value = data.record.event_name;
                    document.getElementById('editDate').value = data.record.date;
                    document.getElementById('editTimeIn').value = data.record.time;
                    document.getElementById('editTd').value = data.record.td;
                    document.getElementById('editSemester').value = data.record.semester;
                    document.getElementById('editStatus').value = data.record.status;
                    
                    // Show cadet information and hide cadet select
                    document.getElementById('cadetNameDisplay').textContent = data.record.cadet_name;
                    document.getElementById('studentIdDisplay').textContent = data.record.student_id;
                    document.getElementById('cadetInfoGroup').style.display = 'block';
                    document.getElementById('cadetSelectGroup').style.display = 'none';
                    
                    // Update modal title
                    document.getElementById('modalTitle').textContent = 'Edit Attendance Record';
                    
                    // Show the modal
                    document.getElementById('editModal').style.display = 'block';
                } else {
                    alert('Error fetching attendance record.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error fetching attendance record.');
            });
        }
        
        function deleteLookupAttendance(attendanceId) {
            if (confirm('Are you sure you want to delete this attendance record?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', attendanceId);
                
                fetch('../attendance/edit_attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Attendance record deleted successfully.');
                        // Refresh the lookup results
                        lookupAttendance();
                    } else {
                        alert(data.message || 'Error deleting attendance record.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting attendance record.');
                });
            }
        }
        
        function showAddAttendanceModal() {
            // Clear the edit modal and set it for adding new record
            document.getElementById('editAttendanceId').value = '';
            document.getElementById('editCadetId').value = '';
            document.getElementById('editEventName').value = '';
            document.getElementById('editDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('editTimeIn').value = new Date().toTimeString().slice(0,5);
            document.getElementById('editTd').value = '';
            document.getElementById('editSemester').value = '';
            document.getElementById('editStatus').value = 'present';
            document.getElementById('editCadetSelect').value = '';
            
            // Hide cadet information section and show cadet select for new records
            document.getElementById('cadetInfoGroup').style.display = 'none';
            document.getElementById('cadetSelectGroup').style.display = 'block';
            
            // Load cadets for selection
            loadCadetsForSelection();
            
            // Update modal title
            document.getElementById('modalTitle').textContent = 'Add New Attendance Record';
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function loadCadetsForSelection() {
            fetch('../attendance/get_cadets.php')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('editCadetSelect');
                select.innerHTML = '<option value="">-- Select a Cadet --</option>';
                
                if (data.success && data.cadets) {
                    data.cadets.forEach(cadet => {
                        const option = document.createElement('option');
                        option.value = cadet.cadet_id;
                        option.textContent = `${cadet.full_name} (${cadet.student_id})`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading cadets:', error);
            });
        }
        
        function updateCadetId() {
            const select = document.getElementById('editCadetSelect');
            document.getElementById('editCadetId').value = select.value;
        }
        
        // Session Attendance Functions
        function loadSessionAttendance() {
            // Use QR/session.php which hosts the JSON endpoints
            fetch('QR/session.php?action=get_session_attendance')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateSessionDisplay(data.session, data.attendance, data.stats);
                    } else {
                        console.error('Failed to load session attendance:', data.message);
                        document.getElementById('current-session').textContent = 'No active session';
                        document.getElementById('session-badge').textContent = 'No Session';
                        document.getElementById('session-badge').className = 'card-badge warning';
                    }
                })
                .catch(error => {
                    console.error('Error loading session attendance:', error);
                    document.getElementById('current-session').textContent = 'Error loading session';
                    document.getElementById('session-badge').textContent = 'Error';
                    document.getElementById('session-badge').className = 'card-badge danger';
                });
        }
        
        function updateSessionDisplay(session, attendance, stats) {
            // Update session info
            if (session && session.td && session.semester) {
                document.getElementById('current-session').textContent = `TD ${session.td} - ${session.semester} Semester`;
                document.getElementById('session-badge').textContent = 'Active Session';
                document.getElementById('session-badge').className = 'card-badge success';
                
                // Update session stats
                const statsHtml = `
                    <div class="session-stat">
                        <span class="session-stat-value">${stats.total || 0}</span>
                        <span class="session-stat-label">Total</span>
                    </div>
                    <div class="session-stat">
                        <span class="session-stat-value">${stats.present || 0}</span>
                        <span class="session-stat-label">Present</span>
                    </div>
                    <div class="session-stat">
                        <span class="session-stat-value">${stats.absent || 0}</span>
                        <span class="session-stat-label">Absent</span>
                    </div>
                    <div class="session-stat">
                        <span class="session-stat-value">${stats.attendance_rate || '0%'}</span>
                        <span class="session-stat-label">Rate</span>
                    </div>
                `;
                document.getElementById('session-stats').innerHTML = statsHtml;
                
                // Update attendance table
                populateSessionAttendanceTable(attendance);
            } else {
                document.getElementById('current-session').textContent = 'No active session';
                document.getElementById('session-badge').textContent = 'No Session';
                document.getElementById('session-badge').className = 'card-badge warning';
                document.getElementById('session-stats').innerHTML = '';
                document.getElementById('session-attendance-table').innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 20px;">No session data available</td></tr>';
            }
        }
        
        function populateSessionAttendanceTable(attendance) {
            const tbody = document.getElementById('session-attendance-table');
            
            if (!attendance || attendance.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 20px;">No attendance records for this session</td></tr>';
                return;
            }
            
            tbody.innerHTML = attendance.map(record => {
                const timeIn = new Date(record.time_in);
                const timeStr = timeIn.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const dateStr = timeIn.toLocaleDateString();
                
                return `
                    <tr>
                        <td>${timeStr}</td>
                        <td>${record.student_id}</td>
                        <td>${record.full_name}</td>
                        <td>${record.platoon || 'N/A'}</td>
                        <td>${record.gender || 'N/A'}</td>
                        <td><span class="status-badge status-${record.status}">${record.status}</span></td>
                        <td>${dateStr}</td>
                    </tr>
                `;
            }).join('');
        }

        // Load attendance records on page load (for admin)
        document.addEventListener('DOMContentLoaded', function() {
            const isAdmin = <?php echo json_encode($is_admin); ?>;
            if (isAdmin) {
                loadAttendanceRecords();
            }
            
            // Load session attendance for all users
            loadSessionAttendance();
            
            // Auto-refresh session attendance every 30 seconds
            setInterval(loadSessionAttendance, 30000);
        });
    </script>
    
    <!-- Include mobile navigation -->
    <script src="../js/mobile-navigation.js"></script>
</body>
</html>
