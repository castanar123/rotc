<?php
// Start session only if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if the user is logged in, otherwise redirect to login page
function check_login(){
    if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
        header("location: ../login.php");
        exit;
    }
}

// Function to redirect logged-in users to their dashboard
function redirect_to_dashboard(){
    if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
        $role = $_SESSION["role"] ?? 'cadet'; // Default to cadet if role is not set

        switch($role) {
            case 'admin':
                header("location: admin_dashboard.php");
                break;
            case 'instructor':
                header("location: instructor_dashboard.php");
                break;
            case '1cl':
            case '2cl':
            case 'commandant':
                header("location: officer_dashboard.php");
                break;
            case 'cadet':
            case 'basic_cadet':
            default:
                header("location: cadet_dashboard.php");
                break;
        }
        exit;
    }
}
?>