<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['success' => true]);
    exit;
}

// Try to init DB ($pdo)
$pdo = null;
try {
    // Prefer shared include if available
    $inc = __DIR__ . '/../includes/db.php';
    if (file_exists($inc)) {
        require_once $inc; // should set $pdo
    } else {
        // Fallback to config/database.php (DSN based)
        $cfg = __DIR__ . '/../config/database.php';
        if (file_exists($cfg)) {
            require_once $cfg; // should expose $dsn, $username, $password, $options
            if (!isset($pdo) && isset($dsn)) {
                $pdo = new PDO($dsn, $username, $password, $options);
            }
        }
    }
} catch (Exception $e) { /* ignore DB init failures for log read */ }

// Helpers for schema-aware queries
function colExistsRL($pdo, $table, $col) {
    if (!$pdo) return false;
    try {
        $qcol = $pdo->quote($col);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$qcol}");
        if ($stmt && $stmt->fetch()) return true;
    } catch (Exception $e) {}
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $col]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) { return false; }
}
function pickColRL($pdo, $table, $cands) { foreach ($cands as $c) { if (colExistsRL($pdo, $table, $c)) return $c; } return null; }
function tableExistsRL($pdo, $table) {
    if (!$pdo) return false;
    try {
        $q = $pdo->quote($table);
        $s = $pdo->query("SHOW TABLES LIKE {$q}");
        if ($s && $s->fetch()) return true;
    } catch (Exception $e) {}
    try {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $st->execute([$table]);
        return (bool)$st->fetch();
    } catch (Exception $e) { return false; }
}

