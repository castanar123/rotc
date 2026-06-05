// Global variables
let qrCodeInstance;
let currentQrData = null;

// Set today's date as default for valid-until field
window.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    // Set default expiration to 6 months from now
    const sixMonthsLater = new Date(today);
    sixMonthsLater.setMonth(today.getMonth() + 6);
    
    const validUntilInput = document.getElementById('valid-until');
    validUntilInput.valueAsDate = sixMonthsLater;
});

// Generate QR Code button click handler
document.getElementById('generate-btn').addEventListener('click', generateQRCode);

// Add event listeners for auto-lookup
document.getElementById('student-id').addEventListener('blur', checkUserByStudentId);
document.getElementById('student-name').addEventListener('blur', checkUserByName);

// Download QR Code button click handler
document.getElementById('download-btn').addEventListener('click', downloadQRCode);

// Set the secret key field to the permanent key and disable it
window.addEventListener('DOMContentLoaded', () => {
    const secretKeyInput = document.getElementById('secret-key');
    secretKeyInput.value = PERMANENT_ENCRYPTION_KEY;
    secretKeyInput.disabled = true;
    secretKeyInput.title = "This key is managed by the system for security";
});

/**
 * Check if user exists by student ID
 */
async function checkUserByStudentId() {
    const studentId = document.getElementById('student-id').value.trim();
    if (!studentId) return;
    
    try {
        const response = await fetch('check_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ student_id: studentId })
        });
        
        const result = await response.json();
        
        if (result.exists) {
            document.getElementById('student-name').value = result.name;
            showMessage('User found: ' + result.name, 'success');
        } else {
            showMessage('Student ID not found in database. Please verify the ID or register the student first.', 'warning');
        }
    } catch (error) {
        console.error('Error checking user:', error);
        showMessage('Error checking user database', 'error');
    }
}

/**
 * Check if user exists by name
 */
async function checkUserByName() {
    const studentName = document.getElementById('student-name').value.trim();
    if (!studentName) return;
    
    try {
        const response = await fetch('check_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ student_name: studentName })
        });
        
        const result = await response.json();
        
        if (result.exists) {
            if (result.multiple) {
                let message = 'Multiple users found:\n';
                result.matches.forEach((match, index) => {
                    message += `${index + 1}. ${match.name} (ID: ${match.student_id})\n`;
                });
                alert(message + 'Please use the specific Student ID for accurate identification.');
            } else {
                document.getElementById('student-id').value = result.student_id;
                showMessage('User found: ' + result.name, 'success');
            }
        } else {
            showMessage('Student name not found in database. Please verify the name or register the student first.', 'warning');
        }
    } catch (error) {
        console.error('Error checking user:', error);
        showMessage('Error checking user database', 'error');
    }
}

/**
 * Show message to user
 */
function showMessage(message, type) {
    // Remove existing messages
    const existingMessage = document.querySelector('.user-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `user-message ${type}`;
    messageDiv.textContent = message;
    messageDiv.style.cssText = `
        padding: 10px;
        margin: 10px 0;
        border-radius: 4px;
        font-weight: 500;
        ${type === 'success' ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : ''}
        ${type === 'warning' ? 'background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7;' : ''}
        ${type === 'error' ? 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : ''}
    `;
    
    const cardBody = document.querySelector('.card-body');
    cardBody.insertBefore(messageDiv, cardBody.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
}

/**
 * Generates an encrypted QR code based on student information
 */
async function generateQRCode() {
    const studentId = document.getElementById('student-id').value.trim();
    const studentName = document.getElementById('student-name').value.trim();
    const validUntil = document.getElementById('valid-until').value;
    
    // Always use the permanent encryption key
    const secretKey = PERMANENT_ENCRYPTION_KEY;
    
    // Validate inputs
    if (!studentId || !studentName || !validUntil) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Check if user is registered first
    try {
        const response = await fetch('check_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ student_id: studentId })
        });
        
        const result = await response.json();
        
        if (!result.exists) {
            const proceed = confirm('Student ID not found in database. This will generate a temporary QR code. Do you want to proceed?\n\nFor permanent attendance QR codes, please register the student first.');
            if (!proceed) {
                return;
            }
        } else {
            // Use the registered name for consistency
            document.getElementById('student-name').value = result.name;
        }
    } catch (error) {
        console.error('Error checking user:', error);
        const proceed = confirm('Unable to verify user registration. Do you want to proceed with QR generation?');
        if (!proceed) {
            return;
        }
    }
    
    // Create data object
    const data = {
        student_id: studentId,
        name: document.getElementById('student-name').value.trim(),
        valid_until: validUntil
    };
    
    // Convert to JSON string
    const jsonData = JSON.stringify(data);
    
    // Encrypt the data
    const encryptedData = CryptoJS.AES.encrypt(jsonData, secretKey).toString();
    
    // Display the encrypted data
    document.getElementById('qr-data').textContent = encryptedData;
    
    // Generate QR code
    const qrcodeElement = document.getElementById('qrcode');
    qrcodeElement.innerHTML = '';
    
    // Create QR code with the encrypted data
    qrCodeInstance = new QRCode(qrcodeElement, {
        text: encryptedData,
        width: 256,
        height: 256,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });
    
    // Store current QR data for download
    currentQrData = encryptedData;
    
    // Show download button
    document.getElementById('download-btn').style.display = 'block';
    
    showMessage('QR code generated successfully!', 'success');
}

/**
 * Downloads the generated QR code as an image
 */
function downloadQRCode() {
    if (!currentQrData) {
        alert('Please generate a QR code first');
        return;
    }
    
    // Get the student ID for the filename
    const studentId = document.getElementById('student-id').value.trim();
    const studentName = document.getElementById('student-name').value.trim();
    
    // Get the canvas element from the QR code
    const canvas = document.querySelector('#qrcode canvas');
    
    if (canvas) {
        // Create a temporary link element
        const link = document.createElement('a');
        link.download = `qr_${studentId}_${studentName.replace(/\s+/g, '_')}.png`;
        link.href = canvas.toDataURL('image/png');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } else {
        alert('Unable to download QR code. Please try again.');
    }
}