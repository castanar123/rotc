/**
 * Configuration file for the Student Attendance QR System
 * Contains global settings and encryption keys
 */

// Permanent encryption key - DO NOT CHANGE once in production
// This ensures all QR codes remain valid
const PERMANENT_ENCRYPTION_KEY = "attendance-system-permanent-key-2023";

// System settings
const SYSTEM_CONFIG = {
    // Default QR code validity period in months
    defaultValidityMonths: 12,
    
    // QR code settings
    qrCode: {
        width: 256,
        height: 256,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: 'H' // High error correction level
    },
    
    // API endpoints
    api: {
        recordAttendance: 'record_attendance.php'
    }
};