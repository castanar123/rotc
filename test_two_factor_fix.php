<?php
require_once 'includes/db.php';
require_once 'includes/TwoFactorAuth.php';
require_once 'includes/SecurityLogger.php';

echo "<h2>Testing TwoFactorAuth Fix</h2>";

try {
    // Initialize the TwoFactorAuth class
    $logger = new SecurityLogger($pdo);
    $twoFA = new TwoFactorAuth($pdo, $logger);
    
    // Test with user ID 29 (test_admin)
    $userId = 29;
    
    echo "<h3>Testing getUserSecret method:</h3>";
    $secret = $twoFA->getUserSecret($userId);
    if ($secret) {
        echo "✅ getUserSecret method works - Secret found for user $userId<br>";
    } else {
        echo "ℹ️ getUserSecret method works - No secret found for user $userId (this is normal if 2FA not set up)<br>";
    }
    
    echo "<h3>Testing is2FAEnabled method:</h3>";
    $isEnabled = $twoFA->is2FAEnabled($userId);
    echo "✅ is2FAEnabled method works - 2FA enabled status for user $userId: " . ($isEnabled ? 'Yes' : 'No') . "<br>";
    
    echo "<h3>Result:</h3>";
    echo "✅ All TwoFactorAuth methods are working correctly without 'is_active' column errors!<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>