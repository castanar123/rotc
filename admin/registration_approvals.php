<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/SecurityLogger.php';
require_once '../includes/term_enrollment.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['admin'])) {
    $securityLogger = new SecurityLogger($pdo);
    $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access registration approvals', 'high');
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

// Log successful admin access to registration approvals
$securityLogger = new SecurityLogger($pdo);
$securityLogger->logSecurityEvent($_SESSION['user_id'], 'ADMIN_ACCESS', 'Admin accessed registration approvals page', 'low');

ensure_term_enrollment_schema();

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $pdo->beginTransaction();
            
            switch ($_POST['action']) {
                case 'approve_single':
                    $user_id = (int)$_POST['user_id'];
                    $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', status = 'active' WHERE id = ? AND approval_status = 'pending'");
                    $stmt->execute([$user_id]);
                    // Activate cadet profile as well
                    $stmtCp = $pdo->prepare("UPDATE cadet_profiles SET status = 'Active' WHERE user_id = ?");
                    $stmtCp->execute([$user_id]);

                    try { enroll_user_into_current_term($user_id, (int)($_SESSION['user_id'] ?? 0), 'registration_approval'); } catch (Throwable $e) {}
                    $message = "User approved successfully!";
                    $message_type = "success";
                    break;
                    
                case 'reject_single':
                    $user_id = (int)$_POST['user_id'];
                    $stmt = $pdo->prepare("UPDATE users SET approval_status = 'rejected', status = 'inactive' WHERE id = ? AND approval_status = 'pending'");
                    $stmt->execute([$user_id]);
                    $message = "User rejected successfully!";
                    $message_type = "warning";
                    break;
                    
                case 'approve_selected':
                    if (!empty($_POST['selected_users'])) {
                        $user_ids = array_map('intval', $_POST['selected_users']);
                        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', status = 'active' WHERE id IN ($placeholders) AND approval_status = 'pending'");
                        $stmt->execute($user_ids);
                        // Activate cadet profiles for approved users
                        $stmtCp = $pdo->prepare("UPDATE cadet_profiles cp JOIN users u ON cp.user_id = u.id SET cp.status = 'Active' WHERE u.id IN ($placeholders)");
                        $stmtCp->execute($user_ids);

                        foreach ($user_ids as $__uid) {
                            try { enroll_user_into_current_term((int)$__uid, (int)($_SESSION['user_id'] ?? 0), 'registration_approval'); } catch (Throwable $e) {}
                        }
                        $count = count($user_ids);
                        $message = "$count users approved successfully!";
                        $message_type = "success";
                    } else {
                        $message = "No users selected for approval.";
                        $message_type = "warning";
                    }
                    break;
                    
                case 'approve_all':
                    $pendingIdsStmt = $pdo->prepare("SELECT id FROM users WHERE approval_status = 'pending'");
                    $pendingIdsStmt->execute();
                    $pendingIds = $pendingIdsStmt->fetchAll(PDO::FETCH_COLUMN);

                    $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', status = 'active' WHERE approval_status = 'pending'");
                    $stmt->execute();
                    // Activate all cadet profiles for users now approved and active
                    $pdo->prepare("UPDATE cadet_profiles cp JOIN users u ON cp.user_id = u.id SET cp.status = 'Active' WHERE u.approval_status = 'approved' AND u.status = 'active'")->execute();
                    $affected = $stmt->rowCount();

                    if (!empty($pendingIds)) {
                        foreach ($pendingIds as $__uid) {
                            try { enroll_user_into_current_term((int)$__uid, (int)($_SESSION['user_id'] ?? 0), 'registration_approval'); } catch (Throwable $e) {}
                        }
                    }
                    $message = "All $affected pending registrations approved successfully!";
                    $message_type = "success";
                    break;
                    
                case 'reject_selected':
                    if (!empty($_POST['selected_users'])) {
                        $user_ids = array_map('intval', $_POST['selected_users']);
                        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
                        $stmt = $pdo->prepare("UPDATE users SET approval_status = 'rejected', status = 'inactive' WHERE id IN ($placeholders) AND approval_status = 'pending'");
                        $stmt->execute($user_ids);
                        $count = count($user_ids);
                        $message = "$count users rejected successfully!";
                        $message_type = "warning";
                    } else {
                        $message = "No users selected for rejection.";
                        $message_type = "warning";
                    }
                    break;
            }
            
            $pdo->commit();
            
            // Log security event for approval actions
            $action_description = "Admin performed {$_POST['action']} action";
            if (isset($user_id)) {
                $action_description .= " for user ID: {$user_id}";
            } elseif (isset($user_ids)) {
                $action_description .= " for " . count($user_ids) . " users";
            } elseif ($_POST['action'] === 'approve_all') {
                $action_description .= " for all pending registrations";
            }
            $securityLogger = new SecurityLogger($pdo);
            $securityLogger->logSecurityEvent($_SESSION['user_id'], 'DATA_MODIFICATION', $action_description, 'medium');
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get pending registrations with cadet profile details (use approval_status)
$pending_query = "
    SELECT 
        u.id,
        u.username,
        u.email,
        u.role,
        u.created_at as registration_date,
        cp.student_id,
        cp.first_name,
        cp.last_name,
        cp.middle_name,
        cp.gender,
        cp.course,
        cp.section,
        cp.platoon,
        cp.contact_number,
        cp.address
    FROM users u
    LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
    WHERE u.approval_status = 'pending'
    ORDER BY u.created_at DESC
