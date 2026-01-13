-- Bu dosyayı phpMyAdmin'de içe aktararak veritabanınızı güncelleyin.

-- ALTER TABLE `subscription_packages`
-- ADD COLUMN `offer_credit` INT(11) NOT NULL DEFAULT 0 COMMENT 'Paketin sağladığı teklif verme kredisi. -1 = Sınırsız';

-- ALTER TABLE `provider_details`
-- ADD COLUMN `remaining_offer_credit` INT(11) NOT NULL DEFAULT 0 COMMENT 'Kullanıcının kalan teklif kredisi. -1 = Sınırsız';

-- ALTER TABLE `transactions` 
-- ADD COLUMN `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
-- ADD COLUMN `package_id` INT(11) UNSIGNED DEFAULT NULL;