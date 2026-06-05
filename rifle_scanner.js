// Global variables
let html5QrCode = null;
let scannerActive = false;
let cameraId = null;
let currentOperation = 'attendance'; // 'attendance', 'assign' or 'return'
let lastScannedCode = null;
let scanCooldown = false;
let isSubmitting = false; // prevent overlapping scan handling
let scanStats = {
    totalScanned: 0,
    successfulScans: 0,
    failedScans: 0
};

// Debug panel state
let debugPanelVisible = false;
let currentDebugInfo = null;

// Encryption keys for QR codes
const RIFLE_ENCRYPTION_KEY = 'rifle-management-system-key-2024'; // For rifle QR codes
const PERMANENT_ENCRYPTION_KEY = 'attendance-system-permanent-key-2023'; // For cadet QR codes from attendance system

// ---- API base URL resolver (works from any subdirectory under '/generate%20qr/') ----
function getAppBasePath() {
    try {
        const path = window.location.pathname;
        const encodedBase = '/generate%20qr/';
        const decodedBase = '/generate qr/';
        const lower = path.toLowerCase();
        let idx = lower.indexOf(encodedBase);
        if (idx !== -1) {
            // Already encoded base in path
            const base = path.substring(0, idx + encodedBase.length);
            return base.replace(/ /g, '%20');
        }
        idx = lower.indexOf(decodedBase);
        if (idx !== -1) {
            // Found unencoded base; normalize to encoded
            const base = path.substring(0, idx + decodedBase.length);
            return base.replace(/ /g, '%20');
        }
        // Fallback: assume current path root and normalize spaces
        const parts = path.split('/').filter(Boolean);
        const guess = parts.length > 0 ? `/${parts[0]}/` : '/';
        return guess.replace(/ /g, '%20');
    } catch (e) {
        return '/';
    }
}

function buildApiUrl(file) {
    // Always anchor to the app base '/generate%20qr/' regardless of current subdirectory
    const base = getAppBasePath();
    const norm = base.endsWith('/') ? base : base + '/';
    const origin = (window.location && window.location.origin)
        ? window.location.origin
        : (window.location.protocol + '//' + window.location.host);
    // Ensure encoded spaces and remove any accidental whitespace
    const safeBase = norm.replace(/\s/g, '%20');
    const candidate = `${origin}${safeBase}api/${file}`;
    try {
        return new URL(candidate).toString();
    } catch (e) {
        // Final fallback to root-anchored path
        return `/generate%20qr/api/${file}`;
    }
}

const API_ATTENDANCE_URL = buildApiUrl('attendance_operations.php');
const API_RIFLE_URL = buildApiUrl('rifle_operations.php');
// One-time debug log of API endpoints (helps diagnose URL format issues)
console.log('[Scanner] API URLs:', { API_ATTENDANCE_URL, API_RIFLE_URL });

// New workflow state management
let assignmentWorkflow = {
    step: 'idle', // 'idle', 'waiting_for_cadet', 'waiting_for_rifle'
    cadetData: null,
    rifleData: null
};

// Track scanned items to prevent duplicate scanning in the same session
let scannedItems = {
    rifles: new Set(), // Track rifle IDs that have been successfully scanned
    cadets: new Set()  // Track cadet IDs that have been scanned
};

// Attendance-specific variables
let attendanceData = {
    schoolYear: '',
    semester: '',
    eventName: ''
};

// Attendance defaults & persistence
const ATT_KEYS = {
    sy: 'attendance.schoolYear',
    sem: 'attendance.semester',
    td: 'attendance.trainingDay',
    rollover: 'attendance.rolloverAfter15TD'
};

function updateSessionInfoUI() {
    const el = document.getElementById('session-info');
    if (!el) return;
    const sy = document.getElementById('school-year')?.value || attendanceData.schoolYear || '';
    const sem = document.getElementById('semester')?.value || attendanceData.semester || '';
    const customTD = (document.getElementById('event-name-custom')?.value || '').trim();
    const td = customTD || (document.getElementById('event-name')?.value || attendanceData.eventName || '');
    el.innerHTML = `
        <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
            <div style="background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: 8px; padding: 6px 10px;"><strong>School Year:</strong> ${sy || '—'}</div>
            <div style="background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: 8px; padding: 6px 10px;"><strong>Semester:</strong> ${sem || '—'}</div>
            <div style="background: var(--bg-secondary); border: 1px solid var(--border-primary); border-radius: 8px; padding: 6px 10px;"><strong>Training Day:</strong> ${td || '—'}</div>
        </div>
    `;
}

function getDefaultSchoolYear() {
    const y = new Date().getFullYear();
    return `${y}-${y + 1}`;
}

function applyAttendanceDefaultsFromStorage() {
    const sySelect = document.getElementById('school-year');
    const semSelect = document.getElementById('semester');
    const tdSelect = document.getElementById('event-name');

    // Load stored values or set sensible defaults
    let sy = localStorage.getItem(ATT_KEYS.sy) || getDefaultSchoolYear();
    let sem = localStorage.getItem(ATT_KEYS.sem) || '1st';
    let td = localStorage.getItem(ATT_KEYS.td) || '1TD';

    // If we prepared rollover after 15TD in 1st sem, apply it once on next load
    const rollover = localStorage.getItem(ATT_KEYS.rollover) === 'true';
    if (rollover && sem === '1st') {
        sem = '2nd';
        td = '1TD';
        localStorage.removeItem(ATT_KEYS.rollover);
    }

    if (sySelect) sySelect.value = sy;
    if (semSelect) semSelect.value = sem;
    if (tdSelect) tdSelect.value = td;

    // Sync global state
    attendanceData.schoolYear = sy;
    attendanceData.semester = sem;
    attendanceData.eventName = td;
    updateSessionInfoUI();
}

function saveAttendanceFormState() {
    const sy = document.getElementById('school-year')?.value || getDefaultSchoolYear();
    const sem = document.getElementById('semester')?.value || '1st';
    const td = document.getElementById('event-name')?.value || '1TD';
    localStorage.setItem(ATT_KEYS.sy, sy);
    localStorage.setItem(ATT_KEYS.sem, sem);
    localStorage.setItem(ATT_KEYS.td, td);
    attendanceData.schoolYear = sy;
    attendanceData.semester = sem;
    attendanceData.eventName = td;
    updateSessionInfoUI();
}

function installAttendanceFormListeners() {
    const sySelect = document.getElementById('school-year');
    const semSelect = document.getElementById('semester');
    const tdSelect = document.getElementById('event-name');
    const customInput = document.getElementById('event-name-custom');

    sySelect && sySelect.addEventListener('change', saveAttendanceFormState);
    semSelect && semSelect.addEventListener('change', saveAttendanceFormState);
    tdSelect && tdSelect.addEventListener('change', () => {
        // If user picks 15TD in 1st sem, prepare rollover for next default
        const sem = document.getElementById('semester')?.value || '1st';
        const td = tdSelect.value;
        if (td === '15TD' && sem === '1st') {
            localStorage.setItem(ATT_KEYS.rollover, 'true');
        }
        saveAttendanceFormState();
    });
    customInput && customInput.addEventListener('input', () => {
        // Custom event doesn't persist as TD; leave stored TD unchanged
        // But keep attendanceData.eventName current for scanning
        attendanceData.eventName = (customInput.value || '').trim() || (document.getElementById('event-name')?.value || '');
        updateSessionInfoUI();
    });
}

// Debug Panel Management Functions
/**
 * Updates the visual debug panel with current debug information
 */
function updateDebugPanel(debugInfo) {
    currentDebugInfo = debugInfo;
    
    const debugPanel = document.getElementById('debug-panel');
    if (!debugPanel) return;
    
    // Show debug panel if there's debug info and it's not explicitly hidden
    if (debugInfo && !debugPanelVisible) {
        showDebugPanel();
    }
    
    // Update raw data
    const rawDataElement = document.getElementById('debug-raw-data');
    if (rawDataElement && debugInfo.rawData) {
        rawDataElement.textContent = debugInfo.rawData.length > 200 
            ? debugInfo.rawData.substring(0, 200) + '...' 
            : debugInfo.rawData;
    }
    
    // Update workflow state
    const workflowElement = document.getElementById('debug-workflow');
    if (workflowElement) {
        workflowElement.innerHTML = `
            <div><strong>Operation:</strong> ${debugInfo.operation || 'Unknown'}</div>
            <div><strong>Step:</strong> ${debugInfo.workflowStep || 'Unknown'}</div>
            <div><strong>Timestamp:</strong> ${debugInfo.timestamp || 'Unknown'}</div>
        `;
    }
    
    // Update decryption attempts
    const decryptionElement = document.getElementById('debug-decryption');
    if (decryptionElement && debugInfo.decryptionAttempts) {
        let html = '';
        debugInfo.decryptionAttempts.forEach((attempt, index) => {
            const statusColor = attempt.status === 'success' ? '#4ecdc4' : 
                               attempt.status === 'failed' ? '#ff6b6b' : '#f9ca24';
            html += `
                <div style="margin-bottom: 8px; padding: 4px; border-left: 3px solid ${statusColor};">
                    <div><strong>Method:</strong> ${attempt.method}</div>
                    <div><strong>Key:</strong> ${attempt.key}</div>
                    <div><strong>Status:</strong> <span style="color: ${statusColor}">${attempt.status}</span></div>
                    ${attempt.error ? `<div><strong>Error:</strong> ${attempt.error}</div>` : ''}
                    ${attempt.result ? `<div><strong>Result:</strong> ${JSON.stringify(attempt.result, null, 2)}</div>` : ''}
                </div>
            `;
        });
        decryptionElement.innerHTML = html || 'No decryption attempts';
    }
    
    // Update validation errors
    const validationElement = document.getElementById('debug-validation');
    if (validationElement) {
        if (debugInfo.validationErrors && debugInfo.validationErrors.length > 0) {
            let html = '';
            debugInfo.validationErrors.forEach(error => {
                html += `<div style="color: #ff6b6b; margin-bottom: 4px;">• ${error}</div>`;
            });
            validationElement.innerHTML = html;
        } else if (debugInfo.finalResult) {
            validationElement.innerHTML = `<div style="color: #4ecdc4;">✓ Data validation passed</div>
                <div style="margin-top: 4px;"><strong>Final Result:</strong><br><pre style="font-size: 10px; margin: 4px 0;">${JSON.stringify(debugInfo.finalResult, null, 2)}</pre></div>`;
        } else {
            validationElement.innerHTML = 'No validation performed';
        }
    }
    
    // Update error details
    const errorsElement = document.getElementById('debug-errors');
    if (errorsElement) {
        if (debugInfo.finalError) {
            errorsElement.innerHTML = `
                <div style="color: #ff6b6b; font-weight: bold;">${debugInfo.finalError}</div>
                ${debugInfo.finalErrorDetails ? `<div style="margin-top: 4px; color: #ffb3b3;">${debugInfo.finalErrorDetails}</div>` : ''}
                ${debugInfo.suggestions ? `<div style="margin-top: 8px;"><strong>Suggestions:</strong><ul style="margin: 4px 0; padding-left: 16px;">${debugInfo.suggestions.map(s => `<li>${s}</li>`).join('')}</ul></div>` : ''}
            `;
        } else {
            errorsElement.innerHTML = '<div style="color: #4ecdc4;">No errors</div>';
        }
    }
}

/**
 * Shows the debug panel
 */
function showDebugPanel() {
    const debugPanel = document.getElementById('debug-panel');
    if (debugPanel) {
        debugPanel.style.display = 'block';
        debugPanel.style.visibility = 'visible';
        debugPanel.style.opacity = '1';
        debugPanelVisible = true;
        
        // Force mobile visibility
        if (window.innerWidth <= 768) {
            debugPanel.style.position = 'relative';
            debugPanel.style.zIndex = '9999';
            debugPanel.style.transform = 'none';
        }
        
        const toggleBtn = document.getElementById('toggle-debug');
        if (toggleBtn) {
            toggleBtn.textContent = 'Hide Debug';
            toggleBtn.style.backgroundColor = '#ff6b6b';
        }
        
        // Scroll to debug panel on mobile
        if (window.innerWidth <= 768) {
            setTimeout(() => {
                debugPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }
}

/**
 * Hides the debug panel
 */
function hideDebugPanel() {
    const debugPanel = document.getElementById('debug-panel');
    if (debugPanel) {
        debugPanel.style.display = 'none';
        debugPanel.style.visibility = 'hidden';
        debugPanel.style.opacity = '0';
        debugPanelVisible = false;
        
        const toggleBtn = document.getElementById('toggle-debug');
        if (toggleBtn) {
            toggleBtn.textContent = 'Show Debug';
            toggleBtn.style.backgroundColor = '#4ecdc4';
        }
    }
}

/**
 * Toggles the debug panel visibility
 */
function toggleDebugPanel() {
    console.log('Debug panel toggle clicked. Current state:', debugPanelVisible);
    
    if (debugPanelVisible) {
        hideDebugPanel();
    } else {
        showDebugPanel();
    }
    
    // Force refresh on mobile to ensure visibility
    if (window.innerWidth <= 768) {
        setTimeout(() => {
            const debugPanel = document.getElementById('debug-panel');
            if (debugPanel && debugPanelVisible) {
                debugPanel.style.display = 'block';
                debugPanel.style.visibility = 'visible';
            }
        }, 50);
    }
}

/**
 * Clears the debug panel
 */
function clearDebugPanel() {
    const debugInfo = {
        rawData: 'No data',
        workflowStep: 'Idle',
        operation: currentOperation,
        timestamp: new Date().toISOString(),
        decryptionAttempts: [],
        validationErrors: [],
        finalResult: null
    };
    updateDebugPanel(debugInfo);
}

/**
 * Initializes mobile-specific debug panel functionality
 */
function initializeMobileDebugPanel() {
    const debugPanel = document.getElementById('debug-panel');
    const toggleBtn = document.getElementById('toggle-debug');
    
    if (!debugPanel || !toggleBtn) {
        console.warn('Debug panel or toggle button not found during mobile initialization');
        return;
    }
    
    // Mobile-specific styling and behavior
    if (window.innerWidth <= 768) {
        console.log('Initializing mobile debug panel');
        
        // Ensure debug panel is properly styled for mobile
        debugPanel.style.position = 'relative';
        debugPanel.style.width = '100%';
        debugPanel.style.maxWidth = 'none';
        debugPanel.style.margin = '10px 0';
        debugPanel.style.zIndex = '9999';
        
        // Make toggle button more prominent on mobile
        toggleBtn.style.width = '100%';
        toggleBtn.style.padding = '12px';
        toggleBtn.style.fontSize = '16px';
        toggleBtn.style.fontWeight = 'bold';
        toggleBtn.style.border = '2px solid #4ecdc4';
        toggleBtn.style.borderRadius = '8px';
        toggleBtn.style.cursor = 'pointer';
        toggleBtn.style.touchAction = 'manipulation';
        
        // Add visual feedback for mobile interactions
        toggleBtn.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.95)';
        });
        
        toggleBtn.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
        
        // Force visibility check
        setTimeout(() => {
            console.log('Debug panel mobile initialization complete');
            console.log('Debug panel display:', debugPanel.style.display);
            console.log('Toggle button display:', toggleBtn.style.display);
        }, 100);
    }
    
    // Add cache-busting for mobile browsers
    if (navigator.userAgent.match(/Mobile|Android|iPhone|iPad/)) {
        const timestamp = new Date().getTime();
        debugPanel.setAttribute('data-mobile-init', timestamp);
        console.log('Mobile debug panel cache-busting timestamp:', timestamp);
    }
}

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the scanner interface
    initializeScanner();
    
    // Load recent activities
    loadRecentActivities();
    
    // Load current assignments
    loadCurrentAssignments();
    
    // Update scan stats display
    updateScanStats();
    
    // Setup debug panel toggle with mobile-specific handling
    const toggleDebugBtn = document.getElementById('toggle-debug');
    if (toggleDebugBtn) {
        // Remove any existing listeners
        toggleDebugBtn.removeEventListener('click', toggleDebugPanel);
        
        // Add click listener with mobile optimization
        toggleDebugBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Debug toggle button clicked');
            toggleDebugPanel();
        });
        
        // Add touch event for better mobile responsiveness
        toggleDebugBtn.addEventListener('touchstart', function(e) {
            e.preventDefault();
            console.log('Debug toggle button touched');
            toggleDebugPanel();
        });
        
        // Ensure button is visible and clickable on mobile
        if (window.innerWidth <= 768) {
            toggleDebugBtn.style.display = 'block';
            toggleDebugBtn.style.visibility = 'visible';
            toggleDebugBtn.style.position = 'relative';
            toggleDebugBtn.style.zIndex = '10000';
            toggleDebugBtn.style.minHeight = '44px';
            toggleDebugBtn.style.fontSize = '16px';
        }
    }
    
    // Initialize debug panel
    clearDebugPanel();
    
    // Mobile-specific debug panel initialization
    initializeMobileDebugPanel();
    
    // Add window resize handler for responsive debug panel
    window.addEventListener('resize', function() {
        if (debugPanelVisible) {
            const debugPanel = document.getElementById('debug-panel');
            if (debugPanel && window.innerWidth <= 768) {
                debugPanel.style.position = 'relative';
                debugPanel.style.zIndex = '9999';
                debugPanel.style.transform = 'none';
            }
        }
    });
});

