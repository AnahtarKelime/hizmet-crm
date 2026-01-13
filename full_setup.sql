SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `hizmet_crm`;

-- 1. TÜM TABLOLARI TEMİZLE (DROP)
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `lead_access_logs`;
DROP TABLE IF EXISTS `offers`;
DROP TABLE IF EXISTS `demand_answers`;
DROP TABLE IF EXISTS `demands`;
DROP TABLE IF EXISTS `category_questions`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `provider_details`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `subscription_packages`;

-- 2. TABLOLARI YENİDEN OLUŞTUR (CREATE)

-- KULLANICILAR
CREATE TABLE `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('customer', 'provider', 'admin') NOT NULL DEFAULT 'customer',
  `balance` DECIMAL(10,2) DEFAULT 0.00,
  `is_verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- HİZMET VEREN DETAYLARI
CREATE TABLE `provider_details` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `business_name` VARCHAR(255) DEFAULT NULL,
  `bio` TEXT,
  `subscription_type` ENUM('free', 'premium') DEFAULT 'free',
  `subscription_ends_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_provider_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LOKASYONLAR
CREATE TABLE `locations` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `city` VARCHAR(100) NOT NULL,
  `district` VARCHAR(100) NOT NULL,
  `neighborhood` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  INDEX `idx_city_district` (`city`, `district`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KATEGORİLER
CREATE TABLE `categories` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `keywords` TEXT DEFAULT NULL COMMENT 'Virgülle ayrılmış arama terimleri',
  `slug` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KATEGORİ SORULARI
CREATE TABLE `category_questions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) UNSIGNED NOT NULL,
  `question_text` VARCHAR(255) NOT NULL,
  `input_type` ENUM('text', 'number', 'textarea', 'select', 'radio', 'checkbox', 'date') NOT NULL,
  `options` JSON DEFAULT NULL,
  `is_required` TINYINT(1) DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_question_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- HİZMET TALEPLERİ (LEADS)
CREATE TABLE `demands` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `category_id` INT(11) UNSIGNED NOT NULL,
  `location_id` INT(11) UNSIGNED NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `details` TEXT,
  `status` ENUM('pending', 'approved', 'completed', 'cancelled') DEFAULT 'pending',
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_demand_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_demand_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_demand_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TALEP CEVAPLARI
CREATE TABLE `demand_answers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `demand_id` INT(11) UNSIGNED NOT NULL,
  `question_id` INT(11) UNSIGNED DEFAULT NULL,
  `answer_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_answer_demand` FOREIGN KEY (`demand_id`) REFERENCES `demands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answer_question` FOREIGN KEY (`question_id`) REFERENCES `category_questions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TEKLİFLER
CREATE TABLE `offers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `demand_id` INT(11) UNSIGNED NOT NULL,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `message` TEXT,
  `status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
  `payment_plan` TEXT DEFAULT NULL,
  `payment_details` TEXT DEFAULT NULL,
  `service_agreement` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_offer_demand` FOREIGN KEY (`demand_id`) REFERENCES `demands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_offer_provider` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LEAD ERİŞİM KAYITLARI
CREATE TABLE `lead_access_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `demand_id` INT(11) UNSIGNED NOT NULL,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `access_type` ENUM('premium_view', 'credit_unlock', 'free_delayed') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_access` (`demand_id`, `user_id`),
  CONSTRAINT `fk_log_demand` FOREIGN KEY (`demand_id`) REFERENCES `demands` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_log_provider` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ÖDEME VE İŞLEMLER
CREATE TABLE `transactions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `type` ENUM('deposit', 'subscription_payment', 'lead_fee', 'refund') NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_transaction_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SİTE AYARLARI
CREATE TABLE `settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ABONELİK PAKETLERİ
CREATE TABLE `subscription_packages` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `duration_days` INT(11) NOT NULL DEFAULT 30,
  `features` JSON DEFAULT NULL COMMENT 'Paket özellikleri listesi',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. VERİLERİ EKLE (INSERT)

