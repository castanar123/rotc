<?php
    require_once '../includes/session.php';
    require_once '../includes/db.php';
    check_login();

// Admin only for now
if (!isset($_SESSION['loggedin']) || !rotc_role_in(['admin'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit;
}

function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (is_array($payload) && isset($payload['action'])) {
            header('Content-Type: application/json');

            $platoonCol = columnExists($pdo, 'cadet_profiles', 'platoon') ? 'platoon' : (columnExists($pdo, 'cadet_profiles', 'section') ? 'section' : null);
            $studentCol = columnExists($pdo, 'cadet_profiles', 'student_id') ? 'student_id' : null;

            if ($payload['action'] === 'bulk_update_platoon') {
                if (!$platoonCol) {
                    echo json_encode(['success' => false, 'message' => 'No platoon/section column found in cadet_profiles.']);
                    exit;
                }
                $platoon = trim((string)($payload['platoon'] ?? ''));
                $idsRaw = $payload['ids'] ?? [];
                $ids = array_values(array_filter(array_map('intval', is_array($idsRaw) ? $idsRaw : [])));
                if ($platoon === '' || empty($ids)) {
                    echo json_encode(['success' => false, 'message' => 'Missing platoon or selected IDs.']);
                    exit;
                }
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE cadet_profiles SET `$platoonCol` = ? WHERE id = ?");
                    $updated = 0;
                    foreach ($ids as $id) {
                        if ($id <= 0) continue;
                        if ($stmt->execute([$platoon, $id])) $updated++;
                    }
                    $pdo->commit();
                    echo json_encode(['success' => true, 'updated' => $updated]);
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
                    exit;
                }
            }

            if ($payload['action'] === 'bulk_update_cadets') {
                if (!$platoonCol && !$studentCol) {
                    echo json_encode(['success' => false, 'message' => 'No editable columns found in cadet_profiles.']);
                    exit;
                }
                $cadets = $payload['cadets'] ?? [];
                if (!is_array($cadets) || empty($cadets)) {
                    echo json_encode(['success' => false, 'message' => 'No cadets provided.']);
                    exit;
                }

                try {
                    $pdo->beginTransaction();
                    $updated = 0;
                    foreach ($cadets as $c) {
                        if (!is_array($c)) continue;
                        $id = (int)($c['id'] ?? 0);
                        if ($id <= 0) continue;

                        $sets = [];
                        $params = [];

                        if ($studentCol) {
                            $sid = trim((string)($c['student_id'] ?? ''));
                            $sets[] = "`$studentCol` = ?";
                            $params[] = $sid;
                        }
                        if ($platoonCol) {
                            $pl = trim((string)($c['platoon'] ?? ''));
                            $sets[] = "`$platoonCol` = ?";
                            $params[] = $pl;
                        }
                        if (empty($sets)) continue;
                        $params[] = $id;
                        $sql = "UPDATE cadet_profiles SET " . implode(', ', $sets) . " WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        if ($stmt->execute($params)) $updated++;
                    }
                    $pdo->commit();
                    echo json_encode(['success' => true, 'updated' => $updated]);
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
                    exit;
                }
            }

            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            exit;
        }
    }
}

// Selected IDs from POST (batch mode)
$selected_ids = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_ids = isset($_POST['selected_ids']) ? array_map('intval', (array)$_POST['selected_ids']) : [];
}

$auto_print = 0;
$print_mode = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auto_print = isset($_POST['auto_print']) ? (int)$_POST['auto_print'] : 0;
    $print_mode = isset($_POST['print_mode']) ? (string)$_POST['print_mode'] : '';
}

$cadet_profile = null;
$cadet_profile_id = isset($_GET['cadet_profile_id']) ? (int)$_GET['cadet_profile_id'] : 0;

if ($cadet_profile_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT cp.*, u.id as user_id FROM cadet_profiles cp LEFT JOIN users u ON u.id = cp.user_id WHERE cp.id = ? LIMIT 1");
        $stmt->execute([$cadet_profile_id]);
        $cadet_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cadet_profile = null;
        $error_message = 'DB error loading cadet profile: ' . $e->getMessage();
    }
}

// For single preview defaults
$last_name = $cadet_profile['last_name'] ?? '';
$first_name = $cadet_profile['first_name'] ?? '';
$platoon    = $cadet_profile['platoon'] ?? ($cadet_profile['section'] ?? '');
$student_id = $cadet_profile['student_id'] ?? '';

// Normalize platoon names
$platoon = strtoupper(trim((string)$platoon));
if ($platoon === 'OFFICE PERSONEL') $platoon = 'OFFICE PERSONNEL';

