<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

global $link;

// Handle toggle switch updates
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_online_enrollment') {
        $new_status = $_POST['status'] === 'true' ? 'true' : 'false';
        $stmt = $link->prepare("UPDATE enrollment_tracking_config SET setting_value = ?, updated_at = NOW(), updated_by = ? WHERE setting_name = 'online_enrollment_enabled'");
        $stmt->bind_param("ss", $new_status, $_SESSION['username']);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode(['success' => true, 'status' => $new_status]);
        exit();
    }
}

// Get current configuration
$config_query = "SELECT setting_name, setting_value FROM enrollment_tracking_config";
$config_result = $link->query($config_query);
$config = [];
while ($row = $config_result->fetch_assoc()) {
    $config[$row['setting_name']] = $row['setting_value'];
}

// Get enrollment statistics
$today = date('Y-m-d');
$stats_query = "
    SELECT 
        COUNT(*) as total_enrollees,
        SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approvals,
        SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_enrollees,
        SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_enrollees,
        SUM(CASE WHEN paper_form_submitted = 1 THEN 1 ELSE 0 END) as paper_forms_submitted,
        SUM(CASE WHEN paper_form_submitted = 0 AND approval_status = 'approved' THEN 1 ELSE 0 END) as paper_forms_pending
    FROM users 
    WHERE role IN ('cadet', 'basic-cadet')
";
$stats_result = $link->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get all enrollees with their details
$enrollees_query = "
    SELECT 
        u.id,
        u.username,
        u.email,
        u.first_name,
        u.last_name,
        u.full_name as user_full_name,
        u.approval_status,
        u.paper_form_submitted,
        u.created_at,
        u.updated_at,
        cp.full_name,
        cp.student_number,
        cp.course,
        cp.platoon,
        cp.year_level,
        cp.contact_number
    FROM users u
    LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
    WHERE u.role IN ('cadet', 'basic-cadet')
    ORDER BY u.created_at DESC
";
$enrollees_result = $link->query($enrollees_query);

// Get daily statistics for the chart
$daily_stats_query = "
    SELECT 
        date_recorded,
        total_enrollees,
        pending_approvals,
        approved_enrollees,
        rejected_enrollees
    FROM enrollment_statistics 
    WHERE date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date_recorded ASC
