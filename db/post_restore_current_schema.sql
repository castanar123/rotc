-- Post-restore schema updates for deployments restored from older ROTC backups.
-- Run this after importing a historical dump and before pointing the app at it.

CREATE TABLE IF NOT EXISTS academic_terms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_year VARCHAR(20) NOT NULL,
  semester VARCHAR(10) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_term (school_year, semester),
  KEY idx_is_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cadet_enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cadet_profile_id INT NOT NULL,
  school_year VARCHAR(20) NOT NULL,
  semester VARCHAR(10) NOT NULL,
  enrollment_status ENUM('enrolled','pending_verification','dropped') NOT NULL DEFAULT 'pending_verification',
  enrolled_at DATETIME NULL,
  verified_at DATETIME NULL,
  dropped_at DATETIME NULL,
  source VARCHAR(30) NULL,
  last_reviewed_by INT NULL,
  last_reviewed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cadet_term (cadet_profile_id, school_year, semester),
  KEY idx_term_status (school_year, semester, enrollment_status),
  KEY idx_cadet (cadet_profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_security (
  user_id INT PRIMARY KEY,
  pin_hash VARCHAR(255) NULL,
  pin_last_changed_at DATETIME NULL,
  must_reset_password TINYINT(1) NOT NULL DEFAULT 0,
  must_set_pin TINYINT(1) NOT NULL DEFAULT 0,
  failed_pin_attempts INT NOT NULL DEFAULT 0,
  pin_locked_until DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @current_year := YEAR(CURDATE());
SET @current_school_year := CONCAT(@current_year, '-', @current_year + 1);
SET @current_semester := '1st';

INSERT INTO academic_terms (school_year, semester, is_current)
SELECT @current_school_year, @current_semester, 1
WHERE NOT EXISTS (SELECT 1 FROM academic_terms WHERE is_current = 1);

INSERT IGNORE INTO cadet_enrollments (
  cadet_profile_id,
  school_year,
  semester,
  enrollment_status,
  enrolled_at,
  verified_at,
  source,
  last_reviewed_at
)
SELECT
  cp.id,
  at.school_year,
  at.semester,
  'enrolled',
  NOW(),
  NOW(),
  'post_restore',
  NOW()
FROM cadet_profiles cp
JOIN users u ON cp.user_id = u.id
JOIN academic_terms at ON at.is_current = 1
WHERE u.approval_status = 'approved'
  AND u.status = 'active'
  AND u.role IN ('basic-cadet', 'basic_cadet', 'cadet', '1cl', '2cl')
  AND COALESCE(cp.status, 'Active') IN ('Active', 'active');
