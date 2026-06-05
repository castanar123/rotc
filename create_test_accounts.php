<?php
require_once 'includes/db.php';

// Function to create test accounts
function createTestAccounts($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Test passwords (will be hashed)
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $officerPassword = password_hash('officer123', PASSWORD_DEFAULT);
        $cadetPassword = password_hash('cadet123', PASSWORD_DEFAULT);
        
        // 1. Create Admin Account
        $stmt = $pdo->prepare("INSERT INTO users (email, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin@rotc.edu', 'admin', $adminPassword, 'admin']);
        $adminUserId = $pdo->lastInsertId();
        
        // Admin profile
        $stmt = $pdo->prepare("
            INSERT INTO cadet_profiles (
                user_id, student_number, full_name, gender, address, contact_number, 
                course, section, religion, birth_date, place_of_birth, height, weight, 
                skin_color, blood_type, father_name, father_occupation, mother_name, 
                mother_occupation, guardian_name, guardian_contact, guardian_relationship, 
                guardian_address, platoon, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $adminUserId, 'ADMIN-001', 'Administrator User', 'Male', 
            '123 Admin Street, Command Center', '+63-912-345-6789',
            'Military Science', 'ADMIN', 'Catholic', '1990-01-01', 
            'Manila, Philippines', '175 cm', '70 kg', 'Fair', 'O+',
            'Admin Father', 'Military Officer', 'Admin Mother', 'Teacher',
            'Admin Guardian', '+63-912-345-6789', 'Parent', 
            '123 Admin Street, Command Center', 'COMMAND', 'Active'
        ]);
        
        // 2. Create 2CL Officer Account
        $stmt = $pdo->prepare("INSERT INTO users (email, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['officer@rotc.edu', 'officer2cl', $officerPassword, '2cl']);
        $officerUserId = $pdo->lastInsertId();
        
        // Officer profile
        $stmt = $pdo->prepare("
            INSERT INTO cadet_profiles (
                user_id, student_number, full_name, gender, address, contact_number, 
                course, section, religion, birth_date, place_of_birth, height, weight, 
                skin_color, blood_type, father_name, father_occupation, mother_name, 
                mother_occupation, guardian_name, guardian_contact, guardian_relationship, 
                guardian_address, platoon, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $officerUserId, '2CL-001', 'Second Lieutenant Officer', 'Male', 
            '456 Officer Avenue, Base Camp', '+63-917-123-4567',
            'Military Leadership', 'OFFICER', 'Catholic', '1995-03-15', 
            'Quezon City, Philippines', '180 cm', '75 kg', 'Medium', 'A+',
            'Officer Father', 'Engineer', 'Officer Mother', 'Nurse',
            'Officer Guardian', '+63-917-123-4567', 'Parent', 
            '456 Officer Avenue, Base Camp', 'ALPHA', 'Active'
        ]);
        
        // 3. Create Basic Cadet Account
        $stmt = $pdo->prepare("INSERT INTO users (email, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['cadet@rotc.edu', 'basiccadet', $cadetPassword, 'basic_cadet']);
        $cadetUserId = $pdo->lastInsertId();
        
        // Cadet profile
        $stmt = $pdo->prepare("
            INSERT INTO cadet_profiles (
                user_id, student_number, full_name, gender, address, contact_number, 
                course, section, religion, birth_date, place_of_birth, height, weight, 
                skin_color, blood_type, father_name, father_occupation, mother_name, 
                mother_occupation, guardian_name, guardian_contact, guardian_relationship, 
                guardian_address, platoon, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $cadetUserId, 'CADET-001', 'Basic Cadet Student', 'Female', 
            '789 Cadet Street, Training Ground', '+63-920-987-6543',
            'Computer Science', 'CS-3A', 'Catholic', '2002-07-20', 
            'Cebu City, Philippines', '165 cm', '55 kg', 'Fair', 'B+',
            'Cadet Father', 'Business Owner', 'Cadet Mother', 'Accountant',
            'Cadet Guardian', '+63-920-987-6543', 'Parent', 
            '789 Cadet Street, Training Ground', 'BRAVO', 'Active'
        ]);
        
        $pdo->commit();
        
        echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f4f4f4; border-radius: 10px;'>";
        echo "<h2 style='color: #2c5530; text-align: center; margin-bottom: 30px;'>✅ Test Accounts Created Successfully!</h2>";
        
        echo "<div style='background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #d4af37;'>";
        echo "<h3 style='color: #d4af37; margin-top: 0;'>🛡️ Admin Account</h3>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Email:</strong> admin@rotc.edu</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "<p><strong>Role:</strong> Administrator</p>";
        echo "</div>";
        
        echo "<div style='background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2c5530;'>";
        echo "<h3 style='color: #2c5530; margin-top: 0;'>⭐ 2CL Officer Account</h3>";
        echo "<p><strong>Username:</strong> officer2cl</p>";
        echo "<p><strong>Email:</strong> officer@rotc.edu</p>";
        echo "<p><strong>Password:</strong> officer123</p>";
        echo "<p><strong>Role:</strong> Second Class Officer</p>";
        echo "</div>";
        
        echo "<div style='background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #8b4513;'>";
        echo "<h3 style='color: #8b4513; margin-top: 0;'>🎖️ Basic Cadet Account</h3>";
        echo "<p><strong>Username:</strong> basiccadet</p>";
        echo "<p><strong>Email:</strong> cadet@rotc.edu</p>";
        echo "<p><strong>Password:</strong> cadet123</p>";
        echo "<p><strong>Role:</strong> Basic Cadet</p>";
        echo "</div>";
        
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;'>";
        echo "<p style='margin: 0; color: #2c5530;'><strong>🔗 Login URL:</strong> <a href='login.php' style='color: #d4af37;'>http://localhost:8080/rotc/login.php</a></p>";
        echo "</div>";
        
        echo "</div>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div style='color: red; font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #ffe6e6; border-radius: 10px;'>";
        echo "<h2>❌ Error Creating Test Accounts</h2>";
        echo "<p>Error: " . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Check if accounts already exist
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username IN ('admin', 'officer2cl', 'basiccadet')");
    $stmt->execute();
    $existingCount = $stmt->fetchColumn();
    
    if ($existingCount > 0) {
        echo "<div style='color: orange; font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #fff3cd; border-radius: 10px;'>";
        echo "<h2>⚠️ Warning</h2>";
        echo "<p>Some test accounts already exist. Please delete existing accounts first or use different usernames.</p>";
        echo "<p><a href='login.php' style='color: #d4af37;'>Go to Login Page</a></p>";
        echo "</div>";
    } else {
        createTestAccounts($pdo);
    }
} catch (Exception $e) {
    echo "<div style='color: red; font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #ffe6e6; border-radius: 10px;'>";
    echo "<h2>❌ Database Error</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>