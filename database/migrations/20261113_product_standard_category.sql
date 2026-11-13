CREATE TABLE IF NOT EXISTS `product_category` (
	`product_id` int UNSIGNED NOT NULL,
	`category_id` int UNSIGNED NOT NULL,
	PRIMARY KEY (`product_id`, `category_id`) USING BTREE,
	INDEX `idx_product_category_category` (`category_id`) USING BTREE,
	CONSTRAINT `fk_product_category_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
	CONSTRAINT `fk_product_category_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;

CREATE TABLE IF NOT EXISTS `standard_category` (
	`standard_number` varchar(100) NOT NULL,
	`category_id` int UNSIGNED NOT NULL,
	PRIMARY KEY (`standard_number`) USING BTREE,
	INDEX `idx_standard_category_category` (`category_id`) USING BTREE,
	CONSTRAINT `fk_standard_category_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE = InnoDB CHARACTER SET = utf8mb3 COLLATE = utf8mb3_general_ci ROW_FORMAT = Dynamic;
