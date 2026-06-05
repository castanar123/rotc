<?php
/**
 * Hourly Backup Cron Job
 *
 * Schedule: Every 1 hour via Windows Task Scheduler
 * Program: C:\xampp\php\php.exe
 * Arguments: "C:\xampp\htdocs\generate qr\cron\hourly_backup.php"
 */

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

require_once dirname(__DIR__) . '/includes/BackupManager.php';
require_once dirname(__DIR__) . '/includes/SecurityLogger.php';

// Create logs directory if it doesn't exist
$logsDir = dirname(__DIR__) . '/logs';
if (!file_exists($logsDir)) { mkdir($logsDir, 0755, true); }

echo "[" . date('Y-m-d H:i:s') . "] Starting hourly backup process...\n";
error_log("[" . date('Y-m-d H:i:s') . "] Hourly backup cron job started");

try {
    // Avoid duplicate with daily 22:00 backup
    if (date('H:i') === '22:00') {
        echo "[" . date('Y-m-d H:i:s') . "] Skipping hourly backup at 22:00 (daily backup runs).\n";
        exit(0);
    }
    $manager = new BackupManager();
    $backupId = $manager->performHourlyBackup(false); // keep .sql by default

    $message = "Hourly backup completed successfully. Backup ID: {$backupId}";
    echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
    error_log("[" . date('Y-m-d H:i:s') . "] {$message}");

    // Verify file
    $hist = $manager->getBackupHistory(1);
    if (!empty($hist)) {
        $row = $hist[0];
        $path = '';
        if (!empty($row['file_path'])) { $path = $row['file_path']; }
        elseif (!empty($row['file_name'])) { $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $row['file_name']; }
        if ($row['status'] === 'completed' && $path && file_exists($path)) {
            echo "[" . date('Y-m-d H:i:s') . "] Backup file verified: " . basename($path) . "\n";
            echo "[" . date('Y-m-d H:i:s') . "] File size: " . round(filesize($path)/1024/1024, 2) . " MB\n";
        } else {
            throw new Exception('Backup file verification failed');
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Hourly backup process completed successfully.\n";
    exit(0);
} catch (Exception $e) {
    $err = "Hourly backup failed: " . $e->getMessage();
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: {$err}\n";
    error_log("[" . date('Y-m-d H:i:s') . "] ERROR: {$err}");
    exit(1);
}
