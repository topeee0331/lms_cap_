-- Add plain_text_password field to users table for admin password viewing
-- WARNING: This reduces security by storing passwords in plain text

ALTER TABLE `users` ADD COLUMN `plain_text_password` VARCHAR(255) DEFAULT NULL COMMENT 'Plain text password for admin viewing (SECURITY RISK)';

-- Update existing users with default password
UPDATE `users` SET `plain_text_password` = 'password123' WHERE `plain_text_password` IS NULL;

-- Add index for better performance
CREATE INDEX `idx_plain_text_password` ON `users` (`plain_text_password`);
