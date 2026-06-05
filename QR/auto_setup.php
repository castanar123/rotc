<?php
/**
 * Automatic HTTPS and Network Setup System
 * Detects current environment and configures HTTPS automatically
 */

header('Content-Type: application/json');

class AutoSetup {
    private $xamppPath;
    private $projectPath;
    private $isWindows;
    
    public function __construct() {
        $this->isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $this->projectPath = dirname(__FILE__);
        $this->xamppPath = $this->detectXamppPath();
    }
    
    /**
     * Main setup function
     */
    public function runSetup() {
        $result = [
            'success' => false,
            'steps' => [],
            'errors' => [],
            'network_info' => [],
            'https_status' => false,
            'mobile_access_url' => null
        ];
        
        try {
            // Step 1: Check current HTTPS status
            $httpsStatus = $this->checkHttpsStatus();
            $result['https_status'] = $httpsStatus;
            $result['steps'][] = 'HTTPS Status: ' . ($httpsStatus ? 'Enabled' : 'Disabled');
            
            // Step 2: Get network information
            $networkInfo = $this->getNetworkInfo();
            $result['network_info'] = $networkInfo;
            $result['steps'][] = 'Network detection completed';
            
            // Step 3: Setup HTTPS if not already configured
            if (!$httpsStatus) {
                $httpsSetup = $this->setupHttps();
                if ($httpsSetup['success']) {
                    $result['steps'][] = 'HTTPS configured successfully';
                    $result['https_status'] = true;
                } else {
                    $result['errors'] = array_merge($result['errors'], $httpsSetup['errors']);
                }
            }
            
            // Step 4: Generate mobile access URLs
            $mobileUrls = $this->generateMobileAccessUrls($networkInfo);
            $result['mobile_access_url'] = $mobileUrls;
            $result['steps'][] = 'Mobile access URLs generated';
            
            // Step 5: Update .htaccess for HTTPS redirection
            $htaccessUpdate = $this->updateHtaccess();
            if ($htaccessUpdate) {
                $result['steps'][] = '.htaccess updated for HTTPS redirection';
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['errors'][] = 'Setup failed: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check if HTTPS is currently working
     */
    private function checkHttpsStatus() {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }
    
    /**
     * Get network information for mobile access
     */
    private function getNetworkInfo() {
        $info = [
            'local_ip' => $this->getLocalIP(),
            'hostname' => gethostname(),
            'port' => $_SERVER['SERVER_PORT'] ?? '80',
            'https_port' => '443'
        ];
        
        return $info;
    }
    
    /**
     * Get local IP address
     */
    private function getLocalIP() {
        $server_ip = $_SERVER['SERVER_ADDR'] ?? null;
        
        if (empty($server_ip) || $server_ip == '127.0.0.1' || $server_ip == '::1') {
            if ($this->isWindows) {
                $output = [];
                exec('ipconfig', $output);
                foreach ($output as $line) {
                    if (strpos($line, 'IPv4') !== false) {
                        $ip = trim(substr($line, strpos($line, ':') + 1));
                        if (filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, ['127.0.0.1', '0.0.0.0'])) {
                            return $ip;
                        }
                    }
                }
            } else {
                $output = [];
                exec('hostname -I', $output);
                if (!empty($output[0])) {
                    $ips = explode(' ', trim($output[0]));
                    foreach ($ips as $ip) {
                        if (filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, ['127.0.0.1', '0.0.0.0'])) {
                            return $ip;
                        }
                    }
                }
            }
        }
        
        return $server_ip ?: '127.0.0.1';
    }
    
    /**
     * Setup HTTPS automatically
     */
    private function setupHttps() {
        $result = ['success' => false, 'errors' => []];
        
        try {
            // Check if certificates already exist
            $certPath = $this->xamppPath . '/apache/conf/ssl.crt/server.crt';
            $keyPath = $this->xamppPath . '/apache/conf/ssl.key/server.key';
            
            if (!file_exists($certPath) || !file_exists($keyPath)) {
                // Generate self-signed certificate
                $certGenerated = $this->generateSelfSignedCertificate();
                if (!$certGenerated) {
                    $result['errors'][] = 'Failed to generate SSL certificate';
                    return $result;
                }
            }
            
            // Update Apache configuration
            $apacheConfigured = $this->configureApacheSSL();
            if (!$apacheConfigured) {
                $result['errors'][] = 'Failed to configure Apache SSL';
                return $result;
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['errors'][] = 'HTTPS setup error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Generate self-signed SSL certificate
     */
    private function generateSelfSignedCertificate() {
        if (!$this->xamppPath) {
            return false;
        }
        
        $opensslPath = $this->xamppPath . '/apache/bin/openssl.exe';
        if (!file_exists($opensslPath)) {
            $opensslPath = 'openssl'; // Try system openssl
        }
        
        $certDir = $this->xamppPath . '/apache/conf/ssl.crt';
        $keyDir = $this->xamppPath . '/apache/conf/ssl.key';
        
        // Create directories if they don't exist
        if (!is_dir($certDir)) mkdir($certDir, 0755, true);
        if (!is_dir($keyDir)) mkdir($keyDir, 0755, true);
        
        $certPath = $certDir . '/server.crt';
        $keyPath = $keyDir . '/server.key';
        
        // Generate certificate command
        $cmd = sprintf(
            '%s req -new -x509 -days 365 -nodes -out "%s" -keyout "%s" -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"',
            $opensslPath,
            $certPath,
            $keyPath
        );
        
        exec($cmd, $output, $returnCode);
        
        return $returnCode === 0 && file_exists($certPath) && file_exists($keyPath);
    }
    
    /**
     * Configure Apache for SSL
     */
    private function configureApacheSSL() {
        if (!$this->xamppPath) {
            return false;
        }
        
        $httpdConf = $this->xamppPath . '/apache/conf/httpd.conf';
        $sslConf = $this->xamppPath . '/apache/conf/extra/httpd-ssl.conf';
        
        // Enable SSL module in httpd.conf
        if (file_exists($httpdConf)) {
            $content = file_get_contents($httpdConf);
            $content = str_replace('#LoadModule ssl_module modules/mod_ssl.so', 'LoadModule ssl_module modules/mod_ssl.so', $content);
            $content = str_replace('#Include conf/extra/httpd-ssl.conf', 'Include conf/extra/httpd-ssl.conf', $content);
            file_put_contents($httpdConf, $content);
        }
        
        return true;
    }
    
    /**
     * Generate mobile access URLs
     */
    private function generateMobileAccessUrls($networkInfo) {
        $projectName = basename($this->projectPath);
        $encodedProjectName = urlencode($projectName);
        
        $urls = [
            'http' => "http://{$networkInfo['local_ip']}/{$encodedProjectName}/home.html",
            'https' => "https://{$networkInfo['local_ip']}/{$encodedProjectName}/home.html",
            'scanner' => "https://{$networkInfo['local_ip']}/{$encodedProjectName}/scanner.html"
        ];
        
        return $urls;
    }
    
    /**
     * Update .htaccess for HTTPS redirection
     */
    private function updateHtaccess() {
        $htaccessPath = $this->projectPath . '/.htaccess';
        
        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            
            // Enable HTTPS redirection if not already enabled
            if (strpos($content, 'RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI}') === false) {
                // Remove any commented HTTPS redirect first
                $content = preg_replace('/# RewriteCond %{HTTPS} off\s*\n# RewriteRule \^\(\*\)\$ https.*\n/', '', $content);
                
                $httpsRedirect = "\n# Force HTTPS\nRewriteCond %{HTTPS} off\nRewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]\n";
                
                // Check if RewriteEngine On exists, if not add it
                if (strpos($content, 'RewriteEngine On') !== false) {
                    $content = str_replace('RewriteEngine On', 'RewriteEngine On' . $httpsRedirect, $content);
                } else {
                    $content = "RewriteEngine On" . $httpsRedirect . "\n" . $content;
                }
                
                return file_put_contents($htaccessPath, $content) !== false;
            } else {
                // Uncomment existing HTTPS redirection if commented
                $content = str_replace('# <IfModule mod_rewrite.c>', '<IfModule mod_rewrite.c>', $content);
                $content = str_replace('#     RewriteEngine On', '    RewriteEngine On', $content);
                $content = str_replace('#     RewriteCond %{HTTPS} off', '    RewriteCond %{HTTPS} off', $content);
                $content = str_replace('#     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]', '    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]', $content);
                $content = str_replace('# </IfModule>', '</IfModule>', $content);
                
                return file_put_contents($htaccessPath, $content) !== false;
            }
        }
        
        return false;
    }
    
    /**
     * Detect XAMPP installation path
     */
    private function detectXamppPath() {
        $possiblePaths = [
            'C:/xampp',
            'C:/XAMPP',
            '/opt/lampp',
            '/Applications/XAMPP'
        ];
        
        foreach ($possiblePaths as $path) {
            if (is_dir($path . '/apache')) {
                return $path;
            }
        }
        
        return null;
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setup = new AutoSetup();
    $result = $setup->runSetup();
    echo json_encode($result);
} else {
    echo json_encode(['error' => 'Only POST requests allowed']);
}
?>