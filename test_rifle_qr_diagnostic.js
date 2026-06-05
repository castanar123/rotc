// Node.js diagnostic script to test rifle QR generation and decryption
const crypto = require('crypto');

// Simple AES encryption/decryption functions (mimicking CryptoJS)
function encrypt(text, key) {
    const algorithm = 'aes-256-cbc';
    const keyHash = crypto.createHash('sha256').update(key).digest();
    const iv = crypto.randomBytes(16);
    const cipher = crypto.createCipher('aes-256-cbc', key);
    let encrypted = cipher.update(text, 'utf8', 'base64');
    encrypted += cipher.final('base64');
    return encrypted;
}

function decrypt(encryptedText, key) {
    try {
        const decipher = crypto.createDecipher('aes-256-cbc', key);
        let decrypted = decipher.update(encryptedText, 'base64', 'utf8');
        decrypted += decipher.final('utf8');
        return decrypted;
    } catch (error) {
        return null;
    }
}

console.log('=== RIFLE QR DIAGNOSTIC TEST ===\n');

// Step 1: Generate test rifle data
const rifleData = {
    type: 'rifle',
    serial: 'TEST123',
    model: 'M16A2',
    condition: 'Good',
    assignedTo: 'Unassigned',
    generatedAt: new Date().toISOString(),
    expiresAt: new Date(Date.now() + (30 * 24 * 60 * 60 * 1000)).toISOString(),
    id: 'RFL-' + Date.now().toString(36) + '-TEST'
};

console.log('1. Generated rifle data:');
console.log(JSON.stringify(rifleData, null, 2));
console.log('');

// Step 2: Encrypt with rifle key
const jsonString = JSON.stringify(rifleData);
const encryptedData = encrypt(jsonString, 'rifle-management-system-key-2024');

console.log('2. Encrypted data:');
console.log(encryptedData);
console.log('');

// Step 3: Test decryption with both keys (mimicking scanner logic)
console.log('3. Testing decryption logic:');

// Test with attendance key (should fail)
let decryptedWithAttendanceKey = decrypt(encryptedData, 'attendance-system-permanent-key-2023');
if (decryptedWithAttendanceKey) {
    console.log('❌ UNEXPECTED: Decrypted with attendance key');
    console.log(decryptedWithAttendanceKey);
} else {
    console.log('✅ Correctly failed with attendance key');
}

// Test with rifle key (should succeed)
let decryptedWithRifleKey = decrypt(encryptedData, 'rifle-management-system-key-2024');
if (decryptedWithRifleKey) {
    console.log('✅ Successfully decrypted with rifle key');
    console.log(decryptedWithRifleKey);
    
    try {
        const parsedData = JSON.parse(decryptedWithRifleKey);
        console.log('✅ Successfully parsed JSON');
        
        // Apply field mapping logic
        if (parsedData.serial && !parsedData.rifle_id) {
            parsedData.rifle_id = parsedData.serial;
            console.log('✅ Mapped serial to rifle_id:', parsedData.rifle_id);
        }
        
        parsedData.type = 'rifle';
        console.log('✅ Set type to rifle');
        
        console.log('\n4. Final processed data:');
        console.log(JSON.stringify(parsedData, null, 2));
        
    } catch (parseError) {
        console.log('❌ Failed to parse decrypted JSON:', parseError.message);
    }
} else {
    console.log('❌ FAILED: Could not decrypt with rifle key');
}

console.log('\n=== DIAGNOSTIC COMPLETE ===');