/**
 * Initializes the scanner interface
 */
function initializeScanner() {
    // Update camera status message
    updateCameraStatus('Camera will appear here when scanner is started');
    
    // Set default operation
    selectOperation('attendance');
    
    // Apply attendance defaults and wire persistence
    applyAttendanceDefaultsFromStorage();
    installAttendanceFormListeners();
}

/**
 * Selects the operation type (attendance, assign or return)
 */
function selectOperation(operation) {
    currentOperation = operation;
    
    // Reset workflow state when changing operations
    assignmentWorkflow = {
        step: 'idle',
        cadetData: null,
        rifleData: null
    };
    
    // Update button states
    document.querySelectorAll('.operation-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.querySelector(`[data-operation="${operation}"]`).classList.add('active');
    
    // Show/hide relevant form sections
    const secretKeyGroup = document.getElementById('secret-key-group');
    const attendanceForm = document.getElementById('attendance-form');
    
    if (operation === 'attendance') {
        if (secretKeyGroup) secretKeyGroup.style.display = 'none';
        if (attendanceForm) attendanceForm.style.display = 'block';
    } else {
        if (secretKeyGroup) secretKeyGroup.style.display = 'block';
        if (attendanceForm) attendanceForm.style.display = 'none';
    }
    
    // Update UI based on operation
    let title, instruction;
    switch(operation) {
        case 'attendance':
            title = 'Attendance Scanner';
            instruction = 'Scan a cadet QR code to record attendance';
            break;
        case 'assign':
            title = 'Assign Rifle';
            instruction = 'Scan a cadet QR code first to start assignment';
            break;
        case 'return':
            title = 'Return Rifle';
            instruction = 'Scan a cadet QR code to return their assigned rifle';
            break;
        default:
            title = 'QR Scanner';
            instruction = 'Select an operation mode';
    }
    
    updateCameraStatus(instruction);
}

/**
 * Checks if the current connection is secure (HTTPS or localhost)
 */
function isSecureContext() {
    return window.isSecureContext || 
           location.protocol === 'https:' || 
           location.hostname === 'localhost' || 
           location.hostname === '127.0.0.1' ||
           location.hostname.endsWith('.local');
}

/**
 * Detects if the user is on a mobile device
 */
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
           (navigator.maxTouchPoints && navigator.maxTouchPoints > 2 && /MacIntel/.test(navigator.platform));
}

/**
 * Gets available camera devices with enhanced mobile support
 */
async function getCameraDevices() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(device => device.kind === 'videoinput');
        console.log('📱 Available camera devices:', videoDevices.length);
        videoDevices.forEach((device, index) => {
            console.log(`   ${index + 1}. ${device.label || 'Camera ' + (index + 1)} (${device.deviceId.substring(0, 8)}...)`);
        });
        return videoDevices;
    } catch (error) {
        console.error('❌ Error enumerating camera devices:', error);
        return [];
    }
}

/**
 * Requests camera permissions with enhanced mobile support
 */
async function requestCameraPermission() {
    try {
        console.log('🔐 Requesting camera permission...');
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            } 
        });
        
        // Stop the stream immediately - we just needed permission
        stream.getTracks().forEach(track => track.stop());
        console.log('✅ Camera permission granted');
        return true;
    } catch (error) {
        console.error('❌ Camera permission denied:', error);
        return false;
    }
}

/**
 * Starts the QR code scanner with HTML5-QRCode
 */
async function startScanner() {
    if (scannerActive) {
        console.log('Scanner already active');
        return;
    }
    
    console.log('🚀 Starting HTML5-QRCode scanner...');
    console.log('📱 Mobile device detected:', isMobileDevice());
    console.log('🔒 Secure context:', isSecureContext());
    console.log('📊 Scanner state before start:', {
        scannerActive,
        currentOperation,
        html5QrCode: !!html5QrCode,
        cameraId
    });
    
    // Reset workflow state when starting scanner
    if (currentOperation === 'assign') {
        assignmentWorkflow = {
            step: 'idle',
            cadetData: null,
            rifleData: null
        };
        
        // Update mode display
        const modeDisplay = document.getElementById('scannerMode');
        if (modeDisplay) {
            modeDisplay.textContent = 'Assign Mode - Scan Cadet QR First';
        }
    }
    
    // Enhanced security check for mobile devices
    if (!isSecureContext()) {
        const errorMsg = 'Camera access requires HTTPS. Please use a secure connection.';
        console.error('❌ ' + errorMsg);
        updateCameraStatus(errorMsg, 'error');
        
        if (isMobileDevice()) {
            alert('📱 Mobile Camera Access Required\n\n' +
                  'This app needs HTTPS to access your camera on mobile devices.\n\n' +
                  'Please ensure you\'re accessing this page through a secure connection (https://) ' +
                  'or contact your administrator for proper SSL setup.');
        } else {
            alert(errorMsg);
        }
        return;
    }
    
    updateCameraStatus('Initializing HTML5-QRCode scanner...');
    
    try {
        // Initialize HTML5-QRCode scanner
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode("reader");
            console.log('✅ HTML5-QRCode scanner initialized');
        }
        
        // Get available cameras
        const cameras = await Html5Qrcode.getCameras();
        console.log('📷 Available cameras:', cameras);
        
        // Use back camera if available, otherwise use first camera
        cameraId = cameras.length > 0 ? cameras[cameras.length - 1].id : cameras[0].id;
        console.log('📹 Selected camera ID:', cameraId);
        
        // Configure scanner
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };
        
        console.log('🔄 Starting camera with config:', config);
        
        // Start scanning
        await html5QrCode.start(
            cameraId,
            config,
            onScanSuccess,
            onScanFailure
        );
        
        scannerActive = true;
        console.log('✅ Scanner started successfully');
        
        // Show stop button and hide start button
        document.getElementById('start-scanner-btn').style.display = 'none';
        document.getElementById('stop-scanner-btn').style.display = 'block';
        
        // Show reset scan button when scanner is active
        const resetScanBtn = document.getElementById('reset-scan-btn');
        if (resetScanBtn) {
            resetScanBtn.style.display = 'block';
        }
        
        const instruction = currentOperation === 'assign' 
            ? 'Point camera at a cadet QR code first to start assignment'
            : 'Point camera at a cadet QR code to return their rifle';
        updateCameraStatus(instruction);
        
        // Update scan stats display
        updateScanStats();
        
        // Show scanner controls
        const scannerControls = document.getElementById('scanner-controls');
        if (scannerControls) {
            scannerControls.style.display = 'block';
        }
        
    } catch (err) {
        console.error('❌ Scanner start failed:', err);
        updateCameraStatus('Unable to start camera: ' + err.message, 'error');
        alert(`Unable to start scanning. Please ensure you've granted camera permissions.\n\nError: ${err.message || err}`);
    }
}

/**
 * Callback when QR code is successfully scanned
 */
