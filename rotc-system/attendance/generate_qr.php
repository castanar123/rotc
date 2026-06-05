<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'instructor'])) {
    header('Location: https://rotc.lspulbrotcunit.online/generate%20qr/login.php');
    exit;
}

$message = '';
$qr_code_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = trim($_POST['event_name'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    
    if (!empty($event_name) && !empty($event_date) && !empty($event_time)) {
        // Create unique identifier for the event
        $event_id = uniqid('event_', true);
        $qr_data = json_encode([
            'event_id' => $event_id,
            'event_name' => $event_name,
            'event_date' => $event_date,
            'event_time' => $event_time,
            'created_by' => $_SESSION['user_id'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Generate QR code using phpqrcode library
        require_once '../libs/phpqrcode/qrlib.php';
        
        $qr_dir = '../uploads/qrcodes/';
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        
        $filename = 'qr_' . $event_id . '.png';
        $qr_code_path = $qr_dir . $filename;
        
        try {
            QRcode::png($qr_data, $qr_code_path, QR_ECLEVEL_L, 8);
            
            // Store event in database
            $stmt = $pdo->prepare("INSERT INTO attendance_events (event_id, event_name, event_date, event_time, qr_code_path, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$event_id, $event_name, $event_date, $event_time, $qr_code_path, $_SESSION['user_id']]);
            
            $message = 'QR code generated successfully!';
        } catch (Exception $e) {
            $message = 'Error generating QR code: ' . $e->getMessage();
        }
    } else {
        $message = 'Please fill in all required fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR Code - ROTC Management System</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-unified.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Back to Attendance
                </a>
                <h1 class="page-title">Generate QR Code</h1>
            </div>
            
            <div class="header-right">
                <div class="user-menu">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                        <span class="user-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content">
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-qrcode"></i>
                            Generate Attendance QR Code
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="form">
                            <div class="form-group">
                                <label for="event_name" class="form-label">Event Name</label>
                                <input type="text" id="event_name" name="event_name" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="event_date" class="form-label">Event Date</label>
                                <input type="date" id="event_date" name="event_date" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="event_time" class="form-label">Event Time</label>
                                <input type="time" id="event_time" name="event_time" class="form-input" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-qrcode"></i>
                                Generate QR Code
                            </button>
                        </form>
                        
                        <?php if ($qr_code_path && file_exists($qr_code_path)): ?>
                            <div class="qr-result">
                                <h4>Generated QR Code:</h4>
                                <div class="qr-display">
                                    <img src="<?php echo $qr_code_path; ?>" alt="QR Code" class="qr-image">
                                    <div class="qr-actions">
                                        <a href="<?php echo $qr_code_path; ?>" download class="btn btn-secondary">
                                            <i class="fas fa-download"></i>
                                            Download QR Code
                                        </a>
                                        <button onclick="printQR()" class="btn btn-outline">
                                            <i class="fas fa-print"></i>
                                            Print QR Code
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .qr-result {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-primary);
        }
        
        .qr-display {
            text-align: center;
            margin-top: 1rem;
        }
        
        .qr-image {
            max-width: 300px;
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: 1rem;
            background: white;
        }
        
        .qr-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--military-green);
            color: var(--military-green);
        }
        
        .alert-danger {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
    </style>

    <script>
        function printQR() {
            const qrImage = document.querySelector('.qr-image');
            if (qrImage) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Print QR Code</title>
                            <style>
                                body { text-align: center; margin: 20px; }
                                img { max-width: 400px; }
                            </style>
                        </head>
                        <body>
                            <h2>Attendance QR Code</h2>
                            <img src="${qrImage.src}" alt="QR Code">
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }
    </script>
</body>
</html>