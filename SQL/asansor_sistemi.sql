-- 1. ADIM: ASANSÖR KATEGORİSİNİ OLUŞTURMA
-- Eğer kategori zaten varsa hata vermemesi için kontrol ekleyebilirsiniz.
INSERT INTO `categories` (`id`, `name`, `keywords`, `slug`, `icon`, `image`, `is_active`, `is_featured`, `created_at`, `updated_at`) 
VALUES (11, 'Asansör', 'asansör bakımı, asansör tamiri, asansör montajı, revizyon, teknik servis', 'asansor', 'elevator', 'uploads/categories/asansor_default.jpg', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE `name`='Asansör';

-- 2. ADIM: KATEGORİYE AİT SORULARI EKLEME (category_id = 11)

INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`, `created_at`, `updated_at`) 
VALUES 
-- Soru 1: Hizmet Türü (Hizmet veren için en kritik bilgi)
(11, 'Hangi hizmeti almak istiyorsunuz?', 'select', '["Sıfırdan Montaj / Kurulum", "Periyodik Aylık Bakım", "Arıza Tamiri", "Revizyon / Modernizasyon", "Mavi-Kırmızı Etiket Eksik Giderme"]', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 2: Asansör Tipi
(11, 'Asansör tipi nedir?', 'select', '["İnsan Asansörü", "Yük Asansörü", "Sedye Asansörü", "Araç Asansörü", "Mutfak/Servis (Monşarj) Asansörü"]', 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 3: Bina Tipi (Erişim ve güvenlik standartları için)
(11, 'Asansörün bulunduğu bina tipi nedir?', 'radio', '["Apartman / Konut", "İş Merkezi / Ofis", "Fabrika / Sanayi", "Villa / Müstakil Ev", "Kamu Binası / Hastane"]', 1, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 4: Kat Sayısı (Teklif fiyatını doğrudan etkiler)
(11, 'Bina toplam kaç katlı?', 'number', NULL, 1, 4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 5: Mevcut Durum (Bakım ve Arıza için kritik)
(11, 'Asansörün şu anki durumu nedir?', 'radio', '["Çalışıyor", "Çalışıyor (Sesli/Sarsıntılı)", "Tamamen Durdu / Çalışmıyor", "Mühürlü / Kullanım Dışı"]', 1, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 6: Asansör Sayısı (Tasarruf ve toplu fiyat teklifi için)
(11, 'Hizmet alınacak toplam asansör sayısı?', 'select', '["1", "2", "3", "4", "5+"]', 1, 6, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 7: Ek Bilgiler ve Fotoğraf İsteği
(11, 'Eklemek istediğiniz notlar veya arıza detayı', 'textarea', NULL, 0, 7, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 8: Keşif Tarihi
(11, 'Keşif veya bakım için uygun olduğunuz tarih?', 'date', NULL, 1, 8, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);