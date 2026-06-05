<?php
require_once 'includes/db.php';

echo "<h1>Attendance Module Setup</h1>";

$sql = "
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cadet_profile_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `status` enum('present','absent','late','excused') NOT NULL,
  `logged_by_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cadet_profile_id` (`cadet_profile_id`),
  KEY `logged_by_user_id` (`logged_by_user_id`),
  CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`cadet_profile_id`) REFERENCES `cadet_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`logged_by_user_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($link->query($sql) === TRUE) {
  echo "<p style='color:green;'>'attendance_logs' table created successfully or already exists.</p>";
  echo "<p>You can now delete this file (setup_attendance.php).</p>";
} else {
  echo "<p style='color:red;'>Error creating table: " . $link->error . "</p>";
}

$link->close();
?>
