ALTER TABLE `organizations`
	ADD COLUMN `currency` TINYINT NULL DEFAULT NULL AFTER `c_code`;

ALTER TABLE `organization_archieve`
	ADD COLUMN `currency` TINYINT NULL DEFAULT NULL AFTER `c_code`;
