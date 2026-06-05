<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function updateItem($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = isset($input['id']) ? (int)$input['id'] : 0;
        $newName = isset($input['item_name']) ? trim((string)$input['item_name']) : null;
        $newQty  = isset($input['quantity']) ? (int)$input['quantity'] : null; // absolute target total quantity
        // New editable fields
        $newCategory   = array_key_exists('category', $input) ? trim((string)$input['category']) : null;
        $newUnit       = array_key_exists('unit', $input) ? trim((string)$input['unit']) : null;
        $newReturnable = array_key_exists('can_be_returned', $input) ? $input['can_be_returned'] : null;
        if ($itemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item id']);
            return;
        }
        if ($newName === null && $newQty === null && $newCategory === null && $newUnit === null && $newReturnable === null) {
            echo json_encode(['success' => false, 'message' => 'Nothing to update']);
            return;
        }

        // Detect schema table/columns
        $itemsTable = tableExists($pdo, 'items') ? 'items' : (tableExists($pdo, 'inventory_items') ? 'inventory_items' : 'items');
        $colTotal = pickCol($pdo, $itemsTable, ['total_quantity','quantity_total','total','quantity','stock']);
        $colAvail = pickCol($pdo, $itemsTable, ['available_quantity','quantity_available','qty_available','available','qty']);
        $hasBorrowed = colExists($pdo, $itemsTable, 'borrowed_quantity');
        $hasCategoryCol = colExists($pdo, $itemsTable, 'category');
        $hasUnitCol = colExists($pdo, $itemsTable, 'unit');
        $hasReturnableCol = colExists($pdo, $itemsTable, 'can_be_returned');

        // Load current row
        $row = null;
        $st = $pdo->prepare("SELECT * FROM {$itemsTable} WHERE id = ? LIMIT 1");
        $st->execute([$itemId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            return;
        }

        $oldName = $row['item_name'] ?? '';
        $oldTotal = $colTotal ? (int)$row[$colTotal] : null;
        $oldAvail = $colAvail ? (int)$row[$colAvail] : null;
        $borrowed = $hasBorrowed ? (int)$row['borrowed_quantity'] : 0;
        $oldCategory = $row['category'] ?? null;
        $oldUnit = $row['unit'] ?? null;
        $oldReturnable = $row['can_be_returned'] ?? null;

        // Normalize category and returnable inputs
        if ($newCategory !== null && $newCategory !== '') {
            $map = [
                'supplies' => 'Consumable',
                'consumable' => 'Consumable',
                'non-consumable' => 'Non-consumable',
                'semi-expendable' => 'Semi-expendable',
                'capital' => 'Capital',
                'disposable' => 'Disposable'
            ];
            $lc = strtolower($newCategory);
            if (isset($map[$lc])) { $newCategory = $map[$lc]; }
        }
        $resolvedReturnable = null;
        if ($newReturnable !== null && $newReturnable !== '') {
            $rv = strtolower(trim((string)$newReturnable));
            if (in_array($rv, ['returnable','non-returnable'], true)) {
                $resolvedReturnable = $rv;
            } elseif (in_array($rv, ['1','true','yes'], true)) {
                $resolvedReturnable = 'returnable';
            } elseif (in_array($rv, ['0','false','no'], true)) {
                $resolvedReturnable = 'non-returnable';
            }
        }

        // Build update dynamically
        $sets = [];
        $vals = [];
        if ($newName !== null && $newName !== '' && $newName !== $oldName) {
            if (colExists($pdo, $itemsTable, 'item_name')) { $sets[] = 'item_name = ?'; $vals[] = $newName; }
        }
        if ($hasCategoryCol && $newCategory !== null && $newCategory !== '' && $newCategory !== $oldCategory) {
            $sets[] = 'category = ?';
            $vals[] = $newCategory;
        }
        if ($hasUnitCol && $newUnit !== null && $newUnit !== '' && $newUnit !== $oldUnit) {
            $sets[] = 'unit = ?';
            $vals[] = $newUnit;
        }
        if ($hasReturnableCol && $resolvedReturnable !== null && strcasecmp((string)$oldReturnable, $resolvedReturnable) !== 0) {
            $sets[] = 'can_be_returned = ?';
            $vals[] = $resolvedReturnable;
        }
        if ($newQty !== null) {
            if ($newQty < 0) { echo json_encode(['success' => false, 'message' => 'Quantity must be >= 0']); return; }
            // Compute new available consistent with borrowed
            $newAvail = $newQty;
            if ($hasBorrowed) { $newAvail = max(0, $newQty - $borrowed); }
            if ($colTotal) { $sets[] = "$colTotal = ?"; $vals[] = $newQty; }
            if ($colAvail) { $sets[] = "$colAvail = ?"; $vals[] = $newAvail; }
            // If neither total nor avail detected, refuse
            if (!$colTotal && !$colAvail) {
                echo json_encode(['success' => false, 'message' => 'No quantity columns found to update']);
                return;
            }
        }
        if (empty($sets)) {
            echo json_encode(['success' => false, 'message' => 'No applicable changes']);
            return;
        }
        $setsSql = implode(', ', $sets);
        $vals[] = $itemId;
        $sql = "UPDATE {$itemsTable} SET {$setsSql} WHERE id = ?";
        $pdo->prepare($sql)->execute($vals);

        // Fetch updated row for response
        $qtyExpr = $colTotal ? $colTotal : ($colAvail ? $colAvail : '0');
        $itemSql = "SELECT item_name, $qtyExpr as quantity FROM {$itemsTable} WHERE id = ?";
        $stmt = $pdo->prepare($itemSql);
        $stmt->execute([$itemId]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log edit
        $officerId = $_SESSION['duty_officer_id'] ?? null;
        $officerName = getDutyOfficerNameLocal($pdo, $officerId);
        $parts = [];
        if ($newName !== null && $newName !== '' && $newName !== $oldName) { $parts[] = "Name='{$oldName}'->'{$newName}'"; }
        if ($newQty !== null) { $parts[] = "Qty=" . ($oldTotal !== null ? $oldTotal : ($oldAvail !== null ? $oldAvail : 'N/A')) . "->{$newQty}"; }
        if ($hasCategoryCol && $newCategory !== null && $newCategory !== '' && $newCategory !== $oldCategory) { $parts[] = "Category='" . ($oldCategory ?? 'N/A') . "'->'{$newCategory}'"; }
        if ($hasUnitCol && $newUnit !== null && $newUnit !== '' && $newUnit !== $oldUnit) { $parts[] = "Unit='" . ($oldUnit ?? 'N/A') . "'->'{$newUnit}'"; }
        if ($hasReturnableCol && $resolvedReturnable !== null && strcasecmp((string)$oldReturnable, $resolvedReturnable) !== 0) { $parts[] = "Returnable='" . ($oldReturnable ?? 'N/A') . "'->'{$resolvedReturnable}'"; }
        inv_append_log_local('SUPPLY', "Officer={$officerName}" . ($officerId ? " (ID:{$officerId})" : '') . " Action=Edit Item ID={$itemId} " . implode(' ', $parts));

        echo json_encode(['success' => true, 'message' => 'Item updated successfully', 'item' => ['id' => $itemId] + ($updated ?: [])]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating item: ' . $e->getMessage()]);
    }
}

function deleteItem($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = isset($input['id']) ? (int)$input['id'] : 0;
        $pin    = isset($input['pin']) ? trim((string)$input['pin']) : '';
        $reason = isset($input['reason']) ? trim((string)$input['reason']) : '';
        if ($itemId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid item id']); return; }
        if ($pin !== '472005') { echo json_encode(['success' => false, 'message' => 'Invalid PIN']); return; }
        if ($reason === '' || mb_strlen($reason) < 3) { echo json_encode(['success' => false, 'message' => 'Deletion reason is required']); return; }

        // Load item info for logging
        $row = null; $name = ''; $qty = null;
        $colTotal = pickCol($pdo, 'items', ['total_quantity','quantity_total','total','quantity','stock']);
        $st = $pdo->prepare('SELECT item_name' . ($colTotal ? (', ' . $colTotal . ' as quantity') : '') . ' FROM items WHERE id = ? LIMIT 1');
        $st->execute([$itemId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) { $name = (string)($row['item_name'] ?? ''); $qty = isset($row['quantity']) ? (int)$row['quantity'] : null; }

        // Delete the item
        $del = $pdo->prepare('DELETE FROM items WHERE id = ?');
        $del->execute([$itemId]);
        if ($del->rowCount() <= 0) { echo json_encode(['success' => false, 'message' => 'Item not found or already deleted']); return; }

        // Log deletion (and mirror to transaction_logs if available)
        $officerId = $_SESSION['duty_officer_id'] ?? null;
        $officerName = getDutyOfficerNameLocal($pdo, $officerId);
        $reasonSafe = preg_replace('/[\r\n\t]+/', ' ', (string)$reason);
        inv_append_log_local('SUPPLY', "Officer={$officerName}" . ($officerId ? " (ID:{$officerId})" : '') . " Action=Delete Item Item=" . ($name ?: 'Unknown') . " (ID={$itemId}) Qty=" . ($qty !== null ? $qty : 'N/A') . " Reason=" . ($reasonSafe !== '' ? $reasonSafe : 'N/A'));

        // Also write a transaction_logs entry if table exists/creatable
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_logs (id INT AUTO_INCREMENT PRIMARY KEY, transaction_type VARCHAR(20), item_id INT, quantity INT, notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
            $logSql = "INSERT INTO transaction_logs (transaction_type, item_id, quantity, notes, created_at) VALUES ('delete_item', ?, ?, ?, NOW())";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([$itemId, ($qty !== null ? (int)$qty : null), 'Reason: ' . $reasonSafe]);
        } catch (Exception $e) { /* ignore logging errors */ }

        echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting item: ' . $e->getMessage()]);
    }
}

// Helper: resolve duty officer display name (prefer actual name; rank/position as fallback)
function getDutyOfficerNameLocal($pdo, $officer_id) {
    if (empty($officer_id)) return 'Unknown';
    try {
        $stmt = $pdo->prepare("SELECT * FROM officers WHERE id = ? LIMIT 1");
        $stmt->execute([$officer_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 'Officer#' . $officer_id;
        $cands = [];
        if (!empty($row['name'])) $cands[] = $row['name'];
        if (!empty($row['full_name'])) $cands[] = $row['full_name'];
        if (!empty($row['fullname'])) $cands[] = $row['fullname'];
        $full = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($full !== '') $cands[] = $full;
        if (!empty($row['rank_position'])) $cands[] = $row['rank_position'];
        $rankPos = trim(($row['rank'] ?? '') . ' ' . ($row['position'] ?? ''));
        if ($rankPos !== '') $cands[] = $rankPos;
        foreach ($cands as $n) { if (!empty($n)) return $n; }
        return 'Officer#' . $officer_id;
    } catch (Exception $e) {
        return 'Officer#' . $officer_id;
    }
}

// Helper: append to logs/inventory.log at project root
function inv_append_log_local($type, $details) {
    $ts = date('Y-m-d H:i:s');
    $line = "[{$ts}] [{$type}] " . $details;
    $base = dirname(dirname(__DIR__)); // project root
    $dir = $base . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $file = $dir . DIRECTORY_SEPARATOR . 'inventory.log';
    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

require_once '../config/database.php';

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_supply_items':
        getSupplyItems($pdo);
        break;
    case 'add_supply_item':
        addSupplyItem($pdo);
        break;
    case 'process_resupply':
        processResupply($pdo);
        break;
    case 'update_item':
        updateItem($pdo);
        break;
    case 'delete_item':
        deleteItem($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// Helpers
function colExists($pdo, $table, $col) {
    // First try: direct SHOW query (cannot be prepared on some MySQL versions)
    try {
        $qcol = $pdo->quote($col);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$qcol}");
        if ($stmt && $stmt->fetch()) return true;
    } catch (Exception $e) { /* fall through */ }
    // Fallback: INFORMATION_SCHEMA which supports prepared statements
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $col]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function pickCol($pdo, $table, $candidates) {
    foreach ($candidates as $c) { if (colExists($pdo, $table, $c)) return $c; }
    return null;
}

function tableExists($pdo, $table) {
    if (!$pdo || !$table) return false;
    // Try SHOW TABLES first
    try {
        $q = $pdo->quote($table);
        $s = $pdo->query("SHOW TABLES LIKE {$q}");
        if ($s && $s->fetch()) return true;
    } catch (Exception $e) { /* ignore */ }
    // Fallback to INFORMATION_SCHEMA
    try {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $st->execute([$table]);
        return (bool)$st->fetch();
    } catch (Exception $e) { return false; }
}

function getSupplyItems($pdo) {
    try {
        $category = $_GET['category'] ?? '';
        // Map incoming category (from UI) to ENUM values in DB
        $categoryMap = [
            'supplies' => 'Consumable',
            'consumable' => 'Consumable',
            'non-consumable' => 'Non-consumable',
            'semi-expendable' => 'Semi-expendable',
            'capital' => 'Capital',
            'disposable' => 'Disposable'
        ];
        if (!empty($category) && isset($categoryMap[strtolower($category)])) {
            $category = $categoryMap[strtolower($category)];
        }
        
        $hasCategory   = colExists($pdo, 'items', 'category');
        $hasReturnable = colExists($pdo, 'items', 'can_be_returned');
        $colTotal      = pickCol($pdo, 'items', ['total_quantity','quantity_total','total','quantity','stock']);
        $colAvail      = pickCol($pdo, 'items', ['available_quantity','quantity_available','qty_available','available','qty']);
        $colUnit       = colExists($pdo, 'items', 'unit') ? 'unit' : null;

        $selectParts = ['id', 'item_name'];
        if ($hasCategory) { $selectParts[] = 'category'; }
        if ($colTotal)    { $selectParts[] = "$colTotal AS quantity"; }
        elseif ($colAvail){ $selectParts[] = "$colAvail AS quantity"; }
        else              { $selectParts[] = "0 AS quantity"; }
        $selectParts[] = $colUnit ? $colUnit : "'pcs' AS unit";
        if ($hasReturnable) { $selectParts[] = 'can_be_returned'; }
        $qtyExpr = $colTotal ? $colTotal : ($colAvail ? $colAvail : '0');
        $select = implode(', ', $selectParts);
        $sql = "SELECT $select, CASE WHEN $qtyExpr <= 10 THEN 'low' WHEN $qtyExpr <= 50 THEN 'medium' ELSE 'good' END as stock_level FROM items WHERE 1=1";
        
        $params = [];
        // If the schema has category, optionally filter by it and limit categories set
        if ($hasCategory) {
            $sql .= " AND category IN ('Consumable', 'Non-consumable', 'Semi-expendable', 'Capital', 'Disposable')";
            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
        }
        
        $sql .= " ORDER BY item_name";
        
        // Debug: log the query and params
        error_log("getSupplyItems SQL: " . $sql);
        error_log("getSupplyItems Params: " . json_encode($params));

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $items
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching supply items: ' . $e->getMessage()
        ]);
    }
}

function addSupplyItem($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Debug: Log input data to see what we're receiving
        error_log("Supply Add Input: " . json_encode($input));
        
        $itemName = $input['item_name'] ?? '';
        $category = $input['category'] ?? '';
        $unit = $input['unit'] ?? '';
        $quantity = (int)($input['quantity'] ?? 0);
        $canBeReturned = isset($input['can_be_returned']) ? $input['can_be_returned'] : 'returnable';
        
        // Map frontend categories to database ENUM values
        $categoryMap = [
            'supplies' => 'Consumable',
            'consumable' => 'Consumable', 
            'non-consumable' => 'Non-consumable',
            'semi-expendable' => 'Semi-expendable',
            'capital' => 'Capital',
            'disposable' => 'Disposable'
        ];
        
        if (!empty($category) && isset($categoryMap[strtolower($category)])) {
            $category = $categoryMap[strtolower($category)];
        } elseif (!empty($category) && !in_array($category, ['Consumable','Non-consumable','Semi-expendable','Capital','Disposable'])) {
            // Default to Consumable if category doesn't match ENUM values
            $category = 'Consumable';
        }
        
        // Debug: Log processed values
        error_log("Supply Add Processed - Name: $itemName, Category: $category, Unit: $unit, Quantity: $quantity, Returnable: $canBeReturned");
        
        // Validate quantity is not zero
        if ($quantity <= 0) {
            error_log("ERROR: Quantity is $quantity, should be positive integer");
        }

        $hasCategory   = colExists($pdo, 'items', 'category');
        $hasReturnable = colExists($pdo, 'items', 'can_be_returned');
        $hasItemCode   = colExists($pdo, 'items', 'item_code');
        $colTotal      = pickCol($pdo, 'items', ['total_quantity','quantity_total','total','quantity','stock']);
        $colAvail      = pickCol($pdo, 'items', ['available_quantity','quantity_available','qty_available','available','qty']);
        $colUnit       = colExists($pdo, 'items', 'unit') ? 'unit' : null;
        
        // Debug: Log column detection results
        error_log("Column detection - hasCategory: " . ($hasCategory ? 'YES' : 'NO'));
        error_log("Column detection - colTotal: " . ($colTotal ?: 'NULL'));
        error_log("Column detection - colAvail: " . ($colAvail ?: 'NULL'));
        error_log("Column detection - colUnit: " . ($colUnit ?: 'NULL'));

        if (empty($itemName) || $quantity <= 0 || ($hasCategory && empty($category))) {
            echo json_encode([
                'success' => false,
                'message' => 'All required fields must be provided and quantity must be greater than 0'
            ]);
            return;
        }
        
        // Check if item already exists (with or without category)
        if ($hasCategory) {
            $checkSql = "SELECT id FROM items WHERE item_name = ? AND category = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$itemName, $category]);
        } else {
            $checkSql = "SELECT id FROM items WHERE item_name = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$itemName]);
        }
        
        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => $hasCategory ? 'Item already exists in this category' : 'Item already exists'
            ]);
            return;
        }
        
        // Generate item code
        $itemCode = $hasItemCode ? generateItemCode($category ?: 'GEN', $itemName) : null;

        // Insert dynamically according to schema
        $cols = [];
        $vals = [];
        $phs  = [];
        if ($hasItemCode && $itemCode) { $cols[] = 'item_code'; $vals[] = $itemCode; $phs[] = '?'; }
        $cols[] = 'item_name'; $vals[] = $itemName; $phs[] = '?';
        if ($hasCategory && $category !== '') { $cols[] = 'category'; $vals[] = $category; $phs[] = '?'; }
        if ($colTotal) { 
            $cols[] = $colTotal; 
            $vals[] = (int)$quantity; 
            $phs[] = '?'; 
            error_log("Adding $colTotal with value: " . (int)$quantity);
        }
        if ($colAvail) { 
            $cols[] = $colAvail; 
            $vals[] = (int)$quantity; 
            $phs[] = '?';
            error_log("Adding $colAvail with value: " . (int)$quantity);
        }
        if (colExists($pdo, 'items', 'borrowed_quantity')) { $cols[] = 'borrowed_quantity'; $vals[] = 0; $phs[] = '?'; }
        if ($colUnit)  { $cols[] = $colUnit;  $vals[] = $unit ?: 'pcs'; $phs[] = '?'; }
        if (colExists($pdo, 'items', 'description')) { $cols[] = 'description'; $vals[] = 'Supply item'; $phs[] = '?'; }
        if ($hasReturnable) { $cols[] = 'can_be_returned'; $vals[] = $canBeReturned; $phs[] = '?'; }
        if (empty($cols)) { throw new Exception('No compatible columns to insert'); }
        $sql = 'INSERT INTO items (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $phs) . ')';
        
        // Debug: Log the exact SQL and values being executed
        error_log("SQL: $sql");
        error_log("Values: " . json_encode($vals));
        error_log("Columns: " . json_encode($cols));
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($vals);
        
        error_log("Insert result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        $newItemId = $pdo->lastInsertId();

        // Log to transactions if table exists, including duty officer if available
        try {
            $dutyOfficerId = isset($_SESSION['duty_officer_id']) ? (int)$_SESSION['duty_officer_id'] : null;
            $hasTransactions = $pdo->query("SHOW TABLES LIKE 'transactions'")->fetch();
            if ($hasTransactions) {
                // Build dynamic insert
                $cols = ['transaction_id', 'type'];
                $vals = ['SUP' . date('Ymd') . sprintf('%04d', rand(1000, 9999)), 'supply'];
                if ($dutyOfficerId && $pdo->query("SHOW COLUMNS FROM transactions LIKE 'duty_officer_id'")->fetch()) { $cols[] = 'duty_officer_id'; $vals[] = $dutyOfficerId; }
                if ($pdo->query("SHOW COLUMNS FROM transactions LIKE 'status'")->fetch()) { $cols[] = 'status'; $vals[] = 'completed'; }
                $ph = rtrim(str_repeat('?,', count($vals)), ',');
                $pdo->prepare('INSERT INTO transactions (' . implode(',', $cols) . ') VALUES (' . $ph . ')')->execute($vals);
                $txnId = $pdo->lastInsertId();
                // transaction_items
                if ($pdo->query("SHOW TABLES LIKE 'transaction_items'")->fetch()) {
                    if ($pdo->query("SHOW COLUMNS FROM transaction_items LIKE 'unit_price'")->fetch()) {
                        $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity, unit_price) VALUES (?, ?, ?, 0)")->execute([$txnId, $newItemId, (int)$quantity]);
                    } else {
                        $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity) VALUES (?, ?, ?)")->execute([$txnId, $newItemId, (int)$quantity]);
                    }
                }
            }
        } catch (Exception $e) { /* ignore logging errors */ }

        // Get the inserted item details to return
        $qtyExpr = $colTotal ? $colTotal : ($colAvail ? $colAvail : '0');
        $itemSql = "SELECT item_name, $qtyExpr as quantity, " . ($colTotal ? "$colTotal as total_quantity, " : "") . ($colAvail ? "$colAvail as available_quantity, " : "") . "category, unit FROM items WHERE id = ?";
        $itemStmt = $pdo->prepare($itemSql);
        $itemStmt->execute([$newItemId]);
        $insertedItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
        
        // Text log (Add Supply Item)
        $officerId = $_SESSION['duty_officer_id'] ?? null;
        $officerName = getDutyOfficerNameLocal($pdo, $officerId);
        inv_append_log_local('SUPPLY', "Officer={$officerName}" . ($officerId ? " (ID:{$officerId})" : '') . " Action=Add Item Item={$itemName} (ID={$newItemId}) Qty={$quantity} Category=" . ($category ?: 'N/A'));

        echo json_encode([
            'success' => true,
            'message' => 'Supply item added successfully',
            'item_id' => $newItemId,
            'item_details' => $insertedItem
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error adding supply item: ' . $e->getMessage()
        ]);
    }
}

