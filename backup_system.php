<?php
// Automated Database Backup System for ROTC System
// Features: Hourly backups, daily cleanup, folder + zip storage, manual backup

class DatabaseBackupSystem {
    private $db_host = 'localhost';
    private $db_name = 'rotc_db';
    private $db_user = 'root';
    private $db_pass = 'root';
    private $backup_dir = 'backups';
    private $daily_backup_dir = 'backups/daily';
    private $hourly_backup_dir = 'backups/hourly';
    private $zip_backup_dir = 'backups/zip';
    
    public function __construct() {
        $this->createBackupDirectories();
    }
    
    private function createBackupDirectories() {
        $directories = [
            $this->backup_dir,
            $this->daily_backup_dir,
            $this->hourly_backup_dir,
            $this->zip_backup_dir
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
                echo "Created directory: {$dir}\n";
            }
        }
    }
    
    public function createBackup($type = 'hourly', $manual = false) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backup_filename = "rotc_db_backup_{$type}_{$timestamp}.sql";
            
            if ($type === 'daily') {
                $backup_path = $this->daily_backup_dir . '/' . $backup_filename;
            } else {
                $backup_path = $this->hourly_backup_dir . '/' . $backup_filename;
            }
            
            if ($manual) {
                $backup_filename = "rotc_db_manual_backup_{$timestamp}.sql";
                $backup_path = $this->backup_dir . '/' . $backup_filename;
            }
            
            // Create mysqldump command with skip-lock-tables and ignore-table for problematic views
            $mysqldump_path = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
            $command = "\"{$mysqldump_path}\" --host={$this->db_host} --user={$this->db_user} --password={$this->db_pass} --single-transaction --routines --triggers --skip-lock-tables --force {$this->db_name} > \"{$backup_path}\"";
            
            // Execute backup command
            $output = [];
            $return_code = 0;
            exec($command, $output, $return_code);
            
            if ($return_code === 0 && file_exists($backup_path)) {
                $file_size = filesize($backup_path);
                echo "✓ Backup created successfully: {$backup_filename} ({$file_size} bytes)\n";
                
                // Create zip version
                $this->createZipBackup($backup_path, $backup_filename);
                
                return [
                    'success' => true,
                    'filename' => $backup_filename,
                    'path' => $backup_path,
                    'size' => $file_size,
                    'timestamp' => $timestamp
                ];
            } else {
                throw new Exception("Backup failed with return code: {$return_code}");
            }
            
        } catch (Exception $e) {
            echo "❌ Backup error: " . $e->getMessage() . "\n";
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function createZipBackup($sql_file_path, $sql_filename) {
        try {
            // Check if ZipArchive extension is available
            if (!class_exists('ZipArchive')) {
                echo "⚠️ ZipArchive extension not available, skipping zip backup\n";
                return false;
            }
            
            $zip_filename = str_replace('.sql', '.zip', $sql_filename);
            $zip_path = $this->zip_backup_dir . '/' . $zip_filename;
            
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                $zip->addFile($sql_file_path, $sql_filename);
                $zip->close();
                
                $zip_size = filesize($zip_path);
                echo "✓ Zip backup created: {$zip_filename} ({$zip_size} bytes)\n";
                
                return true;
            } else {
                throw new Exception("Failed to create zip file: {$zip_path}");
            }
        } catch (Exception $e) {
            echo "❌ Zip creation error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function cleanupOldBackups() {
        echo "\n=== Cleaning up old backups ===\n";
        
        // Cleanup hourly backups (keep only last 24)
        $this->cleanupHourlyBackups();
        
        // Cleanup daily backups (keep only last 7 days)
        $this->cleanupDailyBackups();
        
        // Cleanup zip backups (keep only last 7 days)
        $this->cleanupZipBackups();
    }
    
    private function cleanupHourlyBackups() {
        $files = glob($this->hourly_backup_dir . '/rotc_db_backup_hourly_*.sql');
        
        if (count($files) > 24) {
            // Sort files by modification time (oldest first)
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files, keep only last 24
            $files_to_remove = array_slice($files, 0, count($files) - 24);
            
            foreach ($files_to_remove as $file) {
                if (unlink($file)) {
                    echo "Removed old hourly backup: " . basename($file) . "\n";
                }
            }
        }
        
        echo "Hourly backups: " . count(glob($this->hourly_backup_dir . '/rotc_db_backup_hourly_*.sql')) . " files remaining\n";
    }
    
    private function cleanupDailyBackups() {
        $files = glob($this->daily_backup_dir . '/rotc_db_backup_daily_*.sql');
        
        // Remove files older than 7 days
        $cutoff_time = time() - (7 * 24 * 60 * 60); // 7 days ago
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    echo "Removed old daily backup: " . basename($file) . "\n";
                }
            }
        }
        
        echo "Daily backups: " . count(glob($this->daily_backup_dir . '/rotc_db_backup_daily_*.sql')) . " files remaining\n";
    }
    
    private function cleanupZipBackups() {
        $files = glob($this->zip_backup_dir . '/rotc_db_backup_*.zip');
        
        // Remove zip files older than 7 days
        $cutoff_time = time() - (7 * 24 * 60 * 60); // 7 days ago
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    echo "Removed old zip backup: " . basename($file) . "\n";
                }
            }
        }
        
        echo "Zip backups: " . count(glob($this->zip_backup_dir . '/rotc_db_backup_*.zip')) . " files remaining\n";
    }
    
    public function createDailyBackup() {
        echo "\n=== Creating Daily Backup ===\n";
        
        // Check if daily backup already exists for today
        $today = date('Y-m-d');
        $existing_daily = glob($this->daily_backup_dir . "/rotc_db_backup_daily_{$today}_*.sql");
        
        if (empty($existing_daily)) {
            return $this->createBackup('daily');
        } else {
            echo "Daily backup already exists for today: " . basename($existing_daily[0]) . "\n";
            return ['success' => true, 'message' => 'Daily backup already exists'];
        }
    }
    
    public function getBackupStatus() {
        $status = [
            'hourly_backups' => count(glob($this->hourly_backup_dir . '/rotc_db_backup_hourly_*.sql')),
            'daily_backups' => count(glob($this->daily_backup_dir . '/rotc_db_backup_daily_*.sql')),
            'zip_backups' => count(glob($this->zip_backup_dir . '/rotc_db_backup_*.zip')),
            'manual_backups' => count(glob($this->backup_dir . '/rotc_db_manual_backup_*.sql')),
            'total_backup_size' => 0
        ];
        
        // Calculate total backup size
        $all_files = array_merge(
            glob($this->hourly_backup_dir . '/*.sql'),
            glob($this->daily_backup_dir . '/*.sql'),
            glob($this->zip_backup_dir . '/*.zip'),
            glob($this->backup_dir . '/rotc_db_manual_backup_*.sql')
        );
        
        foreach ($all_files as $file) {
            $status['total_backup_size'] += filesize($file);
        }
        
        return $status;
    }
    
    public function listRecentBackups($limit = 10) {
        $all_backups = [];
        
        // Get all backup files
        $files = array_merge(
            glob($this->hourly_backup_dir . '/*.sql'),
            glob($this->daily_backup_dir . '/*.sql'),
            glob($this->backup_dir . '/rotc_db_manual_backup_*.sql')
        );
        
        foreach ($files as $file) {
            $all_backups[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => $this->getBackupType($file)
            ];
        }
        
        // Sort by creation time (newest first)
        usort($all_backups, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return array_slice($all_backups, 0, $limit);
    }
    
    private function getBackupType($filepath) {
        if (strpos($filepath, 'manual') !== false) return 'manual';
        if (strpos($filepath, 'daily') !== false) return 'daily';
        if (strpos($filepath, 'hourly') !== false) return 'hourly';
        return 'unknown';
    }
}

