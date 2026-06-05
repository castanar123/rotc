<?php
// Database configuration
define('DB_SERVER', 'localhost:3306');
define('DB_USERNAME', 'root'); // Your MySQL username
define('DB_PASSWORD', 'root'); // Your MySQL password
define('DB_NAME', 'rotc_db'); // Updated to use rotc_db where cadet data is stored

// --- Improved Object-Oriented Connection ---

// Enable error reporting for mysqli to throw exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create a new mysqli object (Object-Oriented style)
    $link = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Set the character set to utf8mb4 for full Unicode support
    $link->set_charset("utf8mb4");

    // Also create PDO connection for compatibility
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (mysqli_sql_exception $e) {
    // In a production environment, you would log this to a file
    error_log("Database connection failed: " . $e->getMessage());
    
    // Display a generic, user-friendly error message
    die("ERROR: A database connection error occurred. Please try again later.");
} catch (PDOException $e) {
    // In a production environment, you would log this to a file
    error_log("PDO Database connection failed: " . $e->getMessage());
    
    // Display a generic, user-friendly error message
    die("ERROR: A database connection error occurred. Please try again later.");
}
?>