<?php
// Aggressive cache control headers for mobile browsers
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('ETag: "' . md5(time()) . '"');

// Generate cache-busting version parameter - Force complete cache refresh
$cache_version = time() . '_cleared_' . rand(1000, 9999); // Use current timestamp + random for aggressive cache busting

require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';

// Temporary bypass for testing - remove in production
if (isset($_GET['test_mode']) && $_GET['test_mode'] === 'true') {
    // Log test mode access for security monitoring
    $securityLogger = new SecurityLogger();
    $securityLogger->logSecurityEvent(null, 'TEST_MODE_ACCESS', 'Rifle scanner accessed in test mode', [], 'medium');
    // Skip authentication for testing
    $first_name = 'Test';
    $last_name = 'User';
    $user_name = $first_name . ' ' . $last_name;
    $user_role = 'Officer';
} else {
    // Access control: Allow officers, instructors, and admins for rifle management
    check_login();
    if (!in_array($_SESSION['role'], ['officer', 'instructor', 'admin', 'commandant'])) {
        $securityLogger = new SecurityLogger();
        $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'UNAUTHORIZED_ACCESS', 'User attempted to access rifle scanner without proper role', [], 'high');
        redirect_to_dashboard();
    }
    
    // Log access to rifle scanner
    $securityLogger = new SecurityLogger();
    $securityLogger->logSecurityEvent($_SESSION['user_id'], 'DATA_ACCESS', 'User accessed rifle scanner', [], 'low');
    
    // Get user information
    $first_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'Unknown';
    $last_name = isset($_SESSION['last_name']) ? $_SESSION['last_name'] : 'User';
    $user_name = $first_name . ' ' . $last_name;
    $user_role = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'User';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - ROTC Management System</title>
    
    <!-- Aggressive cache-busting meta tags for mobile browsers -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate, max-age=0, private">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="Thu, 01 Jan 1970 00:00:00 GMT">
    <meta http-equiv="Last-Modified" content="<?php echo gmdate('D, d M Y H:i:s'); ?> GMT">
    <meta name="cache-control" content="no-cache">
    <meta name="expires" content="0">
    <meta name="pragma" content="no-cache">
    
    <!-- CSS Files with cache-busting -->
    <link rel="stylesheet" href="css/tactical-theme.css?v=<?php echo $cache_version; ?>">
    <link rel="stylesheet" href="css/dashboard-redesigned.css?v=<?php echo $cache_version; ?>">
    <link rel="stylesheet" href="css/mobile-responsive.css?v=<?php echo $cache_version; ?>">
    <link rel="stylesheet" href="css/rifle-mobile.css?v=<?php echo $cache_version; ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- HTML5-QRCode Scanner Library with cache-busting -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js?v=<?php echo $cache_version; ?>" type="text/javascript"></script>
    
    <!-- Crypto JS for decryption with cache-busting -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js?v=<?php echo $cache_version; ?>"></script>
    
    <style>
        /* Rifle Scanner Specific Styles - Tactical Theme */
        .scanner-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }
        
        .page-header {
            background: var(--bg-dark);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        
        .page-header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            color: var(--accent-green);
            margin-bottom: var(--spacing-sm);
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
            margin: 0;
        }
        
        .scanner-section {
            background: var(--bg-dark);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
        }
        
        .scanner-section h3 {
            font-family: 'Orbitron', sans-serif;
            color: var(--accent-green);
            margin-bottom: var(--spacing-lg);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .operation-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .operation-btn {
            padding: var(--spacing-lg) var(--spacing-xl);
            border: 2px solid var(--border-color);
            background: var(--bg-medium);
            color: var(--text-light);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-normal);
            font-weight: 600;
            font-family: 'Rajdhani', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
            position: relative;
            overflow: hidden;
        }
        
        .operation-btn.active {
            background: var(--accent-green);
            border-color: var(--accent-green);
            color: var(--text-dark);
            box-shadow: 0 4px 12px rgba(var(--accent-green-rgb), 0.3);
            transform: translateY(-2px);
        }
        
        .operation-btn:hover {
            border-color: var(--accent-green);
            color: var(--accent-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--accent-green-rgb), 0.2);
        }
        
        .scanner-camera-section {
            text-align: center;
            padding: var(--spacing-xl);
        }
        
        #camera-status {
            margin-bottom: var(--spacing-lg);
            color: var(--text-muted);
            font-size: 1.1rem;
            padding: var(--spacing-md);
            background: var(--bg-medium);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }
        
        #reader {
            width: 100%;
            max-width: 500px;
            margin: 20px auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        #reader video {
            width: 100% !important;
            height: auto !important;
        }
        
        /* QR Card Styling with tactical theme */
        .qr-card {
            background: linear-gradient(135deg, rgba(20, 25, 30, 0.95) 0%, rgba(15, 20, 25, 0.98) 100%);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
          
                
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(40, 167, 69, 0.2);
border-radius: 16px;
        }
        
        .qr-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            padding: var(--spacing-lg);
            background: linear-gradient(135deg, rgba(20, 25, 30, 0.95) 0%, rgba(15, 20, 25, 0.98) 100%);
                       border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(40, 167, 69, 0.2);
