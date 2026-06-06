<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/BackupManager.php';
require_once '../includes/SecurityLogger.php';

// Ensure server-side time functions use local timezone (UTC+8)
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !rotc_role_in(['admin'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit();
}

$backupManager = new BackupManager();
$logger = new SecurityLogger();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_backup':
                try {
                    $description = trim($_POST['description'] ?? 'Manual backup');
                    $encrypt = !empty($_POST['encrypt']);
                    $backupId = $backupManager->createManualBackup($_SESSION['user_id'], $description, $encrypt);
                    $message = "Backup created successfully! Backup ID: {$backupId}";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Backup failed: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
                
            case 'test_backup_system':
                try {
                    // Test database connection
                    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                    $userCount = $stmt->fetchColumn();
                    
                    // Test backup directory
                    $backupDir = dirname(__DIR__) . '/backups';
                    if (!is_writable($backupDir)) {
                        throw new Exception('Backup directory is not writable');
                    }
                    
                    $message = "Backup system test passed. Database accessible ({$userCount} users), backup directory writable.";
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = "Backup system test failed: " . $e->getMessage();
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get backup history
$backupHistory = $backupManager->getBackupHistory(20);

// Calculate backup statistics
$totalBackups = count($backupHistory);
$successfulBackups = count(array_filter($backupHistory, function($backup) {
    return $backup['status'] === 'completed';
}));
$totalSize = array_sum(array_column($backupHistory, 'file_size'));

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    return floor($time/86400) . ' days ago';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Management - ROTC Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <style>
        /* Dark theme alignment */
        .card { background: var(--card-bg); color: var(--text-primary); border: 1px solid var(--border-primary); }
        .backup-stats { background: var(--card-bg); color: var(--text-primary); border: 1px solid var(--border-primary); }
        .manual-backup-section { background: var(--card-bg); color: var(--text-primary); border: 1px solid var(--border-primary); }
        .btn.btn-light, .btn.btn-outline-primary, .btn.btn-outline-secondary, .btn.btn-outline-info { border-color: var(--border-primary); color: var(--text-primary); }
        .backup-item { border-bottom: 1px solid var(--border-primary); padding: 15px 0; }
        .backup-item:last-child { border-bottom: none; }
        .badge { background: var(--hover-bg); color: var(--text-primary); border: 1px solid var(--border-primary); }
        .badge.bg-success { background: #198754 !important; color: #fff; border: none; }
        .badge.bg-danger { background: #dc3545 !important; color: #fff; border: none; }
        .badge.bg-warning { background: #ffc107 !important; color: #111; border: none; }
    </style>
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
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
                        <h1 class="header-title">Backup Management</h1>
                        <p class="header-subtitle">Automated and manual database backups</p>
                    </div>
                    <div class="header-actions">
                        <form method="POST" style="display:inline-block; margin-right: 10px;">
                            <input type="hidden" name="action" value="test_backup_system">
                            <button type="submit" class="manual-attendance-btn" style="padding: var(--spacing-sm) var(--spacing-md);">
                                <i class="fas fa-vial"></i> Test Backup System
                            </button>
                        </form>
                        <a href="../cron/daily_backup.php" class="qr-integration-btn" target="_blank" style="padding: var(--spacing-sm) var(--spacing-md);">
                            <i class="fas fa-terminal"></i> View Script
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade-in" role="alert" style="margin-bottom: var(--spacing-lg);">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Backups</span>
                        <i class="fas fa-database stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $totalBackups; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-archive"></i>
                        <span>Stored</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Successful</span>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo $successfulBackups; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-shield-alt"></i>
                        <span>Verified</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Total Size</span>
                        <i class="fas fa-hdd stat-icon"></i>
                    </div>
                    <div class="stat-value"><?php echo formatBytes($totalSize); ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-chart-line"></i>
                        <span>Compressed</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-title">Last Backup</span>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-value">
                        <?php 
                            function getBackupDatetime($row){ foreach(['completed_at','created_at','timestamp','created_on'] as $k){ if(!empty($row[$k])) return $row[$k]; } return null; }
                            $lastDate = null;
                            if (!empty($backupHistory)) {
                                foreach ($backupHistory as $row) {
                                    $st = isset($row['status']) ? strtolower($row['status']) : '';
                                    if ($st === 'completed') { $lastDate = getBackupDatetime($row); break; }
                                }
                                if (!$lastDate) { // fallback to newest row's date if no completed found
                                    $lastDate = getBackupDatetime($backupHistory[0]);
                                }
                            }
                            $abs = $lastDate ? date('Y-m-d H:i:s', strtotime($lastDate)) : '';
                            echo $lastDate ? formatTimeAgo($lastDate) : 'Never'; 
                        ?>
                        <?php if (!empty($abs)): ?>
                            <div class="text-muted" style="font-size: .8rem;" data-iso="<?php echo htmlspecialchars(date('c', strtotime($lastDate))); ?>">
                                <?php echo htmlspecialchars($abs); ?> PH Time
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-change positive">
                        <i class="fas fa-clock"></i>
                        <span>Recent</span>
                    </div>
                </div>
            </div>

            <!-- Manual Backup and Tools -->
            <div class="content-grid fade-in">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-plus-circle"></i> Create Manual Backup</h3>
                    </div>
                    <div style="padding: var(--spacing-md);">
                        <form method="POST" style="display: grid; gap: var(--spacing-md);">
                            <input type="hidden" name="action" value="create_backup">
                            <div>
                                <label for="description" class="form-label" style="color: var(--text-secondary);">Backup Description</label>
                                <input type="text" class="form-control" id="description" name="description" placeholder="e.g., Before system update" maxlength="255" style="background: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-primary);">
                            </div>
                            <div class="form-check" style="color: var(--text-secondary);">
                                <input class="form-check-input" type="checkbox" value="1" id="encrypt" name="encrypt">
                                <label class="form-check-label" for="encrypt">
                                    Encrypt backup file (.enc). Leave unchecked for plain .sql
                                </label>
                            </div>
                            <button type="submit" class="qr-action-btn">
                                <i class="fas fa-download"></i> Create Backup Now
                            </button>
                        </form>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-tools"></i> System Tools</h3>
                    </div>
                    <div style="padding: var(--spacing-md); display: flex; flex-direction: column; gap: var(--spacing-sm);">
                        <form method="POST">
                            <input type="hidden" name="action" value="test_backup_system">
                            <button type="submit" class="qr-action-btn secondary">
                                <i class="fas fa-vial"></i> Test Backup System
                            </button>
                        </form>
                        <a href="../setup_backup_scheduler.bat" class="qr-action-btn secondary" download>
                            <i class="fas fa-clock"></i> Download Scheduler Setup
                        </a>
                        <a href="../cron/daily_backup.php" class="qr-action-btn secondary" target="_blank">
                            <i class="fas fa-terminal"></i> View Backup Script
                        </a>
                    </div>
                </div>
            </div>

            <!-- Backup History -->
            <div class="dashboard-card fade-in" style="margin-top: var(--spacing-lg);">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Backup History</h3>
                </div>
                <div style="padding: var(--spacing-md);">
                    <?php if (empty($backupHistory)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No backups found</h5>
                            <p class="text-muted">Create your first backup using the form above.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                            function safeField($row, $keys, $default='') { foreach((array)$keys as $k){ if(isset($row[$k]) && $row[$k] !== '') return $row[$k]; } return $default; }
                        ?>
                        <?php foreach ($backupHistory as $backup): 
                            $status = strtolower(safeField($backup, ['status'], 'unknown'));
                            $badgeClass = $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : ($status === 'running' ? 'warning' : 'secondary'));
                            $statusLabel = ucfirst($status);
                            $dt = getBackupDatetime($backup);
                        ?>
                        <div class="backup-item">
                            <div style="display:flex; align-items:center; gap: var(--spacing-md); flex-wrap: wrap;">
                                <div style="flex: 1 1 260px;">
                                    <?php 
                                        $displayName = safeField($backup, ['file_name'], '');
                                        if ($displayName === '' && !empty($backup['file_path'])) { $displayName = basename($backup['file_path']); }
                                        if ($displayName === '') { $displayName = 'N/A'; }
                                    ?>
                                    <h6 style="margin:0; color: var(--text-primary);"><?php echo htmlspecialchars($displayName); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars(safeField($backup, ['description','details','note'], '')); ?></small>
                                </div>
                                <div style="width: 120px;">
                                    <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span>
                                </div>
                                <div style="width: 140px;">
                                    <small class="text-muted"><?php echo $backup['file_size'] ? formatBytes($backup['file_size']) : 'N/A'; ?></small>
                                </div>
                                <div style="width: 160px;">
                                    <small class="text-muted"><?php echo ucfirst(safeField($backup, ['backup_type','type','job_type'], 'unknown')); ?></small>
                                </div>
                                <div style="width: 200px;">
                                    <small class="text-muted"><?php echo $dt ? formatTimeAgo($dt) : 'N/A'; ?></small>
                                    <?php if ($dt): ?>
                                    <div class="text-muted" style="font-size:.75rem;" title="Absolute time">
                                        <?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($dt))); ?> PH Time
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div style="width: 160px; display:flex; gap:8px; align-items:center; justify-content:flex-end;">
                                    <?php if ($status === 'completed'): ?>
                                        <i class="fas fa-check-circle" style="color:#198754" title="Backup completed successfully"></i>
                                        <?php 
                                            $fname = safeField($backup, ['file_name'], '');
                                            if ($fname === '' && !empty($backup['file_path'])) { $fname = basename($backup['file_path']); }
                                            if ($fname): $url = '../backups/' . rawurlencode($fname); 
                                        ?>
                                            <a href="<?php echo $url; ?>" class="btn btn-sm btn-outline-success" download>
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif ($status === 'failed'): ?>
                                        <i class="fas fa-exclamation-circle" style="color:#dc3545" title="Backup failed"></i>
                                    <?php else: ?>
                                        <i class="fas fa-clock" style="color:#ffc107" title="Backup in progress"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/mobile-navigation.js"></script>
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

        // Fade-in animation initialization
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease-out';
        });
    </script>
</body>
</html>
