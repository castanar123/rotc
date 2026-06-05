<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');
// Prevent PHP notices/warnings from corrupting JSON output
@ini_set('display_errors', '0');
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Decode JSON body once (if present) and extract action for JSON requests
$RAW_BODY = file_get_contents('php://input');
$JSON_INPUT = null;
if (!empty($RAW_BODY)) {
    $tmp = json_decode($RAW_BODY, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $JSON_INPUT = $tmp;
        if (empty($action) && isset($JSON_INPUT['action'])) {
            $action = $JSON_INPUT['action'];
        }

// Helper: resolve duty officer display name safely across schema variations
function getDutyOfficerName($pdo, $officer_id) {
    if (empty($officer_id)) return 'Unknown';
    try {
        $stmt = $pdo->prepare("SELECT * FROM officers WHERE id = ? LIMIT 1");
        $stmt->execute([$officer_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 'Officer#' . $officer_id;
        $cands = [];
        if (!empty($row['rank_position'])) $cands[] = $row['rank_position'];
        if (!empty($row['name'])) $cands[] = $row['name'];
        if (!empty($row['full_name'])) $cands[] = $row['full_name'];
        if (!empty($row['fullname'])) $cands[] = $row['fullname'];
        $full = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($full !== '') $cands[] = $full;
        $rankPos = trim(($row['rank'] ?? '') . ' ' . ($row['position'] ?? ''));
        if ($rankPos !== '') $cands[] = $rankPos;
        foreach ($cands as $n) { if (!empty($n)) return $n; }
        return 'Officer#' . $officer_id;
    } catch (Exception $e) {
        return 'Officer#' . $officer_id;
    }
}

// Helper: append a single line to logs/inventory.log
function inv_append_log($type, $details) {
    $ts = date('Y-m-d H:i:s');
    $line = "[{$ts}] [{$type}] " . $details;
    $base = dirname(dirname(__DIR__)); // project root
    $dir = $base . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $file = $dir . DIRECTORY_SEPARATOR . 'inventory.log';
    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
// (moved below tableExists)

// If the found item comes from a non-primary table, try to map it to the primary items row
function mapToPrimaryInventoryItem($pdo, $found) {
    $primary = getItemsTable($pdo); // forced to 'items'
    if ($found && isset($found['table']) && $found['table'] !== $primary) {
        $src = $found['row'];
        // Prefer mapping by item_code, else by item_name into primary 'items'
        if (columnExists($pdo, $found['table'], 'item_code') && columnExists($pdo, $primary, 'item_code') && !empty($src['item_code'])) {
            $stmt = $pdo->prepare("SELECT * FROM {$primary} WHERE item_code = ? LIMIT 1");
            $stmt->execute([$src['item_code']]);
            $row = $stmt->fetch();
            if ($row) return ['table' => $primary, 'row' => $row];
        }
        // Fallback: try by item_name
        if (!empty($src['item_name'])) {
            $stmt = $pdo->prepare("SELECT * FROM {$primary} WHERE item_name = ? LIMIT 1");
            $stmt->execute([$src['item_name']]);
            $row = $stmt->fetch();
            if ($row) return ['table' => $primary, 'row' => $row];
        }
    }
    return $found;
}
    }
}

// Expose decoded JSON to handlers to avoid re-reading php://input
$GLOBALS['_JSON_INPUT'] = $JSON_INPUT;

// Ensure helpers are available regardless of JSON body presence
if (!function_exists('getDutyOfficerName')) {
function getDutyOfficerName($pdo, $officer_id) {
    if (empty($officer_id)) return 'Unknown';
    try {
        $stmt = $pdo->prepare("SELECT * FROM officers WHERE id = ? LIMIT 1");
        $stmt->execute([$officer_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 'Officer#' . $officer_id;
        $cands = [];
        if (!empty($row['rank_position'])) $cands[] = $row['rank_position'];
        if (!empty($row['name'])) $cands[] = $row['name'];
        if (!empty($row['full_name'])) $cands[] = $row['full_name'];
        if (!empty($row['fullname'])) $cands[] = $row['fullname'];
        $full = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($full !== '') $cands[] = $full;
        $rankPos = trim(($row['rank'] ?? '') . ' ' . ($row['position'] ?? ''));
        if ($rankPos !== '') $cands[] = $rankPos;
        foreach ($cands as $n) { if (!empty($n)) return $n; }
        return 'Officer#' . $officer_id;
    } catch (Exception $e) {
        return 'Officer#' . $officer_id;
    }
}
}

if (!function_exists('inv_append_log')) {
function inv_append_log($type, $details) {
    $ts = date('Y-m-d H:i:s');
    $line = "[{$ts}] [{$type}] " . $details;
    $base = dirname(dirname(__DIR__)); // project root
    $dir = $base . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $file = $dir . DIRECTORY_SEPARATOR . 'inventory.log';
    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
}

if (!function_exists('mapToPrimaryInventoryItem')) {
function mapToPrimaryInventoryItem($pdo, $found) {
    $primary = getItemsTable($pdo); // forced to 'items'
    if ($found && isset($found['table']) && $found['table'] !== $primary) {
        $src = $found['row'];
        // Prefer mapping by item_code, else by item_name into primary 'items'
        if (columnExists($pdo, $found['table'], 'item_code') && columnExists($pdo, $primary, 'item_code') && !empty($src['item_code'])) {
            $stmt = $pdo->prepare("SELECT * FROM {$primary} WHERE item_code = ? LIMIT 1");
            $stmt->execute([$src['item_code']]);
            $row = $stmt->fetch();
            if ($row) return ['table' => $primary, 'row' => $row];
        }
        // Fallback: try by item_name
        if (!empty($src['item_name'])) {
            $stmt = $pdo->prepare("SELECT * FROM {$primary} WHERE item_name = ? LIMIT 1");
            $stmt->execute([$src['item_name']]);
            $row = $stmt->fetch();
            if ($row) return ['table' => $primary, 'row' => $row];
        }
    }
    return $found;
}
}

// Insert into transactions table using only columns that exist
function insertTransactionRecord($pdo, $data) {
    $possible = ['transaction_id','type','duty_officer_id','borrower_name','borrower_id','borrower_contact','purpose','expected_return_date','status','notes'];
    $cols = [];
    $params = [];
    foreach ($possible as $col) {
        if (!array_key_exists($col, $data)) continue;
        if (!columnExists($pdo, 'transactions', $col)) continue;
        // Only include status if value is allowed by schema
        if ($col === 'status') {
            if (!statusValueAllowed($pdo, 'transactions', $data[$col])) {
                continue;
            }
        }
        $cols[] = $col;
        $params[] = $data[$col];
    }
    if (empty($cols)) throw new Exception('No valid columns to insert into transactions');
    $placeholders = rtrim(str_repeat('?,', count($cols)), ',');
    $sql = 'INSERT INTO transactions (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

// Allow browsing items without authentication
if ($action === 'get_items') {
    getItems();
    exit;
}

// Utility: check if column exists on a table
function columnExists($pdo, $table, $column) {
    // First try: direct SHOW query (cannot be prepared server-side on some MySQL setups)
    try {
        $qcol = $pdo->quote($column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$qcol}");
        if ($stmt && $stmt->fetch()) return true;
    } catch (Exception $e) { /* ignore and fallback */ }
    // Fallback: INFORMATION_SCHEMA which supports prepared statements
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Utility: check if a table exists
function tableExists($pdo, $table) {
    // Try direct SHOW TABLES LIKE
    try {
        $q = $pdo->quote($table);
        $stmt = $pdo->query("SHOW TABLES LIKE {$q}");
        if ($stmt && $stmt->fetch()) return true;
    } catch (Exception $e) { /* ignore and fallback */ }
    // Fallback: INFORMATION_SCHEMA
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $stmt->execute([$table]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// Ensure a foreign key on a table/column points to items(id) instead of inventory_items(id)
function ensureItemFKPointsToItems($pdo, $table, $fkColumn) {
    try {
        // Do not attempt DDL while inside an active transaction; MySQL will auto-commit and break flow
        if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) return;
        if (!tableExists($pdo, $table)) return;
        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $create = $row['Create Table'] ?? '';
        if (strpos($create, 'REFERENCES `inventory_items` (`id`)') !== false || strpos($create, 'REFERENCES inventory_items (`id`)') !== false) {
            // Extract the constraint name for the specific fkColumn if possible
            $patternSpecific = '/CONSTRAINT `([^`]+)` FOREIGN KEY \(`' . preg_quote($fkColumn, '/') . '`\) REFERENCES `inventory_items` \(`id`\)/';
            $constraint = null;
            if (preg_match($patternSpecific, $create, $m)) {
                $constraint = $m[1];
            } else {
                // Fallback: find any FK referencing inventory_items
                $patternAny = '/CONSTRAINT `([^`]+)` FOREIGN KEY .* REFERENCES `inventory_items` \(`id`\)/';
                if (preg_match($patternAny, $create, $m2)) {
                    $constraint = $m2[1];
                }
            }
            if ($constraint) {
                $pdo->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }
            // Add a deterministic constraint name
            $newConstraint = $table . '_' . $fkColumn . '_fk_items';
            // Ensure items table exists before adding
            if (tableExists($pdo, 'items')) {
                $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$newConstraint}` FOREIGN KEY (`{$fkColumn}`) REFERENCES `items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
            }
        }
    } catch (Exception $e) {
        // Do not block operations if we cannot migrate FK; borrowing code will proceed without crash fixes
    }
}

// Determine if a string status value is safe to write to the given table's 'status' column
function statusValueAllowed($pdo, $table, $value) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE 'status'");
        $stmt->execute();
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col) return false;
        $type = strtolower($col['Type'] ?? '');
        if (strpos($type, 'enum(') === 0) {
            // Parse enum options
            if (preg_match("/enum\((.*)\)/i", $type, $m)) {
                $opts = array_map(function($s){ return trim($s, "'\" "); }, explode(',', $m[1]));
                return in_array($value, $opts, true);
            }
            return false;
        }
        if (strpos($type, 'int') !== false || strpos($type, 'tinyint') !== false || strpos($type, 'smallint') !== false) {
            // Numeric status not supported by our string values; skip
            return false;
        }
        // varchar/text or others: assume allowed
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Determine which items table to use; FORCED to 'items' per requirement
function getItemsTable($pdo) {
    return 'items';
}

// Determine the correct available quantity column name on items table
function getQtyAvailColumn($pdo, $table) {
    if (columnExists($pdo, $table, 'available_quantity')) return 'available_quantity';
    if (columnExists($pdo, $table, 'quantity_available')) return 'quantity_available';
    if (columnExists($pdo, $table, 'qty_available')) return 'qty_available';
    // Default fallback (will likely fail if not present)
    return 'available_quantity';
}

// Item finder: use only the 'items' table (by item_code/qr_code if present, else numeric id)
function findInventoryItemByCodeOrId($pdo, $code) {
    $table = getItemsTable($pdo); // 'items'
    $hasItemCode = columnExists($pdo, $table, 'item_code');
    $hasQrCode   = columnExists($pdo, $table, 'qr_code');
    if ($hasItemCode || $hasQrCode) {
        $conds = [];
        $params = [];
        if ($hasItemCode) { $conds[] = 'item_code = ?'; $params[] = $code; }
        if ($hasQrCode)   { $conds[] = 'qr_code = ?';   $params[] = $code; }
        if (!empty($conds)) {
            $sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' OR ', $conds) . ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            if ($row) return ['table' => $table, 'row' => $row];
        }
    }
    if (ctype_digit((string)$code)) {
        $byId = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $byId->execute([(int)$code]);
        $row = $byId->fetch();
        if ($row) return ['table' => $table, 'row' => $row];
    }
    return null;
}

// Check if duty officer is selected for other actions
if (!isset($_SESSION['duty_officer_id'])) {
    echo json_encode(['success' => false, 'message' => 'No duty officer selected']);
    exit;
}

$duty_officer_id = $_SESSION['duty_officer_id'];

try {
    switch ($action) {
        case 'borrow':
            handleBorrow($GLOBALS['_JSON_INPUT'] ?? null);
            break;
        case 'multiple_borrow':
            handleMultipleBorrow($GLOBALS['_JSON_INPUT'] ?? []);
            break;
        case 'return':
            handleReturn($GLOBALS['_JSON_INPUT'] ?? $_POST);
            break;
        case 'supply':
            handleSupply($GLOBALS['_JSON_INPUT'] ?? $_POST);
            break;
        case 'search_transaction':
            searchTransaction();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
function handleBorrow($jsonInput = null) {
    global $pdo, $duty_officer_id;
    $itemsTable = getItemsTable($pdo);
    $qtyAvailCol = getQtyAvailColumn($pdo, $itemsTable);

    // Support form POST or JSON
    $src = is_array($jsonInput) ? $jsonInput : $_POST;
    $borrower_name = $src['borrower_name'] ?? '';
    $borrower_id = $src['borrower_id'] ?? '';
    $borrower_contact = $src['borrower_contact'] ?? '';
    $purpose = $src['purpose'] ?? '';
    $expected_return_date = $src['expected_return_date'] ?? '';
    $item_code = $src['item_code'] ?? '';
    $quantity = (int)($src['quantity'] ?? 0);
    
    if (empty($borrower_name) || empty($borrower_id) || empty($purpose) || empty($item_code) || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Required fields: borrower name, ID, purpose, item code, and quantity']);
        return;
    }
    
    // Guard: ensure FK relation is correct BEFORE starting a transaction to avoid DDL auto-commit issues
    ensureItemFKPointsToItems($pdo, 'transaction_items', 'item_id');

    // Check if item exists and has sufficient quantity across supported tables
    $found = findInventoryItemByCodeOrId($pdo, $item_code);
    $found = mapToPrimaryInventoryItem($pdo, $found);
    $item = $found ? $found['row'] : null;
    if ($found) { $itemsTable = $found['table']; }
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        return;
    }
    
    $availableNow = isset($item[$qtyAvailCol]) ? (int)$item[$qtyAvailCol] : 0;
    if ($availableNow < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Insufficient quantity available']);
        return;
    }
    
    // Resolve item name now that we have $itemId
    $itemName = '';
    try {
        $nstmt = $pdo->prepare("SELECT item_name FROM {$itemsTable} WHERE id = ?");
        $nstmt->execute([$itemId]);
        $itemName = (string)($nstmt->fetchColumn() ?: '');
    } catch (Exception $e) { $itemName = ''; }

    $pdo->beginTransaction();
    
    try {
        // Generate transaction ID
        $transaction_id = 'TXN' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
        
        // Create transaction record (schema-aware)
        $transaction_db_id = insertTransactionRecord($pdo, [
            'transaction_id' => $transaction_id,
            'type' => 'borrow',
            'duty_officer_id' => $duty_officer_id,
            'borrower_name' => $borrower_name,
            'borrower_id' => $borrower_id,
            'borrower_contact' => $borrower_contact,
            'purpose' => $purpose,
            'expected_return_date' => $expected_return_date,
            'status' => 'pending'
        ]);
        
        // Create transaction item record (schema-aware: unit_price may not exist)
        $hasUnitPrice = columnExists($pdo, 'transaction_items', 'unit_price');
        if ($hasUnitPrice) {
            $stmtTi = $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity, unit_price) VALUES (?, ?, ?, 0)");
            $stmtTi->execute([$transaction_db_id, $item['id'], $quantity]);
        } else {
            $stmtTi = $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity) VALUES (?, ?, ?)");
            $stmtTi->execute([$transaction_db_id, $item['id'], $quantity]);
        }

        // Update inventory quantities (schema-aware)
        $borrowedCol = columnExists($pdo, $itemsTable, 'borrowed_quantity') ? 'borrowed_quantity' : null;
        $sqlUpdate = "UPDATE {$itemsTable} SET {$qtyAvailCol} = {$qtyAvailCol} - ?";
        $paramsUpd = [$quantity];
        if ($borrowedCol) { $sqlUpdate .= ", {$borrowedCol} = {$borrowedCol} + ?"; $paramsUpd[] = $quantity; }
        $sqlUpdate .= " WHERE id = ?"; $paramsUpd[] = $item['id'];
        $pdo->prepare($sqlUpdate)->execute($paramsUpd);

        // Record into borrowed_items if available
        if (tableExists($pdo, 'borrowed_items')) {
            $cols = ['item_id']; $place = ['?']; $vals = [$item['id']];
            if (columnExists($pdo, 'borrowed_items', 'transaction_id')) { $cols[] = 'transaction_id'; $place[] = '?'; $vals[] = $transaction_db_id; }
            if (columnExists($pdo, 'borrowed_items', 'borrower_id')) { $cols[] = 'borrower_id'; $place[] = '?'; $vals[] = $borrower_id; }
            if (columnExists($pdo, 'borrowed_items', 'borrower_name')) { $cols[] = 'borrower_name'; $place[] = '?'; $vals[] = $borrower_name; }
            $qtyCol = columnExists($pdo, 'borrowed_items', 'quantity_borrowed') ? 'quantity_borrowed' : (columnExists($pdo, 'borrowed_items', 'quantity') ? 'quantity' : null);
            if ($qtyCol) { $cols[] = $qtyCol; $place[] = '?'; $vals[] = $quantity; }
            if (columnExists($pdo, 'borrowed_items', 'purpose')) { $cols[] = 'purpose'; $place[] = '?'; $vals[] = $purpose; }
            if (columnExists($pdo, 'borrowed_items', 'expected_return_date')) { $cols[] = 'expected_return_date'; $place[] = '?'; $vals[] = $expected_return_date; }
            if (columnExists($pdo, 'borrowed_items', 'borrowed_date')) { $cols[] = 'borrowed_date'; $place[] = 'NOW()'; }
            if (columnExists($pdo, 'borrowed_items', 'status')) { $cols[] = 'status'; $place[] = "'borrowed'"; }
            $sqlBi = 'INSERT INTO borrowed_items (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
            $pdo->prepare($sqlBi)->execute($vals);
        }

        // Mark transaction as borrowed if column exists
        if (columnExists($pdo, 'transactions', 'status') && statusValueAllowed($pdo, 'transactions', 'borrowed')) {
            $pdo->prepare("UPDATE transactions SET status = 'borrowed' WHERE id = ?")->execute([$transaction_db_id]);
        }

        $pdo->commit();
        // Log borrow event to text log
        $officerName = getDutyOfficerName($pdo, $duty_officer_id);
        $borrowerDisp = $borrower_name . ($borrower_id ? " (ID:{$borrower_id})" : '');
        inv_append_log('BORROW', "Officer={$officerName} (ID:{$duty_officer_id}) Borrower={$borrowerDisp} Item={$item['item_name']} (ID:{$item['id']}) Qty={$quantity} Txn={$transaction_id} Purpose=" . ($purpose ?: 'N/A') . " ExpectedReturn=" . ($expected_return_date ?: 'N/A'));
        echo json_encode(['success' => true, 'message' => 'Borrow processed successfully', 'transaction_id' => $transaction_id]);
        return;
    } catch (Exception $e) {
        if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'message' => 'Error processing borrow: ' . $e->getMessage()]);
        return;
    }
}

function handleMultipleBorrow($input) {
    global $pdo, $duty_officer_id;
    $itemsTable = getItemsTable($pdo);

    $src = is_array($input) ? $input : $_POST;
    $borrower_id = $src['borrower_id'] ?? '';
    $borrower_name = $src['borrower_name'] ?? '';
    $purpose = $src['purpose'] ?? '';
    $expected_return_date = $src['expected_return_date'] ?? '';
    $items = $src['items'] ?? [];

    if (empty($borrower_id) || empty($borrower_name) || empty($expected_return_date) || !is_array($items) || count($items) === 0) {
        echo json_encode(['success' => false, 'message' => 'Missing borrower or items information']);
        return;
    }

    $qtyAvailCol = getQtyAvailColumn($pdo, $itemsTable);
    $transactionIds = [];
    $processedItems = [];
    $logLines = [];

    // Guard FK before starting transaction
    ensureItemFKPointsToItems($pdo, 'transaction_items', 'item_id');

    $pdo->beginTransaction();
    try {
        $officerName = getDutyOfficerName($pdo, $duty_officer_id);
        foreach ($items as $it) {
            $code = $it['code'] ?? '';
            $name = $it['name'] ?? '';
            $qty  = (int)($it['quantity'] ?? 0);
            if (!$code || $qty <= 0) { throw new Exception('Invalid item in request'); }

            $found = findInventoryItemByCodeOrId($pdo, $code);
            $found = mapToPrimaryInventoryItem($pdo, $found);
            $row = $found ? $found['row'] : null;
            if (!$row) { throw new Exception("Item not found: {$code}"); }

            $available = isset($row[$qtyAvailCol]) ? (int)$row[$qtyAvailCol] : 0;
            if ($available < $qty) { throw new Exception("Insufficient quantity for {$row['item_name']}"); }

            $txnId = 'TXN' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
            $txnDbId = insertTransactionRecord($pdo, [
                'transaction_id' => $txnId,
                'type' => 'borrow',
                'duty_officer_id' => $duty_officer_id,
                'borrower_name' => $borrower_name,
                'borrower_id' => $borrower_id,
                'purpose' => $purpose,
                'expected_return_date' => $expected_return_date,
                'status' => 'pending'
            ]);

            if (columnExists($pdo, 'transaction_items', 'unit_price')) {
                $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity, unit_price) VALUES (?, ?, ?, 0)")
                    ->execute([$txnDbId, $row['id'], $qty]);
            } else {
                $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity) VALUES (?, ?, ?)")
                    ->execute([$txnDbId, $row['id'], $qty]);
            }

            $borrowedCol = columnExists($pdo, $itemsTable, 'borrowed_quantity') ? 'borrowed_quantity' : null;
            $sqlUpdate = "UPDATE {$itemsTable} SET {$qtyAvailCol} = {$qtyAvailCol} - ?";
            $paramsUpd = [$qty];
            if ($borrowedCol) { $sqlUpdate .= ", {$borrowedCol} = {$borrowedCol} + ?"; $paramsUpd[] = $qty; }
            $sqlUpdate .= " WHERE id = ?"; $paramsUpd[] = $row['id'];
            $pdo->prepare($sqlUpdate)->execute($paramsUpd);

            if (tableExists($pdo, 'borrowed_items')) {
                $cols = ['item_id']; $place = ['?']; $vals = [$row['id']];
                if (columnExists($pdo, 'borrowed_items', 'transaction_id')) { $cols[] = 'transaction_id'; $place[] = '?'; $vals[] = $txnDbId; }
                if (columnExists($pdo, 'borrowed_items', 'borrower_id')) { $cols[] = 'borrower_id'; $place[] = '?'; $vals[] = $borrower_id; }
                if (columnExists($pdo, 'borrowed_items', 'borrower_name')) { $cols[] = 'borrower_name'; $place[] = '?'; $vals[] = $borrower_name; }
                $qtyCol = columnExists($pdo, 'borrowed_items', 'quantity_borrowed') ? 'quantity_borrowed' : (columnExists($pdo, 'borrowed_items', 'quantity') ? 'quantity' : null);
                if ($qtyCol) { $cols[] = $qtyCol; $place[] = '?'; $vals[] = $qty; }
                if (columnExists($pdo, 'borrowed_items', 'purpose')) { $cols[] = 'purpose'; $place[] = '?'; $vals[] = $purpose; }
                if (columnExists($pdo, 'borrowed_items', 'expected_return_date')) { $cols[] = 'expected_return_date'; $place[] = '?'; $vals[] = $expected_return_date; }
                if (columnExists($pdo, 'borrowed_items', 'borrowed_date')) { $cols[] = 'borrowed_date'; $place[] = 'NOW()'; }
                if (columnExists($pdo, 'borrowed_items', 'status')) { $cols[] = 'status'; $place[] = "'borrowed'"; }
                $sqlBi = 'INSERT INTO borrowed_items (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
                $pdo->prepare($sqlBi)->execute($vals);
            }

            if (columnExists($pdo, 'transactions', 'status') && statusValueAllowed($pdo, 'transactions', 'borrowed')) {
                $pdo->prepare("UPDATE transactions SET status = 'borrowed' WHERE id = ?")->execute([$txnDbId]);
            }

            $transactionIds[] = $txnId;
            $processedItems[] = ['item_id' => $row['id'], 'item_name' => $row['item_name'], 'quantity' => $qty];
            $borrowerDisp = $borrower_name . ($borrower_id ? " (ID:{$borrower_id})" : '');
            $logLines[] = "Officer={$officerName} (ID:{$duty_officer_id}) Borrower={$borrowerDisp} Item={$row['item_name']} (ID:{$row['id']}) Qty={$qty} Txn={$txnId} Purpose=" . ($purpose ?: 'N/A') . " ExpectedReturn=" . ($expected_return_date ?: 'N/A');
        }

        $pdo->commit();
        // Write logs after successful commit
        foreach ($logLines as $L) { inv_append_log('BORROW', $L); }
        echo json_encode(['success' => true, 'message' => 'Multiple borrow processed successfully', 'transaction_ids' => $transactionIds, 'processed_items' => $processedItems]);
    } catch (Exception $e) {
        if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'message' => 'Multiple borrow failed: ' . $e->getMessage()]);
    }
}

