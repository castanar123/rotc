<?php
require_once 'includes/db.php';

echo "<h1>Restoring ALL Original ROTC Data</h1>";
echo "<p>Starting comprehensive data restoration...</p>";

try {
    $pdo->beginTransaction();
    
    echo "<h2>1. Restoring Original User Accounts</h2>";
    
    // Clear existing data first (except admin)
    $pdo->exec("DELETE FROM users WHERE username != 'admin'");
    
    // Restore original admin user with correct structure
    $adminExists = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
    if ($adminExists == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, created_at, updated_at, is_active, two_factor_enabled, two_factor_secret) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'admin',
            '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'admin@rotc.edu',
            'Administrator',
            'admin',
            '2024-01-01 00:00:00',
            '2024-01-01 00:00:00',
            1,
            0,
            null
        ]);
        echo "<p>✅ Admin user restored</p>";
    }
    
    // Create test officer account
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, created_at, updated_at, is_active, two_factor_enabled, two_factor_secret) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'officer2cl',
        password_hash('officer123', PASSWORD_DEFAULT),
        'officer@rotc.edu',
        'Second Lieutenant Officer',
        '2cl',
        '2024-01-02 00:00:00',
        '2024-01-02 00:00:00',
        1,
        0,
        null
    ]);
    $officerUserId = $pdo->lastInsertId();
    echo "<p>✅ Officer user created (ID: $officerUserId)</p>";
    
    // Create test cadet accounts
    $cadets = [
        ['basiccadet1', 'cadet123', 'cadet1@rotc.edu', 'John Doe Cadet'],
        ['basiccadet2', 'cadet123', 'cadet2@rotc.edu', 'Jane Smith Cadet'],
        ['basiccadet3', 'cadet123', 'cadet3@rotc.edu', 'Mike Johnson Cadet']
    ];
    
    $cadetUserIds = [];
    foreach ($cadets as $cadet) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role, created_at, updated_at, is_active, two_factor_enabled, two_factor_secret) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $cadet[0],
            password_hash($cadet[1], PASSWORD_DEFAULT),
            $cadet[2],
            $cadet[3],
            'basic_cadet',
            '2024-01-03 00:00:00',
            '2024-01-03 00:00:00',
            1,
            0,
            null
        ]);
        $cadetUserIds[] = $pdo->lastInsertId();
    }
    echo "<p>✅ " . count($cadets) . " cadet users created</p>";
    
    echo "<h2>2. Restoring Cadet Profiles</h2>";
    
    // Clear existing cadet profiles
    $pdo->exec("DELETE FROM cadet_profiles");
    
    // Create cadet profiles for the test accounts
    $profiles = [
        [
            'user_id' => $cadetUserIds[0],
            'student_id' => 'BC001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'middle_name' => 'A',
            'gender' => 'Male',
            'email' => 'cadet1@rotc.edu',
            'address' => '123 Cadet Street, Training Ground',
            'contact_number' => '+63-920-111-1111',
            'course' => 'Computer Science',
            'section' => 'CS-3A',
            'religion' => 'Catholic',
            'birthdate' => '2002-01-15',
            'place_of_birth' => 'Manila, Philippines',
            'height' => '175 cm',
            'weight' => '65 kg',
            'skin_color' => 'Fair',
            'blood_type' => 'O+',
            'father' => 'John Doe Sr.',
            'father_occupation' => 'Engineer',
            'mother' => 'Jane Doe',
            'mother_occupation' => 'Teacher',
            'guardian' => 'John Doe Sr.',
            'guardian_contact' => '+63-920-111-1111',
            'guardian_relationship' => 'Father',
            'guardian_address' => '123 Cadet Street, Training Ground',
            'platoon' => 'Alpha',
            'status' => 'Active'
        ],
        [
            'user_id' => $cadetUserIds[1],
            'student_id' => 'BC002',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'middle_name' => 'B',
            'gender' => 'Female',
            'email' => 'cadet2@rotc.edu',
            'address' => '456 Training Avenue, Base Camp',
            'contact_number' => '+63-920-222-2222',
            'course' => 'Information Technology',
            'section' => 'IT-2B',
            'religion' => 'Catholic',
            'birthdate' => '2002-03-20',
            'place_of_birth' => 'Quezon City, Philippines',
            'height' => '160 cm',
            'weight' => '50 kg',
            'skin_color' => 'Fair',
            'blood_type' => 'A+',
            'father' => 'Robert Smith',
            'father_occupation' => 'Business Owner',
            'mother' => 'Mary Smith',
            'mother_occupation' => 'Nurse',
            'guardian' => 'Robert Smith',
            'guardian_contact' => '+63-920-222-2222',
            'guardian_relationship' => 'Father',
            'guardian_address' => '456 Training Avenue, Base Camp',
            'platoon' => 'Bravo',
            'status' => 'Active'
        ],
        [
            'user_id' => $cadetUserIds[2],
            'student_id' => 'BC003',
            'first_name' => 'Mike',
            'last_name' => 'Johnson',
            'middle_name' => 'C',
            'gender' => 'Male',
            'email' => 'cadet3@rotc.edu',
            'address' => '789 Military Road, Command Center',
            'contact_number' => '+63-920-333-3333',
            'course' => 'Engineering',
            'section' => 'ENG-1A',
            'religion' => 'Catholic',
            'birthdate' => '2002-05-10',
            'place_of_birth' => 'Cebu City, Philippines',
            'height' => '180 cm',
            'weight' => '70 kg',
            'skin_color' => 'Medium',
            'blood_type' => 'B+',
            'father' => 'Michael Johnson',
            'father_occupation' => 'Military Officer',
            'mother' => 'Sarah Johnson',
            'mother_occupation' => 'Doctor',
            'guardian' => 'Michael Johnson',
            'guardian_contact' => '+63-920-333-3333',
            'guardian_relationship' => 'Father',
            'guardian_address' => '789 Military Road, Command Center',
            'platoon' => 'Charlie',
            'status' => 'Active'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO cadet_profiles (
            user_id, student_id, first_name, last_name, middle_name, gender, email, 
            address, contact_number, course, section, religion, birthdate, place_of_birth, 
            height, weight, skin_color, blood_type, father, father_occupation, mother, 
            mother_occupation, guardian, guardian_contact, guardian_relationship, 
            guardian_address, platoon, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($profiles as $profile) {
        $stmt->execute([
            $profile['user_id'], $profile['student_id'], $profile['first_name'], $profile['last_name'],
            $profile['middle_name'], $profile['gender'], $profile['email'], $profile['address'],
            $profile['contact_number'], $profile['course'], $profile['section'], $profile['religion'],
            $profile['birthdate'], $profile['place_of_birth'], $profile['height'], $profile['weight'],
            $profile['skin_color'], $profile['blood_type'], $profile['father'], $profile['father_occupation'],
            $profile['mother'], $profile['mother_occupation'], $profile['guardian'], $profile['guardian_contact'],
            $profile['guardian_relationship'], $profile['guardian_address'], $profile['platoon'], $profile['status']
        ]);
    }
    echo "<p>✅ " . count($profiles) . " cadet profiles restored</p>";
    
    echo "<h2>3. Restoring Rifle Inventory</h2>";
    
    // Clear existing rifles
    $pdo->exec("DELETE FROM rifles");
    
    // Restore original rifle data
    $rifles = [
        ['M16A2-001', 'M16A2', 'Colt', '5.56mm', 'excellent', 'Armory A1', '2024-01-01', '2024-07-01'],
        ['M16A2-002', 'M16A2', 'Colt', '5.56mm', 'good', 'Armory A1', '2024-01-01', '2024-07-01'],
        ['M16A2-003', 'M16A2', 'Colt', '5.56mm', 'good', 'Armory A2', '2024-01-01', '2024-07-01'],
        ['M4A1-001', 'M4A1', 'Colt', '5.56mm', 'excellent', 'Armory B1', '2024-01-01', '2024-07-01'],
        ['M4A1-002', 'M4A1', 'Colt', '5.56mm', 'good', 'Armory B1', '2024-01-01', '2024-07-01'],
        ['M14-001', 'M14', 'Springfield', '7.62mm', 'good', 'Armory C1', '2024-01-01', '2024-07-01'],
        ['M14-002', 'M14', 'Springfield', '7.62mm', 'fair', 'Armory C1', '2024-01-01', '2024-07-01']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO rifles (serial_number, model, manufacturer, caliber, condition_status, location, last_maintenance, next_maintenance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($rifles as $rifle) {
        $stmt->execute($rifle);
    }
    echo "<p>✅ " . count($rifles) . " rifles restored</p>";
    
    echo "<h2>4. Restoring Inventory Items</h2>";
    
    // Clear existing items
    $pdo->exec("DELETE FROM items");
    
    // Restore original inventory items
    $items = [
        ['Combat Boots', 'Standard issue combat boots for training', 50, 45, 5, 'pairs', 'Supply Room A', 'good'],
        ['Uniform Set', 'Complete ROTC uniform with insignia', 30, 25, 5, 'sets', 'Supply Room A', 'good'],
        ['Field Pack', 'Military field backpack for exercises', 25, 20, 5, 'pcs', 'Supply Room B', 'excellent'],
        ['Helmet', 'Protective helmet for field training', 40, 35, 5, 'pcs', 'Supply Room B', 'good'],
        ['Belt', 'Military belt with buckle', 60, 50, 10, 'pcs', 'Supply Room A', 'good'],
        ['Canteen', 'Water canteen for field exercises', 35, 30, 5, 'pcs', 'Supply Room C', 'good']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO items (item_name, description, total_quantity, available_quantity, borrowed_quantity, unit, location, condition_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $stmt->execute($item);
    }
    echo "<p>✅ " . count($items) . " inventory items restored</p>";
    
    echo "<h2>5. Restoring Borrowed Items Records</h2>";
    
    // Clear existing borrowed items
    $pdo->exec("DELETE FROM borrowed_items");
    
    // Get the actual item IDs that were just created
    $itemIds = $pdo->query("SELECT id FROM items ORDER BY id LIMIT 6")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($itemIds) >= 2) {
        // Create sample borrowed items records using actual item IDs
        $borrowedItems = [
            [$itemIds[0], 'John Doe (BC001)', '+63-920-111-1111', 1, '2024-01-15', '2024-02-15', null, 'borrowed', 'For field training exercise'],
            [$itemIds[1], 'Jane Smith (BC002)', '+63-920-222-2222', 1, '2024-01-16', '2024-02-16', null, 'borrowed', 'For parade practice'],
            [$itemIds[2], 'Mike Johnson (BC003)', '+63-920-333-3333', 1, '2024-01-17', '2024-02-17', null, 'borrowed', 'For outdoor training'],
            [$itemIds[0], 'John Doe (BC001)', '+63-920-111-1111', 1, '2024-01-18', '2024-02-18', null, 'borrowed', 'For field exercise'],
            [$itemIds[1], 'Jane Smith (BC002)', '+63-920-222-2222', 2, '2024-01-19', '2024-02-19', null, 'borrowed', 'For training camp']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO borrowed_items (item_id, borrower_name, borrower_contact, quantity_borrowed, borrow_date, expected_return_date, actual_return_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($borrowedItems as $borrowed) {
            $stmt->execute($borrowed);
        }
        echo "<p>✅ " . count($borrowedItems) . " borrowed items records restored</p>";
    } else {
        echo "<p>⚠️ No items found to create borrowed records</p>";
    }
    
    echo "<h2>6. Creating Sample Attendance Records</h2>";
    
    // Clear existing attendance
    $pdo->exec("DELETE FROM attendance");
    
    // Get cadet profile IDs
    $cadetProfiles = $pdo->query("SELECT id FROM cadet_profiles ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    
    // Create sample attendance records for the past week
    $dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $dates[] = date('Y-m-d', strtotime("-$i days"));
    }
    
    $attendanceCount = 0;
    foreach ($dates as $date) {
        foreach ($cadetProfiles as $cadetId) {
            // Random attendance status (mostly present)
            $statuses = ['present', 'present', 'present', 'present', 'late', 'absent'];
            $status = $statuses[array_rand($statuses)];
            
            $timeIn = $status === 'absent' ? null : '07:' . sprintf('%02d', rand(0, 30)) . ':00';
            $timeOut = $status === 'absent' ? null : '17:' . sprintf('%02d', rand(0, 30)) . ':00';
            
            $stmt = $pdo->prepare("INSERT INTO attendance (cadet_id, date, time_in, time_out, status, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cadetId, $date, $timeIn, $timeOut, $status, 1]);
            $attendanceCount++;
        }
    }
    echo "<p>✅ $attendanceCount attendance records created</p>";
    
    $pdo->commit();
    
    echo "<h2>✅ DATA RESTORATION COMPLETE!</h2>";
    echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>Summary of Restored Data:</h3>";
    echo "<ul>";
    echo "<li><strong>Users:</strong> 1 admin + 1 officer + 3 cadets = 5 total</li>";
    echo "<li><strong>Cadet Profiles:</strong> 3 complete profiles with personal details</li>";
    echo "<li><strong>Rifles:</strong> 7 rifles (M16A2, M4A1, M14 models)</li>";
    echo "<li><strong>Inventory Items:</strong> 6 different equipment types</li>";
    echo "<li><strong>Borrowed Items:</strong> 5 active borrowing records</li>";
    echo "<li><strong>Attendance Records:</strong> $attendanceCount records for past 7 days</li>";
    echo "</ul>";
    echo "<p><strong>Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li>Admin: username=admin, password=password</li>";
    echo "<li>Officer: username=officer2cl, password=officer123</li>";
    echo "<li>Cadets: username=basiccadet1/2/3, password=cadet123</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div style='color: red; background: #ffe6e6; padding: 20px; border-radius: 8px;'>";
    echo "<h2>❌ Error During Restoration</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: " . $e->getTraceAsString() . "</p>";
    echo "</div>";
}
?>