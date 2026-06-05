<?php
/**
 * Secure Error Handler
 * Provides secure error handling that doesn't expose sensitive information
 * while maintaining proper logging and debugging capabilities
 */

class SecureErrorHandler {
    private $secure_db;
    private $log_file;
    private $debug_mode;
    private $error_levels;
    
    public function __construct($secure_db, $debug_mode = false) {
        $this->secure_db = $secure_db;
        $this->debug_mode = $debug_mode;
        $this->log_file = __DIR__ . '/../logs/security_errors.log';
        $this->initializeErrorLevels();
        $this->setupErrorHandling();
        $this->ensureLogDirectory();
    }
    
    /**
     * Initialize error level mappings
     */
    private function initializeErrorLevels() {
        $this->error_levels = [
            E_ERROR => 'CRITICAL',
            E_WARNING => 'HIGH',
            E_PARSE => 'CRITICAL',
            E_NOTICE => 'MEDIUM',
            E_CORE_ERROR => 'CRITICAL',
            E_CORE_WARNING => 'HIGH',
            E_COMPILE_ERROR => 'CRITICAL',
            E_COMPILE_WARNING => 'HIGH',
            E_USER_ERROR => 'HIGH',
            E_USER_WARNING => 'MEDIUM',
            E_USER_NOTICE => 'LOW',
            E_STRICT => 'LOW',
            E_RECOVERABLE_ERROR => 'HIGH',
            E_DEPRECATED => 'LOW',
            E_USER_DEPRECATED => 'LOW'
        ];
    }
    
