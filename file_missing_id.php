<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Require a logged-in user session; actual access is determined by cadet profile presence
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get cadet profile
$cadet_profile = null;
$cadet_profile_id = null;
$profile_missing = false;
try {
    $stmt = $pdo->prepare("SELECT id, student_id, first_name, last_name FROM cadet_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cadet_profile = $stmt->fetch();
    $cadet_profile_id = $cadet_profile ? $cadet_profile['id'] : null;
} catch (PDOException $e) {
    error_log("Profile query error: " . $e->getMessage());
}

if (!$cadet_profile_id) {
    $profile_missing = true;
}

// Check for active missing ID request
$active_request = null;
try {
    $stmt = $pdo->prepare("
        SELECT *, 
               CASE WHEN expiry_date > NOW() THEN 'active' ELSE 'expired' END as current_status
        FROM missing_id_requests 
        WHERE cadet_id = ? AND status = 'active' AND expiry_date > NOW()
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$cadet_profile_id]);
    $active_request = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Active request query error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $reason = $_POST['reason'] ?? '';
    $reason_details = $_POST['reason_details'] ?? '';
    
    if (empty($reason)) {
        $error = 'Please select a reason for missing ID.';
    } else {
        try {
            // Create new missing ID request
            $expiry_date = date('Y-m-d H:i:s', strtotime('+1 day'));
            
            // Generate QR code data
            $qr_data = json_encode([
                'type' => 'temporary_id',
                'cadet_id' => $cadet_profile_id,
                'student_id' => $cadet_profile['student_id'],
                'name' => $cadet_profile['first_name'] . ' ' . $cadet_profile['last_name'],
                'valid_until' => $expiry_date,
                'issued_at' => date('Y-m-d H:i:s'),
                'reason' => $reason
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO missing_id_requests (cadet_id, reason, reason_details, expiry_date, qr_code_data) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$cadet_profile_id, $reason, $reason_details, $expiry_date, $qr_data]);
            
            // Redirect to show the temporary QR
            header('Location: file_missing_id.php?success=1');
            exit;
            
        } catch (PDOException $e) {
            error_log("Missing ID request error: " . $e->getMessage());
            $error = 'Failed to submit request. Please try again.';
        }
    }
}

