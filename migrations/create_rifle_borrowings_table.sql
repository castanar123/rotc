-- Create rifle_borrowings table for rifle borrowing system
-- This table stores information about rifle borrowing transactions

CREATE TABLE IF NOT EXISTS `rifle_borrowings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `borrower_name` varchar(255) NOT NULL,
  `rifle_ids` text NOT NULL COMMENT 'JSON array of rifle IDs being borrowed',
  `borrowed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `returned_at` datetime DEFAULT NULL,
  `status` enum('borrowed','returned') NOT NULL DEFAULT 'borrowed',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_borrower_name` (`borrower_name`),
  INDEX `idx_status` (`status`),
  INDEX `idx_borrowed_at` (`borrowed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores rifle borrowing transactions';