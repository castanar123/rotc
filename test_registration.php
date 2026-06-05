<?php
// Test Registration System
// Comprehensive test for all registration-related functionality

require_once 'includes/db.php';

$tests = [];
$overall_status = true;

// Test 1: Database Connection
try {
    $pdo->query("SELECT 1");
    $tests[] = ['name' => 'Database Connection', 'status' => 'PASS', 'message' => 'Successfully connected to rotc_db'];
} catch (Exception $e) {
    $tests[] = ['name' => 'Database Connection', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

// Test 2: Check Registration Tables
$required_tables = [
    'users',
    'cadet_profiles', 
    'registration_requests',
    'registration_documents',
    'registration_status_log'
];

foreach ($required_tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $tests[] = ['name' => "Table: $table", 'status' => 'PASS', 'message' => 'Table exists'];
        } else {
            $tests[] = ['name' => "Table: $table", 'status' => 'FAIL', 'message' => 'Table missing'];
            $overall_status = false;
        }
    } catch (Exception $e) {
        $tests[] = ['name' => "Table: $table", 'status' => 'FAIL', 'message' => $e->getMessage()];
        $overall_status = false;
    }
}

// Test 3: Check Registration Views
$required_views = [
    'pending_registrations_view',
    'registration_stats_view',
    'students'
];

foreach ($required_views as $view) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $view");
        $count = $stmt->fetchColumn();
        $tests[] = ['name' => "View: $view", 'status' => 'PASS', 'message' => "View accessible, contains $count records"];
    } catch (Exception $e) {
        $tests[] = ['name' => "View: $view", 'status' => 'FAIL', 'message' => $e->getMessage()];
        $overall_status = false;
    }
}

