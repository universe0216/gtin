-- GTIN initial schema for database: goods_schema

CREATE DATABASE IF NOT EXISTS `goods_schema` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `goods_schema`;

CREATE TABLE IF NOT EXISTS `primaries` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `products` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `organizations` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`description` TEXT NULL,
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL,
	PRIMARY KEY (`id`)
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
