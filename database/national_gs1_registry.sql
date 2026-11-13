/*
 Navicat Premium Dump SQL

 Source Server         : localhost
 Source Server Type    : MySQL
 Source Server Version : 80046 (8.0.46)
 Source Host           : localhost:3306
 Source Schema         : national_gs1_registry

 Target Server Type    : MySQL
 Target Server Version : 80046 (8.0.46)
 File Encoding         : 65001

 Date: 24/10/2026 05:03:23
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for account_permissions
-- ----------------------------
DROP TABLE IF EXISTS `account_permissions`;
CREATE TABLE `account_permissions`  (
  `account_id` int UNSIGNED NOT NULL,
  `permission` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`account_id`, `permission`) USING BTREE,
  CONSTRAINT `fk_account_permissions_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for accounts
-- ----------------------------
DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_superadmin` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `username`(`username` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for locations
-- ----------------------------
DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int UNSIGNED NULL DEFAULT NULL,
  `level` tinyint UNSIGNED NOT NULL DEFAULT 1,
  `sort_order` int UNSIGNED NOT NULL DEFAULT 0,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `tag` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_locations_parent`(`parent_id` ASC) USING BTREE,
  CONSTRAINT `fk_locations_parent` FOREIGN KEY (`parent_id`) REFERENCES `locations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for organization_archive
-- ----------------------------
DROP TABLE IF EXISTS `organization_archive`;
CREATE TABLE `organization_archive`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `reg_number` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `gs1_prefix` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `init_date` date NULL DEFAULT NULL,
  `reg_date` date NULL DEFAULT NULL,
  `expiry_date` date NULL DEFAULT NULL,
  `parent_org` varbinary(255) NULL DEFAULT NULL,
  `location_id` int NULL DEFAULT NULL,
  `address` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `phone` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `c_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `archived_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_organization_archive_archived`(`archived_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for organization_registration_items
-- ----------------------------
DROP TABLE IF EXISTS `organization_registration_items`;
CREATE TABLE `organization_registration_items`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_registration_id` int UNSIGNED NOT NULL,
  `org_registration_id` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `gs1_prefix` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `parent_organization_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_organization_registration_items_registration`(`organization_registration_id` ASC) USING BTREE,
  CONSTRAINT `fk_organization_registration_items_registration` FOREIGN KEY (`organization_registration_id`) REFERENCES `organization_registrations` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for organization_registrations
-- ----------------------------
DROP TABLE IF EXISTS `organization_registrations`;
CREATE TABLE `organization_registrations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `storage_path` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `procedure_number` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'uploaded',
  `account_id` int UNSIGNED NOT NULL,
  `total_items` int UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_organization_registrations_account`(`account_id` ASC) USING BTREE,
  INDEX `idx_organization_registrations_created`(`created_at` ASC) USING BTREE,
  CONSTRAINT `fk_organization_registrations_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for organizations
-- ----------------------------
DROP TABLE IF EXISTS `organizations`;
CREATE TABLE `organizations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `reg_number` int NULL DEFAULT NULL,
  `parent_org` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `location_id` int NULL DEFAULT NULL,
  `gs1_prefix` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `init_date` date NULL DEFAULT NULL,
  `reg_date` date NULL DEFAULT NULL,
  `expiry_date` date NULL DEFAULT NULL,
  `address` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `phone` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `c_code` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `currency` tinyint NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 51 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for product_archive
-- ----------------------------
DROP TABLE IF EXISTS `product_archive`;
CREATE TABLE `product_archive`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `standard_number` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `barcode` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `barcode_type` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `barcode_2d_value` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `barcode_2d_type` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `package_type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `organization_id` int UNSIGNED NULL DEFAULT NULL,
  `registration_date` date NULL DEFAULT NULL,
  `reregistration_date` date NULL DEFAULT NULL,
  `expiry_date` date NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `archived_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_product_archive_organization`(`organization_id` ASC) USING BTREE,
  INDEX `idx_product_archive_archived`(`archived_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for product_registration_items
-- ----------------------------
DROP TABLE IF EXISTS `product_registration_items`;
CREATE TABLE `product_registration_items`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_registration_id` int UNSIGNED NOT NULL,
  `product_procedure_number` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `info` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `status` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'pending',
  `message` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `barcode` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_product_registration_items_registration`(`product_registration_id` ASC) USING BTREE,
  CONSTRAINT `fk_product_registration_items_registration` FOREIGN KEY (`product_registration_id`) REFERENCES `product_registrations` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for product_registrations
-- ----------------------------
DROP TABLE IF EXISTS `product_registrations`;
CREATE TABLE `product_registrations`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `procedure_number` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `organization_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `account_id` int UNSIGNED NOT NULL,
  `status` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'uploaded',
  `total_products` int UNSIGNED NOT NULL DEFAULT 0,
  `approved` int UNSIGNED NOT NULL DEFAULT 0,
  `rejected` int UNSIGNED NOT NULL DEFAULT 0,
  `storage_path` varchar(500) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_product_registrations_account`(`account_id` ASC) USING BTREE,
  INDEX `idx_product_registrations_created`(`created_at` ASC) USING BTREE,
  CONSTRAINT `fk_product_registrations_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for products
-- ----------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `standard_number` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `brand` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `package_type` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `normalized_key` varchar(800) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `org_id` int UNSIGNED NULL DEFAULT NULL,
  `effect` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `usage` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `price` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `effective_period` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `test_date` date NULL DEFAULT NULL,
  `test_status` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `barcode_1d` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `barcode_1d_type` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `barcode_2d` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `barcode_2d_type` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `reg_date` date NULL DEFAULT NULL,
  `processor_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `global_id` bigint NULL DEFAULT NULL,
  `c_code` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_products_organization`(`org_id` ASC) USING BTREE,
  INDEX `idx_products_normalized_key`(`normalized_key` ASC) USING BTREE,
  CONSTRAINT `fk_products_organization` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1001 CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

CREATE TABLE IF NOT EXISTS `source_file_history` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `relative_path` varchar(500) NOT NULL,
  `backup_path` varchar(500) NOT NULL,
  `uploaded_name` varchar(255) NOT NULL,
  `account_id` int UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_source_file_history_path` (`relative_path`(191)) USING BTREE,
  CONSTRAINT `fk_source_file_history_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

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

CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(100) NOT NULL,
  `description` varchar(500) NULL DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `products_archieve` LIKE `products`;

ALTER TABLE `products_archieve`
	ADD COLUMN `origin_id` INT UNSIGNED NOT NULL AFTER `id`,
	ADD COLUMN `deleted_at` DATETIME NOT NULL AFTER `updated_at`,
	ADD INDEX `idx_products_archieve_origin` (`origin_id`),
	ADD INDEX `idx_products_archieve_deleted` (`deleted_at`);

CREATE TABLE IF NOT EXISTS `organization_archieve` LIKE `organizations`;

ALTER TABLE `organization_archieve`
	ADD COLUMN `origin_id` INT UNSIGNED NOT NULL AFTER `id`,
	ADD COLUMN `deleted_at` DATETIME NOT NULL AFTER `updated_at`,
	ADD INDEX `idx_organization_archieve_origin` (`origin_id`),
	ADD INDEX `idx_organization_archieve_deleted` (`deleted_at`);

-- ----------------------------
-- Table structure for categories
-- GS1 GPC hierarchy: 1 segment, 2 family, 3 class, 4 brick
-- ----------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories`  (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int UNSIGNED NULL DEFAULT NULL,
  `gpc_code` char(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `level` tinyint UNSIGNED NOT NULL,
  `sort_order` int UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_categories_gpc_code`(`gpc_code` ASC) USING BTREE,
  INDEX `idx_categories_parent`(`parent_id` ASC) USING BTREE,
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