// Test 4: Check Users Table Structure
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['id', 'username', 'email', 'password', 'role', 'status', 'created_at'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        $tests[] = ['name' => 'Users Table Structure', 'status' => 'PASS', 'message' => 'All required columns present: ' . implode(', ', $columns)];
    } else {
        $tests[] = ['name' => 'Users Table Structure', 'status' => 'FAIL', 'message' => 'Missing columns: ' . implode(', ', $missing_columns)];
        $overall_status = false;
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Users Table Structure', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

// Test 4.5: Check Registration Fields View
try {
    $stmt = $pdo->query("SELECT COUNT(*) as field_count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'registration_fields_view' AND TABLE_SCHEMA = 'rotc_db'");
    $result = $stmt->fetch();
    $field_count = $result['field_count'];
    
    if ($field_count >= 30) {
        $tests[] = ['name' => 'Registration Fields View', 'status' => 'PASS', 'message' => "View contains $field_count fields - all registration fields available"];
    } else {
        $tests[] = ['name' => 'Registration Fields View', 'status' => 'FAIL', 'message' => "View only contains $field_count fields - missing registration fields"];
        $overall_status = false;
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Registration Fields View', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

// Test 5: Check Cadet Profiles Table Structure
try {
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['id', 'user_id', 'student_id', 'first_name', 'last_name', 'status'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        $tests[] = ['name' => 'Cadet Profiles Table Structure', 'status' => 'PASS', 'message' => 'All required columns present: ' . implode(', ', $columns)];
    } else {
        $tests[] = ['name' => 'Cadet Profiles Table Structure', 'status' => 'FAIL', 'message' => 'Missing columns: ' . implode(', ', $missing_columns)];
        $overall_status = false;
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Cadet Profiles Table Structure', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

// Test 5.5: Check All Required Registration Fields
try {
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // All the fields mentioned by the user
    $registration_fields = [
        'id', 'user_id', 'student_id', 'first_name', 'last_name', 'middle_name',
        'gender', 'email', 'address', 'contact_number', 'course', 'section', 
        'religion', 'birthdate', 'place_of_birth', 'height', 'weight', 
        'skin_color', 'blood_type', 'father', 'father_occupation', 
        'mother', 'mother_occupation', 'guardian', 'guardian_contact', 
        'guardian_relationship', 'guardian_address', 'platoon', 
        'profile_photo', 'created_at', 'updated_at'
    ];
    
    $missing_fields = array_diff($registration_fields, $columns);
    
    if (empty($missing_fields)) {
        $tests[] = ['name' => 'All Registration Fields Present', 'status' => 'PASS', 'message' => 'All ' . count($registration_fields) . ' required registration fields are present in cadet_profiles table'];
    } else {
        $tests[] = ['name' => 'All Registration Fields Present', 'status' => 'FAIL', 'message' => 'Missing registration fields: ' . implode(', ', $missing_fields)];
        $overall_status = false;
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'All Registration Fields Present', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

// Test 6: Test Registration Request Creation
try {
    // Create a test user first
    $test_email = 'test_registration_' . time() . '@example.com';
    $test_username = 'test_reg_' . time();
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, status) 
        VALUES (?, ?, ?, 'cadet', 'pending')
    ");
    $stmt->execute([$test_username, $test_email, password_hash('test123', PASSWORD_DEFAULT)]);
    $test_user_id = $pdo->lastInsertId();
    
    // Create cadet profile
    $stmt = $pdo->prepare("
        INSERT INTO cadet_profiles (user_id, student_id, first_name, last_name, status)
        VALUES (?, ?, 'Test', 'User', 'pending')
    ");
    $stmt->execute([$test_user_id, 'TEST' . time()]);
    
    // Create registration request
    $stmt = $pdo->prepare("
        INSERT INTO registration_requests (user_id, request_type, status, submitted_at)
        VALUES (?, 'new_cadet', 'pending', NOW())
    ");
    $stmt->execute([$test_user_id]);
    $request_id = $pdo->lastInsertId();
    
    $tests[] = ['name' => 'Registration Request Creation', 'status' => 'PASS', 'message' => "Successfully created test registration request (ID: $request_id)"];
    
    // Clean up test data
    $pdo->prepare("DELETE FROM registration_requests WHERE id = ?")->execute([$request_id]);
    $pdo->prepare("DELETE FROM cadet_profiles WHERE user_id = ?")->execute([$test_user_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$test_user_id]);
    
} catch (Exception $e) {
    $tests[] = ['name' => 'Registration Request Creation', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

// Test 7: Test Registration Statistics View
try {
    $stmt = $pdo->query("SELECT * FROM registration_stats_view");
    $stats = $stmt->fetch();
    
    if ($stats && isset($stats['total_requests'])) {
        $tests[] = ['name' => 'Registration Statistics', 'status' => 'PASS', 'message' => 
            "Total: {$stats['total_requests']}, Pending: {$stats['pending_count']}, Approved: {$stats['approved_count']}, Rejected: {$stats['rejected_count']}"];
    } else {
        $tests[] = ['name' => 'Registration Statistics', 'status' => 'FAIL', 'message' => 'Statistics view returned no data'];
        $overall_status = false;
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Registration Statistics', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

// Test 8: Test Pending Registrations View
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pending_registrations_view");
    $result = $stmt->fetch();
    $count = $result['count'];
    
    $tests[] = ['name' => 'Pending Registrations View', 'status' => 'PASS', 'message' => "Found $count pending registrations"];
} catch (Exception $e) {
    $tests[] = ['name' => 'Pending Registrations View', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

// Test 9: Check File Permissions for Registration Management
$registration_files = [
    'registration_management.php',
    'register.php'
];

foreach ($registration_files as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            $tests[] = ['name' => "File Access: $file", 'status' => 'PASS', 'message' => 'File exists and is readable'];
        } else {
            $tests[] = ['name' => "File Access: $file", 'status' => 'FAIL', 'message' => 'File exists but is not readable'];
            $overall_status = false;
        }
    } else {
        $tests[] = ['name' => "File Access: $file", 'status' => 'FAIL', 'message' => 'File does not exist'];
        $overall_status = false;
    }
}

// Test 10: Test Status Enum Values
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
    $column = $stmt->fetch();
    
    if ($column && strpos($column['Type'], 'enum') !== false) {
        $tests[] = ['name' => 'Users Status Enum', 'status' => 'PASS', 'message' => 'Status column: ' . $column['Type']];
    } else {
        $tests[] = ['name' => 'Users Status Enum', 'status' => 'FAIL', 'message' => 'Status column is not an enum or missing'];
        $overall_status = false;
    }
} catch (Exception $e) {
    $tests[] = ['name' => 'Users Status Enum', 'status' => 'FAIL', 'message' => $e->getMessage()];
    $overall_status = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration System Test Results</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .overall-status {
            padding: 1.5rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .overall-status.pass {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .overall-status.fail {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .tests-container {
            padding: 2rem;
        }
        
        .test-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 5px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .test-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .test-item.pass {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        
        .test-item.fail {
            border-left-color: #dc3545;
            background: #fff8f8;
        }
        
        .test-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .test-name {
            font-weight: bold;
            font-size: 1.1rem;
            color: #2c3e50;
        }
        
        .test-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .test-status.pass {
            background: #28a745;
            color: white;
        }
        
        .test-status.fail {
            background: #dc3545;
            color: white;
        }
        
        .test-message {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .summary {
            background: #f8f9fa;
            padding: 2rem;
            border-top: 1px solid #dee2e6;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-card .number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .summary-card .label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .summary-card.total .number { color: #007bff; }
        .summary-card.pass .number { color: #28a745; }
        .summary-card.fail .number { color: #dc3545; }
        
        .actions {
            padding: 2rem;
            text-align: center;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            margin: 0 0.5rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn.success {
            background: #28a745;
        }
        
        .btn.success:hover {
            background: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-clipboard-check"></i> Registration System Test</h1>
            <p>Comprehensive testing of ROTC registration functionality</p>
        </div>
        
        <div class="overall-status <?php echo $overall_status ? 'pass' : 'fail'; ?>">
            <i class="fas <?php echo $overall_status ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo $overall_status ? 'ALL TESTS PASSED' : 'SOME TESTS FAILED'; ?>
        </div>
        
        <div class="tests-container">
            <?php foreach ($tests as $test): ?>
            <div class="test-item <?php echo strtolower($test['status']); ?>">
                <div class="test-header">
                    <div class="test-name">
                        <i class="fas <?php echo $test['status'] === 'PASS' ? 'fa-check' : 'fa-times'; ?>"></i>
                        <?php echo htmlspecialchars($test['name']); ?>
                    </div>
                    <div class="test-status <?php echo strtolower($test['status']); ?>">
                        <?php echo $test['status']; ?>
                    </div>
                </div>
                <div class="test-message">
                    <?php echo htmlspecialchars($test['message']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="summary">
            <h3><i class="fas fa-chart-bar"></i> Test Summary</h3>
            <div class="summary-grid">
                <div class="summary-card total">
                    <div class="number"><?php echo count($tests); ?></div>
                    <div class="label">Total Tests</div>
                </div>
                <div class="summary-card pass">
                    <div class="number"><?php echo count(array_filter($tests, function($t) { return $t['status'] === 'PASS'; })); ?></div>
                    <div class="label">Passed</div>
                </div>
                <div class="summary-card fail">
                    <div class="number"><?php echo count(array_filter($tests, function($t) { return $t['status'] === 'FAIL'; })); ?></div>
                    <div class="label">Failed</div>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <a href="registration_management.php" class="btn success">
                <i class="fas fa-user-check"></i>
                Go to Registration Management
            </a>
            <a href="register.php" class="btn">
                <i class="fas fa-user-plus"></i>
                Test Registration Form
            </a>
            <a href="javascript:location.reload()" class="btn">
                <i class="fas fa-sync-alt"></i>
                Run Tests Again
            </a>
        </div>
    </div>
</body>
</html>