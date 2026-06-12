-- Test seed: 50 companies + 20 products each (1000 products)
-- GS1 prefix: 9 digits (600000001 - 600000050)

USE `national_gs1_registry`;

DELIMITER $$

DROP PROCEDURE IF EXISTS `seed_test_companies_products`$$
CREATE PROCEDURE `seed_test_companies_products`()
BEGIN
	DECLARE org_i INT DEFAULT 1;
	DECLARE prod_i INT DEFAULT 1;
	DECLARE org_id INT UNSIGNED;
	DECLARE gs1_prefix CHAR(9);
	DECLARE now_dt DATETIME DEFAULT NOW();
	DECLARE barcode_value VARCHAR(100);

	WHILE org_i <= 50 DO
		SET gs1_prefix = LPAD(600000000 + org_i, 9, '0');

		INSERT INTO `organizations` (
			`name`,
			`registration_number`,
			`gs1_prefix`,
			`registration_date`,
			`reregistration_date`,
			`expiry_date`,
			`address`,
			`procedure_number`,
			`description`,
			`created_at`,
			`updated_at`
		) VALUES (
			CONCAT('Test Company ', org_i),
			CONCAT('REG-', LPAD(org_i, 5, '0')),
			gs1_prefix,
			DATE_SUB(CURDATE(), INTERVAL 365 DAY),
			CURDATE(),
			DATE_ADD(CURDATE(), INTERVAL 730 DAY),
			CONCAT(org_i, ' Test Street, Ulaanbaatar'),
			CONCAT('10', LPAD(org_i, 3, '0')),
			CONCAT('Test organization ', org_i),
			now_dt,
			now_dt
		);

		SET org_id = LAST_INSERT_ID();
		SET prod_i = 1;

		WHILE prod_i <= 20 DO
			SET barcode_value = CONCAT(gs1_prefix, LPAD(prod_i, 4, '0'));

			INSERT INTO `products` (
				`name`,
				`standard_number`,
				`barcode`,
				`barcode_type`,
				`barcode_2d_value`,
				`barcode_2d_type`,
				`package_type`,
				`organization_id`,
				`registration_date`,
				`reregistration_date`,
				`expiry_date`,
				`description`,
				`created_at`,
				`updated_at`
			) VALUES (
				CONCAT('Test Product ', org_i, '-', LPAD(prod_i, 2, '0')),
				CONCAT('STD-', org_i, '-', LPAD(prod_i, 3, '0')),
				barcode_value,
				'EAN-13',
				CONCAT('QR:', barcode_value),
				'QR',
				'Unit',
				org_id,
				DATE_SUB(CURDATE(), INTERVAL 180 DAY),
				CURDATE(),
				DATE_ADD(CURDATE(), INTERVAL 365 DAY),
				CONCAT('Test product ', prod_i, ' for company ', org_i),
				now_dt,
				now_dt
			);

			SET prod_i = prod_i + 1;
		END WHILE;

		SET org_i = org_i + 1;
	END WHILE;
END$$

DELIMITER ;

CALL `seed_test_companies_products`();
DROP PROCEDURE IF EXISTS `seed_test_companies_products`;
