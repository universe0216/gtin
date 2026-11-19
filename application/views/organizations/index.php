<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="entity-list-page" id="organizationsPage">
	<div class="entity-list-page-header d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
		</div>
		<div class="d-flex flex-wrap align-items-center gap-2">
			<?php if ( ! empty($can_edit) && ($entity ?? '') === 'organizations'): ?>
				<button type="button" class="btn btn-sm btn-primary" id="organizationAddBtn" data-mdb-ripple-init>
					<i class="fas fa-plus me-1"></i> <?php echo html_escape(app_line('org_add_new')); ?>
				</button>
			<?php endif; ?>
			<?php if ( ! empty($can_edit) && ! empty($add_url)): ?>
				<a href="<?php echo html_escape($add_url); ?>" class="btn btn-sm btn-primary" data-mdb-ripple-init>
					<i class="fas fa-file-import me-1"></i> <?php echo html_escape($add_label ?? 'Add New'); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="organizations-layout">
		<aside class="app-panel organizations-location-panel" aria-label="<?php echo html_escape(app_line('col_location_id')); ?>">
			<div class="organizations-location-header">
				<span class="organizations-location-title"><?php echo html_escape(app_line('col_location_id')); ?></span>
				<button type="button" class="btn btn-secondary btn-sm d-none" id="organizationLocationFilterClear"><?php echo html_escape(app_line('common_clear')); ?></button>
			</div>
			<div id="organizationLocationFilterTree"></div>
		</aside>

		<div class="app-panel p-2 entity-list-panel">
		<div class="entity-list-toolbar d-flex flex-wrap align-items-center gap-2 px-2 py-2 mb-2">
			<div class="entity-list-search-form d-flex flex-wrap align-items-center gap-2 flex-grow-1">
				<select class="form-select form-select-sm organizations-expiry-filter" id="organizationsExpiryFilter" aria-label="<?php echo html_escape(app_line('org_expiry_filter')); ?>">
					<option value=""><?php echo html_escape(app_line('org_expiry_filter_all')); ?></option>
					<option value="expired"><?php echo html_escape(app_line('org_expiry_filter_expired')); ?></option>
					<option value="expiring_1_month"><?php echo html_escape(app_line('org_expiry_filter_1_month')); ?></option>
					<option value="expiring_2_months"><?php echo html_escape(app_line('org_expiry_filter_2_months')); ?></option>
				</select>
			</div>
			<button type="button" class="btn btn-sm btn-outline-primary" id="organizationsExportBtn" data-mdb-ripple-init>
				<i class="fas fa-file-excel me-1"></i> <?php echo html_escape(app_line('common_download_excel')); ?>
			</button>
		</div>

		<div id="organizationsGrid"></div>
		</div>
	</div>
</div>

<?php if ( ! empty($can_edit) && ($entity ?? '') === 'organizations'): ?>
<div class="modal fade" id="organizationCreateModal" tabindex="-1" aria-labelledby="organizationCreateModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable organization-create-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="organizationCreateModalLabel"><?php echo html_escape(app_line('org_add_new')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<form id="organizationCreateInfoForm" class="entity-detail-info-form">
				<div class="modal-body">
					<div id="organizationCreateAlert" class="alert alert-danger d-none" role="alert"></div>
					<div id="organizationCreateFields" class="entity-detail-info-grid"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_cancel')); ?></button>
					<button type="submit" class="btn btn-primary" id="organizationCreateSaveBtn" data-mdb-ripple-init>
						<span class="organization-create-save-label"><?php echo html_escape(app_line('common_save')); ?></span>
						<span class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php endif; ?>

<?php $this->load->view('organizations/detail_modal', array('detail_config' => $detail_config ?? array())); ?>
<?php $this->load->view('products/detail_modal', array('product_detail' => ($detail_config['productDetail'] ?? array()))); ?>

<script>
	window.ORGANIZATIONS_CONFIG = <?php echo json_encode(array(
		'apiUrl'             => $list_api_url,
		'columns'            => $list_columns,
		'perPage'            => (int) $list_per_page,
		'perPageOptions'     => array_values($list_per_page_options),
		'emptyMessage'       => $list_empty_message,
		'emptySearchMessage' => $list_empty_search_message,
		'exportUrl'          => site_url($entity.'/export'),
		'detail'             => $detail_config ?? NULL,
		'barcode1dTypes'     => barcode_1d_type_options(),
		'barcode2dTypes'     => barcode_2d_type_options(),
		'currencyOptions'    => currency_options_for_js(),
		'createUrl'          => ( ! empty($can_edit) && ($entity ?? '') === 'organizations') ? site_url('organizations/create') : '',
	)); ?>;
</script>
