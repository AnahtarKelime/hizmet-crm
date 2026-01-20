ALTER TABLE `demands` ADD COLUMN `address_text` VARCHAR(255) NULL AFTER `location_id`;
ALTER TABLE `demands` ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `address_text`;
ALTER TABLE `demands` ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`;