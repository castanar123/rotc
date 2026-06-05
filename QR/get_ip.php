<?php
// Get the server's IP address
$server_ip = $_SERVER['SERVER_ADDR'];

// If SERVER_ADDR is not available or is localhost/127.0.0.1
if (empty($server_ip) || $server_ip == '127.0.0.1' || $server_ip == '::1') {
    // Try to get the IP address using other methods
    $possible_ips = array();
    
    // Method 1: Using hostname
    if (function_exists('gethostname') && function_exists('gethostbyname')) {
        $hostname = gethostname();
        $ip = gethostbyname($hostname);
        if ($ip != $hostname && filter_var($ip, FILTER_VALIDATE_IP)) {
            $possible_ips[] = $ip;
        }
    }
    
    // Method 2: Using network interfaces (Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = array();
        exec('ipconfig', $output);
        foreach ($output as $line) {
            if (strpos($line, 'IPv4') !== false) {
                $ip = trim(substr($line, strpos($line, ':') + 1));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $possible_ips[] = $ip;
                }
            }
        }
    }
    
    // Method 3: Using network interfaces (Linux/Unix)
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $output = array();
        exec('ifconfig || ip addr', $output);
        foreach ($output as $line) {
            if (preg_match('/inet (addr:)?(([0-9]*\.){3}[0-9]*)/', $line, $matches)) {
                $ip = $matches[2];
                if ($ip != '127.0.0.1' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $possible_ips[] = $ip;
                }
            }
        }
    }
    
    // Filter out localhost and private network IPs that are not useful
    $filtered_ips = array();
    foreach ($possible_ips as $ip) {
        // Keep only IPs that are likely to be on the local network (192.168.x.x, 10.x.x.x, etc.)
        if (strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '172.16.') === 0) {
            $filtered_ips[] = $ip;
        }
    }
    
    // Use the first filtered IP, or fallback to a hardcoded one from ipconfig output
    $server_ip = !empty($filtered_ips) ? $filtered_ips[0] : '192.168.1.7';
}

// Output the IP address (no HTML, just the raw IP)
echo $server_ip;
?>