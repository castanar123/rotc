<?php
require_once 'includes/db.php';

echo "=== CHECKING CADET PROFILES DATA ===\n\n";

try {
    // Check cadet_profiles table structure
    echo "1. CADET PROFILES TABLE STRUCTURE:\n";
    $stmt = $pdo->query("DESCRIBE cadet_profiles");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n2. SAMPLE CADET PROFILES DATA:\n";
    $stmt = $pdo->query("SELECT id, user_id, last_name, first_name, course, year_level, beneficiary_address, region, father_name, mother_name FROM cadet_profiles LIMIT 5");
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($profiles as $profile) {
        echo "Profile ID: {$profile['id']}\n";
        echo "  - User ID: {$profile['user_id']}\n";
        echo "  - Name: {$profile['first_name']} {$profile['last_name']}\n";
        echo "  - Course: {$profile['course']}\n";
        echo "  - Year Level: {$profile['year_level']}\n";
        echo "  - Beneficiary Address: {$profile['beneficiary_address']}\n";
        echo "  - Region: {$profile['region']}\n";
        echo "  - Father: {$profile['father_name']}\n";
        echo "  - Mother: {$profile['mother_name']}\n";
        echo "\n";
    }
    
    echo "3. USERS TABLE DATA:\n";
    $stmt = $pdo->query("SELECT id, username, role, status FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        echo "User ID: {$user['id']}\n";
        echo "  - Username: {$user['username']}\n";
        echo "  - Role: {$user['role']}\n";
        echo "  - Status: {$user['status']}\n";
        echo "\n";
    }
    
    echo "4. JOIN QUERY TEST (users + cadet_profiles):\n";
    $stmt = $pdo->query("SELECT u.id, u.username, u.status, cp.first_name, cp.last_name, cp.course, cp.beneficiary_address, cp.region FROM users u LEFT JOIN cadet_profiles cp ON u.id = cp.user_id WHERE u.status = 'active' LIMIT 3");
    $joined = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($joined as $record) {
        echo "User: {$record['username']} (ID: {$record['id']})\n";
        echo "  - Name: {$record['first_name']} {$record['last_name']}\n";
        echo "  - Course: {$record['course']}\n";
        echo "  - Beneficiary Address: {$record['beneficiary_address']}\n";
        echo "  - Region: {$record['region']}\n";
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "🎯 DATA CHECK COMPLETED\n";
?>