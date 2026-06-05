<?php
// Backup Monitor Service for ROTC System
// Monitors backup status and provides admin dashboard integration

class BackupMonitor {
    private $backup_scheduler;
    private $status_file = 'backup_status.json';
    private $service_file = 'backup_service.json';
    
    public function __construct() {
        require_once 'backup_scheduler.php';
        $this->backup_scheduler = new BackupScheduler();
    }
    
    public function getBackupServiceStatus() {
        $status = [
            'service_running' => $this->isServiceRunning(),
            'last_check' => date('Y-m-d H:i:s'),
            'next_hourly' => $this->getNextHourlyTime(),
            'next_daily' => $this->getNextDailyTime(),
            'backup_counts' => $this->getBackupCounts(),
            'recent_backups' => $this->getRecentBackups(),
            'service_health' => 'healthy'
        ];
        
        // Check if backups are running on schedule
        $schedule_status = $this->backup_scheduler->getScheduleStatus();
        $last_hourly = $schedule_status['schedule']['last_hourly'] ?? null;
        
        if ($last_hourly) {
            $last_hourly_time = DateTime::createFromFormat('Y-m-d-H', $last_hourly);
            $current_time = new DateTime();
            $diff = $current_time->diff($last_hourly_time);
            
            // If last backup was more than 2 hours ago, mark as unhealthy
            if ($diff->h >= 2 || $diff->days > 0) {
                $status['service_health'] = 'warning';
                $status['warning_message'] = 'Last backup was more than 2 hours ago';
            }
        } else {
            $status['service_health'] = 'error';
            $status['warning_message'] = 'No backup history found';
        }
        
        return $status;
    }
    
    public function isServiceRunning() {
        // Check if backup service is active by looking at recent activity
        $schedule_data = $this->backup_scheduler->getScheduleStatus();
        $last_updated = $schedule_data['schedule']['last_updated'] ?? null;
        
        if (!$last_updated) {
            return false;
        }
        
        $last_update_time = strtotime($last_updated);
        $current_time = time();
        
        // Consider service running if updated within last 2 hours
        return ($current_time - $last_update_time) < 7200;
    }
    
    public function startBackupService() {
        // Simulate starting the backup service
        $service_data = [
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
            'pid' => getmypid(),
            'last_activity' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($this->service_file, json_encode($service_data, JSON_PRETTY_PRINT));
        
        // Run initial backup check
        $this->runBackupCheck();
        
        return ['success' => true, 'message' => 'Backup service started'];
    }
    
    public function stopBackupService() {
        if (file_exists($this->service_file)) {
            unlink($this->service_file);
        }
        
        return ['success' => true, 'message' => 'Backup service stopped'];
    }
    
    public function runBackupCheck() {
        try {
            $results = $this->backup_scheduler->runScheduledBackups();
            
            $status = [
                'last_check' => date('Y-m-d H:i:s'),
                'results' => $results,
                'status' => 'success'
            ];
            
            file_put_contents($this->status_file, json_encode($status, JSON_PRETTY_PRINT));
            
            return $status;
        } catch (Exception $e) {
            $status = [
                'last_check' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
            
            file_put_contents($this->status_file, json_encode($status, JSON_PRETTY_PRINT));
            
            return $status;
        }
    }
    
    public function createManualBackup() {
        return $this->backup_scheduler->createManualBackup();
    }
    
    private function getNextHourlyTime() {
        return date('Y-m-d H:00:00', strtotime('+1 hour'));
    }
    
    private function getNextDailyTime() {
        return date('Y-m-d 02:00:00', strtotime('+1 day'));
    }
    
    private function getBackupCounts() {
        return [
            'hourly' => count(glob('backups/hourly/rotc_db_backup_hourly_*.sql')),
            'daily' => count(glob('backups/daily/rotc_db_backup_daily_*.sql')),
            'manual' => count(glob('backups/rotc_db_manual_backup_*.sql'))
        ];
    }
    
    private function getRecentBackups() {
        $backups = [];
        
        // Get recent hourly backups
        $hourly_files = glob('backups/hourly/rotc_db_backup_hourly_*.sql');
        usort($hourly_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        foreach (array_slice($hourly_files, 0, 5) as $file) {
            $backups[] = [
                'type' => 'hourly',
                'filename' => basename($file),
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Get recent daily backups
        $daily_files = glob('backups/daily/rotc_db_backup_daily_*.sql');
        usort($daily_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        foreach (array_slice($daily_files, 0, 3) as $file) {
            $backups[] = [
                'type' => 'daily',
                'filename' => basename($file),
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by creation time
        usort($backups, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return array_slice($backups, 0, 8);
    }
    
    public function getBackupDownloadUrl($filename) {
        $backup_dirs = ['backups/hourly', 'backups/daily', 'backups'];
        
        foreach ($backup_dirs as $dir) {
            $file_path = $dir . '/' . $filename;
            if (file_exists($file_path)) {
                return 'download_backup.php?file=' . urlencode($filename);
            }
        }
        
        return null;
    }
}

// Web API endpoints
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $monitor = new BackupMonitor();
    $response = ['success' => false, 'message' => ''];
    
    switch ($_GET['action']) {
        case 'status':
            $response = [
                'success' => true,
                'data' => $monitor->getBackupServiceStatus()
            ];
            break;
            
        case 'start_service':
            $response = $monitor->startBackupService();
            break;
            
        case 'stop_service':
            $response = $monitor->stopBackupService();
            break;
            
        case 'run_backup':
            $response = $monitor->runBackupCheck();
            $response['success'] = true;
            break;
            
        case 'manual_backup':
            $result = $monitor->createManualBackup();
            $response = $result;
            break;
            
        default:
            $response['message'] = 'Invalid action';
            break;
    }
    
    echo json_encode($response);
    exit;
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $monitor = new BackupMonitor();
    
    $action = isset($argv[1]) ? $argv[1] : 'status';
    
    switch ($action) {
        case 'start':
            $result = $monitor->startBackupService();
            echo $result['message'] . "\n";
            break;
            
        case 'stop':
            $result = $monitor->stopBackupService();
            echo $result['message'] . "\n";
            break;
            
        case 'check':
            $monitor->runBackupCheck();
            echo "Backup check completed\n";
            break;
            
        case 'status':
        default:
            $status = $monitor->getBackupServiceStatus();
            echo "=== Backup Service Status ===\n";
            echo "Service Running: " . ($status['service_running'] ? 'Yes' : 'No') . "\n";
            echo "Health: {$status['service_health']}\n";
            echo "Next Hourly: {$status['next_hourly']}\n";
            echo "Next Daily: {$status['next_daily']}\n";
            echo "Backup Counts: Hourly={$status['backup_counts']['hourly']}, Daily={$status['backup_counts']['daily']}\n";
            break;
    }
}
?>