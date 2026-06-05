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

// Temporary bypass for testing - remove in production
if (isset($_GET['test_mode']) && $_GET['test_mode'] === 'true') {
    // Skip authentication for testing
    $first_name = 'Test';
    $last_name = 'User';
    $user_name = $first_name . ' ' . $last_name;
    $user_role = 'Officer';
} else {
    // Access control: Allow officers, instructors, and admins for rifle management
    check_login();
    if (!in_array($_SESSION['role'], ['officer', 'instructor', 'admin', 'commandant'])) {
        redirect_to_dashboard();
    }
    
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
    <title>QR Scanner (ZXing) - ROTC Management System</title>
    
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
    
    <!-- ZXing QR Code Scanner Library -->
    <script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
    
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
            position: relative;
        }
        
        #reader video {
            width: 100% !important;
            height: auto !important;
        }
        
        #reader canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* QR Card Styling with tactical theme */
        .qr-card {
            background: linear-gradient(135deg, rgba(20, 25, 30, 0.95) 0%, rgba(15, 20, 25, 0.98) 100%);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
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
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-green);
            box-shadow: 0 0 0 2px rgba(var(--accent-green-rgb), 0.2);
        }
        
        /* Scan result styling */
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
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                    <span class="logo-text">Admin Command</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="admin_dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="QR/home.php" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="rifle_management.php" class="nav-link">
                            <i class="fas fa-crosshairs"></i>
                            <span>Rifle Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="rifle_scanner.php" class="nav-link">
                            <i class="fas fa-qrcode"></i>
                            <span>QR Scanner</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="test_rifle_scanner_fixed.php" class="nav-link active">
                            <i class="fas fa-qrcode"></i>
                            <span>ZXing Scanner</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
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
                        <h1 style="font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--text-accent); text-transform: uppercase; letter-spacing: 2px; margin: 0; font-size: 2rem;"><i class="fas fa-qrcode"></i> ZXing QR Scanner</h1>
                        <div style="font-size: 0.9rem; color: var(--text-secondary);">Logged in as: <strong><?php echo htmlspecialchars($user_name); ?></strong> (<?php echo htmlspecialchars($user_role); ?>)</div>
                    </div>
        
                    <div class="qr-card fade-in">
                        <div id="session-info" class="session-info"></div>
                        
                        <h2 style="font-family: 'Orbitron', sans-serif; font-weight: 700; color: var(--text-accent); text-transform: uppercase; letter-spacing: 2px; margin-bottom: var(--spacing-lg); font-size: 1.5rem; text-align: center;"><i class="fas fa-qrcode"></i> ZXing QR Scanner</h2>
                        
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
                                <label for="event-name"><strong>Event Name</strong></label>
                                <input type="text" id="event-name" class="form-control" placeholder="E.g., Morning Formation, Saturday Drill">
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
                        
                        <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto; border: 1px solid var(--military-green); border-radius: var(--radius-md); overflow: hidden;">
                            <video id="video" style="width: 100%; height: auto;"></video>
                            <canvas id="canvas" style="display: none;"></canvas>
                        </div>
                        <div id="scan-result" style="display: none; margin-top: var(--spacing-lg); padding: var(--spacing-md); border-radius: var(--radius-md);"></div>
                        
                        <div id="camera-status" style="margin-top: 10px; font-size: 14px; color: #666;">Camera will appear here when scanner is started</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality and other event handlers
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const backBtn = document.getElementById('backBtn');
            
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
        });
    </script>
    
    <!-- ZXing Scanner JavaScript Implementation -->
    <script>
        // Global variables for ZXing scanner
        let zxingCodeReader = null;
        let scannerActive = false;
        let videoElement = null;
        let canvasElement = null;
        let canvasContext = null;
        let currentOperation = 'attendance';
        let lastScannedCode = null;
        let scanCooldown = false;
        let scanStats = {
            total: 0,
            successful: 0,
            failed: 0
        };
        
        // Encryption keys
        const attendanceKey = 'attendance_secret_key_2024';
        const rifleKey = 'rifle_secret_key_2024';
        
        // Initialize scanner when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ZXing Scanner page loaded');
            initializeScanner();
        });
        
        function initializeScanner() {
            console.log('Initializing ZXing scanner...');
            
            // Get video and canvas elements
            videoElement = document.getElementById('video');
            canvasElement = document.getElementById('canvas');
            
            if (!videoElement || !canvasElement) {
                console.error('Video or canvas element not found');
                return;
            }
            
            canvasContext = canvasElement.getContext('2d');
            
            // Initialize ZXing code reader
            try {
                zxingCodeReader = new ZXing.BrowserQRCodeReader();
                console.log('ZXing BrowserQRCodeReader initialized successfully');
                updateCameraStatus('Scanner initialized. Click "Start Scanner" to begin.');
            } catch (error) {
                console.error('Failed to initialize ZXing:', error);
                updateCameraStatus('Failed to initialize scanner: ' + error.message);
            }
        }
        
        function selectOperation(operation) {
            console.log('Selecting operation:', operation);
            currentOperation = operation;
            
            // Update button states
            document.querySelectorAll('.operation-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(operation + '-btn').classList.add('active');
            
            // Update scanner mode display
            const modeDisplay = document.getElementById('scanner-mode');
            const secretKeyGroup = document.getElementById('secret-key-group');
            const attendanceForm = document.getElementById('attendance-form');
            
            switch(operation) {
                case 'attendance':
                    modeDisplay.textContent = 'Attendance Mode - Scan student QR codes';
                    secretKeyGroup.style.display = 'none';
                    attendanceForm.style.display = 'block';
                    break;
                case 'assign':
                    modeDisplay.textContent = 'Rifle Assignment Mode - Scan rifle QR codes';
                    secretKeyGroup.style.display = 'block';
                    attendanceForm.style.display = 'none';
                    break;
                case 'return':
                    modeDisplay.textContent = 'Rifle Return Mode - Scan rifle QR codes';
                    secretKeyGroup.style.display = 'block';
                    attendanceForm.style.display = 'none';
                    break;
            }
        }
        
        async function startScanner() {
            console.log('Starting ZXing scanner...');
            
            if (!zxingCodeReader) {
                console.error('ZXing code reader not initialized');
                updateCameraStatus('Scanner not initialized');
                return;
            }
            
            if (scannerActive) {
                console.log('Scanner already active');
                return;
            }
            
            try {
                // Check for secure context (HTTPS)
                if (!window.isSecureContext) {
                    console.warn('Not in secure context, camera access may be limited');
                }
                
                // Request camera permissions
                updateCameraStatus('Requesting camera permissions...');
                
                // Get available video input devices using navigator.mediaDevices
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoInputDevices = devices.filter(device => device.kind === 'videoinput');
                console.log('Available cameras:', videoInputDevices);
                
                if (videoInputDevices.length === 0) {
                    throw new Error('No camera devices found');
                }
                
                // Use the first available camera (or back camera if available)
                let selectedDeviceId = videoInputDevices[0].deviceId;
                
                // Try to find back camera
                const backCamera = videoInputDevices.find(device => 
                    device.label.toLowerCase().includes('back') || 
                    device.label.toLowerCase().includes('rear') ||
                    device.label.toLowerCase().includes('environment')
                );
                
                if (backCamera) {
                    selectedDeviceId = backCamera.deviceId;
                    console.log('Using back camera:', backCamera.label);
                } else {
                    console.log('Using first available camera:', videoInputDevices[0].label);
                }
                
                // Start decoding from video device
                const result = await zxingCodeReader.decodeFromVideoDevice(selectedDeviceId, videoElement, (result, error) => {
                    if (result) {
                        console.log('QR Code detected:', result.text);
                        onScanSuccess(result.text);
                    }
                    if (error && !(error instanceof ZXing.NotFoundException)) {
                        console.error('Scan error:', error);
                    }
                });
                
                scannerActive = true;
                updateCameraStatus('Scanner active - Point camera at QR code');
                updateScannerButtons();
                
                console.log('ZXing scanner started successfully');
                
            } catch (error) {
                console.error('Failed to start scanner:', error);
                updateCameraStatus('Failed to start scanner: ' + error.message);
                scannerActive = false;
            }
        }
        
        function stopScanner() {
            console.log('Stopping ZXing scanner...');
            
            if (zxingCodeReader && scannerActive) {
                try {
                    // Stop the video stream
                    if (videoElement && videoElement.srcObject) {
                        const stream = videoElement.srcObject;
                        const tracks = stream.getTracks();
                        tracks.forEach(track => track.stop());
                        videoElement.srcObject = null;
                    }
                    
                    // Reset the ZXing reader
                    zxingCodeReader.reset();
                    console.log('ZXing scanner stopped');
                } catch (error) {
                    console.error('Error stopping scanner:', error);
                }
            }
            
            scannerActive = false;
            updateCameraStatus('Scanner stopped');
            updateScannerButtons();
        }
        
        function updateScannerButtons() {
            const startBtn = document.getElementById('start-scanner-btn');
            const stopBtn = document.getElementById('stop-scanner-btn');
            const resetBtn = document.getElementById('reset-scan-btn');
            
            if (scannerActive) {
                startBtn.style.display = 'none';
                stopBtn.style.display = 'flex';
                resetBtn.style.display = 'flex';
            } else {
                startBtn.style.display = 'flex';
                stopBtn.style.display = 'none';
                resetBtn.style.display = 'none';
            }
        }
        
        function updateCameraStatus(message) {
            const statusElement = document.getElementById('camera-status');
            if (statusElement) {
                statusElement.textContent = message;
                console.log('Camera status:', message);
            }
        }
        
        function onScanSuccess(qrCodeData) {
            console.log('QR scan success:', qrCodeData);
            
            // Prevent rapid successive scans
            if (scanCooldown) {
                console.log('Scan cooldown active, ignoring scan');
                return;
            }
            
            // Check if this is the same code as last scan
            if (lastScannedCode === qrCodeData) {
                console.log('Duplicate scan detected, ignoring');
                return;
            }
            
            lastScannedCode = qrCodeData;
            scanCooldown = true;
            
            // Reset cooldown after 2 seconds
            setTimeout(() => {
                scanCooldown = false;
                lastScannedCode = null;
            }, 2000);
            
            // Update scan statistics
            scanStats.total++;
            
            // Process the QR code based on current operation
            processQRCode(qrCodeData);
        }
        
        function processQRCode(qrData) {
            console.log('Processing QR code for operation:', currentOperation);
            console.log('Raw QR data:', qrData);
            
            try {
                // Try to parse as JSON first
                let parsedData;
                try {
                    parsedData = JSON.parse(qrData);
                    console.log('Parsed JSON data:', parsedData);
                } catch (e) {
                    // If not JSON, treat as plain text
                    parsedData = { raw: qrData };
                    console.log('Plain text data:', parsedData);
                }
                
                // Process based on operation type
                switch (currentOperation) {
                    case 'attendance':
                        processAttendanceQR(parsedData, qrData);
                        break;
                    case 'assign':
                    case 'return':
                        processRifleQR(parsedData, qrData);
                        break;
                    default:
                        showScanResult('Unknown operation mode', 'error');
                }
                
            } catch (error) {
                console.error('Error processing QR code:', error);
                scanStats.failed++;
                showScanResult('Error processing QR code: ' + error.message, 'error');
            }
        }
        
        function processAttendanceQR(data, rawData) {
            console.log('Processing attendance QR');
            
            // Check if required attendance fields are filled
            const schoolYear = document.getElementById('school-year').value;
            const semester = document.getElementById('semester').value;
            const eventName = document.getElementById('event-name').value;
            
            if (!schoolYear || !semester || !eventName) {
                showScanResult('Please fill in all attendance fields (School Year, Semester, Event Name)', 'warning');
                return;
            }
            
            // Try to decrypt attendance data
            let studentData = null;
            
            if (data.encrypted) {
                try {
                    const decrypted = CryptoJS.AES.decrypt(data.encrypted, attendanceKey).toString(CryptoJS.enc.Utf8);
                    studentData = JSON.parse(decrypted);
                    console.log('Decrypted attendance data:', studentData);
                } catch (e) {
                    console.error('Failed to decrypt attendance data:', e);
                }
            } else if (data.student_id || data.id) {
                studentData = data;
            }
            
            if (studentData && (studentData.student_id || studentData.id)) {
                scanStats.successful++;
                const studentId = studentData.student_id || studentData.id;
                const studentName = studentData.name || studentData.full_name || 'Unknown Student';
                
                showScanResult(`Attendance recorded for: ${studentName} (ID: ${studentId})`, 'success');
                
                // Here you would typically send the data to your attendance API
                console.log('Attendance data to submit:', {
                    student_id: studentId,
                    student_name: studentName,
                    school_year: schoolYear,
                    semester: semester,
                    event_name: eventName,
                    timestamp: new Date().toISOString()
                });
                
            } else {
                scanStats.failed++;
                showScanResult('Invalid attendance QR code format', 'error');
            }
        }
        
        function processRifleQR(data, rawData) {
            console.log('Processing rifle QR for operation:', currentOperation);
            
            // Check if secret key is provided
            const secretKey = document.getElementById('secret-key').value;
            if (!secretKey) {
                showScanResult('Please enter the secret key for rifle operations', 'warning');
                return;
            }
            
            // Try to decrypt rifle data
            let rifleData = null;
            
            if (data.encrypted) {
                try {
                    const decrypted = CryptoJS.AES.decrypt(data.encrypted, rifleKey).toString(CryptoJS.enc.Utf8);
                    rifleData = JSON.parse(decrypted);
                    console.log('Decrypted rifle data:', rifleData);
                } catch (e) {
                    console.error('Failed to decrypt rifle data:', e);
                }
            } else if (data.rifle_id || data.serial_number) {
                rifleData = data;
            }
            
            if (rifleData && (rifleData.rifle_id || rifleData.serial_number)) {
                scanStats.successful++;
                const rifleId = rifleData.rifle_id || rifleData.serial_number;
                const rifleModel = rifleData.model || 'Unknown Model';
                
                const operationText = currentOperation === 'assign' ? 'assigned' : 'returned';
                showScanResult(`Rifle ${operationText}: ${rifleModel} (ID: ${rifleId})`, 'success');
                
                // Here you would typically send the data to your rifle management API
                console.log('Rifle operation data to submit:', {
                    rifle_id: rifleId,
                    rifle_model: rifleModel,
                    operation: currentOperation,
                    secret_key: secretKey,
                    timestamp: new Date().toISOString()
                });
                
            } else {
                scanStats.failed++;
                showScanResult('Invalid rifle QR code format', 'error');
            }
        }
        
        function showScanResult(message, type = 'info') {
            const resultElement = document.getElementById('scan-result');
            if (!resultElement) return;
            
            // Clear previous classes
            resultElement.className = '';
            resultElement.classList.add('result-' + type);
            
            resultElement.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            resultElement.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                resultElement.style.display = 'none';
            }, 5000);
        }
        
        function resetCurrentScan() {
            console.log('Resetting current scan');
            lastScannedCode = null;
            scanCooldown = false;
            
            const resultElement = document.getElementById('scan-result');
            if (resultElement) {
                resultElement.style.display = 'none';
            }
            
            updateCameraStatus('Scan reset - Ready for next QR code');
        }
        
        // Initialize with attendance mode
        selectOperation('attendance');
    </script>
</body>
</html>