<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
// Strongly discourage caching so Return tab always reflects latest DB state
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
// Prevent PHP notices/warnings from corrupting JSON output
@ini_set('display_errors', '0');
@error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
// Emit JSON even on fatal errors (helps avoid empty 500 responses)
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        http_response_code(500);
        // Write to logs/inventory.log as well
        try {
            $base = dirname(dirname(__DIR__));
            $dir = $base . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $file = $dir . DIRECTORY_SEPARATOR . 'inventory.log';
            $ts = date('Y-m-d H:i:s');
            $msg = isset($e['message']) ? $e['message'] : 'Unknown';
            $fileLine = (isset($e['file']) ? $e['file'] : '-') . ':' . (isset($e['line']) ? $e['line'] : '-');
            @file_put_contents($file, "[{$ts}] [FATAL] borrowed_items.php shutdown: {$msg} at {$fileLine}" . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Exception $ex) { /* ignore */ }
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error',
            'error' => $e['message'] ?? 'Unknown'
        ]);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/db.php';

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
    // Suppress noisy DEBUG logs from appearing in inventory.log
    if (strcasecmp((string)$type, 'DEBUG') === 0) { return; }
    $ts = date('Y-m-d H:i:s');
    $line = "[{$ts}] [{$type}] " . $details;
    $base = dirname(dirname(__DIR__)); // project root
    $dir = $base . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $file = $dir . DIRECTORY_SEPARATOR . 'inventory.log';
    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Helper: finalize a non-returnable item by logging and deleting its borrowed entry so it won't appear in Return
function finalizeNonReturnableReturn($pdo, $transaction_id, $itemId, $itemName, $condition, $notes) {
    try {
        // Officer info for logs
        $officerId = $_SESSION['duty_officer_id'] ?? null;
        $officerName = getDutyOfficerNameLocal($pdo, $officerId);
        $dispName = $itemName ?: ('Item#' . (int)$itemId);
        // Write text log entry (no stock change)
        inv_append_log_local('RETURN', "NonReturnable=YES Officer={$officerName}" . ($officerId ? " (ID:{$officerId})" : '') . " Item={$dispName} (ID=" . (int)$itemId . ") Qty=0 Txn=" . (string)$transaction_id . " Condition=" . preg_replace('/[\r\n]+/',' ',(string)$condition) . " Notes=" . preg_replace('/[\r\n]+/',' ',(string)$notes));
        
        // Delete borrowed_items row(s) so UI no longer shows them
        try {
            $colCheck = function($table, $col) use ($pdo) {
                try { $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $s->execute([$col]); return (bool)$s->fetch(); } catch (Exception $e) { return false; }
            };
            $biHasId = $colCheck('borrowed_items','id');
            $biHasTrans = $colCheck('borrowed_items','transaction_id');
            if ($biHasId && ctype_digit((string)$transaction_id)) {
                $pdo->prepare("DELETE FROM borrowed_items WHERE id = ?")->execute([(int)$transaction_id]);
            }
            if ($biHasTrans) {
                // Try both numeric and string transaction_id values
                $pdo->prepare("DELETE FROM borrowed_items WHERE transaction_id = ?")->execute([$transaction_id]);
                if (!ctype_digit((string)$transaction_id)) {
                    // Map string code to numeric ID if needed
                    $tHasCode = $colCheck('transactions','transaction_id');
                    if ($tHasCode) {
                        $rs = $pdo->prepare("SELECT id FROM transactions WHERE transaction_id = ? LIMIT 1");
                        $rs->execute([$transaction_id]);
                        $rid = $rs->fetchColumn();
                        if ($rid) { $pdo->prepare("DELETE FROM borrowed_items WHERE transaction_id = ?")->execute([$rid]); }
                    }
                }
            }
        } catch (Exception $e) { /* ignore deletion failures */ }
    } catch (Exception $e) { /* ignore */ }
}

// Helper: schema-aware available quantity column
function getQtyAvailColumnLocal($pdo, $table) {
    try {
        $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'available_quantity'");
        $s->execute();
        if ($s->fetch()) return 'available_quantity';
    } catch (Exception $e) {}
    try {
        $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'quantity_available'");
        $s->execute();
        if ($s->fetch()) return 'quantity_available';
    } catch (Exception $e) {}
    try {
        $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE 'qty_available'");
        $s->execute();
        if ($s->fetch()) return 'qty_available';
    } catch (Exception $e) {}
    return 'available_quantity';
}

