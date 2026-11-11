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

<?php $this->load->view('products/detail_modal', array('product_detail' => $detail_config ?? array())); ?>

<script>
	window.PRODUCTS_CONFIG = <?php echo json_encode(array(
		'apiUrl'             => $list_api_url,
		'columns'            => $list_columns,
		'perPage'            => (int) $list_per_page,
		'perPageOptions'     => array_values($list_per_page_options),
		'emptyMessage'       => $list_empty_message,
		'emptySearchMessage' => $list_empty_search_message,
		'locationNodes'      => $location_nodes ?? array(),
		'exportUrl'          => site_url($entity.'/export'),
		'generateKeyUrl'     => ! empty($is_admin) ? site_url('products/generate_normalized_keys') : NULL,
		'detail'             => $detail_config ?? NULL,
	)); ?>;
</script>
