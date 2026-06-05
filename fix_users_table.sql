-- Fix users table to match original SQLite structure
-- Original SQLite structure from check_sqlite_tables.php output:
-- id (INTEGER PRIMARY KEY), username (VARCHAR(50) NOT NULL), password (VARCHAR(255) NOT NULL),
-- email (VARCHAR(100)), full_name (VARCHAR(255)), role (VARCHAR(20) DEFAULT 'user'),
-- created_at (DATETIME DEFAULT CURRENT_TIMESTAMP), updated_at (DATETIME DEFAULT CURRENT_TIMESTAMP),
-- is_active (BOOLEAN DEFAULT TRUE), two_factor_enabled (BOOLEAN DEFAULT FALSE), two_factor_secret (VARCHAR(32))

USE rotc_system;

-- Drop existing users table
DROP TABLE IF EXISTS users;

-- Recreate users table with correct structure matching SQLite original
CREATE TABLE users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(255),
    role VARCHAR(20) DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(32),
    PRIMARY KEY (id),
    UNIQUE KEY unique_username (username)
);

-- Insert the original admin user from SQLite database
INSERT INTO users (id, username, password, email, full_name, role, created_at, updated_at, is_active, two_factor_enabled, two_factor_secret) 
VALUES (1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@rotc.mil', 'System Administrator', 'admin', '2025-09-08 02:50:00', '2025-09-08 02:50:00', TRUE, FALSE, NULL);

-- Show the corrected table structure
DESCRIBE users;

-- Show the restored data
SELECT * FROM users;