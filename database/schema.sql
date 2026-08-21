-- SYNCR Real Estate Inventory Management Portal
-- MySQL / MariaDB schema (utf8mb4)

CREATE DATABASE IF NOT EXISTS `syncr_inventory`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `syncr_inventory`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `schema_change_logs`;
DROP TABLE IF EXISTS `mail_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `api_tokens`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `block_requests`;
DROP TABLE IF EXISTS `unit_images`;
DROP TABLE IF EXISTS `inventory_units`;
DROP TABLE IF EXISTS `project_images`;
DROP TABLE IF EXISTS `user_project_assignments`;
DROP TABLE IF EXISTS `company_project_assignments`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `marketing_companies`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `customers`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `is_secret` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `marketing_companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(100) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `permissions` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NULL,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `avatar` VARCHAR(255) NULL,
  `role` ENUM('promoter_admin','marketing_team_admin','marketing_team_user') NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_company` (`company_id`),
  KEY `idx_users_role` (`role`),
  CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `marketing_companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `location` VARCHAR(150) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `project_type` VARCHAR(80) NOT NULL DEFAULT 'Residential Plot',
  `description` TEXT NULL,
  `approval_details` VARCHAR(150) NULL,
  `contact_name` VARCHAR(120) NULL,
  `contact_phone` VARCHAR(30) NULL,
  `contact_email` VARCHAR(150) NULL,
  `cover_image` VARCHAR(255) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_images_project` (`project_id`),
  CONSTRAINT `fk_project_images_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_project_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_project` (`company_id`,`project_id`),
  KEY `idx_cpa_project` (`project_id`),
  CONSTRAINT `fk_cpa_company` FOREIGN KEY (`company_id`) REFERENCES `marketing_companies` (`id`),
  CONSTRAINT `fk_cpa_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_project_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_project` (`user_id`,`project_id`),
  KEY `idx_upa_project` (`project_id`),
  CONSTRAINT `fk_upa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_upa_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_units` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `unit_no` VARCHAR(50) NOT NULL,
  `block_phase` VARCHAR(80) NULL,
  `plot_type` VARCHAR(80) NOT NULL DEFAULT 'Residential Plot',
  `area_sqft` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `facing` VARCHAR(40) NULL,
  `road_width_ft` DECIMAL(8,2) NULL,
  `dimensions` VARCHAR(80) NULL,
  `price` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `price_per_sqft` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `is_premium` TINYINT(1) NOT NULL DEFAULT 0,
  `is_corner` TINYINT(1) NOT NULL DEFAULT 0,
  `approval_details` VARCHAR(150) NULL,
  `remarks` TEXT NULL,
  `status` ENUM('available','on_hold','booked','registered') NOT NULL DEFAULT 'available',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_project_unit` (`project_id`,`unit_no`),
  KEY `idx_units_status` (`status`),
  CONSTRAINT `fk_units_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `unit_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_unit_images_unit` (`unit_id`),
  CONSTRAINT `fk_unit_images_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(150) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `block_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `customer_email` VARCHAR(150) NULL,
  `expected_booking_date` DATE NULL,
  `remarks` TEXT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `review_notes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_requests_status` (`status`),
  KEY `idx_requests_company` (`company_id`),
  CONSTRAINT `fk_req_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`),
  CONSTRAINT `fk_req_company` FOREIGN KEY (`company_id`) REFERENCES `marketing_companies` (`id`),
  CONSTRAINT `fk_req_user` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `customer_phone` VARCHAR(30) NULL,
  `customer_email` VARCHAR(150) NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `booking_date` DATE NOT NULL,
  `status` ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bookings_project` (`project_id`),
  KEY `idx_bookings_company` (`company_id`),
  KEY `idx_bookings_date` (`booking_date`),
  CONSTRAINT `fk_book_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`),
  CONSTRAINT `fk_book_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `registrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NULL,
  `booking_id` INT UNSIGNED NULL,
  `customer_id` INT UNSIGNED NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `customer_phone` VARCHAR(30) NULL,
  `customer_email` VARCHAR(150) NULL,
  `amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `registration_date` DATE NOT NULL,
  `status` ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reg_project` (`project_id`),
  KEY `idx_reg_company` (`company_id`),
  KEY `idx_reg_date` (`registration_date`),
  CONSTRAINT `fk_reg_unit` FOREIGN KEY (`unit_id`) REFERENCES `inventory_units` (`id`),
  CONSTRAINT `fk_reg_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `company_id` INT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(80) NULL,
  `entity_id` INT UNSIGNED NULL,
  `description` VARCHAR(500) NOT NULL,
  `meta` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_created` (`created_at`),
  KEY `idx_activity_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(80) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_token_user` (`user_id`),
  CONSTRAINT `fk_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(80) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mail_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `to_email` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `body` TEXT NULL,
  `event` VARCHAR(80) NULL,
  `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `error_message` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schema_change_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `table_name` VARCHAR(100) NOT NULL,
  `operation` VARCHAR(40) NOT NULL,
  `sql_text` TEXT NOT NULL,
  `status` ENUM('success','blocked','failed') NOT NULL,
  `message` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
