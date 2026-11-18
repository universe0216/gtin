ALTER TABLE `product_registrations`
	DROP FOREIGN KEY `fk_product_registrations_account`,
	MODIFY `account_id` INT UNSIGNED NULL DEFAULT NULL,
	ADD CONSTRAINT `fk_product_registrations_account`
		FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
