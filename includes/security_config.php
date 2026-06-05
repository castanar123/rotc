<?php
/**
 * Security Configuration and Access Control System
 * Implements role-based access controls, data encryption, and security policies
 */

class SecurityConfig {
    private $secure_db;
    private $encryption_key;
    private $access_rules;
    private $security_policies;
    
    // Security levels
    const SECURITY_LOW = 1;
    const SECURITY_MEDIUM = 2;
    const SECURITY_HIGH = 3;
    const SECURITY_CRITICAL = 4;
    
    // User roles hierarchy
    const ROLE_HIERARCHY = [
        'admin' => 100,
        'commandant' => 80,
        '1cl' => 60,
        '2cl' => 40,
        'basic_cadet' => 20,
        'guest' => 0
    ];
    
    public function __construct($secure_db) {
        $this->secure_db = $secure_db;
        $this->initializeEncryption();
        $this->loadAccessRules();
        $this->loadSecurityPolicies();
    }
    
    /**
     * Initialize encryption system
     */
    private function initializeEncryption() {
        $key_file = __DIR__ . '/../.security_key';
        if (!file_exists($key_file)) {
            $this->encryption_key = base64_encode(random_bytes(32));
            file_put_contents($key_file, $this->encryption_key);
            chmod($key_file, 0600);
        } else {
            $this->encryption_key = file_get_contents($key_file);
        }
    }
    
    /**
     * Load access control rules
     */
    private function loadAccessRules() {
        $this->access_rules = [
            // Admin access
            'admin' => [
                'pages' => ['*'], // All pages
                'actions' => ['*'], // All actions
                'data_access' => ['*'], // All data
                'security_level' => self::SECURITY_CRITICAL
            ],
            
            // Commandant access
            'commandant' => [
                'pages' => [
                    'dashboard.php', 'user_management.php', 'reports/*',
                    'announcements/*', 'grades/*', 'attendance/*'
                ],
                'actions' => [
                    'view_users', 'edit_users', 'view_reports', 'manage_announcements',
                    'manage_grades', 'view_attendance'
                ],
                'data_access' => ['users', 'profiles', 'attendance', 'grades', 'reports'],
                'security_level' => self::SECURITY_HIGH
            ],
            
            // 1st Class Cadet access
            '1cl' => [
                'pages' => [
                    'dashboard.php', 'profile.php', 'attendance/*', 'announcements/view.php'
                ],
                'actions' => [
                    'view_profile', 'edit_own_profile', 'view_attendance', 'view_announcements'
                ],
                'data_access' => ['own_profile', 'attendance', 'announcements'],
                'security_level' => self::SECURITY_MEDIUM
            ],
            
            // 2nd Class Cadet access
            '2cl' => [
                'pages' => [
                    'dashboard.php', 'profile.php', 'attendance/view.php', 'announcements/view.php'
                ],
                'actions' => [
                    'view_profile', 'edit_own_profile', 'view_own_attendance', 'view_announcements'
                ],
                'data_access' => ['own_profile', 'own_attendance', 'announcements'],
                'security_level' => self::SECURITY_MEDIUM
            ],
            
            // Basic Cadet access
            'basic_cadet' => [
                'pages' => [
                    'dashboard.php', 'profile.php', 'announcements/view.php'
                ],
                'actions' => [
                    'view_profile', 'edit_own_profile', 'view_announcements'
                ],
                'data_access' => ['own_profile', 'announcements'],
                'security_level' => self::SECURITY_LOW
            ],
            
            // Guest access
            'guest' => [
                'pages' => ['login.php', 'register.php', 'index.php'],
                'actions' => ['login', 'register'],
                'data_access' => [],
                'security_level' => self::SECURITY_LOW
            ]
        ];
    }
    
