<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/term_enrollment.php';
require_once 'includes/SecurityLogger.php';

check_login();
if (($_SESSION['role'] ?? '') !== 'admin') {
    redirect_to_dashboard();
    exit;
}

ensure_term_enrollment_schema();
$logger = new SecurityLogger();

$term = get_active_term();
$sy = $term['school_year'] ?? '';
$searchSySelected = $_POST['school_year'] ?? $sy;
$availableSy = [];
try {
    $stmtSy = $pdo->query("SELECT DISTINCT school_year FROM cadet_enrollments WHERE semester = '1st' AND enrollment_status = 'enrolled' ORDER BY school_year DESC");
    $availableSy = $stmtSy ? $stmtSy->fetchAll(PDO::FETCH_COLUMN, 0) : [];
}
catch (Throwable $e) {
    $availableSy = [];
}
if (empty($availableSy) && $sy !== '') {
    $availableSy[] = $sy;
}

// Handle search + update via POST
$searchResults = [];
$secondSemResults = [];
$notEnrolledSecondSemResults = [];
$selectedCadet = null;
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'search') {
        $searchSy = trim($_POST['school_year'] ?? '');
        $keyword = trim($_POST['keyword'] ?? '');
        $searchType = $_POST['search_type'] ?? 'first_sem';

        if ($searchSy === '') {
            $errors[] = 'Please select a School Year.';
        }
        else {
            if ($searchType === 'first_sem') {
                $sql = "SELECT cp.id AS cadet_profile_id, u.id AS user_id, u.username, u.email,
                               cp.student_id, cp.first_name, cp.middle_name, cp.last_name,
                               cp.course, cp.section, cp.gender, cp.contact_number
                        FROM cadet_enrollments ce
                        JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
                        JOIN users u ON cp.user_id = u.id
                        WHERE ce.school_year = ?
                          AND ce.semester = '1st'
                          AND ce.enrollment_status = 'enrolled'";
                $params = [$searchSy];
                if ($keyword !== '') {
                    $sql .= " AND (cp.student_id LIKE ? OR cp.first_name LIKE ? OR cp.last_name LIKE ? OR u.username LIKE ? )";
                    $like = '%' . $keyword . '%';
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                }
                $sql .= " ORDER BY cp.last_name, cp.first_name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $searchResults = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }
            elseif ($searchType === 'second_sem') {
                // Search 2nd semester enrolled cadets
                $sql = "SELECT cp.id AS cadet_profile_id, u.id AS user_id, u.username, u.email,
                               cp.student_id, cp.first_name, cp.middle_name, cp.last_name,
                               cp.course, cp.section, cp.gender, cp.contact_number
                        FROM cadet_enrollments ce
                        JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
                        JOIN users u ON cp.user_id = u.id
                        WHERE ce.school_year = ?
                          AND ce.semester = '2nd'
                          AND ce.enrollment_status = 'enrolled'";
                $params = [$searchSy];
                if ($keyword !== '') {
                    $sql .= " AND (cp.student_id LIKE ? OR cp.first_name LIKE ? OR cp.last_name LIKE ? OR u.username LIKE ? )";
                    $like = '%' . $keyword . '%';
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                }
                $sql .= " ORDER BY cp.last_name, cp.first_name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $secondSemResults = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }
            elseif ($searchType === 'not_enrolled_second_sem') {
                // Find cadets enrolled in 1st semester but NOT in 2nd semester
                $sql = "SELECT cp.id AS cadet_profile_id, u.id AS user_id, u.username, u.email,
                               cp.student_id, cp.first_name, cp.middle_name, cp.last_name,
                               cp.course, cp.section, cp.gender, cp.contact_number
                        FROM cadet_enrollments ce
                        JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
                        JOIN users u ON cp.user_id = u.id
                        WHERE ce.school_year = ?
                          AND ce.semester = '1st'
                          AND ce.enrollment_status = 'enrolled'
                          AND NOT EXISTS (
                              SELECT 1 FROM cadet_enrollments ce2
                              WHERE ce2.cadet_profile_id = cp.id
                                AND ce2.school_year = ?
                                AND ce2.semester = '2nd'
                                AND ce2.enrollment_status = 'enrolled'
                          )";
                $params = [$searchSy, $searchSy];
                if ($keyword !== '') {
                    $sql .= " AND (cp.student_id LIKE ? OR cp.first_name LIKE ? OR cp.last_name LIKE ? OR u.username LIKE ? )";
                    $like = '%' . $keyword . '%';
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                }
                $sql .= " ORDER BY cp.last_name, cp.first_name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $notEnrolledSecondSemResults = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }
        }
    }
    elseif ($action === 'revert_to_first_sem') {
        $cpid = (int)($_POST['cadet_profile_id'] ?? 0);
        $targetSy = trim($_POST['target_sy'] ?? '');

        if ($cpid <= 0) {
            $errors[] = 'Invalid cadet selection.';
        }
        if ($targetSy === '') {
            $errors[] = 'Target School Year is required.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Set 2nd semester enrollment status to dropped (revert)
                set_cadet_enrollment_status($cpid, $targetSy, '2nd', 'dropped', 'admin_revert', $_SESSION['user_id'] ?? null);

                $pdo->commit();
                $success = 'Cadet reverted from 2nd semester successfully.';

                // Refresh the 2nd sem results
                $sql = "SELECT cp.id AS cadet_profile_id, u.id AS user_id, u.username, u.email,
                               cp.student_id, cp.first_name, cp.middle_name, cp.last_name,
                               cp.course, cp.section, cp.gender, cp.contact_number
                        FROM cadet_enrollments ce
                        JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
                        JOIN users u ON cp.user_id = u.id
                        WHERE ce.school_year = ?
                          AND ce.semester = '2nd'
                          AND ce.enrollment_status = 'enrolled'
                        ORDER BY cp.last_name, cp.first_name";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$targetSy]);
                $secondSemResults = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            }
            catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to revert cadet: ' . $e->getMessage();
            }
        }
    }
    elseif ($action === 'load_cadet') {
        $cpid = (int)($_POST['cadet_profile_id'] ?? 0);
        if ($cpid > 0) {
            $stmt = $pdo->prepare("SELECT cp.*, u.id AS user_id, u.username, u.email FROM cadet_profiles cp JOIN users u ON cp.user_id = u.id WHERE cp.id = ? LIMIT 1");
            $stmt->execute([$cpid]);
            $selectedCadet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }
    elseif ($action === 'update_and_enroll') {
        $cpid = (int)($_POST['cadet_profile_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $targetSy = trim($_POST['target_sy'] ?? '');
        $targetSem = trim($_POST['target_sem'] ?? '2nd');
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($cpid <= 0 || $userId <= 0) {
            $errors[] = 'Invalid cadet selection.';
        }
        if ($targetSy === '') {
            $errors[] = 'Target School Year is required.';
        }
        if ($targetSem !== '2nd') {
            $errors[] = 'Only 2nd semester enrollment is allowed here.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Update username if provided
                $newUsername = trim($_POST['username'] ?? '');
                if ($newUsername !== '') {
                    // Check if username is already taken by another user
                    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $checkStmt->execute([$newUsername, $userId]);
                    if ($checkStmt->fetch()) {
                        $errors[] = 'Username is already taken by another user.';
                        $pdo->rollBack();
                        throw new Exception('Username already taken');
                    }

                    // Update username
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$newUsername, $userId]);
                }

                // Validate password confirmation
                $newPassword = (string)($_POST['new_password'] ?? '');
                $confirmPassword = (string)($_POST['confirm_password'] ?? '');
                if ($newPassword !== '' && $newPassword !== $confirmPassword) {
                    $errors[] = 'Password and confirmation do not match.';
                    $pdo->rollBack();
                    throw new Exception('Password confirmation does not match');
                }

                // Update profile basics (only a subset editable here for safety)
                $fields = [
                    'first_name' => trim($_POST['first_name'] ?? ''),
                    'middle_name' => trim($_POST['middle_name'] ?? ''),
                    'last_name' => trim($_POST['last_name'] ?? ''),
                    'student_id' => trim($_POST['student_id'] ?? ''),
                    'course' => trim($_POST['course'] ?? ''),
                    'section' => trim($_POST['section'] ?? ''),
                    'gender' => trim($_POST['gender'] ?? ''),
                    'contact_number' => trim($_POST['contact_number'] ?? ''),
                    'address' => trim($_POST['address'] ?? ''),
                    'region' => trim($_POST['region'] ?? ''),
                    'province_city' => trim($_POST['province_city'] ?? ''),
                    'birthdate' => trim($_POST['birthdate'] ?? ''),
                    'place_of_birth' => trim($_POST['place_of_birth'] ?? ''),
                    'height' => trim($_POST['height'] ?? ''),
                    'weight' => trim($_POST['weight'] ?? ''),
                    'skin_color' => trim($_POST['skin_color'] ?? ''),
                    'blood_type' => trim($_POST['blood_type'] ?? ''),
                    'father_name' => trim($_POST['father_name'] ?? ''),
                    'father_occupation' => trim($_POST['father_occupation'] ?? ''),
                    'mother_name' => trim($_POST['mother_name'] ?? ''),
                    'mother_occupation' => trim($_POST['mother_occupation'] ?? ''),
                    'guardian_name' => trim($_POST['guardian_name'] ?? ''),
                    'guardian_contact' => trim($_POST['guardian_contact'] ?? ''),
                    'guardian_relationship' => trim($_POST['guardian_relationship'] ?? ''),
                    'guardian_address' => trim($_POST['guardian_address'] ?? ''),
                ];
                $setParts = [];
                $params = [];
                foreach ($fields as $col => $val) {
                    $setParts[] = "$col = ?";
                    $params[] = $val;
                }
                $params[] = $cpid;
                $sql = 'UPDATE cadet_profiles SET ' . implode(', ', $setParts) . ' WHERE id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Enroll into target 2nd semester (admin source)
                $enrollStatus = get_cadet_enrollment_status($cpid, $targetSy, $targetSem);
                if ($enrollStatus !== 'enrolled') {
                    set_cadet_enrollment_status($cpid, $targetSy, $targetSem, 'enrolled', 'admin_second_sem', $_SESSION['user_id'] ?? null);
                }

                // Reset password if provided (no need for old password)
                if ($newPassword !== '') {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$passwordHash, $userId]);

                    // Mark in user_security that password was reset so login flow remains consistent
                    ensure_user_security_row($userId);
                    $stmt = $pdo->prepare("UPDATE user_security SET must_reset_password = 0 WHERE user_id = ?");
                    $stmt->execute([$userId]);

                    $logger->logSecurityEvent($_SESSION['user_id'], 'ADMIN_PASSWORD_RESET', 'Admin reset password for cadet (2nd sem enrollment)', ['target_user' => $userId], 'high');
                }

                $pdo->commit();
                $success = 'Cadet updated and enrolled in 2nd semester successfully.';
            }
            catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to update/enroll cadet: ' . $e->getMessage();
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2nd Semester Enrollment Admin</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard-redesigned.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #050816; color: #e9f3ff; }
        .page-shell { max-width: 1200px; margin: 30px auto; padding: 0 16px; }
        .card { background: rgba(15,23,42,0.98); border-radius: 16px; border: 1px solid rgba(148,163,184,0.35); box-shadow: 0 18px 45px rgba(15,23,42,0.85); padding: 20px 22px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .card-title { font-size: 1.1rem; font-weight: 600; }
        .badge-term { padding: 4px 10px; border-radius: 999px; background: rgba(56,189,248,0.15); border: 1px solid rgba(56,189,248,0.65); font-size: 0.85rem; }
        .grid { display: grid; grid-template-columns: 2fr 3fr; gap: 18px; }
        .form-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .form-control { width: 100%; padding: 8px 10px; border-radius: 10px; border: 1px solid rgba(148,163,184,0.5); background: rgba(15,23,42,0.9); color: #e9f3ff; font-size: 0.9rem; }
        .btn { border-radius: 999px; padding: 8px 14px; border: none; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: linear-gradient(135deg,#38bdf8,#6366f1); color: #0b1220; }
        .btn-secondary { background: rgba(148,163,184,0.15); color: #e5e7eb; border: 1px solid rgba(148,163,184,0.5); }
        .btn-back { background: rgba(107,114,128,0.2); color: #d1d5db; border: 1px solid rgba(107,114,128,0.4); }
        .btn-back:hover { background: rgba(107,114,128,0.3); }
        .btn-primary:hover { filter: brightness(1.05); }
        .table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .table th, .table td { padding: 8px 10px; border-bottom: 1px solid rgba(30,64,175,0.6); }
        .table th { text-align: left; font-weight: 600; color: #9ca3af; background: radial-gradient(circle at top, rgba(37,99,235,0.22), transparent 60%); }
        .alert { border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; font-size: 0.9rem; }
        .alert-error { border: 1px solid rgba(248,113,113,0.7); background: rgba(127,29,29,0.75); }
        .alert-success { border: 1px solid rgba(52,211,153,0.75); background: rgba(6,95,70,0.75); }
        label { font-size: 0.8rem; color: #9ca3af; margin-bottom: 4px; display: block; }
        .back-button-container { margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="page-shell">
    <!-- Back to Dashboard Button -->
    <div class="back-button-container">
        <a href="admin_dashboard.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title"><i class="fas fa-user-check"></i> 2nd Semester Enrollment Admin</div>
                <div style="font-size:0.85rem;color:#9ca3af;">Search cadets and manage semester enrollments. Enroll 1st sem cadets to 2nd sem, or revert 2nd sem cadets back to 1st sem.</div>
            </div>
            <div class="badge-term">
                Active Term: <?php echo htmlspecialchars(($term['school_year'] ?? '') . ' ' . ($term['semester'] ?? '')); ?>
            </div>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php
    endforeach; ?>
            </div>
        <?php
endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php
endif; ?>
        <form method="POST" style="margin-bottom:14px;">
            <input type="hidden" name="action" value="search">
            <div class="form-row">
                <div style="flex:0 0 200px;">
                    <label>School Year</label>
                    <select name="school_year" class="form-control">
                        <option value="">-- Select SY --</option>
                        <?php
if (!empty($availableSy)) {
    foreach ($availableSy as $optSy) {
        $selectedAttr = ($optSy === $searchSySelected) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($optSy) . '"' . $selectedAttr . '>' . htmlspecialchars($optSy) . '</option>';
    }
}
else {
    $year = (int)date('Y');
    $month = (int)date('n');
    $startYear = ($month >= 8) ? $year : ($year - 1);
    for ($off = 0; $off < 4; $off++) {
        $y = $startYear + $off;
        $optSy = $y . '-' . ($y + 1);
        $selectedAttr = ($optSy === $searchSySelected) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($optSy) . '"' . $selectedAttr . '>' . htmlspecialchars($optSy) . '</option>';
    }
}
?>
                    </select>
                </div>
                <div style="flex:0 0 180px;">
                    <label>Search Type</label>
                    <select name="search_type" class="form-control">
                        <option value="first_sem">1st Sem Enrolled</option>
                        <option value="second_sem">2nd Sem Enrolled</option>
                        <option value="not_enrolled_second_sem">Not Enrolled in 2nd Sem</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label>Search (Student ID / Name / Username)</label>
                    <input type="text" name="keyword" class="form-control" placeholder="e.g. 2023-0001 or Juan Dela Cruz">
                </div>
                <div style="display:flex; align-items:flex-end;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                </div>
            </div>
        </form>
        <?php if (!empty($searchResults)): ?>
            <h4 style="color:#38bdf8; margin-bottom:10px;"><i class="fas fa-users"></i> 1st Semester Enrolled Cadets</h4>
            <div style="max-height:260px; overflow:auto; margin-bottom:12px;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course / Section</th>
                        <th>Contact</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($searchResults as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                            <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(($row['course'] ?? '') . ' ' . ($row['section'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_number'] ?? ''); ?></td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="load_cadet">
                                    <input type="hidden" name="cadet_profile_id" value="<?php echo (int)$row['cadet_profile_id']; ?>">
                                    <button type="submit" class="btn btn-secondary"><i class="fas fa-pen"></i> Review & Enroll</button>
                                </form>
                            </td>
                        </tr>
                    <?php
    endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php
endif; ?>
        
        <?php if (!empty($notEnrolledSecondSemResults)): ?>
            <h4 style="color:#fbbf24; margin-bottom:10px;"><i class="fas fa-user-clock"></i> Not Yet Enrolled in 2nd Semester (<?php echo count($notEnrolledSecondSemResults); ?> cadets)</h4>
            <p style="font-size:0.85rem; color:#9ca3af; margin-bottom:10px;">These cadets are enrolled in 1st semester but have not been enrolled in 2nd semester yet.</p>
            <div style="max-height:400px; overflow:auto; margin-bottom:12px;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course / Section</th>
                        <th>Contact</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($notEnrolledSecondSemResults as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                            <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(($row['course'] ?? '') . ' ' . ($row['section'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_number'] ?? ''); ?></td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="load_cadet">
                                    <input type="hidden" name="cadet_profile_id" value="<?php echo (int)$row['cadet_profile_id']; ?>">
                                    <button type="submit" class="btn btn-secondary" style="background: rgba(251,191,36,0.2); border-color: rgba(251,191,36,0.5); color: #fbbf24;"><i class="fas fa-plus-circle"></i> Enroll to 2nd Sem</button>
                                </form>
                            </td>
                        </tr>
                    <?php
    endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php
endif; ?>
        
        <?php if (!empty($secondSemResults)): ?>
            <h4 style="color:#f87171; margin-bottom:10px;"><i class="fas fa-user-times"></i> 2nd Semester Enrolled Cadets (Can Revert)</h4>
            <div style="max-height:260px; overflow:auto; margin-bottom:12px;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course / Section</th>
                        <th>Contact</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($secondSemResults as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                            <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars(($row['course'] ?? '') . ' ' . ($row['section'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_number'] ?? ''); ?></td>
                            <td>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to revert this cadet from 2nd semester?');">
                                    <input type="hidden" name="action" value="revert_to_first_sem">
                                    <input type="hidden" name="cadet_profile_id" value="<?php echo (int)$row['cadet_profile_id']; ?>">
                                    <input type="hidden" name="target_sy" value="<?php echo htmlspecialchars($searchSySelected); ?>">
                                    <button type="submit" class="btn btn-secondary" style="background: rgba(248,113,113,0.2); border-color: rgba(248,113,113,0.5); color: #fca5a5;"><i class="fas fa-undo"></i> Revert to 1st Sem</button>
                                </form>
                            </td>
                        </tr>
                    <?php
    endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php
endif; ?>

        <?php if ($selectedCadet): ?>
            <hr style="border-color:rgba(31,41,55,0.9); margin:16px 0;">
            <form method="POST">
                <input type="hidden" name="action" value="update_and_enroll">
                <input type="hidden" name="cadet_profile_id" value="<?php echo (int)$selectedCadet['id']; ?>">
                <input type="hidden" name="user_id" value="<?php echo (int)$selectedCadet['user_id']; ?>">
                <div class="grid">
                    <div>
                        <h3 style="font-size:0.95rem; margin-bottom:8px;">Cadet Information</h3>
                        <div class="form-row">
                            <div>
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['first_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['middle_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Student ID</label>
                                <input type="text" name="student_id" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['student_id'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['username'] ?? ''); ?>" placeholder="Enter new username (optional)">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Course</label>
                                <input type="text" name="course" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['course'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Section</label>
                                <input type="text" name="section" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['section'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Gender</label>
                                <input type="text" name="gender" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['gender'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Contact Number</label>
                                <input type="text" name="contact_number" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['contact_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Facebook Profile</label>
                                <input type="text" name="facebook_profile" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['facebook_profile'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Address</label>
                                <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['address'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Region</label>
                                <input type="text" name="region" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['region'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>City / Province</label>
                                <input type="text" name="province_city" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['province_city'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Birthdate</label>
                                <input type="text" name="birthdate" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['birthdate'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Place of Birth</label>
                                <input type="text" name="place_of_birth" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['place_of_birth'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Height</label>
                                <input type="text" name="height" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['height'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Weight</label>
                                <input type="text" name="weight" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['weight'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Skin Color</label>
                                <input type="text" name="skin_color" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['skin_color'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Blood Type</label>
                                <input type="text" name="blood_type" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['blood_type'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Father's Name</label>
                                <input type="text" name="father_name" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['father_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Father's Occupation</label>
                                <input type="text" name="father_occupation" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['father_occupation'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Mother's Name</label>
                                <input type="text" name="mother_name" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['mother_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Mother's Occupation</label>
                                <input type="text" name="mother_occupation" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['mother_occupation'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Guardian Name</label>
                                <input type="text" name="guardian_name" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['guardian_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Guardian Contact</label>
                                <input type="text" name="guardian_contact" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['guardian_contact'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Guardian Relationship</label>
                                <input type="text" name="guardian_relationship" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['guardian_relationship'] ?? ''); ?>">
                            </div>
                            <div>
                                <label>Guardian Address</label>
                                <input type="text" name="guardian_address" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['guardian_address'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 style="font-size:0.95rem; margin-bottom:8px;">2nd Sem Enrollment & Security</h3>
                        <div class="form-row">
                            <div>
                                <label>Target School Year</label>
                                <input type="text" name="target_sy" class="form-control" value="<?php echo htmlspecialchars($sy); ?>">
                            </div>
                            <div>
                                <label>Semester</label>
                                <input type="text" name="target_sem" class="form-control" value="2nd" readonly>
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($selectedCadet['username'] ?? ''); ?>" placeholder="Enter new username (optional)">
                            </div>
                        </div>
                        <div class="form-row">
                            <div style="position: relative;">
                                <label>New Password (optional)</label>
                                <div style="position: relative;">
                                    <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Leave blank to keep current password">
                                    <button type="button" onclick="togglePassword('new_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer;" title="Show/Hide Password">
                                        <i class="fas fa-eye" id="new_password_toggle"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="position: relative;">
                                <label>Confirm Password</label>
                                <div style="position: relative;">
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password">
                                    <button type="button" onclick="togglePassword('confirm_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer;" title="Show/Hide Password">
                                        <i class="fas fa-eye" id="confirm_password_toggle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p style="font-size:0.8rem; color:#9ca3af; margin-top:4px;">If you enter a new password, it will immediately replace the cadet's old password. They will log in with this new password and then go through PIN / reenrollment flow as configured.</p>
                        <div style="margin-top:14px; display:flex; gap:10px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Save & Enroll to 2nd Sem</button>
                        </div>
                    </div>
                </div>
            </form>
            <script>
                function togglePassword(fieldId) {
                    const field = document.getElementById(fieldId);
                    const toggle = document.getElementById(fieldId + '_toggle');
                    
                    if (field.type === 'password') {
                        field.type = 'text';
                        toggle.classList.remove('fa-eye');
                        toggle.classList.add('fa-eye-slash');
                    } else {
                        field.type = 'password';
                        toggle.classList.remove('fa-eye-slash');
                        toggle.classList.add('fa-eye');
                    }
                }
                
                document.querySelector('form').addEventListener('submit', function(e) {
                    const newPass = document.getElementById('new_password').value;
                    const confirmPass = document.getElementById('confirm_password').value;
                    
                    if (newPass !== '' && newPass !== confirmPass) {
                        e.preventDefault();
                        alert('New password and confirmation do not match.');
                        return false;
                    }
                });
            </script>
        <?php
endif; ?>
    </div>
</div>
</body>
</html>
