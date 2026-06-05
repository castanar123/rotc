<?php
/**
 * Input Validation and Sanitization Class
 * Provides comprehensive input validation, sanitization, and security checks
 */

class InputValidator {
    private $secure_db;
    private $max_input_length = 10000;
    private $allowed_file_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    private $max_file_size = 5242880; // 5MB
    
    public function __construct($secure_db = null) {
        $this->secure_db = $secure_db;
    }
    
    /**
     * Validate and sanitize string input
     */
    public function validateString($input, $min_length = 0, $max_length = null, $allow_html = false) {
        if (!is_string($input)) {
            throw new InvalidArgumentException('Input must be a string');
        }
        
        // Remove null bytes and control characters
        $input = $this->removeNullBytes($input);
        
        // Check length
        if (strlen($input) < $min_length) {
            throw new InvalidArgumentException("Input must be at least {$min_length} characters long");
        }
        
        $max_len = $max_length ?? $this->max_input_length;
        if (strlen($input) > $max_len) {
            throw new InvalidArgumentException("Input exceeds maximum length of {$max_len} characters");
        }
        
        // Sanitize based on HTML allowance
        if ($allow_html) {
            $input = $this->sanitizeHtml($input);
        } else {
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        
        return trim($input);
    }
    
    /**
     * Validate email address
     */
    public function validateEmail($email) {
        $email = $this->removeNullBytes($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        
        // Additional security checks
        if (strlen($email) > 254) {
            throw new InvalidArgumentException('Email address too long');
        }
        
        // Check for suspicious patterns
        $suspicious_patterns = [
            '/[\x00-\x1F\x7F]/', // Control characters
            '/\.\./', // Double dots
            '/^\.|\.$/', // Starting or ending with dot
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $email)) {
                throw new InvalidArgumentException('Email contains invalid characters');
            }
        }
        
        return strtolower($email);
    }
    
    /**
     * Validate integer input
     */
    public function validateInteger($input, $min = null, $max = null) {
        if (!filter_var($input, FILTER_VALIDATE_INT)) {
            throw new InvalidArgumentException('Invalid integer value');
        }
        
        $int_value = (int)$input;
        
        if ($min !== null && $int_value < $min) {
            throw new InvalidArgumentException("Value must be at least {$min}");
        }
        
        if ($max !== null && $int_value > $max) {
            throw new InvalidArgumentException("Value must not exceed {$max}");
        }
        
        return $int_value;
    }
    
    /**
     * Validate float input
     */
    public function validateFloat($input, $min = null, $max = null) {
        if (!filter_var($input, FILTER_VALIDATE_FLOAT)) {
            throw new InvalidArgumentException('Invalid float value');
        }
        
        $float_value = (float)$input;
        
        if ($min !== null && $float_value < $min) {
            throw new InvalidArgumentException("Value must be at least {$min}");
        }
        
        if ($max !== null && $float_value > $max) {
            throw new InvalidArgumentException("Value must not exceed {$max}");
        }
        
        return $float_value;
    }
    
    /**
     * Validate phone number
     */
    public function validatePhone($phone) {
        $phone = $this->removeNullBytes($phone);
        
        // Remove all non-digit characters except + and -
        $cleaned = preg_replace('/[^0-9+\-\s()]/', '', $phone);
        
        // Check if it matches common phone patterns
        $patterns = [
            '/^\+?[1-9]\d{1,14}$/', // International format
            '/^\(?[0-9]{3}\)?[-. ]?[0-9]{3}[-. ]?[0-9]{4}$/', // US format
            '/^[0-9]{10,11}$/' // Simple 10-11 digit format
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                return $cleaned;
            }
        }
        
