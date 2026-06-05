<?php
/**
 * Prune today's hourly backups after 22:00 (10PM): delete all 'hourly' backups for the day.
 * Keeps separately scheduled 'daily' backups (e.g., 20:30 and 22:00) intact.
 *
 * Schedule: Daily at 22:05 via Windows Task Scheduler
 * Program: C:\xampp\php\php.exe
 * Arguments: "C:\xampp\htdocs\generate qr\cron\prune_hourly.php"
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

echo "[" . date('Y-m-d H:i:s') . "] Starting prune of today's hourly backups...\n";
error_log("[" . date('Y-m-d H:i:s') . "] Prune hourly backups task started");

try {
    $manager = new BackupManager();
    $result = $manager->pruneHourlyBackups(date('Y-m-d'));
    echo "[" . date('Y-m-d H:i:s') . "] Deleted hourly backups: " . $result['deleted'] . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Prune completed successfully.\n";
    error_log("[" . date('Y-m-d H:i:s') . "] Prune results: Deleted=" . $result['deleted']);
    exit(0);
} catch (Exception $e) {
    $err = "Prune failed: " . $e->getMessage();
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: {$err}\n";
    error_log("[" . date('Y-m-d H:i:s') . "] ERROR: {$err}");
    exit(1);
}
