<?php
// Database configuration with environment detection and optional overrides

// Shared config with optional local/environment overrides.
$__localCfg = __DIR__ . '/db_config.php';
if (file_exists($__localCfg)) {
    require_once $__localCfg; // may define DB_TYPE, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME
}

// Detect if running on production environment (disabled for Cloudflare tunnels)
function isProductionEnvironment() {
    // Cloudflare tunnels should use local MySQL, not SQLite
    // Only return true for actual production deployments, not tunneled localhost
    return false; // Always use MySQL for both localhost and Cloudflare tunnels
}

if (isProductionEnvironment()) {
    // Production/Cloudflare environment - use SQLite unless overridden
    if (!defined('DB_TYPE')) define('DB_TYPE', 'sqlite');
    if (!defined('DB_PATH')) define('DB_PATH', __DIR__ . '/../data/rotc_db.sqlite');
    if (!defined('DB_SERVER')) define('DB_SERVER', '');
    if (!defined('DB_USERNAME')) define('DB_USERNAME', '');
    if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
    if (!defined('DB_NAME')) define('DB_NAME', 'rotc_db');
} else {
    // Local development environment (XAMPP) - Using MySQL unless overridden
    if (!defined('DB_TYPE')) define('DB_TYPE', 'mysql');
    if (!defined('DB_PATH')) define('DB_PATH', '');
    if (!defined('DB_SERVER')) define('DB_SERVER', getenv('ROTC_DB_SERVER') ?: 'localhost:3306');
    if (!defined('DB_USERNAME')) define('DB_USERNAME', getenv('ROTC_DB_USER') ?: 'root');
    if (!defined('DB_PASSWORD')) define('DB_PASSWORD', getenv('ROTC_DB_PASS') ?: '');
    if (!defined('DB_NAME')) define('DB_NAME', getenv('ROTC_DB_NAME') ?: 'rotc_db');
}

if (!function_exists('rotc_parse_mysql_server')) {
    function rotc_parse_mysql_server($server) {
        $host = $server;
        $port = null;

        if (strpos($server, ':') !== false) {
            list($hostOnly, $portPart) = explode(':', $server, 2);
            if ($hostOnly !== '') {
                $host = $hostOnly;
            }
            if ($portPart !== '') {
                $port = (int) $portPart;
            }
        }

        return [$host, $port];
    }
}

if (!function_exists('rotc_mysql_dsn')) {
    function rotc_mysql_dsn($server, $database) {
        list($host, $port) = rotc_parse_mysql_server($server);
        $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
        if ($port) {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        }
        return $dsn;
    }
}

if (!function_exists('rotc_bool_env')) {
    function rotc_bool_env($value) {
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on', 'required'], true);
    }
}

if (!function_exists('rotc_mysql_ssl_enabled')) {
    function rotc_mysql_ssl_enabled() {
        return defined('DB_SSL') && rotc_bool_env(DB_SSL);
    }
}

if (!function_exists('rotc_mysql_ssl_ca_path')) {
    function rotc_mysql_ssl_ca_path() {
        if (!rotc_mysql_ssl_enabled()) {
            return '';
        }

        if (defined('DB_SSL_CA') && DB_SSL_CA !== '') {
            return DB_SSL_CA;
        }

        $bundledCa = __DIR__ . '/../certs/isrgrootx1.pem';
        return file_exists($bundledCa) ? $bundledCa : '';
    }
}

if (!function_exists('rotc_mysql_pdo_options')) {
    function rotc_mysql_pdo_options() {
        $options = [];
        $caPath = rotc_mysql_ssl_ca_path();

        if ($caPath !== '' && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
        }

        return $options;
    }
}

if (!function_exists('rotc_mysqli_connect')) {
    function rotc_mysqli_connect($server, $username, $password, $database) {
        list($mysqlHost, $mysqlPort) = rotc_parse_mysql_server($server);

        if (!rotc_mysql_ssl_enabled()) {
            return new mysqli($mysqlHost, $username, $password, $database, $mysqlPort ?: null);
        }

        $mysqli = mysqli_init();
        if (!$mysqli) {
            throw new mysqli_sql_exception('Failed to initialize mysqli');
        }

        $caPath = rotc_mysql_ssl_ca_path();
        if ($caPath !== '') {
            $mysqli->ssl_set(null, null, $caPath, null, null);
        }

        $flags = defined('MYSQLI_CLIENT_SSL') ? MYSQLI_CLIENT_SSL : 0;
        $mysqli->real_connect($mysqlHost, $username, $password, $database, $mysqlPort ?: null, null, $flags);

        return $mysqli;
    }
}

