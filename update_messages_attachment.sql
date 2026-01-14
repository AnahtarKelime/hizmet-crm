USE `hizmet_crm`;

ALTER TABLE `messages` ADD COLUMN `attachment` VARCHAR(255) DEFAULT NULL AFTER `message`;