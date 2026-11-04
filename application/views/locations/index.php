<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$can_edit = ! empty($can_edit);
?>
<div class="entity-list-page" id="locationsPage">
	<div class="entity-list-page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-1"><?php echo html_escape($title); ?></h1>
			<p class="text-muted small mb-0"><?php echo html_escape(app_line('location_hierarchy')); ?></p>
		</div>
		<?php if ($can_edit): ?>
			<button type="button" class="btn btn-sm btn-primary" id="locationAddProvinceBtn" data-mdb-ripple-init>
				<i class="fas fa-plus me-1"></i> <?php echo html_escape(app_line('location_add_province')); ?>
			</button>
		<?php endif; ?>
	</div>

	<div class="app-panel location-tree-panel">
		<div class="location-tree-toolbar">
			<div class="input-group input-group-sm">
				<span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
				<input
					type="search"
					class="form-control"
					id="locationSearchInput"
					placeholder="<?php echo html_escape(app_line('location_search')); ?>"
					autocomplete="off"
					aria-label="<?php echo html_escape(app_line('location_search')); ?>"
				>
			</div>
		</div>
		<div id="locationTree" class="location-tree"></div>
		<p class="text-muted small mb-0 d-none" id="locationTreeEmpty"></p>
	</div>
</div>

<?php if ($can_edit): ?>
<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="locationModalLabel"><?php echo html_escape(app_line('location_add_province')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<form id="locationForm">
				<div class="modal-body">
					<input type="hidden" name="record_id" id="locationRecordId" value="">
					<input type="hidden" name="parent_id" id="locationParentId" value="">
					<div id="locationModalAlert" class="alert alert-danger d-none" role="alert"></div>
					<p class="text-muted small d-none" id="locationParentHint"></p>
					<div class="mb-0">
						<label class="form-label" for="locationName"><?php echo html_escape(app_line('col_name')); ?></label>
						<input type="text" class="form-control" id="locationName" name="name" required maxlength="255">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-mdb-dismiss="modal"><?php echo html_escape(app_line('common_cancel')); ?></button>
					<button type="submit" class="btn btn-primary" id="locationSaveBtn">
						<span class="save-label"><?php echo html_escape(app_line('common_save')); ?></span>
						<span class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

<script>
	window.LOCATIONS_CONFIG = <?php echo json_encode(array(
		'baseUrl' => site_url('locations'),
		'canEdit' => $can_edit,
		'records' => $records,
	)); ?>;
</script>
