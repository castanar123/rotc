<?php
// rifle_backup_manager.php - Backup management interface for rifle system

session_start();
require_once 'includes/db.php';
require_once 'includes/rifle_backup.php';
require_once 'includes/SecurityLogger.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'commandant'])) {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access rifle backup manager', $_SESSION['user_id'] ?? null, 'HIGH');
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

// Log successful admin access to rifle backup manager
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed rifle backup manager', $_SESSION['user_id'], 'LOW');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_backup':
            $backup_type = $_POST['backup_type'] ?? 'manual';
            SecurityLogger::logSecurityEvent('BACKUP_OPERATION', "Admin initiated {$backup_type} rifle backup", $_SESSION['user_id'], 'MEDIUM');
            $result = createRifleBackup($backup_type);
            echo json_encode($result);
            exit;
            
        case 'clean_backups':
            $keep_count = (int)($_POST['keep_count'] ?? 10);
            SecurityLogger::logSecurityEvent('BACKUP_OPERATION', "Admin cleaned old rifle backups, keeping {$keep_count} files", $_SESSION['user_id'], 'MEDIUM');
            $result = cleanOldBackups($keep_count);
            echo json_encode($result);
            exit;
            
        case 'download_backup':
            $filename = $_POST['filename'] ?? '';
            $backup_dir = 'backups/rifle_backups';
            $file_path = $backup_dir . '/' . basename($filename);
            
            if (file_exists($file_path) && strpos($filename, 'rifle_backup_') === 0) {
                SecurityLogger::logSecurityEvent('FILE_ACCESS', "Admin downloaded rifle backup file: {$filename}", $_SESSION['user_id'], 'MEDIUM');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'File not found']);
                exit;
            }
            break;
    }
}

// Get backup history
$backup_history = getBackupHistory(20);

// Get system statistics
$stats = getRifleStatistics();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rifle Backup Manager - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link rel="stylesheet" href="css/rifle-mobile.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .backup-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .backup-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .backup-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .action-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .backup-btn {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .backup-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .backup-btn.danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .backup-history {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .backup-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .backup-table th,
        .backup-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .backup-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .backup-table tr:hover {
            background: #f8f9fa;
        }
        
        .download-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .download-btn:hover {
            background: #2980b9;
        }
        
        .status-message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .status-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="backup-container">
        <!-- Header -->
        <div class="backup-header">
            <h1><i class="fas fa-shield-alt"></i> Rifle Management Backup System</h1>
            <p>Secure backup and recovery for rifle management data</p>
        </div>
        
        <!-- Status Message -->
        <div id="statusMessage" class="status-message"></div>
        
        <!-- Current Statistics -->
        <?php if ($stats): ?>
        <div class="action-card">
            <h3><i class="fas fa-chart-bar"></i> Current System Statistics</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Rifles</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['available']; ?></div>
                    <div class="stat-label">Available</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['assigned']; ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $stats['maintenance']; ?></div>
                    <div class="stat-label">Maintenance</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Backup Actions -->
        <div class="backup-actions">
            <div class="action-card">
                <h3><i class="fas fa-download"></i> Create Backup</h3>
                <p>Create a comprehensive backup of all rifle management data including rifles, assignments, logs, and QR codes.</p>
                <button class="backup-btn" onclick="createBackup('manual')">
                    <i class="fas fa-save"></i> Create Manual Backup
                </button>
                <button class="backup-btn" onclick="createBackup('full')">
                    <i class="fas fa-database"></i> Create Full Backup
                </button>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-broom"></i> Cleanup Backups</h3>
                <p>Remove old backup files to free up storage space. Keep only the most recent backups.</p>
                <div style="margin-bottom: 10px;">
                    <label for="keepCount">Keep last:</label>
                    <select id="keepCount" style="margin-left: 10px; padding: 5px;">
                        <option value="5">5 backups</option>
                        <option value="10" selected>10 backups</option>
                        <option value="20">20 backups</option>
                        <option value="30">30 backups</option>
                    </select>
                </div>
                <button class="backup-btn danger" onclick="cleanBackups()">
                    <i class="fas fa-trash"></i> Clean Old Backups
                </button>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-info-circle"></i> Backup Information</h3>
                <p>Backups include:</p>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>All rifle records and details</li>
                    <li>Assignment history and logs</li>
                    <li>Transaction logs with timestamps</li>
                    <li>QR code images (if available)</li>
                    <li>System statistics and metadata</li>
                </ul>
                <button class="backup-btn" onclick="window.location.href='rifle_management.php'">
                    <i class="fas fa-arrow-left"></i> Back to Rifle Management
                </button>
            </div>
        </div>
        
        <!-- Backup History -->
        <div class="backup-history">
            <h3><i class="fas fa-history"></i> Backup History</h3>
            
            <?php if (empty($backup_history)): ?>
                <p style="text-align: center; color: #7f8c8d; margin: 20px 0;">
                    <i class="fas fa-info-circle"></i> No backups found. Create your first backup above.
                </p>
            <?php else: ?>
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backup_history as $backup): ?>
                        <tr>
                            <td>
                                <i class="fas fa-file-archive"></i>
                                <?php echo htmlspecialchars($backup['filename']); ?>
                            </td>
                            <td><?php echo $backup['size']; ?></td>
                            <td><?php echo $backup['created']; ?></td>
                            <td>
                                <button class="download-btn" onclick="downloadBackup('<?php echo htmlspecialchars($backup['filename']); ?>')">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function showStatus(message, type = 'success') {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.textContent = message;
            statusDiv.className = `status-message ${type}`;
            statusDiv.style.display = 'block';
            
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        }
        
        function createBackup(type) {
            const button = event.target;
            const originalText = button.innerHTML;
            
            button.innerHTML = '<span class="loading"></span> Creating Backup...';
            button.disabled = true;
            
            fetch('rifle_backup_manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=create_backup&backup_type=${type}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus(`Backup created successfully! File: ${data.file.split('/').pop()}, Size: ${data.size}`, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showStatus(`Backup failed: ${data.message}`, 'error');
                }
            })
            .catch(error => {
                showStatus(`Error: ${error.message}`, 'error');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        function cleanBackups() {
            const keepCount = document.getElementById('keepCount').value;
            
            if (!confirm(`Are you sure you want to delete old backups? This will keep only the last ${keepCount} backups.`)) {
                return;
            }
            
            const button = event.target;
            const originalText = button.innerHTML;
            
            button.innerHTML = '<span class="loading"></span> Cleaning...';
            button.disabled = true;
            
            fetch('rifle_backup_manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=clean_backups&keep_count=${keepCount}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus(data.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showStatus(`Cleanup failed: ${data.message}`, 'error');
                }
            })
            .catch(error => {
                showStatus(`Error: ${error.message}`, 'error');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        function downloadBackup(filename) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'rifle_backup_manager.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'download_backup';
            
            const filenameInput = document.createElement('input');
            filenameInput.type = 'hidden';
            filenameInput.name = 'filename';
            filenameInput.value = filename;
            
            form.appendChild(actionInput);
            form.appendChild(filenameInput);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</body>
</html>