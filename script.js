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
 * Generates an encrypted QR code based on student information
 */
function generateQRCode() {
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
    
    // Create data object
    const data = {
        student_id: studentId,
        name: studentName,
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