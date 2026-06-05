<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/term_enrollment.php';

check_login();

if (!in_array($_SESSION['role'] ?? '', ['cadet','basic_cadet','basic-cadet','basic-cadet'])) {
    header('Location: login.php');
    exit;
}

ensure_term_enrollment_schema();
$logger = new SecurityLogger();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Active term is the term they are trying to enroll into.
$term = get_active_term();
$school_year = $term['school_year'];
$semester = $term['semester'];

// Resolve cadet_profile_id
$stmt = $pdo->prepare("SELECT * FROM cadet_profiles WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) {
    header('Location: cadet_dashboard.php');
    exit;
}
$cadetProfileId = (int)$profile['id'];

// If already enrolled, just go back.
$status = get_cadet_enrollment_status($cadetProfileId, $school_year, $semester);
if ($status === 'enrolled') {
    header('Location: cadet_dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        // 1) Update editable profile fields
        $fields = [
            'first_name','middle_name','last_name','gender','course','section','platoon',
            'province_city','region','address','contact_number','facebook_profile',
            'religion','birthdate','place_of_birth','height','weight','skin_color','blood_type',
            'father_name','father_occupation','mother_name','mother_occupation',
            'guardian_name','guardian_contact','guardian_relationship','guardian_address'
        ];

        $updates = [];
        $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $_POST)) {
                $updates[] = "$f = ?";
                $params[] = trim((string)$_POST[$f]);
            }
        }
        if (!empty($updates)) {
            $params[] = $userId;
            $sql = "UPDATE cadet_profiles SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $u = $pdo->prepare($sql);
            $u->execute($params);
        }

        // 2) Require confirmation
        if (!isset($_POST['confirm_correct']) || $_POST['confirm_correct'] !== '1') {
            throw new Exception('Please confirm that your profile information is correct.');
        }

        // 3) Force password reset
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');
        if ($newPass === '' || strlen($newPass) < 8) {
            throw new Exception('New password must be at least 8 characters.');
        }
        if ($newPass !== $confirmPass) {
            throw new Exception('Password confirmation does not match.');
        }

        // Minimal strength rules: upper/lower/number/special
        if (!preg_match('/[A-Z]/', $newPass) || !preg_match('/[a-z]/', $newPass) || !preg_match('/[0-9]/', $newPass) || !preg_match('/[!@#$%^&*()_+\-\[\]{};":\\|,.<>\/?]/', $newPass)) {
            throw new Exception('Password must include uppercase, lowercase, number, and special character.');
        }

        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $up->execute([$hash, $userId]);
        $logger->logSecurityEvent($userId, 'PASSWORD_RESET', 'Password reset during term verification', ['term' => $school_year . ' ' . $semester], 'medium');

        // 4) Set PIN
        $pin = preg_replace('/\D+/', '', $_POST['pin'] ?? '');
        $pinConfirm = preg_replace('/\D+/', '', $_POST['pin_confirm'] ?? '');
        if (strlen($pin) < 4 || strlen($pin) > 6) {
            throw new Exception('PIN must be 4 to 6 digits.');
        }
        if ($pin !== $pinConfirm) {
            throw new Exception('PIN confirmation does not match.');
        }
        set_user_pin($userId, $pin);
        $logger->logSecurityEvent($userId, 'PIN_SET', 'PIN set during term verification', ['term' => $school_year . ' ' . $semester], 'medium');

        // 5) Enroll into active term
        set_cadet_enrollment_status($cadetProfileId, $school_year, $semester, 'enrolled', 're_enroll', $userId);
        $logger->logSecurityEvent($userId, 'REENROLL_SUCCESS', 'Cadet verified profile and enrolled into term', ['school_year' => $school_year, 'semester' => $semester], 'medium');

        $pdo->commit();

        // Require PIN next time; allow pass-through now.
        $_SESSION['pin_verified'] = true;
        $_SESSION['require_pin'] = false;

        header('Location: cadet_dashboard.php?reenroll=success');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
        $logger->logSecurityEvent($userId, 'REENROLL_FAILED', 'Re-enroll failed: ' . $e->getMessage(), ['school_year' => $school_year, 'semester' => $semester], 'medium');
        // reload profile
        $stmt = $pdo->prepare("SELECT * FROM cadet_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Profile & Re-enroll</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .verify-container { padding: var(--spacing-lg); max-width: 1100px; margin: 0 auto; }
        .verify-card { background: var(--card-bg); border-radius: var(--border-radius); box-shadow: var(--shadow-lg); border: 1px solid var(--border-primary); overflow: hidden; }
        .verify-header { padding: var(--spacing-lg); background: linear-gradient(135deg, rgba(0,255,136,0.12), rgba(78,115,223,0.10)); border-bottom: 1px solid var(--border-primary); }
        .verify-header h1 { margin: 0; color: var(--text-primary); }
        .verify-header p { margin: 8px 0 0; color: var(--text-secondary); }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--spacing-md); }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .section { padding: var(--spacing-lg); }
        .section-title { color: var(--text-accent); font-weight: 700; margin-bottom: var(--spacing-md); display:flex; align-items:center; gap:10px; }
        .field label { display:block; font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; padding: 12px 12px; border-radius: 10px; border: 1px solid var(--border-primary); background: var(--surface-primary); color: var(--text-primary); }
        .field textarea { min-height: 92px; resize: vertical; }
        .pin-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md); }
        @media (max-width: 700px) { .pin-row { grid-template-columns: 1fr; } }
        .pin-boxes { display:grid; grid-template-columns: repeat(6, 1fr); gap: 10px; }
        .pin-box { height: 54px; border-radius: 12px; border: 1px solid var(--border-primary); background: rgba(0,0,0,0.25); color: var(--text-primary); font-size: 1.3rem; text-align:center; }
        .pin-box:focus { outline: none; border-color: rgba(0,255,136,0.65); box-shadow: 0 0 0 4px rgba(0,255,136,0.14); }
        .actions { display:flex; gap: 12px; justify-content:flex-end; padding: var(--spacing-lg); border-top: 1px solid var(--border-primary); }
        .btn-primary { background: linear-gradient(135deg, #00ff88, #2d7ff9) !important; color: #06101a !important; border: none !important; }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-header">
                <h1><i class="fas fa-clipboard-check"></i> Verify Profile & Re-enroll</h1>
                <p>Term: <strong><?php echo htmlspecialchars($school_year . ' • ' . $semester); ?></strong>. Update any wrong info, set your new password and PIN, then confirm.</p>
            </div>

            <div class="section">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error" style="margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-triangle-exclamation"></i>
                        <?php foreach ($errors as $e): ?>
                            <div><?php echo htmlspecialchars($e); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="grid">
                        <div>
                            <div class="section-title"><i class="fas fa-user"></i> Personal</div>
                            <div class="field"><label>First Name</label><input name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" required></div>
                            <div class="field"><label>Middle Name</label><input name="middle_name" value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>"></div>
                            <div class="field"><label>Last Name</label><input name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" required></div>
                            <div class="field"><label>Gender</label><input name="gender" value="<?php echo htmlspecialchars($profile['gender'] ?? ''); ?>"></div>
                            <div class="field"><label>Course</label><input name="course" value="<?php echo htmlspecialchars($profile['course'] ?? ''); ?>"></div>
                            <div class="field"><label>Section</label><input name="section" value="<?php echo htmlspecialchars($profile['section'] ?? ''); ?>"></div>
                            <div class="field"><label>Platoon</label><input name="platoon" value="<?php echo htmlspecialchars($profile['platoon'] ?? ''); ?>"></div>
                        </div>
                        <div>
                            <div class="section-title"><i class="fas fa-location-dot"></i> Address & Contact</div>
                            <div class="field"><label>Province/City</label><input name="province_city" value="<?php echo htmlspecialchars($profile['province_city'] ?? ''); ?>"></div>
                            <div class="field"><label>Region</label><input name="region" value="<?php echo htmlspecialchars($profile['region'] ?? ''); ?>"></div>
                            <div class="field"><label>Address</label><textarea name="address"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea></div>
                            <div class="field"><label>Contact Number</label><input name="contact_number" value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>"></div>
                            <div class="field"><label>Facebook Profile</label><input name="facebook_profile" value="<?php echo htmlspecialchars($profile['facebook_profile'] ?? ''); ?>"></div>
                        </div>
                    </div>

                    <div style="margin-top: var(--spacing-xl);"></div>

                    <div class="grid">
                        <div>
                            <div class="section-title"><i class="fas fa-key"></i> New Password</div>
                            <div class="field"><label>New Password</label><input type="password" name="new_password" required autocomplete="new-password"></div>
                            <div class="field"><label>Confirm Password</label><input type="password" name="confirm_password" required autocomplete="new-password"></div>
                            <div class="hint" style="color: var(--text-secondary); margin-top: 8px; font-size: 0.9rem;">Must contain uppercase, lowercase, number, special, and at least 8 characters.</div>
                        </div>
                        <div>
                            <div class="section-title"><i class="fas fa-shield-halved"></i> Security PIN</div>
                            <input type="hidden" name="pin" id="pinHidden">
                            <input type="hidden" name="pin_confirm" id="pinConfirmHidden">

                            <div class="field"><label>PIN (4–6 digits)</label>
                                <div class="pin-boxes" id="pinBoxes"></div>
                            </div>
                            <div class="field" style="margin-top: 12px;"><label>Confirm PIN</label>
                                <div class="pin-boxes" id="pinConfirmBoxes"></div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: var(--spacing-lg);">
                        <label style="display:flex; align-items:center; gap:10px; color: var(--text-primary);">
                            <input type="checkbox" name="confirm_correct" value="1" required>
                            I confirm that the information above is correct and I want to enroll for this term.
                        </label>
                    </div>

                    <div class="actions">
                        <a href="cadet_dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button class="btn btn-primary btn-primary" type="submit"><i class="fas fa-check"></i> Confirm & Enroll</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function buildBoxes(container, hiddenInput) {
            const maxLen = 6;
            const boxes = [];
            container.innerHTML = '';
            for (let i = 0; i < maxLen; i++) {
                const b = document.createElement('input');
                b.type = 'password';
                b.inputMode = 'numeric';
                b.maxLength = 1;
                b.className = 'pin-box';
                b.addEventListener('input', () => {
                    b.value = (b.value || '').replace(/\D/g, '');
                    hiddenInput.value = boxes.map(x => x.value || '').join('');
                    if (b.value && i < maxLen - 1) boxes[i+1].focus();
                });
                b.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !b.value && i > 0) boxes[i-1].focus();
                });
                boxes.push(b);
                container.appendChild(b);
            }
            setTimeout(() => boxes[0].focus(), 150);
        }

        buildBoxes(document.getElementById('pinBoxes'), document.getElementById('pinHidden'));
        buildBoxes(document.getElementById('pinConfirmBoxes'), document.getElementById('pinConfirmHidden'));
    </script>
</body>
</html>
