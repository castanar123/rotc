<?php
// Check profile data structure for basic-cadet users
require_once 'includes/db.php';

global $link;

echo "<h2>🔍 Checking Profile Data Structure</h2>\n";

// Check users table structure
echo "<h3>1. Users Table Structure</h3>\n";
$users_structure = $link->query("DESCRIBE users");
if ($users_structure) {
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    while ($field = $users_structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$field['Field']}</td>";
        echo "<td>{$field['Type']}</td>";
        echo "<td>{$field['Null']}</td>";
        echo "<td>{$field['Key']}</td>";
        echo "<td>{$field['Default']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// Check cadet_profiles table structure
echo "<h3>2. Cadet Profiles Table Structure</h3>\n";
$profiles_structure = $link->query("DESCRIBE cadet_profiles");
if ($profiles_structure) {
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
    while ($field = $profiles_structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$field['Field']}</td>";
        echo "<td>{$field['Type']}</td>";
        echo "<td>{$field['Null']}</td>";
        echo "<td>{$field['Key']}</td>";
        echo "<td>{$field['Default']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// Check sample data from basic-cadet users
echo "<h3>3. Sample Basic-Cadet Users Data</h3>\n";
$sample_query = "
    SELECT 
        u.id,
        u.username,
        u.email,
        u.role,
        u.first_name,
        u.last_name,
        u.full_name,
        cp.full_name as profile_full_name,
        cp.student_number
    FROM users u
    LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
    WHERE u.role = 'basic-cadet' AND u.approval_status = 'pending'
    LIMIT 5
";

$sample_result = $link->query($sample_query);
if ($sample_result && $sample_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>First Name</th><th>Last Name</th><th>Full Name (users)</th><th>Full Name (profile)</th><th>Student #</th></tr>\n";
    
    while ($user = $sample_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>" . ($user['first_name'] ?? 'NULL') . "</td>";
        echo "<td>" . ($user['last_name'] ?? 'NULL') . "</td>";
        echo "<td>" . ($user['full_name'] ?? 'NULL') . "</td>";
        echo "<td>" . ($user['profile_full_name'] ?? 'NULL') . "</td>";
        echo "<td>" . ($user['student_number'] ?? 'NULL') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p>No basic-cadet users found or error in query.</p>\n";
}

$link->close();
?>