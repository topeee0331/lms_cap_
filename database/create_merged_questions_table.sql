-- Create merged questions table that combines questions and options
-- This approach uses a single table with JSON for options

-- Drop existing tables if they exist (for clean setup)
DROP TABLE IF EXISTS `question_options`;
DROP TABLE IF EXISTS `questions`;

-- Create the merged questions table
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` varchar(32) NOT NULL DEFAULT 'multiple_choice',
  `question_order` int(11) NOT NULL,
  `points` int(11) DEFAULT 1,
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of question options',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assessment_id` (`assessment_id`),
  KEY `question_order` (`question_order`),
  KEY `question_type` (`question_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Options JSON structure:
-- [
--   {
--     "text": "Option 1",
--     "is_correct": true,
--     "order": 1
--   },
--   {
--     "text": "Option 2", 
--     "is_correct": false,
--     "order": 2
--   }
-- ]

-- Example data for testing:
INSERT INTO `questions` (`assessment_id`, `question_text`, `question_type`, `question_order`, `points`, `options`) VALUES
(1, 'What is the capital of the Philippines?', 'multiple_choice', 1, 1, '[{"text":"Manila","is_correct":true,"order":1},{"text":"Cebu","is_correct":false,"order":2},{"text":"Davao","is_correct":false,"order":3},{"text":"Quezon City","is_correct":false,"order":4}]'),
(1, 'PHP is a server-side scripting language.', 'true_false', 2, 1, '[{"text":"True","is_correct":true,"order":1},{"text":"False","is_correct":false,"order":2}]'),
(1, 'What does HTML stand for?', 'identification', 3, 2, '[{"text":"HyperText Markup Language","is_correct":true,"order":1}]');

