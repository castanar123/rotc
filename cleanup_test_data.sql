-- Clean up all test data to prevent duplicate key errors
-- This script removes any leftover test data from previous test runs

USE rotc_db;

-- Clean up test users and related data
DELETE FROM attendance WHERE user_id IN (
    SELECT id FROM users WHERE email LIKE '%@example.com'
);

DELETE FROM cadet_profiles WHERE user_id IN (
    SELECT id FROM users WHERE email LIKE '%@example.com'
);

DELETE FROM rifle_assignments WHERE user_id IN (
    SELECT id FROM users WHERE email LIKE '%@example.com'
);

DELETE FROM security_logs WHERE user_id IN (
    SELECT id FROM users WHERE email LIKE '%@example.com'
);

DELETE FROM users WHERE email LIKE '%@example.com';

-- Clean up test rifles
DELETE FROM rifle_assignments WHERE rifle_id IN (
    SELECT id FROM rifles WHERE serial_number LIKE 'TEST-%'
);

DELETE FROM rifles WHERE serial_number LIKE 'TEST-%';

-- Clean up any test security logs
DELETE FROM security_logs WHERE action = 'test_action';

SELECT 'Test data cleanup completed' as status;
SELECT COUNT(*) as remaining_test_users FROM users WHERE email LIKE '%@example.com';
SELECT COUNT(*) as remaining_test_rifles FROM rifles WHERE serial_number LIKE 'TEST-%';