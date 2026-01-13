-- Güncel bir talep (Lead) oluşturma simülasyonu
-- Müşteri: Ahmet Yılmaz (ID: 2)
-- Kategori: Ev Temizliği (ID: 1)
-- Lokasyon: İstanbul, Kadıköy, Caferağa (ID: 1)

INSERT INTO `demands` (`user_id`, `category_id`, `location_id`, `title`, `details`, `status`, `created_at`) 
VALUES (2, 1, 1, 'Acil 3+1 Ev Temizliği', 'Eşyalı evim için detaylı temizlik istiyorum. Malzemeler bende mevcut değil.', 'pending', NOW());

SET @demand_id = LAST_INSERT_ID();

INSERT INTO `demand_answers` (`demand_id`, `question_id`, `answer_text`) VALUES 
(@demand_id, 1, '3+1'), -- Evin büyüklüğü
(@demand_id, 2, '2'),   -- Banyo sayısı
(@demand_id, 3, 'Hayır'), -- Evcil hayvan
(@demand_id, 4, 'Cam silme, Balkon yıkama'), -- Ekstra hizmetler
(@demand_id, 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY)); -- Tarih (2 gün sonra)