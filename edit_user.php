<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/user_admin_helpers.php';

check_login();
if (!rotc_role_in(['admin'])) {
    redirect_to_dashboard();
}

$securityLogger = new SecurityLogger($pdo);
$user_id = (int)($_GET['id'] ?? 0);
if ($user_id <= 0) {
    header('Location: ' . rotc_relative_url('user_management.php'));
    exit;
}

$error_message = '';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function selected_value($actual, string $expected): string
{
    $normalize = fn($value) => strtolower(str_replace('-', '_', (string)$value));
    return $normalize($actual) === $normalize($expected) ? 'selected' : '';
}

function checked_value($actual): string
{
    return ((string)$actual === '1' || strtolower((string)$actual) === 'yes') ? 'checked' : '';
}

function edit_field(string $label, string $name, $value, string $type = 'text'): void
{
    echo '<label class="field">';
    echo '<span>' . h($label) . '</span>';
    echo '<input type="' . h($type) . '" name="' . h($name) . '" value="' . h($value) . '">';
    echo '</label>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = rotc_post_value('username');
    $email = rotc_post_value('email');

    try {
        if ($username === '' || $email === '') {
            throw new RuntimeException('Username and email are required.');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('That username is already used by another account.');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('That email is already used by another account.');
        }

        $pdo->beginTransaction();

        $userValues = [
            'username' => $username,
            'email' => $email,
            'role' => rotc_post_value('role'),
            'full_name' => rotc_post_value('full_name'),
            'first_name' => rotc_post_value('first_name'),
            'last_name' => rotc_post_value('last_name'),
            'student_id' => rotc_post_value('student_id'),
            'course' => rotc_post_value('course'),
            'year_level' => rotc_post_value('year_level'),
            'contact_number' => rotc_post_value('contact_number'),
            'status' => rotc_post_value('status'),
            'approval_status' => rotc_post_value('approval_status'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'paper_form_submitted' => isset($_POST['paper_form_submitted']) ? 1 : 0,
            'paper_form_notes' => rotc_post_value('paper_form_notes'),
        ];

        $newPassword = (string)($_POST['new_password'] ?? '');
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            $userValues['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        rotc_update_columns($pdo, 'users', $userValues, 'id = ?', [$user_id]);

        $profileValues = [
            'student_number' => rotc_post_value('profile_student_number'),
            'student_id' => rotc_post_value('student_id'),
            'full_name' => rotc_post_value('full_name'),
            'email' => $email,
            'first_name' => rotc_post_value('first_name'),
            'middle_name' => rotc_post_value('profile_middle_name'),
            'last_name' => rotc_post_value('last_name'),
            'gender' => rotc_post_value('profile_gender'),
            'address' => rotc_post_value('profile_address'),
            'contact_number' => rotc_post_value('contact_number'),
            'contact' => rotc_post_value('profile_contact'),
            'phone' => rotc_post_value('profile_phone'),
            'course' => rotc_post_value('course'),
            'section' => rotc_post_value('profile_section'),
            'year_level' => rotc_post_value('year_level'),
            'platoon' => rotc_post_value('profile_platoon'),
            'status' => rotc_post_value('profile_status'),
            'facebook_profile' => rotc_post_value('profile_facebook_profile'),
            'birthdate' => rotc_post_value('profile_birthdate'),
            'date_of_birth' => rotc_post_value('profile_birthdate'),
            'birth_date' => rotc_post_value('profile_birthdate'),
            'place_of_birth' => rotc_post_value('profile_birth_place'),
            'birth_place' => rotc_post_value('profile_birth_place'),
            'province_city' => rotc_post_value('profile_province_city'),
            'barangay' => rotc_post_value('profile_barangay'),
            'city' => rotc_post_value('profile_city'),
            'region' => rotc_post_value('profile_region'),
            'civil_status' => rotc_post_value('profile_civil_status'),
            'religion' => rotc_post_value('profile_religion'),
            'blood_type' => rotc_post_value('profile_blood_type'),
            'height' => rotc_post_value('profile_height'),
            'weight' => rotc_post_value('profile_weight'),
            'medical_conditions' => rotc_post_value('profile_medical_conditions'),
            'emergency_contact' => rotc_post_value('profile_emergency_contact'),
            'emergency_contact_name' => rotc_post_value('profile_emergency_contact_name'),
            'emergency_contact_number' => rotc_post_value('profile_emergency_contact_number'),
            'emergency_phone' => rotc_post_value('profile_emergency_contact_number'),
            'guardian_name' => rotc_post_value('profile_guardian_name'),
            'guardian_contact' => rotc_post_value('profile_guardian_contact'),
            'guardian_relationship' => rotc_post_value('profile_guardian_relationship'),
            'guardian_address' => rotc_post_value('profile_guardian_address'),
            'beneficiary_name' => rotc_post_value('profile_beneficiary_name'),
            'beneficiary_relationship' => rotc_post_value('profile_beneficiary_relationship'),
            'beneficiary_address' => rotc_post_value('profile_beneficiary_address'),
            'father_name' => rotc_post_value('profile_father_name'),
            'father_occupation' => rotc_post_value('profile_father_occupation'),
            'mother_name' => rotc_post_value('profile_mother_name'),
            'mother_occupation' => rotc_post_value('profile_mother_occupation'),
        ];

        rotc_upsert_cadet_profile($pdo, $user_id, $profileValues);
        $pdo->commit();

        $securityLogger->logSecurityEvent($_SESSION['user_id'], 'USER_MODIFIED', "Admin updated user ID: {$user_id}", 'medium');
        header('Location: ' . rotc_relative_url('view_user.php?id=' . $user_id . '&updated=1'));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = $e->getMessage();
        $securityLogger->logSecurityEvent($_SESSION['user_id'] ?? null, 'USER_MODIFICATION_FAILED', "Failed updating user ID {$user_id}: {$error_message}", 'high');
    }
}

$user = rotc_fetch_admin_user($pdo, $user_id);
if (!$user) {
    header('Location: ' . rotc_relative_url('user_management.php?error=user_not_found'));
    exit;
}

$displayName = rotc_display_name($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - ROTC Management System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <button class="sidebar-toggle-fixed" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div class="dashboard-container">
        <?php $NAV_BASE = ''; include __DIR__ . '/includes/admin_nav.php'; ?>
        <main class="main-content">
            <div class="dashboard-header compact-header">
                <div class="header-content">
                    <div class="header-title">
                        <div class="title-icon"><i class="fas fa-user-pen"></i></div>
                        <div class="title-text">
                            <h1>Edit User</h1>
                            <p class="subtitle"><?php echo h($displayName); ?></p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a class="action-btn secondary" href="view_user.php?id=<?php echo $user_id; ?>"><i class="fas fa-eye"></i> View</a>
                        <a class="action-btn secondary" href="user_management.php"><i class="fas fa-arrow-left"></i> Back</a>
                    </div>
                </div>
            </div>

            <?php if ($error_message !== ''): ?>
                <div class="alert error"><i class="fas fa-triangle-exclamation"></i> <?php echo h($error_message); ?></div>
            <?php endif; ?>

            <form class="admin-edit-form" method="post">
                <section class="edit-section">
                    <h2>Account</h2>
                    <div class="field-grid">
                        <?php edit_field('Username', 'username', $user['username'] ?? ''); ?>
                        <?php edit_field('Email', 'email', rotc_preferred_value($user, ['email', 'profile_email'])); ?>
                        <label class="field">
                            <span>Role</span>
                            <select name="role">
                                <?php foreach (['admin' => 'Admin', 'commandant' => 'Commandant', 'instructor' => 'Instructor', 'officer' => 'Officer', '1cl' => '1CL', '2cl' => '2CL', 'basic_cadet' => 'Basic Cadet'] as $value => $label): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo selected_value($user['role'] ?? '', $value); ?>><?php echo h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Account Status</span>
                            <select name="status">
                                <?php foreach (['active', 'inactive', 'pending', 'suspended'] as $value): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo selected_value(strtolower((string)($user['status'] ?? 'active')), $value); ?>><?php echo h(ucfirst($value)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Approval Status</span>
                            <select name="approval_status">
                                <?php foreach (['approved', 'pending', 'rejected'] as $value): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo selected_value(strtolower((string)($user['approval_status'] ?? 'approved')), $value); ?>><?php echo h(ucfirst($value)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field checkbox-field"><input type="checkbox" name="is_active" value="1" <?php echo checked_value($user['is_active'] ?? '1'); ?>> <span>Active login</span></label>
                        <?php edit_field('Reset Password', 'new_password', '', 'password'); ?>
                    </div>
                </section>

                <section class="edit-section">
                    <h2>Cadet Identity</h2>
                    <div class="field-grid">
                        <?php edit_field('Full Name', 'full_name', rotc_preferred_value($user, ['profile_full_name', 'full_name'])); ?>
                        <?php edit_field('First Name', 'first_name', rotc_preferred_value($user, ['profile_first_name', 'first_name'])); ?>
                        <?php edit_field('Middle Name', 'profile_middle_name', $user['profile_middle_name'] ?? ''); ?>
                        <?php edit_field('Last Name', 'last_name', rotc_preferred_value($user, ['profile_last_name', 'last_name'])); ?>
                        <?php edit_field('Student ID', 'student_id', rotc_preferred_value($user, ['profile_student_id', 'student_id'])); ?>
                        <?php edit_field('Student Number', 'profile_student_number', $user['profile_student_number'] ?? ''); ?>
                        <label class="field">
                            <span>Gender</span>
                            <select name="profile_gender">
                                <option value="">Select</option>
                                <?php foreach (['Male', 'Female'] as $value): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo selected_value($user['profile_gender'] ?? '', $value); ?>><?php echo h($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php edit_field('Birthdate', 'profile_birthdate', rotc_preferred_value($user, ['profile_birthdate', 'profile_date_of_birth', 'profile_birth_date']), 'date'); ?>
                        <?php edit_field('Birth Place', 'profile_birth_place', rotc_preferred_value($user, ['profile_birth_place', 'profile_place_of_birth'])); ?>
                    </div>
                </section>

                <section class="edit-section">
                    <h2>Academic and ROTC</h2>
                    <div class="field-grid">
                        <?php edit_field('Course', 'course', rotc_preferred_value($user, ['profile_course', 'course'])); ?>
                        <?php edit_field('Year Level', 'year_level', rotc_preferred_value($user, ['profile_year_level', 'year_level'])); ?>
                        <?php edit_field('Section', 'profile_section', $user['profile_section'] ?? ''); ?>
                        <?php edit_field('Platoon', 'profile_platoon', $user['profile_platoon'] ?? ''); ?>
                        <label class="field">
                            <span>Cadet Profile Status</span>
                            <select name="profile_status">
                                <?php foreach (['Active', 'Inactive', 'Pending'] as $value): ?>
                                    <option value="<?php echo h($value); ?>" <?php echo selected_value($user['profile_status'] ?? 'Active', $value); ?>><?php echo h($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field checkbox-field"><input type="checkbox" name="paper_form_submitted" value="1" <?php echo checked_value($user['paper_form_submitted'] ?? '0'); ?>> <span>Paper form submitted</span></label>
                    </div>
                    <label class="field full-width">
                        <span>Paper Form Notes</span>
                        <textarea name="paper_form_notes"><?php echo h($user['paper_form_notes'] ?? ''); ?></textarea>
                    </label>
                </section>

                <section class="edit-section">
                    <h2>Contact and Address</h2>
                    <div class="field-grid">
                        <?php edit_field('Contact Number', 'contact_number', rotc_preferred_value($user, ['profile_contact_number', 'contact_number'])); ?>
                        <?php edit_field('Alternate Contact', 'profile_contact', $user['profile_contact'] ?? ''); ?>
                        <?php edit_field('Phone', 'profile_phone', $user['profile_phone'] ?? ''); ?>
                        <?php edit_field('Facebook Profile', 'profile_facebook_profile', $user['profile_facebook_profile'] ?? ''); ?>
                        <?php edit_field('Province / City', 'profile_province_city', $user['profile_province_city'] ?? ''); ?>
                        <?php edit_field('Region', 'profile_region', $user['profile_region'] ?? ''); ?>
                        <?php edit_field('City', 'profile_city', $user['profile_city'] ?? ''); ?>
                        <?php edit_field('Barangay', 'profile_barangay', $user['profile_barangay'] ?? ''); ?>
                    </div>
                    <label class="field full-width">
                        <span>Address</span>
                        <textarea name="profile_address"><?php echo h($user['profile_address'] ?? ''); ?></textarea>
                    </label>
                </section>

                <section class="edit-section">
                    <h2>Medical and Emergency</h2>
                    <div class="field-grid">
                        <?php edit_field('Civil Status', 'profile_civil_status', $user['profile_civil_status'] ?? ''); ?>
                        <?php edit_field('Religion', 'profile_religion', $user['profile_religion'] ?? ''); ?>
                        <?php edit_field('Blood Type', 'profile_blood_type', $user['profile_blood_type'] ?? ''); ?>
                        <?php edit_field('Height', 'profile_height', $user['profile_height'] ?? ''); ?>
                        <?php edit_field('Weight', 'profile_weight', $user['profile_weight'] ?? ''); ?>
                        <?php edit_field('Emergency Contact Name', 'profile_emergency_contact_name', $user['profile_emergency_contact_name'] ?? ''); ?>
                        <?php edit_field('Emergency Contact Number', 'profile_emergency_contact_number', rotc_preferred_value($user, ['profile_emergency_contact_number', 'profile_emergency_phone'])); ?>
                        <?php edit_field('Emergency Contact Details', 'profile_emergency_contact', $user['profile_emergency_contact'] ?? ''); ?>
                    </div>
                    <label class="field full-width">
                        <span>Medical Conditions</span>
                        <textarea name="profile_medical_conditions"><?php echo h($user['profile_medical_conditions'] ?? ''); ?></textarea>
                    </label>
                </section>

                <section class="edit-section">
                    <h2>Family and Beneficiary</h2>
                    <div class="field-grid">
                        <?php edit_field('Father Name', 'profile_father_name', $user['profile_father_name'] ?? ''); ?>
                        <?php edit_field('Father Occupation', 'profile_father_occupation', $user['profile_father_occupation'] ?? ''); ?>
                        <?php edit_field('Mother Name', 'profile_mother_name', $user['profile_mother_name'] ?? ''); ?>
                        <?php edit_field('Mother Occupation', 'profile_mother_occupation', $user['profile_mother_occupation'] ?? ''); ?>
                        <?php edit_field('Guardian Name', 'profile_guardian_name', $user['profile_guardian_name'] ?? ''); ?>
                        <?php edit_field('Guardian Contact', 'profile_guardian_contact', $user['profile_guardian_contact'] ?? ''); ?>
                        <?php edit_field('Guardian Relationship', 'profile_guardian_relationship', $user['profile_guardian_relationship'] ?? ''); ?>
                        <?php edit_field('Beneficiary Name', 'profile_beneficiary_name', $user['profile_beneficiary_name'] ?? ''); ?>
                        <?php edit_field('Beneficiary Relationship', 'profile_beneficiary_relationship', $user['profile_beneficiary_relationship'] ?? ''); ?>
                    </div>
                    <label class="field full-width">
                        <span>Guardian Address</span>
                        <textarea name="profile_guardian_address"><?php echo h($user['profile_guardian_address'] ?? ''); ?></textarea>
                    </label>
                    <label class="field full-width">
                        <span>Beneficiary Address</span>
                        <textarea name="profile_beneficiary_address"><?php echo h($user['profile_beneficiary_address'] ?? ''); ?></textarea>
                    </label>
                </section>

                <div class="form-actions">
                    <button class="action-btn primary" type="submit"><i class="fas fa-save"></i> Save Changes</button>
                    <a class="action-btn secondary" href="view_user.php?id=<?php echo $user_id; ?>">Cancel</a>
                </div>
            </form>
        </main>
    </div>

    <style>
    .compact-header { margin-bottom: var(--spacing-lg); }
    .alert.error {
        margin: 0 0 var(--spacing-lg);
        padding: var(--spacing-md) var(--spacing-lg);
        border: 1px solid rgba(220, 53, 69, .35);
        background: rgba(220, 53, 69, .12);
        color: #ffb3bd;
        border-radius: var(--radius-md);
    }
    .admin-edit-form {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-lg);
    }
    .edit-section {
        background: rgba(15, 20, 25, .86);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-lg);
    }
    .edit-section h2 {
        margin: 0 0 var(--spacing-md);
        color: var(--text-accent);
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .field-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: var(--spacing-md);
    }
    .field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        color: var(--text-secondary);
        font-weight: 600;
        font-size: .85rem;
    }
    .field input, .field select, .field textarea {
        width: 100%;
        min-height: 42px;
        padding: 10px 12px;
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-sm);
        background: rgba(0, 0, 0, .28);
        color: var(--text-accent);
    }
    .field textarea { min-height: 90px; resize: vertical; }
    .full-width { margin-top: var(--spacing-md); }
    .checkbox-field {
        justify-content: center;
        flex-direction: row;
        align-items: center;
        min-height: 42px;
    }
    .checkbox-field input { width: auto; min-height: auto; }
    .form-actions {
        display: flex;
        gap: var(--spacing-md);
        justify-content: flex-end;
        padding-bottom: var(--spacing-xl);
    }
    @media (max-width: 768px) {
        .form-actions { flex-direction: column; }
    }
    </style>
    <script src="js/mobile-navigation.js"></script>
</body>
</html>
