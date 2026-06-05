<?php
require_once 'db.php';
require_once 'SecurityLogger.php';

class TwoFactorAuth {
    private $pdo;
    private $logger;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->logger = new SecurityLogger();
    }
    
    /**
     * Generate a new secret key for TOTP
     */
    public function generateSecret($length = 32) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Enable 2FA for a user
     */
    public function enable2FA($userId, $secret) {
        try {
            $this->pdo->beginTransaction();
            
            // Update user table
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET two_factor_enabled = 1 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
            // Insert or update 2FA record
            $stmt = $this->pdo->prepare("
                INSERT INTO two_factor_auth (user_id, secret_key, is_verified, created_at) 
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                secret_key = VALUES(secret_key), 
                is_verified = 1
            ");
            $stmt->execute([$userId, $secret]);
            
            $this->pdo->commit();
            
            // Log the event
            $this->logger->logEvent(
                'two_factor_enabled',
                'Two-factor authentication enabled',
                $userId,
                'medium',
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            );
            
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('2FA Enable Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disable 2FA for a user
     */
    public function disable2FA($userId) {
        try {
            $this->pdo->beginTransaction();
            
            // Update user table
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET two_factor_enabled = 0 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
            // Deactivate 2FA record
            $stmt = $this->pdo->prepare("
                UPDATE two_factor_auth 
                SET is_verified = 0 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            $this->pdo->commit();
            
            // Log the event
            $this->logger->logEvent(
                'two_factor_disabled',
                'Two-factor authentication disabled',
                $userId,
                'medium',
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            );
            
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('2FA Disable Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's 2FA secret
     */
    public function getUserSecret($userId) {
        $stmt = $this->pdo->prepare("
            SELECT secret_key 
            FROM two_factor_auth 
            WHERE user_id = ? AND is_verified = 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Check if user has 2FA enabled
     */
    public function is2FAEnabled($userId) {
        $stmt = $this->pdo->prepare("
            SELECT two_factor_enabled 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    }
    
    /**
     * Generate TOTP code
     */
    public function generateTOTP($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        
        $secretkey = $this->base32Decode($secret);
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hm = hash_hmac('SHA1', $time, $secretkey, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify TOTP code
     */
    public function verifyTOTP($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->generateTOTP($secret, $currentTimeSlice + $i);
            if ($calculatedCode === $code) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verify user's 2FA code
     */
    public function verifyUser2FA($userId, $code) {
        $secret = $this->getUserSecret($userId);
        if (!$secret) {
            return false;
        }
        
        $isValid = $this->verifyTOTP($secret, $code);
        
        // Log the verification attempt
        $this->logger->logEvent(
            $isValid ? 'two_factor_success' : 'two_factor_failed',
            $isValid ? 'Two-factor authentication successful' : 'Two-factor authentication failed',
            $userId,
            $isValid ? 'low' : 'medium',
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );
        
        return $isValid;
    }
    
    /**
     * Generate QR code URL for Google Authenticator
     */
    public function getQRCodeURL($user, $secret, $issuer = 'ROTC System') {
        $accountName = urlencode($issuer . ':' . $user);
        $issuerName = urlencode($issuer);
        
        return "otpauth://totp/{$accountName}?secret={$secret}&issuer={$issuerName}";
    }
    
    /**
     * Generate backup codes
     */
    public function generateBackupCodes($userId, $count = 10) {
        $codes = [];
        
        try {
            // Delete existing backup codes
            $stmt = $this->pdo->prepare("
                DELETE FROM two_factor_backup_codes 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            // Generate new codes
            for ($i = 0; $i < $count; $i++) {
                $code = $this->generateRandomCode(8);
                $codes[] = $code;
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO two_factor_backup_codes (user_id, code, created_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$userId, password_hash($code, PASSWORD_DEFAULT)]);
            }
            
            return $codes;
        } catch (Exception $e) {
            error_log('Backup codes generation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify backup code
     */
    public function verifyBackupCode($userId, $code) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT backup_code_id, code 
                FROM two_factor_backup_codes 
                WHERE user_id = ? AND used_at IS NULL
            ");
            $stmt->execute([$userId]);
            $backupCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($backupCodes as $backupCode) {
                if (password_verify($code, $backupCode['code'])) {
                    // Mark code as used
                    $stmt = $this->pdo->prepare("
                        UPDATE two_factor_backup_codes 
                        SET used_at = NOW() 
                        WHERE backup_code_id = ?
                    ");
                    $stmt->execute([$backupCode['backup_code_id']]);
                    
                    // Log the event
                    $this->logger->logEvent(
                        'backup_code_used',
                        'Backup code used for authentication',
                        $userId,
                        'medium',
                        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    );
                    
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log('Backup code verification error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Base32 decode function
     */
    private function base32Decode($secret) {
        if (empty($secret)) return '';
        
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsArray = str_split($base32chars);
        $base32charsFlipped = array_flip($base32charsArray);
        
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = array(6, 4, 3, 1, 0);
        
        if (!in_array($paddingCharCount, $allowedValues)) return false;
        
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount == $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])) return false;
        }
        
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], $base32charsArray)) return false;
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }
        
        return $binaryString;
    }
    
    /**
     * Generate random code
     */
    private function generateRandomCode($length = 8) {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }
    
    /**
     * Get remaining backup codes count
     */
    public function getRemainingBackupCodes($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM two_factor_backup_codes 
            WHERE user_id = ? AND used_at IS NULL
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
}
?>