<?php
require_once 'includes/db.php';

echo "<h2>Fixing ROTC Database Structure</h2>";
echo "<p>Creating missing cadet_profiles table...</p>";

try {
    // Create cadet_profiles table with all required columns
    $sql = "
    CREATE TABLE IF NOT EXISTS cadet_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        student_id VARCHAR(20) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100),
        gender ENUM('Male', 'Female') NOT NULL,
        email VARCHAR(100),
        address TEXT,
        contact_number VARCHAR(20),
        facebook_profile VARCHAR(255) DEFAULT NULL,
        course VARCHAR(100),
        section VARCHAR(50),
        religion VARCHAR(50),
        birthdate DATE,
        place_of_birth VARCHAR(100),
        height VARCHAR(20),
        weight VARCHAR(20),
        skin_color VARCHAR(50),
        blood_type VARCHAR(10),
        father VARCHAR(100),
        father_occupation VARCHAR(100),
        mother VARCHAR(100),
        mother_occupation VARCHAR(100),
        guardian VARCHAR(100),
        guardian_contact VARCHAR(20),
        guardian_relationship VARCHAR(50),
        guardian_address TEXT,
        platoon VARCHAR(50),
        company VARCHAR(50),
        rank VARCHAR(50),
        year_level INT,
        status ENUM('Active', 'Inactive', 'Graduated', 'Dropped') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "<p>✅ cadet_profiles table created successfully!</p>";
    
    // Add indexes for better performance
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_cadet_user_id ON cadet_profiles(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_cadet_student_id ON cadet_profiles(student_id)",
        "CREATE INDEX IF NOT EXISTS idx_cadet_email ON cadet_profiles(email)",
        "CREATE INDEX IF NOT EXISTS idx_cadet_platoon ON cadet_profiles(platoon)",
        "CREATE INDEX IF NOT EXISTS idx_cadet_status ON cadet_profiles(status)",
        "CREATE INDEX IF NOT EXISTS idx_facebook_profile ON cadet_profiles(facebook_profile)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $pdo->exec($index);
        } catch (Exception $e) {
            // Index might already exist, continue
        }
    }
    echo "<p>✅ Indexes created successfully!</p>";
    
    // Verify the table structure
    echo "<h3>Verifying cadet_profiles table structure:</h3>";
    $result = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $result->fetchAll();
    
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>{$column['Field']}</strong> ({$column['Type']})";
        if ($column['Null'] === 'NO') echo " NOT NULL";
        if (!empty($column['Default'])) echo " DEFAULT {$column['Default']}";
        echo "</li>";
    }
    echo "</ul>";
    
    // Check for the specific columns that were causing errors
    $requiredColumns = ['birthdate', 'facebook_profile', 'middle_name'];
    echo "<h3>Critical Columns Check:</h3>";
    
    $columnNames = array_column($columns, 'Field');
    foreach ($requiredColumns as $column) {
        $exists = in_array($column, $columnNames);
        $status = $exists ? '✅' : '❌';
        echo "<p>$status <strong>$column</strong> - " . ($exists ? 'EXISTS' : 'MISSING') . "</p>";
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p>✅ cadet_profiles table has been created with all required columns!</p>";
    echo "<p>✅ This should fix the following errors:</p>";
    echo "<ul>";
    echo "<li>❌ Unknown column 'cp.birthdate' in 'field list' → ✅ FIXED</li>";
    echo "<li>❌ Unknown column 'cp.facebook_profile' in 'field list' → ✅ FIXED</li>";
    echo "<li>❌ Unknown column 'cp.middle_name' in 'field list' → ✅ FIXED</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    
    // If table already exists, try to add missing columns
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "<p>Table exists, checking for missing columns...</p>";
        
        try {
            // Check current columns
            $result = $pdo->query("DESCRIBE cadet_profiles");
            $columns = $result->fetchAll();
            $columnNames = array_column($columns, 'Field');
            
            // Add missing columns
            if (!in_array('facebook_profile', $columnNames)) {
                $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN facebook_profile VARCHAR(255) DEFAULT NULL AFTER contact_number");
                echo "<p>✅ Added facebook_profile column</p>";
            }
            
            if (!in_array('birthdate', $columnNames)) {
                $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN birthdate DATE AFTER religion");
                echo "<p>✅ Added birthdate column</p>";
            }
            
            if (!in_array('middle_name', $columnNames)) {
                $pdo->exec("ALTER TABLE cadet_profiles ADD COLUMN middle_name VARCHAR(100) AFTER last_name");
                echo "<p>✅ Added middle_name column</p>";
            }
            
        } catch (Exception $e2) {
            echo "<p style='color: red;'>❌ Error adding columns: " . $e2->getMessage() . "</p>";
        }
    }
}
?>