async function onScanSuccess(decodedText, decodedResult) {
    // Track successful scan and reset failure counters
    lastSuccessfulScanTime = Date.now();
    window.consecutiveScanFailures = 0;
    window.scanFailureHistory = [];
    
    // Deactivate scan assist mode on successful scan
    if (scanAssistMode) {
        console.log('✅ Scan successful - deactivating scan assist mode');
        deactivateScanAssistMode();
    }
    
    // Hide manual entry option on successful scan
    hideManualEntryOption();
    
    // Ignore if cooling down or already processing a submission
    if (scanCooldown || isSubmitting) {
        return;
    }
    // Immediately start cooldown and lock submission
    setCooldown();
    isSubmitting = true;
    // Count only after passing guards
    scanStats.totalScanned++;
    
    // Initialize comprehensive debug information
    const debugInfo = {
        rawData: decodedText,
        workflowStep: assignmentWorkflow.step,
        operation: currentOperation,
        timestamp: new Date().toISOString(),
        scanNumber: scanStats.totalScanned,
        decryptionAttempts: [],
        validationErrors: [],
        finalResult: null,
        processingSteps: [],
        qrCodeAnalysis: {
            length: decodedText.length,
            startsWithBase64: /^[A-Za-z0-9+/]+=*$/.test(decodedText.substring(0, 20)),
            containsSpecialChars: /[^A-Za-z0-9+/=]/.test(decodedText),
            estimatedType: 'unknown'
        }
    };
    
    console.log('=== QR SCAN DEBUG START ===');
    console.log('📊 Scan #' + scanStats.totalScanned);
    console.log('📱 Raw QR data length:', decodedText.length);
    console.log('📱 Raw QR data preview:', decodedText.substring(0, 100) + (decodedText.length > 100 ? '...' : ''));
    console.log('🔄 Current workflow step:', assignmentWorkflow.step);
    console.log('⚙️ Current operation:', currentOperation);
    console.log('🕐 Scan timestamp:', debugInfo.timestamp);
    
    // Add initial processing step
    debugInfo.processingSteps.push({
        step: 'scan_initiated',
        timestamp: new Date().toISOString(),
        details: `Scan #${scanStats.totalScanned} - ${currentOperation} operation in ${assignmentWorkflow.step} state`
    });
    
    try {
        let qrData;
        
        // Context-aware decryption based on workflow step
        const isWaitingForRifle = assignmentWorkflow.step === 'waiting_for_rifle';
        
        // Check if this is a base64 encoded QR from batch generation (like cadet scanner)
        debugInfo.processingSteps.push({
            step: 'base64_decode_attempt',
            timestamp: new Date().toISOString(),
            details: 'Attempting to decode as base64 batch-generated QR code'
        });
        
        try {
            debugInfo.decryptionAttempts.push({
                method: 'base64_decode',
                key: 'batch-generated',
                keyValue: 'attendance-system-permanent-key-2023',
                status: 'attempting',
                details: 'Checking for batch-generated QR format'
            });
            
            console.log('🔍 Attempting base64 decode for batch-generated QR...');
            const decoded = atob(decodedText);
            console.log('📝 Base64 decoded result length:', decoded.length);
            console.log('📝 Base64 decoded preview:', decoded.substring(0, 100) + (decoded.length > 100 ? '...' : ''));
            
            debugInfo.qrCodeAnalysis.estimatedType = 'base64_encoded';
            debugInfo.qrCodeAnalysis.decodedLength = decoded.length;
            
            if (decoded.startsWith('attendance-system-permanent-key-2023|')) {
                // This is a batch-generated QR code
                console.log('✅ Detected batch-generated QR code with attendance system prefix');
                debugInfo.processingSteps.push({
                    step: 'batch_qr_detected',
                    timestamp: new Date().toISOString(),
                    details: 'Found attendance-system-permanent-key-2023 prefix - this is a batch-generated QR'
                });
                
                const jsonData = decoded.substring('attendance-system-permanent-key-2023|'.length);
                console.log('📊 Extracting JSON data from batch QR, length:', jsonData.length);
                console.log('📊 JSON data preview:', jsonData.substring(0, 200) + (jsonData.length > 200 ? '...' : ''));
                
                qrData = JSON.parse(jsonData);
                console.log('✅ Successfully parsed batch-generated QR data:', qrData);
                
                debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'success';
                debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].result = qrData;
                debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].details += ' - Successfully decoded batch QR';
                
                // Enhanced cadet QR processing with detailed logging
                if (qrData.student_id && !qrData.cadet_id) {
                    console.log('🔄 Mapping student_id to cadet_id for compatibility');
                    console.log('📝 Original student_id:', qrData.student_id);
                    qrData.cadet_id = qrData.student_id;
                    qrData.type = 'cadet';
                    console.log('✅ Successfully mapped to cadet_id:', qrData.cadet_id);
                    
                    debugInfo.processingSteps.push({
                        step: 'cadet_id_mapping',
                        timestamp: new Date().toISOString(),
                        details: `Mapped student_id (${qrData.student_id}) to cadet_id for compatibility`
                    });
                }
                
                // Normalize cadet profile identifier for compatibility
                if ((qrData.cadet_profile_id || qrData.profile_id)) {
                    const pid = qrData.profile_id || qrData.cadet_profile_id;
                    console.log('🔄 Normalizing cadet_profile_id to profile_id for compatibility');
                    qrData.profile_id = pid;
                    qrData.cadet_profile_id = pid;
                    qrData.type = 'cadet';
                    debugInfo.processingSteps.push({
                        step: 'profile_id_mapping',
                        timestamp: new Date().toISOString(),
                        details: `Normalized cadet profile id (${pid}) to profile_id`
                    });
                }
                
                // Log cadet information if this is a cadet QR
                if (qrData.type === 'cadet' || qrData.cadet_id || qrData.student_id) {
                    console.log('👤 Cadet QR Code Details:');
                    console.log('   - Cadet ID:', qrData.cadet_id || qrData.student_id);
                    console.log('   - Name:', qrData.name || 'Not specified');
                    console.log('   - Type:', qrData.type || 'Inferred as cadet');
                    console.log('   - Generated At:', qrData.generatedAt || 'Unknown');
                    
                    debugInfo.processingSteps.push({
                        step: 'cadet_qr_analysis',
                        timestamp: new Date().toISOString(),
                        details: `Cadet QR detected - ID: ${qrData.cadet_id || qrData.student_id}, Name: ${qrData.name || 'Unknown'}`
                    });
                }
            } else {
                console.log('❌ Not a batch-generated QR code - missing attendance system prefix');
                debugInfo.qrCodeAnalysis.estimatedType = 'base64_but_not_batch';
                throw new Error('Not a batch-generated QR code - missing expected prefix');
            }
        } catch (e) {
            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'failed';
            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].error = e.message;
            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].details += ` - Error: ${e.message}`;
            
            console.log('❌ Base64 decode failed, proceeding to CryptoJS decryption');
            console.log('📝 Error details:', e.message);
            
            debugInfo.processingSteps.push({
                step: 'base64_decode_failed',
                timestamp: new Date().toISOString(),
                details: `Base64 decode failed: ${e.message} - Will try CryptoJS decryption`
            });
            
            debugInfo.qrCodeAnalysis.estimatedType = 'encrypted';
            
            // Context-aware decryption order with enhanced debugging
            debugInfo.processingSteps.push({
                step: 'cryptojs_decryption_start',
                timestamp: new Date().toISOString(),
                details: `Starting CryptoJS decryption - Context: ${isWaitingForRifle ? 'waiting for rifle' : 'waiting for cadet'}`
            });
            
            if (isWaitingForRifle) {
                // When waiting for rifle, try rifle key first
                console.log('🔍 Context: Waiting for rifle - trying rifle key first');
                debugInfo.processingSteps.push({
                    step: 'rifle_context_decryption',
                    timestamp: new Date().toISOString(),
                    details: 'Context is waiting for rifle - prioritizing rifle encryption key'
                });
                
                try {
                    debugInfo.decryptionAttempts.push({
                        method: 'cryptojs_aes',
                        key: 'rifle_key',
                        keyValue: RIFLE_ENCRYPTION_KEY.substring(0, 10) + '...',
                        status: 'attempting',
                        details: 'Primary attempt - rifle key (context: waiting for rifle)'
                    });
                    
                    console.log('🔑 Attempting rifle key decryption...');
                    console.log('📝 Rifle key (truncated):', RIFLE_ENCRYPTION_KEY.substring(0, 10) + '...');
                    const decryptedBytes = CryptoJS.AES.decrypt(decodedText, RIFLE_ENCRYPTION_KEY);
                    const decryptedText = decryptedBytes.toString(CryptoJS.enc.Utf8);
                    console.log('📝 Rifle key decryption result length:', decryptedText ? decryptedText.length : 0);
                    console.log('📝 Rifle key decrypted preview:', decryptedText ? decryptedText.substring(0, 100) + (decryptedText.length > 100 ? '...' : '') : 'null');
                    
                    if (decryptedText && decryptedText.trim()) {
                        // Parse the decrypted JSON
                        console.log('📊 Parsing rifle key decryption result...');
                        qrData = JSON.parse(decryptedText);
                        console.log('✅ Rifle key decryption successful:', qrData);
                        
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'success';
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].result = qrData;
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].details += ' - Successfully decrypted with rifle key';
                        
                        debugInfo.processingSteps.push({
                            step: 'rifle_key_success',
                            timestamp: new Date().toISOString(),
                            details: 'Successfully decrypted QR using rifle encryption key'
                        });
                        
                        // Enhanced rifle QR processing with detailed logging
                        if (!qrData.rifle_id) {
                            if (qrData.serial) {
                                console.log('🔄 Mapping serial to rifle_id for compatibility');
                                console.log('📝 Original serial:', qrData.serial);
                                qrData.rifle_id = qrData.serial;
                                debugInfo.processingSteps.push({
                                    step: 'rifle_id_mapping',
                                    timestamp: new Date().toISOString(),
                                    details: `Mapped serial (${qrData.serial}) to rifle_id for compatibility`
                                });
                            } else if (qrData.serial_number) {
                                console.log('🔄 Mapping serial_number to rifle_id for compatibility');
                                console.log('📝 Original serial_number:', qrData.serial_number);
                                qrData.rifle_id = qrData.serial_number;
                                debugInfo.processingSteps.push({
                                    step: 'rifle_id_mapping_serial_number',
                                    timestamp: new Date().toISOString(),
                                    details: `Mapped serial_number (${qrData.serial_number}) to rifle_id`
                                });
                            } else if (qrData.rifle_number) {
                                console.log('🔄 Mapping rifle_number to rifle_id for compatibility');
                                console.log('📝 Original rifle_number:', qrData.rifle_number);
                                qrData.rifle_id = qrData.rifle_number;
                                debugInfo.processingSteps.push({
                                    step: 'rifle_id_mapping_rifle_number',
                                    timestamp: new Date().toISOString(),
                                    details: `Mapped rifle_number (${qrData.rifle_number}) to rifle_id`
                                });
                            } else if (qrData.id) {
                                console.log('🔄 Mapping id to rifle_id for compatibility');
                                console.log('📝 Original id:', qrData.id);
                                qrData.rifle_id = qrData.id;
                                debugInfo.processingSteps.push({
                                    step: 'rifle_id_mapping_id',
                                    timestamp: new Date().toISOString(),
                                    details: `Mapped id (${qrData.id}) to rifle_id`
                                });
                            }
                            if (qrData.rifle_id) {
                                console.log('✅ Successfully mapped to rifle_id:', qrData.rifle_id);
                            }
                        }
                        
                        // Enhanced type setting with detailed logging
                        if (!qrData.type) {
                            console.log('🏷️ Setting QR type to rifle');
                            qrData.type = 'rifle';
                            console.log('✅ QR type set to:', qrData.type);
                            
                            debugInfo.processingSteps.push({
                                step: 'rifle_type_assignment',
                                timestamp: new Date().toISOString(),
                                details: 'Assigned type as rifle for QR without explicit type'
                            });
                        }
                        
                        // Log comprehensive rifle information
                        console.log('🔫 Rifle QR Code Details:');
                        console.log('   - Rifle ID:', qrData.rifle_id || qrData.serial || 'Not specified');
                        console.log('   - Type:', qrData.type || 'Not specified');
                        console.log('   - Generated At:', qrData.generatedAt || 'Unknown');
                        console.log('   - Additional Data:', Object.keys(qrData).filter(k => !['rifle_id', 'serial', 'type', 'generatedAt'].includes(k)));
                        
                        debugInfo.processingSteps.push({
                            step: 'rifle_qr_analysis',
                            timestamp: new Date().toISOString(),
                            details: `Rifle QR analyzed - ID: ${qrData.rifle_id || qrData.serial}, Type: ${qrData.type}`
                        });
                    } else {
                        console.log('❌ Rifle key decryption returned empty or null result');
                        debugInfo.processingSteps.push({
                            step: 'rifle_key_empty_result',
                            timestamp: new Date().toISOString(),
                            details: 'Rifle key decryption returned empty or null text'
                        });
                        throw new Error('Rifle key decryption resulted in empty text');
                    }
                } catch (rifleKeyError) {
                    debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'failed';
                    debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].error = rifleKeyError.message;
                    debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].details += ` - Error: ${rifleKeyError.message}`;
                    
                    console.log('❌ Rifle key decryption failed, trying attendance key fallback');
                    console.log('📝 Rifle key error details:', rifleKeyError.message);
                    
                    debugInfo.processingSteps.push({
                        step: 'rifle_key_failed',
                        timestamp: new Date().toISOString(),
                        details: `Rifle key failed: ${rifleKeyError.message} - Trying attendance key fallback`
                    });
                    
                    // Fallback to attendance key
                    try {
                        debugInfo.decryptionAttempts.push({
                            method: 'cryptojs_aes',
                            key: 'attendance_key',
                            keyValue: PERMANENT_ENCRYPTION_KEY.substring(0, 10) + '...',
                            status: 'attempting',
                            details: 'Fallback attempt - attendance key (context: waiting for rifle)'
                        });
                        
                        console.log('🔓 Attempting attendance key decryption as fallback...');
                        console.log('📝 Attendance key (truncated):', PERMANENT_ENCRYPTION_KEY.substring(0, 10) + '...');
                        const decryptedBytes = CryptoJS.AES.decrypt(decodedText, PERMANENT_ENCRYPTION_KEY);
                        const decryptedText = decryptedBytes.toString(CryptoJS.enc.Utf8);
                        console.log('📝 Attendance key decryption result length:', decryptedText ? decryptedText.length : 0);
                        console.log('📝 Attendance key decrypted preview:', decryptedText ? decryptedText.substring(0, 100) + (decryptedText.length > 100 ? '...' : '') : 'null');
                        
                        if (decryptedText && decryptedText.trim()) {
                            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'success';
                            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].result = decryptedText.substring(0, 100) + (decryptedText.length > 100 ? '...' : '');
                            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].details += ' - SUCCESS (fallback)';
                            
                            console.log('✅ Attendance key decryption successful (fallback)');
                            
                            debugInfo.processingSteps.push({
                                step: 'attendance_key_success_fallback',
                                timestamp: new Date().toISOString(),
                                details: `Attendance key fallback successful - Data length: ${decryptedText.length}`
                            });
                            
                            try {
                                console.log('🔍 Parsing attendance key decrypted JSON (fallback)...');
                                // Parse the decrypted JSON
                                qrData = JSON.parse(decryptedText);
                                
                                debugInfo.processingSteps.push({
                                    step: 'json_parse_success_fallback',
                                    timestamp: new Date().toISOString(),
                                    details: `JSON parsing successful (fallback) - Keys: ${Object.keys(qrData).join(', ')}`
                                });
                                
                                console.log('✓ Attendance system QR data (fallback):', qrData);
                                console.log('🔍 QR data analysis (fallback):');
                                console.log('  - Keys found:', Object.keys(qrData));
                                console.log('  - Has student_id:', !!qrData.student_id);
                                console.log('  - Has cadet_id:', !!qrData.cadet_id);
                                console.log('  - Has type:', !!qrData.type);
                                
                                // For student QR codes from QR folder, map student_id to cadet_id
                                if (qrData.student_id && !qrData.cadet_id) {
                                    qrData.cadet_id = qrData.student_id;
                                    qrData.type = 'cadet';
                                    console.log('🔄 Mapped student_id to cadet_id (fallback):', qrData.cadet_id);
                                    debugInfo.processingSteps.push({
                                        step: 'id_mapping_fallback',
                                        timestamp: new Date().toISOString(),
                                        details: `Mapped student_id (${qrData.student_id}) to cadet_id (fallback)`
                                    });
                                }
                                
                                console.log('📋 Final cadet QR data (fallback):', {
                                    cadet_id: qrData.cadet_id,
                                    type: qrData.type,
                                    name: qrData.name || 'N/A'
                                });
                                
                            } catch (parseError) {
                                debugInfo.validationErrors.push({
                                    type: 'json_parse_error_fallback',
                                    message: parseError.message,
                                    data: decryptedText.substring(0, 200)
                                });
                                debugInfo.processingSteps.push({
                                    step: 'json_parse_failed_fallback',
                                    timestamp: new Date().toISOString(),
                                    details: `JSON parsing failed (fallback): ${parseError.message}`
                                });
                                console.error('❌ Attendance key JSON parsing failed (fallback):', parseError.message);
                                throw parseError;
                            }
                        } else {
                            throw new Error('Attendance key decryption resulted in empty text');
                        }
                    } catch (attendanceKeyError) {
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'failed';
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].error = attendanceKeyError.message;
                        console.log('✗ Both decryption methods failed. Attendance key error:', attendanceKeyError.message);
                        
                        // Create detailed error message
                        const errorDetails = {
                            message: 'All decryption methods failed',
                            attempts: debugInfo.decryptionAttempts,
                            rawData: decodedText.substring(0, 100) + '...',
                            suggestions: [
                                'Verify QR code was generated by this system',
                                'Check if encryption keys match between generation and scanning',
                                'Ensure QR code is not corrupted or partially scanned'
                            ]
                        };
                        
                        throw new Error(`Invalid QR code. ${JSON.stringify(errorDetails, null, 2)}`);
                    }
                }
            } else {
                // When waiting for cadet or idle, try attendance key first
                console.log('🔍 Context-aware decryption: Waiting for cadet - prioritizing attendance key');
                
                debugInfo.processingSteps.push({
                    step: 'attendance_key_priority',
                    timestamp: new Date().toISOString(),
                    details: 'Context: waiting for cadet - trying attendance key first'
                });
                
                try {
                    debugInfo.decryptionAttempts.push({
                        method: 'cryptojs_aes',
                        key: 'attendance_key',
                        keyValue: PERMANENT_ENCRYPTION_KEY.substring(0, 10) + '...',
                        status: 'attempting',
                        details: 'Primary attempt - attendance key (context: waiting for cadet)'
                    });
                    
                    console.log('🔓 Attempting attendance key decryption (primary)...');
                    console.log('📝 Attendance key (truncated):', PERMANENT_ENCRYPTION_KEY.substring(0, 10) + '...');
                    const decryptedBytes = CryptoJS.AES.decrypt(decodedText, PERMANENT_ENCRYPTION_KEY);
                    const decryptedText = decryptedBytes.toString(CryptoJS.enc.Utf8);
                    console.log('📝 Attendance key decryption result length:', decryptedText ? decryptedText.length : 0);
                    console.log('📝 Attendance key decrypted preview:', decryptedText ? decryptedText.substring(0, 100) + (decryptedText.length > 100 ? '...' : '') : 'null');
                    
                    if (decryptedText && decryptedText.trim()) {
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'success';
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].result = decryptedText.substring(0, 100) + (decryptedText.length > 100 ? '...' : '');
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].details += ' - SUCCESS (primary)';
                        
                        console.log('✅ Attendance key decryption successful (primary)');
                        
                        debugInfo.processingSteps.push({
                            step: 'attendance_key_success_primary',
                            timestamp: new Date().toISOString(),
                            details: `Attendance key primary successful - Data length: ${decryptedText.length}`
                        });
                        
                        try {
                            console.log('🔍 Parsing attendance key decrypted JSON (primary)...');
                            // Parse the decrypted JSON
                            qrData = JSON.parse(decryptedText);
                            
                            debugInfo.processingSteps.push({
                                step: 'json_parse_success_primary',
                                timestamp: new Date().toISOString(),
                                details: `JSON parsing successful (primary) - Keys: ${Object.keys(qrData).join(', ')}`
                            });
                            
                            console.log('✓ Attendance system QR data (primary):', qrData);
                            console.log('🔍 QR data analysis (primary):');
                            console.log('  - Keys found:', Object.keys(qrData));
                            console.log('  - Has student_id:', !!qrData.student_id);
                            console.log('  - Has cadet_id:', !!qrData.cadet_id);
                            console.log('  - Has type:', !!qrData.type);
                            
                            // For student QR codes from QR folder, map student_id to cadet_id
                            if (qrData.student_id && !qrData.cadet_id) {
                                qrData.cadet_id = qrData.student_id;
                                qrData.type = 'cadet';
                                console.log('🔄 Mapped student_id to cadet_id (primary):', qrData.cadet_id);
                                debugInfo.processingSteps.push({
                                    step: 'id_mapping_primary',
                                    timestamp: new Date().toISOString(),
                                    details: `Mapped student_id (${qrData.student_id}) to cadet_id (primary)`
                                });
                            }
                            
                            console.log('📋 Final cadet QR data (primary):', {
                                cadet_id: qrData.cadet_id,
                                type: qrData.type,
                                name: qrData.name || 'N/A'
                            });
                            
                        } catch (parseError) {
                            debugInfo.validationErrors.push({
                                type: 'json_parse_error_primary',
                                message: parseError.message,
                                data: decryptedText.substring(0, 200)
                            });
                            debugInfo.processingSteps.push({
                                step: 'json_parse_failed_primary',
                                timestamp: new Date().toISOString(),
                                details: `JSON parsing failed (primary): ${parseError.message}`
                            });
                            console.error('❌ Attendance key JSON parsing failed (primary):', parseError.message);
                            throw parseError;
                        }
                    } else {
                        throw new Error('Attendance key decryption resulted in empty text');
                    }
                } catch (attendanceKeyError) {
                    debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'failed';
                    debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].error = attendanceKeyError.message;
                    console.log('✗ Attendance key decryption failed, trying rifle key. Error:', attendanceKeyError.message);
                    
                    // Fallback to rifle key
                    try {
                        debugInfo.decryptionAttempts.push({
                            method: 'cryptojs_aes',
                            key: 'rifle_key',
                            keyValue: RIFLE_ENCRYPTION_KEY.substring(0, 10) + '...',
                            status: 'attempting',
                            details: 'Fallback attempt - rifle key (context: waiting for cadet)'
                        });
                        
                        console.log('🔑 Attempting rifle key decryption as fallback...');
                        console.log('📝 Rifle key (truncated):', RIFLE_ENCRYPTION_KEY.substring(0, 10) + '...');
                        
                        debugInfo.processingSteps.push({
                            step: 'rifle_key_fallback_attempt',
                            timestamp: new Date().toISOString(),
                            details: 'Attempting rifle key decryption as fallback after attendance key failed'
                        });
                        
                        const decryptedBytes = CryptoJS.AES.decrypt(decodedText, RIFLE_ENCRYPTION_KEY);
                        const decryptedText = decryptedBytes.toString(CryptoJS.enc.Utf8);
                        console.log('📝 Rifle key decryption result length:', decryptedText ? decryptedText.length : 0);
                        console.log('📝 Rifle key decrypted preview:', decryptedText ? decryptedText.substring(0, 100) + (decryptedText.length > 100 ? '...' : '') : 'null');
                        
                        if (decryptedText && decryptedText.trim()) {
                            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'success';
                            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].result = decryptedText.substring(0, 100) + (decryptedText.length > 100 ? '...' : '');
                            debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].details += ' - SUCCESS (fallback)';
                            
                            console.log('✅ Rifle key decryption successful (fallback)');
                            
                            debugInfo.processingSteps.push({
                                step: 'rifle_key_success_fallback',
                                timestamp: new Date().toISOString(),
                                details: `Rifle key fallback successful - Data length: ${decryptedText.length}`
                            });
                            
                            try {
                                console.log('🔍 Parsing rifle key decrypted JSON (fallback)...');
                                // Parse the decrypted JSON
                                qrData = JSON.parse(decryptedText);
                                
                                debugInfo.processingSteps.push({
                                    step: 'json_parse_success_fallback_rifle',
                                    timestamp: new Date().toISOString(),
                                    details: `JSON parsing successful (rifle fallback) - Keys: ${Object.keys(qrData).join(', ')}`
                                });
                                
                                console.log('✓ Rifle system QR data (fallback):', qrData);
                                console.log('🔍 Rifle QR data analysis (fallback):');
                                console.log('  - Keys found:', Object.keys(qrData));
                                console.log('  - Has serial:', !!qrData.serial);
                                console.log('  - Has rifle_id:', !!qrData.rifle_id);
                                console.log('  - Has type:', !!qrData.type);
                                
                                // For rifle QR codes from QR folder, map 'serial' to 'rifle_id' for compatibility
                                if (!qrData.rifle_id) {
                                    if (qrData.serial) {
                                        console.log('🔄 Mapping serial to rifle_id for compatibility (fallback)');
                                        console.log('📝 Original serial:', qrData.serial);
                                        qrData.rifle_id = qrData.serial;
                                        debugInfo.processingSteps.push({
                                            step: 'rifle_id_mapping_fallback',
                                            timestamp: new Date().toISOString(),
                                            details: `Mapped serial (${qrData.serial}) to rifle_id for compatibility (fallback)`
                                        });
                                    } else if (qrData.serial_number) {
                                        console.log('🔄 Mapping serial_number to rifle_id (fallback)');
                                        console.log('📝 Original serial_number:', qrData.serial_number);
                                        qrData.rifle_id = qrData.serial_number;
                                        debugInfo.processingSteps.push({
                                            step: 'rifle_id_mapping_serial_number_fallback',
                                            timestamp: new Date().toISOString(),
                                            details: `Mapped serial_number (${qrData.serial_number}) to rifle_id (fallback)`
                                        });
                                    } else if (qrData.rifle_number) {
                                        console.log('🔄 Mapping rifle_number to rifle_id (fallback)');
                                        console.log('📝 Original rifle_number:', qrData.rifle_number);
                                        qrData.rifle_id = qrData.rifle_number;
                                        debugInfo.processingSteps.push({
                                            step: 'rifle_id_mapping_rifle_number_fallback',
                                            timestamp: new Date().toISOString(),
                                            details: `Mapped rifle_number (${qrData.rifle_number}) to rifle_id (fallback)`
                                        });
                                    } else if (qrData.id) {
                                        console.log('🔄 Mapping id to rifle_id (fallback)');
                                        console.log('📝 Original id:', qrData.id);
                                        qrData.rifle_id = qrData.id;
                                        debugInfo.processingSteps.push({
                                            step: 'rifle_id_mapping_id_fallback',
                                            timestamp: new Date().toISOString(),
                                            details: `Mapped id (${qrData.id}) to rifle_id (fallback)`
                                        });
                                    }
                                    if (qrData.rifle_id) {
                                        console.log('✅ Successfully mapped to rifle_id:', qrData.rifle_id);
                                    }
                                }
                                
                                // For rifle QR codes from QR folder, ensure proper type
                                if (!qrData.type) {
                                    console.log('🏷️ Setting QR type to rifle (fallback)');
                                    qrData.type = 'rifle';
                                    console.log('✅ QR type set to:', qrData.type);
                                    
                                    debugInfo.processingSteps.push({
                                        step: 'rifle_type_assignment_fallback',
                                        timestamp: new Date().toISOString(),
                                        details: 'Assigned type as rifle for QR without explicit type (fallback)'
                                    });
                                }
                                
                                console.log('📋 Final rifle QR data (fallback):', {
                                    rifle_id: qrData.rifle_id,
                                    type: qrData.type,
                                    serial: qrData.serial || 'N/A'
                                });
                                
                            } catch (parseError) {
                                debugInfo.validationErrors.push({
                                    type: 'json_parse_error_fallback_rifle',
                                    message: parseError.message,
                                    data: decryptedText.substring(0, 200)
                                });
                                debugInfo.processingSteps.push({
                                    step: 'json_parse_failed_fallback_rifle',
                                    timestamp: new Date().toISOString(),
                                    details: `JSON parsing failed (rifle fallback): ${parseError.message}`
                                });
                                console.error('❌ Rifle key JSON parsing failed (fallback):', parseError.message);
                                throw parseError;
                            }
                        } else {
                            console.log('❌ Rifle key decryption returned empty or null result (fallback)');
                            debugInfo.processingSteps.push({
                                step: 'rifle_key_empty_result_fallback',
                                timestamp: new Date().toISOString(),
                                details: 'Rifle key decryption returned empty or null text (fallback)'
                            });
                            throw new Error('Rifle key decryption resulted in empty text');
                        }
                    } catch (rifleKeyError) {
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].status = 'failed';
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].error = rifleKeyError.message;
                        debugInfo.decryptionAttempts[debugInfo.decryptionAttempts.length - 1].details += ' - FAILED (fallback)';
                        
                        debugInfo.processingSteps.push({
                            step: 'rifle_key_fallback_failed',
                            timestamp: new Date().toISOString(),
                            details: `Rifle key fallback failed: ${rifleKeyError.message}`
                        });
                        
                        console.log('❌ Rifle key decryption failed (fallback):', rifleKeyError.message);
                        console.log('💥 Both decryption methods failed for cadet context');
                        console.log('📊 Decryption summary:');
                        debugInfo.decryptionAttempts.forEach((attempt, index) => {
                            console.log(`  ${index + 1}. ${attempt.key} (${attempt.method}): ${attempt.status} - ${attempt.details || 'No details'}`);
                            if (attempt.error) console.log(`     Error: ${attempt.error}`);
                        });
                        
                        debugInfo.finalError = {
                            type: 'dual_decryption_failure_cadet_context',
                            message: 'Both attendance and rifle key decryption failed in cadet context',
                            details: {
                                totalAttempts: debugInfo.decryptionAttempts.length,
                                context: 'waiting_for_cadet',
                                qrDataLength: decodedText ? decodedText.length : 0,
                                lastError: rifleKeyError.message
                            },
                            suggestions: [
                                'Verify QR code was generated with correct encryption key',
                                'Check if QR code is damaged or partially obscured',
                                'Ensure encryption keys match between generation and scanning',
                                'Try regenerating the QR code',
                                'Verify the QR code type matches expected format',
                                'Check if this is a QR code from a different system'
                            ]
                        };
                        debugInfo.finalErrorDetails = rifleKeyError.message;
                        
                        console.error('✗ Both decryption methods failed. Debug info:', debugInfo);
                        throw new Error(`Invalid QR code. Debug info: ${JSON.stringify(debugInfo, null, 2)}`);
                    }
                }
            }
        }
        
        // Final type inference if not set
        if (!qrData.type) {
            if (qrData.rifle_id || qrData.serial || qrData.serial_number || qrData.rifle_number || qrData.id) {
                qrData.type = 'rifle';
                // Ensure rifle_id is set for compatibility
                if (!qrData.rifle_id) {
                    qrData.rifle_id = qrData.serial || qrData.serial_number || qrData.rifle_number || qrData.id || qrData.rifle_id;
                }
            } else if (qrData.profile_id || qrData.cadet_profile_id || qrData.student_id || qrData.cadet_id) {
                qrData.type = 'cadet';
            }
        }
        
        // Normalize cadet fields globally (works for both base64 and encrypted QR paths)
        if (qrData.type === 'cadet') {
            if ((qrData.cadet_profile_id || qrData.profile_id) && !qrData.profile_id) {
                qrData.profile_id = qrData.cadet_profile_id || qrData.profile_id;
            }
            if (qrData.profile_id && !qrData.cadet_profile_id) {
                qrData.cadet_profile_id = qrData.profile_id;
            }
            if (qrData.student_id && !qrData.cadet_id) {
                qrData.cadet_id = qrData.student_id;
            }
        }

        // Auto-resolve missing cadet details (name/student_id) if needed
        if (qrData.type === 'cadet' && (!qrData.name || !qrData.student_id)) {
            try {
                const resolvePayload = {
                    action: 'resolve_cadet'
                };
                if (qrData.profile_id || qrData.cadet_profile_id) {
                    resolvePayload.profile_id = qrData.profile_id || qrData.cadet_profile_id;
                } else if (qrData.student_id || qrData.cadet_id) {
                    resolvePayload.cadet_id = qrData.student_id || qrData.cadet_id;
                }
                if (resolvePayload.profile_id || resolvePayload.cadet_id) {
                    const resp = await fetch(API_RIFLE_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify(resolvePayload)
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        if (data && data.success && data.cadet) {
                            // Fill normalized fields
                            qrData.profile_id = data.cadet.profile_id || qrData.profile_id || qrData.cadet_profile_id;
                            qrData.cadet_profile_id = qrData.profile_id;
                            qrData.student_id = data.cadet.student_id || qrData.student_id || qrData.cadet_id;
                            if (!qrData.cadet_id) qrData.cadet_id = qrData.student_id;
                            // Compose name
                            const fullName = data.cadet.full_name || [data.cadet.first_name, data.cadet.last_name].filter(Boolean).join(' ');
                            if (fullName) qrData.name = fullName;
                        }
                    }
                }
            } catch (e) {
                console.warn('Cadet detail resolution skipped due to error:', e);
            }
        }

        // Set final result in debug info for successful processing
        debugInfo.finalResult = qrData;
        debugInfo.validationErrors = []; // Clear any validation errors
        
        console.log('✅ QR processing completed successfully');
        console.log('=== QR SCAN DEBUG END ===');
        
        // Handle workflow based on current operation and step
        if (currentOperation === 'attendance') {
            await handleAttendanceScanning(qrData, debugInfo);
        } else if (currentOperation === 'assign') {
            await handleAssignmentWorkflow(qrData, debugInfo);
        } else {
            // For return operation, still scan cadet QR only
            if (qrData.type !== 'cadet') {
                debugInfo.finalError = 'Wrong QR Type';
                debugInfo.finalErrorDetails = `Expected cadet QR for return operation, got ${qrData.type}`;
                debugInfo.validationErrors = ['Wrong QR code type for current operation'];
                debugInfo.suggestions = [
                    'Scan a cadet QR code for return operation',
                    'Switch to assign mode if you want to assign rifles'
                ];
                
                showScanResult('Please scan a cadet QR code for return operation', 'error', debugInfo);
                scanStats.failedScans++;
                updateScanStats();
                setCooldown();
                return;
            }
            await processRifleReturn(qrData, debugInfo);
        }
        
    } catch (error) {
        console.error('🚨 Error processing QR code:', error);
        
        // Enhanced error message with debug info if available
        let errorMessage = 'Invalid QR Code! Make sure you are using a QR code generated by this system.';
        let debugInfoToPass = null;
        
        // If we have debug info from decryption attempts, include it
        if (typeof debugInfo !== 'undefined') {
            debugInfo.finalError = debugInfo.finalError || 'QR Code Processing Failed';
            debugInfo.finalErrorDetails = error.message || 'Unknown error occurred';
            
            // Add validation errors if not already present
            if (!debugInfo.validationErrors) {
                debugInfo.validationErrors = [];
            }
            
            // Add specific error details based on error type
            if (error.message.includes('JSON')) {
                debugInfo.validationErrors.push('Invalid JSON format in QR data');
                debugInfo.suggestions = [
                    'QR code may be corrupted or incomplete',
                    'Verify QR code was generated correctly',
                    'Try scanning again with better lighting'
                ];
            } else if (error.message.includes('decrypt')) {
                debugInfo.validationErrors.push('Decryption failed with all available keys');
                debugInfo.suggestions = [
                    'Check if QR code was generated by this system',
                    'Verify encryption keys match between generation and scanning',
                    'QR code may be from a different system or corrupted'
                ];
            } else {
                debugInfo.validationErrors.push(`Processing error: ${error.message}`);
            }
            
            console.error('🔍 Detailed debug info:', debugInfo);
            debugInfoToPass = debugInfo;
            
            // Don't include raw debug JSON in user message anymore
            errorMessage = 'Invalid QR Code! Please check the debug panel below for detailed information.';
        }
        
        // If the error message contains debug info, extract it
        if (error.message && error.message.includes('Debug info:')) {
            try {
                const debugMatch = error.message.match(/Debug info: ({.*})/);
                if (debugMatch) {
                    const extractedDebugInfo = JSON.parse(debugMatch[1]);
                    debugInfoToPass = extractedDebugInfo;
                    errorMessage = 'Invalid QR Code! Please check the debug panel below for detailed information.';
                }
            } catch (parseError) {
                console.error('Failed to parse debug info from error message:', parseError);
            }
        }
        
        showScanResult(errorMessage, 'error', debugInfoToPass);
        scanStats.failedScans++;
        updateScanStats();
        setCooldown();
    } finally {
        // Always unlock submission to allow continuous scanning after each attempt
        isSubmitting = false;
    }
}

