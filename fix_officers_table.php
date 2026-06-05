<?php
// Fix officers table structure for inventory dashboard

// Include the database connection
require_once 'rotc-qr-inventory/includes/db.php';

try {
    echo "Checking database connection...\n";
    
    // Check if officers table exists
    $check_table = $pdo->query("SHOW TABLES LIKE 'officers'");
    $table_exists = $check_table->rowCount() > 0;
    
    if ($table_exists) {
        echo "Officers table exists. Checking structure...\n";
        
        // Check current structure
        $structure = $pdo->query("DESCRIBE officers");
        $columns = $structure->fetchAll();
        
        echo "Current officers table structure:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']}: {$column['Type']}\n";
        }
        
        // Check if user_id column exists
        $has_user_id = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'user_id') {
                $has_user_id = true;
                break;
            }
        }
        
        if (!$has_user_id) {
            echo "Adding user_id column to officers table...\n";
            $pdo->exec("ALTER TABLE officers ADD COLUMN user_id INT NULL AFTER id");
            echo "user_id column added successfully.\n";
        } else {
            echo "user_id column already exists.\n";
        }
        
    } else {
        echo "Officers table does not exist. Creating it...\n";
        
        // Create officers table with proper structure
        $create_sql = "
        CREATE TABLE officers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(255) NOT NULL,
            rank_position VARCHAR(100),
            rank VARCHAR(50),
            position VARCHAR(100),
            platoon VARCHAR(50),
            contact VARCHAR(100),
            email VARCHAR(100),
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($create_sql);
        echo "Officers table created successfully.\n";
    }
    
    // Insert some sample officers if table is empty
    $count_officers = $pdo->query("SELECT COUNT(*) as count FROM officers")->fetch();
    
    if ($count_officers['count'] == 0) {
        echo "Inserting sample officers...\n";
        
        $sample_officers = [
            ['name' => 'Officer John Doe', 'rank_position' => 'Captain', 'rank' => 'Captain', 'position' => 'Commanding Officer', 'platoon' => 'Alpha', 'contact' => '09123456789', 'email' => 'john.doe@rotc.edu'],
            ['name' => 'Officer Jane Smith', 'rank_position' => 'Lieutenant', 'rank' => 'Lieutenant', 'position' => 'Executive Officer', 'platoon' => 'Bravo', 'contact' => '09987654321', 'email' => 'jane.smith@rotc.edu'],
            ['name' => 'Officer Mike Johnson', 'rank_position' => 'Sergeant', 'rank' => 'Sergeant', 'position' => 'Supply Officer', 'platoon' => 'Charlie', 'contact' => '09555666777', 'email' => 'mike.johnson@rotc.edu']
        ];
        
        $insert_stmt = $pdo->prepare("
            INSERT INTO officers (name, rank_position, rank, position, platoon, contact, email, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        foreach ($sample_officers as $officer) {
            $insert_stmt->execute([
                $officer['name'],
                $officer['rank_position'],
                $officer['rank'],
                $officer['position'],
                $officer['platoon'],
                $officer['contact'],
                $officer['email']
            ]);
        }
        
        echo "Sample officers inserted successfully.\n";
    }
    
    // Test the query that was failing
    echo "Testing the problematic query...\n";
    $test_query = "SELECT o.*, u.username, u.email FROM officers o LEFT JOIN users u ON o.user_id = u.id WHERE o.status = 'active' ORDER BY o.rank_position, o.id";
    $test_result = $pdo->query($test_query);
    $officers = $test_result->fetchAll();
    
    echo "Query executed successfully. Found " . count($officers) . " officers.\n";
    
    foreach ($officers as $officer) {
        echo "- {$officer['name']} ({$officer['rank_position']})\n";
    }
    
    echo "\nDatabase fix completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>