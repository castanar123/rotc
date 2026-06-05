<?php
require_once 'includes/db.php';

echo "<h2>Database Schema Fix and Test Account Creation</h2>";

try {
    // First, let's update the users table to include the missing roles
    echo "<h3>Step 1: Updating Users Table Schema</h3>";
    
    $alterQuery = "ALTER TABLE users MODIFY COLUMN role ENUM('basic', 'basic_cadet', '2cl', '1cl', 'commandant', 'admin') NOT NULL";
    $pdo->exec($alterQuery);
    echo "<p style='color: green;'>✓ Users table role enum updated successfully!</p>";
    
    // Check if cadet_profiles status enum needs updating
    echo "<h3>Step 2: Checking Cadet Profiles Table Schema</h3>";
    $stmt = $pdo->prepare("SHOW COLUMNS FROM cadet_profiles LIKE 'status'");
    $stmt->execute();
    $statusColumn = $stmt->fetch();
    
    if ($statusColumn && strpos($statusColumn['Type'], 'Active') === false) {
        // Update cadet_profiles status enum
        $alterQuery2 = "ALTER TABLE cadet_profiles MODIFY COLUMN status ENUM('Active', 'Inactive', 'Graduated', 'active', 'dropped') DEFAULT 'Active'";
        $pdo->exec($alterQuery2);
        echo "<p style='color: green;'>✓ Cadet profiles status enum updated successfully!</p>";
    } else {
        echo "<p style='color: blue;'>ℹ Cadet profiles status enum is already correct.</p>";
    }
    
    echo "<h3>Step 3: Creating Test Accounts</h3>";
    
    // Check if test accounts already exist
    $stmt = $pdo->prepare("SELECT username FROM users WHERE username IN ('admin_test', '2cl_officer_test', 'basic_cadet_test')");
    $stmt->execute();
    $existingAccounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($existingAccounts)) {
        echo "<p style='color: orange;'>⚠ Found existing test accounts: " . implode(', ', $existingAccounts) . "</p>";
        echo "<p>Deleting existing test accounts first...</p>";
        
        // Delete existing test accounts and their profiles
        $stmt = $pdo->prepare("DELETE cp FROM cadet_profiles cp INNER JOIN users u ON cp.user_id = u.id WHERE u.username IN ('admin_test', '2cl_officer_test', 'basic_cadet_test')");
        $stmt->execute();
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE username IN ('admin_test', '2cl_officer_test', 'basic_cadet_test')");
        $stmt->execute();
        
        echo "<p style='color: green;'>✓ Existing test accounts deleted.</p>";
    }
    
    // Create test accounts
    $testAccounts = [
        [
            'username' => 'admin_test',
            'email' => 'admin@test.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'profile' => [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'middle_name' => 'Test',
                'student_id' => 'ADMIN001',
                'course' => 'Administration',
                'year_level' => 4,
                'company' => 'HQ',
                'platoon' => 'ADMIN',
                'status' => 'Active'
            ]
        ],
        [
            'username' => '2cl_officer_test',
            'email' => '2cl@test.com',
            'password' => password_hash('officer123', PASSWORD_DEFAULT),
            'role' => '2cl',
            'profile' => [
                'first_name' => 'Second',
                'last_name' => 'Lieutenant',
                'middle_name' => 'Class',
                'student_id' => '2CL001',
                'course' => 'Military Science',
                'year_level' => 3,
                'company' => 'Alpha',
                'platoon' => 'First',
                'status' => 'Active'
            ]
        ],
        [
            'username' => 'basic_cadet_test',
            'email' => 'cadet@test.com',
            'password' => password_hash('cadet123', PASSWORD_DEFAULT),
            'role' => 'basic_cadet',
            'profile' => [
                'first_name' => 'Basic',
                'last_name' => 'Cadet',
                'middle_name' => 'Test',
                'student_id' => 'CAD001',
                'course' => 'Computer Science',
                'year_level' => 1,
                'company' => 'Bravo',
                'platoon' => 'Second',
                'status' => 'Active'
            ]
        ]
    ];
    
    foreach ($testAccounts as $account) {
        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $account['username'],
            $account['email'],
            $account['password'],
            $account['role']
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Insert cadet profile
        $stmt = $pdo->prepare("
            INSERT INTO cadet_profiles 
            (user_id, first_name, last_name, middle_name, student_id, course, year_level, company, platoon, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $account['profile']['first_name'],
            $account['profile']['last_name'],
            $account['profile']['middle_name'],
            $account['profile']['student_id'],
            $account['profile']['course'],
            $account['profile']['year_level'],
            $account['profile']['company'],
            $account['profile']['platoon'],
            $account['profile']['status']
        ]);
        
        echo "<p style='color: green;'>✓ Created account: {$account['username']} ({$account['role']})</p>";
    }
    
    echo "<h3>Step 4: Verification</h3>";
    
    // Verify created accounts
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.role, u.created_at, 
               cp.first_name, cp.last_name, cp.student_id, cp.status
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.username IN ('admin_test', '2cl_officer_test', 'basic_cadet_test')
        ORDER BY u.username
    ");
    $stmt->execute();
    $accounts = $stmt->fetchAll();
    
    if (!empty($accounts)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-top: 10px;'>";
        echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>Full Name</th><th>Student ID</th><th>Status</th><th>Created</th></tr>";
        
        foreach ($accounts as $account) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($account['username']) . "</td>";
            echo "<td>" . htmlspecialchars($account['email']) . "</td>";
            echo "<td>" . htmlspecialchars($account['role']) . "</td>";
            echo "<td>" . htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($account['student_id']) . "</td>";
            echo "<td>" . htmlspecialchars($account['status']) . "</td>";
            echo "<td>" . htmlspecialchars($account['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>✅ All Done!</h3>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>Test Account Credentials:</h4>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username: admin_test, password: admin123</li>";
    echo "<li><strong>2CL Officer:</strong> username: 2cl_officer_test, password: officer123</li>";
    echo "<li><strong>Basic Cadet:</strong> username: basic_cadet_test, password: cadet123</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h3>Actions</h3>";
echo "<p><a href='login.php' style='color: #d4af37; text-decoration: none; font-weight: bold;'>🔐 Go to Login Page</a></p>";
echo "<p><a href='check_test_accounts.php' style='color: #4a90e2; text-decoration: none;'>📊 Check Database Status</a></p>";
?>