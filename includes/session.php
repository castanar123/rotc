<?php
if (!function_exists('rotc_should_use_database_sessions')) {
    function rotc_should_use_database_sessions() {
        $databaseUrl = getenv('DATABASE_URL');
        $isVercel = getenv('VERCEL') !== false || getenv('VERCEL_ENV') !== false;

        if ($databaseUrl !== false && $databaseUrl !== '') {
            return stripos($databaseUrl, 'mysql') === 0 || stripos($databaseUrl, 'mariadb') === 0;
        }

        if (defined('DB_TYPE') && DB_TYPE !== 'mysql') {
            return false;
        }

        return $isVercel;
    }
}

if (!class_exists('RotcDatabaseSessionHandler')) {
    class RotcDatabaseSessionHandler implements SessionHandlerInterface {
        private $pdo;
        private $tableReady = false;

        public function __construct(PDO $pdo) {
            $this->pdo = $pdo;
        }

        public function open($savePath, $sessionName): bool {
            return $this->ensureTable();
        }

        public function close(): bool {
            return true;
        }

        public function read($id): string {
            if (!$this->ensureTable()) {
                return '';
            }

            try {
                $stmt = $this->pdo->prepare("SELECT data FROM php_sessions WHERE id = ? AND expires_at > ?");
                $stmt->execute([$id, date('Y-m-d H:i:s')]);
                $data = $stmt->fetchColumn();
                return is_string($data) ? $data : '';
            } catch (Throwable $e) {
                error_log('Session read failed: ' . $e->getMessage());
                return '';
            }
        }

        public function write($id, $data): bool {
            if (!$this->ensureTable()) {
                return false;
            }

            try {
                $expiresAt = date('Y-m-d H:i:s', time() + (int)ini_get('session.gc_maxlifetime'));
                $stmt = $this->pdo->prepare("
                    INSERT INTO php_sessions (id, data, expires_at, updated_at)
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                        data = VALUES(data),
                        expires_at = VALUES(expires_at),
                        updated_at = CURRENT_TIMESTAMP
                ");
                return $stmt->execute([$id, $data, $expiresAt]);
            } catch (Throwable $e) {
                error_log('Session write failed: ' . $e->getMessage());
                return false;
            }
        }

        public function destroy($id): bool {
            if (!$this->ensureTable()) {
                return false;
            }

            try {
                $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE id = ?");
                return $stmt->execute([$id]);
            } catch (Throwable $e) {
                error_log('Session destroy failed: ' . $e->getMessage());
                return false;
            }
        }

        public function gc($max_lifetime): int|false {
            if (!$this->ensureTable()) {
                return false;
            }

            try {
                $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE expires_at <= ?");
                $stmt->execute([date('Y-m-d H:i:s')]);
                return $stmt->rowCount();
            } catch (Throwable $e) {
                error_log('Session cleanup failed: ' . $e->getMessage());
                return false;
            }
        }

        private function ensureTable(): bool {
            if ($this->tableReady) {
                return true;
            }

            try {
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS php_sessions (
                        id VARCHAR(128) NOT NULL PRIMARY KEY,
                        data MEDIUMBLOB NOT NULL,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        expires_at TIMESTAMP NOT NULL,
                        KEY idx_php_sessions_expires_at (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $this->tableReady = true;
                return true;
            } catch (Throwable $e) {
                error_log('Session table setup failed: ' . $e->getMessage());
                return false;
            }
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isSecureRequest = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        getenv('VERCEL') !== false
    );

    session_name('ROTCSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecureRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (rotc_should_use_database_sessions()) {
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $dbPath = __DIR__ . '/db.php';
            if (file_exists($dbPath)) {
                require_once $dbPath;
            }
        }

        if (isset($pdo) && $pdo instanceof PDO) {
            session_set_save_handler(new RotcDatabaseSessionHandler($pdo), true);
        }
    }

    session_start();
}

if (!function_exists('rotc_relative_url')) {
    function rotc_relative_url($target) {
        $target = ltrim((string)$target, '/');
        $root = realpath(__DIR__ . '/..');
        $cwd = realpath(getcwd());

        if ($root === false || $cwd === false || stripos($cwd, $root) !== 0 || $cwd === $root) {
            return $target;
        }

        $relative = trim(substr($cwd, strlen($root)), DIRECTORY_SEPARATOR);
        if ($relative === '') {
            return $target;
        }

        $depth = count(array_filter(explode(DIRECTORY_SEPARATOR, $relative), 'strlen'));
        return str_repeat('../', $depth) . $target;
    }
}

if (!function_exists('rotc_normalize_role')) {
    function rotc_normalize_role($role) {
        $role = strtolower(trim((string)$role));
        $role = str_replace([' ', '_'], '-', $role);

        $aliases = [
            'administrator' => 'admin',
            'basic' => 'basic-cadet',
            'basic-cadet' => 'basic-cadet',
            'cadet' => 'basic-cadet',
            'basiccadet' => 'basic-cadet',
            '1cl-officer' => '1cl',
            '2cl-officer' => '2cl',
            'officer-1cl' => '1cl',
            'officer-2cl' => '2cl',
            'officer' => 'officer',
        ];

        return $aliases[$role] ?? $role;
    }
}

if (!function_exists('rotc_role_in')) {
    function rotc_role_in(array $allowedRoles) {
        $currentRole = rotc_normalize_role($_SESSION['role'] ?? '');
        foreach ($allowedRoles as $allowedRole) {
            if ($currentRole === rotc_normalize_role($allowedRole)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('rotc_dashboard_url')) {
    function rotc_dashboard_url($role = null) {
        switch (rotc_normalize_role($role ?? ($_SESSION['role'] ?? 'basic-cadet'))) {
            case 'admin':
                return rotc_relative_url('admin_dashboard.php');
            case 'instructor':
                return rotc_relative_url('instructor_dashboard.php');
            case 'officer':
            case '1cl':
            case '2cl':
            case 'commandant':
                return rotc_relative_url('officer_dashboard.php');
            case 'basic-cadet':
            default:
                return rotc_relative_url('cadet_dashboard.php');
        }
    }
}

if (!function_exists('require_roles')) {
    function require_roles(array $allowedRoles, $redirectTarget = 'login.php') {
        check_login();

        if (!rotc_role_in($allowedRoles)) {
            header('Location: ' . rotc_relative_url($redirectTarget));
            exit;
        }
    }
}

$__requirePin = isset($_SESSION['require_pin']) && $_SESSION['require_pin'] === true;
$__pinVerified = isset($_SESSION['pin_verified']) && $_SESSION['pin_verified'] === true;
if ($__requirePin && !$__pinVerified && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if (!in_array($current, ['verify_pin.php', 'logout.php', 'login.php', 'verify_2fa.php'], true)) {
        header('Location: ' . rotc_relative_url('verify_pin.php'));
        exit;
    }
}

// Function to check if the user is logged in, otherwise redirect to login page
function check_login(){
    if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
        // Redirect to login page (works for both local and production)
        header('Location: ' . rotc_relative_url('login.php'));
        exit;
    }

    $requirePin = isset($_SESSION['require_pin']) && $_SESSION['require_pin'] === true;
    $pinVerified = isset($_SESSION['pin_verified']) && $_SESSION['pin_verified'] === true;
    $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    if ($requirePin && !$pinVerified && $current !== 'verify_pin.php') {
        header('Location: ' . rotc_relative_url('verify_pin.php'));
        exit;
    }
}

// Function to redirect logged-in users to their dashboard
function redirect_to_dashboard(){
    if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
        header('Location: ' . rotc_dashboard_url());
        exit;
    }
}
?>