-- KATEGORİLER
INSERT INTO `categories` (`id`, `name`, `keywords`, `slug`, `icon`, `is_active`) VALUES
(1, 'Ev Temizliği', 'temizlikçi, gündelikçi, ev süpürme, cam silme, temizlik şirketi', 'ev-temizligi', 'cleaning_services', 1),
(2, 'Ofis Temizliği', 'iş yeri temizliği, büro temizliği, plaza temizliği', 'ofis-temizligi', 'business_center', 1),
(3, 'Evden Eve Nakliyat', 'taşımacılık, nakliye, kamyonet, eşya taşıma, parça eşya', 'evden-eve-nakliyat', 'local_shipping', 1),
(4, 'Boya Badana', 'boyacı, duvar boyama, alçı, tadilat, dekorasyon', 'boya-badana', 'format_paint', 1),
(5, 'Elektrikçi', 'elektrik tesisatı, priz montajı, avize takma, sigorta', 'elektrikci', 'electric_bolt', 1),
(6, 'Su Tesisatçısı', 'musluk tamiri, su kaçağı, tıkanıklık açma, klozet tamiri', 'su-tesisatcisi', 'plumbing', 1),
(7, 'Özel Ders', 'matematik, ingilizce, lgs, yks, piyano, gitar', 'ozel-ders', 'school', 1),
(8, 'Klima Servisi', 'klima montajı, klima bakımı, gaz dolumu, klima tamiri', 'klima-servisi', 'ac_unit', 1),
(9, 'Psikolog', 'terapi, danışmanlık, aile terapisi, pedagog', 'psikolog', 'psychology', 1),
(10, 'Diyetisyen', 'zayıflama, kilo alma, beslenme programı, diyet', 'diyetisyen', 'monitor_weight', 1);

