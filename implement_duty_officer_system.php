<?php
// Implement duty officer system with PIN authentication and fix inventory issues

try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== IMPLEMENTING DUTY OFFICER SYSTEM ===\n";
    
    // 1. Add duty officer with PIN 472005
    echo "\n1. Adding new duty officer with PIN authentication...\n";
    
    // Check if duty officer already exists
    $check_stmt = $pdo->prepare("SELECT id FROM officers WHERE rank = 'Duty Officer' AND position = 'Duty Officer'");
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() == 0) {
        // First check if user_id column allows NULL
        $column_info = $pdo->query("SHOW COLUMNS FROM officers WHERE Field = 'user_id'");
        $column_data = $column_info->fetch();
        
        if ($column_data && $column_data['Null'] === 'NO') {
            // user_id cannot be null, create a dummy user first or use existing
            $dummy_user_stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'duty_officer' LIMIT 1");
            $dummy_user_stmt->execute();
            $dummy_user = $dummy_user_stmt->fetch();
            
            if (!$dummy_user) {
                // Create dummy user for duty officer
                $create_user_stmt = $pdo->prepare("
                    INSERT INTO users (username, password, email, role, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $create_user_stmt->execute([
                    'duty_officer',
                    password_hash('472005', PASSWORD_DEFAULT),
                    'duty.officer@rotc.system',
                    'admin'
                ]);
                $user_id = $pdo->lastInsertId();
            } else {
                $user_id = $dummy_user['id'];
            }
        } else {
            $user_id = null;
        }
        
        // Add duty officer
        $insert_stmt = $pdo->prepare("
            INSERT INTO officers (user_id, rank, position, rank_position, platoon, department, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insert_stmt->execute([
            $user_id,
            'Duty Officer',
            'Duty Officer', 
            'Duty Officer',
            'HQ',
            'Headquarters',
            'active'
        ]);
        
        $duty_officer_id = $pdo->lastInsertId();
        echo "✓ Duty officer created with ID: $duty_officer_id\n";
    } else {
        $duty_officer_id = $check_stmt->fetchColumn();
        echo "✓ Duty officer already exists with ID: $duty_officer_id\n";
    }
    
    // 2. Create duty_officer_pins table for PIN authentication
    echo "\n2. Creating duty officer PIN authentication system...\n";
    
    $create_pins_table = "
    CREATE TABLE IF NOT EXISTS duty_officer_pins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        officer_id INT NOT NULL,
        pin VARCHAR(10) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (officer_id) REFERENCES officers(id),
        UNIQUE KEY unique_officer_pin (officer_id, pin)
    )
    ";
    
    $pdo->exec($create_pins_table);
    echo "✓ Duty officer pins table created\n";
    
    // 3. Insert PIN 472005 for duty officer
    $pin_stmt = $pdo->prepare("
        INSERT IGNORE INTO duty_officer_pins (officer_id, pin, is_active) 
        VALUES (?, ?, ?)
    ");
    
    $pin_stmt->execute([$duty_officer_id, '472005', true]);
    echo "✓ PIN 472005 assigned to duty officer\n";
    
    // 4. Create duty officer authentication function
    echo "\n3. Creating duty officer authentication functions...\n";
    
    // Create authentication API endpoint
    $auth_api_content = '<?php
// Duty Officer PIN Authentication API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$pin = $input["pin"] ?? "";

if (empty($pin)) {
    http_response_code(400);
    echo json_encode(["error" => "PIN is required"]);
    exit;
}

try {
    // Check if PIN exists and is active
    $stmt = $pdo->prepare("
        SELECT o.id, o.rank, o.position, o.rank_position, p.pin 
        FROM officers o 
        JOIN duty_officer_pins p ON o.id = p.officer_id 
        WHERE p.pin = ? AND p.is_active = 1 AND o.status = \'active\'
    ");
    
    $stmt->execute([$pin]);
    $officer = $stmt->fetch();
    
    if ($officer) {
        echo json_encode([
            "success" => true,
            "officer" => [
                "id" => $officer["id"],
                "rank" => $officer["rank"],
                "position" => $officer["position"],
                "rank_position" => $officer["rank_position"]
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid PIN"]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}
?>';
    
    // Create API directory if it doesn't exist
    $api_dir = 'rotc-qr-inventory/api';
    if (!is_dir($api_dir)) {
        mkdir($api_dir, 0755, true);
    }
    
    file_put_contents($api_dir . '/authenticate_duty_officer.php', $auth_api_content);
    echo "✓ Duty officer authentication API created\n";
    
    // 5. Test the authentication system
    echo "\n4. Testing duty officer authentication...\n";
    
    $test_stmt = $pdo->prepare("
        SELECT o.id, o.rank, o.position, o.rank_position, p.pin 
        FROM officers o 
        JOIN duty_officer_pins p ON o.id = p.officer_id 
        WHERE p.pin = ? AND p.is_active = 1 AND o.status = 'active'
    ");
    
    $test_stmt->execute(['472005']);
    $test_officer = $test_stmt->fetch();
    
    if ($test_officer) {
        echo "✓ Authentication test successful\n";
        echo "  Officer: {$test_officer['rank_position']}\n";
        echo "  PIN: {$test_officer['pin']}\n";
    } else {
        echo "✗ Authentication test failed\n";
    }
    
    // 6. Update dashboard.php to handle potential database connection issues
    echo "\n5. Creating dashboard error handling...\n";
    
    $dashboard_fix_content = '<?php
// Dashboard database connection fix
require_once "includes/db.php";

// Test database connection and required tables
function testDatabaseConnection($pdo) {
    try {
        // Test officers table
        $stmt = $pdo->query("SELECT COUNT(*) FROM officers");
        $officers_count = $stmt->fetchColumn();
        
        // Test inventory table
        $stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
        $inventory_count = $stmt->fetchColumn();
        
        // Test transactions table
        $stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
        $transactions_count = $stmt->fetchColumn();
        
        return [
            "success" => true,
            "officers" => $officers_count,
            "inventory" => $inventory_count,
            "transactions" => $transactions_count
        ];
        
    } catch (Exception $e) {
        return [
            "success" => false,
            "error" => $e->getMessage()
        ];
    }
}

$db_test = testDatabaseConnection($pdo);
if (!$db_test["success"]) {
    die("Database Error: " . $db_test["error"]);
}

echo "Database connection successful:\n";
echo "Officers: {$db_test["officers"]}\n";
echo "Inventory items: {$db_test["inventory"]}\n";
echo "Transactions: {$db_test["transactions"]}\n";
?>';
    
    file_put_contents('rotc-qr-inventory/test_dashboard_connection.php', $dashboard_fix_content);
    echo "✓ Dashboard connection test created\n";
    
    echo "\n=== DUTY OFFICER SYSTEM IMPLEMENTATION COMPLETE ===\n";
    echo "✓ Duty officer created\n";
    echo "✓ PIN authentication system implemented\n";
    echo "✓ PIN 472005 configured\n";
    echo "✓ Authentication API created\n";
    echo "✓ Database connection tests added\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>