USE `hizmet_crm`;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `offer_id` int(11) unsigned NOT NULL,
  `reviewer_id` int(11) unsigned NOT NULL, -- Değerlendiren (Provider)
  `reviewed_id` int(11) unsigned NOT NULL, -- Değerlendirilen (Customer)
  `rating` decimal(2,1) NOT NULL, -- Genel Puan
  `criteria_ratings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`criteria_ratings`)), -- Detaylı Puanlar (JSON)
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;