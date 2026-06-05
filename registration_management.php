<?php
// Registration Management System
// Allows admins to view and manage pending registrations

require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/SecurityLogger.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'commandant'])) {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access registration management', $_SESSION['user_id'] ?? null, 'HIGH');
    header('Location: login.php');
    exit();
}

// Log successful admin access to registration management
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed registration management page', $_SESSION['user_id'], 'LOW');

// Handle registration approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if ($request_id > 0 && in_array($action, ['approve', 'reject', 'review'])) {
        try {
            $pdo->beginTransaction();
            
            // Get the registration request
            $stmt = $pdo->prepare("SELECT * FROM registration_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if ($request) {
                $new_status = '';
                switch ($action) {
                    case 'approve':
                        $new_status = 'approved';
                        // Update user approval_status and status to active
                        $stmt = $pdo->prepare("UPDATE users SET approval_status = 'approved', status = 'active' WHERE id = ?");
                        $stmt->execute([$request['user_id']]);
                        
                        $stmt = $pdo->prepare("UPDATE cadet_profiles SET status = 'Active' WHERE user_id = ?");
                        $stmt->execute([$request['user_id']]);
                        break;
                        
                    case 'reject':
                        $new_status = 'rejected';
                        // Update user approval_status to rejected and set status to inactive
                        $stmt = $pdo->prepare("UPDATE users SET approval_status = 'rejected', status = 'inactive' WHERE id = ?");
                        $stmt->execute([$request['user_id']]);
                        break;
                        
                    case 'review':
                        $new_status = 'under_review';
                        break;
                }
                
                // Update registration request
                $stmt = $pdo->prepare("
                    UPDATE registration_requests 
                    SET status = ?, reviewed_at = NOW(), reviewed_by = ?, admin_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_status, $_SESSION['user_id'], $admin_notes, $request_id]);
                
                // Log the status change
                $stmt = $pdo->prepare("
                    INSERT INTO registration_status_log 
                    (registration_id, old_status, new_status, changed_by, change_reason, changed_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $request_id, 
                    $request['status'], 
                    $new_status, 
                    $_SESSION['user_id'], 
                    $admin_notes
                ]);
                
                $pdo->commit();
                SecurityLogger::logSecurityEvent('DATA_MODIFICATION', "Admin {$action}d registration request ID: {$request_id} for user ID: {$request['user_id']}", $_SESSION['user_id'], 'MEDIUM');
                $success_message = "Registration request {$action}d successfully!";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error processing request: " . $e->getMessage();
        }
    }
}

// Get registration statistics
try {
    $stmt = $pdo->query("SELECT * FROM registration_stats_view");
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = ['total_requests' => 0, 'pending_count' => 0, 'approved_count' => 0, 'rejected_count' => 0];
}

// Get pending registrations
try {
    $stmt = $pdo->query("SELECT * FROM pending_registrations_view ORDER BY priority DESC, submitted_at ASC");
    $pending_registrations = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_registrations = [];
}

