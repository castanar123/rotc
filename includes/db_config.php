<?php
// Shared DB configuration for all scanner/attendance modules.
// Machine-specific credentials belong in includes/db_config.local.php or
// ROTC_DB_* environment variables, not in source control.

$localConfig = __DIR__ . '/db_config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

if (!function_exists('rotc_env')) {
    function rotc_env($key, $default = '')
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!defined('DB_TYPE')) {
    define('DB_TYPE', rotc_env('ROTC_DB_TYPE', 'mysql'));
}

if (!defined('DB_SERVER')) {
    define('DB_SERVER', rotc_env('ROTC_DB_SERVER', 'localhost:3306'));
}

if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', rotc_env('ROTC_DB_USER', 'root'));
}

if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', rotc_env('ROTC_DB_PASS', ''));
}

if (!defined('DB_NAME')) {
    define('DB_NAME', rotc_env('ROTC_DB_NAME', 'rotc_db'));
}