try {
    $tail = isset($_GET['tail']) ? (int)$_GET['tail'] : 200;
    if ($tail <= 0) { $tail = 200; }

    // logs directory lives at project root (two levels up from this api directory)
    $base = dirname(dirname(__DIR__));
    $logDir = $base . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'inventory.log';

    if (!file_exists($logFile)) {
        // Create empty file so UI doesn't error out
        @file_put_contents($logFile, "", FILE_APPEND);
    }

    // Tail last N lines efficiently
    $fp = fopen($logFile, 'r');
    if ($fp === false) {
        echo json_encode(['success' => false, 'message' => 'Unable to open log file', 'path' => $logFile]);
        exit;
    }

    $buffer = '';
    $chunkSize = 4096;
    $lines = [];
    $lineCount = 0;

    fseek($fp, 0, SEEK_END);
    $fileSize = ftell($fp);

    while ($fileSize > 0 && $lineCount <= $tail) {
        $seek = max($fileSize - $chunkSize, 0);
        $readSize = $fileSize - $seek;
        fseek($fp, $seek);
        $chunk = fread($fp, $readSize);
        $buffer = $chunk . $buffer;
        $fileSize = $seek;
        // Count lines
        $lines = explode("\n", $buffer);
        $lineCount = count($lines) - 1; // last may be incomplete
        if ($fileSize === 0) break;
    }

    fclose($fp);

    // Get only the last N lines
    if ($lineCount > $tail) {
        $lines = array_slice($lines, -$tail - 1); // include possible last empty
    }

    // Optionally exclude DEBUG lines from the response (default: exclude)
    $includeDebug = false;
    if (isset($_GET['include_debug'])) {
        $val = strtolower((string)$_GET['include_debug']);
        $includeDebug = ($val === '1' || $val === 'true' || $val === 'yes');
    }
    if (!$includeDebug) {
        $lines = array_values(array_filter($lines, function($ln){
            // Match lines containing a [DEBUG] type tag
            return !preg_match('/\[\s*DEBUG\s*\]/i', $ln);
        }));
    }

    // Show latest first (reverse chronological)
    $lines = array_reverse($lines);

    $content = trim(implode("\n", $lines));

    // Compute summary counts (schema-aware)
    $summary = null;
    if ($pdo) {
        try {
            // Decide items table first
            $itemsTable = tableExistsRL($pdo, 'items') ? 'items' : (tableExistsRL($pdo, 'inventory_items') ? 'inventory_items' : null);
            if ($itemsTable === null) { throw new Exception('No items table found'); }
            $totalCol = pickColRL($pdo, $itemsTable, ['total_quantity','quantity_total','total','quantity','stock']);
            $availCol = pickColRL($pdo, $itemsTable, ['available_quantity','quantity_available','qty_available','available','qty']);
            $borrCol  = colExistsRL($pdo, $itemsTable, 'borrowed_quantity') ? 'borrowed_quantity' : null;
            $sumTotal = 0; $sumAvail = 0; $sumBorr = 0; $itemCount = 0;

            // Always compute items totals for total/available
            $sel = [];
            if ($totalCol) $sel[] = "SUM($totalCol) AS sum_total";
            if ($availCol) $sel[] = "SUM($availCol) AS sum_avail";
            if (empty($sel)) { $sel[] = 'COUNT(*) AS cnt'; }
            $sql = 'SELECT ' . implode(',', $sel) . ', COUNT(*) AS item_count FROM ' . $itemsTable;
            $st = $pdo->query($sql);
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
            if ($row) {
                $sumTotal = (int)($row['sum_total'] ?? 0);
                $sumAvail = (int)($row['sum_avail'] ?? 0);
                $itemCount = (int)($row['item_count'] ?? 0);
            }

            // Prefer borrowed count from outstanding borrowed_items if table exists
            if (tableExistsRL($pdo, 'borrowed_items')) {
                // Detect columns
                $hasQtyBorrowed = colExistsRL($pdo, 'borrowed_items', 'quantity_borrowed');
                $hasQty = colExistsRL($pdo, 'borrowed_items', 'quantity');
                $hasStatus = colExistsRL($pdo, 'borrowed_items', 'status');
                $retDateCol = null;
                foreach (['actual_return_date','returned_at','return_date','date_returned'] as $cand) {
                    if (colExistsRL($pdo, 'borrowed_items', $cand)) { $retDateCol = $cand; break; }
                }
                $hasReturnable = colExistsRL($pdo, $itemsTable, 'can_be_returned');
                $qtyExpr = $hasQtyBorrowed ? 'bi.quantity_borrowed' : ($hasQty ? 'bi.quantity' : '1');

                $where = [];
                if ($hasStatus) { $where[] = "(bi.status IS NULL OR TRIM(bi.status) = '' OR LOWER(bi.status) NOT IN ('returned','complete','completed'))"; }
                if ($retDateCol) { $where[] = "(bi.{$retDateCol} IS NULL OR bi.{$retDateCol} = '' OR bi.{$retDateCol} = '0000-00-00' OR bi.{$retDateCol} = '0000-00-00 00:00:00')"; }
                if ($hasReturnable) { $where[] = "i.can_be_returned = 'returnable'"; }
                $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

                $sqlB = "SELECT SUM($qtyExpr) AS sum_borrowed FROM borrowed_items bi JOIN {$itemsTable} i ON bi.item_id = i.id {$whereSql}";
                $rb = $pdo->query($sqlB);
                $rbr = $rb ? $rb->fetch(PDO::FETCH_ASSOC) : null;
                $sumBorr = (int)($rbr['sum_borrowed'] ?? 0);
            } else {
                // Fallbacks: borrowed_quantity column or total-available
                if ($borrCol) {
                    $rb = $pdo->query('SELECT SUM(' . $borrCol . ') AS sum_borrowed FROM ' . $itemsTable);
                    $rbr = $rb ? $rb->fetch(PDO::FETCH_ASSOC) : null;
                    $sumBorr = (int)($rbr['sum_borrowed'] ?? 0);
                } else if ($totalCol && $availCol) {
                    $sumBorr = max(0, $sumTotal - $sumAvail);
                } else {
                    $sumBorr = 0;
                }
            }

            $summary = [
                'items_count' => $itemCount,
                'total' => $sumTotal,
                'available' => $sumAvail,
                'borrowed' => $sumBorr
            ];
        } catch (Exception $e) {
            $summary = null;
        }
    }

    echo json_encode([
        'success' => true,
        'lines' => $lines,
        'content' => $content,
        'summary' => $summary,
        'log_path' => $logFile
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
