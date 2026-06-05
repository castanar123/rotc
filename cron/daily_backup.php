<?php
/**
 * Daily Backup Cron Job
 * 
 * This script should be scheduled to run daily via Windows Task Scheduler
 * or cron (if on Linux). It performs automated database backups.
 * 
 * Windows Task Scheduler Command:
 * C:\xampp\php\php.exe "C:\xampp\htdocs\generate qr\cron\daily_backup.php"
 * 
 * Schedule: Daily at 2:00 AM
 */

// Set error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/backup_cron.log');
// Use local timezone for consistent timestamps
date_default_timezone_set('Asia/Manila');

// Ensure this script is only run from command line or cron
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    die('This script can only be run from command line.');
}

// Include required files
require_once dirname(__DIR__) . '/includes/BackupManager.php';
require_once dirname(__DIR__) . '/includes/SecurityLogger.php';

// Create logs directory if it doesn't exist
$logsDir = dirname(__DIR__) . '/logs';
if (!file_exists($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Log script start
echo "[" . date('Y-m-d H:i:s') . "] Starting daily backup process...\n";
error_log("[" . date('Y-m-d H:i:s') . "] Daily backup cron job started");

try {
    // Create backup manager instance
    $backupManager = new BackupManager();
    
    // Perform daily backup (keeps .sql by default)
    $backupId = $backupManager->performDailyBackup(false);
    
    // Log success
    $message = "Daily backup completed successfully. Backup ID: {$backupId}";
    echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
    error_log("[" . date('Y-m-d H:i:s') . "] {$message}");
    
    // Check backup file integrity
    $backupHistory = $backupManager->getBackupHistory(1);
    if (!empty($backupHistory)) {
        $latestBackup = $backupHistory[0];
        $path = '';
        if (!empty($latestBackup['file_path'])) {
            $path = $latestBackup['file_path'];
        } elseif (!empty($latestBackup['file_name'])) {
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $latestBackup['file_name'];
        }
        if ($latestBackup['status'] === 'completed' && $path && file_exists($path)) {
            echo "[" . date('Y-m-d H:i:s') . "] Backup file verified: " . basename($path) . "\n";
            echo "[" . date('Y-m-d H:i:s') . "] File size: " . formatBytes(filesize($path)) . "\n";
        } else {
            throw new Exception('Backup file verification failed');
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Daily backup process completed successfully.\n";
    
} catch (Exception $e) {
    $errorMessage = "Daily backup failed: " . $e->getMessage();
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: {$errorMessage}\n";
    error_log("[" . date('Y-m-d H:i:s') . "] ERROR: {$errorMessage}");
    
    // Send alert email (if email system is configured)
    // sendBackupFailureAlert($errorMessage);
    
    exit(1);
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Send backup failure alert email
 * TODO: Implement email functionality
 */
function sendBackupFailureAlert($errorMessage) {
    // This would integrate with your email system
    // For now, just log the alert
    error_log("[ALERT] Backup failure notification: {$errorMessage}");
}

echo "[" . date('Y-m-d H:i:s') . "] Script execution completed.\n";
?>