// --- Environment-Aware Database Connection ---
global $pdo, $link;

try {
    if (DB_TYPE === 'sqlite') {
        // SQLite connection for production/Cloudflare
        
        // Ensure data directory exists
        $dataDir = dirname(DB_PATH);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        if (class_exists('PDO')) {
            // Create PDO SQLite connection
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign key constraints for SQLite
            $pdo->exec('PRAGMA foreign_keys = ON');
            
            // Initialize database if it doesn't exist
            initializeSQLiteDatabase($pdo);
            
            // For compatibility, create a mysqli-like wrapper
            $link = new SQLiteWrapper($pdo);
        } else {
            // PDO extension not available; cannot use SQLite driver
            $pdo = null;
            $link = null;
            throw new Exception('PDO extension not available for SQLite');
        }
        
    } else {
        // MySQL connection for local development
        
        // Enable error reporting for mysqli to throw exceptions
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        // Create a new mysqli object (Object-Oriented style)
        $link = rotc_mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        
        // Set the character set to utf8mb4 for full Unicode support
        $link->set_charset("utf8mb4");
        
        // Also create PDO connection for compatibility (parse host:port if provided)
        if (class_exists('PDO')) {
            $pdo = new PDO(rotc_mysql_dsn(DB_SERVER, DB_NAME), DB_USERNAME, DB_PASSWORD, rotc_mysql_pdo_options());
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Keep SQL strict without deprecated modes removed in MySQL 8.
            $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $link->query("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } else {
            // PDO extension not available; continue with mysqli-only mode
            $pdo = null;
        }
    }
    
} catch (mysqli_sql_exception $e) {
    // Log and set global error instead of dying, so APIs can respond with JSON
    error_log("Database connection failed: " . $e->getMessage());
    $GLOBALS['DB_CONNECTION_ERROR'] = $e->getMessage();
    if (isset($pdo)) { $pdo = null; }
    if (isset($link)) { $link = null; }
} catch (PDOException $e) {
    error_log("PDO Database connection failed: " . $e->getMessage());
    $GLOBALS['DB_CONNECTION_ERROR'] = $e->getMessage();
    if (isset($pdo)) { $pdo = null; }
    if (isset($link)) { $link = null; }
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    $GLOBALS['DB_CONNECTION_ERROR'] = $e->getMessage();
    if (isset($pdo)) { $pdo = null; }
    if (isset($link)) { $link = null; }
}

// --- Secondary fallback attempt for common XAMPP default (empty password) ---
if (DB_TYPE === 'mysql' && !rotc_mysql_ssl_enabled() && (!isset($link) || !$link) && isset($GLOBALS['DB_CONNECTION_ERROR'])) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $fallbackPassword = '';
        $link = rotc_mysqli_connect(DB_SERVER, DB_USERNAME, $fallbackPassword, DB_NAME);
        $link->set_charset("utf8mb4");
        $pdo = new PDO(rotc_mysql_dsn(DB_SERVER, DB_NAME), DB_USERNAME, $fallbackPassword, rotc_mysql_pdo_options());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $link->query("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        unset($GLOBALS['DB_CONNECTION_ERROR']);
        error_log('DB connection fallback with empty password succeeded.');
    } catch (Throwable $e) {
        error_log('DB fallback connection failed: ' . $e->getMessage());
    }
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

// Initialize SQLite database with required tables
function initializeSQLiteDatabase($pdo) {
    // Check if tables exist
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if ($result->fetch()) {
        return; // Database already initialized
    }
    
    // Create tables with complete structure matching MySQL
    $sql = "
    CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_name VARCHAR(255) NOT NULL,
        description TEXT,
        total_quantity INTEGER NOT NULL DEFAULT 0,
        available_quantity INTEGER NOT NULL DEFAULT 0,
        borrowed_quantity INTEGER NOT NULL DEFAULT 0,
        unit VARCHAR(20) DEFAULT 'pcs',
        location VARCHAR(100),
        condition_status VARCHAR(20) DEFAULT 'good',
        qr_code VARCHAR(255) UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        role VARCHAR(20) DEFAULT 'basic_cadet',
        is_active INTEGER DEFAULT 1,
        two_factor_enabled INTEGER DEFAULT 0,
        two_factor_secret VARCHAR(32),
        two_factor_backup TEXT,
        failed_login_attempts INTEGER DEFAULT 0,
        locked_until DATETIME NULL,
        last_login DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS user_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        session_token VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    
    CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action VARCHAR(100) NOT NULL,
        table_name VARCHAR(50),
        record_id INTEGER,
        old_values TEXT,
        new_values TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    ";
    
    $pdo->exec($sql);
    
    // Insert 20 users with proper roles and settings
    $users = [
        // Administrators (2)
        ['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin@rotc.mil', 'System Administrator', 'admin', 1, 1],
        ['admin2', password_hash('admin123', PASSWORD_DEFAULT), 'admin2@rotc.mil', 'Deputy Administrator', 'admin', 1, 0],
        
        // Instructors (3)
        ['instructor1', password_hash('admin123', PASSWORD_DEFAULT), 'instructor1@rotc.mil', 'Senior Instructor', 'instructor', 1, 1],
        ['instructor2', password_hash('admin123', PASSWORD_DEFAULT), 'instructor2@rotc.mil', 'Training Instructor', 'instructor', 1, 0],
        ['instructor3', password_hash('admin123', PASSWORD_DEFAULT), 'instructor3@rotc.mil', 'Drill Instructor', 'instructor', 1, 1],
        
        // Officers (3)
        ['officer1cl', password_hash('admin123', PASSWORD_DEFAULT), 'officer1cl@rotc.mil', 'First Class Officer', '1cl_officer', 1, 0],
        ['officer2cl', password_hash('admin123', PASSWORD_DEFAULT), 'officer2cl@rotc.mil', 'Second Class Officer', '2cl_officer', 1, 1],
        ['officer2cl2', password_hash('admin123', PASSWORD_DEFAULT), 'officer2cl2@rotc.mil', 'Second Class Officer 2', '2cl_officer', 1, 0],
        
        // Commandant (1)
        ['commandant', password_hash('admin123', PASSWORD_DEFAULT), 'commandant@rotc.mil', 'Unit Commandant', 'commandant', 1, 1],
        
        // Cadets and Basic Cadets (11)
        ['cadet1', password_hash('admin123', PASSWORD_DEFAULT), 'cadet1@rotc.mil', 'Cadet Alpha', 'cadet', 1, 0],
        ['cadet2', password_hash('admin123', PASSWORD_DEFAULT), 'cadet2@rotc.mil', 'Cadet Bravo', 'cadet', 1, 1],
        ['cadet3', password_hash('admin123', PASSWORD_DEFAULT), 'cadet3@rotc.mil', 'Cadet Charlie', 'cadet', 1, 0],
        ['cadet4', password_hash('admin123', PASSWORD_DEFAULT), 'cadet4@rotc.mil', 'Cadet Delta', 'cadet', 1, 1],
        ['basic1', password_hash('admin123', PASSWORD_DEFAULT), 'basic1@rotc.mil', 'Basic Cadet Echo', 'basic_cadet', 1, 0],
        ['basic2', password_hash('admin123', PASSWORD_DEFAULT), 'basic2@rotc.mil', 'Basic Cadet Foxtrot', 'basic_cadet', 1, 1],
        ['basic3', password_hash('admin123', PASSWORD_DEFAULT), 'basic3@rotc.mil', 'Basic Cadet Golf', 'basic_cadet', 1, 0],
        ['basic4', password_hash('admin123', PASSWORD_DEFAULT), 'basic4@rotc.mil', 'Basic Cadet Hotel', 'basic_cadet', 1, 0],
        ['basic5', password_hash('admin123', PASSWORD_DEFAULT), 'basic5@rotc.mil', 'Basic Cadet India', 'basic_cadet', 1, 1],
        ['basic6', password_hash('admin123', PASSWORD_DEFAULT), 'basic6@rotc.mil', 'Basic Cadet Juliet', 'basic_cadet', 1, 0],
        ['basic7', password_hash('admin123', PASSWORD_DEFAULT), 'basic7@rotc.mil', 'Basic Cadet Kilo', 'basic_cadet', 1, 1]
    ];
    
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password, email, full_name, role, is_active, two_factor_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($users as $user) {
        $stmt->execute($user);
    }
}

?>
