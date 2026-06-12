RENAME TABLE `org_registrations` TO `organization_registrations`;

ALTER TABLE `organization_registrations`
	DROP FOREIGN KEY `fk_org_registrations_account`,
	DROP KEY `idx_org_registrations_account`,
	DROP KEY `idx_org_registrations_created`,
	ADD KEY `idx_organization_registrations_account` (`account_id`),
	ADD KEY `idx_organization_registrations_created` (`created_at`),
	ADD CONSTRAINT `fk_organization_registrations_account`
		FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);

RENAME TABLE `org_registration_items` TO `organization_registration_items`;

ALTER TABLE `organization_registration_items`
	CHANGE COLUMN `registration_id` `organization_registration_id` INT UNSIGNED NOT NULL,
	DROP FOREIGN KEY `fk_org_registration_items_registration`,
	DROP KEY `idx_org_registration_items_registration`,
	ADD KEY `idx_organization_registration_items_registration` (`organization_registration_id`),
	ADD CONSTRAINT `fk_organization_registration_items_registration`
		FOREIGN KEY (`organization_registration_id`) REFERENCES `organization_registrations` (`id`) ON DELETE CASCADE;
