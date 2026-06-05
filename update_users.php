<?php
// Script to update user roles and passwords
// Database configuration
$host = 'localhost:3306';
$username = 'root';
$password = 'root';
$database = 'rotc_db';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

try {
    // Generate proper password hash for 'admin123'
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Update all users with correct roles and password
    $updates = [
        1 => 'admin',
        2 => 'instructor', 
        3 => 'instructor',
        4 => '1cl',
        5 => '2cl', 
        6 => 'cadet',
        7 => 'cadet',
        8 => 'cadet',
        9 => 'cadet',
        10 => 'cadet',
        11 => 'basic_cadet',
        12 => 'basic_cadet', 
        13 => 'cadet',
        14 => 'cadet',
        15 => 'cadet',
        16 => 'commandant',
        17 => 'cadet',
        18 => 'cadet',
        19 => 'instructor',
        20 => 'basic_cadet'
    ];
    
    foreach ($updates as $userId => $role) {
        $stmt = $pdo->prepare("UPDATE users SET role = ?, password = ? WHERE id = ?");
        $stmt->execute([$role, $hashedPassword, $userId]);
        echo "Updated user ID $userId to role '$role'\n";
    }
    
    // Verify the updates
    echo "\n=== Updated Users ===";
    $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Username: {$row['username']}, Role: {$row['role']}\n";
    }
    
    echo "\n=== Role Distribution ===";
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role ORDER BY role");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Role: {$row['role']}, Count: {$row['count']}\n";
    }
    
    echo "\nAll users updated successfully! All passwords are now 'admin123'\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>