// Helper: check if a table exists (robust)
function tableExistsLocal($pdo, $table) {
    if (!$pdo || !$table) return false;
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
    // $pdo is already created in db.php
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'ping':
            echo json_encode(['success' => true, 'message' => 'pong']);
            break;
        case 'get_borrowed':
            getBorrowedItems($pdo);
            break;
        case 'debug_bi':
            debugBorrowedItems($pdo);
            break;
        case 'return_item':
            returnItem($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function getBorrowedItems($pdo) {
    try {
        $category = $_GET['category'] ?? '';
        $borrower_id = $_GET['borrower_id'] ?? '';
        
        // Column existence checks (robust: direct SHOW + INFORMATION_SCHEMA fallback)
        $colCheck = function($table, $col) use ($pdo) {
            // Direct SHOW query (not preparable server-side)
            try {
                $qcol = $pdo->quote($col);
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$qcol}");
                if ($stmt && $stmt->fetch()) return true;
            } catch (Exception $e) { /* ignore and fallback */ }
            // INFORMATION_SCHEMA fallback
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
                $stmt->execute([$table, $col]);
                return (bool)$stmt->fetch();
            } catch (Exception $e) {
                return false;
            }
        };
        
        // Check table structures to determine how to find borrowed items
        $biHasTransId = $colCheck('borrowed_items', 'transaction_id');
        $biHasQtyBorrowed = $colCheck('borrowed_items', 'quantity_borrowed');
        $biHasQty = $colCheck('borrowed_items', 'quantity');
        $biHasId = $colCheck('borrowed_items', 'id');
        $biHasStatus = $colCheck('borrowed_items', 'status');
        $biHasId = $colCheck('borrowed_items', 'id');
        $biHasStatus = $colCheck('borrowed_items', 'status');
        $biHasId = $colCheck('borrowed_items', 'id');
        $biHasId = $colCheck('borrowed_items', 'id');
        $biHasBorrowedDate = $colCheck('borrowed_items', 'borrowed_date');
        $tHasExpReturn = $colCheck('transactions', 'expected_return_date');
        $tiExists = $colCheck('transaction_items', 'id');
        // Detect the transaction id column for transaction_items table
        $tiTransIdCol = null;
        if ($tiExists) {
            foreach (['transaction_id','trans_id','txn_id','t_id','transaction'] as $cand) {
                if ($colCheck('transaction_items', $cand)) { $tiTransIdCol = $cand; break; }
            }
        }
        $tHasType = $colCheck('transactions', 'type');
        $tHasStatus = $colCheck('transactions', 'status');
        $biHasStatus = $colCheck('borrowed_items', 'status');
        // Detect a return date column to exclude returned rows when 'status' is not present
        $returnDateCol = null;
        foreach (['actual_return_date','returned_at','return_date','date_returned'] as $cand) {
            if ($colCheck('borrowed_items', $cand)) { $returnDateCol = 'bi.' . $cand; break; }
        }
        $tHasCode = $colCheck('transactions', 'transaction_id');
        // Items table settings (schema-aware)
        $itemsTable = tableExistsLocal($pdo, 'items') ? 'items' : (tableExistsLocal($pdo, 'inventory_items') ? 'inventory_items' : 'items');
        $itemCategoryCol = $colCheck($itemsTable, 'category') ? 'i.category' : "'Uncategorized'";
        $itemUnitCol = $colCheck($itemsTable, 'unit') ? 'i.unit' : "'pcs'";
        $hasReturnable = $colCheck($itemsTable, 'can_be_returned');

        // Build dynamic selects
        $qtySelect = $biHasQtyBorrowed ? 'bi.quantity_borrowed AS quantity_borrowed' : ($biHasQty ? 'bi.quantity AS quantity_borrowed' : '1 AS quantity_borrowed');
        $hasBorrowDateAlt = $colCheck('borrowed_items', 'borrow_date');
        $borrowDateExpr = $biHasBorrowedDate ? 'bi.borrowed_date' : ($hasBorrowDateAlt ? 'bi.borrow_date' : 'NULL');
        // Join clause for transactions/officers (support both numeric id and string code transaction_id)
        $joinTrans = '';
        if ($biHasTransId) {
            if ($tHasCode) {
                $joinTrans = "LEFT JOIN transactions t ON (bi.transaction_id = t.id OR bi.transaction_id = t.transaction_id) LEFT JOIN officers o ON t.duty_officer_id = o.id";
            } else {
                $joinTrans = "LEFT JOIN transactions t ON bi.transaction_id = t.id LEFT JOIN officers o ON t.duty_officer_id = o.id";
            }
        } elseif ($biHasId) {
            // Fallback: if borrowed_items lacks transaction_id, try correlating by ID where schemas reused same PKs
            if ($tHasCode) {
                $joinTrans = "LEFT JOIN transactions t ON (t.id = bi.id OR t.transaction_id = bi.id) LEFT JOIN officers o ON t.duty_officer_id = o.id";
            } else {
                $joinTrans = "LEFT JOIN transactions t ON t.id = bi.id LEFT JOIN officers o ON t.duty_officer_id = o.id";
            }
        }
        // Officer column detection (support duty_officer_id or officer_id)
        $txnOfficerCol = $colCheck('transactions', 'duty_officer_id') ? 't.duty_officer_id' : ($colCheck('transactions', 'officer_id') ? 't.officer_id' : null);
        $biOfficerCol = $colCheck('borrowed_items', 'duty_officer_id') ? 'bi.duty_officer_id' : ($colCheck('borrowed_items', 'officer_id') ? 'bi.officer_id' : null);
        if (!empty($joinTrans) && $txnOfficerCol && $biOfficerCol) {
            $officerSelect = "COALESCE({$txnOfficerCol}, {$biOfficerCol}) AS duty_officer_id";
        } elseif (!empty($joinTrans) && $txnOfficerCol) {
            $officerSelect = "{$txnOfficerCol} AS duty_officer_id";
        } elseif ($biOfficerCol) {
            $officerSelect = "{$biOfficerCol} AS duty_officer_id";
        } else {
            $officerSelect = "NULL AS duty_officer_id";
        }
        // Optionally project officer name parts for final fallback (only if columns exist)
        $officerNameSelects = [];
        if (!empty($joinTrans)) {
            foreach (['name','rank_position','full_name','fullname','first_name','last_name','rank','position'] as $oc) {
                if ($colCheck('officers', $oc)) {
                    $alias = 'officer_' . str_replace(' ', '_', $oc);
                    $officerNameSelects[] = "o.{$oc} AS {$alias}";
                }
            }
        }
        
        // Debug output (only to error log, not to response)
        error_log("DEBUG getBorrowedItems: bi.transaction_id=" . ($biHasTransId ? 'YES' : 'NO') . 
                  ", bi.quantity_borrowed=" . ($biHasQtyBorrowed ? 'YES' : 'NO') . 
                  ", transaction_items exists=" . ($tiExists ? 'YES' : 'NO') .
                  ", category filter: '$category', borrower filter: '$borrower_id'");
        
        $borrowed_items = [];
        
        // Primary approach: Query borrowed_items table directly
        $officerNameSelectSql = '';
        if (!empty($officerNameSelects)) {
            $officerNameSelectSql = ",\n                " . implode(",\n                ", $officerNameSelects);
        }
        // Project status and return date columns (if present) for frontend filtering
        $statusSelect = $biHasStatus ? 'bi.status AS bi_status' : "NULL AS bi_status";
        $retDateSelect = $returnDateCol ? ($returnDateCol . ' AS bi_return_date') : "NULL AS bi_return_date";
        $sql = "
            SELECT 
                bi.id as transaction_id,
                bi.borrower_name,
                '' as rank_position,
                '' as unit,
                i.item_name,
                {$itemCategoryCol} as category,
                {$itemUnitCol} as item_unit,
                {$qtySelect},
                bi.expected_return_date,
                '' as purpose,
                {$borrowDateExpr} as borrowed_date,
                " . ($hasReturnable ? "CASE WHEN i.can_be_returned = 'returnable' THEN 'yes' ELSE 'no' END" : "'yes'") . " as can_return,
                {$officerSelect},
                " . ($biHasTransId ? "bi.transaction_id" : "NULL") . " as trans_id,
                {$statusSelect},
                {$retDateSelect}" . $officerNameSelectSql . "
            FROM borrowed_items bi
            JOIN {$itemsTable} i ON bi.item_id = i.id
            {$joinTrans}
            WHERE 1=1
        ";
        
        $params = [];
        
        // Exclude non-returnables: only show rows that can actually be returned
        if ($hasReturnable) {
            $sql .= " AND i.can_be_returned = 'returnable'";
        }

        // Category filter
        if (!empty($category)) {
            if ($itemCategoryCol !== "'Uncategorized'") {
                $sql .= " AND {$itemCategoryCol} = ?";
                $params[] = $category;
            }
        }
        
        // Borrower filter (by name since borrower_id might not exist in borrowed_items)
        if (!empty($borrower_id)) {
            $sql .= " AND bi.borrower_name LIKE ?";
            $params[] = "%$borrower_id%";
        }
        // Show only outstanding rows: all present indicators must say 'not returned'
        $activeConds = [];
        if ($biHasStatus) {
            $activeConds[] = "(bi.status IS NULL OR TRIM(bi.status) = '' OR LOWER(bi.status) NOT IN ('returned','complete','completed'))";
        }
        if ($returnDateCol) {
            $activeConds[] = "(" . $returnDateCol . " IS NULL OR " . $returnDateCol . " = '' OR " . $returnDateCol . " = '0000-00-00' OR " . $returnDateCol . " = '0000-00-00 00:00:00')";
        }
        if ($biHasQtyBorrowed) {
            $activeConds[] = "bi.quantity_borrowed > 0";
        } elseif ($biHasQty) {
            $activeConds[] = "bi.quantity > 0";
        }
        if (!empty($activeConds)) {
            $sql .= " AND " . implode(' AND ', $activeConds);
        }
        // Also require the parent transaction (if joined) to not be returned/completed
        if (!empty($joinTrans) && $tHasStatus) {
            $sql .= " AND (t.status IS NULL OR LOWER(t.status) NOT IN ('returned','complete','completed'))";
        }
        
        $sql .= " ORDER BY COALESCE({$borrowDateExpr}, bi.id) DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $borrowed_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Resolve duty officer display name safely with fallbacks
        if ($borrowed_items) {
            foreach ($borrowed_items as &$row) {
                $officerName = null;
                if (isset($row['duty_officer_id']) && !is_null($row['duty_officer_id'])) {
                    $officerName = getDutyOfficerNameLocal($pdo, $row['duty_officer_id']);
                }
                if (!$officerName || $officerName === 'Unknown') {
                    // Fallback: resolve via transactions table using trans_id
                    $transId = $row['trans_id'] ?? null;
                    if ($transId) {
                        try {
                            $offCol = $colCheck('transactions', 'duty_officer_id') ? 'duty_officer_id' : ($colCheck('transactions', 'officer_id') ? 'officer_id' : null);
                            if (ctype_digit((string)$transId)) {
                                $st = $offCol ? $pdo->prepare("SELECT {$offCol} AS officer_id FROM transactions WHERE id = ? LIMIT 1") : null;
                                if ($st) $st->execute([$transId]);
                            } elseif ($tHasCode) {
                                $st = $offCol ? $pdo->prepare("SELECT {$offCol} AS officer_id FROM transactions WHERE transaction_id = ? LIMIT 1") : null;
                                if ($st) $st->execute([$transId]);
                            } else { $st = null; }
                            $tr = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
                            if ($tr && !empty($tr['officer_id'])) {
                                $officerName = getDutyOfficerNameLocal($pdo, $tr['officer_id']);
                            }
                        } catch (Exception $e) { /* ignore */ }
                    }
                }
                if ((!$officerName || $officerName === 'Unknown')) {
                    // Final-2 fallback: synthesize from any selected officer columns
                    $parts = [];
                    foreach (['officer_name','officer_rank_position','officer_full_name','officer_fullname'] as $k) {
                        if (!empty($row[$k])) $parts[] = trim($row[$k]);
                    }
                    $fl = trim((($row['officer_first_name'] ?? '') . ' ' . ($row['officer_last_name'] ?? '')));
                    if ($fl !== '') $parts[] = $fl;
                    $rp = trim(((($row['officer_rank'] ?? '')) . ' ' . (($row['officer_position'] ?? ''))));
                    if ($rp !== '') $parts[] = $rp;
                    if (!empty($parts)) {
                        $officerName = implode(' ', array_filter($parts));
                    }
                }
                if (!$officerName || $officerName === 'Unknown') {
                    // Final fallback: use current session duty officer if set
                    $sessOfficer = $_SESSION['duty_officer_id'] ?? null;
                    if ($sessOfficer) {
                        $officerName = getDutyOfficerNameLocal($pdo, $sessOfficer);
                    }
                }
                $row['duty_officer'] = $officerName ?: 'Unknown';
            }
            unset($row);
        }
        
        // Log how many items we found from borrowed_items table
        error_log("Found " . count($borrowed_items) . " items from borrowed_items table");
        // Also mirror to inventory.log for field debugging (avoid dumping full rows)
        try { inv_append_log_local('DEBUG', 'get_borrowed count=' . count($borrowed_items) . ' cat=' . ($category ?: '-') . ' borrower=' . ($borrower_id ?: '-') ); } catch (Exception $e) { /* ignore */ }
        
        echo json_encode([
            'success' => true,
            'data' => $borrowed_items,
            'message' => 'Borrowed items loaded successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("getBorrowedItems error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error fetching borrowed items: ' . $e->getMessage(),
            'debug' => $e->getTraceAsString()
        ]);
    }
}

