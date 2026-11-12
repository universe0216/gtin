<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="entity-list-page" id="productsPage">
	<div class="entity-list-page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
		</div>
		<?php if ( ! empty($can_edit) && ! empty($add_url)): ?>
			<a href="<?php echo html_escape($add_url); ?>" class="btn btn-sm btn-primary" data-mdb-ripple-init>
				<i class="fas fa-plus me-1"></i> <?php echo html_escape($add_label ?? 'Add New'); ?>
			</a>
		<?php endif; ?>
	</div>

	<div class="app-panel p-2 entity-list-panel">
		<div class="entity-list-toolbar d-flex flex-wrap align-items-center gap-2 px-2 py-2 mb-2">
			<div class="entity-list-search-form d-flex flex-wrap align-items-center gap-2 flex-grow-1">
				<div class="products-location-filter">
					<div id="productsLocationDropDown" class="products-location-dropdown" aria-label="<?php echo html_escape(app_line('col_location_id')); ?>">
						<div id="productsLocationTree"></div>
					</div>
					<button type="button" class="btn btn-secondary btn-sm d-none" id="productsLocationClear"><?php echo html_escape(app_line('common_clear')); ?></button>
				</div>
			</div>
			<?php if ($entity === 'products' && ! empty($can_edit)): ?>
				<button type="button" class="btn btn-sm btn-outline-danger d-none" id="productsDeleteSelectedBtn" data-mdb-ripple-init>
					<i class="fas fa-trash me-1"></i> <?php echo html_escape(app_line('product_delete_selected')); ?>
				</button>
				<button type="button" class="btn btn-sm btn-outline-danger" id="productsDeleteBatchBtn" data-mdb-ripple-init>
					<i class="fas fa-file-excel me-1"></i> <?php echo html_escape(app_line('product_delete_batch')); ?>
				</button>
			<?php endif; ?>
			<?php if ($entity === 'products' && ! empty($is_admin)): ?>
				<button type="button" class="btn btn-sm btn-outline-secondary" id="productsGenerateKeyBtn" data-mdb-ripple-init>
					<i class="fas fa-key me-1"></i> <?php echo html_escape(app_line('product_generate_key')); ?>
				</button>
			<?php endif; ?>
			<button type="button" class="btn btn-sm btn-outline-primary" id="productsExportBtn" data-mdb-ripple-init>
				<i class="fas fa-file-excel me-1"></i> <?php echo html_escape(app_line('common_download_excel')); ?>
			</button>
		</div>

		<div id="productsGrid"></div>
	</div>
</div>

<?php if ($entity === 'products' && ! empty($can_edit)): ?>
<div class="modal fade" id="productsDeleteBatchModal" tabindex="-1" aria-labelledby="productsDeleteBatchModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="productsDeleteBatchModalLabel"><?php echo html_escape(app_line('product_delete_batch')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small mb-3"><?php echo html_escape(app_line('product_delete_batch_hint')); ?></p>
				<div class="mb-3">
					<label class="form-label" for="productsDeleteBatchFile"><?php echo html_escape(app_line('product_delete_batch_file')); ?></label>
					<input type="file" class="form-control" id="productsDeleteBatchFile" accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
				</div>
				<div id="productsDeleteBatchFileName" class="small text-muted mb-2 d-none"></div>
				<div id="productsDeleteBatchLoading" class="text-center text-muted py-4 d-none">
					<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
					<?php echo html_escape(app_line('jqx_load')); ?>
				</div>
				<div id="productsDeleteBatchPreview" class="products-delete-batch-preview d-none">
					<div class="table-responsive">
						<table class="table table-sm table-hover mb-0">
							<thead>
								<tr>
									<th>#</th>
									<th><?php echo html_escape(app_line('col_name')); ?></th>
									<th><?php echo html_escape(app_line('col_barcode_1d')); ?></th>
								</tr>
							</thead>
							<tbody id="productsDeleteBatchBody"></tbody>
						</table>
					</div>
				</div>
				<div id="productsDeleteBatchEmpty" class="text-muted small d-none"><?php echo html_escape(app_line('product_delete_batch_empty')); ?></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_cancel')); ?></button>
				<button type="button" class="btn btn-danger" id="productsDeleteBatchSubmit" data-mdb-ripple-init disabled>
					<i class="fas fa-trash me-1"></i> <?php echo html_escape(app_line('common_delete')); ?>
				</button>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<?php $this->load->view('products/detail_modal', array('product_detail' => $detail_config ?? array())); ?>

<script>
	window.PRODUCTS_CONFIG = <?php echo json_encode(array(
		'apiUrl'                  => $list_api_url,
		'columns'                 => $list_columns,
		'perPage'                 => (int) $list_per_page,
		'perPageOptions'          => array_values($list_per_page_options),
		'emptyMessage'            => $list_empty_message,
		'emptySearchMessage'      => $list_empty_search_message,
		'locationNodes'           => $location_nodes ?? array(),
		'exportUrl'               => site_url($entity.'/export'),
		'generateKeyUrl'          => ! empty($is_admin) ? site_url('products/generate_normalized_keys') : NULL,
		'batchDeletePreviewUrl'   => ($entity === 'products' && ! empty($can_edit)) ? site_url('products/batch_delete_preview') : NULL,
		'batchDeleteUrl'          => ($entity === 'products' && ! empty($can_edit)) ? site_url('products/batch_delete') : NULL,
		'detail'                  => $detail_config ?? NULL,
	)); ?>;
</script>
