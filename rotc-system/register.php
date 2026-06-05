<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/SecurityLogger.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/');
    exit();
}

$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log registration attempt
    SecurityLogger::logSecurityEvent('REGISTRATION_ATTEMPT', 'User attempted registration', null, 'LOW', [
        'email' => $_POST['email'] ?? 'not_provided',
        'username' => $_POST['username'] ?? 'not_provided',
        'student_number' => $_POST['student_number'] ?? 'not_provided'
    ]);
    
    // Validate and sanitize input
    $student_number = trim($_POST['student_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
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
    
    // Validation
    if (empty($username)) $errors['username'] = 'Username is required';
    if (empty($student_number)) $errors['student_number'] = 'Student number is required';
    if (empty($full_name)) $errors['full_name'] = 'Full name is required';
    if (empty($gender)) $errors['gender'] = 'Gender is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required';
    if (empty($password)) $errors['password'] = 'Password is required';
    if (strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters';
    if ($password !== $confirm_password) $errors['confirm_password'] = 'Passwords do not match';
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] != 0) { $errors['photo'] = 'Profile photo is required.'; }
    if (!isset($_FILES['signature']) || $_FILES['signature']['error'] != 0) { $errors['signature'] = 'Signature image is required.'; }
    
    // Check if email or student number already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $errors['db'] = 'Email or username already exists';
        }
    }
    
    // If no errors, create the user
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, approval_status, status) 
                VALUES (?, ?, ?, 'cadet', 'pending', 'inactive')
            ");
            $stmt->execute([$username, $email, $hashed_password]);
            $user_id = $pdo->lastInsertId();
            
            $photo_path = 'uploads/photos/' . $user_id . '_' . basename($_FILES['photo']['name']);
            $signature_path = 'uploads/signatures/' . $user_id . '_' . basename($_FILES['signature']['name']);

            // Ensure upload directories exist
            if (!is_dir(dirname($photo_path))) { mkdir(dirname($photo_path), 0755, true); }
            if (!is_dir(dirname($signature_path))) { mkdir(dirname($signature_path), 0755, true); }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path) && move_uploaded_file($_FILES['signature']['tmp_name'], $signature_path)) {
                // Create cadet profile
                $stmt = $pdo->prepare("
                    INSERT INTO cadet_profiles (
                    user_id, student_number, full_name, gender, address, 
                    contact_number, course, section, religion, birth_date, place_of_birth,
                    height, weight, skin_color, blood_type, father_name, father_occupation,
                    mother_name, mother_occupation, guardian_name, guardian_contact,
                    guardian_relationship, guardian_address, platoon, photo_path, signature_path, status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
                )
                ");
                $stmt->execute([
                    $user_id, $student_number, $full_name, $gender, $address,
                    $contact_number, $course, $section, $religion, $birthdate, $place_of_birth,
                    $height, $weight, $skin_color, $blood_type, $father_name, $father_occupation,
                    $mother_name, $mother_occupation, $guardian_name, $guardian_contact,
                    $guardian_relationship, $guardian_address, $platoon, $photo_path, $signature_path
                ]);
                
                $pdo->commit();
                
                // Log successful registration
                SecurityLogger::logSecurityEvent('REGISTRATION_SUCCESS', 'User registration completed successfully', $user_id, 'MEDIUM', [
                    'username' => $username,
                    'email' => $email,
                    'student_number' => $student_number,
                    'role' => 'cadet'
                ]);
                
                $success_message = 'Registration successful! Your application is pending approval.';
            } else {
                $pdo->rollBack();
                $errors['db'] = 'File upload failed.';
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            
            // Log failed registration
            SecurityLogger::logSecurityEvent('REGISTRATION_FAILED', 'User registration failed: ' . $e->getMessage(), null, 'MEDIUM', [
                'username' => $username ?? 'unknown',
                'email' => $email ?? 'unknown',
                'error_message' => $e->getMessage()
            ]);
            
            $errors['db'] = 'Registration failed. Please try again.';
        }
    }
}

$page_title = 'Cadet Registration';
include 'includes/header.php';
?>

