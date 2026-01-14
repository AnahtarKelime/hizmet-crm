ALTER TABLE `categories`
ADD COLUMN `is_featured` TINYINT(1) DEFAULT 0 AFTER `is_active`;