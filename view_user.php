<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/user_admin_helpers.php';

check_login();
if (!rotc_role_in(['admin'])) {
    redirect_to_dashboard();
}

$user_id = (int)($_GET['id'] ?? 0);
if ($user_id <= 0) {
    header('Location: ' . rotc_relative_url('user_management.php'));
    exit;
}

$user = rotc_fetch_admin_user($pdo, $user_id);
if (!$user) {
    header('Location: ' . rotc_relative_url('user_management.php?error=user_not_found'));
    exit;
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function detail_item(string $label, $value, string $icon = 'fas fa-circle-info'): void
{
    $display = trim((string)$value);
    if ($display === '') {
        $display = 'N/A';
    }
    echo '<div class="detail-item">';
    echo '<div class="detail-label"><i class="' . h($icon) . '"></i>' . h($label) . '</div>';
    echo '<div class="detail-value">' . nl2br(h($display)) . '</div>';
    echo '</div>';
}

$displayName = rotc_display_name($user);
$role = rotc_preferred_value($user, ['role'], 'user');
$roleClass = str_replace('-', '_', strtolower($role));
$status = strtolower(rotc_preferred_value($user, ['status', 'profile_status'], 'active'));
$approval = strtolower(rotc_preferred_value($user, ['approval_status'], 'approved'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - ROTC Management System</title>
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
                        <div class="title-icon"><i class="fas fa-user"></i></div>
                        <div class="title-text">
                            <h1>User Details</h1>
                            <p class="subtitle"><?php echo h($displayName); ?></p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="user_management.php" class="action-btn secondary"><i class="fas fa-arrow-left"></i> Back</a>
                        <a href="edit_user.php?id=<?php echo $user_id; ?>" class="action-btn primary"><i class="fas fa-edit"></i> Edit User</a>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert success"><i class="fas fa-circle-check"></i> User information updated.</div>
            <?php endif; ?>

            <section class="profile-summary">
                <div class="user-avatar-large"><i class="fas fa-user"></i></div>
                <div>
                    <h2><?php echo h($displayName); ?></h2>
                    <p><?php echo h(rotc_preferred_value($user, ['email', 'profile_email'], 'No email')); ?></p>
                    <div class="badge-row">
                        <span class="modern-badge role-<?php echo h($roleClass); ?>"><?php echo h(ucfirst(str_replace(['_', '-'], ' ', $role))); ?></span>
                        <span class="modern-badge status-<?php echo h($status); ?>"><?php echo h(ucfirst($status)); ?></span>
                        <span class="modern-badge approval-<?php echo h($approval); ?>"><?php echo h(ucfirst($approval)); ?></span>
                    </div>
                </div>
            </section>

            <section class="detail-section">
                <h2>Account</h2>
                <div class="user-details-grid">
                    <?php detail_item('User ID', $user['id'] ?? '', 'fas fa-id-badge'); ?>
                    <?php detail_item('Username', $user['username'] ?? '', 'fas fa-user-tag'); ?>
                    <?php detail_item('Email', rotc_preferred_value($user, ['email', 'profile_email']), 'fas fa-envelope'); ?>
                    <?php detail_item('Role', ucfirst(str_replace(['_', '-'], ' ', $role)), 'fas fa-shield-halved'); ?>
                    <?php detail_item('Account Status', ucfirst($status), 'fas fa-toggle-on'); ?>
                    <?php detail_item('Approval Status', ucfirst($approval), 'fas fa-clipboard-check'); ?>
                    <?php detail_item('Active Login', ((string)($user['is_active'] ?? '1') === '0' ? 'No' : 'Yes'), 'fas fa-key'); ?>
                    <?php detail_item('Last Login', $user['last_login'] ?? '', 'fas fa-clock'); ?>
                    <?php detail_item('Created', $user['created_at'] ?? '', 'fas fa-calendar-plus'); ?>
                    <?php detail_item('Updated', $user['updated_at'] ?? '', 'fas fa-calendar-check'); ?>
                </div>
            </section>

            <section class="detail-section">
                <h2>Cadet Identity</h2>
                <div class="user-details-grid">
                    <?php detail_item('Full Name', rotc_preferred_value($user, ['profile_full_name', 'full_name'], $displayName), 'fas fa-signature'); ?>
                    <?php detail_item('First Name', rotc_preferred_value($user, ['profile_first_name', 'first_name']), 'fas fa-user'); ?>
                    <?php detail_item('Middle Name', $user['profile_middle_name'] ?? '', 'fas fa-user'); ?>
                    <?php detail_item('Last Name', rotc_preferred_value($user, ['profile_last_name', 'last_name']), 'fas fa-user'); ?>
                    <?php detail_item('Student ID', rotc_preferred_value($user, ['profile_student_id', 'student_id']), 'fas fa-id-card'); ?>
                    <?php detail_item('Student Number', $user['profile_student_number'] ?? '', 'fas fa-hashtag'); ?>
                    <?php detail_item('Gender', $user['profile_gender'] ?? '', 'fas fa-venus-mars'); ?>
                    <?php detail_item('Birthdate', rotc_preferred_value($user, ['profile_birthdate', 'profile_date_of_birth', 'profile_birth_date']), 'fas fa-cake-candles'); ?>
                    <?php detail_item('Birth Place', rotc_preferred_value($user, ['profile_birth_place', 'profile_place_of_birth']), 'fas fa-location-dot'); ?>
                    <?php detail_item('Civil Status', $user['profile_civil_status'] ?? '', 'fas fa-ring'); ?>
                    <?php detail_item('Religion', $user['profile_religion'] ?? '', 'fas fa-place-of-worship'); ?>
                </div>
            </section>

            <section class="detail-section">
                <h2>Academic and ROTC</h2>
                <div class="user-details-grid">
                    <?php detail_item('Course', rotc_preferred_value($user, ['profile_course', 'course']), 'fas fa-graduation-cap'); ?>
                    <?php detail_item('Year Level', rotc_preferred_value($user, ['profile_year_level', 'year_level']), 'fas fa-layer-group'); ?>
                    <?php detail_item('Section', $user['profile_section'] ?? '', 'fas fa-users-line'); ?>
                    <?php detail_item('Platoon', $user['profile_platoon'] ?? '', 'fas fa-person-military-rifle'); ?>
                    <?php detail_item('Profile Status', $user['profile_status'] ?? '', 'fas fa-circle-check'); ?>
                    <?php detail_item('Academic Year', $user['profile_academic_year'] ?? '', 'fas fa-calendar-days'); ?>
                    <?php detail_item('Semester', $user['profile_semester'] ?? '', 'fas fa-book'); ?>
                    <?php detail_item('Paper Form Submitted', ((string)($user['paper_form_submitted'] ?? '0') === '1' ? 'Yes' : 'No'), 'fas fa-file-lines'); ?>
                    <?php detail_item('Paper Form Date', $user['paper_form_submitted_date'] ?? '', 'fas fa-calendar'); ?>
                    <?php detail_item('Paper Form Notes', $user['paper_form_notes'] ?? '', 'fas fa-note-sticky'); ?>
                </div>
            </section>

            <section class="detail-section">
                <h2>Contact and Address</h2>
                <div class="user-details-grid">
                    <?php detail_item('Contact Number', rotc_preferred_value($user, ['profile_contact_number', 'contact_number']), 'fas fa-phone'); ?>
                    <?php detail_item('Alternate Contact', $user['profile_contact'] ?? '', 'fas fa-phone-volume'); ?>
                    <?php detail_item('Phone', $user['profile_phone'] ?? '', 'fas fa-mobile-screen'); ?>
                    <?php detail_item('Facebook Profile', $user['profile_facebook_profile'] ?? '', 'fab fa-facebook'); ?>
                    <?php detail_item('Address', $user['profile_address'] ?? '', 'fas fa-map-location-dot'); ?>
                    <?php detail_item('Province / City', $user['profile_province_city'] ?? '', 'fas fa-city'); ?>
                    <?php detail_item('Region', $user['profile_region'] ?? '', 'fas fa-map'); ?>
                    <?php detail_item('City', $user['profile_city'] ?? '', 'fas fa-building'); ?>
                    <?php detail_item('Barangay', $user['profile_barangay'] ?? '', 'fas fa-location-crosshairs'); ?>
                </div>
            </section>

            <section class="detail-section">
                <h2>Medical and Emergency</h2>
                <div class="user-details-grid">
                    <?php detail_item('Blood Type', $user['profile_blood_type'] ?? '', 'fas fa-droplet'); ?>
                    <?php detail_item('Height', $user['profile_height'] ?? '', 'fas fa-ruler-vertical'); ?>
                    <?php detail_item('Weight', $user['profile_weight'] ?? '', 'fas fa-weight-scale'); ?>
                    <?php detail_item('Medical Conditions', $user['profile_medical_conditions'] ?? '', 'fas fa-notes-medical'); ?>
                    <?php detail_item('Emergency Contact', $user['profile_emergency_contact'] ?? '', 'fas fa-truck-medical'); ?>
                    <?php detail_item('Emergency Contact Name', $user['profile_emergency_contact_name'] ?? '', 'fas fa-user-shield'); ?>
                    <?php detail_item('Emergency Contact Number', rotc_preferred_value($user, ['profile_emergency_contact_number', 'profile_emergency_phone']), 'fas fa-phone-flip'); ?>
                </div>
            </section>

            <section class="detail-section">
                <h2>Family and Beneficiary</h2>
                <div class="user-details-grid">
                    <?php detail_item('Father Name', $user['profile_father_name'] ?? '', 'fas fa-user'); ?>
                    <?php detail_item('Father Occupation', $user['profile_father_occupation'] ?? '', 'fas fa-briefcase'); ?>
                    <?php detail_item('Mother Name', $user['profile_mother_name'] ?? '', 'fas fa-user'); ?>
                    <?php detail_item('Mother Occupation', $user['profile_mother_occupation'] ?? '', 'fas fa-briefcase'); ?>
                    <?php detail_item('Guardian Name', $user['profile_guardian_name'] ?? '', 'fas fa-user-lock'); ?>
                    <?php detail_item('Guardian Contact', $user['profile_guardian_contact'] ?? '', 'fas fa-phone'); ?>
                    <?php detail_item('Guardian Relationship', $user['profile_guardian_relationship'] ?? '', 'fas fa-people-roof'); ?>
                    <?php detail_item('Guardian Address', $user['profile_guardian_address'] ?? '', 'fas fa-house'); ?>
                    <?php detail_item('Beneficiary Name', $user['profile_beneficiary_name'] ?? '', 'fas fa-hand-holding-heart'); ?>
                    <?php detail_item('Beneficiary Relationship', $user['profile_beneficiary_relationship'] ?? '', 'fas fa-user-group'); ?>
                    <?php detail_item('Beneficiary Address', $user['profile_beneficiary_address'] ?? '', 'fas fa-map-pin'); ?>
                </div>
            </section>
        </main>
    </div>

    <style>
    .compact-header { margin-bottom: var(--spacing-lg); }
    .alert.success {
        margin: 0 0 var(--spacing-lg);
        padding: var(--spacing-md) var(--spacing-lg);
        border: 1px solid rgba(40, 167, 69, .35);
        background: rgba(40, 167, 69, .12);
        color: #b8ffd0;
        border-radius: var(--radius-md);
    }
    .profile-summary {
        display: flex;
        align-items: center;
        gap: var(--spacing-lg);
        padding: var(--spacing-xl);
        margin-bottom: var(--spacing-lg);
        background: rgba(15, 20, 25, .86);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
    }
    .user-avatar-large {
        width: 88px;
        height: 88px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--military-green) 0%, #20c997 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        flex: 0 0 auto;
    }
    .profile-summary h2 {
        margin: 0 0 4px;
        color: var(--text-accent);
    }
    .profile-summary p {
        margin: 0 0 var(--spacing-sm);
        color: var(--text-secondary);
    }
    .badge-row {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-sm);
    }
    .detail-section {
        background: rgba(15, 20, 25, .86);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
        padding: var(--spacing-lg);
        margin-bottom: var(--spacing-lg);
    }
    .detail-section h2 {
        margin: 0 0 var(--spacing-md);
        color: var(--text-accent);
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .user-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: var(--spacing-md);
    }
    .detail-item {
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-sm);
        background: rgba(0, 0, 0, .22);
        padding: var(--spacing-md);
        min-width: 0;
    }
    .detail-label {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        color: var(--text-secondary);
        font-size: .78rem;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .detail-value {
        color: var(--text-accent);
        font-weight: 600;
        overflow-wrap: anywhere;
    }
    .modern-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: var(--radius-full);
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .role-admin { background: #dc3545; color: white; }
    .role-commandant { background: #6f42c1; color: white; }
    .role-instructor { background: #fd7e14; color: white; }
    .role-officer { background: #17a2b8; color: white; }
    .role-1cl { background: #28a745; color: white; }
    .role-2cl { background: #007bff; color: white; }
    .role-basic_cadet { background: #6c757d; color: white; }
    .status-active, .approval-approved { background: #28a745; color: white; }
    .status-inactive, .approval-rejected { background: #6c757d; color: white; }
    .status-pending, .approval-pending { background: #ffc107; color: #111; }
    @media (max-width: 768px) {
        .profile-summary { flex-direction: column; text-align: center; }
        .badge-row { justify-content: center; }
    }
    </style>
    <script src="js/mobile-navigation.js"></script>
</body>
</html>
