<?php
// Database configuration for ROTC QR Inventory System
// This file is used by API endpoints

$host = 'localhost:3306';
$dbname = 'rotc_db'; // Use main ROTC database
$username = 'root';
$password = 'root';

// DSN for PDO connection
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

// PDO options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Test connection (optional - can be removed in production)
try {
    $test_pdo = new PDO($dsn, $username, $password, $options);
    // Connection successful
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // In production, you might want to handle this differently
}
?>