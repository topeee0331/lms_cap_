-- Update questions table structure to ensure compatibility
-- This script will modify the questions table if needed

-- First, check if the table exists and create it if it doesn't
CREATE TABLE IF NOT EXISTS `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` varchar(50) NOT NULL COMMENT 'String ID like assess_68b63fb3c750e',
  `question_text` text NOT NULL,
  `question_type` varchar(32) NOT NULL DEFAULT 'multiple_choice',
  `question_order` int(11) NOT NULL,
  `points` int(11) DEFAULT 1,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of question options',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assessment_id` (`assessment_id`),
  KEY `question_order` (`question_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add any missing columns if they don't exist
ALTER TABLE `questions` 
ADD COLUMN IF NOT EXISTS `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
ADD COLUMN IF NOT EXISTS `assessment_id` varchar(50) NOT NULL COMMENT 'String ID like assess_68b63fb3c750e' AFTER `id`,
ADD COLUMN IF NOT EXISTS `question_text` text NOT NULL AFTER `assessment_id`,
ADD COLUMN IF NOT EXISTS `question_type` varchar(32) NOT NULL DEFAULT 'multiple_choice' AFTER `question_text`,
ADD COLUMN IF NOT EXISTS `question_order` int(11) NOT NULL AFTER `question_type`,
ADD COLUMN IF NOT EXISTS `points` int(11) DEFAULT 1 AFTER `question_order`,
ADD COLUMN IF NOT EXISTS `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of question options' AFTER `points`,
ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `options`,
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() AFTER `created_at`;

-- Ensure the primary key exists
ALTER TABLE `questions` ADD PRIMARY KEY IF NOT EXISTS (`id`);

-- Add indexes if they don't exist
ALTER TABLE `questions` ADD INDEX IF NOT EXISTS `assessment_id` (`assessment_id`);
ALTER TABLE `questions` ADD INDEX IF NOT EXISTS `question_order` (`question_order`);

-- Update the auto_increment if needed
ALTER TABLE `questions` MODIFY COLUMN `id` int(11) NOT NULL AUTO_INCREMENT;

-- Show the final table structure
DESCRIBE `questions`;
