<?php
// Shared DB configuration for all scanner/attendance modules.
// Machine-specific credentials belong in includes/db_config.local.php or
// ROTC_DB_* environment variables, not in source control.

if (!function_exists('rotc_env')) {
    function rotc_env($key, $default = '')
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('rotc_has_database_env')) {
    function rotc_has_database_env()
    {
        $keys = ['DATABASE_URL', 'ROTC_DB_TYPE', 'ROTC_DB_SERVER', 'ROTC_DB_USER', 'ROTC_DB_PASS', 'ROTC_DB_NAME'];
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                return true;
            }
        }

        return false;
    }
}

$localConfig = __DIR__ . '/db_config.local.php';
if (!rotc_has_database_env() && file_exists($localConfig)) {
    require_once $localConfig;
}

if (!function_exists('rotc_database_url_config')) {
    function rotc_database_url_config()
    {
        $databaseUrl = rotc_env('DATABASE_URL', '');
        if ($databaseUrl === '') {
            return [];
        }

        $parts = parse_url($databaseUrl);
        if ($parts === false || empty($parts['host'])) {
            return [];
        }

        $scheme = strtolower($parts['scheme'] ?? 'mysql');
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        return [
            'type' => str_contains($scheme, 'sqlite') ? 'sqlite' : 'mysql',
            'server' => $host . $port,
            'username' => isset($parts['user']) ? rawurldecode($parts['user']) : '',
            'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
            'database' => $path !== '' ? rawurldecode($path) : 'rotc_db',
        ];
    }
}

$databaseUrlConfig = rotc_database_url_config();

if (!defined('DB_TYPE')) {
    define('DB_TYPE', rotc_env('ROTC_DB_TYPE', $databaseUrlConfig['type'] ?? 'mysql'));
}

if (!defined('DB_SERVER')) {
    define('DB_SERVER', rotc_env('ROTC_DB_SERVER', $databaseUrlConfig['server'] ?? 'localhost:3306'));
}

if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', rotc_env('ROTC_DB_USER', $databaseUrlConfig['username'] ?? 'root'));
}

if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', rotc_env('ROTC_DB_PASS', $databaseUrlConfig['password'] ?? ''));
}

if (!defined('DB_NAME')) {
    define('DB_NAME', rotc_env('ROTC_DB_NAME', $databaseUrlConfig['database'] ?? 'rotc_db'));
}
