-- Add questions JSON column to assessments table for normalized storage
-- This approach stores questions directly in the assessments table as JSON

-- Add questions column to assessments table if it doesn't exist
ALTER TABLE `assessments` 
ADD COLUMN IF NOT EXISTS `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of questions with options';

-- Note: This approach is more normalized as it keeps all assessment data together
-- Questions are stored as JSON objects with the following structure:
-- [
--   {
--     "id": "unique_question_id",
--     "question_text": "What is the question?",
--     "question_type": "multiple_choice|true_false|identification",
--     "question_order": 1,
--     "points": 1,
--     "options": [
--       {"text": "Option 1", "is_correct": true, "order": 1},
--       {"text": "Option 2", "is_correct": false, "order": 2}
--     ],
--     "created_at": "2024-01-01 12:00:00"
--   }
-- ]
