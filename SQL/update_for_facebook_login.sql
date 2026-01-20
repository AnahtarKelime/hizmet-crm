-- Kullanıcılar tablosuna Facebook ID ekle
ALTER TABLE `users`
ADD COLUMN `facebook_id` VARCHAR(255) DEFAULT NULL,
ADD UNIQUE KEY `facebook_id` (`facebook_id`);

-- Ayarlar tablosuna Facebook Login ayarlarını ekle
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('facebook_login_active', '0'),
('facebook_app_id', ''),
('facebook_app_secret', '')
ON DUPLICATE KEY UPDATE setting_key=setting_key;