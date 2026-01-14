USE `hizmet_crm`;

CREATE TABLE `menu_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `menu_location` varchar(50) NOT NULL COMMENT 'e.g., header, footer',
  `title` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `visibility` enum('all','guest','customer','provider') NOT NULL DEFAULT 'all' COMMENT 'all=herkes, guest=misafir, customer=müşteri, provider=hizmet veren',
  `target` varchar(20) DEFAULT '_self',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan Menü Linkleri
INSERT INTO `menu_items` (`menu_location`, `title`, `url`, `visibility`, `sort_order`) VALUES
('header', 'Hizmetleri Keşfet', 'index.php', 'all', 10),
('header', 'Hizmet Veren Ol', 'provider/apply.php', 'guest', 20),
('header', 'Nasıl Çalışır?', 'nasil-calisir.php', 'all', 30),
('header', 'Taleplerim', 'my-demands.php', 'customer', 20),
('header', 'İş Fırsatları', 'provider/leads.php', 'provider', 20),
('footer', 'Hakkımızda', '#', 'all', 10),
('footer', 'Kullanım Koşulları', '#', 'all', 20),
('footer', 'Gizlilik Politikası', '#', 'all', 30),
('footer', 'İletişim', '#', 'all', 40);