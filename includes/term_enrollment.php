<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

function ensure_term_enrollment_schema() {
    global $pdo;
    if (!isset($pdo)) return;

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS academic_terms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_year TEXT NOT NULL,
            semester TEXT NOT NULL,
            start_date TEXT NULL,
            end_date TEXT NULL,
            is_current INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(school_year, semester)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cadet_enrollments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cadet_profile_id INTEGER NOT NULL,
            school_year TEXT NOT NULL,
            semester TEXT NOT NULL,
            enrollment_status TEXT NOT NULL DEFAULT 'pending_verification',
            enrolled_at TEXT NULL,
            verified_at TEXT NULL,
            dropped_at TEXT NULL,
            source TEXT NULL,
            last_reviewed_by INTEGER NULL,
            last_reviewed_at TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(cadet_profile_id, school_year, semester)
        )");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_security (
            user_id INTEGER PRIMARY KEY,
            pin_hash TEXT NULL,
            pin_last_changed_at TEXT NULL,
            must_reset_password INTEGER DEFAULT 0,
            must_set_pin INTEGER DEFAULT 0,
            failed_pin_attempts INTEGER DEFAULT 0,
            pin_locked_until TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS academic_terms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_year VARCHAR(20) NOT NULL,
            semester VARCHAR(10) NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_term (school_year, semester),
            KEY idx_is_current (is_current)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS cadet_enrollments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cadet_profile_id INT NOT NULL,
            school_year VARCHAR(20) NOT NULL,
            semester VARCHAR(10) NOT NULL,
            enrollment_status ENUM('enrolled','pending_verification','dropped') NOT NULL DEFAULT 'pending_verification',
            enrolled_at DATETIME NULL,
            verified_at DATETIME NULL,
            dropped_at DATETIME NULL,
            source VARCHAR(30) NULL,
            last_reviewed_by INT NULL,
            last_reviewed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cadet_term (cadet_profile_id, school_year, semester),
            KEY idx_term_status (school_year, semester, enrollment_status),
            KEY idx_cadet (cadet_profile_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_security (
            user_id INT PRIMARY KEY,
            pin_hash VARCHAR(255) NULL,
            pin_last_changed_at DATETIME NULL,
            must_reset_password TINYINT(1) NOT NULL DEFAULT 0,
            must_set_pin TINYINT(1) NOT NULL DEFAULT 0,
            failed_pin_attempts INT NOT NULL DEFAULT 0,
            pin_locked_until DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

function ensure_default_term() {
    global $pdo;
    ensure_term_enrollment_schema();
    if (!isset($pdo)) return;

    $stmt = $pdo->query("SELECT school_year, semester FROM academic_terms WHERE is_current = 1 LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($row) {
        bootstrap_enrollments_if_empty($row['school_year'], $row['semester']);
        return;
    }

    $year = (int)date('Y');
    $school_year = $year . '-' . ($year + 1);
    $semester = '1st';

    $pdo->exec("UPDATE academic_terms SET is_current = 0");
    $ins = $pdo->prepare("INSERT INTO academic_terms (school_year, semester, is_current) VALUES (?, ?, 1)");
    $ins->execute([$school_year, $semester]);

    bootstrap_enrollments_if_empty($school_year, $semester);
}

function bootstrap_enrollments_if_empty($school_year = null, $semester = null) {
    global $pdo;
    ensure_term_enrollment_schema();
    if (!isset($pdo)) return;

    try {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM cadet_enrollments");
        $count = (int)($countStmt ? $countStmt->fetchColumn() : 0);
        if ($count > 0) return;

        if (!$school_year || !$semester) {
            $stmt = $pdo->query("SELECT school_year, semester FROM academic_terms WHERE is_current = 1 LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$row) return;
            $school_year = $row['school_year'];
            $semester = $row['semester'];
        }

        $rows = $pdo->query("SELECT cp.id AS cadet_profile_id, u.id AS user_id FROM cadet_profiles cp JOIN users u ON cp.user_id = u.id WHERE u.approval_status = 'approved' AND u.status = 'active' AND u.role IN ('basic-cadet','basic_cadet','cadet','1cl','2cl') AND cp.status IN ('Active','active')");
        if (!$rows) return;
        $all = $rows->fetchAll(PDO::FETCH_ASSOC);

        $now = date('Y-m-d H:i:s');
        $ins = $pdo->prepare("INSERT INTO cadet_enrollments (cadet_profile_id, school_year, semester, enrollment_status, enrolled_at, verified_at, source, last_reviewed_by, last_reviewed_at) VALUES (?, ?, ?, 'enrolled', ?, ?, 'bootstrap', NULL, ?)");
        foreach ($all as $r) {
            try {
                $ins->execute([(int)$r['cadet_profile_id'], $school_year, $semester, $now, $now, $now]);
            } catch (Throwable $e) {
                // ignore duplicates
            }
        }
    } catch (Throwable $e) {
        return;
    }
}

function get_current_term() {
    global $pdo;
    ensure_default_term();
    if (!isset($pdo)) return ['school_year' => '', 'semester' => ''];

    $stmt = $pdo->query("SELECT school_year, semester FROM academic_terms WHERE is_current = 1 LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (!$row) return ['school_year' => '', 'semester' => ''];
    return ['school_year' => $row['school_year'], 'semester' => $row['semester']];
}

function get_active_term() {
    $sy = $_SESSION['active_school_year'] ?? null;
    $sem = $_SESSION['active_semester'] ?? null;

    if ($sy && $sem) {
        return ['school_year' => $sy, 'semester' => $sem];
    }

    $term = get_current_term();
    $_SESSION['active_school_year'] = $term['school_year'];
    $_SESSION['active_semester'] = $term['semester'];
    return $term;
}

function set_active_term($school_year, $semester) {
    $_SESSION['active_school_year'] = $school_year;
    $_SESSION['active_semester'] = $semester;
}

function get_all_terms() {
    global $pdo;
    ensure_default_term();
    if (!isset($pdo)) return [];

    // Ensure we have a reasonable catalog of terms: current SY + next 3, each with 1st and 2nd semester
    try {
        $stmt = $pdo->query("SELECT school_year, semester FROM academic_terms");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $existing = [];
        foreach ($rows as $r) {
            $sy = $r['school_year'] ?? '';
            $sem = $r['semester'] ?? '';
            if ($sy === '' || $sem === '') continue;
            $existing[$sy . '|' . $sem] = true;
        }

        $year = (int)date('Y');
        $month = (int)date('n');
        $startYear = ($month >= 8) ? $year : ($year - 1);

        $ins = $pdo->prepare("INSERT INTO academic_terms (school_year, semester, is_current) VALUES (?, ?, 0)");
        for ($offset = 0; $offset < 4; $offset++) {
            $y = $startYear + $offset;
            $sy = $y . '-' . ($y + 1);
            foreach (['1st', '2nd'] as $sem) {
                $key = $sy . '|' . $sem;
                if (!isset($existing[$key])) {
                    try {
                        $ins->execute([$sy, $sem]);
                    } catch (Throwable $e) {
                        // ignore duplicate or insert failures
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // ignore seeding errors and continue
    }

    try {
        $stmt = $pdo->query("SELECT school_year, semester FROM academic_terms ORDER BY school_year DESC, CASE semester WHEN '1st' THEN 1 WHEN '2nd' THEN 2 ELSE 3 END ASC");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        return [];
    }
}

function get_user_security($user_id) {
    global $pdo;
    ensure_user_security_row($user_id);
    if (!isset($pdo)) return null;

    try {
        $stmt = $pdo->prepare("SELECT user_id, pin_hash, pin_last_changed_at, must_reset_password, must_set_pin, failed_pin_attempts, pin_locked_until FROM user_security WHERE user_id = ? LIMIT 1");
        $stmt->execute([(int)$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function enroll_user_into_current_term($user_id, $reviewed_by = null, $source = 'registration') {
    global $pdo;
    ensure_term_enrollment_schema();
    if (!isset($pdo)) return false;

    $term = get_current_term();
    if (($term['school_year'] ?? '') === '' || ($term['semester'] ?? '') === '') return false;

    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$user_id]);
    $cadetProfileId = $stmt->fetchColumn();
    if (!$cadetProfileId) return false;

    set_cadet_enrollment_status((int)$cadetProfileId, $term['school_year'], $term['semester'], 'enrolled', $source, $reviewed_by);
    return true;
}

function ensure_user_security_row($user_id) {
    global $pdo;
    ensure_term_enrollment_schema();
    if (!isset($pdo)) return;

    $stmt = $pdo->prepare("SELECT user_id FROM user_security WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$user_id]);
    if ($stmt->fetch()) return;

    $ins = $pdo->prepare("INSERT INTO user_security (user_id, must_reset_password, must_set_pin, failed_pin_attempts) VALUES (?, 0, 0, 0)");
    $ins->execute([(int)$user_id]);
}

function get_user_pin_hash($user_id) {
    global $pdo;
    ensure_user_security_row($user_id);
    if (!isset($pdo)) return null;

    $stmt = $pdo->prepare("SELECT pin_hash FROM user_security WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? ($row['pin_hash'] ?? null) : null;
}

function is_pin_locked($user_id) {
    global $pdo;
    ensure_user_security_row($user_id);
    if (!isset($pdo)) return [false, null];

    $stmt = $pdo->prepare("SELECT pin_locked_until FROM user_security WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $until = $row ? ($row['pin_locked_until'] ?? null) : null;

    if (!$until) return [false, null];
    $ts = strtotime($until);
    if ($ts !== false && $ts > time()) {
        return [true, $until];
    }

    $upd = $pdo->prepare("UPDATE user_security SET pin_locked_until = NULL, failed_pin_attempts = 0 WHERE user_id = ?");
    $upd->execute([(int)$user_id]);
    return [false, null];
}

function record_failed_pin_attempt($user_id, $max_attempts = 5, $lock_minutes = 15) {
    global $pdo;
    ensure_user_security_row($user_id);
    if (!isset($pdo)) return;

    $stmt = $pdo->prepare("SELECT failed_pin_attempts FROM user_security WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $attempts = (int)($row['failed_pin_attempts'] ?? 0);
    $attempts++;

    $lockedUntil = null;
    if ($attempts >= $max_attempts) {
        $lockedUntil = date('Y-m-d H:i:s', strtotime('+' . (int)$lock_minutes . ' minutes'));
    }

    $upd = $pdo->prepare("UPDATE user_security SET failed_pin_attempts = ?, pin_locked_until = ? WHERE user_id = ?");
    $upd->execute([$attempts, $lockedUntil, (int)$user_id]);
}

function reset_pin_attempts($user_id) {
    global $pdo;
    ensure_user_security_row($user_id);
    if (!isset($pdo)) return;

    $upd = $pdo->prepare("UPDATE user_security SET failed_pin_attempts = 0, pin_locked_until = NULL WHERE user_id = ?");
    $upd->execute([(int)$user_id]);
}

function set_user_pin($user_id, $pin) {
    global $pdo;
    ensure_user_security_row($user_id);
    if (!isset($pdo)) return;

    $hash = password_hash($pin, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE user_security SET pin_hash = ?, pin_last_changed_at = ?, must_set_pin = 0, failed_pin_attempts = 0, pin_locked_until = NULL WHERE user_id = ?");
    $upd->execute([$hash, date('Y-m-d H:i:s'), (int)$user_id]);
}

function get_cadet_enrollment_status($cadet_profile_id, $school_year, $semester) {
    global $pdo;
    ensure_term_enrollment_schema();
    if (!isset($pdo)) return null;

    $stmt = $pdo->prepare("SELECT enrollment_status FROM cadet_enrollments WHERE cadet_profile_id = ? AND school_year = ? AND semester = ? LIMIT 1");
    $stmt->execute([(int)$cadet_profile_id, $school_year, $semester]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? ($row['enrollment_status'] ?? null) : null;
}

function set_cadet_enrollment_status($cadet_profile_id, $school_year, $semester, $status, $source = null, $reviewed_by = null) {
    global $pdo;
    ensure_term_enrollment_schema();
    if (!isset($pdo)) return;

    $existing = get_cadet_enrollment_status($cadet_profile_id, $school_year, $semester);
    $now = date('Y-m-d H:i:s');

    if ($existing === null) {
        $ins = $pdo->prepare("INSERT INTO cadet_enrollments (cadet_profile_id, school_year, semester, enrollment_status, enrolled_at, verified_at, dropped_at, source, last_reviewed_by, last_reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $enrolled_at = ($status === 'enrolled') ? $now : null;
        $verified_at = ($status === 'enrolled') ? $now : null;
        $dropped_at = ($status === 'dropped') ? $now : null;
        $ins->execute([(int)$cadet_profile_id, $school_year, $semester, $status, $enrolled_at, $verified_at, $dropped_at, $source, $reviewed_by, $now]);
        return;
    }

    $enrolled_at = null;
    $verified_at = null;
    $dropped_at = null;
    if ($status === 'enrolled') {
        $enrolled_at = $now;
        $verified_at = $now;
    }
    if ($status === 'dropped') {
        $dropped_at = $now;
    }

    $upd = $pdo->prepare("UPDATE cadet_enrollments SET enrollment_status = ?, enrolled_at = COALESCE(?, enrolled_at), verified_at = COALESCE(?, verified_at), dropped_at = COALESCE(?, dropped_at), source = COALESCE(?, source), last_reviewed_by = COALESCE(?, last_reviewed_by), last_reviewed_at = ? WHERE cadet_profile_id = ? AND school_year = ? AND semester = ?");
    $upd->execute([$status, $enrolled_at, $verified_at, $dropped_at, $source, $reviewed_by, $now, (int)$cadet_profile_id, $school_year, $semester]);
}