";

$pending_stmt = $pdo->prepare($pending_query);
$pending_stmt->execute();
$pending_registrations = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (group by approval_status)
$stats_query = "
    SELECT 
        approval_status,
        COUNT(*) as count
    FROM users 
    GROUP BY approval_status
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pending_count = $stats['pending'] ?? 0;
$approved_count = $stats['approved'] ?? 0;
$rejected_count = $stats['rejected'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Approvals - ROTC Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card.pending {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card.approved {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.rejected {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        .table-responsive {
            border-radius: 15px;
            overflow: hidden;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.375rem;
        }
        .navbar-brand {
            font-weight: bold;
            color: white !important;
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <?php 
                    $NAV_BASE = '..';
                    include __DIR__ . '/../includes/admin_nav.php';
                ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-check me-2"></i>Registration Approvals</h2>
                    <div class="d-flex gap-2">
                        <a href="../admin_dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card pending">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h3><?php echo $pending_count; ?></h3>
                                <p class="mb-0">Pending Approvals</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card approved">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h3><?php echo $approved_count; ?></h3>
                                <p class="mb-0">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card rejected">
                            <div class="card-body text-center">
                                <i class="fas fa-times-circle fa-2x mb-2"></i>
                                <h3><?php echo $rejected_count; ?></h3>
                                <p class="mb-0">Rejected</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($pending_registrations)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>No Pending Registrations</h4>
                        <p class="text-muted">All registrations have been processed.</p>
                    </div>
                </div>
                <?php else: ?>
                
                <!-- Batch Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Batch Actions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="batchForm">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" name="action" value="approve_selected" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i>Approve Selected
                                </button>
                                <button type="submit" name="action" value="reject_selected" class="btn btn-warning">
                                    <i class="fas fa-times me-1"></i>Reject Selected
                                </button>
                                <button type="submit" name="action" value="approve_all" class="btn btn-primary" 
                                        onclick="return confirm('Are you sure you want to approve ALL pending registrations?')">
                                    <i class="fas fa-check-double me-1"></i>Approve All (<?php echo $pending_count; ?>)
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="toggleSelectAll()">
                                    <i class="fas fa-list me-1"></i>Select All
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Pending Registrations Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Pending Registrations (<?php echo count($pending_registrations); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                        </th>
                                        <th>Student Info</th>
                                        <th>Contact</th>
                                        <th>Academic</th>
                                        <th>Registration Date</th>
                                        <th width="200">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_registrations as $registration): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_users[]" value="<?php echo $registration['id']; ?>" 
                                                   form="batchForm" class="user-checkbox">
                                        </td>
                                        <td>
                                            <div>
                                                <strong>
                                                    <?php 
                                                    $fullName = trim($registration['first_name'] . ' ' . $registration['middle_name'] . ' ' . $registration['last_name']);
                                                    echo htmlspecialchars($fullName ?: 'N/A');
                                                    ?>
                                                </strong>
                                                <br>
                                                <small class="text-muted">
                                                    ID: <?php echo htmlspecialchars($registration['student_id'] ?: 'N/A'); ?><br>
                                                    Email: <?php echo htmlspecialchars($registration['email']); ?><br>
                                                    Username: <?php echo htmlspecialchars($registration['username']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo htmlspecialchars($registration['contact_number'] ?: 'N/A'); ?><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(substr($registration['address'] ?: 'N/A', 0, 50)); ?>
                                                    <?php if (strlen($registration['address'] ?: '') > 50) echo '...'; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($registration['course'] ?: 'N/A'); ?></strong>
                                                <?php if ($registration['section']): ?>
                                                    - <?php echo htmlspecialchars($registration['section']); ?>
                                                <?php endif; ?><br>
                                                <small class="text-muted">
                                                    Platoon: <?php echo htmlspecialchars($registration['platoon'] ?: 'N/A'); ?><br>
                                                    Role: <?php echo ucfirst($registration['role']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($registration['registration_date'])); ?><br>
                                            <small class="text-muted">
                                                <?php echo date('g:i A', strtotime($registration['registration_date'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $registration['id']; ?>">
                                                    <button type="submit" name="action" value="approve_single" 
                                                            class="btn btn-success btn-action" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $registration['id']; ?>">
                                                    <button type="submit" name="action" value="reject_single" 
                                                            class="btn btn-warning btn-action" title="Reject"
                                                            onclick="return confirm('Are you sure you want to reject this registration?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-info btn-action" title="View Details"
                                                        onclick="viewDetails(<?php echo $registration['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            
            userCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }
        
        function viewDetails(userId) {
            // You can implement a modal or redirect to a detailed view
            window.open(`../view_profile.php?id=${userId}`, '_blank');
        }
        
        // Update select all checkbox based on individual selections
        document.addEventListener('DOMContentLoaded', function() {
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            
            userCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
                    selectAllCheckbox.checked = checkedBoxes.length === userCheckboxes.length;
                    selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < userCheckboxes.length;
                });
            });
        });
    </script>
</body>
</html>
