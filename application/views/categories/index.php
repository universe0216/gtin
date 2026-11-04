<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$can_edit = ! empty($can_edit);
?>
<div class="entity-list-page" id="categoriesPage">
	<div class="entity-list-page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-1"><?php echo html_escape($title); ?></h1>
			<p class="text-muted small mb-0"><?php echo html_escape(app_line('category_hierarchy')); ?></p>
		</div>
	</div>

	<div class="app-panel location-tree-panel">
		<div class="location-tree-toolbar">
			<div class="input-group input-group-sm">
				<span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
				<input
					type="search"
					class="form-control"
					id="categorySearchInput"
					placeholder="<?php echo html_escape(app_line('category_search')); ?>"
					autocomplete="off"
					aria-label="<?php echo html_escape(app_line('category_search')); ?>"
				>
			</div>
		</div>
		<div id="categoryTree" class="category-tree"></div>
		<p class="text-muted small mb-0 d-none" id="categoryTreeEmpty"></p>
	</div>
</div>

<?php if ($can_edit): ?>
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="categoryModalLabel"><?php echo html_escape(app_line('common_edit')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<form id="categoryForm">
				<div class="modal-body">
					<input type="hidden" name="record_id" id="categoryRecordId" value="">
					<div id="categoryModalAlert" class="alert alert-danger d-none" role="alert"></div>
					<div class="mb-3">
						<label class="form-label"><?php echo html_escape(app_line('category_original_name')); ?></label>
						<input type="text" class="form-control" id="categoryOriginalName" readonly>
					</div>
					<div class="mb-3">
						<label class="form-label"><?php echo html_escape(app_line('category_code')); ?></label>
						<input type="text" class="form-control" id="categoryCode" readonly>
					</div>
					<div class="mb-0">
						<label class="form-label" for="categoryName"><?php echo html_escape(app_line('col_name')); ?></label>
						<input type="text" class="form-control" id="categoryName" name="name" required maxlength="255">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-mdb-dismiss="modal"><?php echo html_escape(app_line('common_cancel')); ?></button>
					<button type="submit" class="btn btn-primary" id="categorySaveBtn">
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
	window.CATEGORIES_CONFIG = <?php echo json_encode(array(
		'baseUrl' => site_url('categories'),
		'canEdit' => $can_edit,
		'records' => $records,
	), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
</script>
