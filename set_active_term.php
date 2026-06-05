<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/term_enrollment.php';

check_login();
ensure_term_enrollment_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'admin_dashboard.php'));
    exit;
}

$school_year = trim($_POST['school_year'] ?? '');
$semester = trim($_POST['semester'] ?? '');

$term_key = trim($_POST['term_key'] ?? '');
if (($school_year === '' || $semester === '') && $term_key !== '' && strpos($term_key, '|') !== false) {
    $parts = explode('|', $term_key, 2);
    $school_year = trim($parts[0] ?? '');
    $semester = trim($parts[1] ?? '');
}

if ($school_year === '' || $semester === '') {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'admin_dashboard.php'));
    exit;
}

// Validate term exists; if not, only admins can create it.
$ok = false;
if (isset($pdo)) {
    $stmt = $pdo->prepare("SELECT id FROM academic_terms WHERE school_year = ? AND semester = ? LIMIT 1");
    $stmt->execute([$school_year, $semester]);
    $ok = (bool)$stmt->fetchColumn();

    if (!$ok && (($_SESSION['role'] ?? '') === 'admin')) {
        $ins = $pdo->prepare("INSERT INTO academic_terms (school_year, semester, is_current) VALUES (?, ?, 0)");
        try { $ins->execute([$school_year, $semester]); $ok = true; } catch (Throwable $e) { $ok = false; }
    }
}

if ($ok) {
    set_active_term($school_year, $semester);
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'admin_dashboard.php'));
exit;
