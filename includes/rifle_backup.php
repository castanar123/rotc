<?php
// rifle_backup.php - Backup and logging utilities for rifle management

require_once 'db.php';

// Helper: check if a column exists on a table
function rb_column_exists($table, $column) {
    global $link;
    $tbl = mysqli_real_escape_string($link, $table);
    $col = mysqli_real_escape_string($link, $column);
    $res = mysqli_query($link, "SHOW COLUMNS FROM `$tbl` LIKE '$col'");
    return $res && mysqli_num_rows($res) > 0;
}

// Helper: detect which cadet column rifle_logs uses
function rb_logs_cadet_col() {
    static $cached = null;
    if ($cached !== null) return $cached;
    if (rb_column_exists('rifle_logs', 'cadet_id')) { $cached = 'cadet_id'; return $cached; }
    if (rb_column_exists('rifle_logs', 'cadet_profile_id')) { $cached = 'cadet_profile_id'; return $cached; }
    if (rb_column_exists('rifle_logs', 'borrower_id')) { $cached = 'borrower_id'; return $cached; }
    $cached = 'cadet_id';
    return $cached;
}

/**
 * Create a comprehensive backup of all rifle-related data
 */
function createRifleBackup($backup_type = 'full') {
    global $link;
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_dir = '../backups/rifle_backups';
    
    // Create backup directory if it doesn't exist
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $backup_file = $backup_dir . '/rifle_backup_' . $backup_type . '_' . $timestamp . '.zip';
    $temp_dir = $backup_dir . '/temp_' . $timestamp;
    
    // Create temporary directory
    mkdir($temp_dir, 0755, true);
    
    try {
        // Export rifles data
        exportRiflesToCSV($temp_dir . '/rifles.csv');
        
        // Export assignments data
        exportAssignmentsToCSV($temp_dir . '/rifle_assignments.csv');
        
        // Export logs data
        exportLogsToCSV($temp_dir . '/rifle_logs.csv');
        
        // Export QR codes if they exist
        copyQRCodes($temp_dir . '/qr_codes');
        
        // Create backup info file
        createBackupInfo($temp_dir . '/backup_info.txt', $backup_type);
        
        // Create ZIP archive
        $zip = new ZipArchive();
        if ($zip->open($backup_file, ZipArchive::CREATE) === TRUE) {
            addDirectoryToZip($zip, $temp_dir, '');
            $zip->close();
            
            // Clean up temporary directory
            removeDirectory($temp_dir);
            
            // Log backup creation
            logRifleAction(0, 0, 'backup_created', 'Backup created: ' . basename($backup_file));
            
            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'file' => $backup_file,
                'size' => formatBytes(filesize($backup_file))
            ];
        } else {
            throw new Exception('Failed to create ZIP archive');
        }
        
    } catch (Exception $e) {
        // Clean up on error
        if (file_exists($temp_dir)) {
            removeDirectory($temp_dir);
        }
        
        logRifleAction(0, 0, 'backup_failed', 'Backup failed: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Backup failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Export rifles data to CSV
 */
function exportRiflesToCSV($filename) {
    global $link;
    
    $query = "SELECT * FROM rifles ORDER BY rifle_id";
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        throw new Exception('Failed to fetch rifles data: ' . mysqli_error($link));
    }
    
    $file = fopen($filename, 'w');
    if (!$file) {
        throw new Exception('Failed to create rifles CSV file');
    }
    
    // Write CSV header
    $headers = ['rifle_id', 'rifle_number', 'model', 'status', 'condition_status', 'assigned_to', 'assigned_date', 'last_maintenance', 'qr_code_path', 'notes', 'created_at', 'updated_at'];
    fputcsv($file, $headers);
    
    // Write data rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($file, $row);
    }
    
    fclose($file);
}

/**
 * Export rifle assignments to CSV
 */
function exportAssignmentsToCSV($filename) {
    global $link;
    
    $query = "SELECT ra.*, r.rifle_number as serial_number, b.name as borrower_name 
              FROM rifle_assignments ra 
              LEFT JOIN rifles r ON ra.rifle_id = r.id 
              LEFT JOIN borrowers b ON ra.borrower_id = b.id 
              ORDER BY ra.assigned_date DESC";
    
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        throw new Exception('Failed to fetch assignments data: ' . mysqli_error($link));
    }
    
    $file = fopen($filename, 'w');
    if (!$file) {
        throw new Exception('Failed to create assignments CSV file');
    }
    
    // Write CSV header
    $headers = ['assignment_id', 'rifle_id', 'serial_number', 'cadet_id', 'cadet_name', 'assigned_date', 'returned_date', 'status', 'notes'];
    fputcsv($file, $headers);
    
    // Write data rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($file, $row);
    }
    
    fclose($file);
}