";
$daily_stats_result = $link->query($daily_stats_query);
$daily_stats = [];
while ($row = $daily_stats_result->fetch_assoc()) {
    $daily_stats[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Tracking Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #28a745;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .navbar-brand {
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i>
                Enrollment Tracking Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Admin
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Online Enrollment Toggle -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-toggle-on me-2 text-primary"></i>
                                    Online Enrollment Status
                                </h5>
                                <p class="text-muted mb-0">Control whether new online enrollments are accepted</p>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="me-3" id="status-text">
                                    <?php echo $config['online_enrollment_enabled'] === 'true' ? 'Online' : 'Offline'; ?>
                                </span>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="enrollment-toggle" 
                                           <?php echo $config['online_enrollment_enabled'] === 'true' ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-users stat-icon text-primary"></i>
                        <h3 class="mt-2 mb-1"><?php echo $stats['total_enrollees']; ?></h3>
                        <p class="text-muted mb-0">Total Enrollees</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock stat-icon text-warning"></i>
                        <h3 class="mt-2 mb-1"><?php echo $stats['pending_approvals']; ?></h3>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle stat-icon text-success"></i>
                        <h3 class="mt-2 mb-1"><?php echo $stats['approved_enrollees']; ?></h3>
                        <p class="text-muted mb-0">Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-times-circle stat-icon text-danger"></i>
                        <h3 class="mt-2 mb-1"><?php echo $stats['rejected_enrollees']; ?></h3>
                        <p class="text-muted mb-0">Rejected</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-file-alt stat-icon text-info"></i>
                        <h3 class="mt-2 mb-1"><?php echo $stats['paper_forms_submitted']; ?></h3>
                        <p class="text-muted mb-0">Forms Submitted</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-center">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle stat-icon text-secondary"></i>
                        <h3 class="mt-2 mb-1"><?php echo $stats['paper_forms_pending']; ?></h3>
                        <p class="text-muted mb-0">Forms Pending</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrollment Trend Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-area me-2"></i>
                            Enrollment Trends (Last 30 Days)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="enrollmentChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrollees Table -->
        <div class="row">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            All Enrollees
                        </h5>
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search...">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="enrolleesTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student #</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Course</th>
                                        <th>Platoon</th>
                                        <th>Status</th>
                                        <th>Paper Form</th>
                                        <th>Enrolled</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($enrollee = $enrollees_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($enrollee['student_number'] ?? 'N/A'); ?></td>
                                        <td><?php 
                                            // Try to get the best available name
                                            $display_name = '';
                                            if (!empty($enrollee['full_name'])) {
                                                // Profile full name first
                                                $display_name = $enrollee['full_name'];
                                            } elseif (!empty($enrollee['user_full_name'])) {
                                                // User table full name second
                                                $display_name = $enrollee['user_full_name'];
                                            } elseif (!empty($enrollee['first_name']) && !empty($enrollee['last_name'])) {
                                                // Combine first and last name
                                                $display_name = $enrollee['first_name'] . ' ' . $enrollee['last_name'];
                                            } elseif (!empty($enrollee['first_name'])) {
                                                // Just first name
                                                $display_name = $enrollee['first_name'];
                                            } else {
                                                // Fall back to username
                                                $display_name = $enrollee['username'];
                                            }
                                            echo htmlspecialchars($display_name);
                                        ?></td>
                                        <td><?php echo htmlspecialchars($enrollee['email']); ?></td>
                                        <td><?php echo htmlspecialchars($enrollee['course'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($enrollee['platoon'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $status = $enrollee['approval_status'];
                                            $badge_class = '';
                                            switch ($status) {
                                                case 'pending':
                                                    $badge_class = 'bg-warning text-dark';
                                                    break;
                                                case 'approved':
                                                    $badge_class = 'bg-success';
                                                    break;
                                                case 'rejected':
                                                    $badge_class = 'bg-danger';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge status-badge <?php echo $badge_class; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($enrollee['paper_form_submitted']): ?>
                                                <span class="badge status-badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Submitted
                                                </span>
                                            <?php else: ?>
                                                <span class="badge status-badge bg-warning text-dark">
                                                    <i class="fas fa-clock me-1"></i>Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($enrollee['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-sm" 
                                                        onclick="viewEnrollee(<?php echo $enrollee['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-success btn-sm" 
                                                        onclick="editEnrollee(<?php echo $enrollee['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle switch functionality
        document.getElementById('enrollment-toggle').addEventListener('change', function() {
            const isChecked = this.checked;
            const statusText = document.getElementById('status-text');
            
            fetch('enrollment_tracking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_online_enrollment&status=${isChecked}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusText.textContent = isChecked ? 'Online' : 'Offline';
                    statusText.className = isChecked ? 'text-success' : 'text-danger';
                    
                    // Show notification
                    const toast = document.createElement('div');
                    toast.className = 'toast position-fixed top-0 end-0 m-3';
                    toast.innerHTML = `
                        <div class="toast-body bg-success text-white">
                            Online enrollment ${isChecked ? 'enabled' : 'disabled'} successfully!
                        </div>
                    `;
                    document.body.appendChild(toast);
                    const bsToast = new bootstrap.Toast(toast);
                    bsToast.show();
                    setTimeout(() => toast.remove(), 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.checked = !isChecked; // Revert toggle
            });
        });

        // Chart initialization
        const ctx = document.getElementById('enrollmentChart').getContext('2d');
        const chartData = <?php echo json_encode($daily_stats); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(item => new Date(item.date_recorded).toLocaleDateString()),
                datasets: [{
                    label: 'Total Enrollees',
                    data: chartData.map(item => item.total_enrollees),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Pending',
                    data: chartData.map(item => item.pending_approvals),
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Approved',
                    data: chartData.map(item => item.approved_enrollees),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Table filtering
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        document.getElementById('searchInput').addEventListener('keyup', filterTable);

        function filterTable() {
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const searchFilter = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('enrolleesTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let showRow = true;

                // Status filter
                if (statusFilter && cells[5]) {
                    const status = cells[5].textContent.toLowerCase();
                    if (!status.includes(statusFilter)) {
                        showRow = false;
                    }
                }

                // Search filter
                if (searchFilter && showRow) {
                    let found = false;
                    for (let j = 0; j < cells.length - 1; j++) {
                        if (cells[j].textContent.toLowerCase().includes(searchFilter)) {
                            found = true;
                            break;
                        }
                    }
                    if (!found) showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            }
        }

        // Action functions
        function viewEnrollee(id) {
            window.open(`view_cadet.php?id=${id}`, '_blank');
        }

        function editEnrollee(id) {
            window.open(`edit_cadet.php?id=${id}`, '_blank');
        }
    </script>
</body>
</html>