border-radius: 16px;
        }
        
        .back-btn {
            background: var(--bg-medium);
            color: var(--text-light);
            border: 1px solid var(--border-color);
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-family: 'Rajdhani', sans-serif;
        }
        
        .back-btn:hover {
            background: var(--accent-green);
            color: var(--text-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--accent-green-rgb), 0.3);
        }
        
        .qr-btn {
            background: var(--accent-green);
            color: var(--text-dark);
            border: none;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-family: 'Rajdhani', sans-serif;
            text-decoration: none;
        }
        
        .qr-btn:hover {
            background: var(--accent-green-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--accent-green-rgb), 0.3);
        }
        
        .form-group {
            margin-bottom: var(--spacing-lg);
        }
        
        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--text-light);
            font-weight: 600;
            font-family: 'Rajdhani', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-medium);
            color: var(--text-light);
            font-family: 'Rajdhani', sans-serif;
            transition: all var(--transition-normal);
        }
        
        /* Dropdown styling fixes */
        .form-group select {
            background-color: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-primary) !important;
        }
        
        .form-group select option {
            background-color: var(--bg-tertiary) !important;
            color: var(--text-primary) !important;
        }
        
        .form-group select option:hover {
            background-color: var(--military-green) !important;
            color: var(--text-primary) !important;
        }
        
        #school-year, #semester {
            background-color: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-accent) !important;
        }
        
        #school-year:focus, #semester:focus {
            background-color: var(--bg-tertiary) !important;
            border-color: var(--military-green) !important;
            box-shadow: var(--shadow-accent) !important;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 2px rgba(var(--accent-green-rgb), 0.2);
        }
        
        /* Scan result styling - positioned below camera */
        #scan-result {
            max-width: 95%;
            width: 100%;
            margin: var(--spacing-lg) auto 0;
            padding: var(--spacing-xl);
            border-radius: var(--radius-lg);
            display: none;
            text-align: left;
            font-family: 'Rajdhani', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            border: 2px solid var(--border-color);
            background: var(--bg-dark);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        #scan-result::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-green);
        }
        
        #scan-result.result-success {
            border-color: #28a745;
            background: #d4edda;
            color: #155724;
        }
        
        #scan-result.result-success::before {
            background: #28a745;
        }
        
        #scan-result.result-error {
            border-color: #dc3545;
            background: #f8d7da;
            color: #721c24;
        }
        
        #scan-result.result-error::before {
            background: #dc3545;
        }
        
        #scan-result.result-warning {
            border-color: #ffc107;
            background: #fff3cd;
            color: #856404;
        }
        
        #scan-result.result-warning::before {
            background: #ffc107;
        }
        
        .rifle-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .rifle-detail {
            background: var(--bg-medium);
            padding: var(--spacing-md);
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--accent-green);
        }
        
        .rifle-detail-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
            font-family: 'Orbitron', sans-serif;
        }
        
        .rifle-detail-value {
            font-size: 1.1rem;
            color: var(--text-light);
            font-weight: 700;
            font-family: 'Rajdhani', sans-serif;
        }
        
        /* Current assignments styling */
        .current-assignments {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-medium);
        }
        
        .assignment-item {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color var(--transition-normal);
        }
        
        .assignment-item:last-child {
            border-bottom: none;
        }
        
        .assignment-item:hover {
            background: var(--bg-dark);
        }
        
        .assignment-rifle {
            font-weight: 700;
            color: var(--accent-green);
            font-family: 'Orbitron', sans-serif;
            font-size: 1.1rem;
        }
        
        .assignment-cadet {
            color: var(--text-light);
            font-family: 'Rajdhani', sans-serif;
            font-weight: 600;
        }
        
        .assignment-details {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        .assignment-time {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-family: 'Orbitron', sans-serif;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: 4px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-assigned {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }
        
        .status-available {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }
        
        .status-maintenance {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }
        
        .status-present {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }
        
        .attendance-info {
            background: var(--bg-card);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            margin: var(--spacing-md) 0;
            box-shadow: var(--shadow-primary);
        }
        
        .attendance-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-sm) 0;
            border-bottom: 1px solid var(--border-primary);
        }
        
        .attendance-detail:last-child {
            border-bottom: none;
        }
        
        .attendance-detail strong {
            color: var(--text-accent);
            font-family: 'Orbitron', sans-serif;
            font-weight: 600;
        }
        
        .attendance-detail span {
            color: var(--text-light);
            font-family: 'Rajdhani', sans-serif;
        }
        
        /* Responsive design for mobile devices */
        @media (max-width: 768px) {
            #reader {
                max-width: 95%;
                min-height: 400px; /* Increased for mobile */
            }
            
            #scan-result {
                margin-top: var(--spacing-md);
                padding: var(--spacing-md);
                font-size: 1rem;
            }
        }
        
        .scanner-controls {
            display: flex;
            justify-content: center;
            gap: var(--spacing-md);
            margin: var(--spacing-lg) 0;
        }
        
        .scanner-btn {
            padding: var(--spacing-md) var(--spacing-xl);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            font-family: 'Rajdhani', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all var(--transition-normal);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .start-btn {
            background: #28a745;
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .stop-btn {
            background: #dc3545;
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .scanner-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }
        
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #ffffff;
            border: 1px solid #e9ecef;
            color: #333;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-md);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            transition: all var(--transition-normal);
            z-index: 1000;
        }
        
        .back-btn:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        /* Stats grid styling */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-md);
            margin: var(--spacing-lg) 0;
        }
        
        /* Stats Container and Cards to match QR/scanner.html */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-lg);
            margin-top: var(--spacing-lg);
        }
        
        .stats-card {
            flex: 1;
            min-width: 200px;
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-tertiary) 100%);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-primary);
            border: 1px solid var(--border-primary);
        }
        
        .stats-card h3 {
            margin-top: 0;
            font-size: 1.2rem;
            color: var(--text-accent);
            border-bottom: 1px solid var(--border-primary);
            padding-bottom: var(--spacing-sm);
            font-family: 'Orbitron', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            text-align: center;
            transition: all var(--transition-normal);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-primary);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            font-family: 'Rajdhani', sans-serif;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: var(--spacing-xs);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Recent activities styling */
        .recent-activities {
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-tertiary) 100%);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            max-height: 350px;
            overflow-y: auto;
            margin: var(--spacing-lg) 0;
            box-shadow: var(--shadow-primary);
        }
        
        .recent-activities::-webkit-scrollbar {
            width: 8px;
        }
        
        .recent-activities::-webkit-scrollbar-track {
            background: var(--bg-secondary);
            border-radius: 4px;
        }
        
        .recent-activities::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 4px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm);
            margin-bottom: var(--spacing-xs);
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .activity-icon.type-assign {
            background: rgba(76, 175, 80, 0.1);
            border: 2px solid #4caf50;
        }
        
        .activity-icon.type-return {
            background: rgba(33, 150, 243, 0.1);
            border: 2px solid #2196f3;
        }
        
        .activity-info {
            flex: 1;
            min-width: 0;
        }
        
        .activity-main {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            margin-bottom: 4px;
        }
        
        .rifle-info {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .activity-details {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .cadet-info {
            font-weight: 500;
        }
        
        .activity-time {
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-family: 'Orbitron', sans-serif;
        }
        
        .activity-type {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .activity-type.type-assign {
            background: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }
        
        .activity-type.type-return {
            background: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .activity-status {
            flex-shrink: 0;
        }
        
        .activity-status .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        /* Operation selector styling */
        .operation-selector {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
            margin: var(--spacing-lg) 0;
        }
        
        .operation-btn {
            padding: var(--spacing-md) var(--spacing-xl);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
            background: var(--bg-card);
            color: var(--text-primary);
            cursor: pointer;
            font-weight: 600;
            font-family: 'Rajdhani', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all var(--transition-normal);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 1rem;
        }
        
        .operation-btn:hover {
            background: var(--bg-tertiary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-primary);
        }
        
        .operation-btn.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(0, 255, 127, 0.3);
        }
        
        .operation-btn.active:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 255, 127, 0.4);
        }
        
        /* Mobile responsive camera styling */
        @media (max-width: 768px) {
            #reader {
                width: 100%;
                height: auto;
                margin: 15px auto;
            }
            #reader video {
                width: 100% !important;
                height: auto !important;
            }
        }
    </style>
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            $NAV_BASE = '';
            include __DIR__ . '/includes/admin_nav.php';
        ?>
        
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <div class="main-content">
            <div class="content-body">
                <div class="qr-attendance-container">
                    <div class="qr-header">
                        <button class="back-btn" id="backBtn">
                            <i class="fas fa-arrow-left"></i>
                            Back to Rifle Management
                        </button>
                        <h1 style="font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--text-accent); text-transform: uppercase; letter-spacing: 2px; margin: 0; font-size: 2rem;"><i class="fas fa-qrcode"></i> QR Scanner</h1>
                        <div style="font-size: 0.9rem; color: var(--text-secondary);">Logged in as: <strong><?php echo htmlspecialchars($user_name); ?></strong> (<?php echo htmlspecialchars($user_role); ?>)</div>
                    </div>
        
                    <div class="qr-card fade-in">
                        <div id="session-info" class="session-info"></div>
                        
                        <h2 style="font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--text-accent); text-transform: uppercase; letter-spacing: 2px; margin-bottom: var(--spacing-lg); font-size: 1.5rem; text-align: center;"><i class="fas fa-qrcode"></i> QR Scanner</h2>
                        
                        <div class="form-group">
                            <label for="operation-mode">Operation Mode:</label>
                            <div class="operation-selector">
                                <button class="operation-btn active" id="attendance-btn" data-operation="attendance" onclick="selectOperation('attendance')">
                                    <i class="fas fa-user-check"></i>
                                    Attendance
                                </button>
                                <button class="operation-btn" id="assign-btn" data-operation="assign" onclick="selectOperation('assign')">
                                    <i class="fas fa-plus-circle"></i>
                                    Assign Rifle
                                </button>
                                <button class="operation-btn" id="return-btn" data-operation="return" onclick="selectOperation('return')">
                                    <i class="fas fa-minus-circle"></i>
                                    Return Rifle
                                </button>
                            </div>
                            <div id="scanner-mode" style="text-align: center; margin-top: var(--spacing-md); font-size: 1.1rem; color: var(--text-accent); font-weight: 600;">
                                Select an operation mode to begin
                            </div>
                        </div>
                        
                        <!-- Secret Key Input (for rifle operations) -->
                        <div class="form-group" id="secret-key-group" style="display: none;">
                            <label for="secret-key">Secret Key:</label>
                            <input type="password" id="secret-key" placeholder="Enter secret key for rifle operations" required>
                            <small class="form-text">Required for rifle assignment and return operations</small>
                        </div>

                        <!-- Attendance Form Fields -->
                        <div id="attendance-form" class="attendance-form">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="school-year"><strong>School Year</strong></label>
                                    <select id="school-year" class="form-control">
                                        <option value="" selected disabled>Select S.Y.</option>
                                        <?php
                                        $year = (int)date('Y');
                                        $month = (int)date('n'); // 1-12
                                        // Derive current academic year start (e.g., Aug 2025 -> 2025-2026, Feb 2025 -> 2024-2025)
                                        $startYear = ($month >= 8) ? $year : ($year - 1);
                                        for ($offset = 0; $offset < 4; $offset++) {
                                            $y = $startYear + $offset;
                                            $sy = $y . '-' . ($y + 1);
                                            echo "<option value='{$sy}'>{$sy}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="semester"><strong>Semester</strong></label>
                                    <select id="semester" class="form-control">
                                        <option value="" selected disabled>Select Sem</option>
                                        <option value="1st">1st Semester</option>
                                        <option value="2nd">2nd Semester</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="event-name"><strong>Training Day</strong></label>
                                <select id="event-name" class="form-control">
                                    <option value="" selected disabled>Select TD</option>
                                    <option value="1TD">1TD</option>
                                    <option value="2TD">2TD</option>
                                    <option value="3TD">3TD</option>
                                    <option value="4TD">4TD</option>
                                    <option value="5TD">5TD</option>
                                    <option value="6TD">6TD</option>
                                    <option value="7TD">7TD</option>
                                    <option value="8TD">8TD</option>
                                    <option value="9TD">9TD</option>
                                    <option value="10TD">10TD</option>
                                    <option value="11TD">11TD</option>
                                    <option value="12TD">12TD</option>
                                    <option value="13TD">13TD</option>
                                    <option value="14TD">14TD</option>
                                    <option value="15TD">15TD</option>
                                </select>
                                <input type="text" id="event-name-custom" class="form-control" placeholder="Custom (optional) – e.g., Parade, Special Drill" style="margin-top: 8px;">
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: center; gap: var(--spacing-md); margin-bottom: var(--spacing-lg);">
                            <button id="start-scanner-btn" class="qr-btn" style="background: var(--military-green); color: var(--text-primary); border: none; padding: var(--spacing-sm) var(--spacing-md); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: var(--spacing-xs);" onclick="startScanner()">
                                <i class="fas fa-play"></i> Start Scanner
                            </button>
                            <button id="stop-scanner-btn" class="qr-btn" style="background: #dc3545; color: var(--text-primary); border: none; padding: var(--spacing-sm) var(--spacing-md); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: none; align-items: center; gap: var(--spacing-xs);" onclick="stopScanner()">
                                <i class="fas fa-stop"></i> Stop Scanner
                            </button>
                            <button id="reset-scan-btn" class="qr-btn" style="background: #17a2b8; color: var(--text-primary); border: none; padding: var(--spacing-sm) var(--spacing-md); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: none; align-items: center; gap: var(--spacing-xs);" onclick="resetCurrentScan()">
                                <i class="fas fa-refresh"></i> Reset Scan
                            </button>
                        </div>
                        
                        <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto; border: 1px solid var(--military-green); border-radius: var(--radius-md); overflow: hidden;"></div>
                        <div id="scan-result" style="display: none; margin-top: var(--spacing-lg); padding: var(--spacing-md); border-radius: var(--radius-md);"></div>
                        
                        <!-- Debug Panel -->
                        <div id="debug-panel" class="debug-panel" style="display: none; margin-top: var(--spacing-lg); padding: var(--spacing-md); background: #1a1a1a; border: 1px solid #333; border-radius: var(--radius-md); color: #fff; font-family: 'Courier New', monospace; font-size: 12px;">
                            <div class="debug-header" style="display: flex; justify-content: between; align-items: center; margin-bottom: var(--spacing-sm); border-bottom: 1px solid #333; padding-bottom: var(--spacing-xs);">
                                <h4 style="margin: 0; color: #ff6b6b; font-size: 14px;"><i class="fas fa-bug"></i> Debug Information</h4>
                                <button id="toggle-debug" class="debug-toggle" style="background: #333; color: #fff; border: 1px solid #555; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px;">Hide</button>
                            </div>
                            <div id="debug-content" class="debug-content">
                                <div class="debug-section">
                                    <strong style="color: #4ecdc4;">Raw QR Data:</strong>
                                    <div id="debug-raw-data" style="background: #2a2a2a; padding: 8px; margin: 4px 0; border-radius: 4px; word-break: break-all; max-height: 100px; overflow-y: auto;">No data</div>
                                </div>
                                <div class="debug-section">
                                    <strong style="color: #45b7d1;">Workflow State:</strong>
                                    <div id="debug-workflow" style="background: #2a2a2a; padding: 8px; margin: 4px 0; border-radius: 4px;">Idle</div>
                                </div>
                                <div class="debug-section">
                                    <strong style="color: #f9ca24;">Decryption Attempts:</strong>
                                    <div id="debug-decryption" style="background: #2a2a2a; padding: 8px; margin: 4px 0; border-radius: 4px; max-height: 150px; overflow-y: auto;">No attempts</div>
                                </div>
                                <div class="debug-section">
                                    <strong style="color: #6c5ce7;">Data Validation:</strong>
                                    <div id="debug-validation" style="background: #2a2a2a; padding: 8px; margin: 4px 0; border-radius: 4px;">No validation performed</div>
                                </div>
                                <div class="debug-section">
                                    <strong style="color: #fd79a8;">Error Details:</strong>
                                    <div id="debug-errors" style="background: #2a2a2a; padding: 8px; margin: 4px 0; border-radius: 4px; max-height: 100px; overflow-y: auto;">No errors</div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="camera-status" style="margin-top: 10px; font-size: 14px; color: #666;">Camera will appear here when scanner is started</div>
                    </div>
        
            <div id="scanner-controls" class="qr-card fade-in" style="display: none;">
                <h3 style="font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--text-accent); text-transform: uppercase; letter-spacing: 2px; margin-bottom: var(--spacing-lg); font-size: 1.2rem; text-align: center;"><i class="fas fa-cog"></i> Scanner Controls</h3>
                
                <div style="text-align: center; margin-bottom: var(--spacing-lg);">
                    <button id="reset-btn" class="qr-btn" style="background: #ffc107; color: var(--text-primary); border: none; padding: var(--spacing-sm) var(--spacing-md); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: var(--spacing-xs);">
                        <i class="fas fa-redo"></i> Reset Session
                    </button>
                </div>
                
                <div class="stats-container">
                    <div class="stats-card">
                        <h3><i class="fas fa-qrcode"></i> Scan Statistics</h3>
                        <div id="scan-stats">
                            <p><strong>Total Scans:</strong> <span id="total-scans">0</span></p>
                            <p><strong>Successful:</strong> <span id="successful-scans">0</span></p>
                            <p><strong>Failed:</strong> <span id="failed-scans">0</span></p>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <h3><i class="fas fa-crosshairs"></i> Rifle Operations</h3>
                        <div id="rifle-stats">
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <span class="stat-value" id="assigned-rifles">0</span>
                                    <span class="stat-label">Assigned</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value" id="returned-rifles">0</span>
                                    <span class="stat-label">Returned</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value" id="total-operations">0</span>
                                    <span class="stat-label">Total</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value" id="success-rate">0%</span>
                                    <span class="stat-label">Rate</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="recent-records">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                    <div id="recent-activities" class="recent-activities">
                        <p style="text-align: center; color: var(--text-secondary); margin: var(--spacing-lg);">No recent activities</p>
                    </div>
                </div>
                
                <div id="current-assignments">
                    <h3><i class="fas fa-crosshairs"></i> Current Assignments</h3>
                    <div id="current-assignments-list" class="current-assignments">
                        <p style="text-align: center; color: var(--text-secondary); margin: var(--spacing-lg);">Loading current assignments...</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

    <script>
        // Sidebar toggle functionality and other event handlers
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const backBtn = document.getElementById('backBtn');
            const resetBtn = document.getElementById('reset-btn');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                });
            }
            
            // Back button functionality
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    window.location.href = 'rifle_management.php';
                });
            }
            
            // Reset button functionality
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (typeof resetSession === 'function') {
                        resetSession();
                    }
                });
            }
            
            // Reset scan button functionality
            const resetScanBtn = document.getElementById('reset-scan-btn');
            if (resetScanBtn) {
                resetScanBtn.addEventListener('click', function() {
                    if (typeof resetCurrentScan === 'function') {
                        resetCurrentScan();
                    }
                });
            }
        });
    </script>
    <!-- Include the rifle scanner JavaScript -->
    <script src="rifle_scanner.js?v=<?php echo $cache_version; ?>"></script>
    <!-- Include mobile navigation JavaScript -->
    <script src="js/mobile-navigation.js"></script>
</body>
</html>