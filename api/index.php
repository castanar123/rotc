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

$friendlyRoutes = [
    'home' => 'index.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'logout' => 'logout.php',
    'forgot-password' => 'forgot-password.php',
    'verify-2fa' => 'verify_2fa.php',
    'verify-pin' => 'verify_pin.php',
    'dashboard' => 'dashboard.php',
    'admin' => 'admin_dashboard.php',
    'admin/dashboard' => 'admin_dashboard.php',
    'admin/registrations' => 'admin/registration_approvals.php',
    'cadet' => 'cadet_dashboard.php',
    'cadet/dashboard' => 'cadet_dashboard.php',
    'cadet/profile' => 'my_profile.php',
    'officer' => 'officer_dashboard.php',
    'officer/dashboard' => 'officer_dashboard.php',
    'officer/profile' => 'officer_profile.php',
    'qr' => 'QR/home.php',
    'qr/home' => 'QR/home.php',
    'qr/dashboard' => 'QR/dashboard.php',
    'qr/setup' => 'QR/setup.php',
    'qr/scan' => 'QR/index.php',
    'attendance' => 'attendance/dashboard.php',
    'attendance/dashboard' => 'attendance/dashboard.php',
    'attendance/logs' => 'attendance/logs.php',
    'attendance/manual' => 'attendance/manual_attendance.php',
    'attendance/scan' => 'attendance/scan.php',
    'users' => 'user_management.php',
    'users/add' => 'add_user.php',
    'users/edit' => 'edit_user.php',
    'profile' => 'profile.php',
    'rifles' => 'rifle_management.php',
    'rifles/borrow' => 'borrow_rifle.php',
    'rifles/scanner' => 'rifle_scanner.php',
    'announcements' => 'announcements.php',
    'grades' => 'grades/view_grades.php',
    'grades/manage' => 'grades/manage_grades.php',
    'reports' => 'reports/view_report.php',
    'reports/generate' => 'reports/generate_report.php',
    'settings' => 'user_settings.php',
];

$target = $requestPath === '' ? 'index.php' : ($friendlyRoutes[$requestPath] ?? $requestPath);

if ($target !== '' && pathinfo($target, PATHINFO_EXTENSION) === '') {
    $phpTarget = $target . '.php';
    if (is_file($rootDir . DIRECTORY_SEPARATOR . $phpTarget)) {
        $target = $phpTarget;
    }
}

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
    chdir(dirname($absoluteTarget));
    require $absoluteTarget;
    return;
}

http_response_code(404);
require $rootDir . DIRECTORY_SEPARATOR . 'index.php';