// Main execution
if (php_sapi_name() === 'cli' || isset($_GET['action'])) {
    $backup_system = new DatabaseBackupSystem();
    
    // Determine action
    $action = isset($_GET['action']) ? $_GET['action'] : (isset($argv[1]) ? $argv[1] : 'hourly');
    
    echo "=== ROTC Database Backup System ===\n";
    echo "Action: {$action}\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    switch ($action) {
        case 'hourly':
            echo "Creating hourly backup...\n";
            $result = $backup_system->createBackup('hourly');
            break;
            
        case 'daily':
            echo "Creating daily backup...\n";
            $result = $backup_system->createDailyBackup();
            break;
            
        case 'manual':
            echo "Creating manual backup...\n";
            $result = $backup_system->createBackup('hourly', true);
            break;
            
        case 'cleanup':
            $backup_system->cleanupOldBackups();
            break;
            
        case 'status':
            $status = $backup_system->getBackupStatus();
            echo "Backup Status:\n";
            echo "- Hourly backups: {$status['hourly_backups']}\n";
            echo "- Daily backups: {$status['daily_backups']}\n";
            echo "- Zip backups: {$status['zip_backups']}\n";
            echo "- Manual backups: {$status['manual_backups']}\n";
            echo "- Total size: " . number_format($status['total_backup_size'] / 1024 / 1024, 2) . " MB\n";
            break;
            
        case 'list':
            $backups = $backup_system->listRecentBackups();
            echo "Recent Backups:\n";
            foreach ($backups as $backup) {
                echo "- {$backup['filename']} ({$backup['type']}) - " . number_format($backup['size'] / 1024, 2) . " KB - {$backup['created']}\n";
            }
            break;
            
        default:
            echo "Available actions: hourly, daily, manual, cleanup, status, list\n";
            break;
    }
    
    echo "\n=== Backup operation completed ===\n";
}
?>