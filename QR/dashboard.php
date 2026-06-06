<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

// Access control: Admin and basic users
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'basic'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

$is_admin = $_SESSION['role'] === 'admin';
$current_user_id = $_SESSION['user_id'];

// Pending registrations count (cadet roles awaiting approval)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE approval_status = 'pending' AND role IN ('basic_cadet','2cl','1cl')");
$pending_registrations = (int)($stmt->fetch()['total'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - ROTC Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '..';
            include __DIR__ . '/../includes/admin_nav.php';
        ?>
        
        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation (same style as admin pages) -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-align-left"></i>
                    </button>
                    <div class="ms-auto">
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                        <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </nav>
            <div class="container-fluid mt-4">
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
                        <div class="analytics-card activity-card full-width" id="lookup-card">
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
                                                <input type="text" id="searchName" placeholder="Enter first name, last name, or full name" class="search-input" list="nameSuggestions">
                                            </div>
                                            <div class="input-group">
                                                <label for="searchStudentId">Search by Student ID:</label>
                                                <input type="text" id="searchStudentId" placeholder="Enter student ID" class="search-input" list="idSuggestions">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterDate">Date:</label>
                                                <input type="date" id="filterDate" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterTd">TD:</label>
                                                <input type="text" id="filterTd" placeholder="e.g., 1, 2, 3" class="search-input">
                                            </div>
                                            <div class="input-group">
                                                <label for="filterPlatoon">Platoon:</label>
                                                <input type="text" id="filterPlatoon" placeholder="e.g., Alpha" class="search-input" list="platoonSuggestions">
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
                                                <label for="filterGender">Gender:</label>
                                                <select id="filterGender" class="search-input">
                                                    <option value="">Any</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
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
                                            <div class="input-group">
                                                <label>Exact Match:</label>
                                                <div style="display:flex; gap:10px; align-items:center;">
                                                    <label style="display:flex; gap:6px; align-items:center;">
                                                        <input type="checkbox" id="exactName"> Name
                                                    </label>
                                                    <label style="display:flex; gap:6px; align-items:center;">
                                                        <input type="checkbox" id="exactId"> Student ID
                                                    </label>
                                                </div>
                                            </div>
                                            <button class="lookup-btn" onclick="lookupAttendance()">
                                                <i class="fas fa-search"></i> Look Up Attendance
                                            </button>
                                        </div>
                                        <!-- Typeahead datalists -->
                                        <datalist id="nameSuggestions"></datalist>
                                        <datalist id="idSuggestions"></datalist>
                                        <datalist id="platoonSuggestions"></datalist>
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
            </div>
        </div>
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
        /* Align lookup search grid with admin dashboard cards */
        .lookup-search-section .search-form .search-inputs {
            display: grid;
            grid-template-columns: repeat(4, minmax(220px, 1fr));
            gap: 12px;
            align-items: end;
        }
        @media (max-width: 1200px) {
            .lookup-search-section .search-form .search-inputs {
                grid-template-columns: repeat(3, minmax(220px, 1fr));
            }
        }
        @media (max-width: 992px) {
            .lookup-search-section .search-form .search-inputs {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }
        @media (max-width: 576px) {
            .lookup-search-section .search-form .search-inputs {
                grid-template-columns: 1fr;
            }
        }

        /* Dark wrapper for lookup section */
        .lookup-search-section {
            background: rgba(10, 14, 18, 0.9);
            border: 1px solid var(--border-primary);
            border-radius: 12px;
            padding: 16px;
        }
        #lookup-card {
            background: rgba(12, 16, 21, 0.95);
        }
        #lookup-card .analytics-card-header {
            background: rgba(15, 20, 25, 0.95);
            border-bottom: 1px solid var(--border-primary);
        }
        #lookup-card .analytics-card-body {
            background: transparent !important;
        }
        /* Dark table styles inside lookup card */
        #lookup-card .modern-table {
            background: transparent;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        #lookup-card .modern-table thead th {
            background: rgba(15, 20, 25, 0.95);
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-primary);
        }
        #lookup-card .modern-table tbody tr {
            background: rgba(15, 20, 25, 0.6);
        }
        #lookup-card .modern-table tbody tr:hover {
            background: rgba(0, 255, 136, 0.08);
        }
        #lookup-card .modern-table td,
        #lookup-card .modern-table th {
            color: var(--text-primary);
            border-color: var(--border-primary);
        }

        /* Dark theme styles for lookup inputs */
        .lookup-search-section .input-group label {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 6px;
            display: inline-block;
        }
        .lookup-search-section .search-input {
            background: rgba(15, 20, 25, 0.95);
            color: var(--text-primary);
            border: 1px solid var(--border-primary);
            border-radius: 8px;
            padding: 10px 12px;
            height: 40px;
            width: 100%;
        }
        .lookup-search-section .search-input::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }
        .lookup-search-section .search-input:focus {
            outline: none;
            border-color: #00ff88;
            box-shadow: 0 0 0 2px rgba(0, 255, 136, 0.15);
        }
        .lookup-search-section .lookup-btn {
            align-self: end;
            height: 40px;
            background: linear-gradient(135deg, #00ff88 0%, #00cc6a 100%);
            color: #0b0f14;
            border: none;
            border-radius: 8px;
            padding: 0 14px;
            font-weight: 700;
            letter-spacing: 0.3px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        .lookup-search-section .lookup-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(0, 255, 136, 0.2);
        }
    </style>
    
    <script src="dashboard.js?v=2"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/mobile-navigation.js"></script>
    <script>
        // Sidebar toggle functionality
        const toggleBtn = document.getElementById('sidebarCollapse') || document.getElementById('sidebarToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('mobile-open');
                } else {
                    sidebar.classList.toggle('collapsed');
                }
            });
        }

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
            const sidebarToggle = document.getElementById('sidebarCollapse') || document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !sidebarToggle.contains(event.target) &&
                sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });
        
        // (Removed) Admin Attendance Management Functions
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
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
                    // Removed admin management auto-refresh (management section removed)
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error saving attendance:', error);
                alert('An error occurred while saving the record');
            });
        }
        
        // (Removed) deleteAttendance used by admin-only table
        
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
            // Read optional filters
            const d = document.getElementById('filterDate') ? document.getElementById('filterDate').value : '';
            const td = document.getElementById('filterTd') ? document.getElementById('filterTd').value : '';
            const sem = document.getElementById('filterSemester') ? document.getElementById('filterSemester').value : '';
            const status = document.getElementById('filterStatus') ? document.getElementById('filterStatus').value : '';
            const platoon = document.getElementById('filterPlatoon') ? document.getElementById('filterPlatoon').value : '';
            const gender = document.getElementById('filterGender') ? document.getElementById('filterGender').value : '';
            const exactName = document.getElementById('exactName') ? document.getElementById('exactName').checked : false;
            const exactId = document.getElementById('exactId') ? document.getElementById('exactId').checked : false;
            
            if (!searchName && !searchStudentId && !d && !td && !sem && !status && !platoon && !gender) {
                console.log('No criteria provided');
                alert('Please enter a name, student ID, or select at least one filter.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'lookup');
            if (searchName) formData.append('search_name', searchName);
            if (searchStudentId) formData.append('search_student_id', searchStudentId);
            if (d) formData.append('date', d);
            if (td) formData.append('td', td);
            if (sem) formData.append('semester', sem);
            if (status) formData.append('status', status);
            if (platoon) formData.append('platoon', platoon);
            if (gender) formData.append('gender', gender);
            if (exactName) formData.append('exact_name', '1');
            if (exactId) formData.append('exact_id', '1');
            
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
            const ids = ['searchName','searchStudentId','filterDate','filterTd','filterSemester','filterStatus','filterPlatoon','filterGender'];
            ids.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            const cbx = ['exactName','exactId'];
            cbx.forEach(id => { const el = document.getElementById(id); if (el) el.checked = false; });
            document.getElementById('lookup-results').style.display = 'none';
            document.getElementById('lookup-attendance-table').innerHTML = '';
        }

        // --- Typeahead suggestions ---
        function debounce(fn, delay = 200) {
            let t = null;
            return function(...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        function populateDatalist(datalistId, items) {
            const dl = document.getElementById(datalistId);
            if (!dl) return;
            dl.innerHTML = '';
            (items || []).forEach(val => {
                const opt = document.createElement('option');
                opt.value = val;
                dl.appendChild(opt);
            });
        }

        function fetchSuggestions(type, q, datalistId) {
            if (!q || q.length < 1) { populateDatalist(datalistId, []); return; }
            const url = `../attendance/suggest_cadets.php?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`;
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data && data.success) {
                        populateDatalist(datalistId, data.suggestions || []);
                    }
                })
                .catch(() => {});
        }

        function setupTypeahead() {
            const nameInput = document.getElementById('searchName');
            const idInput = document.getElementById('searchStudentId');
            const platoonInput = document.getElementById('filterPlatoon');
            if (nameInput) nameInput.addEventListener('input', debounce(() => fetchSuggestions('name', nameInput.value, 'nameSuggestions'), 250));
            if (idInput) idInput.addEventListener('input', debounce(() => fetchSuggestions('id', idInput.value, 'idSuggestions'), 250));
            if (platoonInput) platoonInput.addEventListener('input', debounce(() => fetchSuggestions('platoon', platoonInput.value, 'platoonSuggestions'), 250));
        }

        function prepopulatePlatoons() {
            fetch('../attendance/get_platoons.php')
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && Array.isArray(data.platoons)) {
                        populateDatalist('platoonSuggestions', data.platoons);
                    }
                })
                .catch(() => {});
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupTypeahead();
            prepopulatePlatoons();
            const nameInput = document.getElementById('searchName');
            const idInput = document.getElementById('searchStudentId');
            const headerDate = document.getElementById('dateFilter');
            const lookupDate = document.getElementById('filterDate');
            if (headerDate && lookupDate && !lookupDate.value) {
                lookupDate.value = headerDate.value;
            }
            const handleEnter = (e) => { if (e.key === 'Enter') { e.preventDefault(); lookupAttendance(); } };
            if (nameInput) nameInput.addEventListener('keypress', handleEnter);
            if (idInput) idInput.addEventListener('keypress', handleEnter);
        });
        
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
                    if (data && data.success) {
                        alert('Attendance record deleted.');
                        // Refresh lookup table
                        lookupAttendance();
                    } else {
                        const msg = (data && data.message) ? data.message : 'Unable to delete record.';
                        alert('Error: ' + msg);
                    }
                })
                .catch(error => {
                    console.error('Error deleting attendance:', error);
                    alert('Network error while deleting attendance record.');
                });
            }
        }
        
        function showAddAttendanceModal() {
            // Clear the edit modal and set it for adding new record
            document.getElementById('editAttendanceId').value = '';
            document.getElementById('editCadetId').value = '';
            document.getElementById('editEventName').value = '';
            document.getElementById('editDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('editTimeIn').value = new Date().toTimeString().slice(0, 5);
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
                select.innerHTML = '<option value="">-- Select a cadet --</option>';

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
