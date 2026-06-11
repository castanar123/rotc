<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/term_enrollment.php';

// Check if user is logged in and is admin
check_login();
if (!rotc_role_in(['admin'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit();
}

// Verify database connection is available
if (!isset($pdo) || $pdo === null) {
    $error_msg = isset($GLOBALS['DB_CONNECTION_ERROR']) 
        ? $GLOBALS['DB_CONNECTION_ERROR'] 
        : 'Database connection failed. The server may be offline.';
    die('Fatal Error: ' . $error_msg);
}

// Initialize variables
$error_message = '';
$success_message = '';
$generation_status = '';

ensure_term_enrollment_schema();
$__terms = get_all_terms();
$__activeTerm = get_active_term();

try {
    // Database connection is already available from includes/db.php

    // Get cadet statistics for dashboard (active term + enrolled cadets)
    $stats_query = "SELECT 
        COUNT(*) as total_cadets,
        SUM(CASE WHEN year_level = 'MS1' THEN 1 ELSE 0 END) as ms1_count,
        SUM(CASE WHEN year_level = 'MS2' THEN 1 ELSE 0 END) as ms2_count,
        SUM(CASE WHEN year_level = 'MS3' THEN 1 ELSE 0 END) as ms3_count,
        SUM(CASE WHEN year_level = 'MS4' THEN 1 ELSE 0 END) as ms4_count,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_count
        FROM cadet_profiles cp
        JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
        WHERE cp.status IN ('active','Active')
          AND ce.school_year = ?
          AND ce.semester = ?
          AND ce.enrollment_status = 'enrolled'";

    $stmt = $pdo->prepare($stats_query);
    $stmt->execute([
        (string)($__activeTerm['school_year'] ?? ''),
        (string)($__activeTerm['semester'] ?? ''),
    ]);
    $cadet_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Handle document generation requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $document_type = $_POST['document_type'] ?? '';
        $sub_document = $_POST['sub_document'] ?? '';

        if ($document_type === 'aer' && !empty($sub_document)) {
            // Handle AER document generation
            $generation_status = generateAERDocument($sub_document, $pdo);
        }
        elseif ($document_type === 'asr') {
            // Handle ASR document generation
            $generation_status = generateASRDocument($pdo);
        }
    }


}
catch (PDOException $e) {
    error_log("Document generation error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again.";
}

function generateAERDocument($sub_document, $pdo)
{
    // This will be implemented to generate specific AER documents
    return "AER {$sub_document} document generation initiated...";
}

function generateASRDocument($pdo)
{
    // This will be implemented to generate ASR documents
    return "ASR document generation initiated...";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Generation - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <style>
        .document-generation-container {
            padding: var(--spacing-lg);
        }
        
        .document-section {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }
        
        .document-section h3 {
            color: var(--text-accent);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .document-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }
        
        .document-card {
            background: var(--surface-primary);
            border: 1px solid var(--border-primary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            transition: all 0.3s ease;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .document-card h4 {
            color: var(--text-accent);
            margin-bottom: var(--spacing-md);
        }
        
        .sub-document-list {
            list-style: none;
            padding: 0;
            margin: var(--spacing-md) 0;
        }
        
        .sub-document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--spacing-sm);
            margin-bottom: var(--spacing-xs);
            background: var(--surface-secondary);
            border-radius: var(--border-radius-sm);
        }
        
        .generate-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .generate-btn:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .stat-item {
            background: var(--surface-primary);
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            text-align: center;
            border: 1px solid var(--border-primary);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--text-accent);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .generation-status {
            background: var(--surface-secondary);
            border: 1px solid var(--border-primary);
            border-radius: var(--border-radius);
            padding: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .alert {
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-md);
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
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

        <!-- Main Content -->
        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header fade-in">
                <div class="header-content">
                    <div>
                        <h1 class="header-title">Document Generation</h1>
                        <p class="header-subtitle">Generate AER and ASR documents for ROTC cadets</p>
                    </div>
                    <div class="header-actions">
                        <form method="POST" action="set_active_term.php" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                            <select name="term_key" onchange="this.form.submit()" style="background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.18); border-radius: 10px; padding: 10px 12px; min-width: 220px; outline: none;">
                                <?php foreach (($__terms ?? []) as $__t):
    $key = ($__t['school_year'] ?? '') . '|' . ($__t['semester'] ?? '');
    $label = ($__t['school_year'] ?? '') . ' ' . ($__t['semester'] ?? '');
    $selected = (($__activeTerm['school_year'] ?? '') === ($__t['school_year'] ?? '') && ($__activeTerm['semester'] ?? '') === ($__t['semester'] ?? '')) ? 'selected' : ''; ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $selected; ?> style="color:#111;"><?php echo htmlspecialchars($label); ?></option>
                                <?php
endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="qr-integration-btn">Set Term</button></noscript>
                        </form>
                    </div>
                </div>
            </div>

            <div class="document-generation-container">
                <!-- Status Messages -->
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php
endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php
endif; ?>
                
                <!-- Dynamic Generation Status -->
                <div id="generation-status"></div>

                <!-- Cadet Statistics Overview -->
                <div class="document-section">
                    <h3><i class="fas fa-chart-pie"></i> Cadet Statistics Overview</h3>
                    <div class="stats-overview">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $cadet_stats['total_cadets'] ?? 0; ?></div>
                            <div class="stat-label">Total Cadets</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $cadet_stats['ms1_count'] ?? 0; ?></div>
                            <div class="stat-label">MS-1 (Basic)</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $cadet_stats['ms2_count'] ?? 0; ?></div>
                            <div class="stat-label">MS-1 (2CL)</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $cadet_stats['ms3_count'] ?? 0; ?></div>
                            <div class="stat-label">MS-3 (1CL)</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $cadet_stats['male_count'] ?? 0; ?></div>
                            <div class="stat-label">Male Cadets</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $cadet_stats['female_count'] ?? 0; ?></div>
                            <div class="stat-label">Female Cadets</div>
                        </div>
                    </div>
                </div>

                <!-- Document Generation Section -->
                <div class="document-grid">
                    <!-- AER Documents -->
                    <div class="document-card">
                        <h4><i class="fas fa-file-alt"></i> AER Documents</h4>
                        <p>Annual Enrollment Report - Generate comprehensive enrollment reports</p>
                        
                        <div class="document-form">
                            <ul class="sub-document-list">
                                <li class="sub-document-item">
                                    <span><i class="fas fa-chart-bar"></i> Summary Report</span>
                                    <button type="button" id="generate-aer-summary" class="generate-btn">
                                        <i class="fas fa-download"></i> Generate
                                    </button>
                                </li>
                                <li class="sub-document-item">
                                    <span><i class="fas fa-list"></i> Roster of Enrolled Cadets</span>
                                    <button type="button" id="generate-aer-roster" class="generate-btn">
                                        <i class="fas fa-download"></i> Generate
                                    </button>
                                </li>
                                <li class="sub-document-item">
                                    <span><i class="fas fa-users"></i> List of Beneficiaries</span>
                                    <button type="button" id="generate-aer-beneficiaries" class="generate-btn">
                                        <i class="fas fa-download"></i> Generate
                                    </button>
                                </li>
                                <li class="sub-document-item">
                                    <span><i class="fas fa-id-card"></i> Cadet Profiles</span>
                                    <button type="button" id="generate-aer-profile" class="generate-btn">
                                        <i class="fas fa-download"></i> Generate
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Attendance & Roster Section -->
                    <div class="document-card">
                        <h4><i class="fas fa-clipboard-user"></i> Attendance & Roster</h4>
                        <p>Generate attendance sheets and formatted cadet rosters</p>
                        
                        <div class="document-form">
                            <ul class="sub-document-list">
                                <li class="sub-document-item">
                                    <span><i class="fas fa-list-alt"></i> Basic Cadet List (with Student Numbers)</span>
                                    <button type="button" id="generate-basic-cadet-list" class="generate-btn">
                                        <i class="fas fa-download"></i> Generate
                                    </button>
                                </li>
                                <li class="sub-document-item">
                                    <span><i class="fas fa-users-cog"></i> Attendance per Platoon</span>
                                    <button type="button" id="generate-attendance-platoon" class="generate-btn" style="background: linear-gradient(135deg, #059669, #047857);">
                                        <i class="fas fa-clipboard-check"></i> Generate
                                    </button>
                                </li>
                                <li class="sub-document-item">
                                    <span><i class="fas fa-qrcode"></i> Cadet QR Data Export</span>
                                    <button type="button" id="generate-qr-data-export" class="generate-btn">
                                        <i class="fas fa-file-excel"></i> Export
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- ASR Documents -->
                    <div class="document-card">
                        <h4><i class="fas fa-file-text"></i> ASR Documents</h4>
                        <p>Annual Status Report - Generate annual status and progress reports</p>
                        
                        <div class="document-form">
                            <div style="display: flex; flex-direction: column; gap: var(--spacing-md); padding: var(--spacing-lg); align-items: center;">
                                <button type="button" id="generate-asr" class="generate-btn" style="padding: var(--spacing-md) var(--spacing-lg); font-size: 1rem;">
                                    <i class="fas fa-download"></i> ASR Completion List
                                </button>
                                <button type="button" id="generate-asr-grade-report" class="generate-btn" style="padding: var(--spacing-md) var(--spacing-lg); font-size: 1rem;">
                                    <i class="fas fa-download"></i> ASR Grade Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Generation History -->
                <div class="document-section">
                    <h3><i class="fas fa-history"></i> Recent Generation History</h3>
                    <div id="generation-history" class="generation-history">
                        <p style="color: var(--text-secondary); text-align: center; padding: var(--spacing-xl);">
                            No recent document generations found.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript -->
    <script>
        // Add loading state to generate buttons
        document.querySelectorAll('.generate-btn').forEach(button => {
            button.addEventListener('click', function() {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
                this.disabled = true;
            });
        });
        
        // Document generation functionality
        function generateDocument(documentType, subDocument = null) {
            const statusDiv = document.getElementById('generation-status');
            const historyDiv = document.getElementById('generation-history');
            
            // Show loading status
            statusDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Generating document...</div>';
            
            // Prepare JSON data
            const requestData = {
                document_type: documentType
            };
            
            // Get selected term from dropdown if available
            const termSelect = document.querySelector('select[name="term_key"]');
            if (termSelect && termSelect.value) {
                const parts = termSelect.value.split('|');
                if (parts.length === 2) {
                    requestData.target_school_year = parts[0];
                    requestData.target_semester = parts[1];
                }
            }
            
            if (subDocument) {
                requestData.sub_document = subDocument;
            }
            
            // Make AJAX request
            fetch('generate_document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> ${data.message}
                            <br>
                            <a href="${data.download_url}" class="btn btn-sm btn-primary mt-2" download="${data.filename || 'document.csv'}">
                                <i class="fas fa-download"></i> Download Document
                            </a>
                        </div>
                    `;
                    
                    // Add to history
                    const historyItem = document.createElement('div');
                    historyItem.className = 'history-item mb-2 p-2 border rounded';
                    historyItem.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${documentType.toUpperCase()}${subDocument ? ' - ' + subDocument.replace('_', ' ').toUpperCase() : ''}</strong>
                                <br>
                                <small class="text-muted">${new Date().toLocaleString()}</small>
                            </div>
                            <a href="${data.download_url}" class="btn btn-sm btn-outline-primary" download="${data.filename || 'document.csv'}">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    `;
                    historyDiv.insertBefore(historyItem, historyDiv.firstChild);

                    // Re-enable button after success
                    resetGenerateButtons();
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${data.message}
                        </div>
                    `;
                    resetGenerateButtons();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> An error occurred while generating the document.
                    </div>
                `;
                resetGenerateButtons();
            });
        }

        function resetGenerateButtons() {
            document.querySelectorAll('.generate-btn').forEach(button => {
                if (button.id === 'generate-attendance-platoon') {
                     button.innerHTML = '<i class="fas fa-clipboard-check"></i> Generate';
                } else if (button.id === 'generate-asr-grade-report') {
                     button.innerHTML = '<i class="fas fa-download"></i> ASR Grade Report';
                } else if (button.id === 'generate-asr') {
                     button.innerHTML = '<i class="fas fa-download"></i> ASR Completion List';
                } else if (button.id === 'generate-qr-data-export') {
                     button.innerHTML = '<i class="fas fa-file-excel"></i> Export';
                } else {
                    button.innerHTML = '<i class="fas fa-download"></i> Generate';
                }
                button.disabled = false;
            });
        }
        
        // Event listeners for AER document buttons
        document.addEventListener('DOMContentLoaded', function() {
            // AER Summary
            document.getElementById('generate-aer-summary').addEventListener('click', function() {
                generateDocument('aer', 'summary');
            });
            
            // AER Roster
            document.getElementById('generate-aer-roster').addEventListener('click', function() {
                generateDocument('aer', 'roster');
            });
            
            // AER Beneficiaries
            document.getElementById('generate-aer-beneficiaries').addEventListener('click', function() {
                generateDocument('aer', 'beneficiaries');
            });
            
            // AER Cadet Profile
            document.getElementById('generate-aer-profile').addEventListener('click', function() {
                generateDocument('aer', 'cadet_profile');
            });
            
            // Basic Cadet List
            document.getElementById('generate-basic-cadet-list').addEventListener('click', function() {
                // Direct download for basic cadet list
                let url = 'generate_basic_cadet_list.php';
                const termSelect = document.querySelector('select[name="term_key"]');
                if (termSelect && termSelect.value) {
                    const parts = termSelect.value.split('|');
                    if (parts.length === 2) {
                        url += '?school_year=' + encodeURIComponent(parts[0]) + '&semester=' + encodeURIComponent(parts[1]);
                    }
                }
                window.location.href = url;
                setTimeout(resetGenerateButtons, 1000); // Reset after delay for direct download
            });
            
            // ASR Completion List
            document.getElementById('generate-asr').addEventListener('click', function() {
                generateDocument('asr');
            });

            // ASR Grade Report (First Middle Last name format)
            const asrGradeBtn = document.getElementById('generate-asr-grade-report');
            if (asrGradeBtn) {
                asrGradeBtn.addEventListener('click', function() {
                    generateDocument('asr', 'grade_report');
                });
            }

            // Attendance per Platoon
            const attPlatoonBtn = document.getElementById('generate-attendance-platoon');
            if (attPlatoonBtn) {
                attPlatoonBtn.addEventListener('click', function() {
                    generateDocument('attendance_platoon');
                });
            }

            // Cadet QR Data Export
            const qrExportBtn = document.getElementById('generate-qr-data-export');
            if (qrExportBtn) {
                qrExportBtn.addEventListener('click', function() {
                    generateDocument('qr_data_export');
                });
            }
        });
    </script>
    <script src="js/mobile-navigation.js"></script>
</body>
</html>
