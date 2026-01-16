-- 1. ADIM: PET KUAFÖR KATEGORİSİNİ OLUŞTURMA
INSERT INTO `categories` (`name`, `keywords`, `slug`, `icon`, `is_active`, `is_featured`, `created_at`, `updated_at`) 
VALUES ('Pet Kuaför', 'kedi tıraşı, köpek tıraşı, evde pet kuaför, kedi banyosu, tırnak kesimi, tüy bakımı', 'pet-kuafor', 'pets', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- 2. ADIM: KATEGORİYE AİT SORULARI EKLEME
-- Not: category_id değerini yeni eklenen Pet Kuaför ID'sine göre kontrol ediniz (Örn: 12).

INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`, `created_at`, `updated_at`) 
VALUES 
-- Soru 1: Evcil Hayvan Türü
(12, 'Hangi evcil hayvanınız için hizmet istiyorsunuz?', 'radio', '["Köpek", "Kedi"]', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 2: Hizmet Paketi (Tasarruf ve Netlik için)
(12, 'Hangi işlemler yapılacak?', 'checkbox', '["Makas Tıraşı", "Makine Tıraşı", "Banyo & Tarama", "Tırnak Kesimi", "Kulak & Göz Temizliği"]', 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 3: Irk ve Boyut Bilgisi (Fiyatı doğrudan etkiler)
(12, 'Evcil hayvanınızın ırkı veya boyutu nedir?', 'select', '["Küçük Irk (0-10 kg)", "Orta Irk (10-20 kg)", "Büyük Irk (20+ kg)", "Cins Bilgisini Notlara Yazacağım"]', 1, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 4: Agresyon ve Davranış (Güvenlik İçin)
(12, 'Hayvanınızın karakteri/davranışı nasıldır?', 'select', '["Uysal / Alışkın", "Heyecanlı / Hareketli", "Korkak / Çekingen", "Agresif / Isırma Eğilimi Var"]', 1, 4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 5: Sağlık ve Tüy Durumu (Ekstra emek gerektiren durumlar)
(12, 'Tüylerin mevcut durumu nedir?', 'radio', '["Normal", "Kıtıklaşmış / Düğümlenmiş", "Aşırı Dökülme Var", "Deri Hassasiyeti Var"]', 1, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 6: Hizmet Yeri (Konfor İçin)
(12, 'Hizmet nerede verilsin?', 'radio', '["Ben pet kuaföre götüreceğim", "Adresimde (Evde) hizmet istiyorum", "Pet taksi ile alınsın/getirilsin"]', 1, 6, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 7: Ek Notlar
(12, 'Varsa alerji, kronik hastalık veya özel istekleriniz', 'textarea', NULL, 0, 7, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);