    /**
     * Load security policies
     */
    private function loadSecurityPolicies() {
        $this->security_policies = [
            'password_policy' => [
                'min_length' => 8,
                'require_uppercase' => true,
                'require_lowercase' => true,
                'require_numbers' => true,
                'require_special_chars' => true,
                'max_age_days' => 90,
                'history_count' => 5
            ],
            
            'session_policy' => [
                'timeout_minutes' => 30,
                'max_concurrent_sessions' => 3,
                'require_https' => true,
                'regenerate_id_interval' => 300 // 5 minutes
            ],
            
            'access_policy' => [
                'max_login_attempts' => 5,
                'lockout_duration_minutes' => 15,
                'require_2fa_for_admin' => true,
                'ip_whitelist_enabled' => false,
                'allowed_ips' => []
            ],
            
            'data_policy' => [
                'encrypt_sensitive_fields' => true,
                'audit_all_access' => true,
                'data_retention_days' => 365,
                'backup_encryption' => true
            ]
        ];
    }
    
    /**
     * Check if user has access to a specific page
     */
    public function hasPageAccess($user_role, $page) {
        if (!isset($this->access_rules[$user_role])) {
            return false;
        }
        
        $allowed_pages = $this->access_rules[$user_role]['pages'];
        
        // Check for wildcard access
        if (in_array('*', $allowed_pages)) {
            return true;
        }
        
        // Check exact match
        if (in_array($page, $allowed_pages)) {
            return true;
        }
        
        // Check pattern match
        foreach ($allowed_pages as $pattern) {
            if (strpos($pattern, '*') !== false) {
                $regex = str_replace('*', '.*', preg_quote($pattern, '/'));
                if (preg_match('/^' . $regex . '$/', $page)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has permission for a specific action
     */
    public function hasActionPermission($user_role, $action) {
        if (!isset($this->access_rules[$user_role])) {
            return false;
        }
        
        $allowed_actions = $this->access_rules[$user_role]['actions'];
        
        return in_array('*', $allowed_actions) || in_array($action, $allowed_actions);
    }
    
    /**
     * Check if user has access to specific data
     */
    public function hasDataAccess($user_role, $data_type, $user_id = null, $target_user_id = null) {
        if (!isset($this->access_rules[$user_role])) {
            return false;
        }
        
        $allowed_data = $this->access_rules[$user_role]['data_access'];
        
        // Check for wildcard access
        if (in_array('*', $allowed_data)) {
            return true;
        }
        
        // Check for specific data type access
        if (in_array($data_type, $allowed_data)) {
            return true;
        }
        
        // Check for "own" data access
        if (strpos($data_type, 'own_') === 0 && $user_id === $target_user_id) {
            $base_type = substr($data_type, 4); // Remove 'own_' prefix
            return in_array('own_' . $base_type, $allowed_data);
        }
        
        return false;
    }
    
    /**
     * Get user's security level
     */
    public function getUserSecurityLevel($user_role) {
        return $this->access_rules[$user_role]['security_level'] ?? self::SECURITY_LOW;
    }
    
    /**
     * Check if user role has higher or equal privilege than required role
     */
    public function hasRolePrivilege($user_role, $required_role) {
        $user_level = self::ROLE_HIERARCHY[$user_role] ?? 0;
        $required_level = self::ROLE_HIERARCHY[$required_role] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encryptSensitiveData($data, $field_type = 'general') {
        if (!$this->security_policies['data_policy']['encrypt_sensitive_fields']) {
            return $data;
        }
        
        $key = base64_decode($this->encryption_key);
        $iv = random_bytes(16);
        
        // Add field type to data for integrity
        $data_with_type = json_encode([
            'type' => $field_type,
            'data' => $data,
            'timestamp' => time()
        ]);
        
        $encrypted = openssl_encrypt($data_with_type, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decryptSensitiveData($encrypted_data, $expected_type = null) {
        if (!$this->security_policies['data_policy']['encrypt_sensitive_fields']) {
            return $encrypted_data;
        }
        
        try {
            $key = base64_decode($this->encryption_key);
            $data = base64_decode($encrypted_data);
            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);
            
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }
            
            $data_obj = json_decode($decrypted, true);
            
            if (!$data_obj || !isset($data_obj['data'])) {
                throw new Exception('Invalid encrypted data format');
            }
            
            // Verify field type if specified
            if ($expected_type && $data_obj['type'] !== $expected_type) {
                throw new Exception('Data type mismatch');
            }
            
            return $data_obj['data'];
            
        } catch (Exception $e) {
            $this->secure_db->auditLog('DECRYPTION_ERROR', 'Failed to decrypt data: ' . $e->getMessage(), null, 'HIGH');
            return null;
        }
    }
    
    /**
     * Check password against security policy
     */
    public function validatePasswordPolicy($password) {
        $policy = $this->security_policies['password_policy'];
        $errors = [];
        
        if (strlen($password) < $policy['min_length']) {
            $errors[] = "Password must be at least {$policy['min_length']} characters long";
        }
        
        if ($policy['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if ($policy['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if ($policy['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if ($policy['require_special_chars'] && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Check if session is valid according to security policy
     */
    public function validateSession($user_id) {
        $policy = $this->security_policies['session_policy'];
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $inactive_time = time() - $_SESSION['last_activity'];
            if ($inactive_time > ($policy['timeout_minutes'] * 60)) {
                return false;
            }
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        // Check if session ID should be regenerated
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > $policy['regenerate_id_interval']) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        return true;
    }
    
    /**
     * Check login attempt limits
     */
    public function checkLoginAttempts($username, $ip_address) {
        $policy = $this->security_policies['access_policy'];
        
        // Check recent failed attempts
        $stmt = $this->secure_db->secureQuery(
            "SELECT COUNT(*) as attempts FROM security_audit_logs 
             WHERE action = 'LOGIN_FAILED' 
             AND (description LIKE ? OR ip_address = ?) 
             AND timestamp > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            ["%{$username}%", $ip_address, $policy['lockout_duration_minutes']]
        );
        
        $result = $stmt->fetch();
        $attempts = $result['attempts'] ?? 0;
        
        if ($attempts >= $policy['max_login_attempts']) {
            $this->secure_db->auditLog('LOGIN_BLOCKED', "Login blocked for {$username} from {$ip_address} due to too many failed attempts", null, 'HIGH');
            return false;
        }
        
        return true;
    }
    
    /**
     * Get security policies
     */
    public function getSecurityPolicies() {
        return $this->security_policies;
    }
    
    /**
     * Update security policy
     */
    public function updateSecurityPolicy($category, $key, $value, $admin_user_id) {
        if (isset($this->security_policies[$category][$key])) {
            $old_value = $this->security_policies[$category][$key];
            $this->security_policies[$category][$key] = $value;
            
            $this->secure_db->auditLog(
                'SECURITY_POLICY_UPDATED',
                "Security policy {$category}.{$key} changed from {$old_value} to {$value}",
                $admin_user_id,
                'CRITICAL'
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate secure token
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateSecureToken();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Secure page access check
     */
    public function securePageAccess($page, $required_role = null) {
        $user_role = $_SESSION['role'] ?? 'guest';
        $user_id = $_SESSION['user_id'] ?? null;
        
        // Check if user has access to this page
        if (!$this->hasPageAccess($user_role, $page)) {
            $this->secure_db->auditLog('UNAUTHORIZED_PAGE_ACCESS', "Unauthorized access attempt to {$page}", $user_id, 'HIGH');
            header('HTTP/1.1 403 Forbidden');
            header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php?error=access_denied');
            exit;
        }
        
        // Check role requirement if specified
        if ($required_role && !$this->hasRolePrivilege($user_role, $required_role)) {
            $this->secure_db->auditLog('INSUFFICIENT_PRIVILEGES', "Insufficient privileges for {$page}, required: {$required_role}", $user_id, 'HIGH');
            header('HTTP/1.1 403 Forbidden');
            header('Location: dashboard.php?error=insufficient_privileges');
            exit;
        }
        
        // Validate session
        if (!$this->validateSession($user_id)) {
            $this->secure_db->auditLog('SESSION_EXPIRED', 'Session expired during page access', $user_id, 'MEDIUM');
            session_destroy();
            header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php?error=session_expired');
            exit;
        }
        
        // Log successful access
        $this->secure_db->auditLog('PAGE_ACCESS', "Successful access to {$page}", $user_id, 'LOW');
        
        return true;
    }
}

// Initialize global security configuration
$security_config = new SecurityConfig($secure_db);

// Generate CSRF token for forms
if (session_status() === PHP_SESSION_ACTIVE) {
    $csrf_token = $security_config->generateCSRFToken();
}

?>