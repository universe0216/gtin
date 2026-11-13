CREATE TABLE IF NOT EXISTS `settings` (
	`key` varchar(100) NOT NULL,
	`settings` longtext NULL,
	PRIMARY KEY (`key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

CREATE TABLE IF NOT EXISTS `export_history` (
	`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
	`type` varchar(20) NOT NULL COMMENT 'full, patch',
	`status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, running, completed, failed',
	`file_count` int UNSIGNED NOT NULL DEFAULT 0,
	`company_count` int UNSIGNED NOT NULL DEFAULT 0,
	`product_count` int UNSIGNED NOT NULL DEFAULT 0,
	`storage_path` varchar(500) NULL DEFAULT NULL,
	`zip_name` varchar(255) NULL DEFAULT NULL,
	`since_at` datetime NULL DEFAULT NULL COMMENT 'Patch baseline (last completed export time)',
	`account_id` int UNSIGNED NULL DEFAULT NULL,
	`error_message` text NULL,
	`meta` longtext NULL COMMENT 'JSON progress/state',
	`started_at` datetime NOT NULL,
	`finished_at` datetime NULL DEFAULT NULL,
	`created_at` datetime NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_export_history_type` (`type`) USING BTREE,
	INDEX `idx_export_history_status` (`status`) USING BTREE,
	INDEX `idx_export_history_created` (`created_at`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

INSERT INTO `settings` (`key`, `settings`)
VALUES
	(
		'orgn_export_mapper',
		'{"id":"id","name":"name","reg_number":"reg_number","parent_org":"parent_org","location_id":"location_id","gs1_prefix":"gs1_prefix","init_date":"init_date","reg_date":"reg_date","expiry_date":"expiry_date","address":"address","phone":"phone","c_code":"c_code","currency":"currency","description":"description","created_at":"created_at","updated_at":"updated_at"}'
	),
	(
		'proudct_export_mapper',
		'{"id":"id","name":"name","standard_number":"standard_number","brand":"brand","package_type":"package_type","normalized_key":"normalized_key","org_id":"org_id","effect":"effect","usage":"usage","price":"price","effective_period":"effective_period","test_date":"test_date","test_status":"test_status","description":"description","barcode_1d":"barcode_1d","barcode_1d_type":"barcode_1d_type","barcode_2d":"barcode_2d","barcode_2d_type":"barcode_2d_type","reg_date":"reg_date","processor_name":"processor_name","global_id":"global_id","c_code":"c_code","created_at":"created_at","updated_at":"updated_at"}'
	)
ON DUPLICATE KEY UPDATE `settings` = VALUES(`settings`);
