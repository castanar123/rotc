<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Adding sample inventory data...\n";
    
    // Sample inventory items
    $inventory_items = [
        [
            'item_code' => 'UNI001',
            'item_name' => 'ROTC Uniform Set',
            'description' => 'Complete ROTC uniform with cap and accessories',
            'category' => 'Uniform',
            'total_quantity' => 50,
            'available_quantity' => 45,
            'borrowed_quantity' => 5,
            'unit' => 'set',
            'location' => 'Storage Room A',
            'condition_status' => 'good'
        ],
        [
            'item_code' => 'BOOT001',
            'item_name' => 'Combat Boots',
            'description' => 'Black leather combat boots for ROTC training',
            'category' => 'Footwear',
            'total_quantity' => 30,
            'available_quantity' => 25,
            'borrowed_quantity' => 5,
            'unit' => 'pair',
            'location' => 'Storage Room B',
            'condition_status' => 'excellent'
        ],
        [
            'item_code' => 'RIFLE001',
            'item_name' => 'Training Rifle M16A1',
            'description' => 'Deactivated M16A1 rifle for drill and ceremony',
            'category' => 'Equipment',
            'total_quantity' => 20,
            'available_quantity' => 18,
            'borrowed_quantity' => 2,
            'unit' => 'piece',
            'location' => 'Armory',
            'condition_status' => 'good'
        ],
        [
            'item_code' => 'BELT001',
            'item_name' => 'Military Belt',
            'description' => 'Black leather military belt with brass buckle',
            'category' => 'Accessories',
            'total_quantity' => 40,
            'available_quantity' => 35,
            'borrowed_quantity' => 5,
            'unit' => 'piece',
            'location' => 'Storage Room A',
            'condition_status' => 'good'
        ],
        [
            'item_code' => 'CAP001',
            'item_name' => 'ROTC Cap',
            'description' => 'Official ROTC military cap with insignia',
            'category' => 'Headwear',
            'total_quantity' => 60,
            'available_quantity' => 50,
            'borrowed_quantity' => 10,
            'unit' => 'piece',
            'location' => 'Storage Room A',
            'condition_status' => 'excellent'
        ]
    ];
    
    $insert_stmt = $pdo->prepare("
        INSERT IGNORE INTO inventory 
        (item_code, item_name, description, category, total_quantity, available_quantity, borrowed_quantity, unit, location, condition_status, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $inserted_count = 0;
    foreach ($inventory_items as $item) {
        $result = $insert_stmt->execute([
            $item['item_code'],
            $item['item_name'],
            $item['description'],
            $item['category'],
            $item['total_quantity'],
            $item['available_quantity'],
            $item['borrowed_quantity'],
            $item['unit'],
            $item['location'],
            $item['condition_status']
        ]);
        
        if ($insert_stmt->rowCount() > 0) {
            $inserted_count++;
            echo "✓ Added: {$item['item_name']} ({$item['item_code']})\n";
        }
    }
    
    echo "\n✓ Sample inventory data added successfully\n";
    echo "✓ Total items inserted: $inserted_count\n";
    
    // Verify the data
    $count_stmt = $pdo->query('SELECT COUNT(*) FROM inventory');
    $total_count = $count_stmt->fetchColumn();
    echo "✓ Total inventory items in database: $total_count\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>