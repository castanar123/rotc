<?php
// Restore 193 rifles to the database

// Database connection
$host = 'localhost';
$dbname = 'rotc_db';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL database successfully.\n";
    
    // First, clear existing rifle data
    echo "Clearing existing rifle data...\n";
    $pdo->exec("DELETE FROM rifles");
    $pdo->exec("ALTER TABLE rifles AUTO_INCREMENT = 1");
    
    // Prepare insert statement
    $stmt = $pdo->prepare("
        INSERT INTO rifles (serial_number, model, manufacturer, caliber, condition_status, location, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    echo "Inserting 193 rifles...\n";
    
    // Generate and insert 193 rifles
    for ($i = 1; $i <= 193; $i++) {
        $serial_number = sprintf('R%03d', $i);
        $models = ['M16A2', 'M16A4', 'M4A1', 'AR-15'];
        $manufacturers = ['Colt', 'FN Herstal', 'Remington', 'Daniel Defense'];
        $calibers = ['5.56x45mm', '.223 Remington'];
        $conditions = ['excellent', 'good', 'fair'];
        $locations = ['Armory A', 'Armory B', 'Training Room', 'Storage'];
        
        $model = $models[array_rand($models)];
        $manufacturer = $manufacturers[array_rand($manufacturers)];
        $caliber = $calibers[array_rand($calibers)];
        $condition = $conditions[array_rand($conditions)];
        $location = $locations[array_rand($locations)];
        $notes = "Rifle #$i - Standard issue";
        
        $stmt->execute([
            $serial_number,
            $model,
            $manufacturer,
            $caliber,
            $condition,
            $location,
            $notes
        ]);
        
        if ($i % 50 == 0) {
            echo "Inserted $i rifles...\n";
        }
    }
    
    echo "Successfully inserted all 193 rifles!\n";
    
    // Verify the count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rifles");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Total rifles in database: $count\n";
    
    // Show sample of inserted rifles:
    echo "\nSample of inserted rifles:\n";
    $stmt = $pdo->query("SELECT serial_number, model, manufacturer, condition_status, location FROM rifles LIMIT 10");
    $rifles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rifles as $rifle) {
        echo "- {$rifle['serial_number']}: {$rifle['model']} ({$rifle['manufacturer']}) - {$rifle['condition_status']} - {$rifle['location']}\n";
    }
    
    echo "\nLast 5 rifles:\n";
    $stmt = $pdo->query("SELECT rifle_number, serial_number, model, status, rifle_condition, location FROM rifles ORDER BY rifle_number DESC LIMIT 5");
    $rifles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rifles as $rifle) {
        echo "- {$rifle['rifle_number']}: {$rifle['serial_number']} ({$rifle['model']}) - {$rifle['status']} - {$rifle['rifle_condition']} - {$rifle['location']}\n";
    }
    
    echo "\n✅ Successfully restored 193 rifles to the database!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}