<?php
require_once __DIR__ . '/db.php';

class SecurityLogger {
    private $pdo;

    public static function log($eventType, $severity, $description, $metadata = []) {
        $logger = new self();
        $userId = is_array($metadata) && array_key_exists('user_id', $metadata) ? $metadata['user_id'] : null;
        return $logger->logSecurityEvent($userId, $eventType, $description, is_array($metadata) ? $metadata : [], $severity);
    }
    
    public function __construct($pdoArg = null) {
        global $pdo;
        // Prefer explicitly passed PDO if valid; otherwise use global; else null
        if (class_exists('PDO') && ($pdoArg instanceof PDO)) {
            $this->pdo = $pdoArg;
        } elseif (class_exists('PDO') && isset($pdo) && ($pdo instanceof PDO)) {
            $this->pdo = $pdo;
        } else {
            $this->pdo = null;
        }
    }

    /**
     * Log security event
     */
    public function logSecurityEvent($userId, $eventType, $description, $metadata = [], $severity = 'medium') {
        // Gracefully handle missing PDO (e.g., DB down) without fatal error
        if (!(class_exists('PDO') && ($this->pdo instanceof PDO))) {
            error_log(sprintf('SecurityLogger: PDO unavailable, skipping log [%s:%s] %s', (string)$eventType, (string)$severity, (string)$description));
            return false;
        }
        try {
            $stmt = $this->pdo->prepare("\n                INSERT INTO security_logs (user_id, event_type, description, ip_address, user_agent, metadata, severity) \n                VALUES (?, ?, ?, ?, ?, ?, ?)\n            ");
            $ipAddress = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            // Ensure metadata is JSON string
            if (!is_array($metadata)) { $metadata = ['meta' => (string)$metadata]; }
            $metadataJson = json_encode($metadata);
            // Normalize severity to lowercase common values
            $sev = strtolower((string)$severity);
            switch ($sev) {
                case 'low':
                case 'medium':
                case 'high':
                    break;
                default:
                    $sev = 'medium';
            }
            $stmt->execute([
                $userId,
                $eventType,
                $description,
                $ipAddress,
                $userAgent,
                $metadataJson,
                $sev
            ]);
            $logId = $this->pdo->lastInsertId();
            // Low-severity routine events should not do extra alert queries on every request.
            if ($sev !== 'low') {
                $this->checkForAlerts($logId, $eventType, $sev, $metadata);
            }
            return $logId;
        } catch (Throwable $e) {
            error_log('Security logging failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log login attempt
     */
    public function logLoginAttempt($userId, $username, $success, $reason = '') {
        $eventType = $success ? 'login_success' : 'login_failed';
        $description = $success ? 
            "User {$username} logged in successfully" : 
            "Failed login attempt for user {$username}: {$reason}";
        
        $metadata = [
            'username' => $username,
            'success' => $success,
            'reason' => $reason
        ];
        
        $severity = $success ? 'low' : 'medium';
        
        return $this->logSecurityEvent($userId, $eventType, $description, $metadata, $severity);
    }
    
    /**
     * Log password change
     */
    public function logPasswordChange($userId, $username) {
        return $this->logSecurityEvent(
            $userId,
            'password_changed',
            "Password changed for user {$username}",
            ['username' => $username],
            'medium'
        );
    }
    
    /**
     * Log account lockout
     */
    public function logAccountLockout($userId, $username, $reason) {
        return $this->logSecurityEvent(
            $userId,
            'account_locked',
            "Account locked for user {$username}: {$reason}",
            ['username' => $username, 'reason' => $reason],
            'high'
        );
    }
    
    /**
     * Log suspicious activity
     */
    public function logSuspiciousActivity($userId, $activity, $details = []) {
        return $this->logSecurityEvent(
            $userId,
            'suspicious_activity',
            "Suspicious activity detected: {$activity}",
            $details,
            'high'
        );
    }
    
    /**
     * Log data access
     */
    public function logDataAccess($userId, $resource, $action, $details = []) {
        return $this->logSecurityEvent(
            $userId,
            'data_access',
            "Data access: {$action} on {$resource}",
            array_merge($details, ['resource' => $resource, 'action' => $action]),
            'low'
        );
    }
    
    /**
     * Log system changes
     */
    public function logSystemChange($userId, $change, $details = []) {
        return $this->logSecurityEvent(
            $userId,
            'system_change',
            "System change: {$change}",
            $details,
            'medium'
        );
    }
    
    /**
     * Get security logs with filtering
     */
    public function getSecurityLogs($filters = [], $limit = 100, $offset = 0) {
        $where = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = 'sl.user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['event_type'])) {
            $where[] = 'sl.event_type = ?';
            $params[] = $filters['event_type'];
        }
        
        if (!empty($filters['severity'])) {
            $where[] = 'sl.severity = ?';
            $params[] = $filters['severity'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'sl.created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'sl.created_at <= ?';
            $params[] = $filters['date_to'];
        }
        
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        
        $sql = "
            SELECT sl.*, u.username, u.full_name
            FROM security_logs sl
            LEFT JOIN users u ON sl.user_id = u.id
            {$whereClause}
            ORDER BY sl.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get security statistics
     */
    public function getSecurityStats($days = 30) {
        $dateFrom = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Total events by severity
        $stmt = $this->pdo->prepare("
            SELECT severity, COUNT(*) as count
            FROM security_logs
            WHERE created_at >= ?
            GROUP BY severity
        ");
        $stmt->execute([$dateFrom]);
        $severityStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Events by type
        $stmt = $this->pdo->prepare("
            SELECT event_type, COUNT(*) as count
            FROM security_logs
            WHERE created_at >= ?
            GROUP BY event_type
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$dateFrom]);
        $eventTypeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Daily event counts
        $stmt = $this->pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM security_logs
            WHERE created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$dateFrom]);
        $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'severity_stats' => $severityStats,
            'event_type_stats' => $eventTypeStats,
            'daily_stats' => $dailyStats,
            'total_events' => array_sum($severityStats)
        ];
    }
    
    /**
     * Check for security alerts and create notifications
     */
    private function checkForAlerts($logId, $eventType, $severity, $metadata) {
        $alertRules = [
            'multiple_failed_logins' => [
                'event_type' => 'login_failed',
                'threshold' => 5,
                'timeframe' => 300, // 5 minutes
                'severity' => 'high'
            ],
            'backup_failures' => [
                'event_type' => 'backup_failed',
                'threshold' => 1,
                'timeframe' => 3600, // 1 hour
                'severity' => 'critical'
            ],
            'suspicious_activity' => [
                'event_type' => 'suspicious_activity',
                'threshold' => 1,
                'timeframe' => 0,
                'severity' => 'critical'
            ]
        ];
        
        foreach ($alertRules as $ruleName => $rule) {
            if ($eventType === $rule['event_type']) {
                $this->checkAlertRule($logId, $ruleName, $rule);
            }
        }
    }
    
    /**
     * Check specific alert rule
     */
    private function checkAlertRule($logId, $ruleName, $rule) {
        $timeframe = $rule['timeframe'];
        $threshold = $rule['threshold'];
        
        if ($timeframe > 0) {
            $timeLimit = date('Y-m-d H:i:s', time() - $timeframe);
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM security_logs 
                WHERE event_type = ? AND created_at >= ?
            ");
            $stmt->execute([$rule['event_type'], $timeLimit]);
            $count = $stmt->fetchColumn();
        } else {
            $count = 1; // Immediate alert
        }
        
        if ($count >= $threshold) {
            $this->createAlert($logId, $ruleName, $rule['severity'], [
                'rule' => $ruleName,
                'threshold' => $threshold,
                'actual_count' => $count,
                'timeframe' => $timeframe
            ]);
        }
    }
    
    /**
     * Create security alert notification
     */
    private function createAlert($logId, $alertType, $severity, $details) {
        $message = $this->generateAlertMessage($alertType, $details);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO alert_notifications (log_id, alert_type, message, severity, recipient) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        // Get admin email for notifications
        $adminEmail = $this->getAdminEmail();
        
        $stmt->execute([$logId, $alertType, $message, $severity, $adminEmail]);
        
        // TODO: Implement actual email sending
        // $this->sendAlertEmail($adminEmail, $alertType, $message, $severity);
    }
    
    /**
     * Generate alert message
     */
    private function generateAlertMessage($alertType, $details) {
        switch ($alertType) {
            case 'multiple_failed_logins':
                return "Multiple failed login attempts detected: {$details['actual_count']} attempts in {$details['timeframe']} seconds";
            case 'backup_failures':
                return "Backup system failure detected";
            case 'suspicious_activity':
                return "Suspicious activity detected and requires immediate attention";
            default:
                return "Security alert: {$alertType}";
        }
    }
    
    /**
     * Get admin email for notifications
     */
    private function getAdminEmail() {
        $stmt = $this->pdo->prepare("
            SELECT email FROM users 
            WHERE role = 'admin' 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result ? $result['email'] : 'admin@rotc.local';
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
?>
