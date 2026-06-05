<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['loggedin'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Only allow admin and instructors to manually record attendance
if (!in_array($_SESSION['role'], ['admin', 'instructor'])) {
    header('Location: ../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $attendance_date = $_POST['attendance_date'];
    $notes = trim($_POST['notes'] ?? '');
    
    try {
        // Verify user exists
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'User not found';
        } else {
            // Check if attendance already exists for this date
            $stmt = $pdo->prepare("
                SELECT id FROM attendance_logs 
                WHERE user_id = ? AND DATE(timestamp) = ?
            ");
            $stmt->execute([$user_id, $attendance_date]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $error = 'Attendance already recorded for ' . $user['first_name'] . ' ' . $user['last_name'] . ' on this date';
            } else {
                // Record attendance
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_logs (user_id, timestamp, method, recorded_by, notes) 
                    VALUES (?, ?, 'manual', ?, ?)
                ");
                $stmt->execute([$user_id, $attendance_date . ' ' . date('H:i:s'), $_SESSION['user_id'], $notes]);
                
                // Log the activity
                $activity_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, details, timestamp) 
                    VALUES (?, 'attendance_recorded', ?, NOW())
                ");
                $activity_details = json_encode([
                    'method' => 'manual',
                    'recorded_by' => $_SESSION['user_id'],
                    'target_user' => $user_id,
                    'target_name' => $user['first_name'] . ' ' . $user['last_name'],
                    'date' => $attendance_date,
                    'notes' => $notes
                ]);
                $activity_stmt->execute([$_SESSION['user_id'], $activity_details]);
                
                $message = 'Attendance recorded successfully for ' . $user['first_name'] . ' ' . $user['last_name'];
            }
        }
    } catch (PDOException $e) {
        error_log("Manual attendance error: " . $e->getMessage());
        $error = 'Database error occurred';
    }
}

// Get all users for the dropdown (only approved basic cadets)
try {
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, role, platoon 
        FROM users 
        WHERE role = 'basic-cadet' AND approval_status = 'approved' AND status = 'active'
        ORDER BY last_name, first_name
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get users error: " . $e->getMessage());
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Attendance Entry - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern Manual Attendance Styles */
        .management-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .modern-form {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .modern-select,
        .modern-input,
        .modern-textarea {
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        
        .modern-select:focus,
        .modern-input:focus,
        .modern-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .modern-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .action-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            background: var(--secondary-bg);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--hover-bg);
            border-color: var(--primary-color);
        }
        
        .data-container {
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .modern-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
        }
        
        .modern-table th {
            background: var(--secondary-bg);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            position: relative;
            cursor: pointer;
            user-select: none;
        }
        
        .modern-table th:hover {
            background: var(--hover-bg);
        }
        
        .modern-table th.sortable::after {
            content: '↕';
            position: absolute;
            right: 8px;
            opacity: 0.5;
            font-size: 12px;
        }
        
        .modern-table th.sort-asc::after {
            content: '↑';
            opacity: 1;
            color: var(--primary-color);
        }
        
        .modern-table th.sort-desc::after {
            content: '↓';
            opacity: 1;
            color: var(--primary-color);
        }
        
        .modern-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        .table-row:hover {
            background: var(--hover-bg);
        }
        
        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .user-name {
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .modern-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .role-admin {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }
        
        .role-instructor {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .role-student {
            background: linear-gradient(135deg, #007bff, #6610f2);
            color: white;
        }
        
        .text-truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }
        
        .text-muted {
            color: var(--text-muted);
            font-style: italic;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-left-color: #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            border-left-color: #dc3545;
            color: #721c24;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .modern-table {
                font-size: 14px;
            }
            
            .modern-table th,
            .modern-table td {
                padding: 12px 8px;
            }
            
            .user-cell {
                gap: 8px;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body data-role="<?php echo $_SESSION['role']; ?>">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-shield-alt"></i>
                    <span class="logo-text">ROTC</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
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
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-calendar-check"></i>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="manual_attendance.php" class="nav-link">
                            <i class="fas fa-edit"></i>
                            <span>Manual Entry</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../user_management.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../announcements/view.php" class="nav-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-title-section">
                        <h1 class="page-title">
                            <i class="fas fa-edit"></i>
                            Manual Attendance Entry
                        </h1>
                        <p class="page-subtitle">Record attendance manually for cadets and officers</p>
                    </div>
                </div>
                
                <div class="header-right">
                    <div class="header-actions">
                        <a href="dashboard.php" class="action-btn secondary">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Attendance</span>
                        </a>
                    </div>
                    <div class="user-menu">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                            <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <div class="management-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-edit"></i>
                            <h2>Record Attendance Manually</h2>
                        </div>
                        <div class="section-actions">
                            <button type="button" class="action-btn secondary" onclick="resetForm()">
                                <i class="fas fa-refresh"></i>
                                <span>Reset Form</span>
                            </button>
                        </div>
                    </div>
                    <div class="data-container">
                        <form method="POST" class="modern-form" id="attendanceForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="user_id" class="form-label">
                                        <i class="fas fa-user"></i>
                                        Select Cadet/Officer
                                    </label>
                                    <select name="user_id" id="user_id" class="modern-select" required>
                                        <option value="">Choose a person...</option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['last_name'] . ', ' . $user['first_name']); ?>
                                            (<?php echo ucfirst($user['role']); ?>)
                                            <?php if ($user['platoon']): ?>
                                                - Platoon <?php echo htmlspecialchars($user['platoon']); ?>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="attendance_date" class="form-label">
                                        <i class="fas fa-calendar"></i>
                                        Attendance Date
                                    </label>
                                    <input type="date" name="attendance_date" id="attendance_date" class="modern-input" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-sticky-note"></i>
                                    Notes (Optional)
                                </label>
                                <textarea name="notes" id="notes" class="modern-textarea" rows="4" 
                                          placeholder="Add any additional notes about this attendance record..."></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="action-btn primary">
                                    <i class="fas fa-save"></i>
                                    <span>Record Attendance</span>
                                </button>
                                <a href="dashboard.php" class="action-btn secondary">
                                    <i class="fas fa-times"></i>
                                    <span>Cancel</span>
                                </a>
                            </div>
                        </form>
                </div>
            </div>
            
                <!-- Recent Manual Entries -->
                <div class="management-section">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-history"></i>
                            <h2>Recent Manual Entries</h2>
                        </div>
                        <div class="section-actions">
                            <button type="button" class="action-btn secondary" onclick="refreshEntries()">
                                <i class="fas fa-refresh"></i>
                                <span>Refresh</span>
                            </button>
                        </div>
                    </div>
                    <div class="data-container">
                        <div class="modern-table-wrapper">
                            <table class="modern-table" id="entriesTable">
                                <thead>
                                    <tr>
                                        <th class="sortable" onclick="sortTable(0)">
                                            Date <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="sortable" onclick="sortTable(1)">
                                            Name <i class="fas fa-sort"></i>
                                        </th>
                                        <th>Role</th>
                                        <th>Recorded By</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                try {
                                    $stmt = $pdo->query("
                                        SELECT al.*, u.first_name, u.last_name, u.role,
                                               r.first_name as recorder_first, r.last_name as recorder_last
                                        FROM attendance_logs al 
                                        JOIN users u ON al.user_id = u.id 
                                        LEFT JOIN users r ON al.recorded_by = r.id
                                        WHERE al.method = 'manual'
                                        ORDER BY al.timestamp DESC 
                                        LIMIT 10
                                    ");
                                    $manual_entries = $stmt->fetchAll();
                                    
                                        foreach ($manual_entries as $entry):
                                    ?>
                                    <tr class="table-row">
                                        <td><?php echo date('M j, Y', strtotime($entry['timestamp'])); ?></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($entry['first_name'], 0, 1) . substr($entry['last_name'], 0, 1)); ?>
                                                </div>
                                                <span class="user-name"><?php echo htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="modern-badge role-<?php echo strtolower($entry['role']); ?>"><?php echo ucfirst($entry['role']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($entry['recorder_first']): ?>
                                                <?php echo htmlspecialchars($entry['recorder_first'] . ' ' . $entry['recorder_last']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($entry['notes']): ?>
                                                <span class="text-truncate" title="<?php echo htmlspecialchars($entry['notes']); ?>">
                                                    <?php echo htmlspecialchars(substr($entry['notes'], 0, 50)); ?>
                                                    <?php if (strlen($entry['notes']) > 50): ?>...
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">No notes</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php 
                                    endforeach;
                                } catch (PDOException $e) {
                                    echo '<tr><td colspan="5" class="text-center text-muted">Error loading recent entries</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced Manual Attendance JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            initializeManualAttendance();
        });
        
        function initializeManualAttendance() {
            // Auto-focus on user selection
            const userSelect = document.getElementById('user_id');
            if (userSelect) {
                userSelect.focus();
            }
            
            // Initialize table sorting
            initializeTableSorting();
            
            // Initialize form validation
            const form = document.querySelector('.modern-form form');
            if (form) {
                form.addEventListener('submit', validateForm);
            }
        }
        
        // Enhanced form validation
        function validateForm(event) {
            const userId = document.getElementById('user_id').value;
            const date = document.getElementById('attendance_date').value;
            
            if (!userId) {
                event.preventDefault();
                showAlert('Please select a user.', 'danger');
                document.getElementById('user_id').focus();
                return false;
            }
            
            if (!date) {
                event.preventDefault();
                showAlert('Please select a date.', 'danger');
                document.getElementById('attendance_date').focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = event.target.querySelector('.action-btn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recording...';
                submitBtn.disabled = true;
            }
            
            return true;
        }
        
        // Table sorting functionality
        function initializeTableSorting() {
            const table = document.querySelector('.modern-table');
            if (!table) return;
            
            const headers = table.querySelectorAll('th.sortable');
            headers.forEach(header => {
                header.addEventListener('click', () => sortTable(header));
            });
        }
        
        function sortTable(header) {
            const table = header.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const columnIndex = Array.from(header.parentNode.children).indexOf(header);
            const isAscending = !header.classList.contains('sort-asc');
            
            // Remove sort classes from all headers
            table.querySelectorAll('th').forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
            });
            
            // Add appropriate sort class
            header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
            
            // Sort rows
            rows.sort((a, b) => {
                const aText = a.cells[columnIndex].textContent.trim();
                const bText = b.cells[columnIndex].textContent.trim();
                
                // Handle date sorting
                if (columnIndex === 0) {
                    const aDate = new Date(aText);
                    const bDate = new Date(bText);
                    return isAscending ? aDate - bDate : bDate - aDate;
                }
                
                // Handle text sorting
                return isAscending ? 
                    aText.localeCompare(bText) : 
                    bText.localeCompare(aText);
            });
            
            // Reorder rows in DOM
            rows.forEach(row => tbody.appendChild(row));
        }
        
        // Reset form function
        function resetForm() {
            const form = document.querySelector('.modern-form form');
            if (form) {
                form.reset();
                document.getElementById('user_id').focus();
                showAlert('Form has been reset.', 'success');
            }
        }
        
        // Refresh entries function
        function refreshEntries() {
            showAlert('Refreshing entries...', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
        
        // Show alert function
        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
            `;
            
            // Insert alert at the top of main content
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.insertBefore(alert, mainContent.firstChild);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 5000);
            }
        }
        
        // Enhanced user selection with search
        function enhanceUserSelect() {
            const userSelect = document.getElementById('user_id');
            if (!userSelect) return;
            
            // Add search functionality to select
            userSelect.addEventListener('keyup', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const options = userSelect.querySelectorAll('option');
                
                options.forEach(option => {
                    if (option.value === '') return; // Skip default option
                    
                    const text = option.textContent.toLowerCase();
                    option.style.display = text.includes(searchTerm) ? 'block' : 'none';
                });
            });
        }
    </script>
</body>
</html>
