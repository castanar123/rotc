-- Fix rifle status ENUM to include 'assigned' status
-- This resolves the "Data truncated for column 'status'" error

USE rotc_db;

-- Update rifles table status ENUM to include 'assigned'
ALTER TABLE rifles 
MODIFY COLUMN status ENUM('available','assigned','borrowed','maintenance','lost','damaged') 
NOT NULL DEFAULT 'available';

-- Update any existing 'borrowed' status to 'assigned' for consistency
UPDATE rifles SET status = 'assigned' WHERE status = 'borrowed';

SELECT 'Rifle status ENUM updated successfully!' as message;