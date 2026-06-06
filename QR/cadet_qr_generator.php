<?php
require_once '../includes/session.php';
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit();
}

$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    if ($searchTerm !== '') {
        $sql = "SELECT id, student_id, last_name, first_name, middle_name, platoon, gender 
                FROM cadet_profiles
                WHERE student_id LIKE :term
                   OR last_name LIKE :term
                   OR first_name LIKE :term
                ORDER BY last_name, first_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':term' => "%{$searchTerm}%"]);
        $cadets = $stmt->fetchAll();
    }
    else {
        $sql = "SELECT id, student_id, last_name, first_name, middle_name, platoon, gender 
                FROM cadet_profiles
                ORDER BY last_name, first_name";
        $stmt = $pdo->query($sql);
        $cadets = $stmt->fetchAll();
    }
}
catch (Exception $e) {
    $cadets = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadet QR Generator (ROTC_QR_V1)</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <style>
        .cadet-generator-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }
        .generator-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        .layout-grid {
            display: grid;
            grid-template-columns: 2fr 1.5fr;
            gap: var(--spacing-xl);
        }
        .card-panel {
            background: var(--card-bg);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
        }
        .search-row {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        .search-row input[type="text"] {
            flex: 1;
            padding: var(--spacing-md);
            background: rgba(15, 20, 25, 0.95);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-md);
            color: var(--text-primary);
        }
        .search-row button {
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--radius-md);
            border: 1px solid var(--military-green);
            background: linear-gradient(135deg, var(--military-green), #16a34a);
            color: #fff;
            cursor: pointer;
            font-weight: 600;
        }
        .cadet-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--spacing-md);
            font-size: 0.9rem;
        }
        .cadet-table th,
        .cadet-table td {
            padding: var(--spacing-sm);
            border-bottom: 1px solid var(--border-primary);
            text-align: left;
        }
        .cadet-table th {
            color: var(--text-secondary);
            font-weight: 600;
        }
        .generate-qr-btn {
            padding: 4px 10px;
            font-size: 0.8rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--military-green);
            background: rgba(34, 197, 94, 0.1);
            color: var(--text-primary);
            cursor: pointer;
        }
        .qr-preview-header {
            margin-bottom: var(--spacing-md);
        }
        .qr-canvas-wrapper {
            text-align: center;
            margin: var(--spacing-md) 0;
        }
        .qr-canvas-wrapper canvas {
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
        }
        .qr-metadata {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .qr-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        .qr-actions button {
            flex: 1;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-primary);
            background: var(--card-bg);
            color: var(--text-primary);
            cursor: pointer;
        }
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--card-bg);
            border: 1px solid var(--border-primary);
            color: var(--text-secondary);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all var(--transition-fast);
            z-index: 1000;
        }
        .back-btn:hover {
            background: var(--military-green);
            color: var(--text-primary);
            border-color: var(--military-green);
        }
        @media (max-width: 900px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="home.html" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to QR Home
    </a>

    <div class="cadet-generator-container">
        <div class="generator-header fade-in">
            <h1><i class="fas fa-id-card"></i> Cadet QR Generator (ROTC_QR_V1)</h1>
            <p>Select a cadet from the database and generate an interoperable QR code.</p>
        </div>

        <div class="layout-grid">
            <div class="card-panel fade-in">
                <h2><i class="fas fa-users"></i> Cadet List</h2>
                <form method="get" class="search-row">
                    <input type="text" name="q" placeholder="Search by Student ID, Last Name, or First Name" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                </form>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">
                    All cadets are displayed. Use search to filter.
                </p>
                <div style="max-height: 500px; overflow: auto;">
                    <table class="cadet-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Platoon</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($cadets)): ?>
                                <?php foreach ($cadets as $cadet): ?>
                                    <?php
        $fullName = trim(($cadet['last_name'] ?? '') . ', ' . ($cadet['first_name'] ?? '') . ' ' . ($cadet['middle_name'] ?? ''));
?>
                                    <tr>
                                        <td><?php echo (int)$cadet['id']; ?></td>
                                        <td><?php echo htmlspecialchars($cadet['student_id'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($fullName); ?></td>
                                        <td><?php echo htmlspecialchars($cadet['gender'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($cadet['platoon'] ?? ''); ?></td>
                                        <td>
                                            <button type="button" class="generate-qr-btn" onclick="generateCadetQRFromButton(this)"
                                                data-profile-id="<?php echo (int)$cadet['id']; ?>"
                                                data-student-id="<?php echo htmlspecialchars($cadet['student_id'] ?? ''); ?>"
                                                data-last-name="<?php echo htmlspecialchars(strtoupper($cadet['last_name'] ?? '')); ?>"
                                                data-platoon="<?php echo htmlspecialchars($cadet['platoon'] ?? ''); ?>">
                                                Generate QR
                                            </button>
                                        </td>
                                    </tr>
                                <?php
    endforeach; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="6">No cadets found.</td>
                                </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-panel fade-in">
                <div class="qr-preview-header">
                    <h2><i class="fas fa-qrcode"></i> QR Preview</h2>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">
                        QR payload format:
                        <code>{ system: 'rotc_system', type: 'cadet', profile_id, student_id, last_name, platoon }</code>
                    </p>
                </div>
                <div class="qr-canvas-wrapper">
                    <div id="cadetQrContainer" style="width:260px;height:260px;margin:0 auto;"></div>
                </div>
                <div id="qrMeta" class="qr-metadata"></div>
                <div class="qr-actions">
                    <button type="button" id="downloadCadetQr"><i class="fas fa-download"></i> Download</button>
                    <button type="button" id="printCadetQr"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const QR_KEY_PREFIX = 'ROTC_QR_V1::';

        function encodeROTCQR(payload) {
            return btoa(QR_KEY_PREFIX + JSON.stringify(payload));
        }

        const container = document.getElementById('cadetQrContainer');
        const qrMetaEl = document.getElementById('qrMeta');
        const downloadBtn = document.getElementById('downloadCadetQr');
        const printBtn = document.getElementById('printCadetQr');

        let lastPayload = null;
        let qrInstance = null;

        function generateCadetQRFromButton(button) {
            const profileId = button.getAttribute('data-profile-id');
            const studentId = button.getAttribute('data-student-id') || '';
            const lastName = (button.getAttribute('data-last-name') || '').toUpperCase();
            const rawPlatoon = button.getAttribute('data-platoon') || '';
            const platoon = rawPlatoon || 'DELTA SECOND';

            const payload = {
                system: 'rotc_system',
                type: 'cadet',
                profile_id: 'CDT-' + String(profileId),
                student_id: String(studentId),
                last_name: lastName,
                platoon: platoon
            };

            const qrString = encodeROTCQR(payload);

            // Clear previous QR
            container.innerHTML = '';
            qrInstance = new QRCode(container, {
                text: qrString,
                width: 260,
                height: 260,
                colorDark: '#0f1419',
                colorLight: '#ffffff',
                // Let library auto-select QR version and use medium error correction
                typeNumber: 0,
                correctLevel: QRCode.CorrectLevel.M
            });

            lastPayload = payload;
            qrMetaEl.innerHTML = `
                <div>
                    <p><strong>Profile ID:</strong> ${payload.profile_id}</p>
                    <p><strong>Student ID:</strong> ${payload.student_id}</p>
                    <p><strong>Last Name:</strong> ${payload.last_name}</p>
                    <p><strong>Platoon:</strong> ${payload.platoon}</p>
                </div>
            `;
        }

        document.querySelectorAll('.generate-qr-btn').forEach(btn => {
            btn.addEventListener('click', () => generateCadetQRFromButton(btn));
        });



        downloadBtn.addEventListener('click', function () {
            if (!lastPayload) {
                alert('Generate a cadet QR first.');
                return;
            }
            const qrCanvas = container.querySelector('canvas');
            if (!qrCanvas) {
                alert('QR canvas not found. Generate a QR first.');
                return;
            }
            const link = document.createElement('a');
            const label = lastPayload.student_id || lastPayload.profile_id;
            link.download = `cadet-qr-${label}.png`;
            link.href = qrCanvas.toDataURL('image/png');
            link.click();
        });

        printBtn.addEventListener('click', function () {
            if (!lastPayload) {
                alert('Generate a cadet QR first.');
                return;
            }
            const qrCanvas = container.querySelector('canvas');
            if (!qrCanvas) {
                alert('QR canvas not found. Generate a QR first.');
                return;
            }
            const printWindow = window.open('', '_blank');
            const imgData = qrCanvas.toDataURL('image/png');
            const label = lastPayload.student_id || lastPayload.profile_id;
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Cadet QR - ${label}</title>
                        <style>
                            body { text-align: center; font-family: Arial, sans-serif; }
                            .print-header { margin-bottom: 20px; }
                            .qr-info { margin-top: 20px; text-align: left; max-width: 300px; margin-left: auto; margin-right: auto; }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h2>Cadet QR Code</h2>
                            <h3>${label}</h3>
                        </div>
                        <img src="${imgData}" style="border: 2px solid #000;">
                        <div class="qr-info">
                            ${qrMetaEl.innerHTML}
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        });
    </script>
</body>
</html>
