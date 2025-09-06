-- Add assessment_order field to assessments table
-- This allows teachers to manually set the order of assessments

ALTER TABLE `assessments` 
ADD COLUMN `assessment_order` int(11) DEFAULT 1 COMMENT 'Manual order for assessments (1 = first, 2 = second, etc.)' 
AFTER `assessment_title`;

-- Update existing assessments with default order values
-- Assessment 1 should be order 1, Assessment 2 should be order 2, etc.
UPDATE `assessments` 
SET `assessment_order` = 1 
WHERE `assessment_title` LIKE '%Assessment 1%' OR `id` = 'assess_68b6e5b2bd453';

UPDATE `assessments` 
SET `assessment_order` = 2 
WHERE `assessment_title` LIKE '%Assessment 2%' OR `id` = 'assess_68b63fb3c750e';

UPDATE `assessments` 
SET `assessment_order` = 3 
WHERE `assessment_title` LIKE '%Assessment 3%' OR `id` = 'assess_68b6f2db5e3d8';

-- Add index for better performance when sorting by order
ALTER TABLE `assessments` 
ADD KEY `idx_assessment_order` (`assessment_order`);
