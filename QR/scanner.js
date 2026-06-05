// Global variables
let zxingCodeReader;
let scannerActive = false;
let videoElement;
let canvasElement;
let canvasContext;
let lastScannedId = null;
let scanCooldown = false;
let lastScanAt = 0; // ms timestamp for last processed scan
let isSubmitting = false; // prevent multiple in-flight submissions
let scanStats = {
    totalScanned: 0,
    successfulScans: 0,
    failedScans: 0
};

// ---- API base URL resolver (works from any subdirectory under '/generate%20qr/') ----
function getAppBasePath() {
    try {
        const path = window.location.pathname;
        const encodedBase = '/generate%20qr/';
        const decodedBase = '/generate qr/';
        const lower = path.toLowerCase();
        let idx = lower.indexOf(encodedBase);
        let baseToken = encodedBase;
        if (idx === -1) {
            idx = lower.indexOf(decodedBase);
            baseToken = decodedBase;
        }
        if (idx !== -1) {
            return path.substring(0, idx + baseToken.length);
        }
        const parts = path.split('/').filter(Boolean);
        return parts.length > 0 ? `/${parts[0]}/` : '/';
    } catch (e) {
        return '/';
    }
}

function buildApiUrl(file) {
    const base = getAppBasePath();
    const norm = base.endsWith('/') ? base : base + '/';
    return `${norm}api/${file}`;
}

const API_ATTENDANCE_URL = buildApiUrl('attendance_operations.php');

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Start Scanner button click handler
    document.getElementById('start-scanner-btn').addEventListener('click', startScanner);

    // Stop Scanner button click handler
    document.getElementById('stop-scanner-btn').addEventListener('click', stopScanner);
    
    // Set the secret key field to the permanent key and disable it
    const secretKeyInput = document.getElementById('secret-key');
    secretKeyInput.value = PERMANENT_ENCRYPTION_KEY;
    secretKeyInput.disabled = true;
    secretKeyInput.title = "This key is managed by the system for security";
    
    // Update camera status message
    updateCameraStatus('Camera will appear here when scanner is started');
    
    // Load saved session data
    loadSessionData();
    
    // Add event listener for dashboard link if it exists
    const dashboardLink = document.getElementById('dashboard-link');
    if (dashboardLink) {
        dashboardLink.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'dashboard.html';
        });
    }
});

/**
 * Loads saved session data from the server
 */
function loadSessionData() {
    fetch('session.php?action=get_session')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Set TD and semester from saved session
                if (data.td) {
                    document.getElementById('td').value = data.td;
                }
                if (data.semester) {
                    document.getElementById('semester').value = data.semester;
                }
                
                // Show session info
                updateSessionInfo(data.td, data.semester);
            }
        })
        .catch(error => {
            console.error('Error loading session data:', error);
        });
}

/**
 * Updates the session information display
 */
function updateSessionInfo(td, semester) {
    const sessionInfo = document.getElementById('session-info');
    if (td && semester) {
        const tdText = document.getElementById('td').options[document.getElementById('td').selectedIndex].text;
        const semText = document.getElementById('semester').options[document.getElementById('semester').selectedIndex].text;
        sessionInfo.textContent = `Active Session: ${tdText}, ${semText}`;
        sessionInfo.style.display = 'block';
    } else {
        sessionInfo.style.display = 'none';
    }
}

/**
 * Returns default school year string like '2025-2026'
 */
function getDefaultSchoolYear() {
    const y = new Date().getFullYear();
    return `${y}-${y + 1}`;
}

/**
 * Starts the QR code scanner
 */
