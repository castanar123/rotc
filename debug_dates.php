<?php
require_once 'includes/db.php';

// Debug script to check date_of_birth values
try {
    $sql = "SELECT 
                cp.last_name,
                cp.first_name,
                cp.date_of_birth,
                cp.year_level
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.status = 'active'
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Debug: Date of Birth Values</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Name</th><th>Raw DOB</th><th>DOB Type</th><th>DOB Length</th><th>Formatted DOB</th><th>strtotime Result</th></tr>";
    
    foreach ($cadets as $cadet) {
        $rawDob = $cadet['date_of_birth'];
        $dobType = gettype($rawDob);
        $dobLength = strlen($rawDob ?? '');
        $strtotimeResult = strtotime($rawDob);
        $formattedDob = $strtotimeResult ? date('d-M-y', $strtotimeResult) : 'FAILED';
        
        echo "<tr>";
        echo "<td>{$cadet['first_name']} {$cadet['last_name']}</td>";
        echo "<td>" . htmlspecialchars($rawDob ?? 'NULL') . "</td>";
        echo "<td>{$dobType}</td>";
        echo "<td>{$dobLength}</td>";
        echo "<td>{$formattedDob}</td>";
        echo "<td>" . ($strtotimeResult ? $strtotimeResult : 'FALSE') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>