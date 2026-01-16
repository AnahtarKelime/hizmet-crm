-- 1. ADIM: KATEGORİ OLUŞTURMA
INSERT INTO `categories` (`name`, `keywords`, `slug`, `icon`, `is_active`, `is_featured`, `created_at`, `updated_at`) 
VALUES ('Yurt Dışı Express Taşımacılık', 'yurt dışı kargo, avrupa kargo, uçak kargo, express lojistik, mikro ihracat', 'yurt-disi-express-tasimacilik', 'public', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

-- 2. ADIM: SORU VE SEÇENEKLERİN OLUŞTURULMASI
-- Not: category_id değerini yeni eklenen kategorinin ID'sine göre güncelleyiniz (Örn: 13).

INSERT INTO `category_questions` (`category_id`, `question_text`, `input_type`, `options`, `is_required`, `sort_order`, `created_at`, `updated_at`) 
VALUES 
-- Soru 1: Varış Bölgesi (Avrupa Odaklı Orta Düzey Hizmet Paketini Tetikler)
(13, 'Gönderinin varış ülkesi/bölgesi neresidir?', 'select', '["Almanya", "Fransa", "İngiltere", "Hollanda", "Diğer Avrupa Ülkeleri", "Amerika / Kanada", "Orta Doğu"]', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 2: Gönderi Tipi (Gümrük süreçleri için kritik)
(13, 'Gönderinizin içeriği nedir?', 'radio', '["Döküman / Evrak", "Hediyelik Eşya / Numune", "Ticari Ürün (Mikro İhracat)", "Kişisel Eşya"]', 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 3: Paket Ölçüleri (Hacimsel ağırlık hesaplaması için)
(13, 'Paketinizin yaklaşık ağırlığı (kg) ve desi (GxYxDerinlik) bilgisini giriniz.', 'textarea', NULL, 1, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 4: Hizmet Paketi Seçimi (Avrupa Orta Düzey Planı Dahil)
(13, 'Tercih ettiğiniz gönderi hızı ve paketi nedir?', 'select', '["Ekonomik (5-7 İş Günü)", "Standart Avrupa (3-5 İş Günü)", "Express Plus (1-2 İş Günü)", "Kapıdan Kapıya Teslimat"]', 1, 4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 5: Sigorta Durumu (Güven unsuru)
(13, 'Gönderiniz için ek sigorta istiyor musunuz?', 'radio', '["Evet, ürün bedeli üzerinden sigorta istiyorum", "Hayır, standart koruma yeterli"]', 1, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 6: Gümrükleme Desteği (Tasarruf ve Süreç Kolaylığı)
(13, 'Gümrükleme işlemleri için desteğe ihtiyacınız var mı?', 'select', '["ETGB (Mikro İhracat) Desteği İstiyorum", "Kendi gümrükçüm ile çalışacağım", "Gümrük muafiyet sınırı altındayım"]', 1, 6, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),

-- Soru 7: Alım Tercihi
(13, 'Paket nasıl teslim alınsın?', 'radio', '["Kurye adresime gelsin", "Kendim şubeye teslim edeceğim"]', 1, 7, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);