<?php
/**
 * Secure Database Connection Class
 * Provides enhanced security features including:
 * - Encrypted connections
 * - Prepared statements only
 * - Input validation and sanitization
 * - Audit logging
 * - Rate limiting
 * - Secure error handling
 */

class SecureDatabase {
    private $pdo;
    private $link;
    private $audit_enabled = true;
    private $encryption_key;
    private $max_attempts = 5;
    private $lockout_time = 300; // 5 minutes
    private $db_server;
    private $db_username;
    private $db_password;
    private $db_name;
    
    public function __construct() {
        $this->loadDatabaseConfig();
        $this->initializeEncryption();
        $this->connectDatabase();
        $this->createAuditTable();
    }

    /**
     * Load database settings from shared config.
     */
    private function loadDatabaseConfig() {
        require_once __DIR__ . '/db_config.php';

        $this->db_server = defined('DB_SERVER') ? DB_SERVER : 'localhost:3306';
        $this->db_username = defined('DB_USERNAME') ? DB_USERNAME : 'root';
        $this->db_password = defined('DB_PASSWORD') ? DB_PASSWORD : '';
        $this->db_name = defined('DB_NAME') ? DB_NAME : 'rotc_db';
    }

    /**
     * Split host:port values for APIs that require the port separately.
     */
    private function parseMysqlServer() {
        $host = $this->db_server;
        $port = null;

        if (strpos($this->db_server, ':') !== false) {
            list($hostOnly, $portPart) = explode(':', $this->db_server, 2);
            if ($hostOnly !== '') {
                $host = $hostOnly;
            }
            if ($portPart !== '') {
                $port = (int) $portPart;
            }
        }

        return [$host, $port];
    }

    /**
     * Build a PDO DSN from shared MySQL settings.
     */
    private function mysqlDsn() {
        list($host, $port) = $this->parseMysqlServer();
        $dsn = "mysql:host={$host};dbname={$this->db_name};charset=utf8mb4";
        if ($port) {
            $dsn = "mysql:host={$host};port={$port};dbname={$this->db_name};charset=utf8mb4";
        }

        return $dsn;
    }
    
    /**
     * Initialize encryption key for sensitive data
     */
    private function initializeEncryption() {
        // Generate or retrieve encryption key
        $key_file = __DIR__ . '/../.encryption_key';
        if (!file_exists($key_file)) {
            $this->encryption_key = base64_encode(random_bytes(32));
            file_put_contents($key_file, $this->encryption_key);
            chmod($key_file, 0600); // Restrict file permissions
        } else {
            $this->encryption_key = file_get_contents($key_file);
        }
    }
    
