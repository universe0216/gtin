ALTER TABLE `products`
	ADD COLUMN `normalized_key` VARCHAR(800) NULL DEFAULT NULL AFTER `package_type`,
	ADD INDEX `idx_products_normalized_key` (`normalized_key`);

ALTER TABLE `products_archieve`
	ADD COLUMN `normalized_key` VARCHAR(800) NULL DEFAULT NULL AFTER `package_type`,
	ADD INDEX `idx_products_archieve_normalized_key` (`normalized_key`);