function startScanner() {
    const td = document.getElementById('td').value;
    const semester = document.getElementById('semester').value;
    
    // Save session data
    saveSessionData(td, semester);
    
    // Update session info display
    updateSessionInfo(td, semester);
    
    updateCameraStatus('Initializing camera...');
    
    // Initialize ZXing code reader
    if (!zxingCodeReader) {
        zxingCodeReader = new ZXing.BrowserQRCodeReader();
    }
    
    // Get the reader container
    const readerElement = document.getElementById('reader');
    
    // Create video element for camera feed
    videoElement = document.createElement('video');
    videoElement.style.width = '100%';
    videoElement.style.height = '100%';
    videoElement.style.objectFit = 'cover';
    videoElement.autoplay = true;
    videoElement.muted = true;
    videoElement.playsInline = true;
    
    // Create canvas for overlay
    canvasElement = document.createElement('canvas');
    canvasElement.style.position = 'absolute';
    canvasElement.style.top = '0';
    canvasElement.style.left = '0';
    canvasElement.style.width = '100%';
    canvasElement.style.height = '100%';
    canvasElement.style.pointerEvents = 'none';
    canvasContext = canvasElement.getContext('2d');
    
    // Clear and setup reader container
    readerElement.innerHTML = '';
    readerElement.style.position = 'relative';
    readerElement.appendChild(videoElement);
    readerElement.appendChild(canvasElement);
    
    // Start camera
    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'environment',
            width: { ideal: 1280 },
            height: { ideal: 720 }
        }
    })
    .then(stream => {
        videoElement.srcObject = stream;
        
        videoElement.onloadedmetadata = () => {
            // Set canvas size to match video
            canvasElement.width = videoElement.videoWidth;
            canvasElement.height = videoElement.videoHeight;
            
            // Show stop button and hide start button
            document.getElementById('start-scanner-btn').style.display = 'none';
            document.getElementById('stop-scanner-btn').style.display = 'block';
            updateCameraStatus('Camera active - Point at a QR code');
            scannerActive = true;
            
            // Update scan stats display
            updateScanStats();
            
            // Show scanner controls
            document.getElementById('scanner-controls').style.display = 'block';
            
            // Start continuous scanning
            startContinuousScanning();
        };
    })
    .catch(err => {
        console.error(`Unable to start scanning: ${err}`);
        updateCameraStatus(`Error: ${err}. Make sure to allow camera access.`, 'error');
        alert(`Unable to start scanning. Please ensure you've granted camera permissions and are using HTTPS or localhost.`);
    });
}

/**
 * Saves the current session data to the server
 */
function saveSessionData(td, semester) {
    fetch('session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_session&td=${encodeURIComponent(td)}&semester=${encodeURIComponent(semester)}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Failed to save session data:', data.message);
        }
    })
    .catch(error => {
        console.error('Error saving session data:', error);
    });
}
    
    /**
 * Callback when QR code is successfully scanned
 */
