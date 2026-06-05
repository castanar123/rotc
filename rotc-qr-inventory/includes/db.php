<?php
// Database configuration for ROTC QR Inventory System with environment detection

// Detect if running on Cloudflare or production environment
function isProductionEnvironment() {
    // Force local development for debugging
    return false;
    
    // Check for Cloudflare headers or production indicators
    return isset($_SERVER['HTTP_CF_RAY']) || 
           isset($_SERVER['HTTP_CF_CONNECTING_IP']) ||
           (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], '.trycloudflare.com') !== false) ||
           (isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], '.trycloudflare.com') !== false);
}

if (isProductionEnvironment()) {
    // Production/Cloudflare environment - use SQLite
    define('DB_TYPE', 'sqlite');
    define('DB_PATH', __DIR__ . '/../data/rotc_qr_inventory.sqlite');
    define('DB_SERVER', '');
    define('DB_USERNAME', '');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'rotc_qr_inventory');
} else {
    // Local development environment
    define('DB_TYPE', 'mysql');
    define('DB_SERVER', 'localhost:3306');
    define('DB_USERNAME', 'root'); // Your MySQL username
    define('DB_PASSWORD', 'root'); // Your MySQL password
    define('DB_NAME', 'rotc_db'); // Updated to use main ROTC database
}

// --- Environment-Aware Database Connection ---

try {
    if (DB_TYPE === 'sqlite') {
        // SQLite connection for production/Cloudflare
        
        // Ensure data directory exists
        $dataDir = dirname(DB_PATH);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // Create PDO SQLite connection
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Enable foreign key constraints for SQLite
        $pdo->exec('PRAGMA foreign_keys = ON');
        
        // Initialize database if it doesn't exist
        initializeROTCSQLiteDatabase($pdo);
        
        // For compatibility, create a mysqli-like wrapper
        $link = new SQLiteWrapper($pdo);
        
    } else {
        // MySQL connection for local development
        
        // Enable error reporting for mysqli to throw exceptions
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        // Create a new mysqli object (Object-Oriented style)
        $link = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        // Set the character set to utf8mb4 for full Unicode support
        $link->set_charset("utf8mb4");
        
        // Also create PDO connection for compatibility
        $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Set strict SQL mode to enforce ENUM constraints
        $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
        $link->query("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
    }
    
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
} catch (Exception $e) {
    // Catch any other exceptions
    error_log("Database initialization failed: " . $e->getMessage());
    
    // Display a generic, user-friendly error message
    die("ERROR: A database connection error occurred. Please try again later.");
}

// SQLite wrapper class to provide mysqli-like interface
class SQLiteWrapper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function query($sql) {
        return $this->pdo->query($sql);
    }
    
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
    
    public function real_escape_string($string) {
        return str_replace("'", "''", $string);
    }
    
    public function insert_id() {
        return $this->pdo->lastInsertId();
    }
    
    public function affected_rows() {
        return $this->pdo->rowCount();
    }
}

// Initialize ROTC SQLite database with required tables
function initializeROTCSQLiteDatabase($pdo) {
    // Check if tables exist
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='items'");
    if ($result->fetch()) {
        return; // Database already initialized
    }
    
    // Create tables based on ROTC QR Inventory schema
    $sql = "
    CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_name VARCHAR(255) NOT NULL,
        description TEXT,
        total_quantity INTEGER NOT NULL DEFAULT 0,
        available_quantity INTEGER NOT NULL DEFAULT 0,
        quantity_available INTEGER NOT NULL DEFAULT 0,
        borrowed_quantity INTEGER NOT NULL DEFAULT 0,
        unit VARCHAR(20) DEFAULT 'pcs',
        location VARCHAR(100),
        condition_status VARCHAR(20) DEFAULT 'good',
        qr_code VARCHAR(255) UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS officers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        rank VARCHAR(50),
        position VARCHAR(100),
        contact VARCHAR(100),
        email VARCHAR(100),
        status VARCHAR(20) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transaction_type VARCHAR(20) NOT NULL,
        duty_officer_id INTEGER,
        total_items INTEGER DEFAULT 0,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (duty_officer_id) REFERENCES officers(id)
    );
    
    CREATE TABLE IF NOT EXISTS transaction_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transaction_id INTEGER NOT NULL,
        item_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        action VARCHAR(20) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id),
        FOREIGN KEY (item_id) REFERENCES items(id)
    );
    
    CREATE TABLE IF NOT EXISTS borrowed_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_id INTEGER NOT NULL,
        borrower_name VARCHAR(255) NOT NULL,
        borrower_contact VARCHAR(255),
        quantity_borrowed INTEGER NOT NULL,
        borrow_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        expected_return_date DATE,
        actual_return_date DATETIME NULL,
        status VARCHAR(20) DEFAULT 'borrowed',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES items(id)
    );
    
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        full_name VARCHAR(255),
        role VARCHAR(20) DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    $pdo->exec($sql);
    
    // Insert default admin user
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin@rotc.mil', 'ROTC Administrator', 'admin']);
    
    // Insert sample officers
    $sampleOfficers = [
        ['Captain John Smith', 'Captain', 'Commanding Officer', '+1-555-0101', 'john.smith@rotc.mil', 'active'],
        ['Lieutenant Sarah Johnson', 'Lieutenant', 'Supply Officer', '+1-555-0102', 'sarah.johnson@rotc.mil', 'active'],
        ['Sergeant Mike Davis', 'Sergeant', 'Equipment Manager', '+1-555-0103', 'mike.davis@rotc.mil', 'active']
    ];
    
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO officers (name, rank, position, contact, email, status) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($sampleOfficers as $officer) {
        $stmt->execute($officer);
    }
    
    // Insert sample data for testing
    $sampleItems = [
        ['M16 Rifle', 'Standard issue rifle for training', 10, 8, 8, 2, 'pcs', 'Armory A', 'good'],
        ['Combat Helmet', 'Protective headgear', 25, 20, 20, 5, 'pcs', 'Equipment Room B', 'excellent'],
        ['Field Pack', 'Standard military backpack', 15, 12, 12, 3, 'pcs', 'Supply Room C', 'good']
    ];
    
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO items (item_name, description, total_quantity, available_quantity, quantity_available, borrowed_quantity, unit, location, condition_status, qr_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($sampleItems as $index => $item) {
        $qrCode = 'QR' . str_pad($index + 1, 6, '0', STR_PAD_LEFT);
        $stmt->execute(array_merge($item, [$qrCode]));
    }
}

?>