function processResupply($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $resupplyItems = $input['items'] ?? [];
        
        if (empty($resupplyItems)) {
            echo json_encode([
                'success' => false,
                'message' => 'No items selected for resupply'
            ]);
            return;
        }
        
        $pdo->beginTransaction();
        
        $updatedItems = [];
        
        foreach ($resupplyItems as $item) {
            $itemId = $item['id'];
            $addQuantity = (int)$item['quantity']; // can be negative to decrease stock
            
            if ($addQuantity === 0) {
                continue;
            }
            
            // Detect columns
            $colTotal = pickCol($pdo, 'items', ['total_quantity','quantity_total','total','quantity','stock']);
            $colAvail = pickCol($pdo, 'items', ['available_quantity','quantity_available','qty_available','available','qty']);

            if (!$colAvail && !$colTotal) {
                throw new Exception('No quantity columns found on items');
            }

            // Build safe update with clamping at zero for negative adjustments
            $setParts = [];
            $values = [];
            if ($colAvail) {
                $setParts[] = "$colAvail = CASE WHEN ($colAvail + ?) < 0 THEN 0 ELSE $colAvail + ? END";
                $values[] = $addQuantity; // for < 0 check
                $values[] = $addQuantity; // for + ?
            }
            if ($colTotal) {
                $setParts[] = "$colTotal = CASE WHEN ($colTotal + ?) < 0 THEN 0 ELSE $colTotal + ? END";
                $values[] = $addQuantity;
                $values[] = $addQuantity;
            }

            $updateSql = 'UPDATE items SET ' . implode(', ', $setParts) . ' WHERE id = ?';
            $values[] = $itemId;
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($values);
            
            // Get updated item info
            $qtyExpr = $colTotal ? $colTotal : ($colAvail ? $colAvail : '0');
            $itemSql = "SELECT item_name, $qtyExpr as quantity FROM items WHERE id = ?";
            $itemStmt = $pdo->prepare($itemSql);
            $itemStmt->execute([$itemId]);
            $itemInfo = $itemStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($itemInfo) {
                $updatedItems[] = [
                    'name' => $itemInfo['item_name'],
                    'added' => $addQuantity,
                    'new_total' => $itemInfo['quantity']
                ];
            }
            
            // Log the resupply transaction
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS transaction_logs (id INT AUTO_INCREMENT PRIMARY KEY, transaction_type VARCHAR(20), item_id INT, quantity INT, notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                $logSql = "INSERT INTO transaction_logs (transaction_type, item_id, quantity, notes, created_at) 
                           VALUES ('supply', ?, ?, 'Resupply operation', NOW())";
                $logStmt = $pdo->prepare($logSql);
                $logStmt->execute([$itemId, $addQuantity]);
            } catch (Exception $e) { /* ignore */ }

            // Text log (Resupply with direction)
            $officerId = $_SESSION['duty_officer_id'] ?? null;
            $officerName = getDutyOfficerNameLocal($pdo, $officerId);
            $iname = $itemInfo['item_name'] ?? ('Item#' . $itemId);
            $direction = ($addQuantity >= 0) ? 'Resupply-Increase' : 'Resupply-Decrease';
            inv_append_log_local('SUPPLY', "Officer={$officerName}" . ($officerId ? " (ID:{$officerId})" : '') . " Action={$direction} Item={$iname} (ID={$itemId}) Qty={$addQuantity}");
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Resupply completed successfully',
            'updated_items' => $updatedItems
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error processing resupply: ' . $e->getMessage()
        ]);
    }
}

function generateItemCode($category, $itemName) {
    $categoryCode = strtoupper(substr($category, 0, 3));
    $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $itemName), 0, 3));
    $timestamp = substr(time(), -4);
    
    return $categoryCode . $nameCode . $timestamp;
}
?>