function handleReturn($input) {
    global $pdo, $duty_officer_id;
    $itemsTable = getItemsTable($pdo);
    $qtyAvailCol = getQtyAvailColumn($pdo, $itemsTable);

    $src = is_array($input) ? $input : $_POST;
    $trans_identifier = $src['transaction_id'] ?? '';
    $return_condition = $src['return_condition'] ?? 'good';
    $notes = $src['notes'] ?? '';

    if (!$trans_identifier) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
        return;
    }

    // Find transaction
    $transaction = null;
    if (ctype_digit((string)$trans_identifier)) {
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([(int)$trans_identifier]);
        $transaction = $stmt->fetch();
    }
    if (!$transaction && columnExists($pdo, 'transactions', 'transaction_id')) {
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$trans_identifier]);
        $transaction = $stmt->fetch();
    }
    if (!$transaction) {
        // Fallback: interpret identifier as borrowed_items linkage (no transactions row)
        $itemId = null;
        $qty = 0;
        $itemName = '';
        // Attempt to read from borrowed_items
        if (tableExists($pdo, 'borrowed_items')) {
            $biQtyCol = columnExists($pdo, 'borrowed_items', 'quantity_borrowed') ? 'quantity_borrowed' : (columnExists($pdo, 'borrowed_items', 'quantity') ? 'quantity' : null);
            // Try match by borrowed_items.transaction_id if column exists (string codes allowed)
            if (columnExists($pdo, 'borrowed_items', 'transaction_id')) {
                $sql = "SELECT item_id" . ($biQtyCol ? ", {$biQtyCol} AS quantity" : ", 1 AS quantity") . " FROM borrowed_items WHERE transaction_id = ? LIMIT 1";
                $bst = $pdo->prepare($sql);
                $bst->execute([$trans_identifier]);
                $br = $bst->fetch();
                if ($br) { $itemId = $br['item_id']; $qty = (int)$br['quantity']; }
            }
            // If not found, and identifier numeric, try borrowed_items.id
            if ((!$itemId || $qty <= 0) && ctype_digit((string)$trans_identifier) && columnExists($pdo, 'borrowed_items', 'id')) {
                $sql = "SELECT item_id" . ($biQtyCol ? ", {$biQtyCol} AS quantity" : ", 1 AS quantity") . " FROM borrowed_items WHERE id = ? LIMIT 1";
                $bst = $pdo->prepare($sql);
                $bst->execute([(int)$trans_identifier]);
                $br = $bst->fetch();
                if ($br) { $itemId = $br['item_id']; $qty = (int)$br['quantity']; }
            }
        }

        if (!$itemId || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
            return;
        }

        // Proceed without a transactions row: update stock and borrowed_items directly
        $pdo->beginTransaction();
        try {
            // Update items stock
            $borrowedCol = columnExists($pdo, $itemsTable, 'borrowed_quantity') ? 'borrowed_quantity' : null;
            $sqlUpdate = "UPDATE {$itemsTable} SET {$qtyAvailCol} = {$qtyAvailCol} + ?";
            $paramsUpd = [$qty];
            if ($borrowedCol) { $sqlUpdate .= ", {$borrowedCol} = {$borrowedCol} - ?"; $paramsUpd[] = $qty; }
            $sqlUpdate .= " WHERE id = ?"; $paramsUpd[] = $itemId;
            $pdo->prepare($sqlUpdate)->execute($paramsUpd);

            // Update borrowed_items status and return date if present
            if (tableExists($pdo, 'borrowed_items')) {
                if (columnExists($pdo, 'borrowed_items', 'status')) {
                    if (columnExists($pdo, 'borrowed_items', 'transaction_id') && !ctype_digit((string)$trans_identifier)) {
                        $pdo->prepare("UPDATE borrowed_items SET status = 'returned' WHERE transaction_id = ? AND item_id = ?")
                            ->execute([$trans_identifier, $itemId]);
                    } else {
                        $pdo->prepare("UPDATE borrowed_items SET status = 'returned' WHERE id = ?")
                            ->execute([(int)$trans_identifier]);
                    }
                }
                if (columnExists($pdo, 'borrowed_items', 'actual_return_date')) {
                    if (columnExists($pdo, 'borrowed_items', 'transaction_id') && !ctype_digit((string)$trans_identifier)) {
                        $pdo->prepare("UPDATE borrowed_items SET actual_return_date = NOW() WHERE transaction_id = ? AND item_id = ?")
                            ->execute([$trans_identifier, $itemId]);
                    } else {
                        $pdo->prepare("UPDATE borrowed_items SET actual_return_date = NOW() WHERE id = ?")
                            ->execute([(int)$trans_identifier]);
                    }
                }
            }

            $pdo->commit();

            // Resolve item name for logs (safe best-effort)
            try {
                $nstmt = $pdo->prepare("SELECT item_name FROM {$itemsTable} WHERE id = ?");
                $nstmt->execute([$itemId]);
                $itemName = (string)($nstmt->fetchColumn() ?: '');
            } catch (Exception $e) { $itemName = ''; }

            // Log
            $officerName = getDutyOfficerName($pdo, $duty_officer_id);
            $borrowerDisp = 'Unknown';
            inv_append_log('RETURN', "Officer={$officerName} (ID:{$duty_officer_id}) Borrower={$borrowerDisp} Item=" . ($itemName ?: ("Item#".$itemId)) . " (ID:{$itemId}) Qty={$qty} Txn=" . $trans_identifier . " Condition={$return_condition} Notes=" . preg_replace('/[\r\n]+/', ' ', (string)$notes));
            echo json_encode(['success' => true, 'message' => 'Return processed successfully', 'transaction_id' => (string)$trans_identifier]);
        } catch (Exception $e) {
            if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->rollBack(); }
            echo json_encode(['success' => false, 'message' => 'Error processing return: ' . $e->getMessage()]);
        }
        return;
    }

    // Determine item and quantity
    $ti = $pdo->prepare("SELECT item_id, quantity FROM transaction_items WHERE transaction_id = ? LIMIT 1");
    $ti->execute([$transaction['id']]);
    $tiRow = $ti->fetch();
    $itemId = $tiRow['item_id'] ?? null;
    $qty = isset($tiRow['quantity']) ? (int)$tiRow['quantity'] : 0;

    if ((!$itemId || $qty <= 0) && tableExists($pdo, 'borrowed_items')) {
        $qtyCol = columnExists($pdo, 'borrowed_items', 'quantity_borrowed') ? 'quantity_borrowed' : (columnExists($pdo, 'borrowed_items', 'quantity') ? 'quantity' : null);
        $sql = "SELECT item_id" . ($qtyCol ? ", {$qtyCol} AS quantity" : ", 1 AS quantity") . " FROM borrowed_items WHERE transaction_id = ? LIMIT 1";
        $biStmt = $pdo->prepare($sql);
        $biStmt->execute([$transaction['id']]);
        $biRow = $biStmt->fetch();
        if ($biRow) { $itemId = $biRow['item_id']; $qty = (int)$biRow['quantity']; }
    }

    if (!$itemId || $qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unable to determine item/quantity for return']);
        return;
    }

    $pdo->beginTransaction();
    try {
        // Update items stock
        $borrowedCol = columnExists($pdo, $itemsTable, 'borrowed_quantity') ? 'borrowed_quantity' : null;
        $sqlUpdate = "UPDATE {$itemsTable} SET {$qtyAvailCol} = {$qtyAvailCol} + ?";
        $paramsUpd = [$qty];
        if ($borrowedCol) { $sqlUpdate .= ", {$borrowedCol} = {$borrowedCol} - ?"; $paramsUpd[] = $qty; }
        $sqlUpdate .= " WHERE id = ?"; $paramsUpd[] = $itemId;
        $pdo->prepare($sqlUpdate)->execute($paramsUpd);

        // Update borrowed_items if present
        if (tableExists($pdo, 'borrowed_items')) {
            if (columnExists($pdo, 'borrowed_items', 'status')) {
                $pdo->prepare("UPDATE borrowed_items SET status = 'returned' WHERE transaction_id = ? AND item_id = ?")
                    ->execute([$transaction['id'], $itemId]);
            }
            if (columnExists($pdo, 'borrowed_items', 'actual_return_date')) {
                $pdo->prepare("UPDATE borrowed_items SET actual_return_date = NOW() WHERE transaction_id = ? AND item_id = ?")
                    ->execute([$transaction['id'], $itemId]);
            }
        }

        // Update transaction
        if (columnExists($pdo, 'transactions', 'status') && statusValueAllowed($pdo, 'transactions', 'returned')) {
            $pdo->prepare("UPDATE transactions SET status = 'returned' WHERE id = ?")->execute([$transaction['id']]);
        }
        if (columnExists($pdo, 'transactions', 'return_condition')) {
            $pdo->prepare("UPDATE transactions SET return_condition = ?, return_notes = ? WHERE id = ?")
                ->execute([$return_condition, $notes, $transaction['id']]);
        }

        $pdo->commit();
        // Log return event
        $officerName = getDutyOfficerName($pdo, $duty_officer_id);
        $txnCode = $transaction['transaction_id'] ?? (string)$transaction['id'];
        $borrowerDisp = ($transaction['borrower_name'] ?? 'Unknown');
        inv_append_log('RETURN', "Officer={$officerName} (ID:{$duty_officer_id}) Borrower={$borrowerDisp} Item={$itemName} (ID:{$itemId}) Qty={$qty} Txn={$txnCode} Condition={$return_condition} Notes=" . preg_replace('/[\r\n]+/', ' ', (string)$notes));
        echo json_encode(['success' => true, 'message' => 'Return processed successfully', 'transaction_id' => $txnCode]);
    } catch (Exception $e) {
        if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'message' => 'Error processing return: ' . $e->getMessage()]);
    }
}

