<?php
// Compatibility PDO connection used by older modules.
require_once __DIR__ . '/db_config.php';

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

try {
    if (defined('DB_TYPE') && DB_TYPE === 'sqlite') {
        $pdo = new PDO('sqlite:' . DB_PATH);
    } else {
        $pdo = new PDO(rotc_mysql_dsn(DB_SERVER, DB_NAME), DB_USERNAME, DB_PASSWORD);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $GLOBALS['DB_CONNECTION_ERROR'] = $e->getMessage();
    $pdo = null;
}
?>