/**
 * Handler for attendance scanning process
 */
async function handleAttendanceScanning(qrData, debugInfo = null) {
    try {
        // For attendance, we only need cadet QR codes
        if (qrData.type !== 'cadet') {
            if (debugInfo) {
                debugInfo.finalError = 'Wrong QR Type for Attendance';
                debugInfo.finalErrorDetails = `Expected cadet QR for attendance, got ${qrData.type}`;
                debugInfo.validationErrors = ['Wrong QR code type for attendance scanning'];
                debugInfo.suggestions = [
                    'Scan a cadet QR code for attendance',
                    'Make sure you are scanning a cadet QR code, not a rifle QR code'
                ];
            }
            showScanResult('Please scan a cadet QR code for attendance', 'error', debugInfo);
            scanStats.failedScans++;
            updateScanStats();
            setCooldown();
            return;
        }

        // Get attendance form data (DOM is source of truth; fallback to globals)
        const schoolYear = document.getElementById('school-year')?.value || attendanceData.schoolYear || getDefaultSchoolYear();
        const semester = document.getElementById('semester')?.value || attendanceData.semester || '1st';
        const selectedTD = document.getElementById('event-name')?.value || '';
        const customTD = (document.getElementById('event-name-custom')?.value || '').trim();
        const eventName = customTD || selectedTD || attendanceData.eventName || '1TD';

        // Validate attendance form data
        if (!schoolYear || !semester || !eventName) {
            if (debugInfo) {
                debugInfo.finalError = 'Missing Attendance Information';
                debugInfo.finalErrorDetails = 'School year, semester, and training day are required';
                debugInfo.validationErrors = ['Please fill in all attendance form fields'];
                debugInfo.suggestions = [
                    'Enter school year in the form',
                    'Select semester in the form',
                    'Select Training Day or enter a custom event in the form'
                ];
            }
            showScanResult('Please fill in all attendance form fields (School Year, Semester, Training Day)', 'error', debugInfo);
            scanStats.failedScans++;
            updateScanStats();
            setCooldown();
            return;
        }

        // Get cadet ID
        const cadetId = (qrData.profile_id || qrData.cadet_profile_id || qrData.student_id || qrData.cadet_id || '').toString();
        
        // Check if this cadet has already been scanned for this event
        const attendanceKey = `${cadetId}-${eventName}-${schoolYear}-${semester}`;
        if (scannedItems.cadets.has(attendanceKey)) {
            if (debugInfo) {
                debugInfo.finalError = 'Duplicate Attendance Scan';
                debugInfo.finalErrorDetails = `Cadet ${cadetId} already marked present for this event`;
                debugInfo.validationErrors = ['Cadet attendance already recorded'];
                debugInfo.suggestions = [
                    'Scan a different cadet QR code',
                    'Check if this cadet was already marked present'
                ];
            }
            
            const warningHTML = `
                <div style="text-align: center; margin-bottom: var(--spacing-md);">
                    <h3 style="color: var(--warning); margin-bottom: var(--spacing-xs);">⚠️ Already Marked Present</h3>
                    <p style="margin: var(--spacing-xs) 0;">Cadet ${cadetId} is already marked present for this event.</p>
                </div>
                <div style="text-align: center; margin-top: var(--spacing-md); padding: var(--spacing-sm); background-color: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                        <i class="fas fa-info-circle"></i> You can continue scanning other cadets
                    </p>
                </div>
            `;
            
            showScanResult(warningHTML, 'warning', debugInfo);
            // Do not count duplicate as a failed scan
            updateScanStats();
            setCooldown();
            return;
        }

        // Process attendance (pass structured objects expected by processAttendance)
        const cadetPayload = {
            profile_id: qrData.profile_id || qrData.cadet_profile_id || null,
            student_id: qrData.student_id || null,
            cadet_id: qrData.cadet_id || (qrData.student_id || null),
            name: qrData.name || ''
        };
        const attendancePayload = {
            schoolYear: schoolYear,
            semester: semester,
            eventName: eventName
        };
        await processAttendance(cadetPayload, attendancePayload, debugInfo);
        
        // Mark this cadet as scanned for this event
        scannedItems.cadets.add(attendanceKey);
        
    } catch (error) {
        console.error('Error in attendance scanning:', error);
        if (debugInfo) {
            debugInfo.finalError = 'Attendance Processing Error';
            debugInfo.finalErrorDetails = error.message || 'Unknown error occurred';
            debugInfo.validationErrors = ['Failed to process attendance'];
        }
        showScanResult('Error processing attendance', 'error', debugInfo);
        scanStats.failedScans++;
        updateScanStats();
        setCooldown();
    }
}