$success_message = isset($_GET['success']) ? 'Missing ID request submitted successfully!' : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Missing ID - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎖️</text></svg>">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-medal"></i></div>
                    <span class="logo-text">Cadet Portal</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="cadet_dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="file_missing_id.php" class="nav-link active">
                            <i class="fas fa-id-card-alt"></i>
                            <span>File Missing ID</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="cadet_attendance.php" class="nav-link">
                             <i class="fas fa-calendar-check"></i>
                             <span>My Attendance</span>
                         </a>
                    </li>
                    <li class="nav-item">
                        <a href="grades/view_grades.php" class="nav-link">
                            <i class="fas fa-graduation-cap"></i>
                            <span>My Grades</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="announcements/view.php" class="nav-link">
                            <i class="fas fa-bullhorn"></i>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my_profile.php" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            <span>My Profile</span>
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">File Missing ID</h1>
                        <p class="header-subtitle">Request temporary identification for attendance</p>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="content-area">
                <?php if ($success_message): ?>
                    <div class="alert alert-success" style="margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error" style="margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($profile_missing): ?>
                <div class="dashboard-card fade-in">
                    <div class="card-header">
                        <h3 class="card-title">Complete Your Profile</h3>
                    </div>
                    <div class="card-content">
                        <div class="alert alert-error" style="margin-bottom: var(--spacing-lg);">
                            <i class="fas fa-exclamation-triangle"></i>
                            We couldn't find your cadet profile. Please complete your profile before filing a Missing ID request.
                        </div>
                        <div class="form-actions">
                            <a href="my_profile.php" class="qr-action-btn">
                                <i class="fas fa-user-edit"></i>
                                Go to My Profile
                            </a>
                            <a href="cadet_dashboard.php" class="qr-action-btn secondary">
                                <i class="fas fa-arrow-left"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif ($active_request): ?>
                    <!-- Show Active Temporary QR -->
                    <div class="qr-scanner-section fade-in">
                        <div class="qr-scanner-header">
                            <h2 class="qr-scanner-title">Your Temporary QR Code</h2>
                        </div>
                        <div class="qr-scanner-content">
                            <div class="qr-scanner-info">
                                <h3 style="color: var(--text-accent); margin-bottom: var(--spacing-md);">Temporary Identification</h3>
                                <p>This temporary QR code is valid for attendance purposes until it expires. Show this code to instructors when requested.</p>
                                <div class="alert alert-warning" style="margin: var(--spacing-md) 0;">
                                    <i class="fas fa-clock"></i>
                                    <strong>Expires:</strong> <?php echo date('M j, Y g:i A', strtotime($active_request['expiry_date'])); ?>
                                </div>
                                <ul style="margin: var(--spacing-md) 0; padding-left: var(--spacing-lg);">
                                    <li>Valid for 24 hours only</li>
                                    <li>Use for attendance check-ins</li>
                                    <li>Reason: <?php echo ucfirst($active_request['reason']); ?></li>
                                    <?php if ($active_request['reason_details']): ?>
                                        <li>Details: <?php echo htmlspecialchars($active_request['reason_details']); ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="qr-scanner-actions" style="text-align: center;">
                                <div id="tempQrcode" style="margin: var(--spacing-lg) auto; display: inline-block; padding: 15px; background: #ffffff; border: 4px solid #00ff88; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 255, 136, 0.3);"></div>
                                <p style="color: var(--text-accent); margin-top: var(--spacing-md); font-size: 1rem; font-weight: 600;">
                                    <?php echo htmlspecialchars($cadet_profile['first_name'] . ' ' . $cadet_profile['last_name']); ?>
                                </p>
                                <p style="color: var(--text-secondary); margin-top: var(--spacing-xs); font-size: 0.9rem;">
                                    Student ID: <?php echo htmlspecialchars($cadet_profile['student_id']); ?>
                                </p>
                                <p style="color: var(--text-muted); margin-top: var(--spacing-xs); font-size: 0.8rem;">
                                    Temporary ID - Expires in <span id="countdown"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Show Missing ID Form -->
                    <div class="dashboard-card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">Report Missing ID</h3>
                        </div>
                        <div class="card-content">
                            <form method="POST" action="file_missing_id.php">
                                <div class="form-group">
                                    <label for="reason" class="form-label">Reason for Missing ID *</label>
                                    <select name="reason" id="reason" class="form-control" required>
                                        <option value="">Select a reason...</option>
                                        <option value="lost">Lost</option>
                                        <option value="damaged">Damaged</option>
                                        <option value="stolen">Stolen</option>
                                        <option value="confiscated">Confiscated</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="form-group" id="detailsGroup" style="display: none;">
                                    <label for="reason_details" class="form-label">Additional Details</label>
                                    <textarea name="reason_details" id="reason_details" class="form-control" rows="3" placeholder="Please provide additional details about what happened..."></textarea>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Important:</strong> Your temporary QR code will be valid for 24 hours only. After expiration, you'll need to file a new request.
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="submit_request" class="qr-action-btn">
                                        <i class="fas fa-paper-plane"></i>
                                        Submit Request
                                    </button>
                                    <a href="cadet_dashboard.php" class="qr-action-btn secondary">
                                        <i class="fas fa-arrow-left"></i>
                                        Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.getElementById('sidebar').classList.toggle('collapsed');
                });
            }
        });

        // Show/hide details field based on reason selection
        document.addEventListener('DOMContentLoaded', function() {
            const reasonSelect = document.getElementById('reason');
            if (reasonSelect) {
                reasonSelect.addEventListener('change', function() {
                    const detailsGroup = document.getElementById('detailsGroup');
                    const reasonDetails = document.getElementById('reason_details');
                    
                    if (this.value === 'other') {
                        detailsGroup.style.display = 'block';
                        reasonDetails.required = true;
                    } else {
                        detailsGroup.style.display = 'none';
                        reasonDetails.required = false;
                        reasonDetails.value = '';
                    }
                });
            }
        });

        <?php if ($active_request): ?>
        // Generate temporary QR code with encryption (matching QR/index.html method)
        document.addEventListener('DOMContentLoaded', function() {
            const qrCodeElement = document.getElementById('tempQrcode');
            if (qrCodeElement) {
                // Get the raw QR data from PHP
                const rawQrData = <?php echo json_encode($active_request['qr_code_data']); ?>;
                console.log('Raw QR Data:', rawQrData);
                console.log('QR Element:', qrCodeElement);
                console.log('QRCode available:', typeof QRCode !== 'undefined');
                console.log('CryptoJS available:', typeof CryptoJS !== 'undefined');
                
                // Clear any existing content
                qrCodeElement.innerHTML = '';
                
                // Check if required libraries are loaded
                if (typeof QRCode === 'undefined') {
                    console.error('QRCode library not loaded');
                    qrCodeElement.innerHTML = '<p style="color: red; text-align: center;">QR Code library failed to load. Please refresh the page.</p>';
                    return;
                }
                
                if (typeof CryptoJS === 'undefined') {
                    console.error('CryptoJS library not loaded');
                    qrCodeElement.innerHTML = '<p style="color: red; text-align: center;">Encryption library failed to load. Please refresh the page.</p>';
                    return;
                }
                
                try {
                    // Validate QR data
                    if (!rawQrData || rawQrData.trim() === '') {
                        throw new Error('QR data is empty');
                    }
                    
                    // Use the same encryption key as QR/index.html
                    const secretKey = 'attendance-system-permanent-key-2023';
                    
                    // Parse the JSON data to get the structured data
                    const dataObject = JSON.parse(rawQrData);
                    
                    // Create the same data structure as QR/index.html
                    const qrDataForEncryption = {
                        student_id: dataObject.student_id,
                        name: dataObject.name,
                        valid_until: dataObject.valid_until
                    };
                    
                    // Convert to JSON string
                    const jsonData = JSON.stringify(qrDataForEncryption);
                    
                    // Encrypt the data using the same method as QR/index.html
                    const encryptedData = CryptoJS.AES.encrypt(jsonData, secretKey).toString();
                    
                    console.log('Encrypted QR Data:', encryptedData);
                    
                    // Use the same QR generation method as QR/index.html
                    const qrCode = new QRCode(qrCodeElement, {
                        text: encryptedData,
                        width: 256,
                        height: 256,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H
                    });
                    
                    console.log('QR Code generated successfully with encryption');
                    
                    // Add a small delay to check if QR was actually rendered
                    setTimeout(() => {
                        if (qrCodeElement.children.length === 0) {
                            console.error('QR Code element is empty after generation');
                            qrCodeElement.innerHTML = '<p style="color: red; text-align: center;">QR Code generation failed. Please refresh the page.</p>';
                        }
                    }, 1000);
                    
                } catch (error) {
                    console.error('QR Code generation error:', error);
                    qrCodeElement.innerHTML = '<div style="text-align: center; padding: 20px; border: 2px dashed #ccc; color: #666;"><i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px;"></i><br>Error generating QR code<br><small>Please refresh the page or contact support</small></div>';
                }
            } else {
                console.error('QR code element not found');
            }
            
            // Countdown timer
            const expiryDate = new Date('<?php echo $active_request['expiry_date']; ?>');
            const countdownElement = document.getElementById('countdown');
            
            function updateCountdown() {
                const now = new Date();
                const timeLeft = expiryDate - now;
                
                if (timeLeft <= 0) {
                    countdownElement.textContent = 'EXPIRED';
                    countdownElement.style.color = 'var(--color-danger)';
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                    return;
                }
                
                const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                
                countdownElement.textContent = `${hours}h ${minutes}m ${seconds}s`;
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
        });
        <?php endif; ?>

        // Add fade-in animation to elements
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Initialize fade-in elements
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease-out';
        });
    </script>
</body>
</html>