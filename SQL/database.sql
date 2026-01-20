SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Veritabanını Oluştur ve Seç
-- CREATE DATABASE IF NOT EXISTS `hizmet_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Eski Tabloları Temizle (Sıralama Önemli)
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

-- 3. Tabloları Oluştur

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

SET FOREIGN_KEY_CHECKS = 1;
