<?php
session_start();
require_once 'includes/db.php';

// Local helpers for schema-awareness
if (!function_exists('tableExists')) {
    function tableExists($pdo, $table) {
        try {
            $s = $pdo->prepare("SHOW TABLES LIKE ?");
            $s->execute([$table]);
            return (bool)$s->fetch();
        } catch (Exception $e) { return false; }
    }
}

// Get current duty officer (simplified for demo)
$current_duty_officer = null;
if (isset($_SESSION['duty_officer_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM officers WHERE id = ?");
    $stmt->execute([$_SESSION['duty_officer_id']]);
    $current_duty_officer = $stmt->fetch();
}

// Handle duty officer selection (PIN required)
if (isset($_POST['select_duty_officer'])) {
    $selectedId = (int)($_POST['officer_id'] ?? 0);
    $pin = trim($_POST['pin'] ?? '');
    if ($selectedId <= 0 || $pin === '') {
        $_SESSION['flash_error'] = 'Please choose an officer and enter their PIN to continue.';
        header('Location: dashboard.php');
        exit;
    }
    try {
        // Ensure PIN table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS duty_officer_pins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            officer_id INT NOT NULL UNIQUE,
            pin VARCHAR(64) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // If officer has no PIN yet and provided PIN is default '0000', seed it
        $hasRowStmt = $pdo->prepare("SELECT 1 FROM duty_officer_pins WHERE officer_id = ? LIMIT 1");
        $hasRowStmt->execute([$selectedId]);
        if (!$hasRowStmt->fetchColumn() && $pin === '0000') {
            try {
                $seed = $pdo->prepare("INSERT INTO duty_officer_pins (officer_id, pin, is_active) VALUES (?, '0000', 1)");
                $seed->execute([$selectedId]);
            } catch (Exception $ie) { /* ignore unique conflicts */ }
        }
        $chk = $pdo->prepare("SELECT 1 FROM duty_officer_pins WHERE officer_id = ? AND pin = ? AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
        $chk->execute([$selectedId, $pin]);
        if (!$chk->fetchColumn()) {
            $_SESSION['flash_error'] = 'Invalid PIN for the selected officer.';
            header('Location: dashboard.php');
            exit;
        }
        // Auth OK -> set officer
        $_SESSION['duty_officer_id'] = $selectedId;
        // Build a friendly flash message
        try {
            $s = $pdo->prepare("SELECT name, rank_position, rank, position FROM officers WHERE id = ? LIMIT 1");
            $s->execute([$selectedId]);
            $o = $s->fetch();
            $nm = $o['name'] ?? '';
            $rp = '';
            if (!empty($o['rank_position'])) { $rp = $o['rank_position']; }
            else { $rp = trim(($o['rank'] ?? '') . ' ' . ($o['position'] ?? '')); }
            if ($nm || $rp) {
                $_SESSION['flash_success'] = 'Duty officer selected: ' . trim($nm ?: $rp) . ($nm && $rp ? (' — ' . $rp) : '');
            } else {
                $_SESSION['flash_success'] = 'Duty officer selected.';
            }
        } catch (Exception $e) { /* ignore */ }
        header('Location: dashboard.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_error'] = 'Authentication failed. Please try again.';
        header('Location: dashboard.php');
        exit;
    }
}

// Handle change officer action
if (isset($_GET['logout'])) {
    unset($_SESSION['duty_officer_id']);
    header('Location: dashboard.php');
    exit;
}

// Handle add new duty officer (require officer-defined PIN)
$add_error = null;
if (isset($_POST['add_duty_officer'])) {
    $name = trim($_POST['officer_name'] ?? '');
    $rank_position = trim($_POST['rank_position'] ?? '');
    $platoon = trim($_POST['platoon'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_pin = trim($_POST['new_pin'] ?? '');
    $confirm_pin = trim($_POST['confirm_pin'] ?? '');

    // Validate required fields including PIN
    if ($name === '' || $rank_position === '') {
        $add_error = 'Name and Rank/Position are required.';
    } elseif ($new_pin === '' || $confirm_pin === '') {
        $add_error = 'PIN and confirmation are required.';
    } elseif ($new_pin !== $confirm_pin) {
        $add_error = 'PINs do not match.';
    } else {
        try {
            // Ensure 'name' column exists in officers table (adds nullable if missing)
            try {
                $hasNameCol = false;
                $chk = $pdo->query("SHOW COLUMNS FROM officers");
                foreach ($chk as $cr) { if (($cr['Field'] ?? '') === 'name') { $hasNameCol = true; break; } }
                if (!$hasNameCol) {
                    $pdo->exec("ALTER TABLE officers ADD COLUMN name VARCHAR(255) NULL");
                }
            } catch (Exception $ie) {
                // ignore if cannot alter; insert will still be schema-aware
            }

            // Detect officers table columns
            $cols = [];
            $colsInfo = [];
            $colStmt = $pdo->query("SHOW COLUMNS FROM officers");
            foreach ($colStmt as $r) { 
                $cols[$r['Field']] = true; 
                $colsInfo[$r['Field']] = $r; 
            }

            // If officers.user_id exists and is NOT NULL, ensure we have a valid users.id to reference
            $userIdForOfficer = null;
            if (!empty($cols['user_id'])) {
                $userCol = $colsInfo['user_id'] ?? null;
                $needsNotNull = $userCol && (strtoupper($userCol['Null'] ?? 'YES') === 'NO');
                if ($needsNotNull) {
                    // Try to find placeholder user 'duty_officer'
                    try {
                        $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                        $uStmt->execute(['duty_officer']);
                        $userIdForOfficer = $uStmt->fetchColumn();
                        if (!$userIdForOfficer) {
                            // Create minimal user dynamically based on existing columns
                            $uCols = [];
                            $uInfo = [];
                            $uc = $pdo->query("SHOW COLUMNS FROM users");
                            foreach ($uc as $ur) { $uCols[$ur['Field']] = true; $uInfo[$ur['Field']] = $ur; }
                            $uFields = [];
                            $uValues = [];
                            if (!empty($uCols['username'])) { $uFields[] = 'username'; $uValues[] = 'duty_officer'; }
                            if (!empty($uCols['password'])) { $uFields[] = 'password'; $uValues[] = password_hash('472005', PASSWORD_DEFAULT); }
                            if (!empty($uCols['email'])) { $uFields[] = 'email'; $uValues[] = 'duty.officer@rotc.system'; }
                            if (!empty($uCols['full_name'])) { $uFields[] = 'full_name'; $uValues[] = 'Duty Officer'; }
                            if (!empty($uCols['role'])) { $uFields[] = 'role'; $uValues[] = 'admin'; }
                            if (!empty($uCols['status'])) { $uFields[] = 'status'; $uValues[] = 'active'; }
                            if (empty($uFields)) { throw new Exception('Users table has no compatible columns to create placeholder user.'); }
                            $uPh = implode(', ', array_fill(0, count($uFields), '?'));
                            $uSql = 'INSERT INTO users (' . implode(', ', $uFields) . ') VALUES (' . $uPh . ')';
                            $ins = $pdo->prepare($uSql);
                            $ins->execute($uValues);
                            $userIdForOfficer = $pdo->lastInsertId();
                        }
                    } catch (Exception $ue) {
                        throw new Exception('Unable to ensure users record for officer: ' . $ue->getMessage());
                    }
                }
            }

            // Build insert dynamically for available columns
            $fields = [];
            $values = [];
            
            // Name column if present
            if (!empty($cols['name'])) { 
                $fields[] = 'name'; 
                $values[] = $name; 
            }
            // Rank/Position mapping:
            // Prefer a single destination column (rank_position). If rank/position are NOT NULL in schema,
            // populate them too to satisfy constraints.
            $rankNotNull = !empty($cols['rank']) && strtoupper($colsInfo['rank']['Null'] ?? 'YES') === 'NO';
            $posNotNull  = !empty($cols['position']) && strtoupper($colsInfo['position']['Null'] ?? 'YES') === 'NO';
            if (!empty($cols['rank_position'])) {
                $fields[] = 'rank_position';
                $values[] = $rank_position;
                // Also satisfy NOT NULL constraints on rank/position if needed
                if ($rankNotNull) { $fields[] = 'rank'; $values[] = $rank_position; }
                if ($posNotNull)  { $fields[] = 'position'; $values[] = $rank_position; }
            } else {
                // No rank_position column; satisfy constraints or choose one column
                if ($rankNotNull || $posNotNull) {
                    if ($rankNotNull) { $fields[] = 'rank'; $values[] = $rank_position; }
                    if ($posNotNull)  { $fields[] = 'position'; $values[] = $rank_position; }
                } else {
                    if (!empty($cols['rank'])) { $fields[] = 'rank'; $values[] = $rank_position; }
                    elseif (!empty($cols['position'])) { $fields[] = 'position'; $values[] = $rank_position; }
                }
            }
            // Optional columns
            if (!empty($cols['platoon'])) {
                if ($platoon !== '') {
                    $fields[] = 'platoon'; $values[] = $platoon;
                } else {
                    // If NOT NULL, supply a safe default
                    $platoonNull = strtoupper($colsInfo['platoon']['Null'] ?? 'YES');
                    if ($platoonNull === 'NO') { $fields[] = 'platoon'; $values[] = 'HQ'; }
                }
            }
            if ($contact !== '') {
                if (!empty($cols['contact_number'])) { $fields[] = 'contact_number'; $values[] = $contact; }
                elseif (!empty($cols['contact'])) { $fields[] = 'contact'; $values[] = $contact; }
            }
            if ($email !== '' && !empty($cols['email'])) { $fields[] = 'email'; $values[] = $email; }
            if (!empty($cols['department'])) { $fields[] = 'department'; $values[] = 'Headquarters'; }
            if (!empty($cols['status'])) { $fields[] = 'status'; $values[] = 'active'; }
            if (!empty($cols['user_id']) && $userIdForOfficer) { $fields[] = 'user_id'; $values[] = $userIdForOfficer; }

            if (empty($fields)) {
                throw new Exception('No compatible columns found in officers table for insert.');
            }

            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $sql = 'INSERT INTO officers (' . implode(', ', $fields) . ') VALUES (' . $placeholders . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $newId = (int)$pdo->lastInsertId();

            // Ensure duty_officer_pins table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS duty_officer_pins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                officer_id INT NOT NULL UNIQUE,
                pin VARCHAR(64) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Upsert PIN for this officer
            try {
                $ins = $pdo->prepare("INSERT INTO duty_officer_pins (officer_id, pin, is_active) VALUES (?, ?, 1)");
                $ins->execute([$newId, $new_pin]);
            } catch (PDOException $pe) {
                // If duplicate or unique constraint missing, fallback to update-then-insert
                $up = $pdo->prepare("UPDATE duty_officer_pins SET pin = ?, is_active = 1 WHERE officer_id = ?");
                $up->execute([$new_pin, $newId]);
                if ($up->rowCount() === 0) {
                    $ins2 = $pdo->prepare("INSERT INTO duty_officer_pins (officer_id, pin, is_active) VALUES (?, ?, 1)");
                    $ins2->execute([$newId, $new_pin]);
                }
            }

            // Do not auto-select; require PIN auth on selection
            $_SESSION['flash_success'] = 'Duty officer added: ' . $name . ($rank_position ? (' — ' . $rank_position) : '') . '. Please authenticate with your PIN to select.';
            header('Location: dashboard.php');
            exit;
        } catch (Exception $e) {
            error_log('Add Duty Officer failed: ' . $e->getMessage());
            $add_error = 'Failed to add duty officer. Please contact admin. (' . htmlspecialchars($e->getMessage()) . ')';
        }
    }
}

// Get QR data if provided
$qr_data = isset($_GET['qr']) ? $_GET['qr'] : '';

// Get all officers for selection (schema-aware ORDER BY)
try {
    $offCols = [];
    $cstmt = $pdo->query("SHOW COLUMNS FROM officers");
    foreach ($cstmt as $r) { $offCols[$r['Field']] = true; }
    $orderBy = 'o.id';
    if (!empty($offCols['rank_position'])) { $orderBy = 'o.rank_position, o.id'; }
    elseif (!empty($offCols['rank'])) { $orderBy = 'o.rank, o.id'; }
    elseif (!empty($offCols['position'])) { $orderBy = 'o.position, o.id'; }
    $where = '1=1';
    if (!empty($offCols['status'])) { $where = "o.status = 'active'"; }
    $sqlOff = "SELECT o.*, u.username, u.email FROM officers o LEFT JOIN users u ON o.user_id = u.id WHERE $where ORDER BY $orderBy";
    $officers_stmt = $pdo->query($sqlOff);
    $officers = $officers_stmt->fetchAll();
} catch (Exception $e) {
    $officers = [];
}

// Get inventory statistics (schema-aware; prefer outstanding borrowed_items for borrowed count)
try {
    $itemsTable = tableExists($pdo, 'items') ? 'items' : (tableExists($pdo, 'inventory_items') ? 'inventory_items' : null);
    if ($itemsTable === null) { throw new Exception('No items table found'); }

    // Discover available columns
    $colsInfo = [];
    $cstmt = $pdo->query("SHOW COLUMNS FROM `{$itemsTable}`");
    foreach ($cstmt as $r) { $colsInfo[$r['Field']] = true; }
    $pick = function(array $cands) use ($colsInfo) {
        foreach ($cands as $c) { if (!empty($colsInfo[$c])) return $c; }
        return null;
    };

    $totalCol = $pick(['total_quantity','quantity_total','total','quantity','stock']);
    $availCol = $pick(['available_quantity','quantity_available','qty_available','available','qty']);
    $borrCol  = !empty($colsInfo['borrowed_quantity']) ? 'borrowed_quantity' : null;

    // Build SELECT dynamically for total/available
    $selects = [ 'COUNT(*) AS item_count' ];
    if ($totalCol) { $selects[] = "SUM({$totalCol}) AS sum_total"; }
    if ($availCol) { $selects[] = "SUM({$availCol}) AS sum_avail"; }
    $sql = 'SELECT ' . implode(', ', $selects) . ' FROM ' . $itemsTable;
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

    $sumTotal = (int)($row['sum_total'] ?? 0);
    $sumAvail = (int)($row['sum_avail'] ?? 0);
    $itemCount = (int)($row['item_count'] ?? 0);

    // Compute borrowed from outstanding borrowed_items where possible
    $sumBorr = 0;
    if (tableExists($pdo, 'borrowed_items')) {
        // Detect columns on borrowed_items and items
        $hasQtyB = false; $hasQty = false; $hasStatus = false; $retDateCol = null; $hasReturnable = false;
        try { $hasQtyB = (bool)$pdo->query("SHOW COLUMNS FROM borrowed_items LIKE 'quantity_borrowed'")->fetch(); } catch (Exception $e) {}
        try { $hasQty = (bool)$pdo->query("SHOW COLUMNS FROM borrowed_items LIKE 'quantity'")->fetch(); } catch (Exception $e) {}
        try { $hasStatus = (bool)$pdo->query("SHOW COLUMNS FROM borrowed_items LIKE 'status'")->fetch(); } catch (Exception $e) {}
        foreach (['actual_return_date','returned_at','return_date','date_returned'] as $cand) {
            try { if ($pdo->query("SHOW COLUMNS FROM borrowed_items LIKE " . $pdo->quote($cand))->fetch()) { $retDateCol = $cand; break; } } catch (Exception $e) {}
        }
        try { $hasReturnable = (bool)$pdo->query("SHOW COLUMNS FROM `{$itemsTable}` LIKE 'can_be_returned'")->fetch(); } catch (Exception $e) {}
        $qtyExpr = $hasQtyB ? 'bi.quantity_borrowed' : ($hasQty ? 'bi.quantity' : '1');
        $where = [];
        if ($hasStatus) { $where[] = "(bi.status IS NULL OR TRIM(bi.status) = '' OR LOWER(bi.status) NOT IN ('returned','complete','completed'))"; }
        if ($retDateCol) { $where[] = "(bi.{$retDateCol} IS NULL OR bi.{$retDateCol} = '' OR bi.{$retDateCol} = '0000-00-00' OR bi.{$retDateCol} = '0000-00-00 00:00:00')"; }
        if ($hasReturnable) { $where[] = "i.can_be_returned = 'returnable'"; }
        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sqlB = "SELECT SUM($qtyExpr) AS sum_borrowed FROM borrowed_items bi JOIN {$itemsTable} i ON bi.item_id = i.id {$whereSql}";
        try { $r = $pdo->query($sqlB); $rb = $r ? $r->fetch(PDO::FETCH_ASSOC) : null; $sumBorr = (int)($rb['sum_borrowed'] ?? 0); } catch (Exception $e) { $sumBorr = 0; }
    } else if ($borrCol) {
        try { $rb = $pdo->query('SELECT SUM(' . $borrCol . ') AS sum_borrowed FROM ' . $itemsTable)->fetch(PDO::FETCH_ASSOC); $sumBorr = (int)($rb['sum_borrowed'] ?? 0); } catch (Exception $e) { $sumBorr = 0; }
    } else if ($totalCol && $availCol) {
        $sumBorr = max(0, $sumTotal - $sumAvail);
    }

    $stats = [
        'total_items' => $itemCount,
        'available_items' => $sumAvail,
        'borrowed_items' => $sumBorr,
    ];
} catch (Exception $e) {
    $stats = ['total_items' => 0, 'available_items' => 0, 'borrowed_items' => 0];
}

// Get recent transactions (schema-aware officer name expression and ordering)
try {
    $offCols2 = [];
    $cstmt2 = $pdo->query("SHOW COLUMNS FROM officers");
    foreach ($cstmt2 as $r2) { $offCols2[$r2['Field']] = true; }
    $exprs = [];
    if (!empty($offCols2['name'])) { $exprs[] = 'o.name'; }
    if (!empty($offCols2['rank_position'])) { $exprs[] = 'o.rank_position'; }
    $rankPosExpr = null;
    if (!empty($offCols2['rank']) && !empty($offCols2['position'])) {
        $rankPosExpr = "TRIM(CONCAT(IFNULL(o.rank, ''), ' ', IFNULL(o.position, '')))";
    } elseif (!empty($offCols2['rank'])) {
        $rankPosExpr = 'o.rank';
    } elseif (!empty($offCols2['position'])) {
        $rankPosExpr = 'o.position';
    }
    if ($rankPosExpr) { $exprs[] = $rankPosExpr; }
    $officerNameExpr = !empty($exprs) ? ('COALESCE(' . implode(', ', $exprs) . ')') : ("'Officer'");
    // Choose an order column
    $txCols = [];
    try { foreach ($pdo->query("SHOW COLUMNS FROM transactions") as $tr) { $txCols[$tr['Field']] = true; } } catch (Exception $e) {}
    $orderCol = 'id';
    if (!empty($txCols['created_at'])) { $orderCol = 'created_at'; }
    elseif (!empty($txCols['updated_at'])) { $orderCol = 'updated_at'; }
    elseif (!empty($txCols['returned_at'])) { $orderCol = 'returned_at'; }
    $recent_sql = "SELECT t.*, $officerNameExpr as officer_name FROM transactions t LEFT JOIN officers o ON t.duty_officer_id = o.id ORDER BY t.$orderCol DESC LIMIT 5";
    $recent_stmt = $pdo->query($recent_sql);
    $recent_transactions = $recent_stmt->fetchAll();
} catch (Exception $e) {
    $recent_transactions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROTC QR Inventory Dashboard</title>
    <link href="../css/tactical-theme.css" rel="stylesheet">
    <link href="../css/dashboard-redesigned.css" rel="stylesheet">
    <link href="../css/mobile-responsive.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php $inv_css_ver = @filemtime(__DIR__ . '/css/dashboard.css') ?: time(); ?>
    <link href="css/dashboard.css?v=<?php echo $inv_css_ver; ?>" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --accent-gold: #00ff7f;
            --border-color: #333;
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-content {
            padding: 2rem;
            min-height: 100vh;
        }
        
        .dashboard-header {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .duty-officer-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
        }
        
        .stats-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-gold);
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .nav-tabs .nav-link {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            margin-right: 0.5rem;
            border-radius: 8px 8px 0 0;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--accent-gold);
            border-color: var(--accent-gold);
            color: var(--bg-primary);
            font-weight: 600;
        }
        
        .tab-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 2rem;
        }
        
        .form-control, .form-select {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--bg-tertiary);
            border-color: var(--accent-gold);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }
        
        .btn-primary {
            background: var(--accent-gold);
            border: none;
            color: var(--bg-primary);
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: #e6c200;
            color: var(--bg-primary);
        }
        
        .table-dark {
            background: var(--bg-tertiary);
        }
        
        .table-dark th {
            border-color: var(--border-color);
            color: var(--accent-gold);
        }
        
        .table-dark td {
            border-color: var(--border-color);
        }
        
        .badge {
            font-size: 0.75rem;
        }
        
        .alert-info {
            background: var(--bg-tertiary);
            border-color: var(--accent-gold);
            color: var(--text-primary);
        }
        
        .item-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #dee2e6;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #007bff;
        }
        
        /* Admin Dashboard Styling Consistency */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .nav-pills .nav-link {
            border-radius: 25px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .badge {
            border-radius: 20px;
            padding: 0.5em 0.8em;
        }
        
        .text-primary {
            color: #667eea !important;
        }
    </style>
</head>
<body>
    <!-- Fixed Sidebar Toggle Button -->
    <button class="sidebar-toggle-fixed" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Main Content -->
        <main class="main-content" style="margin-left: 0; width: 100%;">
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
                </div>
            <?php endif; ?>
            <!-- Dashboard Header -->
            <div class="dashboard-header fade-in">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="mb-0">
                            <i class="fas fa-qrcode text-success me-2"></i>
                            ROTC QR Inventory Dashboard
                        </h1>
                        <p class="text-muted mb-0">Manage inventory through QR scanning</p>
                    </div>
                <div class="col-md-6">
                    <?php if ($current_duty_officer): ?>
                        <div class="duty-officer-card">
                            <h6 class="mb-1">Current Duty Officer</h6>
                            <?php 
                                $offName = $current_duty_officer['name'] ?? ($current_duty_officer['username'] ?? 'Officer');
                                $rp = '';
                                if (!empty($current_duty_officer['rank_position'])) { $rp = $current_duty_officer['rank_position']; }
                                elseif (!empty($current_duty_officer['rank']) || !empty($current_duty_officer['position'])) {
                                    $rp = trim(($current_duty_officer['rank'] ?? '') . ' ' . ($current_duty_officer['position'] ?? ''));
                                }
                            ?>
                            <strong><?php echo htmlspecialchars($offName); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($rp); ?><?php if (!empty($current_duty_officer['platoon'])): ?> - <?php echo htmlspecialchars($current_duty_officer['platoon']); ?><?php endif; ?></small>
                            <a href="?logout=1" class="btn btn-sm btn-outline-light mt-2 me-2">Change Officer</a>
                            <button type="button" id="toggleChangePin" class="btn btn-sm btn-outline-warning mt-2">Change PIN</button>
                            <div id="changePinPanel" class="mt-3 d-none">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-3 col-12">
                                        <input type="password" id="curPin" class="form-control form-control-sm" placeholder="Current PIN">
                                    </div>
                                    <div class="col-md-3 col-12">
                                        <input type="password" id="newPin" class="form-control form-control-sm" placeholder="New PIN">
                                    </div>
                                    <div class="col-md-3 col-12">
                                        <input type="password" id="confPin" class="form-control form-control-sm" placeholder="Confirm PIN">
                                    </div>
                                    <div class="col-md-3 col-12">
                                        <button type="button" id="updatePinBtn" class="btn btn-success btn-sm w-100">Update PIN</button>
                                    </div>
                                </div>
                                <div id="changePinMsg" class="small mt-2 text-muted"></div>
                            </div>
                            <script>
                            (function(){
                                const t = document.getElementById('toggleChangePin');
                                const p = document.getElementById('changePinPanel');
                                const m = document.getElementById('changePinMsg');
                                const u = document.getElementById('updatePinBtn');
                                if (t) t.addEventListener('click', ()=>{ p.classList.toggle('d-none'); m.textContent=''; m.className='small mt-2 text-muted'; });
                                if (u) u.addEventListener('click', async ()=>{
                                    const cur = document.getElementById('curPin').value.trim();
                                    const np  = document.getElementById('newPin').value.trim();
                                    const cp  = document.getElementById('confPin').value.trim();
                                    if (!cur || !np || !cp) { m.textContent='Please fill out all fields.'; m.classList.add('text-danger'); return; }
                                    if (np !== cp) { m.textContent='New PIN and confirmation do not match.'; m.classList.add('text-danger'); return; }
                                    m.textContent='Updating PIN...'; m.classList.remove('text-danger'); m.classList.add('text-muted');
                                    try {
                                        const res = await fetch('api/change_duty_officer_pin.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ current_pin: cur, new_pin: np, confirm_pin: cp })
                                        });
                                        const data = await res.json();
                                        if (data && data.success) {
                                            m.textContent='PIN updated successfully.'; m.classList.remove('text-danger'); m.classList.add('text-success');
                                            document.getElementById('curPin').value=''; document.getElementById('newPin').value=''; document.getElementById('confPin').value='';
                                        } else {
                                            m.textContent=(data && (data.error||data.message)) ? (data.error||data.message) : 'Failed to update PIN.'; m.classList.remove('text-muted'); m.classList.add('text-danger');
                                        }
                                    } catch(e) {
                                        m.textContent='Network error. Please try again.'; m.classList.remove('text-muted'); m.classList.add('text-danger');
                                    }
                                });
                            })();
                            </script>
                        </div>
                    <?php else: ?>
                        <div class="duty-officer-card">
                            <h6 class="mb-2">Select Duty Officer</h6>
                            <form method="POST">
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-5">
                                        <select name="officer_id" class="form-select form-select-sm" required>
                                            <option value="">Choose Officer...</option>
                                            <?php foreach ($officers as $officer): ?>
                                                <option value="<?php echo $officer['id']; ?>">
                                                    <?php 
                                                        $n = $officer['name'] ?? ($officer['username'] ?? 'Officer');
                                                        $rps = '';
                                                        if (!empty($officer['rank_position'])) { $rps = $officer['rank_position']; }
                                                        else { $rps = trim(($officer['rank'] ?? '') . ' ' . ($officer['position'] ?? '')); }
                                                        echo htmlspecialchars($n . ($rps ? ' - ' . $rps : ''));
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <input type="password" name="pin" class="form-control form-control-sm" placeholder="Officer PIN" required>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <button type="submit" name="select_duty_officer" class="btn btn-primary btn-sm w-100">Select</button>
                                    </div>
                                </div>
                            </form>
                            
                            <h6 class="mb-2 mt-3">Authenticate existing Duty Officer by PIN</h6>
                            <div class="row g-2 align-items-center">
                                <div class="col-8">
                                    <input type="password" id="authPinInput" class="form-control form-control-sm" placeholder="Enter Duty Officer PIN">
                                </div>
                                <div class="col-4">
                                    <button type="button" id="authPinBtn" class="btn btn-outline-success btn-sm w-100">Authenticate</button>
                                </div>
                            </div>
                            <div id="authPinMsg" class="small mt-1 text-muted"></div>
                            <script>
                            (function(){
                                const btn = document.getElementById('authPinBtn');
                                if (!btn) return;
                                btn.addEventListener('click', async function(){
                                    const input = document.getElementById('authPinInput');
                                    const msg = document.getElementById('authPinMsg');
                                    const pin = (input?.value || '').trim();
                                    if (!pin) { msg.textContent = 'Please enter a PIN.'; return; }
                                    msg.textContent = 'Authenticating...';
                                    try {
                                        const res = await fetch('api/authenticate_duty_officer.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ pin })
                                        });
                                        const data = await res.json();
                                        if (data && data.success && data.officer && data.officer.id) {
                                            // Post back to set the duty officer session via existing handler
                                            const form = document.createElement('form');
                                            form.method = 'POST';
                                            form.style.display = 'none';
                                            form.innerHTML = '<input type="hidden" name="officer_id" value="' + data.officer.id + '"><input type="hidden" name="pin" value="' + pin.replace(/"/g,'&quot;') + '"><input type="hidden" name="select_duty_officer" value="1">';
                                            document.body.appendChild(form);
                                            form.submit();
                                        } else {
                                            msg.textContent = (data && (data.error || data.message)) ? data.error || data.message : 'Authentication failed.';
                                            msg.classList.remove('text-muted');
                                            msg.classList.add('text-danger');
                                        }
                                    } catch (e) {
                                        msg.textContent = 'Network error. Please try again.';
                                        msg.classList.remove('text-muted');
                                        msg.classList.add('text-danger');
                                    }
                                });
                            })();
                            </script>
                            <hr>
                            <h6 class="mb-2">Or Add New Duty Officer</h6>
                            <?php if (!empty($add_error)): ?>
                                <div class="alert alert-danger py-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($add_error); ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="text" name="officer_name" class="form-control form-control-sm" placeholder="Full Name" value="<?php echo isset($_POST['officer_name']) ? htmlspecialchars($_POST['officer_name']) : '';?>" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="text" name="rank_position" class="form-control form-control-sm" placeholder="Rank/Position" value="<?php echo isset($_POST['rank_position']) ? htmlspecialchars($_POST['rank_position']) : '';?>" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="password" name="new_pin" class="form-control form-control-sm" placeholder="Set PIN" required>
                                    </div>
                                    <div class="col-6">
                                        <input type="password" name="confirm_pin" class="form-control form-control-sm" placeholder="Confirm PIN" required>
                                    </div>
                                    
                                    <div class="col-12">
                                        <button type="submit" name="add_duty_officer" class="btn btn-success btn-sm">Add Duty Officer</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>

            <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total_items'] ?? 0; ?></div>
                    <div class="text-muted">Total Items</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['available_items'] ?? 0; ?></div>
                    <div class="text-muted">Available</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['borrowed_items'] ?? 0; ?></div>
                    <div class="text-muted">Borrowed</div>
                </div>
            </div>
        </div>

        <?php if (!$current_duty_officer): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Please select a duty officer to access inventory functions.
            </div>
        <?php else: ?>
            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs" id="inventoryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="borrow-tab" data-bs-toggle="tab" data-bs-target="#borrow" type="button" role="tab">
                        <i class="fas fa-hand-holding me-2"></i>Borrow
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="return-tab" data-bs-toggle="tab" data-bs-target="#return" type="button" role="tab">
                        <i class="fas fa-undo me-2"></i>Return
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="supply-tab" data-bs-toggle="tab" data-bs-target="#supply" type="button" role="tab">
                        <i class="fas fa-plus-circle me-2"></i>Supply
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                        <i class="fas fa-list me-2"></i>Logs
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="inventoryTabContent">
                <!-- Borrow Tab -->
                <div class="tab-pane fade show active" id="borrow" role="tabpanel">
                    <h4><i class="fas fa-hand-holding text-success me-2"></i>Borrow Items</h4>
                    <p class="text-muted">Add multiple items to cart and process borrowing requests</p>
                    
                    <!-- Item Selection Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add Items to Cart</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Select Item</label>
                                        <div class="item-selector-container">
                                            <button type="button" class="btn btn-outline-primary w-100" id="itemSelectorBtn">
                                                <i class="fas fa-search me-2"></i>Browse Items by Category
                                            </button>
                                            <input type="hidden" id="selectedItemCode">
                                            <div id="selectedItemDisplay" class="mt-2" style="display: none;">
                                                <div class="alert alert-info">
                                                    <strong>Selected:</strong> <span id="selectedItemName"></span><br>
                                                    <small>Code: <span id="selectedItemCodeDisplay"></span> | Available: <span id="selectedItemQuantity"></span></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" class="form-control" id="itemQuantity" min="1" value="1">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="button" class="btn btn-success w-100" id="addToCartBtn" disabled>
                                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cart Section -->
                    <div class="card mb-4" id="cartSection" style="display: none;">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Items in Cart</h5>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="clearCartBtn">
                                <i class="fas fa-trash me-1"></i>Clear Cart
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="cartItems">
                                <!-- Cart items will be displayed here -->
                            </div>
                            <div class="text-end mt-3">
                                <strong>Total Items: <span id="cartItemCount">0</span></strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Borrower and Details Section -->
                    <form id="borrowForm">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Borrower Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-8">
                                        <label for="borrowerSelect" class="form-label">Select Borrower</label>
                                        <select class="form-select" id="borrowerSelect" required>
                                            <option value="">Choose a borrower...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addBorrowerModal">
                                                <i class="fas fa-plus"></i> Add New
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="guestBorrowerBtn">
                                                <i class="fas fa-user-clock"></i> Guest
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3" id="pinValidationRow" style="display: none;">
                                    <div class="col-md-6">
                                        <label for="borrowerPin" class="form-label">Enter PIN</label>
                                        <input type="password" class="form-control" id="borrowerPin" maxlength="6" placeholder="6-digit PIN">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-success" id="validatePinBtn">
                                            <i class="fas fa-check"></i> Validate PIN
                                        </button>
                                    </div>
                                </div>
                                <div id="borrowerInfo" style="display: none;" class="alert alert-success mt-2">
                                    <strong>Selected Borrower:</strong> <span id="selectedBorrowerName"></span>
                                </div>

                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="purpose" class="form-label">Purpose <small class="text-muted">(Optional)</small></label>
                                            <input type="text" class="form-control" id="purpose" name="purpose" placeholder="e.g., Training, Exercise">
                                            <small class="form-text text-muted">Optional - reason for borrowing</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="returnDate" class="form-label">Expected Return Date</label>
                                            <input type="date" class="form-control" id="returnDate" name="expected_return_date" required>
                                            <small class="form-text text-muted">Default is same day, change if needed</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg" id="processBorrowBtn" disabled>
                                        <i class="fas fa-save me-2"></i>Process Multiple Borrow Request
                                    </button>
                                    <div class="mt-2">
                                        <small class="text-muted">Add items to cart before processing</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Return Tab -->
                <div class="tab-pane fade" id="return" role="tabpanel">
                    <h4><i class="fas fa-undo text-success me-2"></i>Return Items</h4>
                    <p class="text-muted">Process item returns</p>
                    
                    <!-- Category Filter for Returns -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="returnCategoryFilter" class="form-label">Filter by Category</label>
                            <select class="form-select" id="returnCategoryFilter">
                                <option value="">All Categories</option>
                                <option value="Consumable">Consumable</option>
                                <option value="Non-consumable">Non-consumable</option>
                                <option value="Semi-expendable">Semi-expendable</option>
                                <option value="Capital">Capital Assets</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="returnBorrowerFilter" class="form-label">Filter by Borrower</label>
                            <select class="form-select" id="returnBorrowerFilter">
                                <option value="">All Borrowers</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-primary" onclick="loadBorrowedItems()">
                                    <i class="fas fa-search"></i> Load Borrowed Items
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manual Transaction Search -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Manual Transaction Search</h6>
                        </div>
                        <div class="card-body">
                            <form id="returnForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="transactionId" class="form-label">Transaction ID</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="transactionId" name="transaction_id" placeholder="Enter transaction ID" value="<?php echo htmlspecialchars($qr_data); ?>" required>
                                            <button type="button" class="btn btn-outline-secondary qr-scan-btn" onclick="simulateQRScan(this)">
                                                <i class="fas fa-qrcode"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">&nbsp;</label>
                                        <div>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-search"></i> Search Transaction
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Borrowed Items Display -->
                    <div id="borrowedItemsContainer" class="mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Currently Borrowed Items</h6>
                            </div>
                            <div class="card-body">
                                <div id="borrowedItemsList">
                                    <p class="text-muted">Click "Load Borrowed Items" to view current borrowed items</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="returnResults" class="mt-4" style="display: none;">
                        <!-- Return results will be displayed here -->
                    </div>
                </div>

                <!-- Supply Tab -->
                <div class="tab-pane fade" id="supply" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Quick Resupply</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="supplyCategory" class="form-label">Category</label>
                                        <select class="form-select" id="supplyCategory" required>
                                            <option value="">All Categories</option>
                                            <option value="Consumable">Consumable Supplies</option>
                                            <option value="Non-consumable">Non-Consumable Supplies</option>
                                            <option value="Semi-expendable">Semi-Expendable Supplies</option>
                                            <option value="Capital">Capital Assets/Equipment</option>
                                            <option value="Disposable">Disposable Supplies</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center flex-wrap">
                                        <button type="button" class="btn btn-primary" id="loadSupplyItemsBtn" onclick="loadSupplyItems()">Load Items</button>
                                        <div class="input-group" style="max-width: 340px;">
                                            <input type="text" class="form-control" id="supplySearchInput" placeholder="Search supplies...">
                                            <button class="btn btn-outline-secondary" type="button" id="supplySearchBtn"><i class="fas fa-search"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Add New Supply Item</h5>
                                </div>
                                <div class="card-body">
                                    <form id="addSupplyForm">
                                        <div class="mb-3">
                                            <label for="newSupplyName" class="form-label">Item Name</label>
                                            <input type="text" class="form-control" id="newSupplyName" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="newSupplyCategory" class="form-label">Category</label>
                                            <select class="form-select" id="newSupplyCategory" required>
                                                <option value="">Select Category</option>
                                                <option value="Consumable">Consumable Supplies</option>
                                                <option value="Non-consumable">Non-Consumable Supplies</option>
                                                <option value="Semi-expendable">Semi-Expendable Supplies</option>
                                                <option value="Capital">Capital Assets/Equipment</option>
                                                <option value="Disposable">Disposable Supplies</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="newSupplyUnit" class="form-label">Unit</label>
                                            <select class="form-select" id="newSupplyUnit" required>
                                                <option value="">Select Unit</option>
                                                <option value="pieces">Pieces</option>
                                                <option value="packs">Packs</option>
                                                <option value="boxes">Boxes</option>
                                                <option value="bottles">Bottles</option>
                                                <option value="rolls">Rolls</option>
                                                <option value="sheets">Sheets</option>
                                                <option value="pairs">Pairs</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="newSupplyQuantity" class="form-label">Initial Quantity</label>
                                            <input type="number" class="form-control" id="newSupplyQuantity" min="1" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="newSupplyReturnable" class="form-label">Returnable Status</label>
                                            <select class="form-select" id="newSupplyReturnable" required>
                                                <option value="returnable">Returnable (can be returned to stock)</option>
                                                <option value="non-returnable">Non-returnable (consumable item)</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-success">Add Supply Item</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Supply Items</h5>
                                    <button type="button" class="btn btn-sm btn-success" id="processResupplyBtn" style="display: none;">Process Selected Resupply</button>
                                </div>
                                <div class="card-body">
                                    <div id="supplyItemsList">
                                        <p class="text-muted">Select a category and click "Load Items" to view supply items.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logs Tab -->
                <div class="tab-pane fade" id="logs" role="tabpanel">
                    <h4><i class="fas fa-list text-secondary me-2"></i>Transaction Logs</h4>
                    <p class="text-muted">Recent inventory transactions</p>
                    <!-- Summary Badges (filled by loadTextLogs via api/read_log.php summary) -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6 col-md-3">
                            <div class="stats-card">
                                <div class="stats-number" id="logSummaryItems">-</div>
                                <div class="text-muted">Item Rows</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="stats-card">
                                <div class="stats-number" id="logSummaryTotal">-</div>
                                <div class="text-muted">Total Qty</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="stats-card">
                                <div class="stats-number" id="logSummaryAvailable">-</div>
                                <div class="text-muted">Available Qty</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="stats-card">
                                <div class="stats-number" id="logSummaryBorrowed">-</div>
                                <div class="text-muted">Borrowed Qty</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Type</th>
                                    <th>Officer</th>
                                    <th>Borrower</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $transaction['type'] == 'borrow' ? 'success' : ($transaction['type'] == 'return' ? 'success' : 'info'); ?>">
                                            <?php echo ucfirst($transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['officer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['borrower_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $transaction['status'] == 'completed' ? 'success' : ($transaction['status'] == 'pending' ? 'success' : 'secondary'); ?>">
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Logs Filters -->
                    <div class="row g-2 align-items-end mt-3">
                        <div class="col-sm-6 col-md-3">
                            <label class="form-label" for="logTypeFilter">Type</label>
                            <select id="logTypeFilter" class="form-select">
                                <option value="ALL">All</option>
                                <option value="BORROW">Borrow</option>
                                <option value="RETURN">Return</option>
                                <option value="SUPPLY">Supply</option>
                                <option value="ERROR">Error</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <label class="form-label" for="logSearchInput">Search</label>
                            <input type="text" id="logSearchInput" class="form-control" placeholder="Search in logs...">
                        </div>
                        <div class="col-sm-12 col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="logOnlyMatches">
                                <label class="form-check-label" for="logOnlyMatches">Show only matches</label>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Text Logs (inventory.log)</h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-light" onclick="loadTextLogs(300)"><i class="fas fa-rotate"></i> Refresh</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <pre id="textLogsContent" class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow:auto; white-space: pre-wrap;">Click Refresh to load logs...</pre>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        </main>
    </div>

    <!-- Item Selection Modal -->
    <div class="modal fade" id="itemSelectionModal" tabindex="-1" aria-labelledby="itemSelectionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-dark">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemSelectionModalLabel">
                        <i class="fas fa-boxes me-2"></i>Select Item to Borrow
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Search Bar -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="input-group">
                                <input type="text" class="form-control" id="itemSearchInput" placeholder="Search items by name, code, or description...">
                                <button class="btn btn-outline-secondary" type="button" id="searchItemsBtn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="categoryFilter">
                                <option value="">All Categories</option>
                                <option value="Consumable">Consumable Supplies</option>
                                <option value="Non-consumable">Non-Consumable Supplies</option>
                                <option value="Semi-expendable">Semi-Expendable Supplies</option>
                                <option value="Capital">Capital Assets/Equipment</option>
                                <option value="Disposable">Disposable Supplies</option>
                            </select>
                        </div>
                    </div>

                    <!-- Category Tabs -->
                    <ul class="nav nav-pills mb-3" id="categoryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="all-tab" data-bs-toggle="pill" data-bs-target="#all-items" type="button" role="tab">
                                <i class="fas fa-th-large me-2"></i>All Items
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="consumable-tab" data-bs-toggle="pill" data-bs-target="#consumable-items" type="button" role="tab">
                                <i class="fas fa-battery-half me-2"></i>Consumable
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="non-consumable-tab" data-bs-toggle="pill" data-bs-target="#non-consumable-items" type="button" role="tab">
                                <i class="fas fa-tools me-2"></i>Non-Consumable
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="semi-expendable-tab" data-bs-toggle="pill" data-bs-target="#semi-expendable-items" type="button" role="tab">
                                <i class="fas fa-usb me-2"></i>Semi-Expendable
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="capital-tab" data-bs-toggle="pill" data-bs-target="#capital-items" type="button" role="tab">
                                <i class="fas fa-desktop me-2"></i>Capital Assets
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="disposable-tab" data-bs-toggle="pill" data-bs-target="#disposable-items" type="button" role="tab">
                                <i class="fas fa-trash me-2"></i>Disposable
                            </button>
                        </li>
                    </ul>

                    <!-- Items Grid -->
                    <div class="tab-content" id="itemsTabContent">
                        <div class="tab-pane fade show active" id="all-items" role="tabpanel">
                            <div id="itemsGrid" class="items-css-grid">
                                <!-- Items will be loaded here -->
                            </div>
                        </div>
                        <div class="tab-pane fade" id="consumable-items" role="tabpanel">
                            <div id="consumableItemsGrid" class="items-css-grid">
                                <!-- Consumable items will be loaded here -->
                            </div>
                        </div>
                        <div class="tab-pane fade" id="non-consumable-items" role="tabpanel">
                            <div id="nonConsumableItemsGrid" class="items-css-grid">
                                <!-- Non-consumable items will be loaded here -->
                            </div>
                        </div>
                        <div class="tab-pane fade" id="semi-expendable-items" role="tabpanel">
                            <div id="semiExpendableItemsGrid" class="items-css-grid">
                                <!-- Semi-expendable items will be loaded here -->
                            </div>
                        </div>
                        <div class="tab-pane fade" id="capital-items" role="tabpanel">
                            <div id="capitalItemsGrid" class="items-css-grid">
                                <!-- Capital assets will be loaded here -->
                            </div>
                        </div>
                        <div class="tab-pane fade" id="disposable-items" role="tabpanel">
                            <div id="disposableItemsGrid" class="items-css-grid">
                                <!-- Disposable items will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="itemsLoading" class="text-center py-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading items...</p>
                    </div>

                    <!-- No Items Message -->
                    <div id="noItemsMessage" class="text-center py-4" style="display: none;">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No items found matching your criteria.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Borrower Modal -->
    <div class="modal fade" id="addBorrowerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Borrower</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addBorrowerForm">
                        <div class="mb-3">
                            <label for="newBorrowerName" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="newBorrowerName" required>
                        </div>
                        <div class="mb-3">
                            <label for="newBorrowerPin" class="form-label">6-Digit PIN *</label>
                            <input type="password" class="form-control" id="newBorrowerPin" maxlength="6" pattern="[0-9]{6}" required>
                            <small class="form-text text-muted">Enter exactly 6 digits</small>
                        </div>
                        <div class="mb-3">
                            <label for="newBorrowerRank" class="form-label">Rank/Position</label>
                            <input type="text" class="form-control" id="newBorrowerRank" placeholder="e.g., Cadet, Officer">
                        </div>
                        <div class="mb-3">
                            <label for="newBorrowerUnit" class="form-label">Unit</label>
                            <input type="text" class="form-control" id="newBorrowerUnit" placeholder="e.g., ROTC Unit">
                        </div>
                        <div class="mb-3">
                            <label for="newBorrowerContact" class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" id="newBorrowerContact" placeholder="e.g., 09123456789">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveBorrowerBtn">
                        <i class="fas fa-save"></i> Save Borrower
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php $asset_ver = @filemtime(__DIR__ . '/js/inventory.js') ?: time(); ?>
    <script src="js/inventory.js?v=<?php echo $asset_ver; ?>"></script>
    <script>
        // Log JS version to help debug cache issues
        console.log('inventory.js version:', '<?php echo $asset_ver; ?>');
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar removed - no toggle functionality needed
        });
        
        // Handle logout
        <?php if (isset($_GET['logout'])): ?>
        <?php unset($_SESSION['duty_officer_id']); ?>
        window.location.href = 'dashboard.php';
        <?php endif; ?>
    </script>
</body>
</html>