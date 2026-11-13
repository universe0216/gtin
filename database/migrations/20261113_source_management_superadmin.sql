ALTER TABLE `accounts`
	ADD COLUMN `is_superadmin` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_admin`;

-- Promote the first admin account to the single superadmin (idempotent if already set later).
UPDATE `accounts`
SET `is_superadmin` = 1, `is_admin` = 1
WHERE `id` = (
	SELECT `id` FROM (
		SELECT MIN(`id`) AS `id` FROM `accounts` WHERE `is_admin` = 1
	) AS `first_admin`
)
AND NOT EXISTS (
	SELECT 1 FROM (
		SELECT `id` FROM `accounts` WHERE `is_superadmin` = 1 LIMIT 1
	) AS `existing_superadmin`
);

CREATE TABLE IF NOT EXISTS `source_file_history` (
	`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
	`relative_path` varchar(500) NOT NULL,
	`backup_path` varchar(500) NOT NULL,
	`uploaded_name` varchar(255) NOT NULL,
	`account_id` int UNSIGNED NOT NULL,
	`created_at` datetime NOT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `idx_source_file_history_path` (`relative_path`(191)) USING BTREE,
	CONSTRAINT `fk_source_file_history_account`
		FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
		ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci;
