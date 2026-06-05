<?php
// Change Duty Officer PIN API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once "../includes/db.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

if (empty($_SESSION['duty_officer_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not authenticated as a duty officer"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$current_pin = trim($input["current_pin"] ?? "");
$new_pin     = trim($input["new_pin"] ?? "");
$confirm_pin = trim($input["confirm_pin"] ?? "");
$officer_id  = (int)$_SESSION['duty_officer_id'];

if ($current_pin === '' || $new_pin === '' || $confirm_pin === '') {
    http_response_code(400);
    echo json_encode(["error" => "All fields are required"]);
    exit;
}
if ($new_pin !== $confirm_pin) {
    http_response_code(400);
    echo json_encode(["error" => "New PIN and confirmation do not match"]);
    exit;
}

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS duty_officer_pins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        officer_id INT NOT NULL UNIQUE,
        pin VARCHAR(64) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Validate current PIN
    $chk = $pdo->prepare("SELECT 1 FROM duty_officer_pins WHERE officer_id = ? AND pin = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
    $chk->execute([$officer_id, $current_pin]);
    if (!$chk->fetchColumn()) {
        http_response_code(401);
        echo json_encode(["error" => "Current PIN is incorrect"]);
        exit;
    }

    // Update PIN
    $up = $pdo->prepare("UPDATE duty_officer_pins SET pin = ?, is_active = 1 WHERE officer_id = ?");
    $up->execute([$new_pin, $officer_id]);
    if ($up->rowCount() === 0) {
        // If no row (in case schema differs), insert
        $ins = $pdo->prepare("INSERT INTO duty_officer_pins (officer_id, pin, is_active) VALUES (?, ?, 1)");
        $ins->execute([$officer_id, $new_pin]);
    }

    echo json_encode(["success" => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}
