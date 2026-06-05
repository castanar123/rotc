<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/db.php';

function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
} // Added closing brace here

function tableExists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function getItemsTable($pdo) {
    // Per requirement: use only the 'items' table as the source of truth
    return 'items';
}

try {
    $action = $_GET['action'] ?? 'get_all';
    
    switch ($action) {
        case 'get_all':
            getAllItems();
            break;
        case 'get_by_category':
            getItemsByCategory();
            break;
        case 'search':
            searchItems();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getAllItems() {
    global $pdo;
    $itemsTable = getItemsTable($pdo);
    $hasCategory = columnExists($pdo, $itemsTable, 'category');
    $hasItemCode = columnExists($pdo, $itemsTable, 'item_code');
    $hasQrCode   = columnExists($pdo, $itemsTable, 'qr_code');
    $hasUnit     = columnExists($pdo, $itemsTable, 'unit');
    $hasReturnable = columnExists($pdo, $itemsTable, 'can_be_returned');
    // Determine quantity column and WHERE filter
    $qtySelect = '';
    $qtyWhereCol = '';
    if (columnExists($pdo, $itemsTable, 'available_quantity')) {
        $qtySelect = 'available_quantity';
        $qtyWhereCol = 'available_quantity';
    } elseif (columnExists($pdo, $itemsTable, 'quantity_available')) {
        $qtySelect = 'quantity_available AS available_quantity';
        $qtyWhereCol = 'quantity_available';
    } elseif (columnExists($pdo, $itemsTable, 'qty_available')) {
        $qtySelect = 'qty_available AS available_quantity';
        $qtyWhereCol = 'qty_available';
    } else {
        $qtySelect = '0 AS available_quantity';
    }
    $selectCols = [];
    $selectCols[] = 'id';
    if ($hasCategory) { $selectCols[] = 'category'; }
    if ($hasItemCode) { $selectCols[] = 'item_code'; }
    if ($hasQrCode)   { $selectCols[] = 'qr_code'; }
    $selectCols[] = 'item_name';
    $selectCols[] = $qtySelect;
    $selectCols[] = $hasUnit ? 'unit' : "'pcs' AS unit";
    if ($hasReturnable) { $selectCols[] = 'can_be_returned'; }
    $select = implode(', ', $selectCols);
    $order = $hasCategory ? 'ORDER BY category, item_name' : 'ORDER BY item_name';
    $sql = "SELECT $select FROM {$itemsTable}";
    if (!empty($qtyWhereCol)) {
        $sql .= " WHERE {$qtyWhereCol} > 0";
    }
    $sql .= " $order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group items by category if available, else under 'All'
    $grouped_items = [];
    foreach ($items as $item) {
        $category = $hasCategory ? ($item['category'] ?? 'Uncategorized') : 'All';
        if (!isset($grouped_items[$category])) {
            $grouped_items[$category] = [];
        }
        $grouped_items[$category][] = $item;
    }
    
    echo json_encode([
        'success' => true, 
        'items' => $grouped_items,
        'total_items' => count($items)
    ]);
}

function getItemsByCategory() {
    global $pdo;
    
    $category = $_GET['category'] ?? '';
    
    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Category is required']);
        return;
    }
    $itemsTable = getItemsTable($pdo);
    $hasCategory = columnExists($pdo, $itemsTable, 'category');
    if (!$hasCategory) {
        echo json_encode(['success' => true, 'items' => [], 'category' => $category, 'total_items' => 0]);
        return;
    }
    $hasItemCode = columnExists($pdo, $itemsTable, 'item_code');
    $hasQrCode   = columnExists($pdo, $itemsTable, 'qr_code');
    $hasUnit     = columnExists($pdo, $itemsTable, 'unit');
    $hasReturnable = columnExists($pdo, $itemsTable, 'can_be_returned');
    // Determine quantity column and WHERE filter
    $qtySelect = '';
    $qtyWhereCol = '';
    if (columnExists($pdo, $itemsTable, 'available_quantity')) {
        $qtySelect = 'available_quantity';
        $qtyWhereCol = 'available_quantity';
    } elseif (columnExists($pdo, $itemsTable, 'quantity_available')) {
        $qtySelect = 'quantity_available AS available_quantity';
        $qtyWhereCol = 'quantity_available';
    } elseif (columnExists($pdo, $itemsTable, 'qty_available')) {
        $qtySelect = 'qty_available AS available_quantity';
        $qtyWhereCol = 'qty_available';
    } else {
        $qtySelect = '0 AS available_quantity';
    }
    $selectParts = [];
    $selectParts[] = 'id';
    if ($hasItemCode) { $selectParts[] = 'item_code'; }
    if ($hasQrCode)   { $selectParts[] = 'qr_code'; }
    $selectParts[] = 'item_name';
    $selectParts[] = $qtySelect;
    $selectParts[] = $hasUnit ? 'unit' : "'pcs' AS unit";
    if ($hasReturnable) { $selectParts[] = 'can_be_returned'; }
    $selectParts[] = 'category';
    $select = implode(', ', $selectParts);
    $stmt = $pdo->prepare("
        SELECT $select
        FROM {$itemsTable} 
        WHERE category = ?" . (!empty($qtyWhereCol) ? " AND {$qtyWhereCol} > 0" : "") . "
        ORDER BY item_name
    ");
    $stmt->execute([$category]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'items' => $items,
        'category' => $category,
        'total_items' => count($items)
    ]);
}

function searchItems() {
    global $pdo;
    
    $search_term = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    if (empty($search_term)) {
        echo json_encode(['success' => false, 'message' => 'Search term is required']);
        return;
    }
    $itemsTable = getItemsTable($pdo);
    $hasCategory = columnExists($pdo, $itemsTable, 'category');
    $hasItemCode = columnExists($pdo, $itemsTable, 'item_code');
    $hasQrCode   = columnExists($pdo, $itemsTable, 'qr_code');
    $hasUnit     = columnExists($pdo, $itemsTable, 'unit');
    $hasReturnable = columnExists($pdo, $itemsTable, 'can_be_returned');
    // Determine quantity column and WHERE filter
    $qtySelect = '';
    $qtyWhereCol = '';
    if (columnExists($pdo, $itemsTable, 'available_quantity')) {
        $qtySelect = 'available_quantity';
        $qtyWhereCol = 'available_quantity';
    } elseif (columnExists($pdo, $itemsTable, 'quantity_available')) {
        $qtySelect = 'quantity_available AS available_quantity';
        $qtyWhereCol = 'quantity_available';
    } elseif (columnExists($pdo, $itemsTable, 'qty_available')) {
        $qtySelect = 'qty_available AS available_quantity';
        $qtyWhereCol = 'qty_available';
    } else {
        $qtySelect = '0 AS available_quantity';
    }
    $selectParts = [];
    $selectParts[] = 'id';
    if ($hasItemCode) { $selectParts[] = 'item_code'; }
    if ($hasQrCode)   { $selectParts[] = 'qr_code'; }
    $selectParts[] = 'item_name';
    $selectParts[] = $qtySelect;
    $selectParts[] = $hasUnit ? 'unit' : "'pcs' AS unit";
    if ($hasCategory) { $selectParts[] = 'category'; }
    if ($hasReturnable) { $selectParts[] = 'can_be_returned'; }
    $select = implode(', ', $selectParts);
    $sql = "
        SELECT $select
        FROM {$itemsTable} 
        WHERE (item_name LIKE ?" 
            . ($hasItemCode ? " OR item_code LIKE ?" : "") 
            . ($hasQrCode ? " OR qr_code LIKE ?" : "") 
            . ")" 
            . (!empty($qtyWhereCol) ? " AND {$qtyWhereCol} > 0" : "") . "
    ";
    
    $params = ["%$search_term%"]; 
    if ($hasItemCode) { $params[] = "%$search_term%"; }
    if ($hasQrCode)   { $params[] = "%$search_term%"; }
    
    if (!empty($category) && $hasCategory) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY item_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'items' => $items,
        'search_term' => $search_term,
        'category' => $category,
        'total_items' => count($items)
    ]);
}
?>