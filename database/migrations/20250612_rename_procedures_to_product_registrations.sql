RENAME TABLE `procedures` TO `product_registrations`;

ALTER TABLE `product_registrations`
	DROP FOREIGN KEY `fk_procedures_account`,
	DROP KEY `idx_procedures_account`,
	DROP KEY `idx_procedures_created`,
	ADD KEY `idx_product_registrations_account` (`account_id`),
	ADD KEY `idx_product_registrations_created` (`created_at`),
	ADD CONSTRAINT `fk_product_registrations_account`
		FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

RENAME TABLE `procedure_items` TO `product_registration_items`;

ALTER TABLE `product_registration_items`
	CHANGE COLUMN `procedure_id` `product_registration_id` INT UNSIGNED NOT NULL,
	DROP FOREIGN KEY `fk_procedure_items_procedure`,
	DROP KEY `idx_procedure_items_procedure`,
	ADD KEY `idx_product_registration_items_registration` (`product_registration_id`),
	ADD CONSTRAINT `fk_product_registration_items_registration`
		FOREIGN KEY (`product_registration_id`) REFERENCES `product_registrations` (`id`) ON DELETE CASCADE;
