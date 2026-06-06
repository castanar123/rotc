<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
check_login();

// Access control: Admin only
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['admin'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

// Ensure status column supports the values we use
function ensure_missing_id_status_enum($pdo) {
    try {
        $col = $pdo->query("SHOW COLUMNS FROM missing_id_requests LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($col && isset($col['Type'])) {
            $type = strtolower($col['Type']);
            if (strpos($type, 'enum(') === 0) {
                if (preg_match_all("/'([^']*)'/", $col['Type'], $m)) {
                    $existing = $m[1];
                    $needed = ['active','inactive','fixed','expired'];
                    $missing = array_diff($needed, $existing);
                    if (!empty($missing)) {
                        $newVals = array_unique(array_merge($existing, $needed));
                        $enumList = "'" . implode("','", array_map(function($v){ return str_replace("'","''", $v); }, $newVals)) . "'";
                        $pdo->exec("ALTER TABLE missing_id_requests MODIFY status ENUM($enumList) NOT NULL DEFAULT 'active'");
                    }
                }
            } elseif (strpos($type, 'int') !== false) {
                // If numeric type, convert to ENUM to avoid truncation when saving strings
                $pdo->exec("ALTER TABLE missing_id_requests MODIFY status ENUM('active','inactive','fixed','expired') NOT NULL DEFAULT 'active'");
            }
        }
    } catch (Exception $e) {
        // Ignore; fallback updates may still work if column is VARCHAR
    }
}

// Handle AJAX requests for admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    
    try {
        ensure_missing_id_status_enum($pdo);
        switch ($action) {
            case 'mark_inactive':
                $stmt = $pdo->prepare("UPDATE missing_id_requests SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$request_id]);
                echo json_encode(['success' => true, 'message' => 'Request marked as inactive']);
                break;
                
            case 'mark_fixed':
                $stmt = $pdo->prepare("UPDATE missing_id_requests SET status = 'fixed' WHERE id = ?");
                $stmt->execute([$request_id]);
                echo json_encode(['success' => true, 'message' => 'Request marked as fixed']);
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM missing_id_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch missing ID requests with cadet information
try {
    $stmt = $pdo->query("
        SELECT mir.*, 
               cp.first_name, cp.last_name, cp.student_id, cp.year_level, cp.section, cp.facebook_profile,
               u.username
        FROM missing_id_requests mir
        JOIN cadet_profiles cp ON mir.cadet_id = cp.id
        JOIN users u ON cp.user_id = u.id
        ORDER BY mir.created_at DESC
    ");
    $missing_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $missing_requests = [];
    $error_message = "Error fetching missing ID requests: " . $e->getMessage();
}

// Pending registrations count for badge (use approval_status)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE approval_status = 'pending'");
$pending_registrations = $stmt->fetch()['total'];

// Count active missing ID requests
$stmt = $pdo->query("SELECT COUNT(*) as total FROM missing_id_requests WHERE status = 'active' AND expiry_date > NOW()");
$active_missing_ids = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missing ID Management - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
</head>
<body>
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
         <i class="fas fa-bars"></i>
     </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            // Centralized Admin Navigation
            $NAV_BASE = '..';
            include __DIR__ . '/../includes/admin_nav.php';
        ?>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Missing ID Management</h1>
                        <p class="header-subtitle">Monitor and manage cadet missing ID requests</p>
                    </div>
                    <div class="header-actions">
                        <button id="refresh-btn" class="qr-integration-btn" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error fade-in">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Requests</span>
                        <i class="fas fa-clipboard-list stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo count($missing_requests); ?></div>
                    <div class="stat-change">
                        <span>All time</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Active Requests</span>
                        <i class="fas fa-id-card-alt stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $active_missing_ids; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-clock"></i>
                        <span>Currently active</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Expired Today</span>
                        <i class="fas fa-calendar-times stat-icon"></i>
                    </div>
                    <div class="stat-value">
                        <?php 
                        $expired_today = 0;
                        foreach ($missing_requests as $request) {
                            if (date('Y-m-d', strtotime($request['expiry_date'])) === date('Y-m-d')) {
                                $expired_today++;
                            }
                        }
                        echo $expired_today;
                        ?>
                    </div>
                    <div class="stat-change negative">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Expired</span>
                    </div>
                </div>
            </div>

            <!-- Missing ID Requests Table -->
            <div class="dashboard-card fade-in">
                <div class="card-header">
                    <h3><i class="fas fa-id-card-alt"></i> Missing ID Requests</h3>
                    <div class="card-actions">
                        <select id="statusFilter" class="filter-select">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>
                <div class="card-content">
                    <?php if (empty($missing_requests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h4>No Missing ID Requests</h4>
                            <p>No cadets have filed missing ID requests yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Cadet Info</th>
                                        <th>Student ID</th>
                                        <th>Facebook Profile</th>
                                        <th>Year & Section</th>
                                        <th>Reason</th>
                                        <th>Request Date</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($missing_requests as $request): 
                                        $is_expired = strtotime($request['expiry_date']) < time();
                                        $status_class = $is_expired ? 'expired' : 'active';
                                        $status_text = $is_expired ? 'Expired' : 'Active';
                                    ?>
                                        <tr class="request-row" data-status="<?php echo $status_class; ?>">
                                            <td>
                                                <div class="cadet-info">
                                                    <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                                    <small>@<?php echo htmlspecialchars($request['username']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['student_id']); ?></td>
                                            <td>
                                                <?php if (!empty($request['facebook_profile'])): ?>
                                                    <a href="<?php echo htmlspecialchars($request['facebook_profile']); ?>" target="_blank" class="facebook-link" title="Contact on Facebook">
                                                        <i class="fab fa-facebook"></i> Facebook
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Not provided</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['year_level'] . ' - ' . $request['section']); ?></td>
                                            <td>
                                                <span class="reason-badge reason-<?php echo strtolower($request['reason']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($request['reason'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($request['request_date'])); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($request['expiry_date'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if (!$is_expired): ?>
                                                        <button class="btn-action btn-view" onclick="viewQRCode('<?php echo htmlspecialchars($request['qr_code_data']); ?>')" title="View QR Code">
                                                            <i class="fas fa-qrcode"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn-action" style="background: rgba(37, 99, 235, 0.2); color:#2563eb;" onclick="openIdCard(<?php echo (int)$request['cadet_id']; ?>)" title="Generate ID Card">
                                                        <i class="fas fa-id-badge"></i>
                                                    </button>
                                                    <button class="btn-action btn-info" onclick="viewDetails(<?php echo $request['id']; ?>)" title="View Details">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                    <?php if ($request['status'] !== 'inactive' && $request['status'] !== 'fixed'): ?>
                                                        <button class="btn-action btn-inactive" onclick="markRequest(<?php echo $request['id']; ?>, 'mark_inactive')" title="Mark as Inactive">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                        <button class="btn-action btn-fixed" onclick="markRequest(<?php echo $request['id']; ?>, 'mark_fixed')" title="Mark as Fixed">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn-action btn-delete" onclick="deleteRequest(<?php echo $request['id']; ?>)" title="Delete Request">
                                                        <i class="fas fa-trash"></i>
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
        </main>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Temporary QR Code</h3>
                <button class="modal-close" onclick="closeQRModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="qrCodeContainer" style="text-align: center; padding: 20px;">
                    <!-- QR code will be generated here -->
                </div>
                <p style="text-align: center; color: var(--text-secondary); margin-top: 15px;">
                    This QR code is for temporary attendance purposes only.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        // Sidebar toggle functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });

        // Status filter functionality
        document.getElementById('statusFilter').addEventListener('change', function() {
            const filterValue = this.value;
            const rows = document.querySelectorAll('.request-row');
            
            rows.forEach(row => {
                if (filterValue === 'all') {
                    row.style.display = '';
                } else {
                    const status = row.getAttribute('data-status');
                    row.style.display = status === filterValue ? '' : 'none';
                }
            });
        });

        // View QR Code function
        function viewQRCode(qrData) {
            const container = document.getElementById('qrCodeContainer');
            container.innerHTML = '';
            
            QRCode.toCanvas(qrData, { width: 200, height: 200 }, function (error, canvas) {
                if (error) {
                    container.innerHTML = '<p style="color: var(--text-error);">Error generating QR code</p>';
                } else {
                    container.appendChild(canvas);
                }
            });
            
            document.getElementById('qrModal').style.display = 'flex';
        }

        // Close QR Modal
        function closeQRModal() {
            document.getElementById('qrModal').style.display = 'none';
        }

        // Open ID Card generator
        function openIdCard(cadetProfileId) {
            const url = 'id_card.php?cadet_profile_id=' + encodeURIComponent(cadetProfileId);
            window.open(url, '_blank');
        }

        // View Details function (placeholder)
        function viewDetails(requestId) {
            alert('View details for request ID: ' + requestId + '\nThis feature can be expanded to show more detailed information.');
        }

        // Mark request function
        function markRequest(requestId, action) {
            const actionText = action === 'mark_inactive' ? 'inactive' : 'fixed';
            if (confirm(`Are you sure you want to mark this request as ${actionText}?`)) {
                fetch('missing_ids.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=${action}&request_id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the request.');
                });
            }
        }

        // Delete request function
        function deleteRequest(requestId) {
            if (confirm('Are you sure you want to delete this request? This action cannot be undone.')) {
                fetch('missing_ids.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&request_id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the request.');
                });
            }
        }

        // Add fade-in animation to elements
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Initialize fade-in elements
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease-out';
        });
    </script>

    <style>
        .cadet-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .cadet-info small {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .reason-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .reason-lost { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .reason-damaged { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .reason-stolen { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .reason-confiscated { background: rgba(108, 117, 125, 0.2); color: #6c757d; }
        .reason-other { background: rgba(13, 202, 240, 0.2); color: #0dcaf0; }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(25, 135, 84, 0.2);
            color: #198754;
        }

        .status-expired {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-action {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: rgba(13, 202, 240, 0.2);
            color: #0dcaf0;
        }

        .btn-info {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }

        .btn-inactive {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .btn-fixed {
            background: rgba(25, 135, 84, 0.2);
            color: #198754;
        }

        .btn-delete {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--border-primary);
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
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

        .filter-select {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }

        .facebook-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #1877f2;
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            background: rgba(24, 119, 242, 0.1);
        }

        .facebook-link:hover {
            background: rgba(24, 119, 242, 0.2);
            transform: scale(1.05);
        }

        .facebook-link i {
            font-size: 0.9rem;
        }

        .text-muted {
            color: var(--text-secondary);
            font-style: italic;
            font-size: 0.8rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .data-table {
                min-width: 800px;
            }
        }
    </style>
</body>
</html>
