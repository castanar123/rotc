<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Access control: Allow Admins and Instructors
check_login();
if (!in_array($_SESSION['role'], ['admin', 'instructor', '1cl', '2cl'])) {
    redirect_to_dashboard();
}

// --- Helper to generate school year options ---
function get_school_year_options($selected = '') {
    $current_year = date('Y');
    $options = '';
    for ($i = $current_year + 1; $i >= $current_year - 5; $i--) {
        $sy = ($i - 1) . '-' . $i;
        $options .= "<option value='{$sy}' " . ($selected == $sy ? 'selected' : '') . ">{$sy}</option>";
    }
    return $options;
}

$page_title = 'Scan Attendance';
include '../includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">QR Code Attendance Scanner</h1>

    <!-- Status Indicator -->
    <div id="statusIndicator" class="mb-4 alert alert-info">
        <strong>Status:</strong> <span id="onlineStatus" class="badge badge-success">Online</span>
        <span id="syncContainer" style="display: none;" class="ml-3">
            <span id="syncCount" class="badge badge-warning">0</span> records pending sync.
            <button id="syncBtn" class="btn btn-sm btn-info ml-2">Sync Now</button>
        </span>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Scanner Control</h6>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="schoolYear"><strong>School Year</strong></label>
                            <select id="schoolYear" class="form-control">
                                <option value="" selected disabled>Select S.Y.</option>
                                <?php echo get_school_year_options(); ?>
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
                        <label for="eventName"><strong>Event Name</strong></label>
                        <input type="text" id="eventName" class="form-control" placeholder="E.g., Morning Formation, Saturday Drill">
                    </div>
                    <button id="startButton" class="btn btn-primary w-100 mt-3" disabled>Start Scanner</button>
                    <div id="scanner-container" class="mt-3" style="display: none;">
                        <div id="qr-reader"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Scan Results</h6>
                </div>
                <div class="card-body">
                    <div id="scanResult">- Waiting for scan -</div>
                    <hr>
                    <h5>Last 5 Scans:</h5>
                    <ul id="scanHistory" class="list-group"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #qr-reader {
        position: relative;
        /* 4:3 Aspect Ratio */
        padding-bottom: 75%;
        height: 0;
        overflow: hidden;
    }
    #qr-reader video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100% !important;
        height: 100% !important;
        object-fit: cover;
    }