function handleSupply($input) {
    global $pdo, $duty_officer_id;
    $itemsTable = getItemsTable($pdo);
    $qtyAvailCol = getQtyAvailColumn($pdo, $itemsTable);
    
    $item_name = $input['item_name'] ?? '';
    $quantity = (int)($input['quantity'] ?? 0);
    $unit = $input['unit'] ?? 'pcs';
    $description = $input['description'] ?? '';
    $location = $input['location'] ?? '';
    
    if (empty($item_name) || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Item name and quantity are required']);
        return;
    }
    
    // Check if item already exists by name (and category if present)
    $hasCategory = columnExists($pdo, $itemsTable, 'category');
    if ($hasCategory && !empty($input['category'])) {
        $check_stmt = $pdo->prepare("SELECT * FROM {$itemsTable} WHERE item_name = ? AND category = ?");
        $check_stmt->execute([$item_name, $input['category']]);
    } else {
        $check_stmt = $pdo->prepare("SELECT * FROM {$itemsTable} WHERE item_name = ?");
        $check_stmt->execute([$item_name]);
    }
    $existing_item = $check_stmt->fetch();
    
    $pdo->beginTransaction();
    
    try {
        if ($existing_item) {
            // Update existing item quantity (schema-aware)
            $totalCol = columnExists($pdo, $itemsTable, 'total_quantity') ? 'total_quantity' : (columnExists($pdo, $itemsTable, 'quantity_total') ? 'quantity_total' : null);
            $sql = "UPDATE {$itemsTable} SET {$qtyAvailCol} = {$qtyAvailCol} + ?";
            $params = [$quantity];
            if ($totalCol) { $sql .= ", {$totalCol} = {$totalCol} + ?"; $params[] = $quantity; }
            if (columnExists($pdo, $itemsTable, 'updated_at')) { $sql .= ", updated_at = NOW()"; }
            $sql .= " WHERE id = ?"; $params[] = $existing_item['id'];
            $update_stmt = $pdo->prepare($sql);
            $update_stmt->execute($params);
            $item_id = $existing_item['id'];
            $message = 'Inventory updated successfully';
        } else {
            // Create new item (schema-aware dynamic columns)
            $cols = [];
            $place = [];
            $values = [];
            // Optional item_code
            if (columnExists($pdo, $itemsTable, 'item_code')) {
                $cols[] = 'item_code';
                $place[] = '?';
                $values[] = 'AUTO_' . date('YmdHis') . '_' . rand(100,999);
            }
            // Required-ish
            $cols[] = 'item_name'; $place[] = '?'; $values[] = $item_name;
            if (columnExists($pdo, $itemsTable, 'description')) { $cols[] = 'description'; $place[] = '?'; $values[] = $description; }
            // total quantity
            if (columnExists($pdo, $itemsTable, 'total_quantity')) { $cols[] = 'total_quantity'; $place[] = '?'; $values[] = $quantity; }
            elseif (columnExists($pdo, $itemsTable, 'quantity_total')) { $cols[] = 'quantity_total'; $place[] = '?'; $values[] = $quantity; }
            // available quantity (schema-aware)
            $cols[] = $qtyAvailCol; $place[] = '?'; $values[] = $quantity;
            // borrowed quantity default 0 if exists
            if (columnExists($pdo, $itemsTable, 'borrowed_quantity')) { $cols[] = 'borrowed_quantity'; $place[] = '0'; }
            // unit/location
            if (columnExists($pdo, $itemsTable, 'unit')) { $cols[] = 'unit'; $place[] = '?'; $values[] = $unit; }
            if (columnExists($pdo, $itemsTable, 'location')) { $cols[] = 'location'; $place[] = '?'; $values[] = $location; }
            // category if present in schema and provided
            if ($hasCategory && !empty($input['category'])) { $cols[] = 'category'; $place[] = '?'; $values[] = $input['category']; }
            // condition status
            if (columnExists($pdo, $itemsTable, 'condition_status')) { $cols[] = 'condition_status'; $place[] = "'good'"; }
            if (columnExists($pdo, $itemsTable, 'created_at')) { $cols[] = 'created_at'; $place[] = 'NOW()'; }
            if (columnExists($pdo, $itemsTable, 'updated_at')) { $cols[] = 'updated_at'; $place[] = 'NOW()'; }
            // qr_code if exists
            if (columnExists($pdo, $itemsTable, 'qr_code')) { $cols[] = 'qr_code'; $place[] = '?'; $values[] = 'QR_' . strtoupper(str_replace(' ', '_', $item_name)) . '_' . sprintf('%03d', rand(1, 999)); }
            $sql = 'INSERT INTO ' . $itemsTable . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';
            $insert_stmt = $pdo->prepare($sql);
            $insert_stmt->execute($values);
            $item_id = $pdo->lastInsertId();
            $message = 'New item added to inventory successfully';
        }
        
        // Create supply transaction record
        $transaction_id = 'SUP' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
        
        $transaction_db_id = insertTransactionRecord($pdo, [
            'transaction_id' => $transaction_id,
            'type' => 'supply',
            'duty_officer_id' => $duty_officer_id,
            'status' => 'completed'
        ]);
        
        // Create transaction item record (schema-aware: unit_price may not exist)
        $hasUnitPrice = columnExists($pdo, 'transaction_items', 'unit_price');
        if ($hasUnitPrice) {
            $item_stmt = $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity, unit_price) VALUES (?, ?, ?, 0)");
            $item_stmt->execute([$transaction_db_id, $item_id, $quantity]);
        } else {
            $item_stmt = $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity) VALUES (?, ?, ?)");
            $item_stmt->execute([$transaction_db_id, $item_id, $quantity]);
        }
        
        $pdo->commit();
        // Log supply event
        $officerName = getDutyOfficerName($pdo, $duty_officer_id);
        $logItemName = $existing_item ? ($existing_item['item_name'] ?? $item_name) : $item_name;
        $actionName = $existing_item ? 'Resupply' : 'Add Item';
        inv_append_log('SUPPLY', "Officer={$officerName} (ID:{$duty_officer_id}) Action={$actionName} Item={$logItemName} (ID:{$item_id}) Qty={$quantity} Txn={$transaction_id}");
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'transaction_id' => $transaction_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function searchTransaction() {
    global $pdo;
    
    $search_term = $_POST['search_term'] ?? '';
    
    if (empty($search_term)) {
        echo json_encode(['success' => false, 'message' => 'Search term is required']);
        return;
    }
    
    // Search for transaction
    $stmt = $pdo->prepare("
        SELECT t.*, ti.quantity, i.qr_code, i.item_name, COALESCE(o.rank_position, CONCAT(IFNULL(o.rank, ''), ' ', IFNULL(o.position, ''))) as officer_name 
        FROM transactions t 
        JOIN transaction_items ti ON t.id = ti.transaction_id 
        JOIN items i ON ti.item_id = i.id 
        JOIN officers o ON t.duty_officer_id = o.id 
        WHERE t.transaction_id = ? OR t.transaction_id LIKE ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$search_term, '%' . $search_term . '%']);
    $transactions = $stmt->fetchAll();
    
    if (empty($transactions)) {
        echo json_encode(['success' => false, 'message' => 'No transactions found']);
        return;
    }
    
    echo json_encode([
        'success' => true, 
        'transactions' => $transactions
    ]);
}