/**
 * Export rifle logs to CSV
 */
function exportLogsToCSV($filename) {
    global $link;
    
    // Build schema-aware export for logs
    $logsCol = rb_logs_cadet_col();
    if ($logsCol === 'borrower_id') {
        $join = '';
        if (rb_column_exists('borrowers', 'temp_id')) {
            // Map to cadet_profiles via deterministic temp_id if available
            $query = "SELECT rl.*, r.rifle_number as serial_number, 
                             CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) AS cadet_name
                      FROM rifle_logs rl 
                      LEFT JOIN rifles r ON rl.rifle_id = r.id 
                      LEFT JOIN borrowers b ON rl.borrower_id = b.id 
                      LEFT JOIN cadet_profiles cp ON b.temp_id = CONCAT('CADET_PROFILE_', cp.id)
                      ORDER BY COALESCE(rl.timestamp, rl.created_at) DESC";
        } else {
            // Fallback: use borrowers.name
            $query = "SELECT rl.*, r.rifle_number as serial_number, b.name as cadet_name
                      FROM rifle_logs rl 
                      LEFT JOIN rifles r ON rl.rifle_id = r.id 
                      LEFT JOIN borrowers b ON rl.borrower_id = b.id 
                      ORDER BY COALESCE(rl.timestamp, rl.created_at) DESC";
        }
    } else {
        // cadet_id or cadet_profile_id
        $query = "SELECT rl.*, r.rifle_number as serial_number, 
                         CONCAT(cp.first_name, ' ', IFNULL(CONCAT(cp.middle_name, ' '), ''), cp.last_name) AS cadet_name
                  FROM rifle_logs rl 
                  LEFT JOIN rifles r ON rl.rifle_id = r.id 
                  LEFT JOIN cadet_profiles cp ON rl.$logsCol = cp.id 
                  ORDER BY COALESCE(rl.timestamp, rl.created_at) DESC";
    }
    
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        throw new Exception('Failed to fetch logs data: ' . mysqli_error($link));
    }
    
    $file = fopen($filename, 'w');
    if (!$file) {
        throw new Exception('Failed to create logs CSV file');
    }
    
    // Write CSV header
    $headers = ['log_id', 'rifle_id', 'serial_number', 'cadet_id', 'cadet_name', 'action', 'timestamp', 'details'];
    fputcsv($file, $headers);
    
    // Write data rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($file, $row);
    }
    
    fclose($file);
}

/**
 * Copy QR code images to backup
 */
