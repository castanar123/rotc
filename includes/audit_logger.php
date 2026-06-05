<?php
/**
 * Audit Logger Class
 * Provides comprehensive audit logging functionality for security events
 */

require_once 'secure_db.php';

class AuditLogger {
    private $secure_db;
    private $enabled;
    
    public function __construct() {
        global $secure_db;
        $this->secure_db = $secure_db;
        $this->enabled = true;
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($action, $description, $user_id = null, $severity = 'LOW') {
        if (!$this->enabled) return false;
        
        return $this->secure_db->auditLog($action, $description, $user_id, $severity);
    }
    
    /**
     * Log login attempts
     */
    public function logLoginAttempt($username, $success = false, $ip_address = null) {
        $ip_address = $ip_address ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $action = $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED';
        $description = $success ? 
            "Successful login for user: {$username} from {$ip_address}" :
            "Failed login attempt for user: {$username} from {$ip_address}";
        $severity = $success ? 'LOW' : 'MEDIUM';
        
        return $this->logSecurityEvent($action, $description, null, $severity);
    }
    
    /**
     * Log data access events
     */
    public function logDataAccess($table, $action, $user_id = null, $record_id = null) {
        $description = "Data {$action} on table {$table}";
        if ($record_id) {
            $description .= " (record ID: {$record_id})";
        }
        
        return $this->logSecurityEvent('DATA_ACCESS', $description, $user_id, 'LOW');
    }
    
    /**
     * Log privilege escalation attempts
     */
    public function logPrivilegeEscalation($user_id, $attempted_action, $required_role) {
        $description = "User attempted action '{$attempted_action}' requiring role '{$required_role}'";
        return $this->logSecurityEvent('PRIVILEGE_ESCALATION', $description, $user_id, 'HIGH');
    }
    
    /**
     * Log file access events
     */
    public function logFileAccess($filename, $action, $user_id = null) {
        $description = "File {$action}: {$filename}";
        return $this->logSecurityEvent('FILE_ACCESS', $description, $user_id, 'MEDIUM');
    }
    
    /**
     * Log configuration changes
     */
    public function logConfigChange($setting, $old_value, $new_value, $user_id = null) {
        $description = "Configuration changed: {$setting} from '{$old_value}' to '{$new_value}'";
        return $this->logSecurityEvent('CONFIG_CHANGE', $description, $user_id, 'HIGH');
    }
    
    /**
     * Log suspicious activities
     */
    public function logSuspiciousActivity($activity, $details, $user_id = null) {
        $description = "Suspicious activity detected: {$activity} - {$details}";
        return $this->logSecurityEvent('SUSPICIOUS_ACTIVITY', $description, $user_id, 'CRITICAL');
    }
    
    /**
     * Get audit logs with filtering
     */
    public function getAuditLogs($filters = [], $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM security_audit_logs WHERE 1=1";
        $params = [];
        
        // Apply filters
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['severity'])) {
            $sql .= " AND severity = ?";
            $params[] = $filters['severity'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND timestamp >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND timestamp <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $stmt = $this->secure_db->getPDO()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to retrieve audit logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get audit log statistics
     */
    public function getAuditStats($days = 30) {
        $sql = "SELECT 
                    severity,
                    COUNT(*) as count,
                    DATE(timestamp) as log_date
                FROM security_audit_logs 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY severity, DATE(timestamp)
                ORDER BY log_date DESC, severity";
        
        try {
            $stmt = $this->secure_db->getPDO()->prepare($sql);
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to retrieve audit statistics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Enable/disable audit logging
     */
    public function setEnabled($enabled) {
        $this->enabled = (bool)$enabled;
    }
    
    /**
     * Check if audit logging is enabled
     */
    public function isEnabled() {
        return $this->enabled;
    }
}

// Create global audit logger instance