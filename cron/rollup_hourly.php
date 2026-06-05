<?php
/**
 * Roll up hourly backups before 22:00: keep newest 2 and delete the rest.
 *
 * Schedule: Daily at 21:59 via Windows Task Scheduler
 * Program: C:\xampp\php\php.exe
 * Arguments: "C:\xampp\htdocs\generate qr\cron\rollup_hourly.php"
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

echo "[" . date('Y-m-d H:i:s') . "] Starting rollup of hourly backups...\n";
error_log("[" . date('Y-m-d H:i:s') . "] Rollup of hourly backups started");

try {
    $manager = new BackupManager();
    $result = $manager->rollupHourlyBackups(2, '22:00:00');
    echo "[" . date('Y-m-d H:i:s') . "] Kept: " . $result['kept'] . ", Deleted: " . $result['deleted'] . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Rollup completed successfully.\n";
    error_log("[" . date('Y-m-d H:i:s') . "] Rollup results: Kept=" . $result['kept'] . ", Deleted=" . $result['deleted']);
    exit(0);
} catch (Exception $e) {
    $err = "Rollup failed: " . $e->getMessage();
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: {$err}\n";
    error_log("[" . date('Y-m-d H:i:s') . "] ERROR: {$err}");
    exit(1);
}
