ALTER TABLE `organizations`
	ADD COLUMN `registration_number` VARCHAR(100) NULL AFTER `name`,
	ADD COLUMN `gs1_prefix` VARCHAR(50) NULL AFTER `registration_number`,
	ADD COLUMN `registration_date` DATE NULL AFTER `gs1_prefix`,
	ADD COLUMN `reregistration_date` DATE NULL AFTER `registration_date`,
	ADD COLUMN `expiry_date` DATE NULL AFTER `reregistration_date`,
	ADD COLUMN `address` TEXT NULL AFTER `expiry_date`,
	ADD COLUMN `procedure_number` VARCHAR(50) NULL AFTER `address`;
