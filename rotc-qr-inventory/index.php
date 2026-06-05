<?php
session_start();
require_once 'includes/db.php';

// Check if QR code is scanned
$qr_data = isset($_GET['qr']) ? $_GET['qr'] : '';
$scan_mode = isset($_GET['mode']) ? $_GET['mode'] : 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROTC QR Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/dashboard.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --accent-gold: #00ff7f;
            --border-color: #333;
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        }
        
        .hero-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            margin: 2rem;
        }
        
        .logo-section {
            margin-bottom: 2rem;
        }
        
        .logo-icon {
            font-size: 4rem;
            color: var(--accent-gold);
            margin-bottom: 1rem;
        }
        
        .system-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        .system-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .qr-scanner {
            background: var(--bg-tertiary);
            border: 2px dashed var(--accent-gold);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .scan-icon {
            font-size: 3rem;
            color: var(--accent-gold);
            margin-bottom: 1rem;
        }
        
        .scan-text {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .manual-entry {
            margin-top: 1rem;
        }
        
        .btn-primary {
            background: var(--accent-gold);
            border: none;
            color: var(--bg-primary);
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .btn-primary:hover {
            background: #e6c200;
            color: var(--bg-primary);
        }
        
        .btn-outline-light {
            border-color: var(--border-color);
            color: var(--text-secondary);
        }
        
        .btn-outline-light:hover {
            background: var(--bg-tertiary);
            border-color: var(--accent-gold);
            color: var(--text-primary);
        }
        
        .form-control {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            background: var(--bg-tertiary);
            border-color: var(--accent-gold);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="hero-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h1 class="system-title">ROTC QR Inventory</h1>
                <p class="system-subtitle">Scan QR Code to Access Inventory System</p>
            </div>
            
            <div class="qr-scanner">
                <div class="scan-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <p class="scan-text">Point your camera at the QR code</p>
                <button class="btn btn-primary" onclick="startQRScanner()">
                    <i class="fas fa-camera me-2"></i>Start Scanner
                </button>
            </div>
            
            <div class="manual-entry">
                <p class="text-muted mb-3">Or enter manually:</p>
                <form method="GET" action="dashboard.php">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="qr" placeholder="Enter QR Code" value="<?php echo htmlspecialchars($qr_data); ?>">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
                
                <div class="d-grid gap-2">
                    <a href="dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function startQRScanner() {
            // Simple QR scanner implementation
            // In a real implementation, you would use a QR scanner library
            alert('QR Scanner would be implemented here using a library like QuaggaJS or ZXing');
            
            // For demo purposes, redirect to dashboard
            window.location.href = 'dashboard.php';
        }
        
        // Check if QR data is provided in URL
        <?php if (!empty($qr_data)): ?>
        // Redirect to dashboard with QR data
        window.location.href = 'dashboard.php?qr=<?php echo urlencode($qr_data); ?>';
        <?php endif; ?>
    </script>