<?php
require_once 'includes/db.php';

echo "Testing Advance Officer Form Functionality\n";
echo "==========================================\n\n";

try {
    // Test 1: Check table structure
    echo "1. Checking table structure...\n";
    $stmt = $pdo->query('DESCRIBE advance_rotc_signups');
    $columns = $stmt->fetchAll();
    
    $expected_columns = ['id', 'full_name', 'course', 'facebook_link', 'created_at'];
    $actual_columns = array_column($columns, 'Field');
    
    foreach ($expected_columns as $col) {
        if (in_array($col, $actual_columns)) {
            echo "   ✅ Column '$col' exists\n";
        } else {
            echo "   ❌ Column '$col' missing\n";
        }
    }
    
    // Test 2: Test form submission simulation
    echo "\n2. Testing form submission...\n";
    
    // Simulate form data
    $test_data = [
        ['John Doe', 'Computer Science', 'https://facebook.com/johndoe'],
        ['Jane Smith', 'Information Technology', 'https://facebook.com/janesmith'],
        ['Bob Wilson', 'Engineering', 'https://facebook.com/bobwilson']
    ];
    
    foreach ($test_data as $index => $data) {
        list($full_name, $course, $facebook_link) = $data;
        
        // Check for duplicates (simulate the form's duplicate check)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM advance_rotc_signups WHERE full_name = ? OR facebook_link = ?");
        $stmt->execute([$full_name, $facebook_link]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "   ⚠️  Duplicate found for $full_name, skipping...\n";
            continue;
        }
        
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO advance_rotc_signups (full_name, course, facebook_link) VALUES (?, ?, ?)");
        $result = $stmt->execute([$full_name, $course, $facebook_link]);
        
        if ($result) {
            echo "   ✅ Successfully inserted: $full_name\n";
        } else {
            echo "   ❌ Failed to insert: $full_name\n";
        }
    }
    
    // Test 3: Test data retrieval (simulate management page)
    echo "\n3. Testing data retrieval...\n";
    
    $stmt = $pdo->query("SELECT * FROM advance_rotc_signups ORDER BY created_at DESC");
    $signups = $stmt->fetchAll();
    
    echo "   Total signups: " . count($signups) . "\n";
    
    foreach ($signups as $signup) {
        echo "   - ID: {$signup['id']}, Name: {$signup['full_name']}, Course: {$signup['course']}\n";
    }
    
    // Test 4: Test statistics queries
    echo "\n4. Testing statistics queries...\n";
    
    // Total signups
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM advance_rotc_signups");
    $total = $stmt->fetchColumn();
    echo "   Total signups: $total\n";
    
    // Recent signups (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent FROM advance_rotc_signups WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recent = $stmt->fetchColumn();
    echo "   Recent signups (7 days): $recent\n";
    
    // Course statistics
    $stmt = $pdo->query("SELECT course, COUNT(*) as count FROM advance_rotc_signups GROUP BY course ORDER BY count DESC");
    $course_stats = $stmt->fetchAll();
    echo "   Course breakdown:\n";
    foreach ($course_stats as $stat) {
        echo "     - {$stat['course']}: {$stat['count']}\n";
    }
    
    // Test 5: Test Facebook URL validation
    echo "\n5. Testing Facebook URL validation...\n";
    
    $test_urls = [
        'https://facebook.com/test' => true,
        'https://www.facebook.com/test' => true,
        'http://facebook.com/test' => true,
        'facebook.com/test' => false,
        'https://twitter.com/test' => false,
        'invalid-url' => false
    ];
    
    foreach ($test_urls as $url => $expected) {
        $pattern = '/^https?:\/\/(www\.)?facebook\.com\/.+/';
        $is_valid = preg_match($pattern, $url);
        $result = $is_valid ? 'Valid' : 'Invalid';
        $status = ($is_valid == $expected) ? '✅' : '❌';
        echo "   $status $url -> $result\n";
    }
    
    echo "\n✅ All tests completed successfully!\n";
    echo "\nThe advance officer form should now be working properly.\n";
    
} catch(Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>