function copyQRCodes($destination) {
    $qr_source = '../uploads/rifle_qrcodes';
    
    if (!file_exists($qr_source)) {
        return; // No QR codes to backup
    }
    
    if (!file_exists($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $files = glob($qr_source . '/*.png');
    foreach ($files as $file) {
        $filename = basename($file);
        copy($file, $destination . '/' . $filename);
    }
}

/**
 * Create backup information file
 */
function createBackupInfo($filename, $backup_type) {
    global $link;
    
    $info = "ROTC Rifle Management System Backup\n";
    $info .= "=====================================\n\n";
    $info .= "Backup Type: " . ucfirst($backup_type) . "\n";
    $info .= "Created: " . date('Y-m-d H:i:s') . "\n";
    $info .= "System: " . $_SERVER['SERVER_NAME'] . "\n\n";
    
    // Add statistics
    $stats = getRifleStatistics();
    if ($stats) {
        $info .= "Statistics at Backup Time:\n";
        $info .= "- Total Rifles: " . $stats['total'] . "\n";
        $info .= "- Available: " . $stats['available'] . "\n";
        $info .= "- Assigned: " . $stats['assigned'] . "\n";
        $info .= "- In Maintenance: " . $stats['maintenance'] . "\n\n";
    }
    
    // Add recent activity count
    $recent_query = "SELECT COUNT(*) as count FROM rifle_logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $recent_result = mysqli_query($link, $recent_query);
    if ($recent_result) {
        $recent_count = mysqli_fetch_assoc($recent_result)['count'];
        $info .= "Recent Activity (Last 7 days): " . $recent_count . " transactions\n\n";
    }
    
    $info .= "Files Included:\n";
    $info .= "- rifles.csv: All rifle records\n";
    $info .= "- rifle_assignments.csv: Assignment history\n";
    $info .= "- rifle_logs.csv: Transaction logs\n";
    $info .= "- qr_codes/: QR code images (if available)\n";
    
    file_put_contents($filename, $info);
}

/**
 * Enhanced logging function for rifle actions
 */
function logRifleAction($rifle_id, $cadet_profile_id, $action, $details = '', $user_id = null) {
    global $link;
    
    // Get user ID from session if not provided
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    $rifle_id = mysqli_real_escape_string($link, $rifle_id);
    $cadet_profile_id = mysqli_real_escape_string($link, $cadet_profile_id);
    $action = mysqli_real_escape_string($link, $action);
    $details = mysqli_real_escape_string($link, $details);
    $user_id = $user_id ? mysqli_real_escape_string($link, $user_id) : 'NULL';
    
    // Add IP address and user agent for security
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $extended_details = $details;
    if ($ip_address !== 'unknown') {
        $extended_details .= " [IP: $ip_address]";
    }
    
    // Determine cadet column for logs
    $col = rb_logs_cadet_col();
    $cadetValue = ($col === 'borrower_id') ? 0 : $cadet_profile_id; // Use 0 for system events when borrower mapping is unavailable
    $query = "INSERT INTO rifle_logs (rifle_id, $col, action, timestamp, details, user_id, ip_address, user_agent) 
              VALUES ('$rifle_id', '$cadetValue', '$action', NOW(), '$extended_details', $user_id, '$ip_address', '$user_agent')";
    
    $result = mysqli_query($link, $query);
    
    // Also log to system error log for critical actions
    if (in_array($action, ['assign', 'return', 'maintenance', 'lost', 'damaged'])) {
        error_log("RIFLE_LOG: Action=$action, Rifle=$rifle_id, Cadet=$cadet_id, Details=$details, IP=$ip_address");
    }
    
    return $result;
}

/**
 * Get backup history
 */
function getBackupHistory($limit = 20) {
    $backup_dir = '../backups/rifle_backups';
    
    if (!file_exists($backup_dir)) {
        return [];
    }
    
    $backups = [];
    $files = glob($backup_dir . '/rifle_backup_*.zip');
    
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'path' => $file,
            'size' => formatBytes(filesize($file)),
            'created' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    // Sort by creation time (newest first)
    usort($backups, function($a, $b) {
        return filemtime($b['path']) - filemtime($a['path']);
    });
    
    return array_slice($backups, 0, $limit);
}

/**
 * Clean old backups (keep only specified number)
 */
function cleanOldBackups($keep_count = 10) {
    $backup_dir = '../backups/rifle_backups';
    
    if (!file_exists($backup_dir)) {
        return ['success' => true, 'message' => 'No backups to clean'];
    }
    
    $files = glob($backup_dir . '/rifle_backup_*.zip');
    
    if (count($files) <= $keep_count) {
        return ['success' => true, 'message' => 'No cleanup needed'];
    }
    
    // Sort by modification time (oldest first)
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $deleted_count = 0;
    $files_to_delete = array_slice($files, 0, count($files) - $keep_count);
    
    foreach ($files_to_delete as $file) {
        if (unlink($file)) {
            $deleted_count++;
        }
    }
    
    logRifleAction(0, 0, 'backup_cleanup', "Cleaned $deleted_count old backup files");
    
    return [
        'success' => true,
        'message' => "Cleaned $deleted_count old backup files",
        'deleted_count' => $deleted_count
    ];
}

/**
 * Utility functions
 */
function addDirectoryToZip($zip, $dir, $zipPath) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $dir . '/' . $file;
            $zipFilePath = $zipPath . $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipFilePath);
                addDirectoryToZip($zip, $filePath, $zipFilePath . '/');
            } else {
                $zip->addFile($filePath, $zipFilePath);
            }
        }
    }
}

function removeDirectory($dir) {
    if (!file_exists($dir)) return;
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? removeDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Schedule automatic backups (to be called via cron or scheduled task)
 */
function scheduleAutomaticBackup() {
    // Create daily backup
    $result = createRifleBackup('daily');
    
    if ($result['success']) {
        // Clean old backups (keep last 30 daily backups)
        cleanOldBackups(30);
        
        // Log success
        error_log("RIFLE_BACKUP: Automatic daily backup completed successfully");
    } else {
        // Log failure
        error_log("RIFLE_BACKUP: Automatic daily backup failed - " . $result['message']);
    }
    
    return $result;
}
?>