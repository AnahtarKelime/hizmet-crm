-- 1. ADIM: ALTYAPI GÜNCELLEMESİ (Yeni Soru Tipi: location)
-- Mevcut ENUM yapısına 'location' tipini ekliyoruz.
-- Bu sayede form oluştururken bu tipteki sorular için Google Maps Autocomplete özelliğini tetikleyebiliriz.
ALTER TABLE `category_questions` 
MODIFY COLUMN `input_type` ENUM('text', 'number', 'textarea', 'select', 'radio', 'checkbox', 'date', 'location') NOT NULL;

-- 2. ADIM: MOTO KURYE KATEGORİSİNİ OLUŞTURMA
INSERT INTO `categories` (`name`, `keywords`, `slug`, `icon`, `is_active`, `is_featured`, `created_at`, `updated_at`) 
VALUES ('Moto Kurye', 'kurye, motorlu kurye, paket taşıma, acil kurye, şehir içi teslimat, evrak gönderimi', 'moto-kurye', 'two_wheeler', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `keywords` = VALUES(`keywords`), `icon` = VALUES(`icon`);

-- Yeni eklenen kategorinin ID'sini alalım
SET @cat_id = (SELECT id FROM categories WHERE slug = 'moto-kurye');

-- 3. ADIM: SORULARI OLUŞTURMA
-- Burada 'location' tipini kullanarak gönderici ve alıcı adreslerini alıyoruz.

INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`, `created_at`, `updated_at`) 
VALUES 
-- Soru 1: Gönderici Konumu (Öncelikli)
(@cat_id, 'Paket nereden alınacak? (Gönderici Adresi)', 'location', NULL, 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 2: Alıcı Konumu
(@cat_id, 'Paket nereye teslim edilecek? (Alıcı Adresi)', 'location', NULL, 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 3: Paket İçeriği (Yasal ve lojistik uygunluk için)
(@cat_id, 'Paket içeriği nedir?', 'select', '["Evrak / Dosya", "Koli / Paket", "Yiyecek / Gıda", "Elektronik Eşya", "Hediye", "Yedek Parça", "Diğer"]', 1, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 4: Ağırlık ve Boyut (Motor kapasitesi için kritik)
(@cat_id, 'Paket boyutu ve ağırlığı?', 'select', '["Zarf / Dosya (0-1 kg)", "Küçük Paket (Ayakkabı kutusu kadar, 1-5 kg)", "Orta Paket (Sırt çantası kadar, 5-10 kg)", "Büyük Paket (Motor çantasına sığacak max boyut)"]', 1, 4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 5: Aciliyet Durumu (Fiyatlandırmayı etkiler)
(@cat_id, 'Teslimat aciliyeti nedir?', 'radio', '["Standart (Gün içi teslimat)", "Express (90-120 dk)", "VIP (45-60 dk - Direkt Teslimat)"]', 1, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 6: İletişim Notları
(@cat_id, 'Kurye için ek notlar (Zil bozuk, kapıda bırak vb.)', 'textarea', NULL, 0, 6, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- 4. ADIM: VARSAYILAN HİZMET VERENLERİ GÜNCELLEME (Opsiyonel)
-- Mevcut demo kullanıcılarına (Örn: Ayşe Nakliyat) bu kategoriyi de ekleyelim ki test edebilin.
-- Ayşe Nakliyat ID: 4 (Seed dosyasından varsayım)
INSERT IGNORE INTO `provider_service_categories` (`user_id`, `category_id`) 
SELECT 4, @cat_id FROM DUAL;

-- Mehmet Temizlik (ID: 3) de kurye yapsın (Test için)
INSERT IGNORE INTO `provider_service_categories` (`user_id`, `category_id`) 
SELECT 3, @cat_id FROM DUAL;