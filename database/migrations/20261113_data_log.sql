CREATE TABLE IF NOT EXISTS `data_log` (
	`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
	`entity` varchar(50) NOT NULL COMMENT 'product, organization, ...',
	`entity_id` int UNSIGNED NOT NULL,
	`action` varchar(20) NOT NULL COMMENT 'create, update, archive',
	`original_data` longtext NULL COMMENT 'JSON whole row before change',
	`new_data` longtext NULL COMMENT 'JSON whole row after change',
	`account_id` int UNSIGNED NULL DEFAULT NULL,
	`created_at` datetime NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_data_log_entity` (`entity`, `entity_id`) USING BTREE,
	INDEX `idx_data_log_action` (`action`) USING BTREE,
	INDEX `idx_data_log_created` (`created_at`) USING BTREE,
	INDEX `idx_data_log_account` (`account_id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;