/**
 * New workflow handler for assignment process
 */
async function handleAssignmentWorkflow(qrData, debugInfo = null) {
    try {
        if (assignmentWorkflow.step === 'idle' || assignmentWorkflow.step === 'waiting_for_cadet') {
            // First scan should be cadet QR
            if (qrData.type !== 'cadet') {
                if (debugInfo) {
                    debugInfo.finalError = 'Wrong QR Type for Assignment';
                    debugInfo.finalErrorDetails = `Expected cadet QR for assignment start, got ${qrData.type}`;
                    debugInfo.validationErrors = ['Wrong QR code type for assignment workflow'];
                    debugInfo.suggestions = [
                        'Scan a cadet QR code first to start assignment',
                        'Make sure you are scanning the correct QR code'
                    ];
                }
                showScanResult('Please scan a cadet QR code first for assignment', 'error', debugInfo);
                scanStats.failedScans++;
                updateScanStats();
                setCooldown();
                return;
            }
            
            // Check if this cadet has already been scanned in this session
            const cadetId = qrData.profile_id || qrData.cadet_profile_id || qrData.student_id || qrData.cadet_id;
            if (scannedItems.cadets.has(cadetId)) {
                if (debugInfo) {
                    debugInfo.finalError = 'Duplicate Cadet Scan';
                    debugInfo.finalErrorDetails = `Cadet ${cadetId} already scanned in this session`;
                    debugInfo.validationErrors = ['Cadet has already been processed'];
                    debugInfo.suggestions = [
                        'Scan a different cadet QR code',
                        'Use the reset button to clear session if needed'
                    ];
                }
                
                const warningHTML = `
                    <div style="text-align: center; margin-bottom: var(--spacing-md);">
                        <h3 style="color: var(--warning); margin-bottom: var(--spacing-xs);">⚠️ Cadet Already Processed</h3>
                        <p style="margin: var(--spacing-xs) 0;">Cadet ${cadetId} has already been assigned a rifle in this session.</p>
                    </div>
                    <div style="text-align: center; margin-top: var(--spacing-md); padding: var(--spacing-sm); background-color: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> You can continue scanning other cadets
                        </p>
                    </div>
                `;
                
                showScanResult(warningHTML, 'warning', debugInfo);
                scanStats.failedScans++;
                updateScanStats();
                
                // Allow continuous scanning by maintaining workflow state
                setTimeout(() => {
                    const statusDiv = document.getElementById('scan-status');
                    if (statusDiv) {
                        statusDiv.innerHTML = '<i class="fas fa-qrcode"></i> Ready to scan next cadet QR code';
                        statusDiv.className = 'scan-status ready';
                    }
                }, 2000);
                
                setCooldown();
                return;
            }
            
            // Add cadet to scanned items to prevent duplicate scanning
            scannedItems.cadets.add(cadetId);
            
            // Store cadet data and update workflow state
            assignmentWorkflow.cadetData = qrData;
            assignmentWorkflow.step = 'waiting_for_rifle';
            
            if (debugInfo) {
                debugInfo.finalResult = qrData;
                debugInfo.finalError = null;
                debugInfo.finalErrorDetails = null;
                debugInfo.validationErrors = [];
            }
            
            showScanResult(`Cadet ${cadetId} scanned. Now scan the rifle QR code.`, 'success', debugInfo);
            scanStats.successfulScans++;
            updateScanStats();
            setCooldown();
            
        } else if (assignmentWorkflow.step === 'waiting_for_rifle') {
            // Second scan should be rifle QR
            if (qrData.type !== 'rifle') {
                if (debugInfo) {
                    debugInfo.finalError = 'Wrong QR Type for Rifle Assignment';
                    debugInfo.finalErrorDetails = `Expected rifle QR for assignment completion, got ${qrData.type}`;
                    debugInfo.validationErrors = ['Wrong QR code type for rifle assignment'];
                    debugInfo.suggestions = [
                        'Scan a rifle QR code to complete the assignment',
                        'Make sure you are scanning the rifle QR code, not another cadet'
                    ];
                }
                showScanResult('Please scan a rifle QR code to complete assignment', 'error', debugInfo);
                scanStats.failedScans++;
                updateScanStats();
                setCooldown();
                return;
            }
            
            // Check if this rifle has already been scanned in this session
            if (scannedItems.rifles.has(qrData.rifle_id)) {
                if (debugInfo) {
                    debugInfo.finalError = 'Duplicate Rifle Scan';
                    debugInfo.finalErrorDetails = `Rifle ${qrData.rifle_id} already scanned in this session`;
                    debugInfo.validationErrors = ['Rifle has already been processed'];
                    debugInfo.suggestions = [
                        'Scan a different rifle QR code',
                        'Use the reset button to clear session if needed'
                    ];
                }
                
                const warningHTML = `
                    <div style="text-align: center; margin-bottom: var(--spacing-md);">
                        <h3 style="color: var(--warning); margin-bottom: var(--spacing-xs);">⚠️ Rifle Already Assigned</h3>
                        <p style="margin: var(--spacing-xs) 0;">Rifle ${qrData.rifle_id} has already been assigned in this session.</p>
                    </div>
                    <div style="text-align: center; margin-top: var(--spacing-md); padding: var(--spacing-sm); background-color: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> You can scan a different rifle to complete the assignment
                        </p>
                    </div>
                `;
                
                showScanResult(warningHTML, 'warning', debugInfo);
                scanStats.failedScans++;
                updateScanStats();
                
                // Keep workflow in waiting_for_rifle state to allow scanning other rifles
                setTimeout(() => {
                    const statusDiv = document.getElementById('scan-status');
                    if (statusDiv) {
                        statusDiv.innerHTML = '<i class="fas fa-qrcode"></i> Scan a different rifle QR code';
                        statusDiv.className = 'scan-status waiting';
                    }
                }, 2000);
                
                setCooldown();
                return;
            }
            
            // Process the rifle assignment
            await processRifleAssignment(assignmentWorkflow.cadetData, qrData);
            
            // Reset workflow
            assignmentWorkflow = {
                step: 'idle',
                cadetData: null,
                rifleData: null
            };
        }
        
    } catch (error) {
        console.error('Error in assignment workflow:', error);
        showScanResult('Error processing assignment workflow', 'error');
        scanStats.failedScans++;
        updateScanStats();
        setCooldown();
        
        // Reset workflow on error
        assignmentWorkflow = {
            step: 'idle',
            cadetData: null,
            rifleData: null
        };
    }
}

/**
 * Processes rifle assignment operation
 */
