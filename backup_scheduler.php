<?php
// Backup Scheduler for ROTC Database System
// Handles automated hourly and daily backup scheduling

require_once 'backup_system.php';

class BackupScheduler {
    private $backup_system;
    private $schedule_file = 'backup_schedule.json';
    
    public function __construct() {
        $this->backup_system = new DatabaseBackupSystem();
    }
    
    public function shouldRunHourlyBackup() {
        $schedule = $this->getScheduleData();
        $current_hour = date('Y-m-d-H');
        
        // Check if hourly backup already ran this hour
        if (isset($schedule['last_hourly']) && $schedule['last_hourly'] === $current_hour) {
            return false;
        }
        
        return true;
    }
    
    public function shouldRunDailyBackup() {
        $schedule = $this->getScheduleData();
        $current_date = date('Y-m-d');
        
        // Check if daily backup already ran today
        if (isset($schedule['last_daily']) && $schedule['last_daily'] === $current_date) {
            return false;
        }
        
        // Run daily backup at 2 AM or later
        $current_hour = (int)date('H');
        if ($current_hour >= 2) {
            return true;
        }
        
        return false;
    }
    
    public function runScheduledBackups() {
        echo "=== Backup Scheduler Running ===\n";
        echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";
        
        $results = [];
        
        // Check and run daily backup first
        if ($this->shouldRunDailyBackup()) {
            echo "Running scheduled daily backup...\n";
            $daily_result = $this->backup_system->createDailyBackup();
            
            if ($daily_result['success']) {
                $this->updateScheduleData('last_daily', date('Y-m-d'));
                echo "✓ Daily backup completed successfully\n";
            }
            
            $results['daily'] = $daily_result;
        } else {
            echo "Daily backup not needed (already completed today or too early)\n";
        }
        
        // Check and run hourly backup
        if ($this->shouldRunHourlyBackup()) {
            echo "\nRunning scheduled hourly backup...\n";
            $hourly_result = $this->backup_system->createBackup('hourly');
            
            if ($hourly_result['success']) {
                $this->updateScheduleData('last_hourly', date('Y-m-d-H'));
                echo "✓ Hourly backup completed successfully\n";
            }
            
            $results['hourly'] = $hourly_result;
        } else {
            echo "\nHourly backup not needed (already completed this hour)\n";
        }
        
        // Run cleanup if it's a new day
        $schedule = $this->getScheduleData();
        if (!isset($schedule['last_cleanup']) || $schedule['last_cleanup'] !== date('Y-m-d')) {
            echo "\nRunning backup cleanup...\n";
            $this->backup_system->cleanupOldBackups();
            $this->updateScheduleData('last_cleanup', date('Y-m-d'));
            echo "✓ Cleanup completed\n";
        }
        
        echo "\n=== Scheduler completed ===\n";
        return $results;
    }
    
    private function getScheduleData() {
        if (file_exists($this->schedule_file)) {
            $data = file_get_contents($this->schedule_file);
            return json_decode($data, true) ?: [];
        }
        return [];
    }
    
    private function updateScheduleData($key, $value) {
        $schedule = $this->getScheduleData();
        $schedule[$key] = $value;
        $schedule['last_updated'] = date('Y-m-d H:i:s');
        
        file_put_contents($this->schedule_file, json_encode($schedule, JSON_PRETTY_PRINT));
    }
    
    public function getScheduleStatus() {
        $schedule = $this->getScheduleData();
        $status = $this->backup_system->getBackupStatus();
        
        return [
            'schedule' => $schedule,
            'backup_counts' => $status,
            'next_hourly' => $this->getNextHourlyTime(),
            'next_daily' => $this->getNextDailyTime()
        ];
    }
    
    private function getNextHourlyTime() {
        $next_hour = date('Y-m-d H:00:00', strtotime('+1 hour'));
        return $next_hour;
    }
    
    private function getNextDailyTime() {
        $tomorrow_2am = date('Y-m-d 02:00:00', strtotime('+1 day'));
        return $tomorrow_2am;
    }
    
    public function createManualBackup() {
        echo "Creating manual backup...\n";
        return $this->backup_system->createBackup('hourly', true);
    }
}

// Web interface for manual backup operations
if (isset($_GET['web_action'])) {
    header('Content-Type: application/json');
    
    $scheduler = new BackupScheduler();
    $response = ['success' => false, 'message' => ''];
    
    switch ($_GET['web_action']) {
        case 'manual_backup':
            $result = $scheduler->createManualBackup();
            $response = $result;
            break;
            
        case 'status':
            $response = [
                'success' => true,
                'data' => $scheduler->getScheduleStatus()
            ];
            break;
            
        case 'run_scheduler':
            ob_start();
            $results = $scheduler->runScheduledBackups();
            $output = ob_get_clean();
            
            $response = [
                'success' => true,
                'results' => $results,
                'output' => $output
            ];
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
    $scheduler = new BackupScheduler();
    
    $action = isset($argv[1]) ? $argv[1] : 'run';
    
    switch ($action) {
        case 'run':
            $scheduler->runScheduledBackups();
            break;
            
        case 'manual':
            $scheduler->createManualBackup();
            break;
            
        case 'status':
            $status = $scheduler->getScheduleStatus();
            echo "=== Backup Schedule Status ===\n";
            echo "Last hourly: " . ($status['schedule']['last_hourly'] ?? 'Never') . "\n";
            echo "Last daily: " . ($status['schedule']['last_daily'] ?? 'Never') . "\n";
            echo "Last cleanup: " . ($status['schedule']['last_cleanup'] ?? 'Never') . "\n";
            echo "Next hourly: {$status['next_hourly']}\n";
            echo "Next daily: {$status['next_daily']}\n";
            echo "\nBackup counts:\n";
            echo "- Hourly: {$status['backup_counts']['hourly_backups']}\n";
            echo "- Daily: {$status['backup_counts']['daily_backups']}\n";
            echo "- Manual: {$status['backup_counts']['manual_backups']}\n";
            echo "- Zip: {$status['backup_counts']['zip_backups']}\n";
            break;
            
        default:
            echo "Usage: php backup_scheduler.php [run|manual|status]\n";
            break;
    }
}
?>