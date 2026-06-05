<?php
/**
 * Vercel/PHP front controller.
 *
 * Routes friendly paths to the existing PHP app without making the landing
 * page depend on a database connection. Static files are handled by Vercel
 * routes before this file.
 */

$rootDir = dirname(__DIR__);
chdir($rootDir);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = rawurldecode($requestPath);
$requestPath = trim($requestPath, '/');

$blockedPatterns = [
    '/(^|\/)\.env$/',
    '/(^|\/)\.git(\/|$)/',
    '/\.(sql|sqlite|db|log|bak|ini)$/i',
    '/(^|\/)(composer\.(json|lock)|composer\.phar)$/i',
];

foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $requestPath)) {
        http_response_code(404);
        exit('Not found');
    }
}

$target = $requestPath === '' ? 'index.php' : $requestPath;

if (is_dir($rootDir . DIRECTORY_SEPARATOR . $target)) {
    $target = rtrim($target, '/') . '/index.php';
}

$absoluteTarget = realpath($rootDir . DIRECTORY_SEPARATOR . $target);
$allowedExtensions = ['php', 'html', 'htm'];
$extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));

if (
    $absoluteTarget !== false &&
    str_starts_with($absoluteTarget, $rootDir) &&
    is_file($absoluteTarget) &&
    in_array($extension, $allowedExtensions, true)
) {
    require $absoluteTarget;
    return;
}

http_response_code(404);
require $rootDir . DIRECTORY_SEPARATOR . 'index.php';