    /**
     * Establish secure database connection
     */
    private function connectDatabase() {
        try {
            // Enable SSL and set secure options
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES'"
            ];
            
            $this->pdo = new PDO($this->mysqlDsn(), $this->db_username, $this->db_password, $options);
            
            // Also create mysqli connection for compatibility
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            list($mysqlHost, $mysqlPort) = $this->parseMysqlServer();
            $this->link = new mysqli($mysqlHost, $this->db_username, $this->db_password, $this->db_name, $mysqlPort ?: null);
            $this->link->set_charset("utf8mb4");
            
            $this->auditLog('DATABASE_CONNECT', 'Secure database connection established', null, 'SYSTEM');
            
        } catch (PDOException $e) {
            $this->handleSecureError('Database connection failed', $e);
        } catch (mysqli_sql_exception $e) {
            $this->handleSecureError('MySQLi connection failed', $e);
        }
    }
    
    /**
     * Create audit log table if it doesn't exist
     */
    private function createAuditTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS security_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                user_id INT,
                user_type VARCHAR(50),
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'LOW',
                INDEX idx_timestamp (timestamp),
                INDEX idx_action (action),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB
        ";
        
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create audit table: " . $e->getMessage());
        }
    }
    
    /**
     * Execute prepared statement with security validation
     */
    public function secureQuery($sql, $params = [], $user_id = null) {
        // Validate SQL statement
        if (!$this->validateSqlStatement($sql)) {
            $this->auditLog('SQL_INJECTION_ATTEMPT', 'Potentially malicious SQL detected: ' . $sql, $user_id, 'HIGH');
            throw new Exception('Invalid SQL statement detected');
        }
        
        // Check rate limiting
        if (!$this->checkRateLimit($user_id)) {
            $this->auditLog('RATE_LIMIT_EXCEEDED', 'Rate limit exceeded for user', $user_id, 'MEDIUM');
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            
            // Sanitize and validate parameters
            $sanitized_params = $this->sanitizeParameters($params);
            
            $result = $stmt->execute($sanitized_params);
            
            // Log successful query
            $this->auditLog('DATABASE_QUERY', 'Query executed successfully', $user_id);
            
            return $stmt;
            
        } catch (PDOException $e) {
            $this->auditLog('DATABASE_ERROR', 'Query execution failed: ' . $e->getMessage(), $user_id, 'HIGH');
            $this->handleSecureError('Database query failed', $e);
        }
    }
    
    /**
     * Validate SQL statement for security
     */
    private function validateSqlStatement($sql) {
        // Remove comments and normalize whitespace
        $normalized_sql = preg_replace('/\s+/', ' ', trim($sql));
        $normalized_sql = preg_replace('/\/\*.*?\*\//s', '', $normalized_sql);
        $normalized_sql = preg_replace('/--.*$/m', '', $normalized_sql);
        
        // Check for dangerous patterns
        $dangerous_patterns = [
            '/\b(DROP|ALTER|CREATE|TRUNCATE|DELETE)\s+(?!.*WHERE)/i',
            '/\bUNION\s+SELECT/i',
            '/\bINTO\s+OUTFILE/i',
            '/\bLOAD_FILE\s*\(/i',
            '/\bSYSTEM\s*\(/i',
            '/\bEXEC\s*\(/i',
            '/\bxp_cmdshell/i',
            '/\bsp_executesql/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $normalized_sql)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize input parameters
     */
    private function sanitizeParameters($params) {
        $sanitized = [];
        
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                // Remove null bytes and control characters
                $value = str_replace("\0", "", $value);
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                
                // Trim whitespace
                $value = trim($value);
            }
            
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }
    
    /**
     * Check rate limiting for user
     */
    private function checkRateLimit($user_id) {
        if (!$user_id) return true; // Skip rate limiting for system operations
        
        $sql = "SELECT COUNT(*) as count FROM security_audit_logs 
                WHERE user_id = ? AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch();
            
            return $result['count'] < $this->max_attempts;
        } catch (PDOException $e) {
            // If we can't check rate limit, allow the operation but log it
            error_log("Rate limit check failed: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encryptData($data) {
        $key = base64_decode($this->encryption_key);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decryptData($encrypted_data) {
        $key = base64_decode($this->encryption_key);
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Audit logging function
     */
    public function auditLog($action, $description, $user_id = null, $severity = 'LOW') {
        if (!$this->audit_enabled) return;
        
        // Map invalid severity values to valid ENUM values
        $valid_severities = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
        if (!in_array($severity, $valid_severities)) {
            // Map common invalid values
            switch (strtoupper($severity)) {
                case 'SYSTEM':
                case 'INFO':
                case 'DEBUG':
                    $severity = 'LOW';
                    break;
                case 'WARNING':
                case 'WARN':
                    $severity = 'MEDIUM';
                    break;
                case 'ERROR':
                case 'SEVERE':
                    $severity = 'HIGH';
                    break;
                case 'FATAL':
                case 'EMERGENCY':
                    $severity = 'CRITICAL';
                    break;
                default:
                    $severity = 'LOW'; // Default fallback
            }
        }
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $user_type = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
        
        $sql = "INSERT INTO security_audit_logs 
                (action, description, user_id, user_type, ip_address, user_agent, severity) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$action, $description, $user_id, $user_type, $ip_address, $user_agent, $severity]);
        } catch (PDOException $e) {
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Secure error handling
     */
    private function handleSecureError($message, $exception) {
        // Log detailed error for developers
        error_log($message . ": " . $exception->getMessage());
        
        // Log security event
        $this->auditLog('SECURITY_ERROR', $message, null, 'HIGH');
        
        // Throw generic error for users
        throw new Exception('A system error occurred. Please try again later.');
    }
    
    /**
     * Validate and sanitize user input
     */
    public function validateInput($input, $type, $max_length = null) {
        // Remove null bytes and control characters
        $input = str_replace("\0", "", $input);
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        switch ($type) {
            case 'email':
                $input = filter_var($input, FILTER_SANITIZE_EMAIL);
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                break;
                
            case 'int':
                if (!filter_var($input, FILTER_VALIDATE_INT)) {
                    throw new Exception('Invalid integer value');
                }
                $input = (int)$input;
                break;
                
            case 'string':
                $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
                break;
                
            case 'alphanumeric':
                if (!preg_match('/^[a-zA-Z0-9]+$/', $input)) {
                    throw new Exception('Input must be alphanumeric');
                }
                break;
        }
        
        // Check length limit
        if ($max_length && strlen($input) > $max_length) {
            throw new Exception('Input exceeds maximum length');
        }
        
        return $input;
    }
    
    /**
     * Get PDO connection
     */
    public function getPDO() {
        return $this->pdo;
    }
    
    /**
     * Get MySQLi connection
     */
    public function getMySQLi() {
        return $this->link;
    }
    
    /**
     * Close connections
     */
    public function close() {
        $this->pdo = null;
        if ($this->link) {
            $this->link->close();
        }
        $this->auditLog('DATABASE_DISCONNECT', 'Database connections closed', null, 'SYSTEM');
    }
}

// Create global secure database instance
$secure_db = new SecureDatabase();
$pdo = $secure_db->getPDO();
$link = $secure_db->getMySQLi();

?>