// Get recent registration activity
try {
    $stmt = $pdo->query("
        SELECT rr.*, u.username, u.email, 
               CONCAT(cp.first_name, ' ', cp.last_name) as full_name,
               reviewer.username as reviewer_name
        FROM registration_requests rr
        JOIN users u ON rr.user_id = u.id
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        LEFT JOIN users reviewer ON rr.reviewed_by = reviewer.id
        ORDER BY rr.submitted_at DESC
        LIMIT 20
    ");
    $recent_activity = $stmt->fetchAll();
} catch (Exception $e) {
    $recent_activity = [];
}

include 'includes/header.php';
?>

<div class="dashboard-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-user-check"></i>
                Registration Management
            </h1>
            <div class="header-actions">
                <button class="btn btn-outline" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i>
                    Refresh
                </button>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Pending Requests</div>
                    <div class="stat-value"><?php echo $stats['pending_count']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Approved</div>
                    <div class="stat-value"><?php echo $stats['approved_count']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Rejected</div>
                    <div class="stat-value"><?php echo $stats['rejected_count']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-value"><?php echo $stats['total_requests']; ?></div>
                </div>
            </div>
        </div>

        <!-- Pending Registrations -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-clock"></i>
                    Pending Registrations (<?php echo count($pending_registrations); ?>)
                </h3>
            </div>
            <div class="card-content">
                <?php if (empty($pending_registrations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Pending Registrations</h3>
                        <p>All registration requests have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Student ID</th>
                                    <th>Course</th>
                                    <th>Platoon</th>
                                    <th>Submitted</th>
                                    <th>Priority</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_registrations as $registration): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($registration['first_name'] ?? 'U', 0, 1) . substr($registration['last_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div class="user-info">
                                                <span class="user-name"><?php echo htmlspecialchars($registration['full_name'] ?? $registration['username']); ?></span>
                                                <span class="user-email"><?php echo htmlspecialchars($registration['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($registration['student_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($registration['course'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($registration['platoon'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="date-time"><?php echo date('M j, Y', strtotime($registration['submitted_at'])); ?></span>
                                        <small class="text-muted"><?php echo $registration['days_pending']; ?> days ago</small>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo $registration['priority']; ?>">
                                            <?php echo ucfirst($registration['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-success" onclick="showApprovalModal(<?php echo $registration['request_id']; ?>, 'approve', '<?php echo htmlspecialchars($registration['full_name'] ?? $registration['username']); ?>')">
                                                <i class="fas fa-check"></i>
                                                Approve
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="showApprovalModal(<?php echo $registration['request_id']; ?>, 'review', '<?php echo htmlspecialchars($registration['full_name'] ?? $registration['username']); ?>')">
                                                <i class="fas fa-eye"></i>
                                                Review
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="showApprovalModal(<?php echo $registration['request_id']; ?>, 'reject', '<?php echo htmlspecialchars($registration['full_name'] ?? $registration['username']); ?>')">
                                                <i class="fas fa-times"></i>
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i>
                    Recent Registration Activity
                </h3>
            </div>
            <div class="card-content">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Reviewed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $activity): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-info">
                                            <span class="user-name"><?php echo htmlspecialchars($activity['full_name'] ?? $activity['username']); ?></span>
                                            <span class="user-email"><?php echo htmlspecialchars($activity['email']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $activity['request_type'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $activity['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y H:i', strtotime($activity['submitted_at'])); ?></td>
                                <td><?php echo htmlspecialchars($activity['reviewer_name'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div id="approvalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Process Registration</h3>
            <button class="modal-close" onclick="closeApprovalModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" id="requestId" name="request_id">
                <input type="hidden" id="action" name="action">
                
                <div class="form-group">
                    <label>Student:</label>
                    <p id="studentName" class="form-text"></p>
                </div>
                
                <div class="form-group">
                    <label for="adminNotes">Admin Notes:</label>
                    <textarea id="adminNotes" name="admin_notes" class="form-control" rows="4" placeholder="Add notes about this decision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeApprovalModal()">Cancel</button>
                <button type="submit" id="submitBtn" class="btn btn-primary">Process</button>
            </div>
        </form>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.pending { background: #f39c12; }
.stat-icon.approved { background: #27ae60; }
.stat-icon.rejected { background: #e74c3c; }
.stat-icon.total { background: #3498db; }

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #2c3e50;
}

.stat-label {
    color: #7f8c8d;
    font-size: 0.9rem;
}

.priority-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
}

.priority-low { background: #ecf0f1; color: #7f8c8d; }
.priority-normal { background: #d5dbdb; color: #2c3e50; }
.priority-high { background: #f39c12; color: white; }
.priority-urgent { background: #e74c3c; color: white; }

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending { background: #f39c12; color: white; }
.status-approved { background: #27ae60; color: white; }
.status-rejected { background: #e74c3c; color: white; }
.status-under_review { background: #3498db; color: white; }

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #27ae60;
}
</style>

<script>
function showApprovalModal(requestId, action, studentName) {
    document.getElementById('requestId').value = requestId;
    document.getElementById('action').value = action;
    document.getElementById('studentName').textContent = studentName;
    
    const modal = document.getElementById('approvalModal');
    const title = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    switch(action) {
        case 'approve':
            title.textContent = 'Approve Registration';
            submitBtn.textContent = 'Approve';
            submitBtn.className = 'btn btn-success';
            break;
        case 'reject':
            title.textContent = 'Reject Registration';
            submitBtn.textContent = 'Reject';
            submitBtn.className = 'btn btn-danger';
            break;
        case 'review':
            title.textContent = 'Mark for Review';
            submitBtn.textContent = 'Mark for Review';
            submitBtn.className = 'btn btn-warning';
            break;
    }
    
    modal.style.display = 'block';
}

function closeApprovalModal() {
    document.getElementById('approvalModal').style.display = 'none';
    document.getElementById('adminNotes').value = '';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('approvalModal');
    if (event.target === modal) {
        closeApprovalModal();
    }
}
</script>

<?php include 'includes/footer.php'; ?>