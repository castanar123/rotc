<?php
// This is a discreet page for officer registration with secret access codes
require_once 'includes/session.php';
require_once 'includes/db.php';

// Secret access codes for officers
$access_codes = [
    'ALPHA1CL' => '1cl',     // Secret code for 1cl officers
    'BRAVO2CL' => '2cl'      // Secret code for 2cl officers
];

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Test if logging system is working properly
 * @return bool True if logging system is working, false otherwise
 */
function test_logging_system() {
    $test_result = write_log("Logging system test", "system_test.log");
    return $test_result;
}

// Test logging system on page load
$logging_system_working = test_logging_system();

// Function to display admin warning if logging system fails
function display_logging_warning() {
    global $logging_system_working;
    
    // Only show warning to admin users
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && !$logging_system_working) {
        echo '<div class="alert alert-warning" role="alert">';
        echo '<strong>Warning:</strong> The logging system is not working properly. Please check directory permissions for the logs folder.';
        echo '</div>';
    }
}

/**
 * Sanitize data for log entries to prevent log injection
 * @param string $data The data to sanitize
 * @return string Sanitized data
 */
function sanitize_log_data($data) {
    // Remove new lines and carriage returns to prevent log injection
    $data = str_replace(["\r", "\n"], ['[CR]', '[LF]'], $data);
    return $data;
}

/**
 * Get sanitized IP address for logging
 * @return string Sanitized IP address
 */
function get_client_ip() {
    $ip = '';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Get the first IP if multiple are set
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Validate IP format
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    return 'unknown';
}

/**
 * Helper function to write to log files
 * @param string $message The message to log
 * @param string $log_file The log file name
 * @return bool True if log was written successfully, false otherwise
 */
function write_log($message, $log_file) {
    try {
        // Ensure log directory exists
        $log_dir = "../logs";
        if (!is_dir($log_dir)) {
            if (!mkdir($log_dir, 0755, true)) {
                // Failed to create directory
                return false;
            }
        }
        
        // Check if directory is writable
        if (!is_writable($log_dir)) {
            // Directory exists but is not writable
            return false;
        }
        
        // Sanitize the message to prevent log injection
        $message = sanitize_log_data($message);
        
        // Format message with timestamp and write to log
        $log_message = date('Y-m-d H:i:s') . " - " . $message . "\n";
        $log_file_path = $log_dir . "/" . $log_file;
        
        // Write to log file
        if (error_log($log_message, 3, $log_file_path)) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        // Silently fail - we don't want logging errors to break the application
        return false;
    }
}

$page_title = 'System Access';
include 'includes/header.php';