// Load approved+active cadets list for selection
$cadet_list = [];
try {
    $stmt = $pdo->query("SELECT cp.id, cp.first_name, cp.last_name, cp.student_id, COALESCE(cp.platoon, cp.section) as platoon
                         FROM cadet_profiles cp
                         JOIN users u ON u.id = cp.user_id
                         WHERE u.approval_status='approved' AND u.status='active'
                         ORDER BY cp.last_name ASC, cp.first_name ASC");
    $cadet_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = ($error_message ?? '') . ' | Load cadet list failed: ' . $e->getMessage();
}

// If batch selected, fetch those profiles
$batch_profiles = [];
if (!empty($selected_ids)) {
    try {
        $in = implode(',', array_fill(0, count($selected_ids), '?'));
        $stmt = $pdo->prepare("SELECT cp.id, cp.first_name, cp.last_name, cp.student_id, COALESCE(cp.platoon, cp.section) as platoon
                               FROM cadet_profiles cp WHERE cp.id IN ($in)");
        $stmt->execute($selected_ids);
        $batch_profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Keep order as selected
        $byId = [];
        foreach ($batch_profiles as $bp) { $byId[(int)$bp['id']] = $bp; }
        $ordered = [];
        foreach ($selected_ids as $sid) { if (isset($byId[$sid])) $ordered[] = $byId[$sid]; }
        $batch_profiles = $ordered;
    } catch (PDOException $e) {
        $error_message = ($error_message ?? '') . ' | Load batch failed: ' . $e->getMessage();
    }
}

?>
<?php
// Watermark config and upload handling
$wm_config_path = __DIR__ . '/id_watermark.json';
$wm_url = '';
$wm_opacity = 0.3;
if (file_exists($wm_config_path)) {
    $cfg = json_decode(@file_get_contents($wm_config_path), true);
    if (is_array($cfg)) {
        $wm_url = isset($cfg['url']) ? (string)$cfg['url'] : '';
        if (isset($cfg['opacity'])) $wm_opacity = (float)$cfg['opacity'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_watermark'])) {
    $wm_opacity = isset($_POST['watermark_opacity']) ? max(0.0, min(1.0, (float)$_POST['watermark_opacity'])) : $wm_opacity;
    if (isset($_FILES['watermark_file']) && isset($_FILES['watermark_file']['error']) && $_FILES['watermark_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['watermark_file']['tmp_name'];
        $name = preg_replace('/[^a-zA-Z0-9._-]/','_', $_FILES['watermark_file']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','webp','gif','svg'])) {
            $uploadsBase = __DIR__ . '/../uploads';
            if (!is_dir($uploadsBase)) { @mkdir($uploadsBase, 0777, true); }
            $wmDir = $uploadsBase . '/id_watermarks';
            if (!is_dir($wmDir)) { @mkdir($wmDir, 0777, true); }
            $newName = 'wm_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
            $dest = $wmDir . '/' . $newName;
            if (@move_uploaded_file($tmp, $dest)) {
                $wm_url = '../uploads/id_watermarks/' . $newName;
                $_SESSION['wm_url_current'] = $wm_url;
                $_SESSION['wm_opacity_current'] = $wm_opacity;
                if (isset($_POST['set_default']) && $_POST['set_default'] === 'on') {
                    @file_put_contents($wm_config_path, json_encode(['url'=>$wm_url,'opacity'=>$wm_opacity], JSON_PRETTY_PRINT));
                }
            }
        }
    } else {
        if (isset($_SESSION['wm_url_current'])) {
            $wm_url = $_SESSION['wm_url_current'];
            $_SESSION['wm_opacity_current'] = $wm_opacity;
        }
        if (isset($_POST['set_default']) && $_POST['set_default'] === 'on') {
            @file_put_contents($wm_config_path, json_encode(['url'=>$wm_url,'opacity'=>$wm_opacity], JSON_PRETTY_PRINT));
        }
    }
}
if (isset($_SESSION['wm_url_current'])) $wm_url = $_SESSION['wm_url_current'];
if (isset($_SESSION['wm_opacity_current'])) $wm_opacity = (float)$_SESSION['wm_opacity_current'];
?>
<?php
// Header logos (left, center, right) config and upload handling
$hdr_cfg_path = __DIR__ . '/id_header_logos.json';
$logo_left_url = '';
$logo_center_url = '';
$logo_right_url = '';
if (file_exists($hdr_cfg_path)) {
    $hcfg = json_decode(@file_get_contents($hdr_cfg_path), true);
    if (is_array($hcfg)) {
        $logo_left_url = isset($hcfg['left']) ? (string)$hcfg['left'] : '';
        $logo_center_url = isset($hcfg['center']) ? (string)$hcfg['center'] : '';
        $logo_right_url = isset($hcfg['right']) ? (string)$hcfg['right'] : '';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_header_logos'])) {
    $uploadsBase = __DIR__ . '/../uploads';
    if (!is_dir($uploadsBase)) { @mkdir($uploadsBase, 0777, true); }
    $hdrDir = $uploadsBase . '/id_header_logos';
    if (!is_dir($hdrDir)) { @mkdir($hdrDir, 0777, true); }
    $map = [
        'logo_left' => 'left',
        'logo_center' => 'center',
        'logo_right' => 'right'
    ];
    foreach ($map as $input => $slot) {
        if (isset($_FILES[$input]) && isset($_FILES[$input]['error']) && $_FILES[$input]['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES[$input]['tmp_name'];
            $name = preg_replace('/[^a-zA-Z0-9._-]/','_', $_FILES[$input]['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','webp','gif','svg'])) {
                $newName = 'hdr_' . $slot . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                $dest = $hdrDir . '/' . $newName;
                if (@move_uploaded_file($tmp, $dest)) {
                    $rel = '../uploads/id_header_logos/' . $newName;
                    if ($slot === 'left') { $logo_left_url = $rel; $_SESSION['logo_left_url'] = $rel; }
                    if ($slot === 'center') { $logo_center_url = $rel; $_SESSION['logo_center_url'] = $rel; }
                    if ($slot === 'right') { $logo_right_url = $rel; $_SESSION['logo_right_url'] = $rel; }
                }
            }
        }
    }
    if (isset($_POST['set_default_header']) && $_POST['set_default_header'] === 'on') {
        @file_put_contents($hdr_cfg_path, json_encode([
            'left' => $logo_left_url,
            'center' => $logo_center_url,
            'right' => $logo_right_url
        ], JSON_PRETTY_PRINT));
    }
}
if (isset($_SESSION['logo_left_url'])) $logo_left_url = $_SESSION['logo_left_url'];
if (isset($_SESSION['logo_center_url'])) $logo_center_url = $_SESSION['logo_center_url'];
if (isset($_SESSION['logo_right_url'])) $logo_right_url = $_SESSION['logo_right_url'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Generate ID Card - ROTC</title>
    <link rel="stylesheet" href="../css/tactical-theme.css">
    <link rel="stylesheet" href="../css/dashboard-redesigned.css">
    <link rel="stylesheet" href="../css/mobile-responsive.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <!-- Attendance QR config (permanent key, settings) -->
    <script src="../QR/config.js"></script>
    <style>
        :root {
            --id-width: 1011px;
            --id-height: 639px;
        }
        /* Scope page styling to ID Card area to avoid clashing with dashboard nav */
        #idCardApp { /* inherit dashboard theme */ }
        #idCardApp .container { max-width: 1400px; margin: 20px auto; padding: 0 16px; }
        .controls { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 16px; }
        .controls .form-group { display: flex; flex-direction: column; gap: 6px; }
        .preview-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start; }
        .card-canvas { background: #ffffff; width: var(--id-width); height: var(--id-height); position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 8px 40px rgba(0,0,0,.5); margin: 0 auto; }
        .card-title { font-weight: 700; }
        .toolbar { display:flex; gap: 8px; margin: 16px 0; }
        .id-front { position: relative; width: 100%; height: 100%; }
        /* Style A: Topbar */
        .style-topbar .topbar { position:absolute; left:0; top:0; width:100%; height: 110px; background: var(--accent,#0ea5e9); color: #fff; display:flex; align-items:center; padding: 0 28px; justify-content: space-between; }
        .style-topbar .unit-title { font-size: 34px; letter-spacing: .04em; font-weight: 700; }
        .style-topbar .platoon-badge { position:absolute; left: 28px; top: -24px; width: 96px; height: 96px; background:#fff; color:#000; border-radius: 50%; display:flex; align-items:center; justify-content:center; font-size: 42px; font-weight: 800; box-shadow: 0 4px 12px rgba(0,0,0,.25); }
        .style-topbar .lastname { position:absolute; left: 40px; right: 40px; top: 150px; text-align:center; font-size: 144px; font-weight: 900; color:#111; letter-spacing: .06em; }
        .style-topbar .bottombar { position:absolute; left:0; bottom:0; width:100%; height: 80px; background: var(--accent,#0ea5e9); }
        
        /* Style B: Border */
        .style-border .frame { position:absolute; inset: 18px; border: 22px solid var(--accent,#ef4444); border-radius: 8px; }
        .style-border .lastname { position:absolute; left: 40px; right: 40px; top: 220px; text-align:center; font-size: 144px; font-weight: 900; color:#111; letter-spacing: .06em; }

        /* Style C: Clean (side stripe + watermark) */
        .style-clean .side-stripe { position:absolute; left:0; top:0; bottom:0; width: 80px; background: var(--accent,#0ea5e9); }
        .style-clean .unit-title { position:absolute; top: 18px; left: 100px; font-size: 28px; font-weight: 800; letter-spacing: .04em; color:#111; }
        .style-clean .lastname { position:absolute; left: 100px; right: 40px; top: 240px; text-align:center; font-size: 144px; font-weight: 900; color:#111; letter-spacing:.04em; z-index: 2; }
        .style-clean .watermark { position:absolute; inset:0; background-repeat:no-repeat; background-position: center; background-size: 55%; opacity: var(--wm-opacity, 0.3); z-index:1; pointer-events:none; }

        /* Style D: Template (blank) to match provided design */
        .style-template .outer-border { position:absolute; inset: 0; border: 24px solid var(--accent,#22c55e); border-radius: 8px; }
        .style-template .header-row { position:absolute; left: 28px; right: 28px; top: 10px; height: 90px; }
        .style-template .hlogo { position:absolute; width: 74px; height: 74px; background-size: contain; background-position: center; background-repeat: no-repeat; border-radius: 50%; }
        .style-template .hlogo.left { left: 0; top: 8px; }
        .style-template .hlogo.center { left: 50%; top: 0; transform: translateX(-50%); width: 80px; height: 80px; }
        .style-template .hlogo.right { right: 0; top: 8px; }
        .style-template .thin-line { position:absolute; left: 40px; right: 40px; top: 120px; height: 6px; background: var(--accent,#22c55e); border-radius: 3px; }
        .style-template .center-band { position:absolute; left: 24px; right: 24px; top: calc(50% - 10px); height: 220px; background: var(--accent,#22c55e); border-radius: 4px; opacity: 0.95; z-index: 1; }
        .style-template .bottom-whitebar { position:absolute; left: 24px; right: 24px; bottom: 28px; height: 70px; background: #ffffff; border-radius: 4px; }
        .style-template .watermark { position:absolute; inset: 0; background-repeat: no-repeat; background-position: center; background-size: 85%; opacity: var(--wm-opacity, 0.3); pointer-events:none; z-index: 0; }
        /* Template text overlays */
        .style-template .template-header-text { position:absolute; left: 28px; right: 28px; top: 44px; color:#000; text-align:center; font-weight:700; line-height:1.2; font-size: 19px; text-transform: uppercase; letter-spacing:.02em; z-index: 2; }
        .style-template .template-subtitle { position:absolute; left: 24px; right: 24px; top: 180px; color:#111; text-align:center; font-weight:700; font-size: 64px; }
        .style-template .template-subtitle { z-index: 2; }
        .style-template .template-lastname { position:absolute; left: 24px; right: 24px; top: calc(50% + 100px); transform: translateY(-50%); color:#fff; text-align:center; font-weight:900; font-size: 180px; letter-spacing:.02em; text-transform: uppercase; z-index: 2; opacity: 1; mix-blend-mode: normal; white-space: nowrap; overflow: hidden; }
        .style-template .template-platoon { position:absolute; left: 24px; right: 24px; bottom: 36px; color:#000; text-align:center; font-weight:800; font-size: 40px; z-index: 2; }

        .id-back { background:#fff; border-radius:8px; padding: 24px; display:flex; gap: 24px; align-items:center; justify-content:center; height: var(--id-height); box-shadow: 0 8px 40px rgba(0,0,0,.5); }
        .qr-box { background:#fff; border: 1px dashed #cbd5e1; padding: 6px; border-radius: 8px; display:flex; flex-direction: column; align-items:center; gap: 4px; }
        .qr-box .qr-inbox-label { text-align:center; font-size: 12px; line-height: 1.2; color:#111827; }
        .qr-box .qr-inbox-label .sid { font-size: 11px; color:#334155; }
        .qr-label { color:#111827; font-weight:600; text-align:center; margin-top:8px; }
        .id-back .qr-label { display:none; }
        .meta { color:#111827; }
        .meta .row { margin: 4px 0; }
        .accent-preview { width: 28px; height: 28px; border-radius:4px; border:1px solid #cbd5e1; display:inline-block; vertical-align:middle; margin-left:8px; }
        .grid-two { display:grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .hint { color:#94a3b8; font-size: 12px; }

        /* Page header */
        .page-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; padding: 4px 0 18px; border-bottom: 1px solid #1f2937; margin-bottom: 18px; }
        .page-title { font-size: 22px; font-weight: 700; margin: 0; color: #34d399; }
        .page-subtitle { margin-top: 6px; color: #94a3b8; font-size: 13px; }

        /* Section cards */
        .section-card { background: #0b1119; border:1px solid #1f2937; border-radius:14px; padding:0; margin: 16px 0; overflow:hidden; box-shadow: 0 14px 32px rgba(0,0,0,.35); }
        .section-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding: 14px 18px; background: linear-gradient(180deg, rgba(15,23,42,.95), rgba(11,17,25,.75)); border-bottom:1px solid #1f2937; flex-wrap:wrap; }
        .section-title { display:flex; align-items:center; gap:12px; }
        .section-title h3 { margin:0; font-size:15px; font-weight:700; color:#e2e8f0; }
        .section-icon { width:36px; height:36px; border-radius:10px; background: rgba(14,165,233,.15); display:flex; align-items:center; justify-content:center; color:#67e8f9; font-size:16px; }
        .section-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .section-body { padding: 16px 18px; display:flex; flex-direction:column; gap:14px; background:#0b1119; }
        .section-hint { color:#94a3b8; font-size: 12px; }
        .section-tag { padding: 4px 10px; border-radius: 999px; background: rgba(15,118,110,.2); color: #5eead4; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; }
        .section-label { font-size: 11px; letter-spacing: .08em; color:#94a3b8; text-transform: uppercase; }
        .section-toggle { border:1px solid #1f2937; background:#0b1220; color:#94a3b8; border-radius:8px; padding:6px 8px; transition: transform .2s ease; }
        .section-toggle i { font-size: 12px; transition: transform .2s ease; }
        .section-card.collapsed .section-body { display: none; }
        .section-card.collapsed .section-toggle i { transform: rotate(-90deg); }

        .toggle-inline { position: relative; display:inline-flex; align-items:center; width:48px; height:26px; cursor:pointer; }
        .toggle-inline input { display:none; }
        .toggle-inline .toggle-track { width:100%; height:100%; border-radius:999px; background:#0b1119; border:1px solid #1f2937; position:relative; transition: background .2s ease; }
        .toggle-inline .toggle-track::before { content:''; width:20px; height:20px; border-radius:999px; background:#e2e8f0; position:absolute; top:2px; left:2px; transition: transform .2s ease, background .2s ease; }
        .toggle-inline input:checked + .toggle-track { background:#14b8a6; border-color:#14b8a6; }
        .toggle-inline input:checked + .toggle-track::before { transform: translateX(20px); background:#0f172a; }
        .accent-inline { display:flex; align-items:center; gap:8px; }

        /* Selector UI aligned with dashboard theme */
        .selector { background: transparent; padding:0; margin: 0; }
        .selector .list { max-height: 360px; overflow:auto; border:1px solid #1f2937; border-radius:12px; background:#0b1119; }
        .selector table { width:100%; border-collapse:collapse; }
        .selector tbody tr { cursor: pointer; transition: background-color 0.2s; }
        .selector tbody tr:hover { background-color: rgba(15,23,42,0.6); }
        .selector tbody tr.selected { background-color: rgba(14,165,233,.18); }
        .selector .pick { cursor: pointer; }
        .selector th, .selector td { padding:12px 12px; border-bottom:1px solid #1f2937; font-size: 13px; color:#e2e8f0; }
        .selector th { position: sticky; top:0; background: #0f172a; z-index:1; text-transform: uppercase; letter-spacing: .12em; font-size: 10px; color:#94a3b8; }
        .selector .actions { margin-top: 10px; display:flex; gap:10px; justify-content:flex-end; }

        .filter-row { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
        .filter-row .search { flex:1; min-width:200px; position:relative; }
        .filter-row .search input { width:100%; background:#0b1119; border:1px solid #1f2937; border-radius:8px; padding:10px 14px; color:#e2e8f0; font-size:13px; }
        .filter-row .search::before { content:'\f002'; font-family:'Font Awesome 6 Free'; font-weight:900; position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#475569; pointer-events:none; z-index:1; }
        .filter-row .search input { padding-left:38px; }
        .filter-row select { background:#0b1119; border:1px solid #1f2937; border-radius:8px; padding:10px 14px; color:#e2e8f0; font-size:13px; min-width:160px; }
        .platoon-counts { margin-left:12px; font-size:12px; color:#94a3b8; }
        .platoon-counts strong { color:#2dd4bf; }

        .action-panel,
        .print-panel { background: #0f1623; border:1px solid #1f2937; border-radius: 12px; padding: 14px; display:flex; flex-wrap:wrap; gap:18px; align-items:flex-start; }
        .panel-collapsible { border:1px solid #1f2937; border-radius: 12px; background:#0f1623; overflow:hidden; }
        .panel-header { display:flex; align-items:center; justify-content:space-between; padding:10px 12px; border-bottom:1px solid #1f2937; }
        .panel-toggle { border:1px solid #1f2937; background:#0b1220; color:#94a3b8; border-radius:8px; padding:4px 6px; }
        .panel-toggle i { font-size: 12px; transition: transform .2s ease; }
        .panel-collapsible.collapsed .print-panel { display:none; }
        .panel-collapsible.collapsed .panel-toggle i { transform: rotate(-90deg); }
        .action-group { display:flex; flex-direction:column; gap:8px; min-width: 160px; padding-right: 16px; border-right: 1px solid #1f2937; }
        .action-panel .action-group:last-child,
        .print-panel .action-group:last-child { border-right: none; padding-right: 0; }
        .action-group .group-label { font-size: 10px; letter-spacing: .14em; color:#94a3b8; text-transform: uppercase; }
        .action-group .btn-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }

        .qr-action-btn { background: #0f172a; color:#e2e8f0; border:1px solid #1f2937; border-radius:10px; padding:8px 14px; font-size: 12px; font-weight: 600; display:inline-flex; align-items:center; gap:8px; transition: all .2s ease; }
        .qr-action-btn:hover { border-color:#334155; background:#111827; }
        .qr-action-btn.secondary { background:#0b1119; color:#e2e8f0; }
        .qr-action-btn.info { background:#0b1119; color:#93c5fd; border-color:#1f2937; }
        .qr-action-btn.primary { background:#2dd4bf; border-color:#2dd4bf; color:#0f172a; }

        /* Batch options spacing */
        #batchOptions .list { padding: 14px 16px; }
        #batchOptions table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 12px 6px; }
        #batchOptions th { padding-bottom: 4px; color: #94a3b8; font-size: 10px; text-transform: uppercase; letter-spacing: .12em; }
        #batchOptions td { padding: 2px 4px; }
        #batchOptions th:nth-child(1),
        #batchOptions td:nth-child(1),
        #batchOptions th:nth-child(3),
        #batchOptions td:nth-child(3) { width: 38%; text-align: left; }
        #batchOptions th:nth-child(2),
        #batchOptions td:nth-child(2),
        #batchOptions th:nth-child(4),
        #batchOptions td:nth-child(4) { width: 12%; text-align: center; }
        #batchOptions td:first-child,
        #batchOptions td:nth-child(3) { color:#e2e8f0; font-size: 12px; letter-spacing: .02em; }
        #batchOptions td:nth-child(2),
        #batchOptions td:nth-child(4) { display: flex; justify-content: center; }
        #batchOptions .platoon-color-input { width: 46px; height: 24px; border-radius: 6px; border:1px solid #1f2937; background: #0b1119; }

        /* Responsive styles */
        @media (max-width: 768px) {
            .section-header { flex-direction: column; align-items: flex-start; }
            .section-right { width: 100%; }
            .filter-row .search { min-width: auto; width: 100%; }
            .selector input[type="text"],
            .selector select { font-size: 16px; padding: 14px 10px; min-height: 48px; }
        }

        /* Batch grid (10 per page, 2x5 on A4) */
        .page-chunk { margin: 12px 0; }
        .batch-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .batch-item { display:grid; grid-template-columns: 1fr auto; gap: 16px; align-items:center; background: var(--card-bg); border:1px solid var(--border-primary); border-radius:8px; padding: 10px; page-break-inside: avoid; }
        .card-holder { width: 700px; height: 381px; position: relative; overflow: hidden; }
        .card-canvas.mini { transform: scale(.30); transform-origin: top left; }
        .qr-stack { display:flex; flex-direction:column; align-items:center; gap:8px; }
        .qr-stack .qr-box { background:#fff; padding:8px; border-radius:8px; border:1px dashed #cbd5e1; }
        .qr-stack .qr-caption { display:none; }

        /* QR-only pages (separate printing) */
        .qr-pages { margin-top: 24px; }
        .qr-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 2mm 20mm; }
        .qr-only { display:block !important; width: 48mm; height: 48mm; background: var(--card-bg); border:1px solid var(--border-primary); border-radius:8px; padding: 12px; page-break-inside: avoid; }
        .qr-only .qr-box { background:#fff; padding:6px; border-radius:8px; border:1px dashed #cbd5e1; }
        .qr-only .qr-caption { display:none; }

        @media print {
            body { background:#fff !important; color:#000 !important; }
            .sidebar, .sidebar-toggle-fixed, .mobile-overlay { display: none !important; }
            .toolbar, .selector, #batchOptions, #watermarkOptions, #cadetSelector, .card-title, .page-header { display: none !important; }
            html, body { width: 210mm; height: 297mm; margin:0 !important; padding:0 !important; }
            .dashboard-container, .main-content, .container { background:#fff !important; }
            #wrapper, #content-wrapper, .dashboard-container, .main-content, #idCardApp, #idCardApp .container {
                margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; position: static !important; display: block !important;
            }
            .main-content { margin-left: 0 !important; }
            .container { max-width: none !important; padding: 0 !important; margin: 0 !important; }
            @page { size: A4 portrait; margin: 3mm 0 0 0; }
            /* Hide single-preview canvas during print to prevent overlap with batch */
            .grid-two { display: none !important; }
            /* Ensure each page-chunk starts on a new page and QR pages start separately */
            .page-chunk { break-before: page; page-break-before: always; break-after: page; page-break-after: always; margin: 0 !important; padding: 0 !important; width: 209mm !important; box-sizing: border-box; }
            .page-chunk:first-of-type { break-before: auto; page-break-before: auto; }
            .page-chunk:last-child { break-after: auto; page-break-after: auto; }
            .qr-pages { break-before: page; page-break-before: always; }
            .batch-grid { grid-template-columns: repeat(2, 104mm); column-gap: 1mm; row-gap: 1mm; justify-content: start; align-content: start; justify-items: start; width: 209mm !important; box-sizing: border-box; }
            .qr-grid { display:grid !important; grid-template-columns: repeat(4, 48mm) !important; column-gap: 2mm !important; row-gap: 20mm !important; justify-content: start !important; align-content: start !important; justify-items: start !important; width: 209mm !important; box-sizing: border-box !important; }
            .batch-item { break-inside: avoid; page-break-inside: avoid; display: block !important; padding: 0 !important; border: none !important; background: #fff !important; }
            .qr-only { break-inside: avoid; page-break-inside: avoid; display: block !important; width: 48mm !important; height: 48mm !important; }
            .batch-item .qr-stack { display: none !important; }
            /* QR-only mode hides card pages */
            body.qr-only-print .page-chunk { display: none !important; }
            body.qr-only-print .qr-pages { display: block !important; }
            /* Remove dark wrappers and shadows */
            .batch-item, .qr-only { background: #fff !important; border: none !important; }
            .card-canvas { box-shadow: none !important; }
            /* Hide external captions; use in-box labels */
            .id-back .qr-label, .qr-only .qr-caption, .qr-stack .qr-caption { display: none !important; }
            /* IDs-only print mode */
            body.ids-only-print .grid-two { display: none !important; }
            body.ids-only-print .qr-pages { display: none !important; }
            body.ids-only-print .page-chunk { display: block !important; }
            /* Exact sizes for 2x4 layout on A4 portrait (optimized for space) */
            .card-holder { width: 104mm !important; height: 73mm !important; overflow: hidden !important; position: relative !important; }
            .card-canvas.mini { transform: scale(.39) !important; transform-origin: top left !important; position: absolute !important; left: 0 !important; top: 0 !important; }
            body.ids-only-print .card-canvas.mini { transform: scale(.39) !important; transform-origin: top left !important; }
            /* QR-only sizing */
            .qr-only .qr-box { width: 48mm !important; height: 48mm !important; background:#fff; padding:1mm; border-radius:8px; border:1px dashed #cbd5e1; box-sizing: border-box; }
            .qr-only .qr-box canvas, .qr-only .qr-box img { width: 46mm !important; height: 46mm !important; }
            /* Preserve background colors when printing */
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; overflow: visible !important; }
        }

        /* Admin dashboard container styles are already in the imported CSS files */
    </style>
</head>
<body>
    <!-- Sidebar Toggle to match admin dashboard -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
            // Centralized Admin Navigation
            $NAV_BASE = '..';
            include __DIR__ . '/../includes/admin_nav.php';
        ?>
        <div class="mobile-overlay" id="mobileOverlay"></div>
        <main class="main-content">
            <div id="idCardApp">
            <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title card-title">Generate Landscape ID Card</h1>
                <div class="page-subtitle">Configure batch options, manage watermarks, and generate ID cards for cadets.</div>
            </div>
        </div>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error" style="margin: 12px 0;"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Batch Options: color per platoon mapping and overrides (moved above selector) -->
        <section class="section-card collapsed" id="batchOptions">
            <div class="section-header">
                <div class="section-title">
                    <span class="section-icon"><i class="fas fa-sliders-h"></i></span>
                    <div>
                        <h3>Batch Options</h3>
                        <div class="section-hint">Auto-color by platoon or set a single accent for all cards. Adjust platoon colors below.</div>
                    </div>
                </div>
                <div class="section-right">
                    <span class="section-label">Auto color by platoon</span>
                    <label class="toggle-inline" aria-label="Auto color by platoon">
                        <input type="checkbox" id="batchAutoByPlatoon" checked />
                        <span class="toggle-track"></span>
                    </label>
                    <div class="accent-inline">
                        <label for="batchAccentPicker">Accent</label>
                        <input type="color" id="batchAccentPicker" value="#0ea5e9" />
                    </div>
                    <button type="button" class="section-toggle" aria-label="Toggle Batch Options"><i class="fas fa-chevron-down"></i></button>
                </div>
            </div>
            <div class="section-body">
                <div class="list">
                <table>
                    <thead>
                        <tr>
                            <th>Platoon</th>
                            <th>Color</th>
                            <th>Platoon</th>
                            <th>Color</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>ALPHA FIRST</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_ALPHA_FIRST" /></td>
                            <td>ALPHA SECOND</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_ALPHA_SECOND" /></td>
                        </tr>
                        <tr>
                            <td>BRAVO FIRST</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_BRAVO_FIRST" /></td>
                            <td>BRAVO SECOND</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_BRAVO_SECOND" /></td>
                        </tr>
                        <tr>
                            <td>CHARLIE FIRST</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_CHARLIE_FIRST" /></td>
                            <td>CHARLIE SECOND</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_CHARLIE_SECOND" /></td>
                        </tr>
                        <tr>
                            <td>DELTA FIRST</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_DELTA_FIRST" /></td>
                            <td>DELTA SECOND</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_DELTA_SECOND" /></td>
                        </tr>
                        <tr>
                            <td>MEDIC PLATOON</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_MEDIC_PLATOON" /></td>
                            <td>OFFICE PERSONNEL</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_OFFICE_PERSONNEL" /></td>
                        </tr>
                        <tr>
                            <td>MILITARY POLICE</td>
                            <td><input type="color" class="platoon-color-input" id="platoonColor_MILITARY_POLICE" /></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </section>

        <!-- Watermark options -->
        <section class="section-card collapsed" id="watermarkOptions">
            <div class="section-header">
                <div class="section-title">
                    <span class="section-icon"><i class="fas fa-water"></i></span>
                    <div>
                        <h3>Watermark</h3>
                        <div class="section-hint">Upload a logo placed at the center behind the last name (default 30% opacity). You can set it as default.</div>
                    </div>
                </div>
                <div class="section-right">
                    <button type="button" class="section-toggle" aria-label="Toggle Watermark"><i class="fas fa-chevron-down"></i></button>
                </div>
            </div>
            <div class="section-body">
                <form method="POST" enctype="multipart/form-data" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                    <input type="file" name="watermark_file" accept="image/*" />
                    <label>Opacity</label>
                    <input type="range" name="watermark_opacity" min="0" max="1" step="0.05" value="<?php echo htmlspecialchars($wm_opacity); ?>" />
                    <label style="display:flex; align-items:center; gap:6px;"><input type="checkbox" name="set_default" /> Set as default</label>
                    <button type="submit" class="btn primary" name="upload_watermark"><i class="fas fa-upload"></i> Upload/Apply</button>
                    <?php if (!empty($wm_url)): ?>
                        <span class="hint">Current: <code><?php echo htmlspecialchars($wm_url); ?></code></span>
                    <?php endif; ?>
                </form>
            </div>
        </section>

        <!-- Cadet selector (prints 9 per page) -->
        <section class="section-card" id="cadetSelector">
            <form method="POST" class="selector" id="selectorForm">
                <input type="hidden" name="auto_print" id="autoPrint" value="<?php echo (int)$auto_print; ?>" />
                <input type="hidden" name="print_mode" id="autoPrintMode" value="<?php echo htmlspecialchars($print_mode); ?>" />
                <div class="section-header">
                    <div class="section-title">
                        <span class="section-icon"><i class="fas fa-user-check"></i></span>
                        <div>
                            <h3>Select Cadets</h3>
                            <div class="section-hint">Filter by name, student ID, or platoon. Checked items will render below in pages of 8 with on-page QR and labels. QR-only pages show 16 per page (4x4 grid).</div>
                        </div>
                    </div>
                    <span class="section-tag">Prints 8 per page</span>
                </div>
                <div class="section-body">
                    <div class="filter-row">
                        <select id="platoonFilter">
                            <option value="">All Platoons</option>
                            <option value="ALPHA FIRST">ALPHA FIRST</option>
                            <option value="ALPHA SECOND">ALPHA SECOND</option>
                            <option value="BRAVO FIRST">BRAVO FIRST</option>
                            <option value="BRAVO SECOND">BRAVO SECOND</option>
                            <option value="CHARLIE FIRST">CHARLIE FIRST</option>
                            <option value="CHARLIE SECOND">CHARLIE SECOND</option>
                            <option value="DELTA FIRST">DELTA FIRST</option>
                            <option value="DELTA SECOND">DELTA SECOND</option>
                            <option value="MEDIC PLATOON">MEDIC PLATOON</option>
                            <option value="OFFICE PERSONNEL">OFFICE PERSONNEL</option>
                            <option value="MILITARY POLICE">MILITARY POLICE</option>
                        </select>
                        <div class="search">
                            <input type="text" id="searchBox" placeholder="Search by name, student ID, or platoon..." autocomplete="off" />
                        </div>
                        <div class="platoon-counts">
                            <span id="selectedCountLabel">Selected: <strong id="selectedCount">0</strong></span>
                            <span id="platoonCountLabel" style="display:none;">Total: <strong id="totalCount">0</strong></span>
                            <span id="platoonCountDetail" style="display:none;"></span>
                        </div>
                    </div>
                    <div class="action-panel">
                        <div class="action-group">
                            <div class="group-label">Selection</div>
                            <div class="btn-row">
                                <button type="button" class="qr-action-btn secondary" id="btnSelectVisible"><i class="fas fa-check-square"></i> Select Visible</button>
                                <button type="button" class="qr-action-btn secondary" id="btnClearSelection"><i class="fas fa-square"></i> Clear</button>
                            </div>
                        </div>
                        <div class="action-group">
                            <div class="group-label">Edit</div>
                            <div class="btn-row">
                                <select id="bulkPlatoon">
                                    <option value="">Set Platoon</option>
                                    <option value="ALPHA FIRST">ALPHA FIRST</option>
                                    <option value="ALPHA SECOND">ALPHA SECOND</option>
                                    <option value="BRAVO FIRST">BRAVO FIRST</option>
                                    <option value="BRAVO SECOND">BRAVO SECOND</option>
                                    <option value="CHARLIE FIRST">CHARLIE FIRST</option>
                                    <option value="CHARLIE SECOND">CHARLIE SECOND</option>
                                    <option value="DELTA FIRST">DELTA FIRST</option>
                                    <option value="DELTA SECOND">DELTA SECOND</option>
                                    <option value="MEDIC PLATOON">MEDIC PLATOON</option>
                                    <option value="OFFICE PERSONNEL">OFFICE PERSONNEL</option>
                                    <option value="MILITARY POLICE">MILITARY POLICE</option>
                                </select>
                                <button type="button" class="qr-action-btn secondary" id="btnApplyPlatoon"><i class="fas fa-pen"></i> Apply to Selected</button>
                                <button type="button" class="qr-action-btn" id="btnSaveSelected"><i class="fas fa-save"></i> Save Selected</button>
                            </div>
                        </div>
                        <div class="action-group">
                            <div class="group-label">Export</div>
                            <div class="btn-row">
                                <button type="button" class="qr-action-btn info" id="btnExportExcel"><i class="fas fa-file-excel"></i> Export Excel</button>
                            </div>
                        </div>
                        <div class="action-group">
                            <div class="group-label">Generate</div>
                            <div class="btn-row">
                                <button type="submit" class="qr-action-btn primary" id="btnGenerate"><i class="fas fa-id-badge"></i> Generate IDs</button>
                            </div>
                        </div>
                    </div>
                    <div class="panel-collapsible collapsed" id="printOptionsPanel">
                        <div class="panel-header">
                            <span class="section-label">Print Options</span>
                            <button type="button" class="panel-toggle" aria-label="Toggle Print Options"><i class="fas fa-chevron-down"></i></button>
                        </div>
                        <div class="print-panel">
                        <div class="action-group">
                            <div class="group-label">Print Options</div>
                            <div class="btn-row">
                                <select id="printPlatoon">
                                    <option value="">All Platoons</option>
                                    <option value="ALPHA FIRST">ALPHA FIRST</option>
                                    <option value="ALPHA SECOND">ALPHA SECOND</option>
                                    <option value="BRAVO FIRST">BRAVO FIRST</option>
                                    <option value="BRAVO SECOND">BRAVO SECOND</option>
                                    <option value="CHARLIE FIRST">CHARLIE FIRST</option>
                                    <option value="CHARLIE SECOND">CHARLIE SECOND</option>
                                    <option value="DELTA FIRST">DELTA FIRST</option>
                                    <option value="DELTA SECOND">DELTA SECOND</option>
                                    <option value="MEDIC PLATOON">MEDIC PLATOON</option>
                                    <option value="OFFICE PERSONNEL">OFFICE PERSONNEL</option>
                                    <option value="MILITARY POLICE">MILITARY POLICE</option>
                                </select>
                                <select id="printMode">
                                    <option value="both" selected>IDs + QR</option>
                                    <option value="ids">IDs Only</option>
                                    <option value="qr">QR Only</option>
                                </select>
                                <button type="button" class="qr-action-btn secondary" id="btnPrintPlatoon"><i class="fas fa-print"></i> Print Platoon</button>
                            </div>
                        </div>
                        <div class="action-group">
                            <div class="group-label">Print Selected</div>
                            <div class="btn-row">
                                <button type="button" class="qr-action-btn secondary" id="btnPrintSelectedIds"><i class="fas fa-id-card"></i> Print Selected IDs</button>
                                <button type="button" class="qr-action-btn secondary" id="btnPrintSelectedQr"><i class="fas fa-qrcode"></i> Print Selected QR</button>
                            </div>
                        </div>
                        <div class="action-group">
                            <div class="group-label">Quick Print</div>
                            <div class="btn-row">
                                <button type="button" class="qr-action-btn secondary" id="btnPrint"><i class="fas fa-print"></i> Print</button>
                                <button type="button" class="qr-action-btn secondary" id="btnPrintQrOnly"><i class="fas fa-qrcode"></i> Print QR Only</button>
                                <button type="button" class="qr-action-btn secondary" id="btnPrintIdsOnly"><i class="fas fa-id-card"></i> Print IDs Only</button>
                            </div>
                        </div>
                        <div class="action-group">
                            <div class="group-label">Mode</div>
                            <div class="btn-row">
                                <label style="display:flex; align-items:center; gap:6px;">
                                    <input type="checkbox" id="qrOnlyToggle" /> QR-only mode
                                </label>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="list">
                <table>
                    <thead>
                        <tr>
                            <th width="40">#</th>
                            <th>Name</th>
                            <th>Student ID</th>
                            <th>Platoon</th>
                        </tr>
                    </thead>
                    <tbody id="cadetRows">
                        <?php foreach ($cadet_list as $c): ?>
                            <?php 
                                $cid = (int)$c['id'];
                                $nm = trim(($c['last_name'] ?? '') . ', ' . ($c['first_name'] ?? ''));
                                $sid = (string)($c['student_id'] ?? '');
                                $pl = (string)($c['platoon'] ?? '');
                                $plCode = strtoupper(trim($pl));
                                $legacyToName = [
                                    'A1' => 'ALPHA FIRST',
                                    'A2' => 'ALPHA SECOND',
                                    'B1' => 'BRAVO FIRST',
                                    'B2' => 'BRAVO SECOND',
                                    'C1' => 'CHARLIE FIRST',
                                    'C2' => 'CHARLIE SECOND',
                                    'D1' => 'DELTA FIRST',
                                    'D2' => 'DELTA SECOND',
                                ];
                                $nameToLegacy = array_flip($legacyToName);
                                $canonical = $legacyToName[$plCode] ?? $plCode;
                                if ($canonical === 'OFFICE PERSONEL') $canonical = 'OFFICE PERSONNEL';
                                $legacyCode = $nameToLegacy[$canonical] ?? '';
                                $plSearch = strtolower(trim($pl . ' ' . $canonical . ' ' . $legacyCode));
                            ?>
                            <tr data-name="<?php echo htmlspecialchars(strtolower($nm)); ?>" data-student="<?php echo htmlspecialchars(strtolower($sid)); ?>" data-platoon="<?php echo htmlspecialchars($plSearch); ?>" data-profile-id="<?php echo $cid; ?>">
                                <td><input type="checkbox" class="pick" name="selected_ids[]" value="<?php echo $cid; ?>" <?php echo in_array($cid, $selected_ids, true) ? 'checked' : ''; ?> /></td>
                                <td><?php echo htmlspecialchars($nm); ?></td>
                                <td><input type="text" class="edit-student" value="<?php echo htmlspecialchars($sid); ?>" data-id="<?php echo $cid; ?>" style="width: 160px;" /></td>
                                <td><input type="text" class="edit-platoon" value="<?php echo htmlspecialchars($pl); ?>" data-id="<?php echo $cid; ?>" style="width: 180px;" /></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
                </div>
            </form>
        </section>

        

        <?php if ($cadet_profile): ?>
            <div class="controls">
                <div class="form-group">
                    <label>Style</label>
                    <select id="styleSelect">
                        <option value="template" selected>Template (blank)</option>
                        <option value="clean">Clean</option>
                        <option value="topbar">Topbar</option>
                        <option value="border">Border</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Accent color</label>
                    <input type="color" id="accentPicker" value="#0ea5e9" />
                    <span class="hint">Auto by platoon available</span>
                </div>
                <div class="form-group">
                    <label>Auto color by platoon</label>
                    <select id="autoByPlatoon">
                        <option value="on">On</option>
                        <option value="off">Off</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Platoon code</label>
                    <input type="text" id="platoonCode" value="<?php echo htmlspecialchars($platoon ?: 'DELTA SECOND'); ?>" />
                </div>
                <div class="form-group">
                    <label>Header text</label>
                    <input type="text" id="headerText" value="LSPU-LB ROTC UNIT" />
                </div>
                <div class="form-group">
                    <label>Last name size</label>
                    <input type="range" id="lastNameSize" min="80" max="260" value="240" />
                </div>
            </div>

            <div class="toolbar">
                <button class="qr-action-btn" id="btnDownloadPng"><i class="fas fa-download"></i> Download PNG</button>
                <button class="qr-action-btn secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>

            <div class="grid-two">
                <div>
                    <div id="idFront" class="card-canvas style-template" style="--accent:#0ea5e9; --wm-opacity: <?php echo htmlspecialchars($wm_opacity); ?>;">
                        <div class="id-front">
                            <div class="outer-border"></div>
                            <div class="header-row">
                                <?php if (!empty($logo_left_url)): ?><div class="hlogo left" style="background-image:url('<?php echo htmlspecialchars($logo_left_url); ?>');"></div><?php endif; ?>
                                <?php if (!empty($logo_center_url)): ?><div class="hlogo center" style="background-image:url('<?php echo htmlspecialchars($logo_center_url); ?>');"></div><?php endif; ?>
                                <?php if (!empty($logo_right_url)): ?><div class="hlogo right" style="background-image:url('<?php echo htmlspecialchars($logo_right_url); ?>');"></div><?php endif; ?>
                            </div>
                            <div class="template-header-text" id="tplHeader">DEPARTMENT OF MILITARY SCIENCE AND TACTICS<br/>LAGUNA STATE POLYTECHNIC UNIVERSITY LOS BANOS ROTC UNIT<br/>Brgy. Malinta, Los Baños, Laguna</div>
                            <div class="thin-line"></div>
                            <div class="center-band"></div>
                            <div class="template-subtitle">I am Cadet</div>
                            <div class="template-lastname" id="tplLastName"><?php echo htmlspecialchars(strtoupper($last_name)); ?></div>
                            <div class="bottom-whitebar"></div>
                            <div class="template-platoon" id="tplPlatoon"></div>
                            <div class="watermark" id="wmSingle" <?php if (!empty($wm_url)) { echo 'style="background-image:url(\''.htmlspecialchars($wm_url).'\');"'; } ?>></div>
                        </div>
                    </div>
                </div>
                <div>
                    <div id="idBack" class="id-back">
                        <div>
                            <div id="qrPermanent" class="qr-box" data-name="<?php echo htmlspecialchars(strtoupper($last_name) . ', ' . $first_name); ?>" data-sid="<?php echo htmlspecialchars($student_id ?: 'NO STUDENT ID'); ?>" data-profile-id="<?php echo (int)$cadet_profile_id; ?>" data-platoon="<?php echo htmlspecialchars($platoon ?: ''); ?>"></div>
                            <div class="qr-label">Permanent QR for Attendance</div>
                        </div>
                        <div class="meta">
                            <div class="row"><strong>Last name:</strong> <?php echo htmlspecialchars(strtoupper($last_name)); ?></div>
                            <div class="row"><strong>First name:</strong> <?php echo htmlspecialchars($first_name); ?></div>
                            <div class="row"><strong>Student ID:</strong> <?php echo htmlspecialchars($student_id); ?></div>
                            <div class="row"><strong>Platoon:</strong> <span id="metaPlatoon"><?php echo htmlspecialchars($platoon ?: 'N/A'); ?></span></div>
                            <div class="row"><strong>Profile ID:</strong> <?php echo (int)$cadet_profile_id; ?></div>
                            <div class="row"><strong>Accent:</strong> <span id="accentSwatch" class="accent-preview"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($batch_profiles)): ?>
            <h2 class="card-title" style="margin-top: 24px;">Batch Preview (<?php echo count($batch_profiles); ?> selected)</h2>
            <?php 
                $total = count($batch_profiles);
                for ($start = 0; $start < $total; $start += 8): 
                    $slice = array_slice($batch_profiles, $start, 8);
            ?>
            <div class="page-chunk">
                <div class="batch-grid" id="batchGrid">
                    <?php foreach ($slice as $bp): 
                        $code = strtoupper(trim((string)($bp['platoon'] ?? 'DELTA SECOND')));
                        $legacy = [
                            'A1' => 'ALPHA FIRST',
                            'A2' => 'ALPHA SECOND',
                            'B1' => 'BRAVO FIRST',
                            'B2' => 'BRAVO SECOND',
                            'C1' => 'CHARLIE FIRST',
                            'C2' => 'CHARLIE SECOND',
                            'D1' => 'DELTA FIRST',
                            'D2' => 'DELTA SECOND',
                        ];
                        if (isset($legacy[$code])) $code = $legacy[$code];
                        if ($code === 'OFFICE PERSONEL') $code = 'OFFICE PERSONNEL';
                        $displayPlatoon = $code;
                        $needsSuffix = in_array($displayPlatoon, [
                            'ALPHA FIRST','ALPHA SECOND','BRAVO FIRST','BRAVO SECOND','CHARLIE FIRST','CHARLIE SECOND','DELTA FIRST','DELTA SECOND'
                        ], true);
                        if ($needsSuffix) $displayPlatoon .= ' PLATOON';
                        $ln = strtoupper((string)$bp['last_name']);
                        $fn = (string)$bp['first_name'];
                        $sid = (string)$bp['student_id'];
                        $pid = (int)$bp['id'];
                    ?>
                    <div class="batch-item">
                        <div class="card-holder">
                            <div class="card-canvas style-template mini" style="--accent:#0ea5e9; --wm-opacity: <?php echo htmlspecialchars($wm_opacity); ?>;">
                                <div class="id-front">
                                    <div class="outer-border"></div>
                                    <div class="header-row">
                                        <?php if (!empty($logo_left_url)): ?><div class="hlogo left" style="background-image:url('<?php echo htmlspecialchars($logo_left_url); ?>');"></div><?php endif; ?>
                                        <?php if (!empty($logo_center_url)): ?><div class="hlogo center" style="background-image:url('<?php echo htmlspecialchars($logo_center_url); ?>');"></div><?php endif; ?>
                                        <?php if (!empty($logo_right_url)): ?><div class="hlogo right" style="background-image:url('<?php echo htmlspecialchars($logo_right_url); ?>');"></div><?php endif; ?>
                                    </div>
                                    <div class="template-header-text">DEPARTMENT OF MILITARY SCIENCE AND TACTICS<br/>LAGUNA STATE POLYTECHNIC UNIVERSITY LOS BANOS ROTC UNIT<br/>Brgy. Malinta, Los Baños, Laguna</div>
                                    <div class="thin-line"></div>
                                    <div class="center-band"></div>
                                    <div class="template-subtitle">I am cadet</div>
                                    <div class="template-lastname">&nbsp;<?php echo htmlspecialchars($ln); ?></div>
                                    <div class="bottom-whitebar"></div>
                                    <div class="template-platoon"><?php echo htmlspecialchars($displayPlatoon); ?></div>
                                    <div class="watermark" <?php if (!empty($wm_url)) { echo 'style="background-image:url(\''.htmlspecialchars($wm_url).'\');"'; } ?>></div>
                                </div>
                            </div>
                        </div>
                        <div class="qr-stack">
                            <div class="qr-box" id="qrPermanent_<?php echo $pid; ?>" data-profile-id="<?php echo $pid; ?>" data-platoon="<?php echo htmlspecialchars($code); ?>" data-name="<?php echo htmlspecialchars($ln . ', ' . $fn); ?>" data-sid="<?php echo htmlspecialchars($sid ?: 'NO STUDENT ID'); ?>"></div>
                            <div class="qr-caption">
                                <?php echo htmlspecialchars($ln . ', ' . $fn); ?><br/>
                                <small><?php echo htmlspecialchars($sid ?: 'NO STUDENT ID'); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endfor; ?>

            <!-- Separate QR-only pages -->
            <div class="qr-pages">
                <h2 class="card-title" style="margin: 12px 0 8px;">QR Codes</h2>
                <?php 
                    for ($start = 0; $start < $total; $start += 16): 
                        $slice = array_slice($batch_profiles, $start, 16);
                ?>
                <div class="page-chunk">
                    <div class="qr-grid">
                        <?php foreach ($slice as $bp): 
                            $code = strtoupper(trim((string)($bp['platoon'] ?? 'DELTA SECOND')));
                            $legacy = [
                                'A1' => 'ALPHA FIRST',
                                'A2' => 'ALPHA SECOND',
                                'B1' => 'BRAVO FIRST',
                                'B2' => 'BRAVO SECOND',
                                'C1' => 'CHARLIE FIRST',
                                'C2' => 'CHARLIE SECOND',
                                'D1' => 'DELTA FIRST',
                                'D2' => 'DELTA SECOND',
                            ];
                            if (isset($legacy[$code])) $code = $legacy[$code];
                            if ($code === 'OFFICE PERSONEL') $code = 'OFFICE PERSONNEL';
                            $ln = strtoupper((string)$bp['last_name']);
                            $fn = (string)$bp['first_name'];
                            $sid = (string)$bp['student_id'];
                            $pid = (int)$bp['id'];
                        ?>
                        <div class="qr-only">
                            <div class="qr-box" id="qrOnly_<?php echo $pid; ?>" data-profile-id="<?php echo $pid; ?>" data-platoon="<?php echo htmlspecialchars($code); ?>" data-name="<?php echo htmlspecialchars($ln . ', ' . $fn); ?>" data-sid="<?php echo htmlspecialchars($sid ?: 'NO STUDENT ID'); ?>"></div>
                            <div class="qr-caption"><?php echo htmlspecialchars($ln . ', ' . $fn); ?><br/><small><?php echo htmlspecialchars($sid ?: 'NO STUDENT ID'); ?></small></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
            </div>
            </div>
        </main>
    </div>

<script>
(function(){
    // Helper: robust QR renderer using either qrcodejs (constructor) or qrcode (toCanvas)
    function renderQRInto(el, text, size){
        if (!el) return;
        try { el.innerHTML = ''; } catch(_){ }
        const appendLabel = () => {
            const name = el.dataset.name || '';
            const sid = el.dataset.sid || '';
            if (!name && !sid) return;
            const lbl = document.createElement('div');
            lbl.className = 'qr-inbox-label';
            lbl.innerHTML = `${name ? name : ''}${sid ? `<div class="sid">${sid}</div>` : ''}`;
            el.appendChild(lbl);
        };
        // Ensure text is a string
        text = String(text);
        try {
            if (typeof window.QRCode === 'function' && window.QRCode.CorrectLevel) {
                new window.QRCode(el, {
                    text: text,
                    width: size,
                    height: size,
                    colorDark : '#000000',
                    colorLight : '#ffffff',
                    // Lower error correction level to reduce density
                    correctLevel: window.QRCode.CorrectLevel.L
                });
                appendLabel();
                return;
            }
        } catch (e) {}
        try {
            if (window.qrcode && typeof window.qrcode.toCanvas === 'function') {
                window.qrcode.toCanvas(String(text), { errorCorrectionLevel: 'L', width: size }, function(error, canvas){
                    if (!error) el.appendChild(canvas);
                    else el.textContent = 'QR error';
                    appendLabel();
                });
                return;
            }
        } catch (e) {}
        el.innerHTML = '<div style="color:red; font-size:12px;">QR library not loaded</div>';
    }

    // Helper: base64 encode supporting Unicode
    function b64EncodeUnicode(str) {
        try { return btoa(unescape(encodeURIComponent(str))); } catch(e) { return btoa(str); }
    }

    // Build permanent attendance QR payload accepted by QR/scanner.js and attendance scanner:
    // base64("attendance-system-permanent-key-2023|" + JSON.stringify({student_id, profile_id?, valid_until}))
    function normalizePlatoonValue(raw) {
        const v = String(raw || '').trim();
        if (!v) return '';
        const upper = v.toUpperCase().replace(/\s+/g, ' ').trim();
        const legacy = {
            'A1': 'ALPHA FIRST',
            'A2': 'ALPHA SECOND',
            'B1': 'BRAVO FIRST',
            'B2': 'BRAVO SECOND',
            'C1': 'CHARLIE FIRST',
            'C2': 'CHARLIE SECOND',
            'D1': 'DELTA FIRST',
            'D2': 'DELTA SECOND'
        };
        if (legacy[upper]) return legacy[upper];
        if (upper === 'OFFICE PERSONEL') return 'OFFICE PERSONNEL';
        return upper;
    }

    function buildPermanentAttendancePayload(studentId, profileId = '', lastName = '', platoon = '', monthsValid = (window.SYSTEM_CONFIG?.defaultValidityMonths || 12)) {
        const payloadObj = {
            system: 'rotc_system',
            type: 'cadet',
            profile_id: profileId ? ('CDT-' + String(profileId).trim()) : undefined,
            student_id: String(studentId || '').trim(),
            last_name: String(lastName || '').trim().toUpperCase(),
            platoon: normalizePlatoonValue(platoon) || 'DELTA SECOND'
        };
        if (!payloadObj.profile_id) {
            delete payloadObj.profile_id;
        }
        const json = JSON.stringify(payloadObj);
        return b64EncodeUnicode('ROTC_QR_V1::' + json);
    }

    const platoonColors = {
        'ALPHA FIRST': '#2563eb',
        'ALPHA SECOND': '#16a34a',
        'BRAVO FIRST': '#7c3aed',
        'BRAVO SECOND': '#0ea5e9',
        'CHARLIE FIRST': '#22c55e',
        'CHARLIE SECOND': '#eab308',
        'DELTA FIRST': '#fb923c',
        'DELTA SECOND': '#ef4444',
        'MEDIC PLATOON': '#14b8a6',
        'OFFICE PERSONNEL': '#64748b',
        'MILITARY POLICE': '#111827'
    };
    // Persisted settings keys
    const LS = {
        singleAccent: 'idgen_singleAccent',
        singleAuto: 'idgen_singleAuto',
        batchAccent: 'idgen_batchAccent',
        batchAuto: 'idgen_batchAuto',
        platoonColors: 'idgen_platoonColors'
    };
    // Load persisted platoon colors
    try {
        const savedMap = localStorage.getItem(LS.platoonColors);
        if (savedMap) {
            const parsed = JSON.parse(savedMap);
            if (parsed && typeof parsed === 'object') {
                Object.entries(parsed).forEach(([k, v]) => {
                    const nk = normalizePlatoonValue(k);
                    if (nk) platoonColors[nk] = v;
                });
            }
        }
    } catch(_) {}

    const idFront = document.getElementById('idFront');
    const lastNameFront = document.getElementById('lastNameFront');
    const tplLastName = document.getElementById('tplLastName');
    const tplPlatoon = document.getElementById('tplPlatoon');
    const platoonBadge = document.getElementById('platoonBadge');
    const unitTitle = document.getElementById('unitTitle');
    const metaPlatoon = document.getElementById('metaPlatoon');
    const accentSwatch = document.getElementById('accentSwatch');

    const styleSelect = document.getElementById('styleSelect');
    const accentPicker = document.getElementById('accentPicker');
    const autoByPlatoon = document.getElementById('autoByPlatoon');
    const platoonCode = document.getElementById('platoonCode');
    const headerText = document.getElementById('headerText');
    const lastNameSize = document.getElementById('lastNameSize');
    // Restore single controls
    try {
        const savedSingleAccent = localStorage.getItem(LS.singleAccent);
        if (accentPicker && savedSingleAccent) accentPicker.value = savedSingleAccent;
        const savedSingleAuto = localStorage.getItem(LS.singleAuto);
        if (autoByPlatoon && savedSingleAuto) autoByPlatoon.value = savedSingleAuto;
    } catch(_) {}

    function applyStyle(){
        if (!idFront) return;
        const style = styleSelect?.value || 'template';
        idFront.classList.remove('style-topbar','style-border','style-clean','style-template');
        idFront.classList.add('style-'+style);
        // Toggle elements safely
        const elTopbar = idFront.querySelector('.topbar');
        const elBottom = idFront.querySelector('.bottombar');
        const elFrame = idFront.querySelector('.frame');
        const elStripe = idFront.querySelector('.side-stripe');
        if (elTopbar) elTopbar.style.display = (style==='topbar')? 'flex':'none';
        if (elBottom) elBottom.style.display = (style==='topbar')? 'block':'none';
        if (elFrame) elFrame.style.display = (style==='border')? 'block':'none';
        if (elStripe) elStripe.style.display = (style==='clean')? 'block':'none';
        // Lastname size, then fit
        if (tplLastName && lastNameSize) tplLastName.style.fontSize = lastNameSize.value + 'px';
        fitAllLastnames();
    }

    function resolveAccent(){
        if (!idFront) return;
        let color = accentPicker?.value || '#0ea5e9';
        if (autoByPlatoon?.value === 'on') {
            const code = normalizePlatoonValue(platoonCode?.value || '');
            if (platoonColors[code]) color = platoonColors[code];
        }
        idFront.style.setProperty('--accent', color);
        if (accentSwatch) accentSwatch.style.background = color;
    }

    function computePlatoonText(code){
        const canonical = normalizePlatoonValue(code);
        if (!canonical) return '';
        const needsSuffix = [
            'ALPHA FIRST','ALPHA SECOND','BRAVO FIRST','BRAVO SECOND','CHARLIE FIRST','CHARLIE SECOND','DELTA FIRST','DELTA SECOND'
        ].includes(canonical);
        return canonical + (needsSuffix ? ' PLATOON' : '');
    }

    function applyMeta(){
        if (!idFront) return;
        const canonical = normalizePlatoonValue(platoonCode?.value || '');
        if (platoonBadge) platoonBadge.textContent = canonical || 'DELTA SECOND';
        if (metaPlatoon) metaPlatoon.textContent = canonical || 'N/A';
        if (unitTitle) unitTitle.textContent = headerText?.value || 'LSPU-LB ROTC UNIT';
        if (tplPlatoon) tplPlatoon.textContent = computePlatoonText(canonical || '<?php echo htmlspecialchars($platoon ?: ""); ?>');
    }

    // Fit last names to available width without overflow
    function fitTextToWidth(el, maxPx, minPx){
        if (!el) return;
        let size = parseInt(maxPx,10) || 144;
        el.style.fontSize = size + 'px';
        // If element not laid out, skip
        if (el.clientWidth <= 0) return;
        while (size > (minPx||60) && el.scrollWidth > el.clientWidth) {
            size -= 2;
            el.style.fontSize = size + 'px';
        }
    }

    function fitAllLastnames(){
        fitTextToWidth(tplLastName, parseInt(lastNameSize?.value||'240',10), 80);
        document.querySelectorAll('.batch-item .template-lastname').forEach(function(n){
            fitTextToWidth(n, 140, 60);
        });
    }

    styleSelect?.addEventListener('change', applyStyle);
    lastNameSize?.addEventListener('input', applyStyle);
    accentPicker?.addEventListener('input', (e)=>{ resolveAccent(); try { localStorage.setItem(LS.singleAccent, accentPicker.value); } catch(_) {} });
    autoByPlatoon?.addEventListener('change', (e)=>{ resolveAccent(); try { localStorage.setItem(LS.singleAuto, autoByPlatoon.value); } catch(_) {} });
    platoonCode?.addEventListener('input', ()=>{ applyMeta(); resolveAccent(); });
    headerText?.addEventListener('input', applyMeta);

    if (idFront) {
        applyStyle();
        applyMeta();
        resolveAccent();
    }
    // Always fit batch lastnames too
    fitAllLastnames();
    window.addEventListener('load', ()=> setTimeout(fitAllLastnames, 0));
    window.addEventListener('resize', fitAllLastnames);

    // Render permanent QR for single (use encrypted permanent attendance payload)
    const qrContainer = document.getElementById('qrPermanent');
    if (qrContainer) {
        const sid = qrContainer.dataset.sid || '';
        const pid = qrContainer.dataset.profileId || '';
        const name = qrContainer.dataset.name || '';
        const lastName = name.split(',')[0] || '';
        const platoon = qrContainer.dataset.platoon || '';
        const payload = buildPermanentAttendancePayload(sid, pid, lastName, platoon);
        renderQRInto(qrContainer, payload, 200); // larger to reduce density per module
    }

    // Render QRs in batch (cards section)
    document.querySelectorAll('[id^="qrPermanent_"]').forEach(function(el){
        const sid = el.dataset.sid || '';
        const pid = el.dataset.profileId || '';
        const name = el.dataset.name || '';
        const lastName = name.split(',')[0] || '';
        const platoon = el.dataset.platoon || '';
        const payload = buildPermanentAttendancePayload(sid, pid, lastName, platoon);
        renderQRInto(el, payload, 160);
    });

    // Render QR-only pages
    document.querySelectorAll('[id^="qrOnly_"]').forEach(function(el){
        const sid = el.dataset.sid || '';
        const pid = el.dataset.profileId || '';
        const name = el.dataset.name || '';
        const lastName = name.split(',')[0] || '';
        const platoon = el.dataset.platoon || '';
        const payload = buildPermanentAttendancePayload(sid, pid, lastName, platoon);
        renderQRInto(el, payload, 200);
    });

    // Initial apply of styles/meta
    applyStyle();
    applyMeta();
    resolveAccent();

    // Batch options controls
    const batchAutoByPlatoon = document.getElementById('batchAutoByPlatoon');
    const batchAccentPicker = document.getElementById('batchAccentPicker');
    const knownPlatoons = [
        'ALPHA FIRST',
        'ALPHA SECOND',
        'BRAVO FIRST',
        'BRAVO SECOND',
        'CHARLIE FIRST',
        'CHARLIE SECOND',
        'DELTA FIRST',
        'DELTA SECOND',
        'MEDIC PLATOON',
        'OFFICE PERSONNEL',
        'MILITARY POLICE'
    ];
    // Restore batch controls
    try {
        const savedBatchAccent = localStorage.getItem(LS.batchAccent);
        if (batchAccentPicker && savedBatchAccent) batchAccentPicker.value = savedBatchAccent;
        const savedBatchAuto = localStorage.getItem(LS.batchAuto);
        if (batchAutoByPlatoon && savedBatchAuto) batchAutoByPlatoon.checked = (savedBatchAuto === 'on');
    } catch(_) {}

    // Persist batch controls
    batchAccentPicker?.addEventListener('input', ()=>{ try { localStorage.setItem(LS.batchAccent, batchAccentPicker.value); } catch(_) {} applyBatchAccents(); });
    batchAutoByPlatoon?.addEventListener('change', ()=>{ try { localStorage.setItem(LS.batchAuto, batchAutoByPlatoon.checked ? 'on' : 'off'); } catch(_) {} applyBatchAccents(); });

    // Initialize mapping inputs with defaults
    knownPlatoons.forEach(codeRaw => {
        const code = normalizePlatoonValue(codeRaw);
        const inputId = 'platoonColor_' + code.replace(/\s+/g, '_');
        const input = document.getElementById(inputId);
        if (input) {
            input.value = platoonColors[code] || '#0ea5e9';
            input.addEventListener('input', function(){
                platoonColors[code] = this.value;
                applyBatchAccents();
                try { localStorage.setItem(LS.platoonColors, JSON.stringify(platoonColors)); } catch(_) {}
            });
        }
    });

    function applyBatchAccents(){
        const auto = batchAutoByPlatoon ? batchAutoByPlatoon.checked : true;
        const single = batchAccentPicker?.value || '#0ea5e9';
        document.querySelectorAll('.batch-item').forEach(function(item){
            const qr = item.querySelector('.qr-box');
            const card = item.querySelector('.card-canvas');
            const code = normalizePlatoonValue((qr && qr.dataset.platoon) ? qr.dataset.platoon : '');
            const color = auto ? (platoonColors[code] || single) : single;
            if (card) card.style.setProperty('--accent', color);
        });
        // Refit names after possible layout changes
        fitAllLastnames();
    }

    batchAutoByPlatoon?.addEventListener('change', applyBatchAccents);
    batchAccentPicker?.addEventListener('input', applyBatchAccents);
    // Initial apply in case batch grid exists
    applyBatchAccents();

    // Download PNG of front
    document.getElementById('btnDownloadPng')?.addEventListener('click', async () => {
        const node = document.getElementById('idFront');
        const canvas = await html2canvas(node, { backgroundColor: null, scale: 2 });
        const link = document.createElement('a');
        link.download = 'id_front_<?php echo (int)$cadet_profile_id; ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });
    // Selector behaviors: filter and max 10 enforcement
    const searchBox = document.getElementById('searchBox');
    const platoonFilter = document.getElementById('platoonFilter');
    const rows = Array.from(document.querySelectorAll('#cadetRows tr'));
    
    // Collapsible sections
    const batchOptionsCard = document.getElementById('batchOptions');
    const watermarkOptionsCard = document.getElementById('watermarkOptions');
    const printOptionsPanel = document.getElementById('printOptionsPanel');
    const batchToggle = batchOptionsCard?.querySelector('.section-toggle');
    const watermarkToggle = watermarkOptionsCard?.querySelector('.section-toggle');
    const printToggle = printOptionsPanel?.querySelector('.panel-toggle');

    batchToggle?.addEventListener('click', () => {
        batchOptionsCard?.classList.toggle('collapsed');
    });

    watermarkToggle?.addEventListener('click', () => {
        watermarkOptionsCard?.classList.toggle('collapsed');
    });

    printToggle?.addEventListener('click', () => {
        printOptionsPanel?.classList.toggle('collapsed');
    });
    
    // Row click functionality for checkbox selection
    rows.forEach(row => {
        row.addEventListener('click', function(e) {
            // Don't toggle if clicking on input fields
            if (e.target.tagName === 'INPUT' && e.target.type === 'text') {
                return;
            }
            
            // Don't toggle if clicking directly on checkbox (let it work normally)
            if (e.target.tagName === 'INPUT' && e.target.type === 'checkbox') {
                return;
            }
            
            // Toggle the checkbox
            const checkbox = this.querySelector('.pick');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                updateRowSelection(this);
            }
        });
        
        // Update visual selection state
        function updateRowSelection(row) {
            const checkbox = row.querySelector('.pick');
            if (checkbox && checkbox.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
            updateSelectedCount();
        }
        
        // Initial state
        updateRowSelection(row);
        
        // Listen for checkbox changes
        const checkbox = row.querySelector('.pick');
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                updateRowSelection(row);
            });
        }
    });
    
        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.pick:checked');
            const selectedEl = document.getElementById('selectedCount');
            if (selectedEl) selectedEl.textContent = checkedBoxes.length;
        }
        
        function filterRows() {
        const searchText = (searchBox?.value || '').toLowerCase();
        const platoonValue = (platoonFilter?.value || '').toLowerCase();
        
        let visibleCount = 0;
        const platoonCounts = {};
        
        rows.forEach(r => {
            const name = r.dataset.name || '';
            const student = r.dataset.student || '';
            const platoon = r.dataset.platoon || '';
            
            const matchesSearch = !searchText || 
                name.includes(searchText) || 
                student.includes(searchText) || 
                platoon.includes(searchText);
                
            const matchesPlatoon = !platoonValue || 
                platoon.includes(platoonValue);
            
            const isVisible = matchesSearch && matchesPlatoon;
            r.style.display = isVisible ? '' : 'none';
            
            if (isVisible) {
                visibleCount++;
                // Count by platoon (use exact match for display)
                const normalizedPlatoon = platoon.toUpperCase().trim();
                const displayPlatoon = getDisplayPlatoonName(normalizedPlatoon);
                platoonCounts[displayPlatoon] = (platoonCounts[displayPlatoon] || 0) + 1;
            }
        });
        
        // Update counts display
        const totalEl = document.getElementById('totalCount');
        const labelEl = document.getElementById('platoonCountLabel');
        const selectedLabelEl = document.getElementById('selectedCountLabel');
        const detailEl = document.getElementById('platoonCountDetail');
        
        if (totalEl) totalEl.textContent = visibleCount;
        
        // Show selected count always; show total only when filtering
        if (selectedLabelEl) selectedLabelEl.style.display = '';
        
        if (platoonValue && Object.keys(platoonCounts).length > 0) {
            const platoonName = getDisplayPlatoonName(platoonValue.toUpperCase());
            const count = platoonCounts[platoonName] || 0;
            if (detailEl) {
                detailEl.innerHTML = `${platoonName}: <strong>${count}</strong>`;
                detailEl.style.display = '';
            }
            if (labelEl) labelEl.style.display = '';
        } else {
            if (detailEl) detailEl.style.display = 'none';
            if (labelEl) {
                // Only show total if there are any filters applied
                const hasFilters = searchText || platoonValue;
                labelEl.style.display = hasFilters ? '' : 'none';
            }
        }
    }
    
    function getDisplayPlatoonName(normalized) {
        const mapping = {
            'ALPHA FIRST': 'ALPHA FIRST',
            'ALPHA SECOND': 'ALPHA SECOND',
            'BRAVO FIRST': 'BRAVO FIRST',
            'BRAVO SECOND': 'BRAVO SECOND',
            'CHARLIE FIRST': 'CHARLIE FIRST',
            'CHARLIE SECOND': 'CHARLIE SECOND',
            'DELTA FIRST': 'DELTA FIRST',
            'DELTA SECOND': 'DELTA SECOND',
            'MEDIC PLATOON': 'MEDIC PLATOON',
            'OFFICE PERSONNEL': 'OFFICE PERSONNEL',
            'MILITARY POLICE': 'MILITARY POLICE'
        };
        return mapping[normalized] || normalized;
    }
    
    // Initialize counts on load
    filterRows();
    updateSelectedCount();
    
    if (searchBox) {
        searchBox.addEventListener('input', filterRows);
    }
    
    if (platoonFilter) {
        platoonFilter.addEventListener('change', filterRows);
    }

    const picks = Array.from(document.querySelectorAll('.pick'));
    const selectorForm = document.getElementById('selectorForm');
    const editStudentInputs = Array.from(document.querySelectorAll('.edit-student'));
    const editPlatoonInputs = Array.from(document.querySelectorAll('.edit-platoon'));
    function platoonSearchValue(raw){
        const canonical = normalizePlatoonValue(raw);
        if (!canonical) return '';
        const legacy = {
            'ALPHA FIRST': 'A1',
            'ALPHA SECOND': 'A2',
            'BRAVO FIRST': 'B1',
            'BRAVO SECOND': 'B2',
            'CHARLIE FIRST': 'C1',
            'CHARLIE SECOND': 'C2',
            'DELTA FIRST': 'D1',
            'DELTA SECOND': 'D2'
        };
        const legacyCode = legacy[canonical] || '';
        return String((canonical + ' ' + legacyCode).trim()).toLowerCase();
    }
    editStudentInputs.forEach(inp => inp.addEventListener('input', function(){
        const tr = this.closest('tr');
        if (tr) tr.dataset.student = String(this.value || '').toLowerCase();
    }));
    editPlatoonInputs.forEach(inp => inp.addEventListener('input', function(){
        const tr = this.closest('tr');
        if (tr) tr.dataset.platoon = platoonSearchValue(this.value);
    }));

    const btnSelectVisible = document.getElementById('btnSelectVisible');
    const btnClearSelection = document.getElementById('btnClearSelection');
    const btnApplyPlatoon = document.getElementById('btnApplyPlatoon');
    const btnSaveSelected = document.getElementById('btnSaveSelected');
    const bulkPlatoon = document.getElementById('bulkPlatoon');

    function getVisibleRows(){
        return rows.filter(r => r.style.display !== 'none');
    }
    function getSelectedRows(){
        return rows.filter(r => {
            const cb = r.querySelector('.pick');
            return cb && cb.checked;
        });
    }

    btnSelectVisible?.addEventListener('click', () => {
        getVisibleRows().forEach(r => {
            const cb = r.querySelector('.pick');
            if (cb) cb.checked = true;
        });
    });
    btnClearSelection?.addEventListener('click', () => {
        rows.forEach(r => {
            const cb = r.querySelector('.pick');
            if (cb) cb.checked = false;
        });
    });

    btnApplyPlatoon?.addEventListener('click', () => {
        const val = (bulkPlatoon?.value || '').trim();
        if (!val) {
            alert('Please choose a platoon first.');
            return;
        }
        const selected = getSelectedRows();
        if (!selected.length) {
            alert('Please select at least one cadet first.');
            return;
        }
        selected.forEach(r => {
            const inp = r.querySelector('.edit-platoon');
            if (inp) {
                inp.value = val;
                r.dataset.platoon = platoonSearchValue(val);
            }
        });
    });

    btnSaveSelected?.addEventListener('click', async () => {
        const selected = getSelectedRows();
        if (!selected.length) {
            alert('Please select at least one cadet to save.');
            return;
        }
        const cadets = selected.map(r => {
            const id = parseInt((r.querySelector('.pick')?.value || '0'), 10) || 0;
            const studentId = String(r.querySelector('.edit-student')?.value || '').trim();
            const platoonVal = normalizePlatoonValue(String(r.querySelector('.edit-platoon')?.value || '').trim());
            return { id, student_id: studentId, platoon: platoonVal };
        }).filter(c => c.id > 0);

        try {
            // Show saving indicator
            const saveBtn = btnSaveSelected;
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveBtn.disabled = true;

            const res = await fetch('id_card.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'bulk_update_cadets', cadets })
            });
            const data = await res.json();
            
            // Restore button
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;

            if (data && data.success) {
                // Show success message without reload
                const successMsg = document.createElement('div');
                successMsg.className = 'save-success-message';
                successMsg.innerHTML = `
                    <div style="background: #10b981; color: white; padding: 12px 16px; border-radius: 6px; margin: 10px 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-check-circle"></i>
                        <span>Saved ${cadets.length} cadet(s) successfully!</span>
                    </div>
                `;
                
                // Insert success message after the buttons
                const actionsDiv = document.querySelector('.selector .actions');
                if (actionsDiv) {
                    actionsDiv.parentNode.insertBefore(successMsg, actionsDiv);
                    
                    // Remove message after 3 seconds
                    setTimeout(() => {
                        if (successMsg.parentNode) {
                            successMsg.parentNode.removeChild(successMsg);
                        }
                    }, 3000);
                }
                
                // Clear selection after successful save
                selected.forEach(r => {
                    const cb = r.querySelector('.pick');
                    if (cb) {
                        cb.checked = false;
                        r.classList.remove('selected');
                    }
                });
                
            } else {
                alert('Error saving: ' + (data?.message || 'Unknown error'));
            }
        } catch (err) {
            console.error('Save error:', err);
            alert('Error saving. Please try again.');
            
            // Restore button on error
            const saveBtn = btnSaveSelected;
            saveBtn.innerHTML = 'Save Selected';
            saveBtn.disabled = false;
        }
    });

    // Print buttons and QR-only toggle
    const btnPrint = document.getElementById('btnPrint');
    const btnPrintQrOnly = document.getElementById('btnPrintQrOnly');
    const btnPrintIdsOnly = document.getElementById('btnPrintIdsOnly');
    const btnPrintSelectedIds = document.getElementById('btnPrintSelectedIds');
    const btnPrintSelectedQr = document.getElementById('btnPrintSelectedQr');
    const qrOnlyToggle = document.getElementById('qrOnlyToggle');
    
    // Function to hide non-selected cards
    function hideNonSelectedCards() {
        const selected = getSelectedRows();
        const selectedIds = new Set(selected.map(r => r.querySelector('.pick')?.value));
        
        // Hide all batch items that are not selected
        const batchItems = document.querySelectorAll('.batch-item');
        batchItems.forEach(item => {
            const profileId = item.dataset.profileId;
            if (!selectedIds.has(profileId)) {
                item.style.display = 'none';
            } else {
                item.style.display = '';
            }
        });
    }
    
    // Function to show all cards
    function showAllCards() {
        const batchItems = document.querySelectorAll('.batch-item');
        batchItems.forEach(item => {
            item.style.display = '';
        });
    }
    
    btnPrint?.addEventListener('click', () => {
        document.body.classList.remove('qr-only-print');
        showAllCards();
        window.print();
    });
    btnPrintQrOnly?.addEventListener('click', () => {
        document.body.classList.add('qr-only-print');
        showAllCards();
        window.print();
        setTimeout(() => document.body.classList.remove('qr-only-print'), 0);
    });
    btnPrintIdsOnly?.addEventListener('click', () => {
        document.body.classList.add('ids-only-print');
        showAllCards();
        window.print();
        setTimeout(() => document.body.classList.remove('ids-only-print'), 0);
    });
    
    // Print Selected IDs functionality
    btnPrintSelectedIds?.addEventListener('click', () => {
        const selected = getSelectedRows();
        if (selected.length === 0) {
            alert('Please select at least one cadet first.');
            return;
        }
        
        document.body.classList.remove('qr-only-print');
        document.body.classList.add('ids-only-print');
        hideNonSelectedCards();
        window.print();
        setTimeout(() => {
            document.body.classList.remove('ids-only-print');
            showAllCards();
        }, 0);
    });
    
    // Print Selected QR functionality
    btnPrintSelectedQr?.addEventListener('click', () => {
        const selected = getSelectedRows();
        if (selected.length === 0) {
            alert('Please select at least one cadet first.');
            return;
        }
        
        document.body.classList.remove('ids-only-print');
        document.body.classList.add('qr-only-print');
        hideNonSelectedCards();
        window.print();
        setTimeout(() => {
            document.body.classList.remove('qr-only-print');
            showAllCards();
        }, 0);
    });
    
    qrOnlyToggle?.addEventListener('change', function(){
        document.body.classList.toggle('qr-only-print', this.checked);
    });

    const printPlatoon = document.getElementById('printPlatoon');
    const printMode = document.getElementById('printMode');
    const btnPrintPlatoon = document.getElementById('btnPrintPlatoon');
    const btnExportExcel = document.getElementById('btnExportExcel');
    const autoPrint = document.getElementById('autoPrint');
    const autoPrintMode = document.getElementById('autoPrintMode');

    // Export to Excel functionality
    btnExportExcel?.addEventListener('click', () => {
        // Get all checked cadets
        const checkedCadets = [];
        rows.forEach(r => {
            const cb = r.querySelector('.pick');
            if (cb && cb.checked) {
                const nameCell = r.querySelector('td:nth-child(2)');
                const idInput = r.querySelector('.edit-student');
                const platoonCell = r.querySelector('.edit-platoon');
                
                if (nameCell && idInput && platoonCell) {
                    checkedCadets.push({
                        name: nameCell.textContent.trim(),
                        studentId: idInput.value.trim(),
                        platoon: platoonCell.value.trim(),
                        profileId: r.dataset.profileId || ''
                    });
                }
            }
        });

        if (checkedCadets.length === 0) {
            alert('Please select at least one cadet to export.');
            return;
        }

        // Create CSV content
        let csvContent = 'Name,Student ID,Platoon,Profile ID,QR Code URL\n';
        
        checkedCadets.forEach(cadet => {
            const qrUrl = `${window.location.origin}/generate qr/process_qr.php?profile_id=${cadet.profileId}`;
            const name = `"${cadet.name.replace(/"/g, '""')}"`; // Escape quotes
            const studentId = `"${cadet.studentId.replace(/"/g, '""')}"`;
            const platoon = `"${cadet.platoon.replace(/"/g, '""')}"`;
            const profileId = `"${cadet.profileId.replace(/"/g, '""')}"`;
            
            csvContent += `${name},${studentId},${platoon},${profileId},"${qrUrl}"\n`;
        });

        // Create and download the file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
        link.setAttribute('href', url);
        link.setAttribute('download', `ID_Card_Generation_${timestamp}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        alert(`Exported ${checkedCadets.length} cadets to Excel file.`);
    });

    btnPrintPlatoon?.addEventListener('click', () => {
        const platoon = normalizePlatoonValue(printPlatoon?.value || '');
        if (!platoon) {
            alert('Please choose a platoon to print.');
            return;
        }
        const mode = (printMode?.value || 'both').trim();
        rows.forEach(r => {
            const cb = r.querySelector('.pick');
            if (cb) cb.checked = false;
        });
        let count = 0;
        rows.forEach(r => {
            const cb = r.querySelector('.pick');
            const pl = normalizePlatoonValue(r.querySelector('.edit-platoon')?.value || '');
            if (cb && pl === platoon) {
                cb.checked = true;
                count++;
            }
        });
        if (!count) {
            alert('No cadets found for the selected platoon.');
            return;
        }
        if (autoPrint) autoPrint.value = '1';
        if (autoPrintMode) autoPrintMode.value = mode;
        if (selectorForm) {
            if (selectorForm.requestSubmit) selectorForm.requestSubmit(); else selectorForm.submit();
        }
    });

    if (autoPrint && autoPrint.value === '1') {
        const mode = (autoPrintMode?.value || 'both').trim();
        document.body.classList.toggle('qr-only-print', mode === 'qr');
        document.body.classList.toggle('ids-only-print', mode === 'ids');
        setTimeout(() => {
            window.print();
            if (autoPrint) autoPrint.value = '0';
            if (autoPrintMode) autoPrintMode.value = '';
            setTimeout(() => {
                document.body.classList.remove('qr-only-print');
                document.body.classList.remove('ids-only-print');
            }, 0);
        }, 0);
    }

})();
</script>
<script src="../js/mobile-navigation.js"></script>
</body>
</html>
