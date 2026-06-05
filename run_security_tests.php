<?php
/**
 * Comprehensive Security and Backup System Test Script
 * Tests all security and backup functionality
 */

require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/TwoFactorAuth.php';
require_once 'includes/BackupManager.php';

class SecurityTestSuite {
    private $db;
    private $testResults = [];
    private $totalTests = 0;
    private $passedTests = 0;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    public function runAllTests() {
        echo "<h1>Security and Backup System Test Suite</h1>";
        echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";
        
        $this->testDatabaseConnectivity();
        $this->testSecurityTables();
        $this->testSecurityLogger();
        $this->testTwoFactorAuth();
        $this->testBackupSystem();
        $this->testAccessControl();
        
        $this->displaySummary();
        echo "</div>";
    }
    
    private function testDatabaseConnectivity() {
        $this->logTest("Database Connectivity");
        
        try {
            if ($this->db) {
                // Test connection with a simple query
                $stmt = $this->db->query("SELECT 1");
                if ($stmt) {
                    $this->passTest("Database connection successful");
                } else {
                    $this->failTest("Database connection failed");
                }
            } else {
                $this->failTest("Database connection object is null");
            }
        } catch (Exception $e) {
            $this->failTest("Database error: " . $e->getMessage());
        }
    }
    
    private function testSecurityTables() {
        $this->logTest("Security Tables Existence");
        
        $requiredTables = [
            'user_sessions',
            'two_factor_auth',
            'security_logs',
            'backup_jobs',
            'alert_notifications',
            'security_settings'
        ];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                
                if ($stmt->rowCount() > 0) {
                    $this->passTest("Table '$table' exists");
                } else {
                    $this->failTest("Table '$table' missing");
                }
            } catch (Exception $e) {
                $this->failTest("Error checking table '$table': " . $e->getMessage());
            }
        }
    }
    
    private function testSecurityLogger() {
        $this->logTest("SecurityLogger Functionality");
        
        try {
            $logger = new SecurityLogger();
            
            // Test logging
            $testUserId = 1; // Test user ID
            $testEvent = 'TEST_EVENT';
            $testDescription = 'Security test event';
            $testData = ['test' => 'data', 'timestamp' => time()];
            
            $result = $logger->logSecurityEvent($testUserId, $testEvent, $testDescription, $testData, 'low');
            
            if ($result) {
                $this->passTest("SecurityLogger can log events");
                
                // Verify log was written
                $stmt = $this->db->prepare("SELECT * FROM security_logs WHERE event_type = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$testEvent]);
                
                if ($stmt->rowCount() > 0) {
                    $this->passTest("SecurityLogger writes to database correctly");
                } else {
                    $this->failTest("SecurityLogger failed to write to database");
                }
            } else {
                $this->failTest("SecurityLogger failed to log event");
            }
        } catch (Exception $e) {
            $this->failTest("SecurityLogger error: " . $e->getMessage());
        }
    }
    
    private function testTwoFactorAuth() {
        $this->logTest("Two-Factor Authentication System");
        
        try {
            $twoFA = new TwoFactorAuth();
            
            // Test secret generation
            $secret = $twoFA->generateSecret();
            if ($secret && strlen($secret) > 0) {
                $this->passTest("2FA secret generation works");
            } else {
                $this->failTest("2FA secret generation failed");
            }
            
            // Test QR code generation
            $qrCode = $twoFA->getQRCodeURL('test@example.com', $secret);
            if ($qrCode && strlen($qrCode) > 0) {
                $this->passTest("2FA QR code generation works");
            } else {
                $this->failTest("2FA QR code generation failed");
            }
            
            // Test token verification (with current time)
            $currentToken = $twoFA->generateTOTP($secret);
            if ($twoFA->verifyTOTP($secret, $currentToken)) {
                $this->passTest("2FA token verification works");
            } else {
                $this->failTest("2FA token verification failed");
            }
            
        } catch (Exception $e) {
            $this->failTest("2FA system error: " . $e->getMessage());
        }
    }
    
    private function testBackupSystem() {
        $this->logTest("Backup System");
        
        try {
            $backupManager = new BackupManager();
            
            // Test backup directory creation
            $backupDir = 'backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            if (is_dir($backupDir) && is_writable($backupDir)) {
                $this->passTest("Backup directory is accessible and writable");
            } else {
                $this->failTest("Backup directory is not accessible or writable");
            }
            
            // Test backup job creation
            $jobId = $backupManager->createBackupJob('manual', 'test_backup');
            if ($jobId) {
                $this->passTest("Backup job creation works");
                
                // Test backup job status
                $status = $backupManager->getBackupStatus($jobId);
                if ($status) {
                    $this->passTest("Backup job status retrieval works");
                } else {
                    $this->failTest("Backup job status retrieval failed");
                }
            } else {
                $this->failTest("Backup job creation failed");
            }
            
        } catch (Exception $e) {
            $this->failTest("Backup system error: " . $e->getMessage());
        }
    }
    

    
    private function testAccessControl() {
        $this->logTest("Access Control Verification");
        
        try {
            // Test role-based access control
            $adminPages = [
                'admin_dashboard.php',
                'user_management.php',
                'security_dashboard.php',
                'backup_management.php'
            ];
            
            foreach ($adminPages as $page) {
                if (file_exists($page)) {
                    $content = file_get_contents($page);
                    
                    // Check for role verification
                    if (strpos($content, 'role') !== false && 
                        (strpos($content, 'admin') !== false || strpos($content, 'officer') !== false)) {
                        $this->passTest("Access control implemented in $page");
                    } else {
                        $this->failTest("Access control missing or incomplete in $page");
                    }
                    
                    // Check for SecurityLogger integration
                    if (strpos($content, 'SecurityLogger') !== false) {
                        $this->passTest("SecurityLogger integrated in $page");
                    } else {
                        $this->failTest("SecurityLogger not integrated in $page");
                    }
                } else {
                    $this->failTest("Page $page not found");
                }
            }
            
        } catch (Exception $e) {
            $this->failTest("Access control test error: " . $e->getMessage());
        }
    }
    
    private function logTest($testName) {
        echo "<h3>Testing: $testName</h3>";
    }
    
    private function passTest($message) {
        $this->totalTests++;
        $this->passedTests++;
        echo "<div style='color: green;'>✓ PASS: $message</div>";
    }
    
    private function failTest($message) {
        $this->totalTests++;
        echo "<div style='color: red;'>✗ FAIL: $message</div>";
    }
    
    private function displaySummary() {
        echo "<h2>Test Summary</h2>";
        echo "<div style='background: #e8f4f8; padding: 15px; border-left: 4px solid #2196F3;'>";
        echo "<strong>Total Tests:</strong> {$this->totalTests}<br>";
        echo "<strong>Passed:</strong> <span style='color: green;'>{$this->passedTests}</span><br>";
        echo "<strong>Failed:</strong> <span style='color: red;'>" . ($this->totalTests - $this->passedTests) . "</span><br>";
        
        $successRate = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 2) : 0;
        echo "<strong>Success Rate:</strong> {$successRate}%<br>";
        
        if ($successRate >= 90) {
            echo "<div style='color: green; font-weight: bold; margin-top: 10px;'>🎉 Excellent! Security system is functioning well.</div>";
        } elseif ($successRate >= 70) {
            echo "<div style='color: orange; font-weight: bold; margin-top: 10px;'>⚠️ Good, but some issues need attention.</div>";
        } else {
            echo "<div style='color: red; font-weight: bold; margin-top: 10px;'>❌ Critical issues detected. Immediate attention required.</div>";
        }
        
        echo "</div>";
    }
}

// Run the tests
if (isset($_GET['run_tests']) || php_sapi_name() === 'cli') {
    $testSuite = new SecurityTestSuite();
    $testSuite->runAllTests();
} else {
    echo "<h1>Security Test Suite</h1>";
    echo "<p>Click the button below to run comprehensive security and backup tests.</p>";
    echo "<a href='?run_tests=1' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Security Tests</a>";
}
?>