<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$settings = $settings ?? array();
?>
<div class="settings-page">
	<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
		<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
		<button type="button" class="btn btn-primary btn-sm" id="settingsAddKeyBtn" data-mdb-ripple-init>
			<i class="fas fa-plus me-1"></i> <?php echo html_escape(app_line('settings_add_key')); ?>
		</button>
	</div>

	<div class="settings-layout app-panel">
		<aside class="settings-keys-panel border-end border-secondary border-opacity-25">
			<div class="settings-keys-head px-3 py-2 border-bottom border-secondary border-opacity-25 text-muted small">
				<?php echo html_escape(app_line('settings_keys')); ?>
			</div>
			<div class="list-group list-group-flush settings-keys-list" id="settingsKeysList" role="listbox">
				<?php if (empty($settings)): ?>
					<div class="text-muted small px-3 py-4" id="settingsKeysEmpty"><?php echo html_escape(app_line('settings_empty')); ?></div>
				<?php else: ?>
					<?php foreach ($settings as $item): ?>
						<button
							type="button"
							class="list-group-item list-group-item-action settings-key-item"
							data-key="<?php echo html_escape($item['key']); ?>"
						>
							<div class="settings-key-name fw-semibold"><?php echo html_escape($item['key']); ?></div>
							<div class="settings-key-desc text-muted small"><?php echo html_escape($item['description'] ?: '—'); ?></div>
						</button>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</aside>

		<section class="settings-editor-panel">
			<div id="settingsEditorEmpty" class="text-muted px-4 py-5 text-center">
				<?php echo html_escape(app_line('settings_select_prompt')); ?>
			</div>

			<div id="settingsEditor" class="d-none">
				<div class="px-4 pt-4 pb-2 border-bottom border-secondary border-opacity-25">
					<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
						<div>
							<div class="text-muted small mb-1"><?php echo html_escape(app_line('settings_key')); ?></div>
							<div class="h5 mb-0" id="settingsSelectedKey">—</div>
						</div>
						<div class="d-flex gap-2">
							<button type="button" class="btn btn-outline-danger btn-sm" id="settingsDeleteKeyBtn" data-mdb-ripple-init>
								<i class="fas fa-trash me-1"></i> <?php echo html_escape(app_line('common_delete')); ?>
							</button>
							<button type="button" class="btn btn-primary btn-sm" id="settingsSaveBtn" data-mdb-ripple-init>
								<i class="fas fa-save me-1"></i> <?php echo html_escape(app_line('common_save')); ?>
							</button>
						</div>
					</div>
					<div class="mb-3">
						<label class="form-label small text-muted" for="settingsDescriptionInput"><?php echo html_escape(app_line('settings_description')); ?></label>
						<input type="text" class="form-control form-control-sm" id="settingsDescriptionInput" maxlength="500">
					</div>
					<div id="settingsEditorAlert" class="alert d-none mb-0" role="alert"></div>
				</div>

				<div class="px-4 py-3">
					<div class="d-flex justify-content-between align-items-center mb-2">
						<div class="small text-muted"><?php echo html_escape(app_line('settings_values')); ?></div>
						<button type="button" class="btn btn-outline-secondary btn-sm" id="settingsAddPairBtn" data-mdb-ripple-init>
							<i class="fas fa-plus me-1"></i> <?php echo html_escape(app_line('settings_add_pair')); ?>
						</button>
					</div>
					<div class="table-responsive">
						<table class="table table-sm align-middle settings-pairs-table mb-0">
							<thead>
								<tr>
									<th style="width: 40%"><?php echo html_escape(app_line('settings_field')); ?></th>
									<th><?php echo html_escape(app_line('settings_value')); ?></th>
									<th style="width: 3.5rem"></th>
								</tr>
							</thead>
							<tbody id="settingsPairsBody"></tbody>
						</table>
					</div>
				</div>
			</div>
		</section>
	</div>
</div>

<script>
	window.SETTINGS_CONFIG = <?php echo json_encode(array(
		'listUrl'   => site_url('settings/list_items'),
		'getUrl'    => site_url('settings/get_item'),
		'saveUrl'   => site_url('settings/save_item'),
		'createUrl' => site_url('settings/create_item'),
		'deleteUrl' => site_url('settings/delete_item'),
	)); ?>;
</script>
