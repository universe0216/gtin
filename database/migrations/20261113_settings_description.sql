ALTER TABLE `settings`
	ADD COLUMN `description` varchar(500) NULL DEFAULT NULL AFTER `key`;

UPDATE `settings`
SET `description` = 'Organization export field mapper (DB column => export key).'
WHERE `key` = 'orgn_export_mapper';

UPDATE `settings`
SET `description` = 'Product export field mapper (DB column => export key).'
WHERE `key` = 'proudct_export_mapper';
