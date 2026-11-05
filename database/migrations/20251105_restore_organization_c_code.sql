ALTER TABLE `organizations`
	ADD COLUMN `c_code` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL AFTER `phone`;

ALTER TABLE `organization_archieve`
	ADD COLUMN `c_code` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL AFTER `phone`;
