-- Create student_notification_views table
-- This table tracks which notifications students have viewed

CREATE TABLE `student_notification_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `notification_type` enum('enrollment_requests','enrollment_status','announcements','general') NOT NULL,
  `notification_id` int(11) DEFAULT NULL COMMENT 'Specific notification ID if applicable',
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_notification_id` (`notification_id`),
  KEY `idx_viewed_at` (`viewed_at`),
  CONSTRAINT `fk_student_notification_views_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
