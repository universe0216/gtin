INSERT INTO `settings` (`key`, `description`, `settings`)
SELECT
	'prod_pub_json_mapper',
	'Product download JSON. Field is the JSON key, value is the products column.',
	'{"ProductName":"name","TradeMark":"brand","FactoryName":"organization_name","Norm":"standard_number","PackingType":"package_type","KeepDate":"effective_period","Barcode_1D_RegNumber":"barcode_1d","Barcode_2D_Info":"barcode_2d","Barcode_2D_Category":"barcode_2d_type"}'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'prod_pub_json_mapper');

INSERT INTO `settings` (`key`, `description`, `settings`)
SELECT
	'org_pub_json_mapper',
	'Organization download JSON. Field is the JSON key, value is the organizations column.',
	'{"Org_Name":"name","Prefix":"gs1_prefix"}'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key` = 'org_pub_json_mapper');
