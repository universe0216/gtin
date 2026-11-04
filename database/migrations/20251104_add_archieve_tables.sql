CREATE TABLE IF NOT EXISTS `products_archieve` LIKE `products`;

ALTER TABLE `products_archieve`
	ADD COLUMN `origin_id` INT UNSIGNED NOT NULL AFTER `id`,
	ADD COLUMN `deleted_at` DATETIME NOT NULL AFTER `updated_at`,
	ADD INDEX `idx_products_archieve_origin` (`origin_id`),
	ADD INDEX `idx_products_archieve_deleted` (`deleted_at`);

CREATE TABLE IF NOT EXISTS `organization_archieve` LIKE `organizations`;

ALTER TABLE `organization_archieve`
	ADD COLUMN `origin_id` INT UNSIGNED NOT NULL AFTER `id`,
	ADD COLUMN `deleted_at` DATETIME NOT NULL AFTER `updated_at`,
	ADD INDEX `idx_organization_archieve_origin` (`origin_id`),
	ADD INDEX `idx_organization_archieve_deleted` (`deleted_at`);
