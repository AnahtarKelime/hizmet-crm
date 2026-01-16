SET NAMES utf8mb4;
USE `hizmet_crm`;

-- 1. Kullanıcıları Ekle (Şifre: 'password')
-- Roller: 1:Admin, 2:Müşteri, 3:Hizmet Veren (Free), 4:Hizmet Veren (Premium)
INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `password`, `role`, `is_verified`) VALUES
(1, 'Admin', 'Yönetici', 'admin@iyiteklif.com', '5550000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
(2, 'Ahmet', 'Yılmaz', 'musteri@ornek.com', '5550000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 1),
(3, 'Mehmet', 'Temiz', 'mehmet@temizlik.com', '5550000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'provider', 1),
(4, 'Ayşe', 'Nakliyat', 'ayse@nakliyat.com', '5550000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'provider', 1);

-- 2. Hizmet Veren Detayları
INSERT INTO `provider_details` (`user_id`, `business_name`, `bio`, `subscription_type`, `subscription_ends_at`) VALUES
(3, 'Mehmet Temizlik Hizmetleri', '10 yıllık tecrübe ile ev ve ofis temizliği.', 'free', NULL),
(4, 'Ayşe Nakliyat A.Ş.', 'Sigortalı ve asansörlü taşımacılık.', 'premium', DATE_ADD(NOW(), INTERVAL 1 YEAR));

-- 3. Lokasyonlar (İstanbul Örnekleri)
INSERT INTO `locations` (`id`, `city`, `district`, `neighborhood`, `slug`) VALUES
(1, 'İstanbul', 'Kadıköy', 'Caferağa', 'istanbul-kadikoy-caferaga'),
(2, 'İstanbul', 'Kadıköy', 'Rasimpaşa', 'istanbul-kadikoy-rasimpasa'),
(3, 'İstanbul', 'Beşiktaş', 'Sinanpaşa', 'istanbul-besiktas-sinanpasa'),
(4, 'İstanbul', 'Şişli', 'Merkez', 'istanbul-sisli-merkez'),
(5, 'İstanbul', 'Üsküdar', 'Mimar Sinan', 'istanbul-uskudar-mimarsinan');

-- 4. Kategoriler
INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `is_active`) VALUES
(1, 'Ev Temizliği', 'ev-temizligi', 'cleaning_services', 1),
(2, 'Evden Eve Nakliyat', 'evden-eve-nakliyat', 'local_shipping', 1),
(3, 'Boya Badana', 'boya-badana', 'format_paint', 1),
(4, 'Özel Ders', 'ozel-ders', 'school', 1);

-- 5. Kategori Soruları (Ev Temizliği için)
INSERT INTO `category_questions` (`id`, `category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`) VALUES
(1, 1, 'Evin Tipi Nedir?', 'select', '["1+0 (Stüdyo)", "1+1", "2+1", "3+1", "4+1", "Dubleks/Villa"]', 1, 1),
(2, 1, 'Banyo Sayısı', 'number', NULL, 1, 2),
(3, 1, 'Ekstra Hizmetler', 'checkbox', '["Ütü", "Cam Temizliği", "Balkon Yıkama", "Duvar Silme", "Buzdolabı İçi"]', 0, 3),
(4, 1, 'Hizmet Tarihi', 'date', NULL, 1, 4),
(5, 1, 'Evcil Hayvan Var mı?', 'radio', '["Evet", "Hayır"]', 1, 5);

-- 6. Örnek Hizmet Talebi (Lead)
INSERT INTO `demands` (`id`, `user_id`, `category_id`, `location_id`, `title`, `details`, `status`, `approved_at`) VALUES
(1, 2, 1, 1, 'Kadıköy Caferağa 2+1 Detaylı Temizlik', 'Evim eşyalıdır. Kedim var, lütfen alerjisi olmayan arkadaşlar teklif versin. Sabah 09:00 gibi başlanabilir.', 'approved', NOW());

-- 7. Talep Cevapları
INSERT INTO `demand_answers` (`demand_id`, `question_id`, `answer_text`) VALUES
(1, 1, '2+1'),
(1, 2, '1'),
(1, 3, 'Cam Temizliği, Balkon Yıkama'),
(1, 4, '2024-05-20'),
(1, 5, 'Evet');

-- 8. Örnek Teklif (Offer)
INSERT INTO `offers` (`demand_id`, `user_id`, `price`, `message`, `status`) VALUES
(1, 3, 1500.00, 'Merhaba, 2 kişilik ekibimizle gelip 1 günde tamamlarız. Malzemeler bize ait.', 'pending');
