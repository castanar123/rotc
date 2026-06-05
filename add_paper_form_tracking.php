<?php
require_once 'includes/db.php';

echo "<h1>Adding Paper Form Tracking Fields</h1>";
echo "<p>Adding fields to track paper form submission status...</p>";

try {
    // Add paper_form_submitted field to users table
    echo "<h2>1. Adding paper_form_submitted field...</h2>";
    $sql = "ALTER TABLE `users` 
            ADD COLUMN IF NOT EXISTS `paper_form_submitted` TINYINT(1) DEFAULT 0 
            COMMENT 'Tracks if cadet has submitted physical paper form (0=not submitted, 1=submitted)'";
    $pdo->exec($sql);
    echo "<p>✅ paper_form_submitted field added successfully</p>";

    // Add paper_form_submitted_date field
    echo "<h2>2. Adding paper_form_submitted_date field...</h2>";
    $sql = "ALTER TABLE `users` 
            ADD COLUMN IF NOT EXISTS `paper_form_submitted_date` DATETIME DEFAULT NULL 
            COMMENT 'Date when physical paper form was submitted'";
    $pdo->exec($sql);
    echo "<p>✅ paper_form_submitted_date field added successfully</p>";

    // Add paper_form_notes field
    echo "<h2>3. Adding paper_form_notes field...</h2>";
    $sql = "ALTER TABLE `users` 
            ADD COLUMN IF NOT EXISTS `paper_form_notes` TEXT DEFAULT NULL 
            COMMENT 'Additional notes about paper form submission status'";
    $pdo->exec($sql);
    echo "<p>✅ paper_form_notes field added successfully</p>";

    // Create index for better performance
    echo "<h2>4. Creating index for paper form queries...</h2>";
    $sql = "CREATE INDEX IF NOT EXISTS `idx_users_paper_form_status` ON `users` (`paper_form_submitted`, `approval_status`)";
    $pdo->exec($sql);
    echo "<p>✅ Index created successfully</p>";

    // Create view for pending paper form submissions
    echo "<h2>5. Creating pending paper forms view...</h2>";
    $sql = "CREATE OR REPLACE VIEW `pending_paper_forms_view` AS
            SELECT 
                u.id,
                u.username,
                u.email,
                u.full_name,
                u.first_name,
                u.last_name,
                u.student_id,
                u.course,
                u.year_level,
                u.contact_number,
                u.approval_status,
                u.created_at as registration_date,
                u.paper_form_submitted,
                u.paper_form_submitted_date,
                u.paper_form_notes,
                cp.platoon,
                cp.section,
                DATEDIFF(NOW(), u.created_at) as days_since_registration
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
            WHERE u.approval_status = 'pending' 
            AND u.paper_form_submitted = 0
            AND u.role IN ('basic_cadet', 'cadet', 'basic-cadet')
            ORDER BY u.created_at ASC";
    $pdo->exec($sql);
    echo "<p>✅ pending_paper_forms_view created successfully</p>";

    // Show updated table structure
    echo "<h2>6. Updated Users Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test the new view
    echo "<h2>7. Testing Pending Paper Forms View:</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pending_paper_forms_view");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Current pending paper forms count: <strong>" . $result['count'] . "</strong></p>";

    // Show sample data if any exists
    $stmt = $pdo->query("SELECT * FROM pending_paper_forms_view LIMIT 5");
    $pending_forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pending_forms)) {
        echo "<h3>Sample Pending Paper Forms:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Name</th><th>Student ID</th><th>Course</th><th>Registration Date</th><th>Days Since Registration</th></tr>";
        foreach ($pending_forms as $form) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($form['full_name'] ?: $form['first_name'] . ' ' . $form['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($form['student_id']) . "</td>";
            echo "<td>" . htmlspecialchars($form['course']) . "</td>";
            echo "<td>" . htmlspecialchars($form['registration_date']) . "</td>";
            echo "<td>" . htmlspecialchars($form['days_since_registration']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No pending paper forms found in the system.</p>";
    }

    echo "<h2>✅ Paper Form Tracking Setup Complete!</h2>";
    echo "<p>The system can now track:</p>";
    echo "<ul>";
    echo "<li>Whether a cadet has submitted their paper form</li>";
    echo "<li>When the paper form was submitted</li>";
    echo "<li>Additional notes about the submission</li>";
    echo "<li>Generate reports of cadets who need to submit paper forms</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<h2>❌ Error occurred:</h2>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>