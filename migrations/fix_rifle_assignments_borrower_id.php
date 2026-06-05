<?php
// Migration: Ensure rifle_assignments has borrower_id column compatible with current code
// Safe to run multiple times; only applies changes if needed.

require_once __DIR__ . '/../includes/db.php';

if (!defined('DB_TYPE') || DB_TYPE !== 'mysql') {
    echo "This migration targets MySQL only. Current DB_TYPE: " . (defined('DB_TYPE') ? DB_TYPE : 'unknown') . "\n";
    exit(0);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function columnExists(mysqli $link, string $table, string $column): bool {
    // SHOW statements don't support placeholders in MariaDB/MySQL; escape and interpolate safely
    $tableEsc = str_replace('`', '``', $table);
    $like = $link->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$like}'";
    $res = $link->query($sql);
    return $res && $res->num_rows > 0;
}

function tableExists(mysqli $link, string $table): bool {
    // SHOW statements don't support placeholders in MariaDB/MySQL; escape and interpolate safely
    $like = $link->real_escape_string($table);
    $sql = "SHOW TABLES LIKE '{$like}'";
    $res = $link->query($sql);
    return $res && $res->num_rows > 0;
}

try {
    echo "=== rifle_assignments: ensuring borrower_id column ===\n";

    if (!tableExists($link, 'rifle_assignments')) {
        throw new Exception("Table rifle_assignments does not exist. Please create it first.");
    }

    $link->begin_transaction();

    // 1) Add borrower_id if missing
    if (!columnExists($link, 'rifle_assignments', 'borrower_id')) {
        echo "Adding borrower_id INT NULL after rifle_id...\n";
        $link->query("ALTER TABLE rifle_assignments ADD COLUMN borrower_id INT NULL AFTER rifle_id");
    } else {
        echo "borrower_id column already exists.\n";
    }

    // 2) Ensure status column values match the code expectations ('active','returned')
    // Only adjust if status column exists and not already correct
    if (columnExists($link, 'rifle_assignments', 'status')) {
        // Attempt to ensure enum has at least active/returned
        try {
            $link->query("ALTER TABLE rifle_assignments MODIFY COLUMN status ENUM('active','returned','overdue') DEFAULT 'active'");
            echo "Ensured status enum includes active/returned/overdue.\n";
        } catch (Throwable $t) {
            echo "Skipped modifying status enum: " . $t->getMessage() . "\n";
        }
    }

    // 3) Create borrowers table if missing (minimal columns used by code)
    if (!tableExists($link, 'borrowers')) {
        echo "Creating borrowers table (minimal schema)...\n";
        $link->query("CREATE TABLE borrowers (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            name VARCHAR(255) NOT NULL,\n            course VARCHAR(100) NULL,\n            contact VARCHAR(100) NULL,\n            temp_id VARCHAR(100) UNIQUE NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        echo "borrowers table exists.\n";
    }

    // 4) Add FK and indexes for borrower_id if not present
    // MySQL before 8.0 has limited easy detection of FKs; we try-create safely.
    // Create index first (if not exists)
    try {
        $link->query("CREATE INDEX idx_rifle_assignments_borrower ON rifle_assignments (borrower_id)");
        echo "Created index idx_rifle_assignments_borrower.\n";
    } catch (Throwable $t) {
        echo "Index idx_rifle_assignments_borrower may already exist: " . $t->getMessage() . "\n";
    }

    // Try to add foreign key (ignore if already exists)
    try {
        // Need a unique name; use conditional naming based on existing ones is complex, we attempt once.
        $link->query("ALTER TABLE rifle_assignments\n            ADD CONSTRAINT fk_rifle_assignments_borrower\n            FOREIGN KEY (borrower_id) REFERENCES borrowers(id)\n            ON DELETE SET NULL ON UPDATE CASCADE");
        echo "Added foreign key fk_rifle_assignments_borrower.\n";
    } catch (Throwable $t) {
        echo "Foreign key may already exist or cannot be added now: " . $t->getMessage() . "\n";
    }

    $link->commit();
    echo "✓ Migration completed.\n";
    echo "You can now retry rifle_management.php actions.\n";

} catch (Throwable $e) {
    if ($link && $link->errno === 0) {
        // Ignore
    }
    try { $link->rollback(); } catch (Throwable $t) {}
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