async function processRifleAssignment(cadetData, rifleData) {
    try {
        // Check if rifle is available with retry mechanism
        const result = await retryOperation(async () => {
            const response = await fetch(API_RIFLE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'check_rifle_status',
                    rifle_id: rifleData.rifle_id
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        }, 3, 1000);
        
        if (!result.success) {
            showScanResult(result.message || 'Error checking rifle status', 'error');
            scanStats.failedScans++;
            updateScanStats();
            setCooldown();
            return;
        }
        
        if (result.rifle.status !== 'available') {
            // Enhanced message for already assigned rifles
            let warningHTML;
            if (result.rifle.status === 'assigned') {
                const assignedTo = result.rifle.assigned_to || 'Unknown';
                warningHTML = `
                    <div style="text-align: center; margin-bottom: var(--spacing-md);">
                        <h3 style="color: var(--warning); margin-bottom: var(--spacing-xs);">⚠️ Rifle Already Assigned</h3>
                        <p style="margin: var(--spacing-xs) 0;">Rifle ${rifleData.rifle_id} is already assigned to ${assignedTo}.</p>
                    </div>
                    <div style="text-align: center; margin-top: var(--spacing-md); padding: var(--spacing-sm); background-color: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> Scan a different rifle to complete the assignment
                        </p>
                    </div>
                `;
            } else {
                warningHTML = `
                    <div style="text-align: center; margin-bottom: var(--spacing-md);">
                        <h3 style="color: var(--warning); margin-bottom: var(--spacing-xs);">⚠️ Rifle Not Available</h3>
                        <p style="margin: var(--spacing-xs) 0;">Rifle ${rifleData.rifle_id} is not available (Status: ${result.rifle.status}).</p>
                    </div>
                    <div style="text-align: center; margin-top: var(--spacing-md); padding: var(--spacing-sm); background-color: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                            <i class="fas fa-info-circle"></i> Scan a different rifle to complete the assignment
                        </p>
                    </div>
                `;
            }
            
            showScanResult(warningHTML, 'warning');
            scanStats.failedScans++;
            updateScanStats();
            
            // Keep workflow in waiting_for_rifle state to allow scanning other rifles
            setTimeout(() => {
                const statusDiv = document.getElementById('scan-status');
                if (statusDiv) {
                    statusDiv.innerHTML = '<i class="fas fa-qrcode"></i> Scan a different rifle QR code';
                    statusDiv.className = 'scan-status waiting';
                }
            }, 2000);
            
            setCooldown();
            return;
        }
        
        // Assign the rifle with retry mechanism
        const assignResult = await retryOperation(async () => {
            const assignPayload = {
                action: 'assign_rifle',
                rifle_id: rifleData.rifle_id,
                profile_id: cadetData.profile_id || cadetData.cadet_profile_id,
                cadet_id: cadetData.student_id || cadetData.cadet_id
            };
            console.log('📦 Assign Rifle Payload:', assignPayload);
            const response = await fetch(API_RIFLE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(assignPayload)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        }, 3, 1000);
        
        if (assignResult.success) {
            // Add rifle to scanned items to prevent duplicate scanning
            scannedItems.rifles.add(rifleData.rifle_id);
            
            const resultHTML = `
                <div style="text-align: center; margin-bottom: var(--spacing-md);">
                    <h3 style="color: var(--success); margin-bottom: var(--spacing-xs);">✅ Rifle Assigned Successfully!</h3>
                    <span class="status-indicator status-assigned">ASSIGNED</span>
                </div>
                <div class="rifle-info">
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Rifle ID</div>
                        <div class="rifle-detail-value">${rifleData.rifle_id}</div>
                    </div>
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Serial Number</div>
                        <div class="rifle-detail-value">${rifleData.serial_number || 'N/A'}</div>
                    </div>
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Assigned To</div>
                        <div class="rifle-detail-value">${cadetData.name}</div>
                    </div>
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Cadet ID</div>
                        <div class="rifle-detail-value">${cadetData.profile_id || cadetData.cadet_profile_id || cadetData.student_id || cadetData.cadet_id}</div>
                    </div>
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Assignment Time</div>
                        <div class="rifle-detail-value">${new Date().toLocaleString()}</div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: var(--spacing-md); padding: var(--spacing-sm); background-color: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                        <i class="fas fa-info-circle"></i> Ready to scan another cadet for assignment
                    </p>
                </div>
            `;
            
            showScanResult(resultHTML, 'success');
            scanStats.successfulScans++;
            updateScanStats();
            loadRecentActivities();
            
            // Enable continuous scanning by resetting workflow to ready state
            setTimeout(() => {
                assignmentWorkflow = {
                    step: 'waiting_for_cadet',
                    cadetData: null,
                    rifleData: null
                };
                
                // Update status to indicate ready for next scan
                const statusDiv = document.getElementById('scan-status');
                if (statusDiv) {
                    statusDiv.innerHTML = '<i class="fas fa-qrcode"></i> Ready to scan next cadet QR code';
                    statusDiv.className = 'scan-status ready';
                }
            }, 2000); // 2 second delay to show success message
        } else {
            showScanResult(assignResult.message || 'Failed to assign rifle', 'error');
            scanStats.failedScans++;
            updateScanStats();
        }
        
    } catch (error) {
        console.error('Error in rifle assignment:', error);
        
        // Use enhanced network error handling
        const errorInfo = handleNetworkError(error, 'rifle assignment');
        
        scanStats.failedScans++;
        updateScanStats();
    }
    
    setCooldown();
}

/**
 * Processes rifle return operation
 */
async function processRifleReturn(cadetData, debugInfo = null) {
    try {
        // Get cadet's assigned rifle with retry mechanism
        const result = await retryOperation(async () => {
            const response = await fetch(API_RIFLE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'get_cadet_rifle',
                    profile_id: cadetData.profile_id || cadetData.cadet_profile_id,
                    cadet_id: cadetData.student_id || cadetData.cadet_id
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        }, 3, 1000);
        
        if (!result.success) {
            if (debugInfo) {
                debugInfo.finalError = 'API Error - Check Cadet Rifle';
                debugInfo.finalErrorDetails = result.message || 'Error checking cadet rifle assignment';
                debugInfo.validationErrors = ['Failed to retrieve cadet rifle information'];
                debugInfo.suggestions = [
                    'Check if the cadet ID is valid',
                    'Verify database connection',
                    'Check API logs for detailed error information'
                ];
            }
            showScanResult(result.message || 'Error checking cadet rifle assignment', 'error', debugInfo);
            scanStats.failedScans++;
            updateScanStats();
            setCooldown();
            return;
        }
        
        if (!result.rifle) {
            if (debugInfo) {
                debugInfo.finalError = 'No Rifle Assignment Found';
                debugInfo.finalErrorDetails = `Cadet ${cadetData.profile_id || cadetData.cadet_profile_id || cadetData.student_id || cadetData.cadet_id} has no assigned rifle`;
                debugInfo.validationErrors = ['Cadet has no rifle to return'];
                debugInfo.suggestions = [
                    'Verify the cadet has been assigned a rifle',
                    'Check if the rifle was already returned',
                    'Use assignment mode to assign a rifle first'
                ];
            }
            showScanResult(`Cadet ${cadetData.profile_id || cadetData.cadet_profile_id || cadetData.student_id || cadetData.cadet_id} has no assigned rifle`, 'warning', debugInfo);
            scanStats.failedScans++;
            updateScanStats();
            setCooldown();
            return;
        }
        
        // Return the rifle with retry mechanism
        const returnResult = await retryOperation(async () => {
            const response = await fetch(API_RIFLE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'return_rifle',
                    rifle_id: result.rifle.rifle_id,
                    profile_id: cadetData.profile_id || cadetData.cadet_profile_id,
                    cadet_id: cadetData.student_id || cadetData.cadet_id
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        }, 3, 1000);
        
        if (returnResult.success) {
            // Remove rifle from scanned items when returned (allows re-assignment)
            scannedItems.rifles.delete(result.rifle.rifle_id);
            
            if (debugInfo) {
                debugInfo.finalResult = {
                    operation: 'rifle_return',
                    rifle_id: result.rifle.rifle_id,
                    cadet_id: cadetData.student_id || cadetData.cadet_id,
                    timestamp: new Date().toISOString()
                };
                debugInfo.finalError = null;
                debugInfo.finalErrorDetails = null;
                debugInfo.validationErrors = [];
            }
            
            const resultHTML = `
                <div style="text-align: center; margin-bottom: var(--spacing-md);">
                    <h3 style="color: var(--success); margin-bottom: var(--spacing-xs);">✅ Rifle Returned Successfully!</h3>
                    <span class="status-indicator status-available">AVAILABLE</span>
                </div>
                <div class="rifle-info">
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Rifle ID</div>
                        <div class="rifle-detail-value">${result.rifle.rifle_id}</div>
                    </div>
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Serial Number</div>
                        <div class="rifle-detail-value">${result.rifle.serial_number || 'N/A'}</div>
                    </div>
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Returned By</div>
                        <div class="rifle-detail-value">${cadetData.name}</div>
                    </div>
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Cadet ID</div>
                        <div class="rifle-detail-value">${cadetData.student_id || cadetData.cadet_id}</div>
                    </div>
                    <div class="rifle-detail">
                        <div class="rifle-detail-label">Return Time</div>
                        <div class="rifle-detail-value">${new Date().toLocaleString()}</div>
                    </div>
                </div>
            `;
            
            showScanResult(resultHTML, 'success', debugInfo);
            scanStats.successfulScans++;
            updateScanStats();
            loadRecentActivities();
        } else {
            if (debugInfo) {
                debugInfo.finalError = 'API Error - Return Rifle Failed';
                debugInfo.finalErrorDetails = returnResult.message || 'Failed to return rifle';
                debugInfo.validationErrors = ['Rifle return operation failed'];
                debugInfo.suggestions = [
                    'Check if the rifle is currently assigned to this cadet',
                    'Verify database connection',
                    'Check API logs for detailed error information'
                ];
            }
            showScanResult(returnResult.message || 'Failed to return rifle', 'error', debugInfo);
            scanStats.failedScans++;
            updateScanStats();
        }
        
    } catch (error) {
        console.error('Error in rifle return:', error);
        
        // Use enhanced network error handling
        const errorInfo = handleNetworkError(error, 'rifle return');
        
        // Merge with existing debug info if available
        if (debugInfo) {
            debugInfo.finalError = errorInfo.finalError;
            debugInfo.finalErrorDetails = errorInfo.error;
            debugInfo.validationErrors = ['Unexpected error during rifle return process'];
            debugInfo.suggestions = errorInfo.suggestions;
            debugInfo.networkStatus = errorInfo.networkStatus;
            debugInfo.timestamp = errorInfo.timestamp;
        }
        
        scanStats.failedScans++;
        updateScanStats();
    }
    
    setCooldown();
}

/**
 * Processes attendance recording operation
 */
async function processAttendance(cadetData, attendanceData, debugInfo = null) {
    try {
        // Prepare attendance data for submission
        const attendancePayload = {
            action: 'record_attendance',
            profile_id: cadetData.profile_id || cadetData.cadet_profile_id || null,
            cadet_id: cadetData.student_id || cadetData.cadet_id,
            cadet_name: cadetData.name,
            school_year: attendanceData.schoolYear,
            semester: attendanceData.semester,
            event_name: attendanceData.eventName,
            timestamp: new Date().toISOString()
        };
        
        // Record attendance with retry mechanism
        console.log('Posting attendance payload to unified API:', API_ATTENDANCE_URL, attendancePayload);
        const result = await retryOperation(async () => {
            const response = await fetch(API_ATTENDANCE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify(attendancePayload)
            });
            const raw = await response.text();
            const text = raw.trim().replace(/^\uFEFF/, '');
            if (!response.ok) {
                console.warn('Attendance API non-OK response:', response.status, text.substring(0,200));
                throw new Error(`[AttendanceAPI] HTTP ${response.status}: ${response.statusText} | Body: ${text.substring(0,200)}`);
            }
            let json;
            try {
                json = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON from attendance API:', text.substring(0,300));
                throw new Error(`[AttendanceAPI] Invalid JSON: ${text.substring(0,200)}`);
            }
            return json;
        }, 3, 1000);
        
        if (result.success) {
            if (debugInfo) {
                debugInfo.finalResult = {
                    operation: 'attendance_record',
                    cadet_id: cadetData.student_id || cadetData.cadet_id,
                    event_name: attendanceData.eventName,
                    timestamp: new Date().toISOString()
                };
                debugInfo.finalError = null;
                debugInfo.finalErrorDetails = null;
                debugInfo.validationErrors = [];
            }
            
            const resultHTML = `
                <div style="text-align: center; margin-bottom: var(--spacing-md);">
                    <h3 style="color: var(--success); margin-bottom: var(--spacing-xs);">✅ Attendance Recorded Successfully!</h3>
                    <span class="status-indicator status-present">PRESENT</span>
                </div>
                <div class="attendance-info">
                    <div class="attendance-detail">
                        <div class="attendance-detail-label">Cadet Name</div>
                        <div class="attendance-detail-value">${cadetData.name}</div>
                    </div>
                    <div class="attendance-detail">
                        <div class="attendance-detail-label">Cadet ID</div>
                        <div class="attendance-detail-value">${cadetData.student_id || cadetData.cadet_id}</div>
                    </div>
                    <div class="attendance-detail">
                        <div class="attendance-detail-label">Training Day</div>
                        <div class="attendance-detail-value">${attendanceData.eventName}</div>
                    </div>
                    <div class="attendance-detail">
                        <div class="attendance-detail-label">School Year</div>
                        <div class="attendance-detail-value">${attendanceData.schoolYear}</div>
                    </div>
                    <div class="attendance-detail">
                        <div class="attendance-detail-label">Semester</div>
                        <div class="attendance-detail-value">${attendanceData.semester}</div>
                    </div>
                    <div class="attendance-detail">
                        <div class="attendance-detail-label">Time</div>
                        <div class="attendance-detail-value">${new Date().toLocaleString()}</div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: var(--spacing-md); padding: var(--spacing-sm); background-color: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                    <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                        <i class="fas fa-info-circle"></i> Ready to scan next cadet for attendance
                    </p>
                </div>
            `;
            
            showScanResult(resultHTML, 'success', debugInfo);
            scanStats.successfulScans++;
            updateScanStats();

            // Auto-advance Training Day (and semester after 15TD) when no custom TD is used
            try {
                const customTD = (document.getElementById('event-name-custom')?.value || '').trim();
                const tdSelect = document.getElementById('event-name');
                const semSelect = document.getElementById('semester');
                if (customTD === '' && tdSelect && semSelect) {
                    const currentTDVal = tdSelect.value || '';
                    const m = currentTDVal.match(/^(\d{1,2})TD$/i);
                    let sem = semSelect.value || '1st';
                    if (m) {
                        let n = parseInt(m[1], 10);
                        if (sem === '1st') {
                            if (n >= 15) {
                                // rollover to 2nd sem, 1TD
                                semSelect.value = '2nd';
                                tdSelect.value = '1TD';
                                updateCameraStatus('Advanced defaults to 2nd Semester, 1TD', 'success');
                            } else {
                                tdSelect.value = `${n + 1}TD`;
                                updateCameraStatus(`Advanced defaults to ${n + 1}TD`, 'success');
                            }
                        } else {
                            if (n < 15) {
                                tdSelect.value = `${n + 1}TD`;
                                updateCameraStatus(`Advanced defaults to ${n + 1}TD`, 'success');
                            } else {
                                // Stay at 15TD in 2nd sem
                                tdSelect.value = '15TD';
                            }
                        }
                        // Persist new defaults
                        saveAttendanceFormState();
                    }
                }
            } catch (advErr) {
                console.warn('Auto-advance TD failed:', advErr);
            }
            
            // Enable continuous scanning for attendance
            setTimeout(() => {
                const statusDiv = document.getElementById('scan-status');
                if (statusDiv) {
                    statusDiv.innerHTML = '<i class="fas fa-qrcode"></i> Ready to scan next cadet QR code';
                    statusDiv.className = 'scan-status ready';
                }
            }, 2000); // 2 second delay to show success message
            
        } else {
            const msg = (result && result.message ? String(result.message) : '').toLowerCase();
            const looksDuplicate = msg.includes('already') || msg.includes('duplicate') || msg.includes('exist');
            if (looksDuplicate) {
                const cadetId = cadetData.student_id || cadetData.cadet_id || 'Unknown';
                const warningHTML = `
                    <div style="text-align: center; margin-bottom: var(--spacing-md);">
                        <h3 style="color: var(--warning); margin-bottom: var(--spacing-xs);">⚠️ Already Marked Present</h3>
                        <p style="margin: var(--spacing-xs) 0;">Cadet ${cadetId} is already marked present for this event.</p>
                    </div>
                    <div style="text-align: center; margin-top: var(--spacing-md); padding: var(--spacing-sm); background-color: var(--bg-secondary); border-radius: var(--border-radius); border: 1px solid var(--border-color);">
                        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9em;">
                            <i class=\"fas fa-info-circle\"></i> You can continue scanning other cadets
                        </p>
                    </div>
                `;
                showScanResult(warningHTML, 'warning', debugInfo);
                // Do not count duplicate as failure
                updateScanStats();
                // Add to session-scanned set to avoid repeated warnings for same event
                try {
                    const attendanceKey = `${cadetId}-${attendanceData.eventName}-${attendanceData.schoolYear}-${attendanceData.semester}`;
                    scannedItems.cadets.add(attendanceKey);
                } catch (e) { /* ignore */ }
            } else {
                if (debugInfo) {
                    debugInfo.finalError = 'API Error - Attendance Recording Failed';
                    debugInfo.finalErrorDetails = result.message || 'Failed to record attendance';
                    debugInfo.validationErrors = ['Attendance recording operation failed'];
                    debugInfo.suggestions = [
                        'Check if the cadet ID is valid',
                        'Verify database connection',
                        'Check API logs for detailed error information',
                        'Ensure attendance table exists and is accessible'
                    ];
                }
                showScanResult(result.message || 'Failed to record attendance', 'error', debugInfo);
                scanStats.failedScans++;
                updateScanStats();
            }
        }
        
    } catch (error) {
        console.error('Error in attendance recording:', error);
        // Fallback to legacy endpoint if unified API failed
        try {
            const base = getAppBasePath();
            const fallbackUrl = `${base}QR/record_attendance.php`;
            const tdMatch = (attendanceData.eventName || '').match(/^(\d{1,2})TD$/i);
            const tdNum = tdMatch ? tdMatch[1] : attendanceData.eventName;
            let semNum = attendanceData.semester;
            if (typeof semNum === 'string') {
                semNum = semNum.toLowerCase() === '1st' ? '1' : (semNum.toLowerCase() === '2nd' ? '2' : semNum);
            }
            const fallbackPayload = {
                student_id: cadetData.student_id || cadetData.cadet_id,
                cadet_name: cadetData.name || cadetData.full_name || '',
                td: tdNum,
                semester: semNum,
                school_year: attendanceData.schoolYear,
                event_name: attendanceData.eventName,
                timestamp: new Date().toISOString()
            };
            console.warn('Unified API failed; trying legacy attendance endpoint:', fallbackUrl, fallbackPayload);
            const resp = await fetch(fallbackUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store',
                body: JSON.stringify(fallbackPayload)
            });
            const raw = await resp.text();
            const text = raw.trim().replace(/^\uFEFF/, '');
            let json;
            try { json = JSON.parse(text); } catch (e) { throw new Error(`[AttendanceLegacy] Invalid JSON: ${text.substring(0,200)}`); }
            if (json && json.success) {
                const resultHTML = `
                    <div style="text-align: center; margin-bottom: var(--spacing-md);">
                        <h3 style="color: var(--success); margin-bottom: var(--spacing-xs);">✅ Attendance Recorded (Fallback)!</h3>
                        <span class="status-indicator status-present">PRESENT</span>
                    </div>
                `;
                showScanResult(resultHTML, 'success', debugInfo);
                scanStats.successfulScans++;
                updateScanStats();
                setCooldown();
                return; // stop here since fallback succeeded
            }
            if (!resp.ok) {
                throw new Error(`[AttendanceLegacy] HTTP ${resp.status}: ${resp.statusText} | Body: ${text.substring(0,200)}`);
            }
            throw new Error(json && json.message ? `[AttendanceLegacy] ${json.message}` : '[AttendanceLegacy] Legacy attendance failed');
        } catch (fbErr) {
            console.error('Legacy attendance fallback also failed:', fbErr);
            // Use enhanced network error handling
            const errorInfo = handleNetworkError(fbErr, 'attendance recording');
            // Merge with existing debug info if available
            if (debugInfo) {
                debugInfo.finalError = errorInfo.finalError;
                debugInfo.finalErrorDetails = errorInfo.error;
                debugInfo.validationErrors = ['Unexpected error during attendance recording process'];
                debugInfo.suggestions = errorInfo.suggestions;
                debugInfo.networkStatus = errorInfo.networkStatus;
                debugInfo.timestamp = errorInfo.timestamp;
            }
            scanStats.failedScans++;
            updateScanStats();
        }
    }
    
    setCooldown();
}

/**
 * Callback when QR code scanning fails
 */
// Enhanced scan assist variables
let scanAssistMode = false;
let scanAttempts = [];
let currentScanAttempt = 0;
let scanAssistTimer = null;
let lastSuccessfulScanTime = 0;

// Multiple scan configurations for different surface conditions
const scanConfigurations = [
    {
        name: 'standard',
        fps: 10,
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        videoConstraints: {
            focusMode: 'continuous',
            exposureMode: 'continuous',
            whiteBalanceMode: 'continuous'
        }
    },
    {
        name: 'high_contrast',
        fps: 8,
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        videoConstraints: {
            focusMode: 'single-shot',
            exposureMode: 'manual',
            exposureCompensation: 0.5,
            contrast: 1.2,
            brightness: 0.1
        }
    },
    {
        name: 'low_light',
        fps: 6,
        experimentalFeatures: { useBarCodeDetectorIfSupported: true },
        videoConstraints: {
            focusMode: 'continuous',
            exposureMode: 'continuous',
            exposureCompensation: 1.0,
            brightness: 0.2,
            iso: 800
        }
    },
    {
        name: 'uneven_surface',
        fps: 5,
        experimentalFeatures: { useBarCodeDetectorIfSupported: false },
        videoConstraints: {
            focusMode: 'macro',
            exposureMode: 'continuous',
            zoom: 1.5,
            torch: false
        }
    }
];

function onScanFailure(error) {
    // Track consecutive scan failures for better error handling
    if (!window.consecutiveScanFailures) {
        window.consecutiveScanFailures = 0;
        window.lastScanFailureTime = 0;
        window.scanFailureHistory = [];
    }
    
    const now = Date.now();
    const timeSinceLastFailure = now - window.lastScanFailureTime;
    const timeSinceLastSuccess = now - lastSuccessfulScanTime;
    
    // Reset counter if it's been more than 10 seconds since last failure
    if (timeSinceLastFailure > 10000) {
        window.consecutiveScanFailures = 0;
        window.scanFailureHistory = [];
    }
    
    window.consecutiveScanFailures++;
    window.lastScanFailureTime = now;
    window.scanFailureHistory.push({
        timestamp: now,
        error: error.toString(),
        timeSinceLastSuccess: timeSinceLastSuccess
    });
    
    // Enhanced user feedback with progressive assistance
    if (window.consecutiveScanFailures > 30 && timeSinceLastFailure < 5000) {
        const statusElement = document.getElementById('camera-status');
        if (statusElement && !statusElement.textContent.includes('scanning difficulties')) {
            if (window.consecutiveScanFailures > 100) {
                updateCameraStatus('🔍 Scan Assist Mode activated - Trying enhanced detection...', 'info');
                activateScanAssistMode();
            } else if (window.consecutiveScanFailures > 60) {
                updateCameraStatus('💡 Tip: Try better lighting, hold QR steady, or use manual entry below', 'warning');
                showManualEntryOption();
            } else {
                updateCameraStatus('📱 Having scanning difficulties? Try better lighting or hold QR code steady', 'warning');
            }
        }
    }
    
    // Log for debugging with enhanced information
    if (window.consecutiveScanFailures % 25 === 0) {
        console.log(`🔍 QR Scan Analysis (${window.consecutiveScanFailures} consecutive failures):`);
        console.log(`   - Time since last success: ${Math.round(timeSinceLastSuccess/1000)}s`);
        console.log(`   - Recent error pattern:`, window.scanFailureHistory.slice(-5));
        console.log(`   - Scan assist mode: ${scanAssistMode ? 'ACTIVE' : 'INACTIVE'}`);
        
        // Suggest scan assist mode after persistent failures
        if (window.consecutiveScanFailures > 75 && !scanAssistMode) {
            console.log('💡 Suggesting scan assist mode activation');
        }
    }
}

/**
 * Activates enhanced scan assist mode for difficult scanning conditions
 */
function activateScanAssistMode() {
    if (scanAssistMode) return;
    
    console.log('🚀 Activating Scan Assist Mode for enhanced QR detection');
    scanAssistMode = true;
    currentScanAttempt = 0;
    scanAttempts = [];
    
    // Show scan assist UI
    showScanAssistUI();
    
    // Start cycling through different scan configurations
    cycleScanConfigurations();
}

/**
 * Cycles through different scan configurations for better detection
 */
async function cycleScanConfigurations() {
    if (!scanAssistMode || !html5QrCode || !html5QrCode.isScanning) {
        return;
    }
    
    const config = scanConfigurations[currentScanAttempt % scanConfigurations.length];
    console.log(`🔄 Scan Assist: Trying configuration '${config.name}' (attempt ${currentScanAttempt + 1})`);
    
    try {
        // Update status to show current attempt
        updateCameraStatus(`🔍 Scan Assist: Trying ${config.name} detection (${currentScanAttempt + 1}/${scanConfigurations.length})`, 'info');
        
        // Apply new configuration by restarting scanner with new settings
        await restartScannerWithConfig(config);
        
        // Record this attempt
        scanAttempts.push({
            timestamp: Date.now(),
            configName: config.name,
            attempt: currentScanAttempt + 1
        });
        
        currentScanAttempt++;
        
        // Set timer for next configuration if this one doesn't work
        if (scanAssistTimer) {
            clearTimeout(scanAssistTimer);
        }
        
        scanAssistTimer = setTimeout(() => {
            if (scanAssistMode && currentScanAttempt < scanConfigurations.length * 2) {
                cycleScanConfigurations();
            } else {
                // All configurations tried, show manual entry
                console.log('🔍 Scan Assist: All configurations attempted, showing manual entry');
                updateCameraStatus('🔍 Scan Assist complete. Try manual entry below if QR scanning continues to fail.', 'warning');
                showManualEntryOption();
                deactivateScanAssistMode();
            }
        }, 8000); // Try each configuration for 8 seconds
        
    } catch (error) {
        console.error(`❌ Scan Assist: Configuration '${config.name}' failed:`, error);
        currentScanAttempt++;
        
        // Try next configuration immediately if this one fails to start
        setTimeout(() => {
            if (scanAssistMode) {
                cycleScanConfigurations();
            }
        }, 1000);
    }
}

/**
 * Restarts scanner with specific configuration
 */
async function restartScannerWithConfig(config) {
    if (!html5QrCode) return;
    
    try {
        // Stop current scanner
        if (html5QrCode.isScanning) {
            await html5QrCode.stop();
        }
        
        // Wait a moment for cleanup
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Get current QR box size
        const width = window.innerWidth;
        const isMobile = isMobileDevice();
        const qrboxSize = isMobile ? Math.min(Math.min(width, window.innerHeight) * 0.8, 300) : (width < 600 ? Math.min(width * 0.7, 250) : 250);
        
        // Enhanced scan configuration with the specific config
        const scanConfig = {
            fps: config.fps,
            qrbox: { width: qrboxSize, height: qrboxSize },
            aspectRatio: 1.0,
            disableFlip: false,
            rememberLastUsedCamera: true,
            supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
            experimentalFeatures: config.experimentalFeatures
        };
        
        // Enhanced camera configuration
        const cameraConfig = {
            facingMode: "environment",
            width: { ideal: isMobile ? 1280 : 1920, min: 640, max: 1920 },
            height: { ideal: isMobile ? 720 : 1080, min: 480, max: 1080 },
            frameRate: { ideal: config.fps, min: 5, max: 30 },
            ...config.videoConstraints
        };
        
        // Start scanner with new configuration
        await html5QrCode.start(
            cameraConfig,
            scanConfig,
            onScanSuccess,
            onScanFailure
        );
        
        console.log(`✅ Scan Assist: Successfully applied '${config.name}' configuration`);
        
    } catch (error) {
        console.error(`❌ Scan Assist: Failed to apply '${config.name}' configuration:`, error);
        throw error;
    }
}

/**
 * Deactivates scan assist mode
 */
function deactivateScanAssistMode() {
    console.log('🔄 Deactivating Scan Assist Mode');
    scanAssistMode = false;
    currentScanAttempt = 0;
    
    if (scanAssistTimer) {
        clearTimeout(scanAssistTimer);
        scanAssistTimer = null;
    }
    
    // Hide scan assist UI
    hideScanAssistUI();
    
    // Reset to standard configuration
    if (html5QrCode && html5QrCode.isScanning) {
        restartScannerWithConfig(scanConfigurations[0]).catch(error => {
            console.error('❌ Failed to reset to standard configuration:', error);
        });
    }
}

/**
 * Shows scan assist UI elements
 */
function showScanAssistUI() {
    // Create or show scan assist indicator
    let assistIndicator = document.getElementById('scan-assist-indicator');
    if (!assistIndicator) {
        assistIndicator = document.createElement('div');
        assistIndicator.id = 'scan-assist-indicator';
        assistIndicator.innerHTML = `
            <div style="background: linear-gradient(45deg, #4CAF50, #2196F3); color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.2); animation: pulse 2s infinite;">
                🔍 SCAN ASSIST ACTIVE
            </div>
        `;
        assistIndicator.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideInRight 0.5s ease-out;
        `;
        
        // Add CSS animation if not exists
        if (!document.getElementById('scan-assist-styles')) {
            const style = document.createElement('style');
            style.id = 'scan-assist-styles';
            style.textContent = `
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
        }
        
        document.body.appendChild(assistIndicator);
    }
    assistIndicator.style.display = 'block';
}

/**
 * Hides scan assist UI elements
 */
function hideScanAssistUI() {
    const assistIndicator = document.getElementById('scan-assist-indicator');
    if (assistIndicator) {
        assistIndicator.style.display = 'none';
    }
}

/**
 * Shows manual entry option for when QR scanning fails
 */
function showManualEntryOption() {
    let manualEntryDiv = document.getElementById('manual-entry-option');
    if (!manualEntryDiv) {
        manualEntryDiv = document.createElement('div');
        manualEntryDiv.id = 'manual-entry-option';
        manualEntryDiv.innerHTML = `
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 16px; margin: 16px 0; text-align: center;">
                <h4 style="margin: 0 0 12px 0; color: #856404;">📝 Manual Entry Available</h4>
                <p style="margin: 0 0 12px 0; color: #856404; font-size: 14px;">Having trouble scanning? Enter the rifle number manually:</p>
                <div style="display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap;">
                    <input type="text" id="manual-rifle-input" placeholder="Enter rifle number" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 150px;" />
                    <button onclick="processManualEntry()" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px;">Submit</button>
                    <button onclick="hideManualEntryOption()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px;">Cancel</button>
                </div>
            </div>
        `;
        
        // Insert after camera status or at the beginning of scanner container
        const cameraStatus = document.getElementById('camera-status');
        const scannerContainer = document.querySelector('.scanner-container') || document.body;
        
        if (cameraStatus && cameraStatus.parentNode) {
            cameraStatus.parentNode.insertBefore(manualEntryDiv, cameraStatus.nextSibling);
        } else {
            scannerContainer.appendChild(manualEntryDiv);
        }
    }
    manualEntryDiv.style.display = 'block';
}

/**
 * Hides manual entry option
 */
function hideManualEntryOption() {
    const manualEntryDiv = document.getElementById('manual-entry-option');
    if (manualEntryDiv) {
        manualEntryDiv.style.display = 'none';
    }
}

/**
 * Processes manual rifle number entry
 */
function processManualEntry() {
    const input = document.getElementById('manual-rifle-input');
    if (!input) return;
    
    const rifleNumber = input.value.trim();
    if (!rifleNumber) {
        alert('Please enter a rifle number');
        return;
    }
    
    console.log('📝 Processing manual rifle entry:', rifleNumber);
    
    // Create a mock QR data object for manual entry
    const mockQrData = {
        rifle_id: rifleNumber,
        serial: rifleNumber,
        type: 'rifle',
        manual_entry: true,
        timestamp: new Date().toISOString()
    };
    
    // Process as if it was a successful QR scan
    try {
        // Hide manual entry option
        hideManualEntryOption();
        
        // Reset scan failure counters
        window.consecutiveScanFailures = 0;
        lastSuccessfulScanTime = Date.now();
        
        // Process the manual entry through the normal workflow
        if (currentOperation === 'assign') {
            if (assignmentWorkflow.step === 'waiting_for_rifle') {
                processRifleQR(mockQrData);
            } else {
                updateCameraStatus('⚠️ Manual entry: Please scan cadet QR first for assignment', 'warning');
            }
        } else {
            // For return operation, we need cadet info first
            updateCameraStatus('⚠️ Manual entry: Please scan cadet QR first for rifle return', 'warning');
        }
        
        // Clear input
        input.value = '';
        
    } catch (error) {
        console.error('❌ Manual entry processing failed:', error);
        updateCameraStatus('❌ Manual entry failed. Please try again.', 'error');
    }
}

/**
 * Stops the QR code scanner
 */
function stopScanner() {
    if (scannerActive && html5QrCode) {
        try {
            // Stop HTML5-QRCode scanner
            html5QrCode.stop().then(() => {
                console.log('✅ HTML5-QRCode scanner stopped successfully');
                
                // Show start button and hide stop button
                document.getElementById('start-scanner-btn').style.display = 'block';
                document.getElementById('stop-scanner-btn').style.display = 'none';
                
                // Hide reset scan button when scanner is stopped
                const resetScanBtn = document.getElementById('reset-scan-btn');
                if (resetScanBtn) {
                    resetScanBtn.style.display = 'none';
                }
                
                updateCameraStatus('Scanner stopped. Click "Start Scanner" to scan again.');
                scannerActive = false;
                
                // Hide scanner controls
                const scannerControls = document.getElementById('scanner-controls');
                if (scannerControls) {
                    scannerControls.style.display = 'none';
                }
                
            }).catch(err => {
                console.error('❌ Error stopping HTML5-QRCode scanner:', err);
                updateCameraStatus('Error stopping scanner', 'error');
                scannerActive = false;
            });
        } catch (err) {
            console.error('❌ Error stopping scanner:', err);
            updateCameraStatus('Error stopping scanner', 'error');
            scannerActive = false;
        }
    }
}

/**
 * Callback when QR code scan fails
 */
function onScanFailure(error) {
    // Only log errors that aren't normal "no QR found" messages
    if (error && !error.includes('No QR code found') && !error.includes('NotFoundException')) {
        console.log('⚠️ Scan failure:', error);
    }
}

// ZXing-specific functions removed - HTML5-QRCode handles scanning internally

/**
 * Sets a cooldown period to prevent rapid scanning
 */
function setCooldown() {
    scanCooldown = true;
    setTimeout(() => {
        scanCooldown = false;
        // Also ensure submission is unlocked after cooldown
        isSubmitting = false;
    }, 3000); // 3 second cooldown
}

/**
 * Updates the scan statistics display
 */
function updateScanStats() {
    const statsElement = document.getElementById('scan-stats');
    if (statsElement) {
        statsElement.innerHTML = `
            <div class="stat-item">
                <span class="stat-value">${scanStats.totalScanned}</span>
                <span class="stat-label">Total Scans</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">${scanStats.successfulScans}</span>
                <span class="stat-label">Successful</span>
            </div>
            <div class="stat-item">
                <span class="stat-value">${scanStats.failedScans}</span>
                <span class="stat-label">Failed</span>
            </div>
        `;
    }
}

/**
 * Loads and displays recent rifle activities
 */
async function loadRecentActivities() {
    try {
        const result = await retryOperation(async () => {
            const response = await fetch(API_RIFLE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'get_recent_activities',
                    limit: 10
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        });
        
        if (result.success && result.activities) {
            displayRecentActivities(result.activities);
        } else {
            console.warn('Failed to load recent activities:', result.error || 'Unknown error');
            // Show empty state instead of error for recent activities
            displayRecentActivities([]);
        }
    } catch (error) {
        console.error('Error loading recent activities:', error);
        // Use enhanced error handling but don't show UI error for recent activities
        // Just display empty state
        displayRecentActivities([]);
    }
}

/**
 * Displays recent activities in the UI
 */
function displayRecentActivities(activities) {
    const activitiesElement = document.getElementById('recent-activities');
    
    if (activities.length === 0) {
        activitiesElement.innerHTML = '<p style="text-align: center; color: var(--text-secondary); margin: var(--spacing-lg);">No recent activities</p>';
        return;
    }
    
    let html = '';
    activities.forEach(activity => {
        const time = new Date(activity.timestamp).toLocaleString();
        const typeClass = activity.action === 'assigned' ? 'type-assign' : 'type-return';
        const actionIcon = activity.action === 'assigned' ? '➡️' : '⬅️';
        const actionText = activity.action === 'assigned' ? 'ASSIGNED' : 'RETURNED';
        
        html += `
            <div class="activity-item">
                <div class="activity-icon ${typeClass}">
                    ${actionIcon}
                </div>
                <div class="activity-info">
                    <div class="activity-main">
                        <span class="rifle-info">Rifle #${activity.rifle_id || activity.serial_number || 'N/A'}</span>
                        <span class="activity-type ${typeClass}">${actionText}</span>
                    </div>
                    <div class="activity-details">
                        <span class="cadet-info">${activity.cadet_name || activity.cadet_id || 'Unknown Cadet'}</span>
                        <span class="activity-time">
                            <i class="fas fa-clock"></i> ${time}
                        </span>
                    </div>
                </div>
                <div class="activity-status">
                    <span class="status-indicator ${activity.action === 'assigned' ? 'status-assigned' : 'status-available'}"></span>
                </div>
            </div>
        `;
    });
    
    activitiesElement.innerHTML = html;
}

/**
 * Loads and displays current rifle assignments
 */
async function loadCurrentAssignments() {
    try {
        const result = await retryOperation(async () => {
            const response = await fetch(API_RIFLE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'get_current_assignments'
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        });
        
        if (result.success && result.assignments) {
            displayCurrentAssignments(result.assignments);
        } else {
            console.warn('Failed to load current assignments:', result.error || 'Unknown error');
            displayCurrentAssignments([]);
        }
    } catch (error) {
        console.error('Error loading current assignments:', error);
        displayCurrentAssignments([]);
    }
}

/**
 * Displays current assignments in the UI
 */
function displayCurrentAssignments(assignments) {
    const assignmentsElement = document.getElementById('current-assignments-list');
    
    if (assignments.length === 0) {
        assignmentsElement.innerHTML = '<p style="text-align: center; color: var(--text-secondary); margin: var(--spacing-lg);">No current assignments</p>';
        return;
    }
    
    let html = '';
    assignments.forEach(assignment => {
        const assignedTime = new Date(assignment.assigned_at).toLocaleString();
        const cadetName = assignment.cadet_name || `${assignment.first_name || ''} ${assignment.last_name || ''}`.trim() || 'Unknown Cadet';
        
        html += `
            <div class="assignment-item">
                <div class="assignment-left">
                    <div class="assignment-rifle">Rifle #${assignment.rifle_number || assignment.serial_number || 'N/A'}</div>
                    <div class="assignment-cadet">${cadetName}</div>
                    <div class="assignment-details">
                        Course: ${assignment.course || 'N/A'} | Platoon: ${assignment.platoon || 'N/A'}
                    </div>
                </div>
                <div class="assignment-right">
                    <div class="assignment-time">
                        <i class="fas fa-clock"></i> ${assignedTime}
                    </div>
                    <div class="assignment-details">
                        Assigned by: ${assignment.assigned_by || 'Unknown'}
                    </div>
                </div>
            </div>
        `;
    });
    
    assignmentsElement.innerHTML = html;
}

/**
 * Displays the scan result with appropriate styling
 */
function showScanResult(message, type, debugInfo = null) {
    const resultElement = document.getElementById('scan-result');
    resultElement.innerHTML = message;
    resultElement.style.display = 'block';
    
    // Remove existing classes
    resultElement.className = 'scan-result';
    
    // Add appropriate class based on result type
    if (type === 'error') {
        resultElement.classList.add('result-error');
    } else if (type === 'warning') {
        resultElement.classList.add('result-warning');
    } else {
        resultElement.classList.add('result-success');
    }
    
    // Update debug panel if debug info is available
    if (debugInfo && typeof updateDebugPanel === 'function') {
        // Add final error information to debug info
        if (type === 'error') {
            debugInfo.finalError = debugInfo.finalError || message;
            debugInfo.finalErrorDetails = debugInfo.finalErrorDetails || 'Scan failed';
            debugInfo.suggestions = debugInfo.suggestions || [
                'Verify QR code was generated by this system',
                'Check if encryption keys match between generation and scanning',
                'Ensure QR code is not corrupted or partially scanned',
                'Try scanning the QR code again with better lighting'
            ];
        } else if (type === 'success') {
            debugInfo.finalResult = debugInfo.finalResult || { status: 'success', message: message };
        }
        
        updateDebugPanel(debugInfo);
        
        // Show debug panel for errors, hide for success
        if (type === 'error') {
            showDebugPanel();
        }
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
    } else if (type === 'warning') {
        statusElement.style.color = '#f57c00';
    }
}

/**
 * Handles network errors and provides recovery options
 */
function handleNetworkError(error, operation = 'operation') {
    console.error(`Network error during ${operation}:`, error);
    
    let detail = '';
    try {
        const msg = (error && (error.message || error.toString())) || '';
        if (msg) {
            detail = ` Details: ${msg.substring(0, 300)}`;
        }
    } catch (_) {}
    let errorMessage = `Network error during ${operation}.${detail} `;
    let suggestions = [];
    
    if (!navigator.onLine) {
        errorMessage += 'No internet connection detected.';
        suggestions = [
            'Check your internet connection',
            'Try again when connection is restored',
            'Contact your network administrator if issues persist'
        ];
    } else if (error.name === 'TypeError' && error.message.includes('fetch')) {
        errorMessage += 'Unable to connect to server.';
        suggestions = [
            'Check if the server is running',
            'Verify the server URL is correct',
            'Try refreshing the page',
            'Contact your system administrator'
        ];
    } else {
        errorMessage += 'Please try again.';
        suggestions = [
            'Wait a moment and try again',
            'Check your internet connection',
            'Refresh the page if issues persist'
        ];
    }
    
    const debugInfo = {
        timestamp: new Date().toISOString(),
        operation: operation,
        error: error.message || error.toString(),
        networkStatus: navigator.onLine ? 'online' : 'offline',
        finalError: errorMessage,
        suggestions: suggestions
    };
    
    showScanResult(errorMessage, 'error', debugInfo);
    return debugInfo;
}

/**
 * Retries an async operation with exponential backoff
 */
async function retryOperation(operation, maxRetries = 3, baseDelay = 1000) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            return await operation();
        } catch (error) {
            if (attempt === maxRetries) {
                throw error;
            }
            
            const delay = baseDelay * Math.pow(2, attempt - 1);
            console.log(`Operation failed (attempt ${attempt}/${maxRetries}), retrying in ${delay}ms...`);
            
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }
}

// Test function to simulate scanning a rifle QR
function testRifleQRScan() {
    console.log('=== TESTING RIFLE QR SCAN ===');
    
    // Create test rifle data - same as test page
    const testRifleData = {
        type: 'rifle',
        serial: 'TEST123',
        model: 'M16A2',
        condition: 'Good',
        assignedTo: 'Unassigned',
        generatedAt: new Date().toISOString(),
        expiresAt: new Date(Date.now() + (30 * 24 * 60 * 60 * 1000)).toISOString(),
        id: 'RFL-' + Date.now().toString(36) + '-TEST'
    };
    
    // Encrypt with rifle key
    const jsonString = JSON.stringify(testRifleData);
    const encryptedData = CryptoJS.AES.encrypt(jsonString, 'rifle-management-system-key-2024').toString();
    
    console.log('Test encrypted data:', encryptedData);
    console.log('Simulating QR scan...');
    
    // Call the actual scan handler
    onScanSuccess(encryptedData);
}

/**
 * Resets the scanner session and clears all tracking data
 */
function resetSession() {
    // Reset scan statistics
    scanStats = {
        totalScanned: 0,
        successfulScans: 0,
        failedScans: 0
    };
    
    // Clear scanned items tracking
    scannedItems.rifles.clear();
    scannedItems.cadets.clear();
    
    // Reset workflow state
    assignmentWorkflow = {
        step: 'idle',
        cadetData: null,
        rifleData: null
    };
    
    // Reset scan step and scanned cadet
    scanStep = 1;
    scannedCadet = null;
    
    // Reset cooldown
    scanCooldown = false;
    
    // Update displays
    updateScanStats();
    loadRecentActivities();
    
    // Clear scan result
    const resultElement = document.getElementById('scan-result');
    if (resultElement) {
        resultElement.style.display = 'none';
    }
    
    // Clear debug panel
    if (typeof clearDebugPanel === 'function') {
        clearDebugPanel();
    }
    
    // Update camera status
    updateCameraStatus('Session reset - ready for new scans', 'success');
    
    // Show success message
    showScanResult('Session reset successfully! Ready for new scans.', 'success');
    
    console.log('Scanner session reset - all tracking data cleared');
}

/**
 * Resets the current scan state without clearing session data
 */
function resetCurrentScan() {
    // Reset workflow state to allow fresh scanning
    assignmentWorkflow = {
        step: 'idle',
        cadetData: null,
        rifleData: null
    };
    scanStep = 1;
    scannedCadet = null;
    scanCooldown = false;
    
    // Clear scan result display
    const scanResult = document.getElementById('scan-result');
    if (scanResult) {
        scanResult.style.display = 'none';
        scanResult.innerHTML = '';
    }
    
    // Reset status display based on current operation mode
    const statusDiv = document.getElementById('scan-status');
    if (statusDiv) {
        const currentMode = getCurrentOperationMode();
        if (currentMode === 'assign') {
            statusDiv.innerHTML = '<i class="fas fa-qrcode"></i> Ready to scan cadet QR code';
            statusDiv.className = 'scan-status waiting';
            assignmentWorkflow.step = 'waiting_for_cadet';
        } else if (currentMode === 'return') {
            statusDiv.innerHTML = '<i class="fas fa-qrcode"></i> Ready to scan rifle QR code for return';
            statusDiv.className = 'scan-status waiting';
            assignmentWorkflow.step = 'waiting_for_rifle_return';
        } else {
            statusDiv.innerHTML = 'Select an operation mode to begin';
            statusDiv.className = 'scan-status idle';
        }
    }
    
    // Clear debug panel
    if (typeof clearDebugPanel === 'function') {
        clearDebugPanel();
    }
    
    // Update camera status
    updateCameraStatus('Scan state reset - ready for new scans', 'success');
    
    // Show success message
    showScanResult(`
        <div style="text-align: center; margin-bottom: var(--spacing-md);">
            <h3 style="color: var(--success); margin-bottom: var(--spacing-xs);">✅ Scan Reset</h3>
            <p style="margin: var(--spacing-xs) 0;">Scanner is ready for new scans.</p>
        </div>
    `, 'success');
    
    // Hide the message after 2 seconds
    setTimeout(() => {
        const scanResult = document.getElementById('scan-result');
        if (scanResult) {
            scanResult.style.display = 'none';
        }
    }, 2000);
    
    console.log('Current scan reset completed');
}

/**
 * Helper function to get current operation mode
 */
function getCurrentOperationMode() {
    const attendanceBtn = document.getElementById('attendance-btn');
    const assignBtn = document.getElementById('assign-btn');
    const returnBtn = document.getElementById('return-btn');
    
    if (attendanceBtn && attendanceBtn.classList.contains('active')) {
        return 'attendance';
    } else if (assignBtn && assignBtn.classList.contains('active')) {
        return 'assign';
    } else if (returnBtn && returnBtn.classList.contains('active')) {
        return 'return';
    }
    return 'attendance'; // Default to attendance
}

/**
 * Selects the operation mode and updates UI accordingly
 */
function selectOperation(operation) {
    // Update button states
    const buttons = document.querySelectorAll('.operation-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    const selectedBtn = document.getElementById(`${operation}-btn`);
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    // Update current operation
    currentOperation = operation;
    
    // Update scanner mode display
    const scannerMode = document.getElementById('scanner-mode');
    const secretKeyGroup = document.getElementById('secret-key-group');
    const attendanceForm = document.getElementById('attendance-form');
    
    if (scannerMode) {
        switch (operation) {
            case 'attendance':
                scannerMode.innerHTML = '<i class="fas fa-user-check"></i> Attendance Mode - Scan cadet QR codes';
                if (secretKeyGroup) secretKeyGroup.style.display = 'none';
                if (attendanceForm) attendanceForm.style.display = 'block';
                break;
            case 'assign':
                scannerMode.innerHTML = '<i class="fas fa-plus-circle"></i> Assignment Mode - Scan cadet then rifle QR codes';
                if (secretKeyGroup) secretKeyGroup.style.display = 'block';
                if (attendanceForm) attendanceForm.style.display = 'none';
                break;
            case 'return':
                scannerMode.innerHTML = '<i class="fas fa-minus-circle"></i> Return Mode - Scan rifle QR codes';
                if (secretKeyGroup) secretKeyGroup.style.display = 'block';
                if (attendanceForm) attendanceForm.style.display = 'none';
                break;
        }
    }
    
    // Reset workflow state when changing modes
    resetCurrentScan();
    
    console.log(`Operation mode changed to: ${operation}`);
}

/**
 * Initialize the scanner with default settings
 */
function initializeScanner() {
    // Set attendance as default mode
    selectOperation('attendance');
    
    // Apply attendance defaults and wire persistence
    applyAttendanceDefaultsFromStorage();
    installAttendanceFormListeners();
    
    // Load initial data
    loadRecentActivities();
    loadCurrentAssignments();
    updateScanStats();
    
    console.log('Scanner initialized with attendance as default mode');
}

// Initialize scanner when page loads
window.addEventListener('load', function() {
    // Initialize scanner with default settings
    initializeScanner();
    
    // Add test button to page (for development)
    const testButton = document.createElement('button');
    testButton.textContent = 'Test Rifle QR Scan';
    testButton.style.cssText = 'position: fixed; top: 10px; right: 10px; z-index: 9999; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;';
    testButton.onclick = testRifleQRScan;
    document.body.appendChild(testButton);
});

// Also initialize when DOM is ready (fallback)
document.addEventListener('DOMContentLoaded', function() {
    // Initialize scanner if not already done
    if (typeof currentOperation === 'undefined' || !currentOperation) {
        initializeScanner();
    }
});