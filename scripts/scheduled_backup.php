<?php
// scheduled_backup.php - Automated backup script for rifle management system
// This script can be run via cron job or Windows Task Scheduler

// Set script execution time limit
set_time_limit(300); // 5 minutes

// Include required files
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/rifle_backup.php';

// Log file for backup operations
$log_file = dirname(__DIR__) . '/logs/backup_scheduler.log';

// Ensure logs directory exists
$logs_dir = dirname($log_file);
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

/**
 * Log message with timestamp
 */
function logMessage($message, $level = 'INFO') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

/**
 * Check if backup is needed based on schedule
 */
function isBackupNeeded() {
    $backup_dir = dirname(__DIR__) . '/backups/rifle_backups';
    
    if (!is_dir($backup_dir)) {
        return true; // No backups exist
    }
    
    // Get the most recent backup
    $files = glob($backup_dir . '/rifle_backup_*.zip');
    if (empty($files)) {
        return true; // No backup files found
    }
    
    // Sort files by modification time (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $latest_backup = $files[0];
    $last_backup_time = filemtime($latest_backup);
    
    // Check if last backup was more than 24 hours ago
    $hours_since_backup = (time() - $last_backup_time) / 3600;
    
    return $hours_since_backup >= 24;
}

/**
 * Get backup configuration from environment or defaults
 */
function getBackupConfig() {
    return [
        'max_backups' => (int)(getenv('RIFLE_MAX_BACKUPS') ?: 30),
        'backup_interval_hours' => (int)(getenv('RIFLE_BACKUP_INTERVAL') ?: 24),
        'cleanup_enabled' => (bool)(getenv('RIFLE_CLEANUP_ENABLED') ?: true),
        'notification_email' => getenv('RIFLE_BACKUP_EMAIL') ?: null
    ];
}

/**
 * Send notification email if configured
 */
function sendNotification($subject, $message, $is_error = false) {
    $config = getBackupConfig();
    
    if (!$config['notification_email']) {
        return; // No email configured
    }
    
    $headers = [
        'From: ROTC Rifle System <noreply@rotc-system.local>',
        'Content-Type: text/html; charset=UTF-8',
        'X-Priority: ' . ($is_error ? '1' : '3')
    ];
    
    $html_message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .error { color: #e74c3c; }
            .success { color: #27ae60; }
            .footer { background: #ecf0f1; padding: 10px; text-align: center; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>ROTC Rifle Management System</h2>
            <h3>{$subject}</h3>
        </div>
        <div class='content'>
            <p class='" . ($is_error ? 'error' : 'success') . "'>{$message}</p>
            <p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p><strong>Server:</strong> " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "</p>
        </div>
        <div class='footer'>
            <p>This is an automated message from the ROTC Rifle Management System.</p>
        </div>
    </body>
    </html>
    ";
    
    mail($config['notification_email'], $subject, $html_message, implode("\r\n", $headers));
}

// Main execution
try {
    logMessage('Starting scheduled backup process');
    
    $config = getBackupConfig();
    logMessage('Backup configuration loaded: ' . json_encode($config));
    
    // Check if backup is needed
    if (!isBackupNeeded()) {
        logMessage('Backup not needed - recent backup exists');
        exit(0);
    }
    
    logMessage('Backup needed - proceeding with backup creation');
    
    // Create backup
    $result = createRifleBackup('scheduled');
    
    if ($result['success']) {
        logMessage('Backup created successfully: ' . $result['file']);
        logMessage('Backup size: ' . $result['size']);
        
        // Send success notification
        sendNotification(
            'Rifle System Backup Completed',
            "Backup completed successfully.<br><br>"
            . "<strong>File:</strong> " . basename($result['file']) . "<br>"
            . "<strong>Size:</strong> " . $result['size'] . "<br>"
            . "<strong>Items backed up:</strong><br>"
            . "- Rifles: " . ($result['stats']['rifles'] ?? 'N/A') . "<br>"
            . "- Assignments: " . ($result['stats']['assignments'] ?? 'N/A') . "<br>"
            . "- Logs: " . ($result['stats']['logs'] ?? 'N/A')
        );
        
        // Clean old backups if enabled
        if ($config['cleanup_enabled']) {
            logMessage('Starting cleanup of old backups');
            $cleanup_result = cleanOldBackups($config['max_backups']);
            
            if ($cleanup_result['success']) {
                logMessage('Cleanup completed: ' . $cleanup_result['message']);
            } else {
                logMessage('Cleanup failed: ' . $cleanup_result['message'], 'WARNING');
            }
        }
        
    } else {
        $error_msg = 'Backup failed: ' . $result['message'];
        logMessage($error_msg, 'ERROR');
        
        // Send error notification
        sendNotification(
            'Rifle System Backup Failed',
            "Backup process failed.<br><br>"
            . "<strong>Error:</strong> " . htmlspecialchars($result['message']) . "<br>"
            . "<strong>Please check the system and try again.</strong>",
            true
        );
        
        exit(1);
    }
    
} catch (Exception $e) {
    $error_msg = 'Backup script error: ' . $e->getMessage();
    logMessage($error_msg, 'ERROR');
    
    // Send error notification
    sendNotification(
        'Rifle System Backup Script Error',
        "An error occurred during the backup process.<br><br>"
        . "<strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>"
        . "<strong>File:</strong> " . $e->getFile() . "<br>"
        . "<strong>Line:</strong> " . $e->getLine(),
        true
    );
    
    exit(1);
}

logMessage('Scheduled backup process completed successfully');
exit(0);
?>