</style>
<!-- Include the QR Code library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/dist/html5-qrcode.min.js"></script>
<!-- CryptoJS for AES-encrypted QR compatibility -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded. Setting up scanner page.');
    const schoolYearSelect = document.getElementById('schoolYear');
    const semesterSelect = document.getElementById('semester');
    const eventNameInput = document.getElementById('eventName');
    const scannerContainer = document.getElementById('scanner-container');
    const scanResultDiv = document.getElementById('scanResult');
    const scanHistoryUl = document.getElementById('scanHistory');
    let html5QrCode = null;
    let scannerStarted = false;

    // New elements for offline status
    const onlineStatus = document.getElementById('onlineStatus');
    const syncContainer = document.getElementById('syncContainer');
    const syncCount = document.getElementById('syncCount');
    const syncBtn = document.getElementById('syncBtn');
    const PENDING_SCANS_KEY = 'pending_attendance_scans';

    // --- Offline Data Management ---
    function getPendingScans() {
        return JSON.parse(localStorage.getItem(PENDING_SCANS_KEY)) || [];
    }

    function savePendingScan(scanData) {
        const scans = getPendingScans();
        scans.push(scanData);
        localStorage.setItem(PENDING_SCANS_KEY, JSON.stringify(scans));
        updateSyncCount();
    }

    function updateSyncCount() {
        const pendingCount = getPendingScans().length;
        syncCount.textContent = pendingCount;
        if (pendingCount > 0) {
            syncContainer.style.display = 'inline';
        } else {
            syncContainer.style.display = 'none';
        }
    }

    // --- Network Status Handling ---
    function updateOnlineStatus() {
        const isOnline = navigator.onLine;
        if (isOnline) {
            onlineStatus.className = 'badge badge-success';
            onlineStatus.textContent = 'Online';
            syncBtn.disabled = false;
            syncOfflineScans(); // Attempt to sync when coming online
        } else {
            onlineStatus.className = 'badge badge-danger';
            onlineStatus.textContent = 'Offline';
            syncBtn.disabled = true;
        }
        updateSyncCount();
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    syncBtn.addEventListener('click', syncOfflineScans);

    // --- Core Scanning and Syncing Logic ---
    function processScan(decodedText) {
        const KEY_PREFIX = 'attendance-system-permanent-key-2023|';
        const PERMANENT_AES_KEY = 'attendance-system-permanent-key-2023';

        // Try to extract identifiers from QR
        let profileId = '';
        let studentId = '';
        let cadetName = '';

        // 1) Base64 permanent QR
        try {
            const decoded = atob(decodedText);
            if (decoded && decoded.startsWith(KEY_PREFIX)) {
                const json = decoded.substring(KEY_PREFIX.length);
                const obj = JSON.parse(json);
                profileId = (obj.profile_id || obj.cadet_profile_id || '').toString();
                studentId = (obj.student_id || obj.cadet_id || '').toString();
                cadetName = obj.name || (obj.first_name && obj.last_name ? `${obj.first_name} ${obj.last_name}` : '');
            }
        } catch (e) {
            // Not base64 permanent; continue
        }

        // 2) AES manual QR (fallback)
        if (!profileId && !studentId && window.CryptoJS) {
            try {
                const bytes = CryptoJS.AES.decrypt(decodedText, PERMANENT_AES_KEY);
                const decryptedText = bytes.toString(CryptoJS.enc.Utf8);
                if (decryptedText) {
                    const obj = JSON.parse(decryptedText);
                    profileId = (obj.profile_id || obj.cadet_profile_id || '').toString();
                    studentId = (obj.student_id || obj.cadet_id || '').toString();
                    cadetName = obj.name || (obj.first_name && obj.last_name ? `${obj.first_name} ${obj.last_name}` : '');
                }
            } catch (e) {}
        }

        // 3) Numeric fallback
        if (!profileId && !studentId && /^\d+$/.test(decodedText)) {
            profileId = decodedText;
        }

        const scanData = {
            cadet_id: decodedText, // raw for reference (not sent if we have parsed ids)
            student_id: studentId || '',
            profile_id: profileId || '',
            cadet_name: cadetName || '',
            event_name: eventNameInput.value.trim(),
            school_year: schoolYearSelect.value,
            semester: semesterSelect.value,
            timestamp: new Date().toISOString() // For local tracking
        };

        if (navigator.onLine) {
            sendScanToServer(scanData);
        } else {
            savePendingScan(scanData);
            const offlineData = {
                status: 'offline',
                message: 'Scan saved locally. Will sync when online.',
                short_message: 'Saved',
                cadet_name: 'Offline Scan'
            };
            let resultHtml = `<strong>Status:</strong> ${offlineData.message}`;
            scanResultDiv.innerHTML = resultHtml;
            updateScanHistory(offlineData);
        }
    }

    function sendScanToServer(scanData) {
        scanResultDiv.innerHTML = `<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>`;

        // Build unified API payload
        const payload = {
            action: 'record_attendance',
            school_year: scanData.school_year,
            semester: scanData.semester,
            event_name: scanData.event_name
        };
        if (scanData.profile_id) payload.profile_id = scanData.profile_id;
        if (scanData.student_id) payload.cadet_id = scanData.student_id;
        if (scanData.cadet_name) payload.cadet_name = scanData.cadet_name;

        return fetch('../../api/attendance_operations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            const uiData = {
                status: data.success ? 'success' : 'error',
                message: data.message || (data.success ? 'Attendance recorded successfully' : 'Error recording attendance'),
                short_message: data.success ? 'Recorded' : 'Error',
                cadet_name: (data.attendance && data.attendance.cadet_name) ? data.attendance.cadet_name : (scanData.cadet_name || 'Unknown')
            };
            let resultHtml = `<strong>Status:</strong> ${uiData.message}`;
            if (uiData.cadet_name) {
                resultHtml += `<br><strong>Cadet:</strong> ${uiData.cadet_name}`;
            }
            scanResultDiv.innerHTML = resultHtml;
            updateScanHistory(uiData);
            return !!data.success;
        })
        .catch(error => {
            console.error('Error sending scan:', error);
            scanResultDiv.textContent = 'Error processing scan. Check connection.';
            return false;
        });
    }

    async function syncOfflineScans() {
        if (!navigator.onLine) {
            alert('You are offline. Cannot sync.');
            return;
        }
        const pendingScans = getPendingScans();
        if (pendingScans.length === 0) {
            return;
        }

        scanResultDiv.innerHTML = `Syncing ${pendingScans.length} records...`;
        
        const syncedScans = [];
        for (const scan of pendingScans) {
            const success = await sendScanToServer(scan);
            if (success) {
                syncedScans.push(scan);
            } else {
                scanResultDiv.innerHTML = `Sync failed for one record. Please try again later.`;
                break; 
            }
        }

        const remainingScans = getPendingScans().filter(p => !syncedScans.some(s => s.timestamp === p.timestamp));
        localStorage.setItem(PENDING_SCANS_KEY, JSON.stringify(remainingScans));
        
        if (remainingScans.length === 0) {
            scanResultDiv.innerHTML = `Sync complete! All ${syncedScans.length} records uploaded.`;
        }
        
        updateSyncCount();
    }

    // --- UI Update Functions ---
    function updateScanHistory(data) {
        const listItem = document.createElement('li');
        let itemClass = 'list-group-item-info';
        if (data.status === 'success') itemClass = 'list-group-item-success';
        if (data.status === 'error') itemClass = 'list-group-item-danger';
        
        listItem.className = `list-group-item d-flex justify-content-between align-items-center ${itemClass}`;
        listItem.innerHTML = `
            <span>${data.cadet_name || 'Unknown'} - ${new Date().toLocaleTimeString()}</span>
            <span class="badge badge-primary badge-pill">${data.short_message || data.status}</span>
        `;
        
        scanHistoryUl.prepend(listItem);
        if (scanHistoryUl.children.length > 5) {
            scanHistoryUl.removeChild(scanHistoryUl.lastChild);
        }
    }

    const startButton = document.getElementById('startButton');

    const checkFormValidity = () => {
        const allFilled = schoolYearSelect.value && semesterSelect.value && eventNameInput.value.trim() !== '';
        startButton.disabled = !allFilled;
    };

    schoolYearSelect.addEventListener('change', checkFormValidity);
    semesterSelect.addEventListener('change', checkFormValidity);
    eventNameInput.addEventListener('input', checkFormValidity);

    startButton.addEventListener('click', function() {
        console.log('Start Scanner button clicked.');
        if (!scannerStarted) {
            console.log('Calling startScanner() function.');
            startScanner();
        }
    });

    function startScanner() {
    console.log('startScanner() function called - simplified version.');
    scannerStarted = true;
    scannerContainer.style.display = 'block';
    schoolYearSelect.disabled = true;
    semesterSelect.disabled = true;
    eventNameInput.disabled = true;

    html5QrCode = new Html5Qrcode("qr-reader");

    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    
    // The most basic start method. This should work on desktops.
    // We ask for the 'environment' (rear) camera.
    html5QrCode.start({ facingMode: "environment" }, config,
        (decodedText, decodedResult) => {
            document.getElementById('qr-reader').style.border = "5px solid green";
            processScan(decodedText);
            html5QrCode.pause();
            setTimeout(() => {
                document.getElementById('qr-reader').style.border = "none";
                html5QrCode.resume();
            }, 1500);
        },
        (errorMessage) => {
            // parse error, ideally ignore it.
        }
    ).catch((err) => {
        console.error(`Unable to start scanning, error: ${err}`);
        scannerContainer.innerHTML = `<div class="alert alert-danger"><strong>Camera Error:</strong> Could not start camera. Please grant permissions and refresh the page. Make sure you are using HTTPS.</div>`;
    });
}

    // --- Initial Load ---
    updateOnlineStatus();
});
</script>

<?php include '../includes/footer.php'; ?>