-- Kullanıcılar tablosuna Google ID ve avatar URL'si ekle
ALTER TABLE `users`
ADD COLUMN `google_id` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `avatar_url` VARCHAR(255) DEFAULT NULL,
ADD UNIQUE KEY `google_id` (`google_id`);

-- Ayarlar tablosuna Google Login ayarlarını ekle
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('google_login_active', '0'),
('google_client_id', ''),
('google_client_secret', '')
ON DUPLICATE KEY UPDATE setting_key=setting_key;