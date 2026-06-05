<?php
// Test file to check dashboard access without session requirements
require_once '../includes/db.php';

// Skip session check for testing
echo "<h1>Dashboard Access Test</h1>";
echo "<p>Database connection: " . (isset($pdo) ? "✅ Connected" : "❌ Failed") . "</p>";

// Test if we can query the database
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
    $pending_registrations = $stmt->fetch()['total'];
    echo "<p>Pending registrations query: ✅ Success (Found: $pending_registrations)</p>";
} catch (Exception $e) {
    echo "<p>Pending registrations query: ❌ Error - " . $e->getMessage() . "</p>";
}

echo "<p><a href='dashboard.php'>Try Dashboard.php</a> | <a href='dashboard.html'>Try Dashboard.html</a></p>";
?>