-- Ayşe Nakliyat (ID: 4) kullanıcısından, Talep ID: 1 için örnek teklif
-- Bu sorguyu çalıştırarak sisteme demo bir teklif ekleyebilirsiniz.

INSERT INTO `offers` 
(`demand_id`, `user_id`, `price`, `message`, `status`, `payment_plan`, `payment_details`, `service_agreement`, `created_at`) 
VALUES 
(1, 4, 3200.00, 
'Merhaba, Ayşe Nakliyat olarak profesyonel ekibimizle hizmetinizdeyiz. Eşyalarınız sigortalı olarak taşınacaktır. Asansörlü aracımız mevcuttur.', 
'pending', 
'İş başlangıcında %30 kapora, kalan tutar iş tesliminde nakit veya havale olarak alınır.', 
'Fiyatlarımıza KDV dahil değildir. Fatura istenirse +KDV eklenir. Kredi kartı ile ödeme imkanı mevcuttur.', 
'1. Taşıma sırasında oluşabilecek tüm hasarlar firmamız garantisi altındadır.\n2. Mobilyaların demontaj ve montaj işlemleri fiyata dahildir.\n3. Mutfak eşyaları ve kıyafetler özel kolilere paketlenecektir.\n4. Beyaz eşyaların tesisat bağlantıları yapılacaktır.', 
NOW());