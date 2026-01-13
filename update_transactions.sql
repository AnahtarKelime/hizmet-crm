ALTER TABLE `transactions` 
ADD COLUMN `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
ADD COLUMN `package_id` INT(11) UNSIGNED DEFAULT NULL;