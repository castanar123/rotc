<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/SecurityLogger.php';

// PROTOTYPE: Do not redirect logged-in users; keep them on this page for testing
$helpPrototypeLoggedIn = isset($_SESSION['user_id']);
// Intentionally no redirect here (original register.php still redirects)

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log registration attempt
    $securityLogger = new SecurityLogger();
    $securityLogger->logSecurityEvent(null, 'REGISTRATION_ATTEMPT', 'User attempted registration', [
        'email' => $_POST['email'] ?? 'not_provided',
        'username' => $_POST['username'] ?? 'not_provided',
        'student_number' => $_POST['student_number'] ?? 'not_provided'
    ], 'low');
    
    // Debug: Log POST data for troubleshooting
    error_log("Registration POST data received: " . json_encode(array_keys($_POST)));
    error_log("Files uploaded: " . json_encode(array_keys($_FILES)));
    
    // Validate and sanitize input
    $student_number = trim($_POST['student_number'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_initial = trim($_POST['middle_initial'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    // New location fields
    $region_field = trim($_POST['region'] ?? '');
    $province_field = trim($_POST['province'] ?? '');
    $city_field = trim($_POST['city_municipality'] ?? ($_POST['city'] ?? ''));
    $barangay_field = trim($_POST['barangay'] ?? '');
    $purok_field = trim($_POST['purok'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $facebook_profile = trim($_POST['facebook_profile'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $religion = trim($_POST['religion'] ?? '');
    $birthdate = $_POST['birth_date'] ?? '';
    $place_of_birth = trim($_POST['place_of_birth'] ?? '');
    $height = trim($_POST['height'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $skin_color = trim($_POST['skin_color'] ?? '');
    $blood_type = $_POST['blood_type'] ?? '';
    $father_name = trim($_POST['father_name'] ?? '');
    $father_occupation = trim($_POST['father_occupation'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $mother_occupation = trim($_POST['mother_occupation'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_contact = trim($_POST['guardian_contact'] ?? '');
    $guardian_relationship = trim($_POST['guardian_relationship'] ?? '');
    $guardian_address = trim($_POST['guardian_address'] ?? '');
    $platoon = $_POST['platoon'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $username = trim($_POST['username'] ?? '');
    
    // Debug: Check if critical fields are empty
    if (empty($username) || empty($email) || empty($password)) {
        error_log("Critical fields missing - Username: '$username', Email: '$email', Password: " . (empty($password) ? 'empty' : 'provided'));
    }
    
    // Validation
    if (empty($username)) $errors['username'] = 'Username is required';
    if (empty($student_number)) $errors['student_number'] = 'Student number is required';
    if (empty($first_name)) $errors['first_name'] = 'First name is required';
    if (empty($last_name)) $errors['last_name'] = 'Last name is required';
    if (empty($gender)) $errors['gender'] = 'Gender is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required';
    // Location validation (province optional for NCR)
    if (empty($region_field)) $errors['region'] = 'Region is required';
    if (empty($city_field)) $errors['city_municipality'] = 'City/Municipality is required';
    if (empty($barangay_field)) $errors['barangay'] = 'Barangay is required';
    
    // Validate Facebook profile URL if provided
    if (!empty($facebook_profile) && !filter_var($facebook_profile, FILTER_VALIDATE_URL)) {
        $errors['facebook_profile'] = 'Please enter a valid Facebook profile URL';
    } elseif (!empty($facebook_profile) && !preg_match('/facebook\.com/i', $facebook_profile)) {
        $errors['facebook_profile'] = 'Please enter a valid Facebook profile URL';
    }
    
    // Enhanced Password Validation
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } else {
        $password_errors = [];
        
        // Check minimum length
        if (strlen($password) < 8) {
            $password_errors[] = 'at least 8 characters';
        }
        
        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $password_errors[] = 'at least one uppercase letter';
        }
        
        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $password_errors[] = 'at least one lowercase letter';
        }
        
        // Check for number
        if (!preg_match('/[0-9]/', $password)) {
            $password_errors[] = 'at least one number';
        }
        
        // Check for special character
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};":.,<>?]/', $password)) {
            $password_errors[] = 'at least one special character (!@#$%^&*()_+-=[]{};";:.,<>?)';
        }
        
        if (!empty($password_errors)) {
            $errors['password'] = 'Password must contain: ' . implode(', ', $password_errors);
        }
    }
    
    if ($password !== $confirm_password) $errors['confirm_password'] = 'Passwords do not match';
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] != 0) { $errors['photo'] = 'Profile photo is required.'; }
    if (!isset($_FILES['signature']) || $_FILES['signature']['error'] != 0) { $errors['signature'] = 'Signature image is required.'; }
    
    // Check database connection and table existence
    if (empty($errors)) {
        try {
            // Test database connection
            if (!$pdo) {
                $errors['db'] = 'Database connection failed';
            } else {
                // Check if required tables exist
                $tables_check = $pdo->query("SHOW TABLES LIKE 'users'");
                if ($tables_check->rowCount() == 0) {
                    $errors['db'] = 'Database table "users" does not exist';
                }
                
                $tables_check = $pdo->query("SHOW TABLES LIKE 'cadet_profiles'");
                if ($tables_check->rowCount() == 0) {
                    $errors['db'] = 'Database table "cadet_profiles" does not exist';
                }
            }
        } catch (Exception $e) {
            $errors['db'] = 'Database check failed: ' . $e->getMessage();
        }
    }
    
    // Ensure cadet_profiles.student_id column is wide enough for full IDs (avoid truncation to first 4 chars)
    if (empty($errors)) {
        try {
            $colInfo = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE 'student_id'")->fetch(PDO::FETCH_ASSOC);
            if ($colInfo) {
                $colType = strtolower((string)($colInfo['Type'] ?? ''));
                $curLen = 0; $isChar = false;
                if (preg_match('/^(var)?char\\((\\d+)\\)/', $colType, $m)) {
                    $isChar = true; $curLen = (int)$m[2];
                }
                $neededLen = max(20, (int)strlen($student_number));
                if ($isChar && $curLen > 0 && $curLen < $neededLen) {
                    $newLen = min(max($neededLen, $curLen), 64); // cap at 64 chars
                    $pdo->exec("ALTER TABLE cadet_profiles MODIFY `student_id` VARCHAR(" . $newLen . ") NOT NULL");
                }
            }
        } catch (Exception $e) {
            // Ignore if insufficient privileges; fallback to index fix may still help
        }
    }

    // Ensure student_id uniqueness is enforced on the FULL column (not a 4-char prefix)
    if (empty($errors)) {
        try {
            $colInfo = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE 'student_id'")->fetch(PDO::FETCH_ASSOC);
            if ($colInfo) {
                $colType = strtolower((string)($colInfo['Type'] ?? ''));
                $colLen = 0;
                if (preg_match('/varchar\((\d+)\)/', $colType, $m)) { $colLen = (int)$m[1]; }
                $idxRows = $pdo->query("SHOW INDEX FROM cadet_profiles");
                $fixNeeded = false; $dropIdx = null; $subPart = null;
                foreach ($idxRows as $r) {
                    $isUnique = isset($r['Non_unique']) ? (int)$r['Non_unique'] === 0 : false;
                    $colName = $r['Column_name'] ?? '';
                    if ($isUnique && strtolower($colName) === 'student_id') {
                        $dropIdx = $r['Key_name'] ?? $dropIdx;
                        $subPart = isset($r['Sub_part']) ? (int)$r['Sub_part'] : null;
                        if ($subPart !== null && ($colLen === 0 || $subPart < $colLen)) { $fixNeeded = true; break; }
                    }
                }
                if ($fixNeeded && $dropIdx) {
                    $pdo->exec("ALTER TABLE cadet_profiles DROP INDEX `{$dropIdx}`");
                    $pdo->exec("ALTER TABLE cadet_profiles ADD UNIQUE KEY `uq_cadet_profiles_student_id` (`student_id`)");
                }
            }
        } catch (Exception $e) {
            // Ignore if lacking privilege or using a different schema
        }
    }

    // Check if email or student number already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $errors['db'] = 'Email or username already exists';
            }
        } catch (Exception $e) {
            $errors['db'] = 'User validation failed: ' . $e->getMessage();
        }
    }
    // Also prevent duplicate student_id in cadet_profiles (exact match)
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE student_id = ? LIMIT 1");
            $stmt->execute([$student_number]);
            if ($stmt->fetch()) {
                $errors['student_number'] = 'Student number already exists';
            }
        } catch (Exception $e) {
            // If schema differs, allow insert to surface precise error
        }
    }
    
    // If no errors, create the user
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // Create user as pending approval; inactive until admin approves
            $stmt = $pdo->prepare("\n                INSERT INTO users (username, email, password, role, approval_status, status) \n                VALUES (?, ?, ?, 'basic-cadet', 'pending', 'inactive')\n            ");
            $stmt->execute([$username, $email, $hashed_password]);
            $user_id = $pdo->lastInsertId();

            // Attempt to store name components in users table for consistency
            try {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ? WHERE id = ?");
                $stmt->execute([$first_name, $middle_initial, $last_name, $user_id]);
            } catch (Exception $e) {
                // Column(s) may not exist; proceed without failing registration
                error_log('[REGISTRATION] Skipped setting name parts on users table: ' . $e->getMessage());
            }
            
            $photo_path = 'uploads/photos/' . $user_id . '_' . basename($_FILES['photo']['name']);
            $signature_path = 'uploads/signatures/' . $user_id . '_' . basename($_FILES['signature']['name']);

            // Ensure upload directories exist and are writable
            $photo_dir = dirname($photo_path);
            $signature_dir = dirname($signature_path);
            
            if (!is_dir($photo_dir)) {
                if (!mkdir($photo_dir, 0755, true)) {
                    throw new Exception("Failed to create photo upload directory: $photo_dir");
                }
            }
            if (!is_writable($photo_dir)) {
                throw new Exception("Photo upload directory is not writable: $photo_dir");
            }
            
            if (!is_dir($signature_dir)) {
                if (!mkdir($signature_dir, 0755, true)) {
                    throw new Exception("Failed to create signature upload directory: $signature_dir");
                }
            }
            if (!is_writable($signature_dir)) {
                throw new Exception("Signature upload directory is not writable: $signature_dir");
            }

            // Attempt file uploads with detailed error reporting
            $photo_upload = move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
            $signature_upload = move_uploaded_file($_FILES['signature']['tmp_name'], $signature_path);
            
            if (!$photo_upload) {
                throw new Exception("Failed to upload photo file. Check file permissions and disk space.");
            }
            if (!$signature_upload) {
                throw new Exception("Failed to upload signature file. Check file permissions and disk space.");
            }
            
            if ($photo_upload && $signature_upload) {
                // Use the separate name fields directly
                $middle_name = $middle_initial; // Use middle_initial as middle_name for database
                
                // Build Province/City as "City, Province" for consistent document formatting
                if ($city_field !== '' && $province_field !== '') {
                    $province_city = trim($city_field . ', ' . $province_field);
                } elseif ($city_field !== '') {
                    $province_city = trim($city_field);
                } elseif ($province_field !== '') {
                    $province_city = trim($province_field);
                } else {
                    $province_city = '';
                }
                // If address not provided, compose from location parts
                if ($address === '') {
                    $parts = [];
                    if ($purok_field !== '') $parts[] = 'Purok ' . $purok_field;
                    if ($barangay_field !== '') $parts[] = 'Brgy. ' . $barangay_field;
                    if ($province_city !== '') $parts[] = $province_city;
                    if ($region_field !== '') $parts[] = $region_field;
                    $address = implode(', ', $parts);
                }

                // Create cadet profile - added province_city and region columns
                $stmt = $pdo->prepare("
                    INSERT INTO cadet_profiles (
                        user_id, student_id, first_name, last_name, middle_name, 
                        gender, email, address, province_city, region, contact_number, facebook_profile, course, 
                        section, religion, birthdate, place_of_birth, height, 
                        weight, skin_color, blood_type, father_name, father_occupation,
                        mother_name, mother_occupation, guardian_name, guardian_contact,
                        guardian_relationship, guardian_address, platoon, photo_path
                    ) VALUES (
                         ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     )
                ");
                $paramsCadet = [
                    $user_id, $student_number, $first_name, $last_name, $middle_name, 
                    $gender, $email, $address, $province_city, $region_field, $contact_number, $facebook_profile, $course, 
                    $section, $religion, $birthdate, $place_of_birth, $height, 
                    $weight, $skin_color, $blood_type, $father_name, $father_occupation,
                    $mother_name, $mother_occupation, $guardian_name, $guardian_contact,
                    $guardian_relationship, $guardian_address, $platoon, $photo_path
                ];
                try {
                    $stmt->execute($paramsCadet);
                } catch (PDOException $pe) {
                    $msg = $pe->getMessage();
                    $code = (int)$pe->getCode();
                    $isDup = (stripos($msg, 'Duplicate entry') !== false) || $code === 1062;
                    if ($isDup) {
                        // Attempt automated schema fix then retry once
                        try {
                            // Ensure student_id is VARCHAR and wide enough
                            $colInfo = $pdo->query("SHOW COLUMNS FROM cadet_profiles LIKE 'student_id'")->fetch(PDO::FETCH_ASSOC);
                            if ($colInfo) {
                                $type = strtolower((string)($colInfo['Type'] ?? ''));
                                if (!preg_match('/char|text/i', $type)) {
                                    $pdo->exec("ALTER TABLE cadet_profiles MODIFY `student_id` VARCHAR(64) NOT NULL");
                                } else {
                                    $len = 0;
                                    if (preg_match('/\((\d+)\)/', $type, $mm)) { $len = (int)$mm[1]; }
                                    if ($len > 0 && $len < max(20, strlen($student_number))) {
                                        $pdo->exec("ALTER TABLE cadet_profiles MODIFY `student_id` VARCHAR(64) NOT NULL");
                                    }
                                }
                            }
                            // Fix prefix unique index if present
                            try {
                                $idxRows = $pdo->query("SHOW INDEX FROM cadet_profiles");
                                $dropIdx = null; $subPart = null;
                                foreach ($idxRows as $r) {
                                    $isUnique = isset($r['Non_unique']) ? (int)$r['Non_unique'] === 0 : false;
                                    $colName = $r['Column_name'] ?? '';
                                    if ($isUnique && strtolower($colName) === 'student_id') {
                                        $dropIdx = $r['Key_name'] ?? $dropIdx;
                                        if (isset($r['Sub_part'])) $subPart = (int)$r['Sub_part'];
                                    }
                                }
                                if ($dropIdx && $subPart !== null) {
                                    $pdo->exec("ALTER TABLE cadet_profiles DROP INDEX `{$dropIdx}`");
                                    $pdo->exec("ALTER TABLE cadet_profiles ADD UNIQUE KEY `uq_cadet_profiles_student_id` (`student_id`)");
                                }
                            } catch (Exception $ie) { /* ignore */ }

                            // Re-prepare and retry
                            $stmt = $pdo->prepare("
                                INSERT INTO cadet_profiles (
                                    user_id, student_id, first_name, last_name, middle_name, 
                                    gender, email, address, province_city, region, contact_number, facebook_profile, course, 
                                    section, religion, birthdate, place_of_birth, height, 
                                    weight, skin_color, blood_type, father_name, father_occupation,
                                    mother_name, mother_occupation, guardian_name, guardian_contact,
                                    guardian_relationship, guardian_address, platoon, photo_path
                                ) VALUES (
                                     ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                                )
                            ");
                            $stmt->execute($paramsCadet);
                        } catch (Exception $fixe) {
                            throw $pe; // propagate original duplicate if fix fails
                        }
                    } else {
                        throw $pe;
                    }
                }
                
                $pdo->commit();
                
                // Log successful registration
                $securityLogger->logSecurityEvent($user_id, 'REGISTRATION_SUCCESS', 'User registration submitted and pending admin approval', [
                    'username' => $username,
                    'email' => $email,
                    'student_number' => $student_number,
                    'role' => 'basic-cadet'
                ], 'medium');
                
                // Do not auto-login. Require admin approval before access.
                $success_message = 'Registration submitted successfully. Your account is pending admin approval. You will be able to log in once approved.';
            } else {
                $pdo->rollBack();
                $errors['db'] = 'File upload failed.';
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            
            // Log failed registration
            $securityLogger->logSecurityEvent(null, 'REGISTRATION_FAILED', 'User registration failed: ' . $e->getMessage(), [
                'username' => $username ?? 'unknown',
                'email' => $email ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ], 'medium');
            
            // Enhanced error handling for debugging
            $error_details = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            
            // Log the detailed error for debugging
            error_log("Registration Error: " . json_encode($error_details));
            
            // Show user-friendly error with some debug info
            $errors['db'] = 'Registration failed: ' . $e->getMessage() . ' (Error Code: ' . $e->getCode() . ')';
        }
    }
}

$page_title = 'Cadet Registration';

// Initialize input array for form persistence
$input = $_POST;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tactical Theme CSS -->
    <link rel="stylesheet" href="css/tactical-theme.css?v=<?php echo time(); ?>">
    
    <!-- Registration Form CSS -->
    <link rel="stylesheet" href="css/registration-form.css?v=<?php echo time(); ?>">
    
    <!-- Password Requirements Styling -->
    <style>
        .password-requirements {
            margin-top: 10px;
            padding: 12px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border: 1px solid #00ff88;
            border-radius: 8px;
            font-size: 0.9em;
            box-shadow: 0 2px 10px rgba(0, 255, 136, 0.1);
        }
        
        .password-requirements strong {
            color: #00ff88;
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .password-requirements ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .password-requirements li {
            padding: 4px 0;
            font-size: 0.85em;
            transition: all 0.3s ease;
        }
        
        .requirement-met {
            color: #00ff88;
            font-weight: 500;
        }
        
        .requirement-unmet {
            color: #ff6b6b;
            font-weight: 400;
        }
        
        .password-requirements li:before {
            margin-right: 8px;
            font-weight: bold;
        }
        
        /* Animation for requirement changes */
        .password-requirements li {
            transform: translateX(0);
        }
        
        .requirement-met {
            animation: checkmark 0.3s ease-in-out;
        }
        
        @keyframes checkmark {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Hide password feedback initially */
        #password-feedback {
            display: none;
        }
        
        /* Show when JS toggles .show */
        #password-feedback.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Password input visibility toggle */
        .password-input {
            position: relative;
            padding-bottom: 2rem;  /* reserve enough space for one-line error */
            margin-bottom: 0.25rem; /* small gap to next element */
        }
        .form-control.has-toggle {
            padding-right: 3.5rem; /* ensure text doesn't overlap the (wider) icon area */
        }
        .password-input .password-toggle {
            position: absolute;
            right: 10px;
            top: 0;               /* anchor to input top */
            bottom: auto;
            height: 48px;         /* match input height */
            display: inline-flex; /* center icon vertically inside fixed height */
            align-items: center;
            justify-content: center;
            width: 56px;          /* larger hitbox; also covers native eye */
            background: transparent;
            border: 0;
            color: #8a8a8a;
            cursor: pointer;
            padding: 0;
            z-index: 3;           /* ensure above input/native controls */
            transition: none;     /* prevent any movement when typing/focus */
        }
        .password-input .password-toggle i {
            width: 1.25em;        /* fix glyph box width */
            text-align: center;
            font-size: 18px;      /* consistent size */
            line-height: 1;       /* remove vertical jitter */
        }
        .password-input .password-toggle:focus {
            outline: none;
        }

        /* Hide native password reveal/clear icons (Edge/IE/WebKit variants) */
        .password-input input::-ms-reveal,
        .password-input input::-ms-clear {
            display: none;
        }
        .password-input input::-webkit-credentials-auto-fill-button,
        .password-input input::-webkit-textfield-decoration-container,
        .password-input input::-webkit-clear-button {
            display: none !important;
            pointer-events: none;
            visibility: hidden;
        }
        /* Stabilize control appearance to avoid jitter */
        .password-input .form-control.has-toggle {
            appearance: none;
            -webkit-appearance: none;
            height: 48px;      /* fixed height */
            line-height: 48px; /* center text vertically */
            box-sizing: border-box;
        }

        /* Limit transitions on inputs to non-geometry properties */
        .registration-form .form-control {
            transition: border-color .2s ease, box-shadow .2s ease, background .2s ease !important;
        }

        /* Make navigation buttons visible against dark background */
        .form-navigation { gap: 0.75rem; }
        .form-navigation .btn { 
            border: 1px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
        }
        .form-navigation .btn-primary {
            background: linear-gradient(135deg, #2ecc71, #28a745) !important;
            color: #ffffff !important;
        }
        .form-navigation .btn-primary i { color: inherit; }
        .form-navigation .btn-secondary {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
        }
        .form-navigation .btn-success {
            background: linear-gradient(135deg, #00e673, #00c765) !important;
            color: #0f1a12 !important;
        }

        /* Password requirements panel: responsive, non-overlapping */
        .password-group { position: relative; padding-bottom: 0; }
        #password-feedback { position: static; display: none; margin-top: 0.75rem; pointer-events: auto; width: 100%; max-width: 100%; grid-column: 1 / -1; box-sizing: border-box; }
        #password-feedback.show { display: block; }
        #password-feedback .password-requirements { margin: 0; padding-left: 1.25rem; list-style: disc; }
        #password-feedback .password-requirements li { margin: 0.25rem 0; }
        .requirement-met { color: #28a745; }
        .requirement-unmet { color: #ff4757; }
        .password-input .field-error,
        .password-input .error-message {
            position: absolute;
            left: 0;
            top: 100%;
            transform: translateY(2px);
            margin: 0;
            font-size: 0.85rem;
            color: #ff6b6b;
            pointer-events: none;
            white-space: nowrap;           /* single line */
            overflow: hidden;              /* prevent wrapping */
            text-overflow: ellipsis;       /* graceful truncation */
            max-width: calc(100% - 60px);  /* avoid clashing with the eye button */
        }

        /* Mask native reveal area behind our button */
        .password-input::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 56px;
            background: transparent; /* avoid visual shift on focus */
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
            pointer-events: none;
            z-index: 1; /* below the button, above native controls */
        }

        /* Emulate password bullets on text inputs to avoid native reveal button */
        .form-control.masked {
            -webkit-text-security: disc;
            text-security: disc;
        }
        /* Help FAB and modal */
        .help-fab {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1050;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: linear-gradient(135deg, #ffc107, #ffb300);
            color: #0f1a12;
            border: 1px solid rgba(0,0,0,.2);
            border-radius: 999px;
            padding: .6rem 1rem;
            box-shadow: 0 8px 24px rgba(0,0,0,.35);
            cursor: pointer;
        }
        .help-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 2000; display: none; }
        .help-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 2001; }
        .help-modal .panel { width: 96%; max-width: 560px; background: #111827; color: #e5e7eb; border: 1px solid rgba(255,255,255,.08); border-radius: 12px; box-shadow: 0 12px 36px rgba(0,0,0,.5); }
        .help-modal .panel-header { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,.08); display: flex; align-items: center; justify-content: space-between; }
        .help-modal .panel-body { padding: 16px; }
        .help-modal .panel-footer { padding: 12px 16px; border-top: 1px solid rgba(255,255,255,.08); display: flex; gap: .5rem; justify-content: flex-end; }
        .badge-code { display:inline-block; background:#1f2937; border:1px solid rgba(255,255,255,.08); padding:.35rem .6rem; border-radius:8px; font-family: monospace; }
        .visually-hidden { position:absolute !important; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    </style>
</head>
<body>
    <div class="registration-container">
        <!-- Header -->
        <div class="registration-header">
            <div class="header-content">
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
                <div class="logo-section">
                    <div class="logo-group">
                        <img src="IMG/GIDEON.png" alt="GIDEON Logo" class="header-logo">
                        <img src="IMG/MANRILAG.png" alt="MANRILAG Logo" class="header-logo">
                        <img src="IMG/MAKALAYAN.png" alt="MAKALAYAN Logo" class="header-logo">
                    </div>
                    <div class="title-section">
                        <h1><i class="fas fa-shield-alt"></i> ROTC Registration</h1>
                        <p class="subtitle">Reserve Officers' Training Corps</p>
                    </div>
                </div>
                <div></div>
            </div>
        </div>

        <!-- Registration Form Container -->
        <div class="registration-form-container">
            <div class="registration-form">
                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="progress-step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label">Account</div>
                    </div>
                    <div class="progress-step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label">Personal</div>
                    </div>
                    <div class="progress-step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label">Physical</div>
                    </div>
                    <div class="progress-step" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-label">Files</div>
                    </div>
                </div>

                <!-- Display All Errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <h4>Registration Failed</h4>
                            <?php if (isset($errors['db'])): ?>
                                <p><strong>Database Error:</strong> <?php echo htmlspecialchars($errors['db']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (count($errors) > 1 || !isset($errors['db'])): ?>
                                <p><strong>Validation Errors:</strong></p>
                                <ul style="margin: 10px 0; padding-left: 20px;">
                                    <?php foreach ($errors as $field => $error): ?>
                                        <?php if ($field !== 'db'): ?>
                                            <li><strong><?php echo ucfirst(str_replace('_', ' ', $field)); ?>:</strong> <?php echo htmlspecialchars($error); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <!-- Debug Information Section (only for database errors) -->
                            <?php if (isset($errors['db'])): ?>
                                <details style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.1); border-radius: 5px;">
                                    <summary style="cursor: pointer; font-weight: bold;">🔍 Debug Information (Click to expand)</summary>
                                    <div style="margin-top: 10px; font-family: monospace; font-size: 12px;">
                                        <p><strong>Database Connection Status:</strong> <?php echo $pdo ? 'Connected' : 'Failed'; ?></p>
                                        <?php if ($pdo): ?>
                                            <p><strong>Database Name:</strong> <?php echo DB_NAME; ?></p>
                                            <p><strong>Database Host:</strong> <?php echo DB_SERVER; ?></p>
                                            <?php
                                            try {
                                                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                                                echo '<p><strong>Available Tables:</strong> ' . implode(', ', $tables) . '</p>';
                                            } catch (Exception $e) {
                                                echo '<p><strong>Table Check Error:</strong> ' . $e->getMessage() . '</p>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                        <p><strong>Upload Directory Status:</strong></p>
                                        <ul style="margin: 5px 0; padding-left: 20px;">
                                            <li>Photos dir exists: <?php echo is_dir('uploads/photos') ? 'Yes' : 'No'; ?></li>
                                            <li>Photos dir writable: <?php echo is_writable('uploads/photos') ? 'Yes' : 'No'; ?></li>
                                            <li>Signatures dir exists: <?php echo is_dir('uploads/signatures') ? 'Yes' : 'No'; ?></li>
                                            <li>Signatures dir writable: <?php echo is_writable('signatures') ? 'Yes' : 'No'; ?></li>
                                        </ul>
                                        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                                        <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                        <p><strong>Form Submitted:</strong> <?php echo $_SERVER['REQUEST_METHOD'] === 'POST' ? 'Yes' : 'No'; ?></p>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <h4>Registration Successful</h4>
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Debug: Show form submission status -->
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && empty($success_message)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <h4>Form Submitted</h4>
                            <p>Form was submitted but no success or error message was generated. This indicates a potential issue with the registration process.</p>
                            <details style="margin-top: 10px; padding: 10px; background: rgba(0,0,0,0.1); border-radius: 5px;">
                                <summary style="cursor: pointer; font-weight: bold;">📋 Submission Details</summary>
                                <div style="margin-top: 10px; font-family: monospace; font-size: 12px;">
                                    <p><strong>POST Data Received:</strong> <?php echo !empty($_POST) ? 'Yes (' . count($_POST) . ' fields)' : 'No'; ?></p>
                                    <p><strong>Files Uploaded:</strong> <?php echo !empty($_FILES) ? 'Yes (' . count($_FILES) . ' files)' : 'No'; ?></p>
                                    <p><strong>Username:</strong> <?php echo !empty($username) ? 'Provided' : 'Empty'; ?></p>
                                    <p><strong>Email:</strong> <?php echo !empty($email) ? 'Provided' : 'Empty'; ?></p>
                                    <p><strong>Password:</strong> <?php echo !empty($password) ? 'Provided' : 'Empty'; ?></p>
                                    <p><strong>Database Connection:</strong> <?php echo $pdo ? 'Connected' : 'Failed'; ?></p>
                                </div>
                            </details>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="registrationForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" novalidate>

                    <!-- Step 1: Account Credentials -->
                    <div class="form-step active" data-step="1" style="display: block !important; visibility: visible !important; opacity: 1 !important;">
                        <div class="step-header">
                            <h2><i class="fas fa-user-shield"></i> Account Credentials</h2>
                            <p>Create your secure login credentials</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user"></i> Username
                                </label>
                                <input type="text" class="form-control <?php echo !empty($errors['username']) ? 'error' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                                <?php if (!empty($errors['username'])): ?>
                                    <div class="error-message"><?php echo $errors['username']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" class="form-control <?php echo !empty($errors['email']) ? 'error' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                <?php if (!empty($errors['email'])): ?>
                                    <div class="error-message"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group password-group">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <div class="password-input">
                                    <input type="text" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" class="form-control has-toggle masked <?php echo !empty($errors['password']) ? 'error' : ''; ?>" id="password" name="password" required>
                                    <button type="button" class="password-toggle" aria-label="Toggle password visibility" data-target="#password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                    <?php if (!empty($errors['password'])): ?>
                                        <div class="error-message"><?php echo $errors['password']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-group password-group">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock"></i> Confirm Password
                                </label>
                                <div class="password-input">
                                    <input type="text" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" class="form-control has-toggle masked <?php echo !empty($errors['confirm_password']) ? 'error' : ''; ?>" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="password-toggle" aria-label="Toggle password visibility" data-target="#confirm_password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                    <?php if (!empty($errors['confirm_password'])): ?>
                                        <div class="error-message"><?php echo $errors['confirm_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Full-width password requirements below both fields -->
                            <div id="password-feedback" class="password-feedback form-group full-width">
                                <ul class="password-requirements">
                                    <li id="req-length" class="requirement-unmet">At least 8 characters</li>
                                    <li id="req-uppercase" class="requirement-unmet">One uppercase letter</li>
                                    <li id="req-lowercase" class="requirement-unmet">One lowercase letter</li>
                                    <li id="req-number" class="requirement-unmet">One number</li>
                                    <li id="req-special" class="requirement-unmet">One special character</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Personal & Academic -->
                    <div class="form-step" data-step="2">
                        <div class="step-header">
                            <h2><i class="fas fa-id-card"></i> Personal & Academic Information</h2>
                            <p>Provide your personal and academic details</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name" class="form-label">
                                    <i class="fas fa-user"></i> First Name
                                </label>
                                <input type="text" class="form-control <?php echo !empty($errors['first_name']) ? 'error' : ''; ?>" id="first_name" name="first_name" value="<?php echo htmlspecialchars($input['first_name'] ?? ''); ?>" required>
                                <?php if (!empty($errors['first_name'])): ?>
                                    <div class="error-message"><?php echo $errors['first_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="middle_initial" class="form-label">
                                    <i class="fas fa-user"></i> Middle Initial
                                </label>
                                <input type="text" class="form-control <?php echo !empty($errors['middle_initial']) ? 'error' : ''; ?>" id="middle_initial" name="middle_initial" value="<?php echo htmlspecialchars($input['middle_initial'] ?? ''); ?>" maxlength="2" placeholder="M.">
                                <?php if (!empty($errors['middle_initial'])): ?>
                                    <div class="error-message"><?php echo $errors['middle_initial']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="last_name" class="form-label">
                                    <i class="fas fa-user"></i> Last Name
                                </label>
                                <input type="text" class="form-control <?php echo !empty($errors['last_name']) ? 'error' : ''; ?>" id="last_name" name="last_name" value="<?php echo htmlspecialchars($input['last_name'] ?? ''); ?>" required>
                                <?php if (!empty($errors['last_name'])): ?>
                                    <div class="error-message"><?php echo $errors['last_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="student_number" class="form-label">
                                    <i class="fas fa-id-badge"></i> Student Number
                                </label>
                                <input type="text" class="form-control <?php echo !empty($errors['student_number']) ? 'error' : ''; ?>" id="student_number" name="student_number" value="<?php echo htmlspecialchars($input['student_number'] ?? ''); ?>" required>
                                <?php if (!empty($errors['student_number'])): ?>
                                    <div class="error-message"><?php echo $errors['student_number']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="contact_number" class="form-label">
                                    <i class="fas fa-phone"></i> Contact Number
                                </label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($input['contact_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="facebook_profile" class="form-label">
                                    <i class="fab fa-facebook"></i> Facebook Profile Link
                                </label>
                                <input type="url" class="form-control <?php echo !empty($errors['facebook_profile']) ? 'error' : ''; ?>" id="facebook_profile" name="facebook_profile" value="<?php echo htmlspecialchars($input['facebook_profile'] ?? ''); ?>" placeholder="https://facebook.com/yourprofile">
                                <small class="form-text">Optional: Your Facebook profile URL for easier communication</small>
                                <?php if (!empty($errors['facebook_profile'])): ?>
                                    <div class="error-message"><?php echo $errors['facebook_profile']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="region" class="form-label">
                                    <i class="fas fa-globe-asia"></i> Region
                                </label>
                                <select class="form-control" id="region" name="region" required data-default="<?php echo htmlspecialchars($region_field ?? ''); ?>">
                                    <option value="">Select Region</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="province" class="form-label">
                                    <i class="fas fa-map"></i> Province
                                </label>
                                <select class="form-control" id="province" name="province" required disabled data-default="<?php echo htmlspecialchars($province_field ?? ''); ?>">
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="city_municipality" class="form-label">
                                    <i class="fas fa-city"></i> City/Municipality
                                </label>
                                <select class="form-control" id="city_municipality" name="city_municipality" required disabled data-default="<?php echo htmlspecialchars($city_field ?? ''); ?>">
                                    <option value="">Select City/Municipality</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="barangay" class="form-label">
                                    <i class="fas fa-location-arrow"></i> Barangay
                                </label>
                                <select class="form-control" id="barangay" name="barangay" required disabled data-default="<?php echo htmlspecialchars($barangay_field ?? ''); ?>">
                                    <option value="">Select Barangay</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="purok" class="form-label">
                                    <i class="fas fa-map-pin"></i> Purok (optional)
                                </label>
                                <input type="text" class="form-control" id="purok" name="purok" value="<?php echo htmlspecialchars($input['purok'] ?? ''); ?>" placeholder="e.g., 5">
                            </div>
                            <div class="form-group full-width">
                                <label for="address" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Address (optional)
                                </label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($input['address'] ?? ''); ?>" placeholder="Street/House no., Subdivision, etc. (auto-composed if left blank)">
                            </div>
                            <div class="form-group">
                                <label for="course" class="form-label">
                                    <i class="fas fa-graduation-cap"></i> Course
                                </label>
                                    <input type="text" class="form-control" id="course" name="course" value="<?php echo htmlspecialchars($input['course'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="section" class="form-label">
                                    <i class="fas fa-users"></i> Section
                                </label>
                                <input type="text" class="form-control" id="section" name="section" value="<?php echo htmlspecialchars($input['section'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender" class="form-label">
                                    <i class="fas fa-venus-mars"></i> Gender
                                </label>
                                <select class="form-control" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo (($input['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (($input['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="birth_date" class="form-label">
                                    <i class="fas fa-calendar"></i> Date of Birth
                                </label>
                                <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($input['birth_date'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="religion" class="form-label">
                                    <i class="fas fa-pray"></i> Religion
                                </label>
                                <input type="text" class="form-control" id="religion" name="religion" value="<?php echo htmlspecialchars($input['religion'] ?? ''); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label for="place_of_birth" class="form-label">
                                    <i class="fas fa-map-pin"></i> Place of Birth
                                </label>
                                <input type="text" class="form-control" id="place_of_birth" name="place_of_birth" value="<?php echo htmlspecialchars($input['place_of_birth'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Physical & Family -->
                    <div class="form-step" data-step="3">
                        <div class="step-header">
                            <h2><i class="fas fa-heartbeat"></i> Physical & Family Information</h2>
                            <p>Provide your physical characteristics and family details</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="height" class="form-label">
                                    <i class="fas fa-ruler-vertical"></i> Height (cm)
                                </label>
                                <input type="text" class="form-control" id="height" name="height" value="<?php echo htmlspecialchars($input['height'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="weight" class="form-label">
                                    <i class="fas fa-weight"></i> Weight (kg)
                                </label>
                                <input type="text" class="form-control" id="weight" name="weight" value="<?php echo htmlspecialchars($input['weight'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="skin_color" class="form-label">
                                    <i class="fas fa-palette"></i> Skin Color
                                </label>
                                <input type="text" class="form-control" id="skin_color" name="skin_color" value="<?php echo htmlspecialchars($input['skin_color'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="blood_type" class="form-label">
                                    <i class="fas fa-tint"></i> Blood Type
                                </label>
                                <input type="text" class="form-control" id="blood_type" name="blood_type" value="<?php echo htmlspecialchars($input['blood_type'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="father_name" class="form-label">
                                    <i class="fas fa-male"></i> Father's Name
                                </label>
                                <input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo htmlspecialchars($input['father_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="father_occupation" class="form-label">
                                    <i class="fas fa-briefcase"></i> Father's Occupation
                                </label>
                                <input type="text" class="form-control" id="father_occupation" name="father_occupation" value="<?php echo htmlspecialchars($input['father_occupation'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="mother_name" class="form-label">
                                    <i class="fas fa-female"></i> Mother's Name
                                </label>
                                <input type="text" class="form-control" id="mother_name" name="mother_name" value="<?php echo htmlspecialchars($input['mother_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="mother_occupation" class="form-label">
                                    <i class="fas fa-briefcase"></i> Mother's Occupation
                                </label>
                                <input type="text" class="form-control" id="mother_occupation" name="mother_occupation" value="<?php echo htmlspecialchars($input['mother_occupation'] ?? ''); ?>">
                            </div>
                            <div class="form-section-divider">
                                <h4><i class="fas fa-users"></i> Guardian Information</h4>
                            </div>
                            <div class="form-group">
                                <label for="guardian_name" class="form-label">
                                    <i class="fas fa-user-shield"></i> Guardian's Name
                                </label>
                                <input type="text" class="form-control" id="guardian_name" name="guardian_name" value="<?php echo htmlspecialchars($input['guardian_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="guardian_contact" class="form-label">
                                    <i class="fas fa-phone"></i> Guardian's Contact
                                </label>
                                <input type="text" class="form-control" id="guardian_contact" name="guardian_contact" value="<?php echo htmlspecialchars($input['guardian_contact'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="guardian_relationship" class="form-label">
                                    <i class="fas fa-heart"></i> Relationship to Guardian
                                </label>
                                <input type="text" class="form-control" id="guardian_relationship" name="guardian_relationship" value="<?php echo htmlspecialchars($input['guardian_relationship'] ?? ''); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label for="guardian_address" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Guardian's Address
                                </label>
                                <input type="text" class="form-control" id="guardian_address" name="guardian_address" value="<?php echo htmlspecialchars($input['guardian_address'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: File Uploads & Platoon -->
                    <div class="form-step" data-step="4">
                        <div class="step-header">
                            <h2><i class="fas fa-upload"></i> File Uploads & Platoon Assignment</h2>
                            <p>Upload required documents and select your platoon assignment</p>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="photo" class="form-label">
                                    <i class="fas fa-camera"></i> Profile Photo
                                </label>
                                <input type="file" class="form-control <?php echo !empty($errors['photo']) ? 'error' : ''; ?>" id="photo" name="photo" accept="image/*" required>
                                <?php if (!empty($errors['photo'])): ?>
                                    <div class="error-message"><?php echo $errors['photo']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="signature" class="form-label">
                                    <i class="fas fa-signature"></i> Signature
                                </label>
                                <input type="file" class="form-control <?php echo !empty($errors['signature']) ? 'error' : ''; ?>" id="signature" name="signature" accept="image/*" required>
                                <?php if (!empty($errors['signature'])): ?>
                                    <div class="error-message"><?php echo $errors['signature']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="platoon" class="form-label">
                                    <i class="fas fa-users-cog"></i> Platoon
                                </label>
                                <select class="form-control" id="platoon" name="platoon">
                                    <option value="">Select Platoon</option>
                                    <option value="Alpha" <?php echo (($input['platoon'] ?? '') == 'Alpha') ? 'selected' : ''; ?>>Alpha</option>
                                    <option value="Bravo" <?php echo (($input['platoon'] ?? '') == 'Bravo') ? 'selected' : ''; ?>>Bravo</option>
                                    <option value="Charlie" <?php echo (($input['platoon'] ?? '') == 'Charlie') ? 'selected' : ''; ?>>Charlie</option>
                                    <option value="Delta" <?php echo (($input['platoon'] ?? '') == 'Delta') ? 'selected' : ''; ?>>Delta</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Form Navigation -->
                    <div class="form-navigation">
                        <button type="button" id="prevBtn" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" id="nextBtn" class="btn btn-primary">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="submit" id="submitBtn" class="btn btn-success" style="display: none;">
                            <i class="fas fa-check"></i> Submit Registration
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- Registration Form JavaScript with Validation -->
    <script src="js/registration-form.js?v=<?php echo time(); ?>"></script>
    <!-- Help/WebRTC module (prototype only) -->
    <script src="js/help-webrtc.js?v=<?php echo time(); ?>"></script>

    <!-- Help Modal UI (prototype only) -->
    <div id="helpBackdrop" class="help-modal-backdrop" role="presentation" aria-hidden="true"></div>
    <div id="helpModal" class="help-modal" role="dialog" aria-modal="true" aria-labelledby="helpModalTitle" aria-hidden="true">
        <div class="panel">
            <div class="panel-header">
                <h3 id="helpModalTitle" style="margin:0; font-size:1.1rem;"><i class="fas fa-headset"></i> Ask Officer for Help</h3>
                <button id="helpCloseBtn" class="btn btn-secondary btn-sm" type="button">Close</button>
            </div>
            <div class="panel-body">
                <p style="margin-top:0;">We can connect you to an officer to assist with registration. Your screen and microphone may be shared during the session.</p>
                <div style="display:flex; align-items:center; gap:.75rem; margin:.5rem 0 1rem;">
                    <span>Join Code:</span>
                    <span id="helpJoinCode" class="badge-code">— — —</span>
                    <button id="copyJoinCodeBtn" class="btn btn-secondary btn-sm" type="button" title="Copy"><i class="fas fa-copy"></i></button>
                </div>
                <div style="display:flex; align-items:center; gap:.5rem; margin-bottom:1rem;">
                    <input type="checkbox" id="shareAudio" checked>
                    <label for="shareAudio">Share microphone along with screen</label>
                </div>
                <div id="helpStatus" class="text-muted" style="min-height:1.25rem;">Not connected</div>

                <!-- Capability / HTTPS warning -->
                <div id="capabilityWarning" style="display:none; margin-top:8px; padding:8px; border:1px solid rgba(255,255,255,.12); border-radius:8px; background:#181f32; color:#e5e7eb;">
                    <div style="margin-bottom:6px;">
                        <i class="fas fa-info-circle"></i>
                        Screen sharing may not be available on this device or connection. You can still connect for voice and view the officer's screen.
                    </div>
                    <div style="font-size:.9rem; opacity:.9;">
                        Tip: Mobile screen-share usually requires HTTPS. Use an HTTPS URL (e.g., via a tunnel like ngrok) to see the share prompt.
                    </div>
                    <div style="margin-top:8px; display:flex; gap:.5rem; flex-wrap:wrap;">
                        <button id="askOfficerShareBtn" class="btn btn-secondary btn-sm" type="button"><i class="fas fa-desktop"></i> Ask Officer to Share Screen</button>
                    </div>
                </div>

                <!-- Officer screen (takeover) viewer -->
                <div id="officerViewWrap" style="display:none; margin-top:12px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                        <strong><i class="fas fa-display"></i> Officer Screen</strong>
                        <small class="text-muted">Read-only view</small>
                    </div>
                    <div style="position:relative;">
                        <video id="officerVideo" style="width:100%; max-height:48vh; background:#0b1220; border:1px solid rgba(255,255,255,.08); border-radius:8px" autoplay playsinline webkit-playsinline controls muted></video>
                        <button id="officerPlayBtn" type="button" style="display:none;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);padding:.6rem 1rem;border-radius:8px;border:1px solid rgba(255,255,255,.2);background:#111827;color:#e5e7eb;box-shadow:0 8px 24px rgba(0,0,0,.35)"><i class="fas fa-play"></i> Tap to view</button>
                    </div>
                </div>

                <!-- Chat panel -->
                <div id="chatPanel" style="margin-top:12px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                        <strong><i class="fas fa-comments"></i> Chat</strong>
                        <small class="text-muted">Prototype</small>
                    </div>
                    <div id="chatMessages" style="height:160px; overflow:auto; background:#0e1526; border:1px solid rgba(255,255,255,.08); border-radius:8px; padding:8px; font-size:.95rem;"></div>
                    <div id="chatForm" style="display:flex; gap:.5rem; margin-top:8px;">
                        <input id="chatInput" type="text" maxlength="500" class="form-control" placeholder="Type a message" />
                        <button id="chatSend" type="button" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
            <div class="panel-footer">
                <button id="helpStartBtn" class="btn btn-primary" type="button"><i class="fas fa-broadcast-tower"></i> Start Session</button>
                <button id="helpEndBtn" class="btn btn-danger" type="button" style="display:none;"><i class="fas fa-phone-slash"></i> End Session</button>
            </div>
        </div>
    </div>

    <!-- Floating Help Button (prototype only) -->
    <button id="helpBtn" class="help-fab" type="button" aria-haspopup="dialog" aria-controls="helpModal">
        <i class="fas fa-headset"></i>
        <span>Ask Officer for Help</span>
    </button>

</body>
</html>