<style>
    .tab { display: none; }
    .step { height: 15px; width: 15px; margin: 0 2px; background-color: #bbbbbb; border: none; border-radius: 50%; display: inline-block; opacity: 0.5; }
    .step.active { opacity: 1; }
    .step.finish { background-color: #0d6efd; }
    .form-control.is-invalid { border-color: #dc3545; }
    .is-invalid ~ .invalid-feedback, .invalid-feedback { display: block; color: #dc3545; }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card my-4">
                <div class="card-header"><h2 class="text-center">Cadet Registration Form</h2></div>
                <div class="card-body p-4">
                    <?php if (!empty($errors['db'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db']); ?></div>
                    <?php endif; ?>
                    <form id="regForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" novalidate>
                        
                        <!-- Steps Indicator -->
                        <div style="text-align:center;margin-bottom:20px;">
                            <span class="step"></span><span class="step"></span><span class="step"></span><span class="step"></span>
                        </div>

                        <!-- Tab 1: Account Credentials -->
                        <div class="tab">
                            <h4 class="mb-3">Account Credentials</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control <?php echo !empty($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($input['username']); ?>" required>
                                    <div class="invalid-feedback"><?php echo $errors['username'] ?? ''; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($input['email']); ?>" required>
                                    <div class="invalid-feedback"><?php echo $errors['email'] ?? ''; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                                    <div class="invalid-feedback"><?php echo $errors['password'] ?? ''; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control <?php echo !empty($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                                    <div class="invalid-feedback"><?php echo $errors['confirm_password'] ?? ''; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Personal & Academic -->
                        <div class="tab">
                            <h4 class="mb-3">Personal & Academic Information</h4>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control <?php echo !empty($errors['full_name']) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" value="<?php echo htmlspecialchars($input['full_name']); ?>" required>
                                    <div class="invalid-feedback"><?php echo $errors['full_name'] ?? ''; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="student_number" class="form-label">Student Number</label>
                                    <input type="text" class="form-control <?php echo !empty($errors['student_number']) ? 'is-invalid' : ''; ?>" id="student_number" name="student_number" value="<?php echo htmlspecialchars($input['student_number']); ?>" required>
                                    <div class="invalid-feedback"><?php echo $errors['student_number'] ?? ''; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($input['contact_number']); ?>">
                                </div>
                                <div class="col-12">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($input['address']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="course" class="form-label">Course</label>
                                    <input type="text" class="form-control" id="course" name="course" value="<?php echo htmlspecialchars($input['course']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="section" class="form-label">Section</label>
                                    <input type="text" class="form-control" id="section" name="section" value="<?php echo htmlspecialchars($input['section']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="Male" <?php echo ($input['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($input['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="birth_date" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($input['birth_date']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="religion" class="form-label">Religion</label>
                                    <input type="text" class="form-control" id="religion" name="religion" value="<?php echo htmlspecialchars($input['religion']); ?>">
                                </div>
                                <div class="col-12">
                                    <label for="place_of_birth" class="form-label">Place of Birth</label>
                                    <input type="text" class="form-control" id="place_of_birth" name="place_of_birth" value="<?php echo htmlspecialchars($input['place_of_birth']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Physical & Family -->
                        <div class="tab">
                            <h4 class="mb-3">Physical & Family Information</h4>
                            <div class="row g-3">
                                <div class="col-md-3"><label for="height" class="form-label">Height (cm)</label><input type="text" class="form-control" id="height" name="height" value="<?php echo htmlspecialchars($input['height']); ?>"></div>
                                <div class="col-md-3"><label for="weight" class="form-label">Weight (kg)</label><input type="text" class="form-control" id="weight" name="weight" value="<?php echo htmlspecialchars($input['weight']); ?>"></div>
                                <div class="col-md-3"><label for="skin_color" class="form-label">Skin Color</label><input type="text" class="form-control" id="skin_color" name="skin_color" value="<?php echo htmlspecialchars($input['skin_color']); ?>"></div>
                                <div class="col-md-3"><label for="blood_type" class="form-label">Blood Type</label><input type="text" class="form-control" id="blood_type" name="blood_type" value="<?php echo htmlspecialchars($input['blood_type']); ?>"></div>
                                <div class="col-md-6"><label for="father_name" class="form-label">Father's Name</label><input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo htmlspecialchars($input['father_name']); ?>"></div>
                                <div class="col-md-6"><label for="father_occupation" class="form-label">Father's Occupation</label><input type="text" class="form-control" id="father_occupation" name="father_occupation" value="<?php echo htmlspecialchars($input['father_occupation']); ?>"></div>
                                <div class="col-md-6"><label for="mother_name" class="form-label">Mother's Name</label><input type="text" class="form-control" id="mother_name" name="mother_name" value="<?php echo htmlspecialchars($input['mother_name']); ?>"></div>
                                <div class="col-md-6"><label for="mother_occupation" class="form-label">Mother's Occupation</label><input type="text" class="form-control" id="mother_occupation" name="mother_occupation" value="<?php echo htmlspecialchars($input['mother_occupation']); ?>"></div>
                                <div class="col-12"><hr></div>
                                <div class="col-md-6"><label for="guardian_name" class="form-label">Guardian's Name</label><input type="text" class="form-control" id="guardian_name" name="guardian_name" value="<?php echo htmlspecialchars($input['guardian_name']); ?>"></div>
                                <div class="col-md-6"><label for="guardian_contact" class="form-label">Guardian's Contact</label><input type="text" class="form-control" id="guardian_contact" name="guardian_contact" value="<?php echo htmlspecialchars($input['guardian_contact']); ?>"></div>
                                <div class="col-md-6"><label for="guardian_relationship" class="form-label">Relationship to Guardian</label><input type="text" class="form-control" id="guardian_relationship" name="guardian_relationship" value="<?php echo htmlspecialchars($input['guardian_relationship']); ?>"></div>
                                <div class="col-md-6"><label for="guardian_address" class="form-label">Guardian's Address</label><input type="text" class="form-control" id="guardian_address" name="guardian_address" value="<?php echo htmlspecialchars($input['guardian_address']); ?>"></div>
                            </div>
                        </div>

                        <!-- Tab 4: File Uploads & Platoon -->
                        <div class="tab">
                            <h4 class="mb-3">File Uploads & Platoon</h4>
                             <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="photo" class="form-label">Profile Photo</label>
                                    <input type="file" class="form-control <?php echo !empty($errors['photo']) ? 'is-invalid' : ''; ?>" id="photo" name="photo" required>
                                    <div class="invalid-feedback"><?php echo $errors['photo'] ?? ''; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="signature" class="form-label">Signature</label>
                                    <input type="file" class="form-control <?php echo !empty($errors['signature']) ? 'is-invalid' : ''; ?>" id="signature" name="signature" required>
                                    <div class="invalid-feedback"><?php echo $errors['signature'] ?? ''; ?></div>
                                </div>
                                <div class="col-12">
                                    <label for="platoon" class="form-label">Platoon</label>
                                    <input type="text" class="form-control" id="platoon" name="platoon" value="<?php echo htmlspecialchars($input['platoon']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" id="prevBtn" class="btn btn-secondary me-2" onclick="nextPrev(-1)">Previous</button>
                            <button type="button" id="nextBtn" class="btn btn-primary" onclick="nextPrev(1)">Next</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var currentTab = 0;
    showTab(currentTab);

    function showTab(n) {
        var x = document.getElementsByClassName("tab");
        if (x.length == 0) return;
        x[n].style.display = "block";
        document.getElementById("prevBtn").style.display = (n == 0) ? "none" : "inline";
        document.getElementById("nextBtn").innerHTML = (n == (x.length - 1)) ? "Submit" : "Next";
        fixStepIndicator(n);
    }

    function nextPrev(n) {
        var x = document.getElementsByClassName("tab");
        if (n == 1 && !validateForm()) return false;
        x[currentTab].style.display = "none";
        currentTab = currentTab + n;
        if (currentTab >= x.length) {
            document.getElementById("regForm").submit();
            return false;
        }
        showTab(currentTab);
    }

    function validateForm() {
        var x, y, i, valid = true;
        x = document.getElementsByClassName("tab");
        y = x[currentTab].querySelectorAll("input[required]");
        var form = document.getElementById("regForm");

        for (i = 0; i < y.length; i++) {
            if (y[i].value == "") {
                y[i].classList.add("is-invalid");
                valid = false;
            } else {
                 y[i].classList.remove("is-invalid");
            }
        }
        
        // Custom validation for password confirmation
        if (x[currentTab].querySelector('#password')) {
            const password = form.querySelector('#password');
            const confirm_password = form.querySelector('#confirm_password');
            if (password.value !== confirm_password.value) {
                confirm_password.classList.add('is-invalid');
                confirm_password.nextElementSibling.textContent = 'Passwords do not match.';
                valid = false;
            } else {
                confirm_password.classList.remove('is-invalid');
            }
        }

        if (valid) {
            document.getElementsByClassName("step")[currentTab].classList.add("finish");
        }
        return valid;
    }

    function fixStepIndicator(n) {
        var i, x = document.getElementsByClassName("step");
        for (i = 0; i < x.length; i++) {
            x[i].classList.remove("active");
        }
        x[n].classList.add("active");
    }
</script>

<?php include 'includes/footer.php'; ?>