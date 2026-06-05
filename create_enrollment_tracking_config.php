<?php
require_once 'includes/db.php';

// Use the global $link variable from db.php
global $link;

if (!$link) {
    die("❌ Database connection failed. Please check your database configuration.\n");
}

try {
    // Create enrollment_tracking_config table
    $sql = "CREATE TABLE IF NOT EXISTS enrollment_tracking_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_name VARCHAR(100) NOT NULL UNIQUE,
        setting_value VARCHAR(255) NOT NULL,
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100)
    )";
    
    if ($link->query($sql) === TRUE) {
        echo "✅ enrollment_tracking_config table created successfully\n";
    } else {
        echo "❌ Error creating table: " . $link->error . "\n";
    }
    
    // Insert default configuration values
    $default_configs = [
        [
            'setting_name' => 'online_enrollment_enabled',
            'setting_value' => 'true',
            'description' => 'Enable or disable online enrollment tracking',
            'updated_by' => 'system'
        ],
        [
            'setting_name' => 'enrollment_start_date',
            'setting_value' => date('Y-m-d'),
            'description' => 'Date when enrollment tracking started',
            'updated_by' => 'system'
        ],
        [
            'setting_name' => 'enrollment_end_date',
            'setting_value' => '',
            'description' => 'Date when enrollment tracking ended (empty if ongoing)',
            'updated_by' => 'system'
        ],
        [
            'setting_name' => 'max_enrollees',
            'setting_value' => '1000',
            'description' => 'Maximum number of enrollees allowed',
            'updated_by' => 'system'
        ]
    ];
    
    foreach ($default_configs as $config) {
        $stmt = $link->prepare("INSERT IGNORE INTO enrollment_tracking_config (setting_name, setting_value, description, updated_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $config['setting_name'], $config['setting_value'], $config['description'], $config['updated_by']);
        
        if ($stmt->execute()) {
            echo "✅ Configuration '{$config['setting_name']}' added successfully\n";
        } else {
            echo "⚠️  Configuration '{$config['setting_name']}' already exists or error: " . $stmt->error . "\n";
        }
        $stmt->close();
    }
    
    // Create enrollment_statistics table for tracking stats
    $stats_sql = "CREATE TABLE IF NOT EXISTS enrollment_statistics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date_recorded DATE NOT NULL,
        total_enrollees INT DEFAULT 0,
        pending_approvals INT DEFAULT 0,
        approved_enrollees INT DEFAULT 0,
        rejected_enrollees INT DEFAULT 0,
        paper_forms_submitted INT DEFAULT 0,
        paper_forms_pending INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_date (date_recorded)
    )";
    
    if ($link->query($stats_sql) === TRUE) {
        echo "✅ enrollment_statistics table created successfully\n";
    } else {
        echo "❌ Error creating statistics table: " . $link->error . "\n";
    }
    
    // Insert today's statistics
    $today = date('Y-m-d');
    $stats_query = "
        INSERT INTO enrollment_statistics (date_recorded, total_enrollees, pending_approvals, approved_enrollees, rejected_enrollees, paper_forms_submitted, paper_forms_pending)
        SELECT 
            '$today' as date_recorded,
            COUNT(*) as total_enrollees,
            SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_approvals,
            SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_enrollees,
            SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_enrollees,
            SUM(CASE WHEN paper_form_submitted = 1 THEN 1 ELSE 0 END) as paper_forms_submitted,
            SUM(CASE WHEN paper_form_submitted = 0 OR paper_form_submitted IS NULL THEN 1 ELSE 0 END) as paper_forms_pending
        FROM users 
        WHERE role = 'cadet'
        ON DUPLICATE KEY UPDATE
            total_enrollees = VALUES(total_enrollees),
            pending_approvals = VALUES(pending_approvals),
            approved_enrollees = VALUES(approved_enrollees),
            rejected_enrollees = VALUES(rejected_enrollees),
            paper_forms_submitted = VALUES(paper_forms_submitted),
            paper_forms_pending = VALUES(paper_forms_pending)
    ";
    
    if ($link->query($stats_query) === TRUE) {
        echo "✅ Today's enrollment statistics recorded successfully\n";
    } else {
        echo "❌ Error recording statistics: " . $link->error . "\n";
    }
    
    echo "\n📊 Current enrollment statistics:\n";
    $result = $link->query("SELECT * FROM enrollment_statistics WHERE date_recorded = '$today'");
    if ($result && $result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        echo "Total Enrollees: {$stats['total_enrollees']}\n";
        echo "Pending Approvals: {$stats['pending_approvals']}\n";
        echo "Approved: {$stats['approved_enrollees']}\n";
        echo "Rejected: {$stats['rejected_enrollees']}\n";
        echo "Paper Forms Submitted: {$stats['paper_forms_submitted']}\n";
        echo "Paper Forms Pending: {$stats['paper_forms_pending']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} finally {
    if (isset($link)) {
        $link->close();
    }
}
?>