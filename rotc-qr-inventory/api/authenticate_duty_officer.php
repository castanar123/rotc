<?php
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
    // Ensure PIN table exists (officers table does not need a PIN column)
    $pdo->exec("CREATE TABLE IF NOT EXISTS duty_officer_pins (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        officer_id INT NOT NULL UNIQUE,\n        pin VARCHAR(64) NOT NULL,\n        is_active TINYINT(1) NOT NULL DEFAULT 1,\n        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,\n        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Use officers.status filter only if that column exists
    $hasStatus = false;
    try { $hasStatus = (bool)$pdo->query("SHOW COLUMNS FROM officers LIKE 'status'")->fetch(); } catch (Exception $e) {}
    $statusCond = $hasStatus ? " AND o.status = 'active'" : "";

    // Special handling: if pin is '0000' and there is exactly one officer with no PIN row, seed and authenticate
    if ($pin === '0000') {
        $sqlNoPin = "SELECT o.id FROM officers o LEFT JOIN duty_officer_pins p ON o.id = p.officer_id WHERE p.officer_id IS NULL" . $statusCond . " LIMIT 2";
        $rs = $pdo->query($sqlNoPin);
        $rows = $rs ? $rs->fetchAll(PDO::FETCH_ASSOC) : [];
        if (count($rows) === 1) {
            $oid = (int)$rows[0]['id'];
            // Seed default 0000
            $ins = $pdo->prepare("INSERT INTO duty_officer_pins (officer_id, pin, is_active) VALUES (?, '0000', 1)");
            try { $ins->execute([$oid]); } catch (Exception $e) { /* ignore if inserted in race */ }
            echo json_encode(["success" => true, "officer" => ["id" => $oid]]);
            exit;
        }
        // Ambiguous or none: fall through to normal lookup -> will return invalid
    }

    // Check if PIN exists and is active; select only officer id
    $sql = "SELECT o.id\n            FROM officers o\n            JOIN duty_officer_pins p ON o.id = p.officer_id\n            WHERE p.pin = ? AND (p.is_active = 1 OR p.is_active IS NULL)" . $statusCond . " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pin]);
    $officer = $stmt->fetch();
    
    if ($officer) {
        echo json_encode([
            "success" => true,
            "officer" => [ "id" => $officer["id"] ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["error" => "Invalid PIN"]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}
?>