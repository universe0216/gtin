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
