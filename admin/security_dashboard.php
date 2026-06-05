<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/SecurityLogger.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$logger = new SecurityLogger();

// Get filter parameters
$filters = [
    'event_type' => $_GET['event_type'] ?? '',
    'severity' => $_GET['severity'] ?? '',
    'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d')
];

// Remove empty filters
$filters = array_filter($filters);

// Get security logs
$securityLogs = $logger->getSecurityLogs($filters, 50);

// Get security statistics
$stats = $logger->getSecurityStats(30);

// Get recent alerts
$stmt = $pdo->prepare("
    SELECT an.*, sl.event_type, sl.description as log_description, sl.created_at as event_time
    FROM alert_notifications an
    JOIN security_logs sl ON an.log_id = sl.log_id
    WHERE an.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY an.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active sessions count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as active_sessions
    FROM user_sessions 
    WHERE is_active = 1 AND expires_at > NOW()
");
$stmt->execute();
$activeSessions = $stmt->fetchColumn();

// Get failed login attempts in last hour
$stmt = $pdo->prepare("
    SELECT COUNT(*) as failed_logins
    FROM security_logs 
    WHERE event_type = 'login_failed' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute();
$recentFailedLogins = $stmt->fetchColumn();

function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    return floor($time/86400) . ' days ago';
}

function getSeverityBadgeClass($severity) {
    switch ($severity) {
        case 'critical': return 'bg-danger';
        case 'high': return 'bg-warning';
        case 'medium': return 'bg-info';
        case 'low': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}

function getEventTypeIcon($eventType) {
    switch ($eventType) {
        case 'login_success': return 'fas fa-sign-in-alt text-success';
        case 'login_failed': return 'fas fa-times-circle text-danger';
        case 'password_changed': return 'fas fa-key text-warning';
        case 'account_locked': return 'fas fa-lock text-danger';
        case 'backup_completed': return 'fas fa-database text-success';
        case 'backup_failed': return 'fas fa-exclamation-triangle text-danger';
        case 'suspicious_activity': return 'fas fa-shield-alt text-danger';
        case 'data_access': return 'fas fa-file-alt text-info';
        case 'system_change': return 'fas fa-cogs text-warning';
        default: return 'fas fa-info-circle text-muted';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - ROTC Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/dashboard-redesigned.css" rel="stylesheet">
    <style>
        .security-card {
            border-left: 4px solid #dc3545;
            transition: all 0.3s ease;
        }
        .security-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .security-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .alert-card {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
        }
        .log-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
            transition: background-color 0.3s ease;
        }
        .log-item:hover {
            background-color: #f8f9fa;
        }
        .log-item:last-child {
            border-bottom: none;
        }
        .severity-critical { border-left: 4px solid #dc3545; }
        .severity-high { border-left: 4px solid #fd7e14; }
        .severity-medium { border-left: 4px solid #0dcaf0; }
        .severity-low { border-left: 4px solid #6c757d; }
        .chart-container {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
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
            <!-- Top Navigation -->
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

            <!-- Main Content -->
            <div class="container-fluid mt-4">
                <div class="row">
                    <div class="col-12">
                        <h2><i class="fas fa-shield-alt"></i> Security Dashboard</h2>
                        <p class="text-muted">Monitor security events and system threats</p>
                    </div>
                </div>

                <!-- Security Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card security-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?php echo $activeSessions; ?></h4>
                                <p class="mb-0">Active Sessions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card security-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                <h4><?php echo count($recentAlerts); ?></h4>
                                <p class="mb-0">Recent Alerts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card security-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-times-circle fa-2x mb-2"></i>
                                <h4><?php echo $recentFailedLogins; ?></h4>
                                <p class="mb-0">Failed Logins (1h)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card security-stats">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h4><?php echo $stats['total_events']; ?></h4>
                                <p class="mb-0">Total Events (30d)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <?php if (!empty($recentAlerts)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card alert-card">
                            <div class="card-header">
                                <h5><i class="fas fa-bell"></i> Recent Security Alerts</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach (array_slice($recentAlerts, 0, 3) as $alert): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($alert['alert_type']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($alert['message']); ?></small>
                                    </div>
                                    <small><?php echo formatTimeAgo($alert['created_at']); ?></small>
                                </div>
                                <?php if (!$loop->last): ?><hr class="my-2"><?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-filter"></i> Filter Security Logs</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="event_type" class="form-label">Event Type</label>
                                        <select class="form-select" id="event_type" name="event_type">
                                            <option value="">All Events</option>
                                            <option value="login_success" <?php echo ($_GET['event_type'] ?? '') === 'login_success' ? 'selected' : ''; ?>>Login Success</option>
                                            <option value="login_failed" <?php echo ($_GET['event_type'] ?? '') === 'login_failed' ? 'selected' : ''; ?>>Login Failed</option>
                                            <option value="password_changed" <?php echo ($_GET['event_type'] ?? '') === 'password_changed' ? 'selected' : ''; ?>>Password Changed</option>
                                            <option value="account_locked" <?php echo ($_GET['event_type'] ?? '') === 'account_locked' ? 'selected' : ''; ?>>Account Locked</option>
                                            <option value="backup_completed" <?php echo ($_GET['event_type'] ?? '') === 'backup_completed' ? 'selected' : ''; ?>>Backup Completed</option>
                                            <option value="backup_failed" <?php echo ($_GET['event_type'] ?? '') === 'backup_failed' ? 'selected' : ''; ?>>Backup Failed</option>
                                            <option value="suspicious_activity" <?php echo ($_GET['event_type'] ?? '') === 'suspicious_activity' ? 'selected' : ''; ?>>Suspicious Activity</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="severity" class="form-label">Severity</label>
                                        <select class="form-select" id="severity" name="severity">
                                            <option value="">All Severities</option>
                                            <option value="critical" <?php echo ($_GET['severity'] ?? '') === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                            <option value="high" <?php echo ($_GET['severity'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                            <option value="medium" <?php echo ($_GET['severity'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="low" <?php echo ($_GET['severity'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_from" class="form-label">From Date</label>
                                        <input type="date" class="form-control" id="date_from" name="date_from" 
                                               value="<?php echo $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days')); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_to" class="form-label">To Date</label>
                                        <input type="date" class="form-control" id="date_to" name="date_to" 
                                               value="<?php echo $_GET['date_to'] ?? date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Filter
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Logs -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-list"></i> Security Event Log</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($securityLogs)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No security events found</h5>
                                        <p class="text-muted">Try adjusting your filter criteria.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($securityLogs as $log): ?>
                                    <div class="log-item severity-<?php echo $log['severity']; ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-1">
                                                <i class="<?php echo getEventTypeIcon($log['event_type']); ?> fa-lg"></i>
                                            </div>
                                            <div class="col-md-3">
                                                <h6 class="mb-1"><?php echo ucfirst(str_replace('_', ' ', $log['event_type'])); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></small>
                                            </div>
                                            <div class="col-md-4">
                                                <p class="mb-1"><?php echo htmlspecialchars($log['description']); ?></p>
                                                <small class="text-muted">IP: <?php echo htmlspecialchars($log['ip_address']); ?></small>
                                            </div>
                                            <div class="col-md-2">
                                                <span class="badge <?php echo getSeverityBadgeClass($log['severity']); ?>">
                                                    <?php echo ucfirst($log['severity']); ?>
                                                </span>
                                            </div>
                                            <div class="col-md-2">
                                                <small class="text-muted"><?php echo formatTimeAgo($log['created_at']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/mobile-navigation.js"></script>
</body>
</html>