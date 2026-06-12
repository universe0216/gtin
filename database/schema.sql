-- GTIN initial schema for database: national_gs1_registry

CREATE DATABASE IF NOT EXISTS `national_gs1_registry` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `national_gs1_registry`;



CREATE TABLE IF NOT EXISTS `products` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`standard_number` VARCHAR(100) NULL,
	`barcode` VARCHAR(100) NULL,
	`barcode_type` VARCHAR(50) NULL,
	`barcode_2d_value` VARCHAR(255) NULL,
	`barcode_2d_type` VARCHAR(50) NULL,
	`package_type` VARCHAR(100) NULL,
	`organization_id` INT UNSIGNED NULL,
	`registration_date` DATE NULL,
	`reregistration_date` DATE NULL,
	`expiry_date` DATE NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_products_organization` (`organization_id`),
	CONSTRAINT `fk_products_organization`
		FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `organizations` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`registration_number` VARCHAR(100) NULL,
	`gs1_prefix` VARCHAR(50) NULL,
	`registration_date` DATE NULL,
	`reregistration_date` DATE NULL,
	`expiry_date` DATE NULL,
	`address` TEXT NULL,
	`procedure_number` VARCHAR(50) NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `organization_archive` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`registration_number` VARCHAR(100) NULL,
	`gs1_prefix` VARCHAR(50) NULL,
	`registration_date` DATE NULL,
	`reregistration_date` DATE NULL,
	`expiry_date` DATE NULL,
	`address` TEXT NULL,
	`procedure_number` VARCHAR(50) NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	`archived_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_organization_archive_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `product_archive` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`standard_number` VARCHAR(100) NULL,
	`barcode` VARCHAR(100) NULL,
	`barcode_type` VARCHAR(50) NULL,
	`barcode_2d_value` VARCHAR(255) NULL,
	`barcode_2d_type` VARCHAR(50) NULL,
	`package_type` VARCHAR(100) NULL,
	`organization_id` INT UNSIGNED NULL,
	`registration_date` DATE NULL,
	`reregistration_date` DATE NULL,
	`expiry_date` DATE NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	`archived_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_product_archive_organization` (`organization_id`),
	KEY `idx_product_archive_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `organization_registrations` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`file_name` VARCHAR(255) NOT NULL,
	`storage_path` VARCHAR(500) NOT NULL,
	`procedure_number` VARCHAR(50) NOT NULL,
	`status` VARCHAR(50) NOT NULL DEFAULT 'uploaded',
	`account_id` INT UNSIGNED NOT NULL,
	`total_items` INT UNSIGNED NOT NULL DEFAULT 0,
	`created_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_organization_registrations_account` (`account_id`),
	KEY `idx_organization_registrations_created` (`created_at`),
	CONSTRAINT `fk_organization_registrations_account`
		FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `organization_registration_items` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`organization_registration_id` INT UNSIGNED NOT NULL,
	`org_registration_id` VARCHAR(100) NOT NULL,
	`gs1_prefix` VARCHAR(50) NULL,
	`name` VARCHAR(255) NOT NULL,
	`parent_organization_name` VARCHAR(255) NULL,
	`created_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_organization_registration_items_registration` (`organization_registration_id`),
	CONSTRAINT `fk_organization_registration_items_registration`
		FOREIGN KEY (`organization_registration_id`) REFERENCES `organization_registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `users` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `locations` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `accounts` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`username` VARCHAR(100) NOT NULL,
	`password` VARCHAR(255) NOT NULL,
	`full_name` VARCHAR(255) NOT NULL,
	`is_admin` TINYINT(1) NOT NULL DEFAULT 0,
	`is_active` TINYINT(1) NOT NULL DEFAULT 1,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `account_permissions` (
	`account_id` INT UNSIGNED NOT NULL,
	`permission` VARCHAR(50) NOT NULL,
	PRIMARY KEY (`account_id`, `permission`),
	CONSTRAINT `fk_account_permissions_account`
		FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `product_registrations` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`file_name` VARCHAR(255) NOT NULL,
	`procedure_number` VARCHAR(50) NOT NULL,
	`organization_name` VARCHAR(255) NOT NULL,
	`account_id` INT UNSIGNED NOT NULL,
	`status` VARCHAR(50) NOT NULL DEFAULT 'uploaded',
	`total_products` INT UNSIGNED NOT NULL DEFAULT 0,
	`approved` INT UNSIGNED NOT NULL DEFAULT 0,
	`rejected` INT UNSIGNED NOT NULL DEFAULT 0,
	`storage_path` VARCHAR(500) NOT NULL,
	`created_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_product_registrations_account` (`account_id`),
	KEY `idx_product_registrations_created` (`created_at`),
	CONSTRAINT `fk_product_registrations_account`
		FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `product_registration_items` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`product_registration_id` INT UNSIGNED NOT NULL,
	`product_procedure_number` VARCHAR(100) NOT NULL,
	`name` VARCHAR(255) NOT NULL,
	`info` TEXT NULL,
	`status` VARCHAR(50) NOT NULL DEFAULT 'pending',
	`message` TEXT NULL,
	`barcode` VARCHAR(100) NULL,
	`created_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_product_registration_items_registration` (`product_registration_id`),
	CONSTRAINT `fk_product_registration_items_registration`
		FOREIGN KEY (`product_registration_id`) REFERENCES `product_registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `accounts` (`username`, `password`, `full_name`, `is_admin`, `is_active`, `created_at`, `updated_at`)
VALUES (
	'admin',
	'$2y$10$EfXihrFrV8OYhFqsBDRXE.eAxYrNTakmhhMuhm0olGFbjLMXk9NfS',
	'Administrator',
	1,
	1,
	NOW(),
	NOW()
) ON DUPLICATE KEY UPDATE `username` = `username`;