async function onScanSuccess(decodedText, decodedResult) {
    // If we're in cooldown period, ignore this scan
    if (scanCooldown) {
        return;
    }
    // If a submission is already in progress, ignore new scans
    if (isSubmitting) {
        return;
    }
    // Immediately start cooldown to prevent rapid re-triggers
    setCooldown();
    isSubmitting = true;
    // Count this scan attempt now that it passed guard
    scanStats.totalScanned++;
    
    try {
        let studentData;
        
        // Check if this is a base64 encoded QR from batch generation
        try {
            const decoded = atob(decodedText);
            if (decoded.startsWith('attendance-system-permanent-key-2023|')) {
                // This is a batch-generated QR code
                const jsonData = decoded.substring('attendance-system-permanent-key-2023|'.length);
                studentData = JSON.parse(jsonData);
            } else {
                throw new Error('Not a batch-generated QR code');
            }
        } catch (e) {
            // Try CryptoJS decryption for manually generated QR codes
            const secretKey = document.getElementById('secret-key').value;
            const decryptedBytes = CryptoJS.AES.decrypt(decodedText, secretKey);
            const decryptedText = decryptedBytes.toString(CryptoJS.enc.Utf8);
            
            if (!decryptedText) {
                throw new Error('Decryption failed. Invalid QR code or wrong secret key.');
            }
            
            // Parse the decrypted JSON
            studentData = JSON.parse(decryptedText);
        }
            
            // Check if QR code is valid (not expired)
            const validUntil = new Date(studentData.valid_until);
            const now = new Date();
            
            if (validUntil < now) {
                showScanResult(`QR Code Expired! Valid until: ${studentData.valid_until}`, 'error');
                scanStats.failedScans++;
                updateScanStats();
                setCooldown();
                return;
            }
            
            // Check duplicate via unified API (attendance_records), limit to TODAY only
            const tdValDup = document.getElementById('td').value;
            const semValDup = document.getElementById('semester').value;
            const semesterStrDup = (semValDup === '1' || semValDup === '1st') ? '1st' : '2nd';
            const eventNameDup = `${tdValDup}TD`;
            const schoolYearDup = getDefaultSchoolYear();
            let duplicateIsToday = false;
            try {
                const dupResp = await fetch(API_ATTENDANCE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'check_attendance',
                        cadet_id: studentData.student_id,
                        event_name: eventNameDup,
                        school_year: schoolYearDup,
                        semester: semesterStrDup
                    })
                });
                const dupRaw = await dupResp.text();
                const dupText = dupRaw.trim().replace(/^\uFEFF/, '');
                let dupJson;
                try { dupJson = JSON.parse(dupText); } catch (e) { throw new Error(`Invalid JSON from check_attendance: ${dupText.substring(0,200)}`); }
                if (dupJson.success && dupJson.has_attendance && dupJson.attendance) {
                    // Treat as duplicate only if the found record is for today
                    const todayStr = new Date().toISOString().slice(0,10);
                    const eventDate = dupJson.attendance.event_date || (dupJson.attendance.recorded_at ? dupJson.attendance.recorded_at.slice(0,10) : null);
                    duplicateIsToday = (eventDate === todayStr);
                }
            } catch (dupErr) {
                console.warn('Unified duplicate check failed, falling back to legacy duplicate check:', dupErr);
                try {
                    const legacyDupResp = await fetch('session.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=check_duplicate&student_id=${encodeURIComponent(studentData.student_id)}&td=${encodeURIComponent(tdValDup)}&semester=${encodeURIComponent(semValDup)}`
                    });
                    const legacyDup = await legacyDupResp.json();
                    duplicateIsToday = !!(legacyDup.success && (legacyDup.is_duplicate || legacyDup.already_marked));
                } catch (legacyErr) {
                    console.warn('Legacy duplicate check also failed; proceeding without pre-check.', legacyErr);
                }
            }
            if (duplicateIsToday) {
                showScanResult(`${studentData.name} (${studentData.student_id}) already has attendance for this Training Day and Semester today!`, 'warning');
                scanStats.failedScans++;
                updateScanStats();
                setCooldown();
                return;
            }
            
            // If same student scanned within last 3s, ignore (extra safety)
            const nowMs = Date.now();
            if (lastScannedId === studentData.student_id && (nowMs - lastScanAt) < 3000) {
                showScanResult(`Duplicate scan ignored for ${studentData.student_id}`, 'warning');
                scanStats.failedScans++;
                updateScanStats();
                return;
            }
            // Track last scan identity/time
            lastScannedId = studentData.student_id;
            lastScanAt = nowMs;
            
            // Display the result
            const resultHTML = `
                <h3>Attendance Recorded!</h3>
                <p><strong>Student ID:</strong> ${studentData.student_id}</p>
                <p><strong>Name:</strong> ${studentData.name}</p>
                <p><strong>TD:</strong> ${document.getElementById('td').options[document.getElementById('td').selectedIndex].text}</p>
                <p><strong>Semester:</strong> ${document.getElementById('semester').options[document.getElementById('semester').selectedIndex].text}</p>
                <p><strong>Time:</strong> ${new Date().toLocaleString()}</p>
            `;
            
            showScanResult(resultHTML, 'success');
            
            // Increment successful scan count
            scanStats.successfulScans++;
            updateScanStats();
            
            // Send attendance data to the server (Unified API first, fallback to legacy if needed)
            const tdVal = document.getElementById('td').value;
            const semVal = document.getElementById('semester').value;
            const semesterStr = (semVal === '1' || semVal === '1st') ? '1st' : '2nd';
            const eventName = `${tdVal}TD`;
            const schoolYear = getDefaultSchoolYear();

            const unifiedPayload = {
                action: 'record_attendance',
                cadet_id: studentData.student_id,
                cadet_name: studentData.name,
                school_year: schoolYear,
                semester: semesterStr,
                event_name: eventName
            };

            console.log('Posting to unified API:', API_ATTENDANCE_URL, unifiedPayload);
            return fetch(API_ATTENDANCE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(unifiedPayload)
            })
            .then(async response => {
                const raw = await response.text();
                const text = raw.trim().replace(/^\uFEFF/, '');
                let data;
                try { data = JSON.parse(text); } catch (e) { throw new Error(`Invalid JSON from unified API: ${text.substring(0,200)}`); }
                if (!response.ok || !data.success) {
                    throw new Error(data && data.message ? data.message : `HTTP ${response.status}`);
                }
                console.log('Unified API success:', data);
                // If API returns stats/recent, update UI
                if (data.stats) updateAttendanceStats(data.stats);
                if (data.recent_records) updateRecentRecords(data.recent_records);
            })
            .catch(async (err) => {
                console.warn('Unified API failed, falling back to legacy endpoint:', err);
                const legacyPayload = {
                    student_id: studentData.student_id,
                    name: studentData.name,
                    td: tdVal,
                    semester: semVal,
                    timestamp: new Date().toISOString()
                };
                try {
                    const resp = await fetch('record_attendance.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(legacyPayload)
                    });
                    const raw = await resp.text();
                    const clean = raw.trim().replace(/^\uFEFF/, '');
                    const data = JSON.parse(clean);
                    console.log('Legacy endpoint response:', data);
                    if (data.success) {
                        if (data.stats) updateAttendanceStats(data.stats);
                        if (data.recent_records) updateRecentRecords(data.recent_records);
                        return;
                    }
                    throw new Error(data.message || 'Legacy endpoint error');
                } catch (fallbackErr) {
                    console.error('Both unified and legacy attendance calls failed:', fallbackErr);
                    showScanResult('Network error: failed to save attendance. Please check connection and try again.', 'error');
                }
            });
            // cooldown was already set at function start
            
        } catch (error) {
            console.error('Error processing QR code:', error);
            showScanResult('Invalid QR Code! Make sure you are using a QR code generated by this system and the correct secret key.', 'error');
            scanStats.failedScans++;
            updateScanStats();
            setCooldown();
        }
        finally {
            // Allow new submissions after a short delay
            setTimeout(() => { isSubmitting = false; }, 800);
        }
    }
    
    /**
     * Sets a cooldown period to prevent rapid scanning
     */
    function setCooldown() {
        scanCooldown = true;
        setTimeout(() => {
            scanCooldown = false;
        }, 3000); // 3 second cooldown
    }
    
    /**
     * Updates the scan statistics display
     */
    function updateScanStats() {
        const statsElement = document.getElementById('scan-stats');
        if (statsElement) {
            statsElement.innerHTML = `
                <p><strong>Total Scans:</strong> ${scanStats.totalScanned}</p>
                <p><strong>Successful:</strong> ${scanStats.successfulScans}</p>
                <p><strong>Failed:</strong> ${scanStats.failedScans}</p>
            `;
        }
    }
    
    /**
     * Updates the attendance statistics display
     */
    function updateAttendanceStats(stats) {
        const statsElement = document.getElementById('attendance-stats');
        if (statsElement) {
            statsElement.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-value">${stats.total.present}</span>
                        <span class="stat-label">Present</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">${stats.total.absent}</span>
                        <span class="stat-label">Absent</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">${stats.total.strength}</span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">${stats.total.percentage}%</span>
                        <span class="stat-label">Rate</span>
                    </div>
                </div>
            `;
        }
    }
    
    /**
     * Updates the recent records display
     */
    function updateRecentRecords(records) {
        const recentElement = document.getElementById('recent-records');
        if (recentElement && records.length > 0) {
            let html = '<h3>Recent Records</h3><ul class="recent-list">';
            
            records.forEach(record => {
                const time = new Date(record.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                html += `<li>${time} - ${record.name} (${record.student_id})</li>`;
            });
            
            html += '</ul>';
            recentElement.innerHTML = html;
            recentElement.style.display = 'block';
        }
    }
    
    /**
     * Callback when QR code scanning fails
     */
    function onScanFailure(error) {
        // We don't need to do anything here as this is called frequently when no QR code is detected
        console.log(`QR Code scanning failure: ${error}`);
    }


/**
 * Stops the QR code scanner
 */
function stopScanner() {
    if (videoElement && videoElement.srcObject) {
        // Stop all video tracks
        const stream = videoElement.srcObject;
        const tracks = stream.getTracks();
        tracks.forEach(track => track.stop());
        
        // Clear video source
        videoElement.srcObject = null;
        
        // Clear the reader container
        const readerElement = document.getElementById('reader');
        readerElement.innerHTML = '';
        
        // Show start button and hide stop button
        document.getElementById('start-scanner-btn').style.display = 'block';
        document.getElementById('stop-scanner-btn').style.display = 'none';
        updateCameraStatus('Scanner stopped. Click "Start Scanner" to scan again.');
        scannerActive = false;
        
        // Hide scanner controls
        document.getElementById('scanner-controls').style.display = 'none';
    }
}

/**
 * Resets the scanner session
 */
function resetSession() {
    // Reset scan statistics
    scanStats = {
        totalScanned: 0,
        successfulScans: 0,
        failedScans: 0
    };
    
    // Reset last scanned ID
    lastScannedId = null;
    
    // Update displays
    updateScanStats();
    
    // Clear results
    document.getElementById('scan-result').style.display = 'none';
    document.getElementById('recent-records').innerHTML = '';
    document.getElementById('recent-records').style.display = 'none';
    
    // If scanner is active, restart it
    if (scannerActive) {
        stopScanner();
        setTimeout(() => {
            startScanner();
        }, 500);
    }
}

/**
 * Displays the scan result with appropriate styling
 */
function showScanResult(message, type) {
    const resultElement = document.getElementById('scan-result');
    resultElement.innerHTML = message;
    resultElement.style.display = 'block';
    
    // Apply different styling based on result type
    if (type === 'error') {
        resultElement.style.backgroundColor = '#ffebee';
        resultElement.style.color = '#c62828';
        resultElement.style.border = '1px solid #ef9a9a';
    } else if (type === 'warning') {
        resultElement.style.backgroundColor = '#e8f5e9';
        resultElement.style.color = '#2e7d32';
        resultElement.style.border = '1px solid #a5d6a7';
    } else {
        resultElement.style.backgroundColor = '#e8f5e9';
        resultElement.style.color = '#2e7d32';
        resultElement.style.border = '1px solid #a5d6a7';
    }
    
    // Auto-hide the result after 5 seconds if scanner is active
    if (scannerActive) {
        setTimeout(() => {
            resultElement.style.display = 'none';
        }, 5000);
    }
}

/**
 * Updates the camera status message
 */
function updateCameraStatus(message, type = 'info') {
    const statusElement = document.getElementById('camera-status');
    statusElement.textContent = message;
    
    // Reset styles
    statusElement.style.color = '#666';
    
    // Apply different styling based on message type
    if (type === 'error') {
        statusElement.style.color = '#c62828';
    } else if (type === 'success') {
        statusElement.style.color = '#2e7d32';
    }
}

/**
 * Starts continuous scanning using ZXing
 */
function startContinuousScanning() {
    if (!scannerActive || !videoElement || !canvasElement || !zxingCodeReader) {
        return;
    }
    
    const scan = () => {
        if (!scannerActive) {
            return;
        }
        
        try {
            // Draw current video frame to canvas
            canvasContext.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
            
            // Draw scanning overlay
            drawScanningOverlay();
            
            // Throttle decode attempts to at most ~2 per second
            const now = Date.now();
            if (now - lastScanAt < 500) {
                requestAnimationFrame(scan);
                return;
            }
            lastScanAt = now;
            
            // Get image data from canvas
            const imageData = canvasContext.getImageData(0, 0, canvasElement.width, canvasElement.height);
            
            // Try to decode QR code
            zxingCodeReader.decodeFromImageData(imageData)
                .then(result => {
                    if (result && result.text) {
                        onScanSuccess(result.text, result);
                    }
                })
                .catch(err => {
                    // No QR code found, continue scanning
                    onScanFailure(err.message || 'No QR code detected');
                });
        } catch (error) {
            console.error('Scanning error:', error);
        }
        
        // Continue scanning
        requestAnimationFrame(scan);
    };
    
    // Start the scanning loop
    requestAnimationFrame(scan);
}

/**
 * Draws scanning overlay on canvas
 */
function drawScanningOverlay() {
    const width = canvasElement.width;
    const height = canvasElement.height;
    
    // Calculate scanning area (centered square)
    const size = Math.min(width, height) * 0.6;
    const x = (width - size) / 2;
    const y = (height - size) / 2;
    
    // Clear previous overlay
    canvasContext.clearRect(0, 0, width, height);
    
    // Redraw video frame
    canvasContext.drawImage(videoElement, 0, 0, width, height);
    
    // Draw semi-transparent overlay
    canvasContext.fillStyle = 'rgba(0, 0, 0, 0.5)';
    canvasContext.fillRect(0, 0, width, height);
    
    // Clear scanning area
    canvasContext.clearRect(x, y, size, size);
    
    // Draw scanning border
    canvasContext.strokeStyle = '#ffffff';
    canvasContext.lineWidth = 2;
    canvasContext.strokeRect(x, y, size, size);
    
    // Draw corner indicators
    const cornerLength = 20;
    canvasContext.strokeStyle = '#00ff00';
    canvasContext.lineWidth = 4;
    
    // Top-left corner
    canvasContext.beginPath();
    canvasContext.moveTo(x, y + cornerLength);
    canvasContext.lineTo(x, y);
    canvasContext.lineTo(x + cornerLength, y);
    canvasContext.stroke();
    
    // Top-right corner
    canvasContext.beginPath();
    canvasContext.moveTo(x + size - cornerLength, y);
    canvasContext.lineTo(x + size, y);
    canvasContext.lineTo(x + size, y + cornerLength);
    canvasContext.stroke();
    
    // Bottom-left corner
    canvasContext.beginPath();
    canvasContext.moveTo(x, y + size - cornerLength);
    canvasContext.lineTo(x, y + size);
    canvasContext.lineTo(x + cornerLength, y + size);
    canvasContext.stroke();
    
    // Bottom-right corner
    canvasContext.beginPath();
    canvasContext.moveTo(x + size - cornerLength, y + size);
    canvasContext.lineTo(x + size, y + size);
    canvasContext.lineTo(x + size, y + size - cornerLength);
    canvasContext.stroke();
}