echo "<div class='container-fluid'>";

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<div class='alert alert-danger'>";
        echo "<h4>Security Error</h4>";
        echo "<p>Invalid form submission. Please try again.</p>";
        echo "</div>";
        displayAccessCodeForm();
        echo "</div>";
        include 'includes/footer.php';
        exit;
    }
    
    // Implement basic rate limiting
    if (!isset($_SESSION['access_attempts'])) {
        $_SESSION['access_attempts'] = 0;
        $_SESSION['last_attempt_time'] = time();
    }
    
    // Ensure log directory exists
    $log_dir = "../logs";
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Check if too many attempts in a short time
    if ($_SESSION['access_attempts'] >= 5) {
        $time_passed = time() - $_SESSION['last_attempt_time'];
        if ($time_passed < 300) { // 5 minutes cooldown
            $wait_time = 300 - $time_passed;
            echo "<div class='alert alert-warning'>";
            echo "<h4>Too Many Attempts</h4>";
            echo "<p>Please wait " . ceil($wait_time / 60) . " minutes before trying again.</p>";
            echo "</div>";
            echo "</div>";
            
            // Log rate limit trigger
                    write_log("Rate limit triggered - IP: " . get_client_ip(), "access_attempts.log");
            
            include 'includes/footer.php';
            exit;
        } else {
            // Reset counter after cooldown period
            $_SESSION['access_attempts'] = 0;
        }
    }
    
    // Update attempt counter
    $_SESSION['access_attempts']++;
    $_SESSION['last_attempt_time'] = time();
    
    // Log access attempt
    write_log("Access code attempt - IP: " . get_client_ip(), "access_attempts.log");
    
    // Verify access code
    $submitted_code = isset($_POST['access_code']) ? trim($_POST['access_code']) : '';
    
    if (array_key_exists($submitted_code, $access_codes)) {
        // Reset attempt counter on successful code entry
        $_SESSION['access_attempts'] = 0;
        // Valid access code, proceed to registration form
        $officer_role = $access_codes[$submitted_code];
        
        // Process registration if form was submitted
        if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['confirm_password']) && isset($_POST['email'])) {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);
            $email = trim($_POST['email']);
            $role = $officer_role; // Set role based on access code
            
            // Validate inputs
            $errors = [];
            
            // Validate username (alphanumeric, 3-20 characters)
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                $errors[] = "Username must be 3-20 characters and can only contain letters, numbers, and underscores.";
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Please enter a valid email address.";
            }
            
            // Validate password strength (at least 8 characters with letters and numbers)
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $errors[] = "Password must include both letters and numbers.";
            }
            
            // Check if passwords match
            if ($password !== $confirm_password) {
                $errors[] = "Passwords do not match.";
            }
            
            // Check if username already exists
            $stmt = $link->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Username already exists. Please choose another one.";
            }
            $stmt->close();
            
            // Check if email already exists
            $stmt = $link->prepare("SELECT id FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "Email already exists. Please use another email address.";
                }
                $stmt->close();
            } else {
                // Log the error for debugging
                write_log("Database error: Unable to prepare statement for email check - " . $link->error, "officer_registrations.log");
            }
            
            // If no errors, proceed with registration
            if (empty($errors)) {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $link->prepare("INSERT INTO users (username, password, email, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                if (!$stmt) {
                    // Log the error for debugging
                    write_log("Database error: Unable to prepare statement for user insertion - " . $link->error, "officer_registrations.log");
                    $errors[] = "Database error occurred. Please try again later.";
                } else {
                    $stmt->bind_param("ssss", $username, $hashed_password, $email, $role);
                }
                
                // Only proceed with execution if statement was prepared successfully
                if (!empty($errors)) {
                    // Skip execution if there are errors
                } else {
                    try {
                        if ($stmt->execute()) {
                            // Close the statement
                            $stmt->close();
                            
                            // Log successful officer registration (for security audit)
                            write_log("Officer registration successful - Username: {$username}, Role: {$role}, IP: " . get_client_ip(), "officer_registrations.log");
                            
                            // Set session variables for automatic login
                            $_SESSION['loggedin'] = true;
                            $_SESSION['id'] = $link->insert_id;
                            $_SESSION['username'] = $username;
                            $_SESSION['role'] = $role;
                            
                            // Redirect to officer dashboard
                            header("Location: dashboard/officer.php");
                            exit;
                        } else {
                            $errors[] = "Registration failed. Please try again.";
                            
                            // Log failed registration attempt
                            write_log("Officer registration failed - Username: {$username}, Role: {$role}, IP: " . get_client_ip(), "officer_registrations.log");
                        }
                    } catch (mysqli_sql_exception $e) {
                        // Handle database errors
                        $errors[] = "Database error occurred. Please try again later.";
                        
                        // Log database error with sanitized data
                        $error_details = [
                            'error' => $e->getMessage(),
                            'username' => $username,
                            'email' => $email,
                            'role' => $role,
                            'ip' => get_client_ip(),
                            'code' => $e->getCode(),
                            'file' => basename($e->getFile()),
                            'line' => $e->getLine()
                        ];
                        
                        $error_message = "Database error during registration - " . 
                                        "Error: {$error_details['error']}, " . 
                                        "Code: {$error_details['code']}, " . 
                                        "Username: {$error_details['username']}, " . 
                                        "IP: {$error_details['ip']}, " . 
                                        "File: {$error_details['file']}, " . 
                                        "Line: {$error_details['line']}";
                        
                        write_log($error_message, "officer_registrations.log");
                    } finally {
                        // Make sure to close the statement if it's still open
                        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                            $stmt->close();
                        }
                    }
                }
            }
            
            // Display errors if any
            if (!empty($errors)) {
                echo "<div class='alert alert-danger'>";
                foreach ($errors as $error) {
                    echo "<p>{$error}</p>";
                }
                echo "</div>";
            }
        } else {
            // Display registration form
            echo "<h1 class='h3 mb-4 text-gray-800'>Officer Registration</h1>";
            echo "<div class='card shadow mb-4'>";
            echo "<div class='card-body'>";
            echo "<p class='lead'>Complete your officer registration for role: <strong>{$officer_role}</strong></p>";
            echo "<form method='post' action=''>";
            echo "<input type='hidden' name='csrf_token' value='{$_SESSION['csrf_token']}'>";
            echo "<input type='hidden' name='access_code' value='{$submitted_code}'>";
            
            echo "<div class='form-group'>";
            echo "<label for='username'>Username</label>";
            echo "<input type='text' class='form-control' id='username' name='username' required>";
            echo "</div>";
            
            echo "<div class='form-group'>";
            echo "<label for='email'>Email</label>";
            echo "<input type='email' class='form-control' id='email' name='email' required>";
            echo "</div>";
            
            echo "<div class='form-group'>";
            echo "<label for='password'>Password</label>";
            echo "<input type='password' class='form-control' id='password' name='password' required>";
            echo "<small class='form-text text-muted'>Password must be at least 8 characters and include both letters and numbers.</small>";
            echo "</div>";
            
            echo "<div class='form-group'>";
            echo "<label for='confirm_password'>Confirm Password</label>";
            echo "<input type='password' class='form-control' id='confirm_password' name='confirm_password' required>";
            echo "</div>";
            
            echo "<button type='submit' class='btn btn-primary'>Register</button>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
        }
    } else {
        // Invalid access code
        echo "<div class='alert alert-danger'>";
        echo "<h4>Invalid access code.</h4>";
        echo "<p>Please ensure you have the correct access code and try again.</p>";
        echo "</div>";
        
        // Show access code form again
        displayAccessCodeForm();
    }
} else {
    // Display access code form
    displayAccessCodeForm();
}

// Function to display the access code form
function displayAccessCodeForm() {
    global $page_title;
    
    // Display warning if logging system is not working
    display_logging_warning();
    
    echo "<h1 class='h3 mb-4 text-gray-800'>Restricted Access</h1>";
    echo "<div class='card shadow mb-4'>";
    echo "<div class='card-body'>";
    echo "<p class='lead'>Please enter your access code to continue.</p>";
    echo "<form method='post' action=''>";
    echo "<input type='hidden' name='csrf_token' value='{$_SESSION['csrf_token']}'>";
    echo "<div class='form-group'>";
    echo "<label for='access_code'>Access Code</label>";
    echo "<input type='password' class='form-control' id='access_code' name='access_code' required>";
    echo "</div>";
    echo "<button type='submit' class='btn btn-primary'>Submit</button>";
    echo "</form>";
    echo "</div>";
    echo "</div>";
}

echo "</div>";

include 'includes/footer.php';
?>