function debugBorrowedItems($pdo) {
    try {
        // Accept either id (borrowed_items.id) or trans (borrowed_items.transaction_id)
        $id = $_GET['id'] ?? null;           // numeric borrowed_items.id
        $trans = $_GET['trans'] ?? null;     // can be numeric transactions.id or string transaction code stored in borrowed_items.transaction_id
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
        
        // Column checker
        $colCheck = function($table, $col) use ($pdo) {
            try { $q = $pdo->quote($col); $s = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$q}"); return $s && $s->fetch(); } catch (Exception $e) { return false; }
        };
        $hasId = $colCheck('borrowed_items', 'id');
        $hasTrans = $colCheck('borrowed_items', 'transaction_id');
        $hasQtyB = $colCheck('borrowed_items', 'quantity_borrowed');
        $hasQty = $colCheck('borrowed_items', 'quantity');
        $retCols = [];
        foreach (['actual_return_date','returned_at','return_date','date_returned'] as $c) {
            if ($colCheck('borrowed_items', $c)) { $retCols[] = $c; }
        }
        
        $fields = ['id','transaction_id','item_id','borrower_name','status'];
        if ($hasQtyB) $fields[] = 'quantity_borrowed';
        if ($hasQty) $fields[] = 'quantity';
        foreach ($retCols as $c) { $fields[] = $c; }
        
        $rows = [];
        if ($id && $hasId && ctype_digit((string)$id)) {
            $sql = 'SELECT ' . implode(',', array_map(function($f){ return 'bi.' . $f; }, $fields)) . ' FROM borrowed_items bi WHERE bi.id = ? LIMIT ' . (int)$limit;
            $st = $pdo->prepare($sql);
            $st->execute([$id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } elseif ($trans && $hasTrans) {
            $sql = 'SELECT ' . implode(',', array_map(function($f){ return 'bi.' . $f; }, $fields)) . ' FROM borrowed_items bi WHERE bi.transaction_id = ? LIMIT ' . (int)$limit;
            $st = $pdo->prepare($sql);
            $st->execute([$trans]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $sql = 'SELECT ' . implode(',', array_map(function($f){ return 'bi.' . $f; }, $fields)) . ' FROM borrowed_items bi ORDER BY bi.id DESC LIMIT ' . (int)$limit;
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        
        echo json_encode(['success' => true, 'rows' => $rows, 'count' => count($rows)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function returnItem($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $transaction_id = $input['transaction_id'] ?? null;
        $return_quantity = $input['return_quantity'] ?? 0;
        $condition = $input['condition'] ?? 'good';
        $notes = $input['notes'] ?? '';
        // Debug: log input
        inv_append_log_local('DEBUG', 'return_item start tx=' . json_encode($transaction_id) . ' qty=' . json_encode($return_quantity));
        
        if (!$transaction_id || $return_quantity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
            return;
        }
        
        // Column checks for flexible schemas
        $colCheck = function($table, $col) use ($pdo) {
            try { 
                $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); 
                $s->execute([$col]); 
                return (bool)$s->fetch(); 
            } catch (Exception $e) { 
                return false; 
            }
        };
        
        $biHasTransId = $colCheck('borrowed_items', 'transaction_id');
        $biHasQtyBorrowed = $colCheck('borrowed_items', 'quantity_borrowed');
        $biHasQty = $colCheck('borrowed_items', 'quantity');
        $biHasId = $colCheck('borrowed_items', 'id');
        $biHasStatus = $colCheck('borrowed_items', 'status');
        // Detect transaction id column for transaction_items (schema-aware)
        $tiTransIdCol = null;
        if ($colCheck('transaction_items', 'id')) {
            foreach (['transaction_id','trans_id','txn_id','t_id','transaction'] as $cand) {
                if ($colCheck('transaction_items', $cand)) { $tiTransIdCol = $cand; break; }
            }
        }
        // Transactions transaction_id string code support
        $tHasCode = $colCheck('transactions', 'transaction_id');
        inv_append_log_local('DEBUG', 'return_item flags biTrans=' . ($biHasTransId?'1':'0') . ' biQtyB=' . ($biHasQtyBorrowed?'1':'0') . ' biQty=' . ($biHasQty?'1':'0') . ' tiCol=' . ($tiTransIdCol?:'-') . ' tHasCode=' . ($tHasCode?'1':'0'));
        inv_append_log_local('DEBUG', 'return_item columns biHasId=' . ($biHasId?'1':'0') . ' biHasStatus=' . ($biHasStatus?'1':'0'));
        
        // Detect items table to use for joins/updates (schema-aware)
        $itemsTable = tableExistsLocal($pdo, 'items') ? 'items' : (tableExistsLocal($pdo, 'inventory_items') ? 'inventory_items' : 'items');
        $hasReturnable = $colCheck($itemsTable, 'can_be_returned');
        $qtyAvailCol = getQtyAvailColumnLocal($pdo, $itemsTable);
        
        // Get transaction and item details
        $transStmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $transStmt->execute([$transaction_id]);
        $transaction = $transStmt->fetch();
        // If transaction is not found, continue using borrowed_items linkage below.
        inv_append_log_local('DEBUG', 'return_item txRow=' . ($transaction? '1':'0'));

        // Get borrowed quantity - try multiple approaches
        $borrowedQty = 0;
        $itemId = null;
        $itemName = '';
        $numeric = ctype_digit((string)$transaction_id);
        $resolvedVia = 'none';
        
        // First, if numeric, try direct match by borrowed_items.id (works regardless of bi.transaction_id presence)
        if (ctype_digit((string)$transaction_id) && $colCheck('borrowed_items', 'id')) {
            $qtyCol = $biHasQtyBorrowed ? 'quantity_borrowed' : ($biHasQty ? 'quantity' : null);
            if ($qtyCol) {
                $stmt = $pdo->prepare("
                    SELECT bi.{$qtyCol} as borrowed_qty, bi.item_id, i.item_name, " . ($hasReturnable ? 'i.can_be_returned' : "'returnable'") . " as can_be_returned
                    FROM borrowed_items bi 
                    JOIN {$itemsTable} i ON bi.item_id = i.id 
                    WHERE bi.id = ?
                ");
                $stmt->execute([$transaction_id]);
                $borrowData = $stmt->fetch();
                if ($borrowData) {
                    $borrowedQty = $borrowData['borrowed_qty'];
                    $itemId = $borrowData['item_id'];
                    $itemName = $borrowData['item_name'];
                    if ($hasReturnable && $borrowData['can_be_returned'] !== 'returnable') {
                        finalizeNonReturnableReturn($pdo, $transaction_id, $borrowData['item_id'], $borrowData['item_name'], $condition, $notes);
                        echo json_encode(['success' => true, 'message' => 'Non-returnable item finalized', 'returned_quantity' => 0, 'item_name' => $borrowData['item_name']]);
                        return;
                    }
                    $resolvedVia = 'bi.id';
                }
            }
        }
 
        // First try: Use borrowed_items table (primary method - correct table)
        if ($biHasTransId) {
            $qtyCol = $biHasQtyBorrowed ? 'quantity_borrowed' : 'quantity';
            $stmt = $pdo->prepare("
                SELECT bi.{$qtyCol} as borrowed_qty, bi.item_id, i.item_name, " . ($hasReturnable ? 'i.can_be_returned' : "'returnable'") . " as can_be_returned
                FROM borrowed_items bi 
                JOIN {$itemsTable} i ON bi.item_id = i.id 
                WHERE bi.transaction_id = ?
            ");
            $stmt->execute([$transaction_id]);
            $borrowData = $stmt->fetch();
            
            if ($borrowData) {
                $borrowedQty = $borrowData['borrowed_qty'];
                $itemId = $borrowData['item_id'];
                $itemName = $borrowData['item_name'];
                if ($hasReturnable && $borrowData['can_be_returned'] !== 'returnable') {
                    finalizeNonReturnableReturn($pdo, $transaction_id, $borrowData['item_id'], $borrowData['item_name'], $condition, $notes);
                    echo json_encode(['success' => true, 'message' => 'Non-returnable item finalized', 'returned_quantity' => 0, 'item_name' => $borrowData['item_name']]);
                    return;
                }
                $resolvedVia = 'bi.transaction_id';
            }
        }
        
        // Second try: Use transaction_items table if borrowed_items didn't work
        if ((!$borrowedQty || !$itemId) && !empty($tiTransIdCol)) {
            $stmt = $pdo->prepare("
                SELECT ti.quantity as borrowed_qty, ti.item_id, i.item_name, " . ($hasReturnable ? 'i.can_be_returned' : "'returnable'") . " as can_be_returned
                FROM transaction_items ti 
                JOIN {$itemsTable} i ON ti.item_id = i.id 
                WHERE ti.{$tiTransIdCol} = ?
            ");
            $stmt->execute([$transaction_id]);
            $borrowData = $stmt->fetch();
            
            if ($borrowData) {
                $borrowedQty = $borrowData['borrowed_qty'];
                $itemId = $borrowData['item_id'];
                $itemName = $borrowData['item_name'];
                if ($hasReturnable && $borrowData['can_be_returned'] !== 'returnable') {
                    finalizeNonReturnableReturn($pdo, $transaction_id, $borrowData['item_id'], $borrowData['item_name'], $condition, $notes);
                    echo json_encode(['success' => true, 'message' => 'Non-returnable item finalized', 'returned_quantity' => 0, 'item_name' => $borrowData['item_name']]);
                    return;
                }
            }
        }
        
        // Third try: If still not found and non-numeric identifier, try transactions.transaction_id mapping
        if ((!$borrowedQty || !$itemId) && !$numeric && $tHasCode) {
            // Try via transactions.transaction_id -> transaction_items
            if (!empty($tiTransIdCol)) {
                $stmt = $pdo->prepare("
                    SELECT ti.quantity as borrowed_qty, ti.item_id, i.item_name, " . ($hasReturnable ? 'i.can_be_returned' : "'returnable'") . " as can_be_returned
                    FROM transaction_items ti 
                    JOIN transactions t ON ti.{$tiTransIdCol} = t.id
                    JOIN {$itemsTable} i ON ti.item_id = i.id 
                    WHERE t.transaction_id = ?
                ");
                $stmt->execute([$transaction_id]);
                $borrowData = $stmt->fetch();
                if ($borrowData) {
                    $borrowedQty = $borrowData['borrowed_qty'];
                    $itemId = $borrowData['item_id'];
                    $itemName = $borrowData['item_name'];
                    if ($hasReturnable && $borrowData['can_be_returned'] !== 'returnable') {
                        finalizeNonReturnableReturn($pdo, $transaction_id, $borrowData['item_id'], $borrowData['item_name'], $condition, $notes);
                        echo json_encode(['success' => true, 'message' => 'Non-returnable item finalized', 'returned_quantity' => 0, 'item_name' => $borrowData['item_name']]);
                        return;
                    }
                }
            }
            // Also try borrowed_items by transaction_id string
            if ((!$borrowedQty || !$itemId) && $biHasTransId) {
                $qtyCol = $biHasQtyBorrowed ? 'quantity_borrowed' : ($biHasQty ? 'quantity' : null);
                if ($qtyCol) {
                    $stmt = $pdo->prepare("
                        SELECT bi.{$qtyCol} as borrowed_qty, bi.item_id, i.item_name, " . ($hasReturnable ? "i.can_be_returned" : "'returnable'") . " as can_be_returned
                        FROM borrowed_items bi 
                        JOIN {$itemsTable} i ON bi.item_id = i.id 
                        WHERE bi.transaction_id = ? AND (bi.status IS NULL OR bi.status = 'borrowed')
                    ");
                    $stmt->execute([$transaction_id]);
                    $borrowData = $stmt->fetch();
                    if ($borrowData) {
                        $borrowedQty = $borrowData['borrowed_qty'];
                        $itemId = $borrowData['item_id'];
                        $itemName = $borrowData['item_name'];
                        if ($hasReturnable && $borrowData['can_be_returned'] !== 'returnable') {
                            finalizeNonReturnableReturn($pdo, $transaction_id, $borrowData['item_id'], $borrowData['item_name'], $condition, $notes);
                            echo json_encode(['success' => true, 'message' => 'Non-returnable item finalized', 'returned_quantity' => 0, 'item_name' => $borrowData['item_name']]);
                            return;
                        }
                    }
                }
            }
        }

        // Fourth try: Use borrowed_items table if still not found and we have ID column but no transaction_id column
        if ((!$borrowedQty || !$itemId) && $biHasId && !$biHasTransId) {
            $qtyCol = $biHasQtyBorrowed ? 'quantity_borrowed' : 'quantity';
            $stmt = $pdo->prepare("
                SELECT bi.{$qtyCol} as borrowed_qty, bi.item_id, i.item_name, " . ($hasReturnable ? 'i.can_be_returned' : "'returnable'") . " as can_be_returned
                FROM borrowed_items bi 
                JOIN {$itemsTable} i ON bi.item_id = i.id 
                WHERE bi.id = ?
            ");
            $stmt->execute([$transaction_id]);
            $borrowData = $stmt->fetch();
            
            if ($borrowData) {
                $borrowedQty = $borrowData['borrowed_qty'];
                $itemId = $borrowData['item_id'];
                $itemName = $borrowData['item_name'];
                if ($hasReturnable && $borrowData['can_be_returned'] !== 'returnable') {
                    finalizeNonReturnableReturn($pdo, $transaction_id, $borrowData['item_id'], $borrowData['item_name'], $condition, $notes);
                    echo json_encode(['success' => true, 'message' => 'Non-returnable item finalized', 'returned_quantity' => 0, 'item_name' => $borrowData['item_name']]);
                    return;
                }
            }
        }
        
        // Fifth try: Use borrowed_items table with ID matching (if no transaction_id column)
        if ((!$borrowedQty || !$itemId) && $biHasId && !$biHasTransId) {
            $qtyCol = $biHasQtyBorrowed ? 'quantity_borrowed' : 'quantity';
            $stmt = $pdo->prepare("
                SELECT bi.{$qtyCol} as borrowed_qty, bi.item_id, i.item_name, " . ($hasReturnable ? 'i.can_be_returned' : "'returnable'") . " as can_be_returned
                FROM borrowed_items bi 
                JOIN {$itemsTable} i ON bi.item_id = i.id 
                WHERE bi.id = ? AND bi.status = 'borrowed'
            ");
            $stmt->execute([$transaction_id]);
            $borrowData = $stmt->fetch();
            
            if ($borrowData) {
                $borrowedQty = $borrowData['borrowed_qty'];
                $itemId = $borrowData['item_id'];
                $itemName = $borrowData['item_name'];
                if ($hasReturnable && $borrowData['can_be_returned'] !== 'returnable') {
                    finalizeNonReturnableReturn($pdo, $transaction_id, $borrowData['item_id'], $borrowData['item_name'], $condition, $notes);
                    echo json_encode(['success' => true, 'message' => 'Non-returnable item finalized', 'returned_quantity' => 0, 'item_name' => $borrowData['item_name']]);
                    return;
                }
            }
        }
        
        // Last resort: try to find from transaction details
        if ((!$borrowedQty || !$itemId) && isset($transaction['borrower_id']) && isset($transaction['item_id'])) {
            $itemId = $transaction['item_id'];
            $debugInfo = [
                'transaction_id' => $transaction_id,
                'biHasTransId' => $biHasTransId,
                'tiTransIdCol' => $tiTransIdCol,
                'borrowedQty' => $borrowedQty,
                'itemId' => $itemId,
                'transaction' => $transaction
            ];
            error_log("Return debug: " . json_encode($debugInfo));
            echo json_encode(['success' => false, 'message' => 'Could not determine borrowed quantity or item. Debug: ' . json_encode($debugInfo)]);
            return;
        }
        
        if ($return_quantity > $borrowedQty) {
            echo json_encode(['success' => false, 'message' => 'Return quantity exceeds borrowed quantity']);
            return;
        }
        
        // Update transaction status (schema-aware for returned_at column)
        if ($return_quantity == $borrowedQty) {
            inv_append_log_local('DEBUG', 'return_item full_return path tx=' . json_encode($transaction_id));
            // Full return - check if returned_at column exists
            $hasReturnedAt = false;
            try {
                $colStmt = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'returned_at'");
                $colStmt->execute();
                $hasReturnedAt = (bool)$colStmt->fetch();
            } catch (Exception $e) { /* ignore */ }
            
            if ($hasReturnedAt) {
                $stmt = $pdo->prepare("UPDATE transactions SET status = 'returned', returned_at = NOW(), return_condition = ?, return_notes = ? WHERE id = ?");
                $stmt->execute([$condition, $notes, $transaction_id]);
                inv_append_log_local('DEBUG', 'return_item transactions set returned (returned_at) rows=' . $stmt->rowCount());
            } else {
                // Check if return_condition and return_notes columns exist
                $hasReturnCondition = false;
                $hasReturnNotes = false;
                try {
                    $colStmt = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'return_condition'");
                    $colStmt->execute();
                    $hasReturnCondition = (bool)$colStmt->fetch();
                    
                    $colStmt = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'return_notes'");
                    $colStmt->execute();
                    $hasReturnNotes = (bool)$colStmt->fetch();
                } catch (Exception $e) { /* ignore */ }
                
                if ($hasReturnCondition && $hasReturnNotes) {
                    $stmt = $pdo->prepare("UPDATE transactions SET status = 'returned', return_condition = ?, return_notes = ? WHERE id = ?");
                    $stmt->execute([$condition, $notes, $transaction_id]);
                    inv_append_log_local('DEBUG', 'return_item transactions set returned (cond+notes) rows=' . $stmt->rowCount());
                } else {
                    $stmt = $pdo->prepare("UPDATE transactions SET status = 'returned' WHERE id = ?");
                    $stmt->execute([$transaction_id]);
                    inv_append_log_local('DEBUG', 'return_item transactions set returned rows=' . $stmt->rowCount());
                }
            }
            // Also mark borrowed_items as returned and zero quantity where possible
            $biSetParts = [];
            if ($biHasStatus) { $biSetParts[] = "status = 'returned'"; }
            // Detect a return date column on borrowed_items for updates
            $biReturnDateCol = null; foreach (['actual_return_date','returned_at','return_date','date_returned'] as $c) { if ($colCheck('borrowed_items', $c)) { $biReturnDateCol = $c; break; } }
            if ($biReturnDateCol) { $biSetParts[] = $biReturnDateCol . " = NOW()"; }
            $biSetSql = !empty($biSetParts) ? ('SET ' . implode(', ', $biSetParts)) : '';
            if ($biHasTransId) {
                try {
                    $rows = 0;
                    if (ctype_digit((string)$transaction_id)) {
                        if ($biSetSql !== '') { $st = $pdo->prepare("UPDATE borrowed_items {$biSetSql} WHERE transaction_id = ?"); $st->execute([$transaction_id]); $rows += $st->rowCount(); }
                        if ($biHasQtyBorrowed) { $st0 = $pdo->prepare("UPDATE borrowed_items SET quantity_borrowed = 0 WHERE transaction_id = ?"); $st0->execute([$transaction_id]); inv_append_log_local('DEBUG', 'return_item BI zero qty_borrowed by trans_id rows=' . $st0->rowCount()); }
                        elseif ($biHasQty) { $st0 = $pdo->prepare("UPDATE borrowed_items SET quantity = 0 WHERE transaction_id = ?"); $st0->execute([$transaction_id]); inv_append_log_local('DEBUG', 'return_item BI zero quantity by trans_id rows=' . $st0->rowCount()); }
                    } else {
                        // Try numeric id mapping from TXN code
                        $rid = null; if ($tHasCode) { $rs = $pdo->prepare("SELECT id FROM transactions WHERE transaction_id = ? LIMIT 1"); $rs->execute([$transaction_id]); $rid = $rs->fetchColumn(); }
                        if ($rid) {
                            if ($biSetSql !== '') { $st = $pdo->prepare("UPDATE borrowed_items {$biSetSql} WHERE transaction_id = ?"); $st->execute([$rid]); $rows += $st->rowCount(); }
                            if ($biHasQtyBorrowed) { $st0 = $pdo->prepare("UPDATE borrowed_items SET quantity_borrowed = 0 WHERE transaction_id = ?"); $st0->execute([$rid]); inv_append_log_local('DEBUG', 'return_item BI zero qty_borrowed by mapped trans_id rows=' . $st0->rowCount()); }
                            elseif ($biHasQty) { $st0 = $pdo->prepare("UPDATE borrowed_items SET quantity = 0 WHERE transaction_id = ?"); $st0->execute([$rid]); inv_append_log_local('DEBUG', 'return_item BI zero quantity by mapped trans_id rows=' . $st0->rowCount()); }
                        }
                        // Also attempt direct TXN code update if stored as string
                        if ($biSetSql !== '') { $st = $pdo->prepare("UPDATE borrowed_items {$biSetSql} WHERE transaction_id = ?"); $st->execute([$transaction_id]); $rows += $st->rowCount(); }
                        if ($biHasQtyBorrowed) { $st0 = $pdo->prepare("UPDATE borrowed_items SET quantity_borrowed = 0 WHERE transaction_id = ?"); $st0->execute([$transaction_id]); inv_append_log_local('DEBUG', 'return_item BI zero qty_borrowed by string trans_id rows=' . $st0->rowCount()); }
                        elseif ($biHasQty) { $st0 = $pdo->prepare("UPDATE borrowed_items SET quantity = 0 WHERE transaction_id = ?"); $st0->execute([$transaction_id]); inv_append_log_local('DEBUG', 'return_item BI zero quantity by string trans_id rows=' . $st0->rowCount()); }
                    }
                    inv_append_log_local('DEBUG', 'return_item full BI updates rows=' . $rows);
                    // Snapshot BI row state after updates (by id/transaction_id)
                    try {
                        if ($biHasId && ctype_digit((string)$transaction_id)) {
                            $snap = $pdo->prepare("SELECT id, transaction_id, status, quantity_borrowed, quantity, actual_return_date, returned_at, return_date, date_returned FROM borrowed_items WHERE id = ? LIMIT 1");
                            $snap->execute([$transaction_id]);
                            $row = $snap->fetch(PDO::FETCH_ASSOC);
                            inv_append_log_local('DEBUG', 'return_item BI snapshot by id=' . json_encode($row));
                        }
                    } catch (Exception $e) { /* ignore */ }
                } catch (Exception $e) { /* ignore */ }
            }
            if ($biHasId && ctype_digit((string)$transaction_id)) {
                try {
                    if ($biSetSql !== '') { $st = $pdo->prepare("UPDATE borrowed_items {$biSetSql} WHERE id = ?"); $st->execute([$transaction_id]); inv_append_log_local('DEBUG', 'return_item BI set returned by id rows=' . $st->rowCount()); }
                    if ($biHasQtyBorrowed) { $st0 = $pdo->prepare("UPDATE borrowed_items SET quantity_borrowed = 0 WHERE id = ?"); $st0->execute([$transaction_id]); inv_append_log_local('DEBUG', 'return_item BI zero qty_borrowed by id rows=' . $st0->rowCount()); }
                    elseif ($biHasQty) { $st0 = $pdo->prepare("UPDATE borrowed_items SET quantity = 0 WHERE id = ?"); $st0->execute([$transaction_id]); inv_append_log_local('DEBUG', 'return_item BI zero quantity by id rows=' . $st0->rowCount()); }
                    // Snapshot by id after direct id updates
                    try {
                        $snap = $pdo->prepare("SELECT id, transaction_id, status, quantity_borrowed, quantity, actual_return_date, returned_at, return_date, date_returned FROM borrowed_items WHERE id = ? LIMIT 1");
                        $snap->execute([$transaction_id]);
                        $row = $snap->fetch(PDO::FETCH_ASSOC);
                        inv_append_log_local('DEBUG', 'return_item BI snapshot (id updates) id=' . (int)$transaction_id . ' row=' . json_encode($row));
                    } catch (Exception $e) { /* ignore */ }
                } catch (Exception $e) { /* ignore */ }
            }
            // If there are no marker columns at all, delete the row(s) so it won't show again
            $hasMarkers = ($biHasStatus || !empty($biReturnDateCol) || $biHasQtyBorrowed || $biHasQty);
            if (!$hasMarkers) {
                try {
                    $delRows = 0;
                    // Pre-delete snapshot counts
                    try {
                        if ($biHasId && ctype_digit((string)$transaction_id)) {
                            $cnt = $pdo->prepare("SELECT COUNT(*) FROM borrowed_items WHERE id = ?");
                            $cnt->execute([$transaction_id]);
                            inv_append_log_local('DEBUG', 'return_item pre-delete count by id=' . $cnt->fetchColumn());
                        }
                        if ($biHasTransId) {
                            $paramTransTmp = ctype_digit((string)$transaction_id) ? $transaction_id : ($tHasCode ? ($pdo->query("SELECT id FROM transactions WHERE transaction_id=" . $pdo->quote($transaction_id))->fetchColumn() ?: $transaction_id) : $transaction_id);
                            $cnt = $pdo->prepare("SELECT COUNT(*) FROM borrowed_items WHERE transaction_id = ?");
                            $cnt->execute([$paramTransTmp]);
                            inv_append_log_local('DEBUG', 'return_item pre-delete count by trans_id=' . json_encode($paramTransTmp) . ' cnt=' . $cnt->fetchColumn());
                        }
                    } catch (Exception $e) { /* ignore */ }
                    if ($biHasId && ctype_digit((string)$transaction_id)) {
                        $st = $pdo->prepare("DELETE FROM borrowed_items WHERE id = ?");
                        $st->execute([$transaction_id]);
                        $delRows += $st->rowCount();
                    }
                    if ($biHasTransId) {
                        if (ctype_digit((string)$transaction_id)) {
                            $st = $pdo->prepare("DELETE FROM borrowed_items WHERE transaction_id = ?");
                            $st->execute([$transaction_id]);
                            $delRows += $st->rowCount();
                        } else {
                            // Map TXN code → numeric id, and also try direct string match
                            $rid = null; if ($tHasCode) { $rs = $pdo->prepare("SELECT id FROM transactions WHERE transaction_id = ? LIMIT 1"); $rs->execute([$transaction_id]); $rid = $rs->fetchColumn(); }
                            if ($rid) {
                                $st = $pdo->prepare("DELETE FROM borrowed_items WHERE transaction_id = ?");
                                $st->execute([$rid]);
                                $delRows += $st->rowCount();
                            }
                            $st = $pdo->prepare("DELETE FROM borrowed_items WHERE transaction_id = ?");
                            $st->execute([$transaction_id]);
                            $delRows += $st->rowCount();
                        }
                    }
                    inv_append_log_local('DEBUG', 'return_item full BI delete rows=' . $delRows);
                } catch (Exception $e) { /* ignore */ }
            }
            // Removed duplicate delete block (no-op)
        } else {
            // Partial return - update quantity in appropriate table
            $newQty = null;
            $didBIUpdate = false;
            // If client passed a borrowed_items.id, update by id first
            if ($biHasId && ctype_digit((string)$transaction_id)) {
                if ($biHasQtyBorrowed) {
                    $stmt = $pdo->prepare("UPDATE borrowed_items SET quantity_borrowed = quantity_borrowed - ? WHERE id = ?");
                    $stmt->execute([$return_quantity, $transaction_id]);
                    $didBIUpdate = ($stmt->rowCount() >= 0); // best-effort
                    inv_append_log_local('DEBUG', 'return_item partial by id qty_borrowed delta=' . (int)$return_quantity . ' rows=' . $stmt->rowCount());
                } elseif ($biHasQty) {
                    $stmt = $pdo->prepare("UPDATE borrowed_items SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$return_quantity, $transaction_id]);
                    $didBIUpdate = ($stmt->rowCount() >= 0);
                    inv_append_log_local('DEBUG', 'return_item partial by id quantity delta=' . (int)$return_quantity . ' rows=' . $stmt->rowCount());
                }
                $newQty = max(0, (int)$borrowedQty - (int)$return_quantity);
                if ($didBIUpdate && $newQty <= 0) {
                    try {
                        $pdo->prepare("UPDATE borrowed_items SET status = 'returned', actual_return_date = NOW() WHERE id = ?")
                            ->execute([$transaction_id]);
                        inv_append_log_local('DEBUG', 'return_item partial by id set returned where newQty<=0');
                    } catch (Exception $e) { /* ignore */ }
                    // If we still have no marker columns, delete the row
                    $hasMarkers = ($biHasStatus || $biHasQtyBorrowed || $biHasQty || $colCheck('borrowed_items','actual_return_date') || $colCheck('borrowed_items','returned_at') || $colCheck('borrowed_items','return_date') || $colCheck('borrowed_items','date_returned'));
                    if (!$hasMarkers) {
                        try { $pdo->prepare("DELETE FROM borrowed_items WHERE id = ?")->execute([$transaction_id]); } catch (Exception $e) { /* ignore */ }
                    }
                }
            }
            // Fallback: update by transaction_id mapping
            if (!$didBIUpdate && $biHasTransId) {
                // Resolve correct transaction_id value for borrowed_items
                $paramTrans = $transaction_id;
                if (!ctype_digit((string)$paramTrans) && $tHasCode) {
                    try {
                        $rid = null;
                        $rs = $pdo->prepare("SELECT id FROM transactions WHERE transaction_id = ? LIMIT 1");
                        $rs->execute([$transaction_id]);
                        $rid = $rs->fetchColumn();
                        if ($rid) { $paramTrans = $rid; }
                    } catch (Exception $e) { /* ignore */ }
                }
                if ($biHasQtyBorrowed) {
                    $stmt = $pdo->prepare("UPDATE borrowed_items SET quantity_borrowed = quantity_borrowed - ? WHERE transaction_id = ?");
                    $stmt->execute([$return_quantity, $paramTrans]);
                    inv_append_log_local('DEBUG', 'return_item partial by trans_id qty_borrowed delta=' . (int)$return_quantity . ' rows=' . $stmt->rowCount());
                } elseif ($biHasQty) {
                    $stmt = $pdo->prepare("UPDATE borrowed_items SET quantity = quantity - ? WHERE transaction_id = ?");
                    $stmt->execute([$return_quantity, $paramTrans]);
                    inv_append_log_local('DEBUG', 'return_item partial by trans_id quantity delta=' . (int)$return_quantity . ' rows=' . $stmt->rowCount());
                }
                $newQty = max(0, (int)$borrowedQty - (int)$return_quantity);
                if ($newQty <= 0) {
                    try {
                        $pdo->prepare("UPDATE borrowed_items SET status = 'returned', actual_return_date = NOW() WHERE transaction_id = ?")
                            ->execute([$paramTrans]);
                        inv_append_log_local('DEBUG', 'return_item partial by trans_id set returned where newQty<=0');
                    } catch (Exception $e) { /* ignore */ }
                    // If we still have no marker columns, delete the row(s)
                    $hasMarkers = ($biHasStatus || $biHasQtyBorrowed || $biHasQty || $colCheck('borrowed_items','actual_return_date') || $colCheck('borrowed_items','returned_at') || $colCheck('borrowed_items','return_date') || $colCheck('borrowed_items','date_returned'));
                    if (!$hasMarkers) {
                        try { $pdo->prepare("DELETE FROM borrowed_items WHERE transaction_id = ?")->execute([$paramTrans]); } catch (Exception $e) { /* ignore */ }
                    }
                }
            } elseif (!$didBIUpdate && !empty($tiTransIdCol)) {
                $stmt = $pdo->prepare("UPDATE transaction_items SET quantity = quantity - ? WHERE {$tiTransIdCol} = ? AND item_id = ?");
                $stmt->execute([$return_quantity, $transaction_id, $itemId]);
            }
            
            // Create return record if returns table exists
            try {
                $stmt = $pdo->prepare("INSERT INTO returns (transaction_id, quantity_returned, return_condition, return_notes, returned_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$transaction_id, $return_quantity, $condition, $notes]);
            } catch (Exception $e) {
                // Returns table might not exist, continue without it
            }
        }
        
        // Update item stock and decrement borrowed_quantity if present
        $hasBorrowedCol = $colCheck($itemsTable, 'borrowed_quantity');
        if ($hasBorrowedCol) {
            $stmt = $pdo->prepare("UPDATE {$itemsTable} SET {$qtyAvailCol} = {$qtyAvailCol} + ?, borrowed_quantity = GREATEST(0, borrowed_quantity - ?) WHERE id = ?");
            $stmt->execute([$return_quantity, $return_quantity, $itemId]);
        } else {
            $stmt = $pdo->prepare("UPDATE {$itemsTable} SET {$qtyAvailCol} = {$qtyAvailCol} + ? WHERE id = ?");
            $stmt->execute([$return_quantity, $itemId]);
        }
        
        // Prepare log details
        $officerId = $_SESSION['duty_officer_id'] ?? null;
        $officerName = getDutyOfficerNameLocal($pdo, $officerId);
        // Try to get borrower name
        $borrowerName = $transaction['borrower_name'] ?? null;
        if (!$borrowerName) {
            try {
                $nameStmt = $pdo->prepare("SELECT borrower_name FROM borrowed_items WHERE transaction_id = ? LIMIT 1");
                $nameStmt->execute([$transaction_id]);
                $nr = $nameStmt->fetch();
                if ($nr && !empty($nr['borrower_name'])) { $borrowerName = $nr['borrower_name']; }
                if (!$borrowerName) {
                    $nameStmt = $pdo->prepare("SELECT borrower_name FROM borrowed_items WHERE id = ? LIMIT 1");
                    $nameStmt->execute([$transaction_id]);
                    $nr = $nameStmt->fetch();
                    if ($nr && !empty($nr['borrower_name'])) { $borrowerName = $nr['borrower_name']; }
                }
            } catch (Exception $e) { /* ignore */ }
        }
        $borrowerDisp = $borrowerName ?: 'Unknown';
        $txnCode = $transaction['transaction_id'] ?? ('BI#' . $transaction_id);

        // Mirror this return into transactions/transaction_items so it appears in the dashboard's table
        try {
            if (tableExistsLocal($pdo, 'transactions')) {
                $retTxnCode = 'RET' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                $cols = [];
                $ph   = [];
                $vals = [];
                // Always include a transaction_id string code if column exists
                if ($colCheck('transactions', 'transaction_id')) { $cols[] = 'transaction_id'; $ph[] = '?'; $vals[] = $retTxnCode; }
                if ($colCheck('transactions', 'type')) { $cols[] = 'type'; $ph[] = '?'; $vals[] = 'return'; }
                // Officer
                if (!empty($officerId)) {
                    if ($colCheck('transactions', 'duty_officer_id')) { $cols[] = 'duty_officer_id'; $ph[] = '?'; $vals[] = $officerId; }
                    elseif ($colCheck('transactions', 'officer_id')) { $cols[] = 'officer_id'; $ph[] = '?'; $vals[] = $officerId; }
                }
                // Borrower display name if column exists
                if ($colCheck('transactions', 'borrower_name')) { $cols[] = 'borrower_name'; $ph[] = '?'; $vals[] = $borrowerDisp; }
                // Safe default status (only if column exists)
                if ($colCheck('transactions', 'status')) { $cols[] = 'status'; $ph[] = '?'; $vals[] = 'completed'; }
                // Store condition/notes if schema supports it
                if ($colCheck('transactions', 'return_condition')) { $cols[] = 'return_condition'; $ph[] = '?'; $vals[] = $condition; }
                if ($colCheck('transactions', 'return_notes')) { $cols[] = 'return_notes'; $ph[] = '?'; $vals[] = $notes; }

                if (!empty($cols)) {
                    $sql = 'INSERT INTO transactions (' . implode(',', $cols) . ') VALUES (' . implode(',', $ph) . ')';
                    $ins = $pdo->prepare($sql);
                    $ins->execute($vals);
                    $retTxnDbId = $pdo->lastInsertId();

                    // Also create a transaction_items row so dashboard join shows the item/qty
                    if (tableExistsLocal($pdo, 'transaction_items') && $colCheck('transaction_items', 'transaction_id')) {
                        if ($colCheck('transaction_items', 'unit_price')) {
                            $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity, unit_price) VALUES (?, ?, ?, 0)")
                                ->execute([$retTxnDbId, $itemId, (int)$return_quantity]);
                        } else {
                            $pdo->prepare("INSERT INTO transaction_items (transaction_id, item_id, quantity) VALUES (?, ?, ?)")
                                ->execute([$retTxnDbId, $itemId, (int)$return_quantity]);
                        }
                    }
                }
            }
        } catch (Exception $e) { /* best-effort only; do not block */ }

        inv_append_log_local('RETURN', "Src=borrowed_items.php Officer={$officerName}" . ($officerId ? " (ID:{$officerId})" : '') . " Borrower={$borrowerDisp} Item={$itemName} (ID:{$itemId}) Qty={$return_quantity} Txn={$txnCode} Condition={$condition} Notes=" . preg_replace('/[\r\n]+/', ' ', (string)$notes));

        // After logging, if this was a FULL return, hard-delete the borrowed_items row so it cannot linger
        if ((int)$return_quantity === (int)$borrowedQty) {
            try {
                $delRows = 0;
                // Prefer deleting by borrowed_items.id (this is what UI passes as transaction_id)
                if ($biHasId && ctype_digit((string)$transaction_id)) {
                    $st = $pdo->prepare("DELETE FROM borrowed_items WHERE id = ?");
                    $st->execute([$transaction_id]);
                    $delRows += $st->rowCount();
                    inv_append_log_local('DEBUG', 'return_item hard delete by id rows=' . $st->rowCount());
                }
                // If nothing was deleted and borrowed_items.transaction_id exists, try by transaction linkage
                if ($delRows === 0 && $biHasTransId) {
                    $paramTrans = $transaction_id;
                    // Map TXN code to numeric id when needed
                    if (!ctype_digit((string)$paramTrans) && $tHasCode) {
                        try {
                            $rs = $pdo->prepare("SELECT id FROM transactions WHERE transaction_id = ? LIMIT 1");
                            $rs->execute([$transaction_id]);
                            $rid = $rs->fetchColumn();
                            if ($rid) { $paramTrans = $rid; }
                        } catch (Exception $e) { /* ignore */ }
                    }
                    $st = $pdo->prepare("DELETE FROM borrowed_items WHERE transaction_id = ?");
                    $st->execute([$paramTrans]);
                    $delRows += $st->rowCount();
                    inv_append_log_local('DEBUG', 'return_item hard delete by trans_id=' . json_encode($paramTrans) . ' rows=' . $st->rowCount());
                }
                // Final fallback: delete by item_id and borrower_name when markers indicate returned/zeroed
                if ($delRows === 0 && $itemId) {
                    try {
                        $condParts = [];
                        if ($biHasQtyBorrowed) { $condParts[] = 'quantity_borrowed <= 0'; }
                        if ($biHasQty) { $condParts[] = 'quantity <= 0'; }
                        if ($biHasStatus) { $condParts[] = "LOWER(status) IN ('returned','complete','completed')"; }
                        // Detect any return date column
                        $biRetCol = null; foreach (['actual_return_date','returned_at','return_date','date_returned'] as $c) { if ($colCheck('borrowed_items', $c)) { $biRetCol = $c; break; } }
                        if ($biRetCol) { $condParts[] = "({$biRetCol} IS NOT NULL AND {$biRetCol} <> '' AND {$biRetCol} <> '0000-00-00' AND {$biRetCol} <> '0000-00-00 00:00:00')"; }
                        if (!empty($condParts)) {
                            $where = 'item_id = ? AND (' . implode(' OR ', $condParts) . ')';
                            $params = [$itemId];
                            if (!empty($borrowerName)) {
                                $where = 'borrower_name = ? AND ' . $where;
                                array_unshift($params, $borrowerName);
                            }
                            $st = $pdo->prepare("DELETE FROM borrowed_items WHERE {$where}");
                            $st->execute($params);
                            $delRows += $st->rowCount();
                            inv_append_log_local('DEBUG', 'return_item hard delete by item+borrower itemId=' . (int)$itemId . ' borrower=' . json_encode($borrowerName) . ' rows=' . $st->rowCount());
                        }
                    } catch (Exception $e) { /* ignore */ }
                }
                inv_append_log_local('DEBUG', 'return_item hard delete rows=' . $delRows);
            } catch (Exception $e) {
                inv_append_log_local('ERROR', 'return_item hard delete failed: ' . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Item returned successfully',
            'returned_quantity' => $return_quantity,
            'item_name' => $itemName
        ]);
        
    } catch (Exception $e) {
        error_log("Return item error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>