// Global variables
let html5QrCode;
let scannerActive = false;
let lastScannedId = null;
let scanCooldown = false;
let scanStats = {
    totalScanned: 0,
    successfulScans: 0,
    failedScans: 0
};

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
    
    // Add event listener for dashboard link
    const dashboardLink = document.getElementById('dashboard-link');
    if (dashboardLink) {
        dashboardLink.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'QR/dashboard.html';
        });
    }
});

/**
 * Loads saved session data from the server
 */
function loadSessionData() {
    fetch('QR/session.php?action=get_session')
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
    
    // Initialize the scanner if not already initialized
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
    }
    
    // Responsive QR box size based on screen width
    const width = window.innerWidth;
    const qrboxSize = width < 600 ? Math.min(width * 0.7, 250) : 250;
    
    const scanConfig = { 
        fps: 10, 
        qrbox: { width: qrboxSize, height: qrboxSize },
        aspectRatio: 1.0
    };
    
    // Start scanning
    html5QrCode.start(
        { facingMode: "environment" }, // This uses the back camera on mobile devices
        scanConfig,
        onScanSuccess,
        onScanFailure
    )
    .then(() => {
        // Show stop button and hide start button
        document.getElementById('start-scanner-btn').style.display = 'none';
        document.getElementById('stop-scanner-btn').style.display = 'block';
        updateCameraStatus('Camera active - Point at a QR code');
        scannerActive = true;
        
        // Update scan stats display
        updateScanStats();
        
        // Show scanner controls
        document.getElementById('scanner-controls').style.display = 'block';
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
    fetch('QR/session.php', {
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
function onScanSuccess(decodedText, decodedResult) {
    // Increment total scan count
    scanStats.totalScanned++;
    
    // If we're in cooldown period, ignore this scan
    if (scanCooldown) {
        return;
    }
    
    try {
        // Get the secret key from the input field
        const secretKey = document.getElementById('secret-key').value;
        
        // Try to decrypt the data
        const decryptedBytes = CryptoJS.AES.decrypt(decodedText, secretKey);
        const decryptedText = decryptedBytes.toString(CryptoJS.enc.Utf8);
        
        if (!decryptedText) {
            throw new Error('Decryption failed. Invalid QR code or wrong secret key.');
        }
            
        // Parse the decrypted JSON
        const qrData = JSON.parse(decryptedText);
        
        // Check if this is a dummy QR code for borrowing
        if (qrData.type === 'borrowing' && qrData.qr_code_id) {
            // Redirect to borrowing form with QR code ID
            window.location.href = `borrow_rifle.php?qr_id=${qrData.qr_code_id}`;
            return;
        }
        
        // Handle regular student QR codes
        const studentData = qrData;
        
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
        
        // Check if this is a duplicate scan (same student ID scanned recently)
        if (lastScannedId === studentData.student_id) {
            showScanResult(`Already scanned ${studentData.name} (${studentData.student_id})!`, 'warning');
            setCooldown();
            return;
        }
        
        // Set this as the last scanned ID
        lastScannedId = studentData.student_id;
        
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
        
        // Send attendance data to the server
        fetch('QR/record_attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                student_id: studentData.student_id,
                name: studentData.name,
                td: document.getElementById('td').value,
                semester: document.getElementById('semester').value,
                timestamp: new Date().toISOString()
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            
            // Update attendance stats if available
            if (data.success && data.stats) {
                updateAttendanceStats(data.stats);
            }
            
            // Update recent records if available
            if (data.success && data.recent_records) {
                updateRecentRecords(data.recent_records);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showScanResult('Attendance recorded locally but failed to save to server.', 'error');
        });
        
        // Set a cooldown to prevent multiple scans of the same QR code
        setCooldown();
        
    } catch (error) {
        console.error('Error processing QR code:', error);
        showScanResult('Invalid QR Code! Make sure you are using a QR code generated by this system and the correct secret key.', 'error');
        scanStats.failedScans++;
        updateScanStats();
        setCooldown();
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
    if (html5QrCode && html5QrCode.isScanning) {
        html5QrCode.stop()
            .then(() => {
                // Show start button and hide stop button
                document.getElementById('start-scanner-btn').style.display = 'block';
                document.getElementById('stop-scanner-btn').style.display = 'none';
                updateCameraStatus('Scanner stopped. Click "Start Scanner" to scan again.');
                scannerActive = false;
                
                // Hide scanner controls
                document.getElementById('scanner-controls').style.display = 'none';
            })
            .catch(err => {
                console.error(`Unable to stop scanning: ${err}`);
                updateCameraStatus('Error stopping scanner', 'error');
            });
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