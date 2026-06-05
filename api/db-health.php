<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../includes/db.php';

$isConnected = isset($pdo) && $pdo instanceof PDO;
$debugEnabled = getenv('ROTC_DEBUG') === 'true';

$response = [
    'ok' => $isConnected,
    'database' => DB_NAME,
    'driver' => DB_TYPE,
    'server_configured' => DB_SERVER !== '' && !str_starts_with(DB_SERVER, 'localhost'),
];

if ($isConnected) {
    try {
        $pdo->query('SELECT 1');
        $response['query'] = 'ok';
    } catch (Throwable $e) {
        $response['ok'] = false;
        $response['query'] = 'failed';
        if ($debugEnabled) {
            $response['error'] = $e->getMessage();
        }
    }
} elseif ($debugEnabled && isset($GLOBALS['DB_CONNECTION_ERROR'])) {
    $response['error'] = $GLOBALS['DB_CONNECTION_ERROR'];
}

http_response_code($response['ok'] ? 200 : 503);
echo json_encode($response);
