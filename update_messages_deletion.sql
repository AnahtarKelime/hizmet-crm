USE `hizmet_crm`;

ALTER TABLE `messages`
ADD COLUMN `deleted_by_sender` TINYINT(1) DEFAULT 0,
ADD COLUMN `deleted_by_receiver` TINYINT(1) DEFAULT 0;