        throw new InvalidArgumentException('Invalid phone number format');
    }
    
    /**
     * Validate password strength
     */
    public function validatePassword($password, $min_length = 8) {
        if (!is_string($password)) {
            throw new InvalidArgumentException('Password must be a string');
        }
        
        if (strlen($password) < $min_length) {
            throw new InvalidArgumentException("Password must be at least {$min_length} characters long");
        }
        
        if (strlen($password) > 128) {
            throw new InvalidArgumentException('Password is too long');
        }
        
        // Check for null bytes
        if (strpos($password, "\0") !== false) {
            throw new InvalidArgumentException('Password contains invalid characters');
        }
        
        // Check password strength
        $strength_checks = [
            '/[a-z]/' => 'Password must contain at least one lowercase letter',
            '/[A-Z]/' => 'Password must contain at least one uppercase letter',
            '/[0-9]/' => 'Password must contain at least one number',
            '/[^a-zA-Z0-9]/' => 'Password must contain at least one special character'
        ];
        
        foreach ($strength_checks as $pattern => $message) {
            if (!preg_match($pattern, $password)) {
                throw new InvalidArgumentException($message);
            }
        }
        
        return $password;
    }
    
    /**
     * Validate alphanumeric input
     */
    public function validateAlphanumeric($input, $allow_spaces = false, $allow_underscore = false) {
        $input = $this->removeNullBytes($input);
        
        $pattern = '/^[a-zA-Z0-9';
        if ($allow_spaces) $pattern .= '\s';
        if ($allow_underscore) $pattern .= '_';
        $pattern .= ']+$/';
        
        if (!preg_match($pattern, $input)) {
            throw new InvalidArgumentException('Input must be alphanumeric');
        }
        
        return trim($input);
    }
    
    /**
     * Validate URL
     */
    public function validateUrl($url, $allowed_schemes = ['http', 'https']) {
        $url = $this->removeNullBytes($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid URL format');
        }
        
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'])) {
            throw new InvalidArgumentException('URL must include a valid scheme');
        }
        
        if (!in_array(strtolower($parsed['scheme']), $allowed_schemes)) {
            throw new InvalidArgumentException('URL scheme not allowed');
        }
        
        return $url;
    }
    
    /**
     * Validate date input
     */
    public function validateDate($date, $format = 'Y-m-d') {
        $date = $this->removeNullBytes($date);
        
        $d = DateTime::createFromFormat($format, $date);
        if (!$d || $d->format($format) !== $date) {
            throw new InvalidArgumentException('Invalid date format');
        }
        
        return $date;
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file) {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new InvalidArgumentException('Invalid file upload');
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new InvalidArgumentException('No file was uploaded');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new InvalidArgumentException('File size exceeds limit');
            default:
                throw new InvalidArgumentException('File upload error');
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            throw new InvalidArgumentException('File size exceeds maximum allowed size');
        }
        
        // Check file type
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $this->allowed_file_types)) {
            throw new InvalidArgumentException('File type not allowed');
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mime_types = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($mime_type, $allowed_mime_types)) {
            throw new InvalidArgumentException('File MIME type not allowed');
        }
        
        return $file;
    }
    
    /**
     * Validate JSON input
     */
    public function validateJson($json_string, $max_depth = 10) {
        $json_string = $this->removeNullBytes($json_string);
        
        if (strlen($json_string) > $this->max_input_length) {
            throw new InvalidArgumentException('JSON string too long');
        }
        
        $data = json_decode($json_string, true, $max_depth);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Validate array input
     */
    public function validateArray($input, $allowed_keys = null, $required_keys = null) {
        if (!is_array($input)) {
            throw new InvalidArgumentException('Input must be an array');
        }
        
        // Check required keys
        if ($required_keys) {
            foreach ($required_keys as $key) {
                if (!array_key_exists($key, $input)) {
                    throw new InvalidArgumentException("Required key '{$key}' is missing");
                }
            }
        }
        
        // Check allowed keys
        if ($allowed_keys) {
            foreach (array_keys($input) as $key) {
                if (!in_array($key, $allowed_keys)) {
                    throw new InvalidArgumentException("Key '{$key}' is not allowed");
                }
            }
        }
        
        return $input;
    }
    
    /**
     * Remove null bytes and control characters
     */
    private function removeNullBytes($input) {
        if (!is_string($input)) {
            return $input;
        }
        
        // Remove null bytes
        $input = str_replace("\0", "", $input);
        
        // Remove other control characters except tab, newline, and carriage return
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        return $input;
    }
    
    /**
     * Sanitize HTML input
     */
    private function sanitizeHtml($input) {
        // Allow only safe HTML tags
        $allowed_tags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6>';
        
        // Strip dangerous tags
        $input = strip_tags($input, $allowed_tags);
        
        // Remove dangerous attributes
        $input = preg_replace('/\s*on\w+\s*=\s*["\'][^"\'>]*["\']/', '', $input);
        $input = preg_replace('/\s*javascript\s*:/', '', $input);
        $input = preg_replace('/\s*vbscript\s*:/', '', $input);
        
        return $input;
    }
    
    /**
     * Check for SQL injection patterns
     */
    public function checkSqlInjection($input) {
        $sql_patterns = [
            '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION)\b/i',
            '/[\'";]/',
            '/--/',
            '/\/\*.*\*\//s',
            '/\bOR\s+\d+\s*=\s*\d+/i',
            '/\bAND\s+\d+\s*=\s*\d+/i'
        ];
        
        foreach ($sql_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                if ($this->secure_db) {
                    $this->secure_db->auditLog('SQL_INJECTION_ATTEMPT', 'Potential SQL injection detected: ' . substr($input, 0, 100), null, 'CRITICAL');
                }
                throw new SecurityException('Potential SQL injection detected');
            }
        }
        
        return true;
    }
    
    /**
     * Check for XSS patterns
     */
    public function checkXss($input) {
        $xss_patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/<object[^>]*>.*?<\/object>/is',
            '/<embed[^>]*>.*?<\/embed>/is'
        ];
        
        foreach ($xss_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                if ($this->secure_db) {
                    $this->secure_db->auditLog('XSS_ATTEMPT', 'Potential XSS attack detected: ' . substr($input, 0, 100), null, 'CRITICAL');
                }
                throw new SecurityException('Potential XSS attack detected');
            }
        }
        
        return true;
    }
    
    /**
     * Comprehensive input validation
     */
    public function validateInput($input, $type, $options = []) {
        // Security checks
        if (is_string($input)) {
            $this->checkSqlInjection($input);
            $this->checkXss($input);
        }
        
        switch ($type) {
            case 'string':
                return $this->validateString($input, $options['min_length'] ?? 0, $options['max_length'] ?? null, $options['allow_html'] ?? false);
            case 'email':
                return $this->validateEmail($input);
            case 'integer':
                return $this->validateInteger($input, $options['min'] ?? null, $options['max'] ?? null);
            case 'float':
                return $this->validateFloat($input, $options['min'] ?? null, $options['max'] ?? null);
            case 'phone':
                return $this->validatePhone($input);
            case 'password':
                return $this->validatePassword($input, $options['min_length'] ?? 8);
            case 'alphanumeric':
                return $this->validateAlphanumeric($input, $options['allow_spaces'] ?? false, $options['allow_underscore'] ?? false);
            case 'url':
                return $this->validateUrl($input, $options['allowed_schemes'] ?? ['http', 'https']);
            case 'date':
                return $this->validateDate($input, $options['format'] ?? 'Y-m-d');
            case 'json':
                return $this->validateJson($input, $options['max_depth'] ?? 10);
            case 'array':
                return $this->validateArray($input, $options['allowed_keys'] ?? null, $options['required_keys'] ?? null);
            default:
                throw new InvalidArgumentException('Unknown validation type');
        }
    }
}

// Custom security exception class
class SecurityException extends Exception {}

?>