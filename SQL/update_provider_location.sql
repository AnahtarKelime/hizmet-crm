ALTER TABLE `provider_details` ADD COLUMN `address_text` VARCHAR(255) NULL AFTER `bio`;
ALTER TABLE `provider_details` ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `address_text`;
ALTER TABLE `provider_details` ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`;