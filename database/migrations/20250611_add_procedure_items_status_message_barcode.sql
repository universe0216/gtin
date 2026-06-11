ALTER TABLE `procedure_items`
	ADD COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER `info`,
	ADD COLUMN `message` TEXT NULL AFTER `status`,
	ADD COLUMN `barcode` VARCHAR(100) NULL AFTER `message`;