function getItems() {
    global $pdo;
    
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    
    try {
        // Detect if 'category' column exists
        $hasCategory = columnExists($pdo, 'items', 'category');
        $hasItemCode = columnExists($pdo, 'items', 'item_code');
        $hasUnit     = columnExists($pdo, 'items', 'unit');
        // Pick quantity column
        $qtyCol = null;
        foreach (['available_quantity','quantity_available','qty_available'] as $cand) {
            if (columnExists($pdo, 'items', $cand)) { $qtyCol = $cand; break; }
        }
        // Build select with aliases
        $selectParts = ['id'];
        if ($hasItemCode) { $selectParts[] = 'item_code'; }
        $selectParts[] = 'item_name';
        $selectParts[] = ($qtyCol ? ("$qtyCol AS available_quantity") : "0 AS available_quantity");
        $selectParts[] = $hasUnit ? 'unit' : "'pcs' AS unit";
        if ($hasCategory) { $selectParts[] = 'category'; }
        $select = implode(', ', $selectParts);
        $sql = "SELECT $select FROM items WHERE 1=1";
        $params = [];
        
        if (!empty($category) && $hasCategory) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        if (!empty($search)) {
            $sql .= " AND (item_name LIKE ?" . ($hasItemCode ? " OR item_code LIKE ?" : "") . ")";
            $params[] = '%' . $search . '%';
            if ($hasItemCode) { $params[] = '%' . $search . '%'; }
        }
        
        $sql .= " ORDER BY item_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'items' => $items
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching items: ' . $e->getMessage()
        ]);
    }
}
?>