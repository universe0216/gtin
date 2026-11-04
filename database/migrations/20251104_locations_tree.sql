ALTER TABLE `locations`
	ADD COLUMN `parent_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
	ADD COLUMN `level` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `parent_id`,
	ADD INDEX `idx_locations_parent` (`parent_id`),
	ADD CONSTRAINT `fk_locations_parent` FOREIGN KEY (`parent_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
