-- Push Abonelikleri Tablosu
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `public_key` varchar(255) NOT NULL,
  `auth_token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  UNIQUE KEY `endpoint` (`endpoint`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ayarlar tablosuna VAPID anahtarları için yer tutucu (Admin panelinden veya manuel güncelleyin)
-- https://web-push-codelab.glitch.me/ adresinden anahtar üretebilirsiniz.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES 
('vapid_public_key', 'BURAYA_PUBLIC_KEY_GELECEK'),
('vapid_private_key', 'BURAYA_PRIVATE_KEY_GELECEK'),
('vapid_subject', 'mailto:admin@iyiteklif.com');