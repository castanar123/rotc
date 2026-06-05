-- Create quiz_scores table for individual quiz records
USE rotc_db;

CREATE TABLE IF NOT EXISTS `quiz_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_id` int(11) NOT NULL,
  `quiz_name` varchar(255) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `percentage` decimal(5,2) GENERATED ALWAYS AS ((score / max_score) * 100) STORED,
  `semester` varchar(50) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cadet_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for better performance
CREATE INDEX idx_cadet_semester ON quiz_scores(cadet_id, semester, academic_year);
CREATE INDEX idx_quiz_name ON quiz_scores(quiz_name);