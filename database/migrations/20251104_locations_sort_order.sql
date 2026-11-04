ALTER TABLE `locations`
	ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `level`;

UPDATE `locations` AS locations
JOIN (
	SELECT id, ROW_NUMBER() OVER (PARTITION BY parent_id ORDER BY name, id) AS ord
	FROM `locations`
) ranked ON ranked.id = locations.id
SET locations.sort_order = ranked.ord;

UPDATE `locations` AS locations
JOIN (
	WITH RECURSIVE paths AS (
		SELECT id, CAST(id AS CHAR(50)) AS tag_path
		FROM `locations`
		WHERE parent_id IS NULL
		UNION ALL
		SELECT child.id, CONCAT(paths.tag_path, '.', child.id)
		FROM `locations` child
		INNER JOIN paths ON child.parent_id = paths.id
	)
	SELECT id, tag_path FROM paths
) tagged ON tagged.id = locations.id
SET locations.tag = tagged.tag_path;
