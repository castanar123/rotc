<?php
// Test to see what value $basic_cadets actually has in admin_dashboard.php
require_once 'includes/db.php';

echo "<h2>Testing Admin Dashboard Variable Values</h2>";
echo "<hr>";

try {
    // Copy the exact queries from admin_dashboard.php
    
    // Basic cadets query (exact copy from admin_dashboard.php)
    $basic_cadets_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM users u 
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
        WHERE u.role = 'basic_cadet' AND u.status = 'active'
    ");
    $basic_cadets_stmt->execute();
    $basic_cadets = $basic_cadets_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<h3>Basic Cadets Count Test</h3>";
    echo "<p><strong>Query Result:</strong> $basic_cadets</p>";
    echo "<p><strong>Variable Type:</strong> " . gettype($basic_cadets) . "</p>";
    echo "<p><strong>Is Zero:</strong> " . ($basic_cadets == 0 ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>Is Empty:</strong> " . (empty($basic_cadets) ? 'YES' : 'NO') . "</p>";
    
    // Test other counts too
    $cl2_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = '2cl' AND status = 'active'");
    $cl2_stmt->execute();
    $cl2_count = $cl2_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $officers_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role IN ('1cl', 'officer') AND status = 'active'");
    $officers_stmt->execute();
    $officers_count = $officers_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<h3>Other Counts</h3>";
    echo "<p><strong>2CL Cadets:</strong> $cl2_count</p>";
    echo "<p><strong>Officers:</strong> $officers_count</p>";
    
    // Test HTML output
    echo "<h3>HTML Output Test</h3>";
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<div class='stat-value'>" . $basic_cadets . "</div>";
    echo "</div>";
    echo "<p>Above should show the basic cadets count in a styled div.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><em>Test completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>