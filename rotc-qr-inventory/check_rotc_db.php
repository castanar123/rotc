<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "All tables in rotc_db database:\n";
    $stmt = $pdo->query('SHOW TABLES');
    while($row = $stmt->fetch()) {
        echo "- " . $row[0] . "\n";
    }
    
    // Check if officers table exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'rotc_db' AND table_name = 'officers'");
    $officers_exists = $stmt->fetchColumn();
    echo "\nOfficers table exists: " . ($officers_exists ? 'YES' : 'NO') . "\n";
    
    // Check if transactions table exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'rotc_db' AND table_name = 'transactions'");
    $transactions_exists = $stmt->fetchColumn();
    echo "Transactions table exists: " . ($transactions_exists ? 'YES' : 'NO') . "\n";
    
    // Check if items table exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'rotc_db' AND table_name = 'items'");
    $items_exists = $stmt->fetchColumn();
    echo "Items table exists: " . ($items_exists ? 'YES' : 'NO') . "\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>