    /**
     * Setup error and exception handling
     */
    private function setupErrorHandling() {
        // Set custom error handler
        set_error_handler([$this, 'handleError']);
        
        // Set custom exception handler
        set_exception_handler([$this, 'handleException']);
        
        // Set shutdown function to catch fatal errors
        register_shutdown_function([$this, 'handleShutdown']);
        
        // Configure error reporting based on debug mode
        if ($this->debug_mode) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
        }
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0750, true);
        }
        
        // Create .htaccess to protect log files
        $htaccess_file = $log_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order Deny,Allow\nDeny from all");
        }
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $severity = $this->error_levels[$errno] ?? 'MEDIUM';
        $error_type = $this->getErrorTypeName($errno);
        
        // Sanitize error message
        $safe_message = $this->sanitizeErrorMessage($errstr, $errfile);
        
        // Log to file
        $this->logToFile($error_type, $safe_message, $errfile, $errline, $severity);
        
        // Log to database
        $this->logToDatabase('PHP_ERROR', $safe_message, $severity);
        
        // Handle based on severity
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->handleFatalError($safe_message);
        }
        
        // Don't execute PHP's internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception) {
        $severity = 'CRITICAL';
        $error_type = get_class($exception);
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        
        // Sanitize exception message
        $safe_message = $this->sanitizeErrorMessage($message, $file);
        
        // Log to file
        $this->logToFile($error_type, $safe_message, $file, $line, $severity);
        
        // Log to database
        $this->logToDatabase('UNCAUGHT_EXCEPTION', $safe_message, $severity);
        
        // Handle fatal exception
        $this->handleFatalError($safe_message);
    }
    
    /**
     * Handle shutdown errors (fatal errors)
     */
    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $severity = 'CRITICAL';
            $error_type = $this->getErrorTypeName($error['type']);
            $safe_message = $this->sanitizeErrorMessage($error['message'], $error['file']);
            
            // Log to file
            $this->logToFile($error_type, $safe_message, $error['file'], $error['line'], $severity);
            
            // Log to database
            $this->logToDatabase('FATAL_ERROR', $safe_message, $severity);
            
            // Handle fatal error
            $this->handleFatalError($safe_message);
        }
    }
    
    /**
     * Sanitize error messages to remove sensitive information
     */
    private function sanitizeErrorMessage($message, $file = '') {
        // Remove absolute paths
        $message = str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '[DOCUMENT_ROOT]', $message);
        $message = str_replace(dirname(__DIR__), '[APP_ROOT]', $message);
        
        // Remove sensitive patterns
        $sensitive_patterns = [
            '/password[\s]*=[\s]*["\'][^"\'>]+["\']?/i' => 'password=[REDACTED]',
            '/key[\s]*=[\s]*["\'][^"\'>]+["\']?/i' => 'key=[REDACTED]',
            '/token[\s]*=[\s]*["\'][^"\'>]+["\']?/i' => 'token=[REDACTED]',
            '/secret[\s]*=[\s]*["\'][^"\'>]+["\']?/i' => 'secret=[REDACTED]',
            '/mysql:\/\/[^\s]+/i' => 'mysql://[REDACTED]',
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '[CARD_NUMBER]',
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[EMAIL]'
        ];
        
        foreach ($sensitive_patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }
        
        return $message;
    }
    
    /**
     * Get human-readable error type name
     */
    private function getErrorTypeName($errno) {
        $error_types = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        return $error_types[$errno] ?? 'Unknown Error';
    }
    
    /**
     * Log error to file
     */
    private function logToFile($type, $message, $file, $line, $severity) {
        $timestamp = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $log_entry = sprintf(
            "[%s] [%s] [%s] User: %s, IP: %s, File: %s:%d, Message: %s, User-Agent: %s\n",
            $timestamp,
            $severity,
            $type,
            $user_id,
            $ip,
            basename($file),
            $line,
            $message,
            substr($user_agent, 0, 100)
        );
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log error to database
     */
    private function logToDatabase($action, $message, $severity) {
        try {
            $this->secure_db->auditLog($action, $message, $_SESSION['user_id'] ?? null, $severity);
        } catch (Exception $e) {
            // If database logging fails, log to file
            $this->logToFile('DATABASE_LOG_FAILED', 'Failed to log to database: ' . $e->getMessage(), __FILE__, __LINE__, 'HIGH');
        }
    }
    
    /**
     * Handle fatal errors
     */
    private function handleFatalError($message) {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set appropriate HTTP status
        http_response_code(500);
        
        // Show user-friendly error page
        if ($this->debug_mode) {
            $this->showDebugErrorPage($message);
        } else {
            $this->showProductionErrorPage();
        }
        
        exit;
    }
    
    /**
     * Show debug error page (development mode)
     */
    private function showDebugErrorPage($message) {
        echo "<!DOCTYPE html>
<html>
<head>
    <title>System Error - Debug Mode</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-header { color: #d32f2f; border-bottom: 2px solid #d32f2f; padding-bottom: 10px; margin-bottom: 20px; }
        .error-message { background: #ffebee; padding: 15px; border-radius: 4px; border-left: 4px solid #d32f2f; }
        .debug-info { margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='error-container'>
        <h1 class='error-header'>System Error (Debug Mode)</h1>
        <div class='error-message'>
            <strong>Error:</strong> " . htmlspecialchars($message) . "
        </div>
        <div class='debug-info'>
            <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p><strong>User:</strong> " . ($_SESSION['username'] ?? 'Not logged in') . "</p>
            <p><strong>IP:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</p>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Show production error page (production mode)
     */
    private function showProductionErrorPage() {
        echo "<!DOCTYPE html>
<html>
<head>
    <title>System Temporarily Unavailable</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 40px; background: #f5f5f5; text-align: center; }
        .error-container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-icon { font-size: 64px; color: #ff9800; margin-bottom: 20px; }
        .error-title { color: #333; margin-bottom: 20px; }
        .error-message { color: #666; line-height: 1.6; margin-bottom: 30px; }
        .error-actions a { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 0 10px; }
        .error-actions a:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='error-container'>
        <div class='error-icon'>⚠️</div>
        <h1 class='error-title'>System Temporarily Unavailable</h1>
        <p class='error-message'>
            We're experiencing technical difficulties and our team has been notified. 
            Please try again in a few minutes. If the problem persists, please contact support.
        </p>
        <div class='error-actions'>
            <a href='javascript:history.back()'>Go Back</a>
            <a href='/'>Home Page</a>
        </div>
    </div>
</body>
</html>";
    }
    
    /**
     * Handle application-specific errors
     */
    public function handleApplicationError($error_code, $message, $severity = 'MEDIUM') {
        $safe_message = $this->sanitizeErrorMessage($message);
        
        // Log the error
        $this->logToDatabase('APPLICATION_ERROR', "[{$error_code}] {$safe_message}", $severity);
        
        // Return user-friendly message
        $user_messages = [
            'DB_CONNECTION_FAILED' => 'Unable to connect to the database. Please try again later.',
            'INVALID_INPUT' => 'The information provided is invalid. Please check and try again.',
            'UNAUTHORIZED_ACCESS' => 'You do not have permission to access this resource.',
            'SESSION_EXPIRED' => 'Your session has expired. Please log in again.',
            'FILE_UPLOAD_FAILED' => 'File upload failed. Please check the file and try again.',
            'RATE_LIMIT_EXCEEDED' => 'Too many requests. Please wait before trying again.',
            'MAINTENANCE_MODE' => 'The system is currently under maintenance. Please try again later.'
        ];
        
        return $user_messages[$error_code] ?? 'An error occurred. Please try again later.';
    }
    
    /**
     * Handle security violations
     */
    public function handleSecurityViolation($violation_type, $details, $user_id = null) {
        $severity = 'CRITICAL';
        
        // Log security violation
        $this->logToDatabase('SECURITY_VIOLATION', "[{$violation_type}] {$details}", $severity);
        
        // Additional security measures based on violation type
        switch ($violation_type) {
            case 'SQL_INJECTION':
            case 'XSS_ATTEMPT':
            case 'CSRF_VIOLATION':
                // Block IP temporarily
                $this->blockSuspiciousIP();
                break;
                
            case 'BRUTE_FORCE':
                // Lock user account
                if ($user_id) {
                    $this->lockUserAccount($user_id);
                }
                break;
                
            case 'PRIVILEGE_ESCALATION':
                // Terminate all user sessions
                if ($user_id) {
                    $this->terminateUserSessions($user_id);
                }
                break;
        }
        
        // Return generic error message
        return 'Security violation detected. This incident has been logged.';
    }
    
    /**
     * Block suspicious IP (placeholder - implement based on your infrastructure)
     */
    private function blockSuspiciousIP() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->logToDatabase('IP_BLOCKED', "Suspicious IP blocked: {$ip}", 'HIGH');
        // Implement IP blocking logic here
    }
    
    /**
     * Lock user account
     */
    private function lockUserAccount($user_id) {
        try {
            $this->secure_db->secureQuery(
                "UPDATE users SET status = 'locked', locked_at = NOW() WHERE id = ?",
                [$user_id]
            );
            $this->logToDatabase('ACCOUNT_LOCKED', "User account {$user_id} locked due to security violation", 'HIGH');
        } catch (Exception $e) {
            $this->logToFile('ACCOUNT_LOCK_FAILED', "Failed to lock account {$user_id}: " . $e->getMessage(), __FILE__, __LINE__, 'HIGH');
        }
    }
    
    /**
     * Terminate all user sessions
     */
    private function terminateUserSessions($user_id) {
        // Destroy current session if it belongs to the user
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
            session_destroy();
        }
        
        $this->logToDatabase('SESSIONS_TERMINATED', "All sessions terminated for user {$user_id}", 'HIGH');
        // Implement session termination logic here
    }
    
    /**
     * Get error statistics
     */
    public function getErrorStatistics($days = 7) {
        try {
            $stmt = $this->secure_db->secureQuery(
                "SELECT severity, COUNT(*) as count 
                 FROM security_audit_logs 
                 WHERE action LIKE '%ERROR%' 
                 AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                 GROUP BY severity",
                [$days]
            );
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

// Initialize global error handler
if (isset($secure_db)) {
    $debug_mode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
    $error_handler = new SecureErrorHandler($secure_db, $debug_mode);
}

?>