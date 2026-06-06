<?php
// Start session only if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
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
        $role = $_SESSION["role"] ?? 'cadet'; // Default to cadet if role is not set

        switch($role) {
            case 'admin':
                header('Location: ' . rotc_relative_url('admin_dashboard.php'));
                break;
            case 'instructor':
                header('Location: ' . rotc_relative_url('instructor_dashboard.php'));
                break;
            case '1cl':
            case '2cl':
            case 'commandant':
                header('Location: ' . rotc_relative_url('officer_dashboard.php'));
                break;
            case 'cadet':
            case 'basic_cadet':
            default:
                header('Location: ' . rotc_relative_url('cadet_dashboard.php'));
                break;
        }
        exit;
    }
}
?>