-- KULLANICILAR VE PROFİLLER
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `password`, `role`, `is_verified`) VALUES
(1, 'Admin', 'Yönetici', 'admin@iyiteklif.com', '5550000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
(2, 'Ahmet', 'Yılmaz', 'musteri@ornek.com', '5550000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 1),
(3, 'Mehmet', 'Temiz', 'mehmet@temizlik.com', '5550000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'provider', 1),
(4, 'Ayşe', 'Nakliyat', 'ayse@nakliyat.com', '5550000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'provider', 1);

INSERT INTO `provider_details` (`user_id`, `business_name`, `bio`, `subscription_type`, `subscription_ends_at`) VALUES
(3, 'Mehmet Temizlik Hizmetleri', '10 yıllık tecrübe ile ev ve ofis temizliği.', 'free', NULL),
(4, 'Ayşe Nakliyat A.Ş.', 'Sigortalı ve asansörlü taşımacılık.', 'premium', DATE_ADD(NOW(), INTERVAL 1 YEAR));

-- KATEGORİ SORULARI
-- Ev Temizliği Soruları
INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(1, 'Evin büyüklüğü nedir?', 'select', '["1+0 (Stüdyo)", "1+1", "2+1", "3+1", "4+1", "Villa/Müstakil"]', 1, 1),
(1, 'Banyo sayısı?', 'number', NULL, 1, 2),
(1, 'Evde evcil hayvan var mı?', 'radio', '["Evet", "Hayır"]', 1, 3),
(1, 'Ekstra hizmetler', 'checkbox', '["Cam silme", "Ütü yapma", "Balkon yıkama", "Duvar silme", "Buzdolabı içi temizliği"]', 0, 4),
(1, 'Hizmet ne zaman gerekli?', 'date', NULL, 1, 5);

-- Nakliyat Soruları
INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(3, 'Eşyalar nereden taşınacak? (Kat bilgisi)', 'text', NULL, 1, 1),
(3, 'Eşyalar nereye taşınacak? (İlçe/Semt)', 'text', NULL, 1, 2),
(3, 'Oda sayısı?', 'select', '["1+1", "2+1", "3+1", "4+1"]', 1, 3),
(3, 'Paketleme hizmeti istiyor musunuz?', 'radio', '["Evet, tüm eşyalar", "Sadece mobilyalar", "Hayır"]', 1, 4),
(3, 'Asansör gerekli mi?', 'radio', '["Evet", "Hayır", "Bilmiyorum"]', 1, 5);

-- Boya Badana Soruları
INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(4, 'Boyanacak alanın durumu?', 'select', '["Eşyalı", "Boş"]', 1, 1),
(4, 'Kaç oda boyanacak?', 'select', '["1 Oda", "2 Oda", "3 Oda", "Tüm Ev (1+1)", "Tüm Ev (2+1)", "Tüm Ev (3+1)"]', 1, 2),
(4, 'Tavan boyası yapılacak mı?', 'radio', '["Evet", "Hayır"]', 1, 3),
(4, 'Malzeme (Boya) kimden?', 'radio', '["Usta temin etsin", "Ben aldım/alacağım"]', 1, 4);

-- Özel Ders Soruları
INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(7, 'Hangi ders için destek istiyorsunuz?', 'select', '["Matematik", "İngilizce", "Fizik", "Kimya", "Türkçe", "Piyano", "Gitar"]', 1, 1),
(7, 'Ders seviyesi nedir?', 'select', '["İlkokul", "Ortaokul (LGS)", "Lise (YKS)", "Üniversite", "Yetişkin"]', 1, 2),
(7, 'Dersler nerede yapılsın?', 'radio', '["Öğrencinin evinde", "Öğretmenin evinde", "Online", "Farketmez"]', 1, 3);

-- LOKASYONLAR
-- İSTANBUL
INSERT INTO `locations` (`city`, `district`, `neighborhood`, `slug`) VALUES
('İstanbul', 'Kadıköy', 'Caferağa', 'istanbul-kadikoy-caferaga'),
('İstanbul', 'Kadıköy', 'Rasimpaşa', 'istanbul-kadikoy-rasimpasa'),
('İstanbul', 'Kadıköy', 'Osmanağa', 'istanbul-kadikoy-osmanaga'),
('İstanbul', 'Kadıköy', 'Fenerbahçe', 'istanbul-kadikoy-fenerbahce'),
('İstanbul', 'Kadıköy', 'Caddebostan', 'istanbul-kadikoy-caddebostan'),
('İstanbul', 'Kadıköy', 'Suadiye', 'istanbul-kadikoy-suadiye'),
('İstanbul', 'Kadıköy', 'Bostancı', 'istanbul-kadikoy-bostanci'),
('İstanbul', 'Kadıköy', 'Göztepe', 'istanbul-kadikoy-goztepe'),
('İstanbul', 'Beşiktaş', 'Sinanpaşa', 'istanbul-besiktas-sinanpasa'),
('İstanbul', 'Beşiktaş', 'Türkali', 'istanbul-besiktas-turkali'),
('İstanbul', 'Beşiktaş', 'Vişnezade', 'istanbul-besiktas-visnezade'),
('İstanbul', 'Beşiktaş', 'Bebek', 'istanbul-besiktas-bebek'),
('İstanbul', 'Beşiktaş', 'Etiler', 'istanbul-besiktas-etiler'),
('İstanbul', 'Beşiktaş', 'Levent', 'istanbul-besiktas-levent'),
('İstanbul', 'Şişli', 'Merkez', 'istanbul-sisli-merkez'),
('İstanbul', 'Şişli', 'Teşvikiye', 'istanbul-sisli-tesvikiye'),
('İstanbul', 'Şişli', 'Nişantaşı', 'istanbul-sisli-nisantasi'),
('İstanbul', 'Şişli', 'Mecidiyeköy', 'istanbul-sisli-mecidiyekoy'),
('İstanbul', 'Şişli', 'Fulya', 'istanbul-sisli-fulya'),
('İstanbul', 'Üsküdar', 'Mimar Sinan', 'istanbul-uskudar-mimarsinan'),
('İstanbul', 'Üsküdar', 'Aziz Mahmut Hüdayi', 'istanbul-uskudar-aziz-mahmut-hudayi'),
('İstanbul', 'Üsküdar', 'Kuzguncuk', 'istanbul-uskudar-kuzguncuk'),
('İstanbul', 'Üsküdar', 'Beylerbeyi', 'istanbul-uskudar-beylerbeyi'),
('İstanbul', 'Üsküdar', 'Çengelköy', 'istanbul-uskudar-cengelkoy');

-- ANKARA
INSERT INTO `locations` (`city`, `district`, `neighborhood`, `slug`) VALUES
('Ankara', 'Çankaya', 'Kızılay', 'ankara-cankaya-kizilay'),
('Ankara', 'Çankaya', 'Bahçelievler', 'ankara-cankaya-bahcelievler'),
('Ankara', 'Çankaya', 'Ayrancı', 'ankara-cankaya-ayranci'),
('Ankara', 'Çankaya', 'Çayyolu', 'ankara-cankaya-cayyolu'),
('Ankara', 'Çankaya', 'Ümitköy', 'ankara-cankaya-umitkoy'),
('Ankara', 'Keçiören', 'Etlik', 'ankara-kecioren-etlik'),
('Ankara', 'Keçiören', 'İncirli', 'ankara-kecioren-incirli'),
('Ankara', 'Yenimahalle', 'Batıkent', 'ankara-yenimahalle-batikent'),
('Ankara', 'Yenimahalle', 'Demetevler', 'ankara-yenimahalle-demetevler');

-- İZMİR
INSERT INTO `locations` (`city`, `district`, `neighborhood`, `slug`) VALUES
('İzmir', 'Konak', 'Alsancak', 'izmir-konak-alsancak'),
('İzmir', 'Konak', 'Göztepe', 'izmir-konak-goztepe'),
('İzmir', 'Konak', 'Güzelyalı', 'izmir-konak-guzelyali'),
('İzmir', 'Karşıyaka', 'Bostanlı', 'izmir-karsiyaka-bostanli'),
('İzmir', 'Karşıyaka', 'Mavişehir', 'izmir-karsiyaka-mavisehir'),
('İzmir', 'Karşıyaka', 'Aksoy', 'izmir-karsiyaka-aksoy'),
('İzmir', 'Bornova', 'Özkanlar', 'izmir-bornova-ozkanlar'),
('İzmir', 'Bornova', 'Küçükpark', 'izmir-bornova-kucukpark');

-- ÖRNEK HİZMET TALEBİ (LEAD)
INSERT INTO `demands` (`id`, `user_id`, `category_id`, `location_id`, `title`, `details`, `status`, `approved_at`) VALUES
(1, 2, 1, 1, 'Kadıköy Caferağa 2+1 Detaylı Temizlik', 'Evim eşyalıdır. Kedim var, lütfen alerjisi olmayan arkadaşlar teklif versin. Sabah 09:00 gibi başlanabilir.', 'approved', NOW());

-- TALEP CEVAPLARI
INSERT INTO `demand_answers` (`demand_id`, `question_id`, `answer_text`) VALUES
(1, 1, '2+1'),
(1, 2, '1'),
(1, 3, 'Cam Temizliği, Balkon Yıkama'),
(1, 4, '2024-05-20'),
(1, 5, 'Evet');

-- ÖRNEK TEKLİF (OFFER)
INSERT INTO `offers` (`demand_id`, `user_id`, `price`, `message`, `status`, `payment_plan`, `payment_details`, `service_agreement`) VALUES
(1, 3, 1500.00, 'Merhaba, 2 kişilik ekibimizle gelip 1 günde tamamlarız. Malzemeler bize ait.', 'pending', 'İş bitiminde %100 ödeme alınır.', 'Ödeme nakit veya IBAN yoluyla yapılabilir. Kredi kartı geçerli değildir.', '1. Hizmet süresi tahmini 6 saattir.\n2. Kullanılacak tüm temizlik malzemeleri tarafımızca karşılanacaktır.\n3. Memnuniyet garantisi verilmektedir, beğenilmeyen alanlar tekrar temizlenir.');

-- VARSAYILAN AYARLAR
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_title', 'iyiteklif'),
('site_description', 'Türkiye\'nin en güvenilir hizmet pazaryeri.'),
('contact_email', 'info@iyiteklif.com'),
('contact_phone', '0850 123 45 67'),
('site_logo', 'logo.png'),
('payment_bank_active', '0'),
('payment_bank_holder', ''),
('payment_bank_iban', ''),
('payment_cc_active', '0'),
('payment_iyzico_base_url', 'https://sandbox-api.iyzipay.com'),
('payment_iyzico_api_key', ''),
('payment_iyzico_secret_key', '');

-- ABONELİK PAKETLERİ
INSERT INTO `subscription_packages` (`name`, `price`, `duration_days`, `features`, `is_active`) VALUES
('Başlangıç Paketi', 0.00, 30, '["Ayda 5 Teklif Hakkı", "Temel Profil Görünümü", "E-posta Desteği"]', 1),
('Profesyonel Paket', 499.00, 30, '["Ayda 50 Teklif Hakkı", "Öne Çıkan Profil", "SMS Bildirimleri", "7/24 Destek"]', 1),
('Kurumsal Paket', 4999.00, 365, '["Sınırsız Teklif Hakkı", "Rozetli Onaylı Profil", "Arama Sonuçlarında En Üstte", "Özel Müşteri Temsilcisi"]', 1);

SET FOREIGN_KEY_CHECKS = 1;
DELETE FROM `transactions`;
ALTER TABLE `transactions` AUTO_INCREMENT = 1;
DELETE FROM `lead_access_logs`;
ALTER TABLE `lead_access_logs` AUTO_INCREMENT = 1;
DELETE FROM `offers`;
ALTER TABLE `offers` AUTO_INCREMENT = 1;
DELETE FROM `demand_answers`;
ALTER TABLE `demand_answers` AUTO_INCREMENT = 1;
DELETE FROM `demands`;
ALTER TABLE `demands` AUTO_INCREMENT = 1;
DELETE FROM `category_questions`;
ALTER TABLE `category_questions` AUTO_INCREMENT = 1;
DELETE FROM `categories`;
ALTER TABLE `categories` AUTO_INCREMENT = 1;
DELETE FROM `locations`;
ALTER TABLE `locations` AUTO_INCREMENT = 1;
DELETE FROM `provider_details`;
ALTER TABLE `provider_details` AUTO_INCREMENT = 1;
DELETE FROM `users`;
ALTER TABLE `users` AUTO_INCREMENT = 1;

-- --------------------------------------------------------
-- 1. KATEGORİLER (HİZMETLER)
-- --------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `keywords` TEXT DEFAULT NULL COMMENT 'Virgülle ayrılmış arama terimleri',
  `slug` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `tracking_code_head` TEXT DEFAULT NULL,
  `tracking_code_body` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `categories` (`id`, `name`, `keywords`, `slug`, `icon`, `is_active`) VALUES
(1, 'Ev Temizliği', 'temizlikçi, gündelikçi, ev süpürme, cam silme, temizlik şirketi', 'ev-temizligi', 'cleaning_services', 1),
(2, 'Ofis Temizliği', 'iş yeri temizliği, büro temizliği, plaza temizliği', 'ofis-temizligi', 'business_center', 1),
(3, 'Evden Eve Nakliyat', 'taşımacılık, nakliye, kamyonet, eşya taşıma, parça eşya', 'evden-eve-nakliyat', 'local_shipping', 1),
(4, 'Boya Badana', 'boyacı, duvar boyama, alçı, tadilat, dekorasyon', 'boya-badana', 'format_paint', 1),
(5, 'Elektrikçi', 'elektrik tesisatı, priz montajı, avize takma, sigorta', 'elektrikci', 'electric_bolt', 1),
(6, 'Su Tesisatçısı', 'musluk tamiri, su kaçağı, tıkanıklık açma, klozet tamiri', 'su-tesisatcisi', 'plumbing', 1),
(7, 'Özel Ders', 'matematik, ingilizce, lgs, yks, piyano, gitar', 'ozel-ders', 'school', 1),
(8, 'Klima Servisi', 'klima montajı, klima bakımı, gaz dolumu, klima tamiri', 'klima-servisi', 'ac_unit', 1),
(9, 'Psikolog', 'terapi, danışmanlık, aile terapisi, pedagog', 'psikolog', 'psychology', 1),
(10, 'Diyetisyen', 'zayıflama, kilo alma, beslenme programı, diyet', 'diyetisyen', 'monitor_weight', 1);

-- --------------------------------------------------------
-- 2. KULLANICILAR VE PROFİLLER
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `password`, `role`, `is_verified`) VALUES
(1, 'Admin', 'Yönetici', 'admin@iyiteklif.com', '5550000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
(2, 'Ahmet', 'Yılmaz', 'musteri@ornek.com', '5550000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 1),
(3, 'Mehmet', 'Temiz', 'mehmet@temizlik.com', '5550000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'provider', 1),
(4, 'Ayşe', 'Nakliyat', 'ayse@nakliyat.com', '5550000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'provider', 1);

INSERT INTO `provider_details` (`user_id`, `business_name`, `bio`, `subscription_type`, `subscription_ends_at`) VALUES
(3, 'Mehmet Temizlik Hizmetleri', '10 yıllık tecrübe ile ev ve ofis temizliği.', 'free', NULL),
(4, 'Ayşe Nakliyat A.Ş.', 'Sigortalı ve asansörlü taşımacılık.', 'premium', DATE_ADD(NOW(), INTERVAL 1 YEAR));

-- --------------------------------------------------------
-- 3. KATEGORİ SORULARI (DİNAMİK FORM)
-- --------------------------------------------------------

-- Ev Temizliği Soruları
INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(1, 'Evin büyüklüğü nedir?', 'select', '["1+0 (Stüdyo)", "1+1", "2+1", "3+1", "4+1", "Villa/Müstakil"]', 1, 1),
(1, 'Banyo sayısı?', 'number', NULL, 1, 2),
(1, 'Evde evcil hayvan var mı?', 'radio', '["Evet", "Hayır"]', 1, 3),
(1, 'Ekstra hizmetler', 'checkbox', '["Cam silme", "Ütü yapma", "Balkon yıkama", "Duvar silme", "Buzdolabı içi temizliği"]', 0, 4),
(1, 'Hizmet ne zaman gerekli?', 'date', NULL, 1, 5);

-- Nakliyat Soruları
INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(3, 'Eşyalar nereden taşınacak? (Kat bilgisi)', 'text', NULL, 1, 1),
(3, 'Eşyalar nereye taşınacak? (İlçe/Semt)', 'text', NULL, 1, 2),
(3, 'Oda sayısı?', 'select', '["1+1", "2+1", "3+1", "4+1"]', 1, 3),
(3, 'Paketleme hizmeti istiyor musunuz?', 'radio', '["Evet, tüm eşyalar", "Sadece mobilyalar", "Hayır"]', 1, 4),
(3, 'Asansör gerekli mi?', 'radio', '["Evet", "Hayır", "Bilmiyorum"]', 1, 5);

-- Boya Badana Soruları
INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(4, 'Boyanacak alanın durumu?', 'select', '["Eşyalı", "Boş"]', 1, 1),
(4, 'Kaç oda boyanacak?', 'select', '["1 Oda", "2 Oda", "3 Oda", "Tüm Ev (1+1)", "Tüm Ev (2+1)", "Tüm Ev (3+1)"]', 1, 2),
(4, 'Tavan boyası yapılacak mı?', 'radio', '["Evet", "Hayır"]', 1, 3),
(4, 'Malzeme (Boya) kimden?', 'radio', '["Usta temin etsin", "Ben aldım/alacağım"]', 1, 4);

-- Özel Ders Soruları
INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(7, 'Hangi ders için destek istiyorsunuz?', 'select', '["Matematik", "İngilizce", "Fizik", "Kimya", "Türkçe", "Piyano", "Gitar"]', 1, 1),
(7, 'Ders seviyesi nedir?', 'select', '["İlkokul", "Ortaokul (LGS)", "Lise (YKS)", "Üniversite", "Yetişkin"]', 1, 2),
(7, 'Dersler nerede yapılsın?', 'radio', '["Öğrencinin evinde", "Öğretmenin evinde", "Online", "Farketmez"]', 1, 3);

-- --------------------------------------------------------
-- 4. LOKASYONLAR (ÖRNEK GENİŞLETİLMİŞ SET)
-- --------------------------------------------------------

-- İSTANBUL
INSERT INTO `locations` (`city`, `district`, `neighborhood`, `slug`) VALUES
-- Kadıköy
('İstanbul', 'Kadıköy', 'Caferağa', 'istanbul-kadikoy-caferaga'),
('İstanbul', 'Kadıköy', 'Rasimpaşa', 'istanbul-kadikoy-rasimpasa'),
('İstanbul', 'Kadıköy', 'Osmanağa', 'istanbul-kadikoy-osmanaga'),
('İstanbul', 'Kadıköy', 'Fenerbahçe', 'istanbul-kadikoy-fenerbahce'),
('İstanbul', 'Kadıköy', 'Caddebostan', 'istanbul-kadikoy-caddebostan'),
('İstanbul', 'Kadıköy', 'Suadiye', 'istanbul-kadikoy-suadiye'),
('İstanbul', 'Kadıköy', 'Bostancı', 'istanbul-kadikoy-bostanci'),
('İstanbul', 'Kadıköy', 'Göztepe', 'istanbul-kadikoy-goztepe'),
-- Beşiktaş
('İstanbul', 'Beşiktaş', 'Sinanpaşa', 'istanbul-besiktas-sinanpasa'),
('İstanbul', 'Beşiktaş', 'Türkali', 'istanbul-besiktas-turkali'),
('İstanbul', 'Beşiktaş', 'Vişnezade', 'istanbul-besiktas-visnezade'),
('İstanbul', 'Beşiktaş', 'Bebek', 'istanbul-besiktas-bebek'),
('İstanbul', 'Beşiktaş', 'Etiler', 'istanbul-besiktas-etiler'),
('İstanbul', 'Beşiktaş', 'Levent', 'istanbul-besiktas-levent'),
-- Şişli
('İstanbul', 'Şişli', 'Merkez', 'istanbul-sisli-merkez'),
('İstanbul', 'Şişli', 'Teşvikiye', 'istanbul-sisli-tesvikiye'),
('İstanbul', 'Şişli', 'Nişantaşı', 'istanbul-sisli-nisantasi'),
('İstanbul', 'Şişli', 'Mecidiyeköy', 'istanbul-sisli-mecidiyekoy'),
('İstanbul', 'Şişli', 'Fulya', 'istanbul-sisli-fulya'),
-- Üsküdar
('İstanbul', 'Üsküdar', 'Mimar Sinan', 'istanbul-uskudar-mimarsinan'),
('İstanbul', 'Üsküdar', 'Aziz Mahmut Hüdayi', 'istanbul-uskudar-aziz-mahmut-hudayi'),
('İstanbul', 'Üsküdar', 'Kuzguncuk', 'istanbul-uskudar-kuzguncuk'),
('İstanbul', 'Üsküdar', 'Beylerbeyi', 'istanbul-uskudar-beylerbeyi'),
('İstanbul', 'Üsküdar', 'Çengelköy', 'istanbul-uskudar-cengelkoy');

-- ANKARA
INSERT INTO `locations` (`city`, `district`, `neighborhood`, `slug`) VALUES
-- Çankaya
('Ankara', 'Çankaya', 'Kızılay', 'ankara-cankaya-kizilay'),
('Ankara', 'Çankaya', 'Bahçelievler', 'ankara-cankaya-bahcelievler'),
('Ankara', 'Çankaya', 'Ayrancı', 'ankara-cankaya-ayranci'),
('Ankara', 'Çankaya', 'Çayyolu', 'ankara-cankaya-cayyolu'),
('Ankara', 'Çankaya', 'Ümitköy', 'ankara-cankaya-umitkoy'),
-- Keçiören
('Ankara', 'Keçiören', 'Etlik', 'ankara-kecioren-etlik'),
('Ankara', 'Keçiören', 'İncirli', 'ankara-kecioren-incirli'),
-- Yenimahalle
('Ankara', 'Yenimahalle', 'Batıkent', 'ankara-yenimahalle-batikent'),
('Ankara', 'Yenimahalle', 'Demetevler', 'ankara-yenimahalle-demetevler');

-- İZMİR
INSERT INTO `locations` (`city`, `district`, `neighborhood`, `slug`) VALUES
-- Konak
('İzmir', 'Konak', 'Alsancak', 'izmir-konak-alsancak'),
('İzmir', 'Konak', 'Göztepe', 'izmir-konak-goztepe'),
('İzmir', 'Konak', 'Güzelyalı', 'izmir-konak-guzelyali'),
-- Karşıyaka
('İzmir', 'Karşıyaka', 'Bostanlı', 'izmir-karsiyaka-bostanli'),
('İzmir', 'Karşıyaka', 'Mavişehir', 'izmir-karsiyaka-mavisehir'),
('İzmir', 'Karşıyaka', 'Aksoy', 'izmir-karsiyaka-aksoy'),
-- Bornova
('İzmir', 'Bornova', 'Özkanlar', 'izmir-bornova-ozkanlar'),
('İzmir', 'Bornova', 'Küçükpark', 'izmir-bornova-kucukpark');
