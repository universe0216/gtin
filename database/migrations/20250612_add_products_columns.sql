ALTER TABLE `products`
	ADD COLUMN `standard_number` VARCHAR(100) NULL AFTER `name`,
	ADD COLUMN `barcode` VARCHAR(100) NULL AFTER `standard_number`,
	ADD COLUMN `barcode_type` VARCHAR(50) NULL AFTER `barcode`,
	ADD COLUMN `barcode_2d_value` VARCHAR(255) NULL AFTER `barcode_type`,
	ADD COLUMN `barcode_2d_type` VARCHAR(50) NULL AFTER `barcode_2d_value`,
	ADD COLUMN `package_type` VARCHAR(100) NULL AFTER `barcode_2d_type`,
	ADD COLUMN `organization_id` INT UNSIGNED NULL AFTER `package_type`,
	ADD COLUMN `registration_date` DATE NULL AFTER `organization_id`,
	ADD COLUMN `reregistration_date` DATE NULL AFTER `registration_date`,
	ADD COLUMN `expiry_date` DATE NULL AFTER `reregistration_date`,
	ADD KEY `idx_products_organization` (`organization_id`),
	ADD CONSTRAINT `fk_products_organization`
		FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL;
