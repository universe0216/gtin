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
