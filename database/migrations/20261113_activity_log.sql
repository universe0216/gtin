CREATE TABLE IF NOT EXISTS `activity_log` (
	`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
	`account_id` int UNSIGNED NULL DEFAULT NULL,
	`action` varchar(20) NOT NULL COMMENT 'visit, api',
	`method` varchar(10) NOT NULL,
	`path` varchar(500) NOT NULL,
	`controller` varchar(100) NULL DEFAULT NULL,
	`method_name` varchar(100) NULL DEFAULT NULL,
	`ip_address` varchar(45) NOT NULL,
	`user_agent` varchar(500) NULL DEFAULT NULL,
	`request_data` longtext NULL COMMENT 'JSON request payload (sanitized)',
	`created_at` datetime NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_activity_log_account` (`account_id`) USING BTREE,
	INDEX `idx_activity_log_action` (`action`) USING BTREE,
	INDEX `idx_activity_log_path` (`path`(191)) USING BTREE,
	INDEX `idx_activity_log_ip` (`ip_address`) USING BTREE,
	INDEX `idx_activity_log_created` (`created_at`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;
