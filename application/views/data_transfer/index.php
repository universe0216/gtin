<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="data-transfer-page">
	<h1 class="h4 mb-3"><?php echo html_escape($title); ?></h1>

	<div class="app-panel p-4">
		<p class="mb-3">
			<?php echo html_escape(app_line('data_transfer_intro')); ?>
		</p>
		<ol class="mb-4 text-muted">
			<li><?php echo html_escape(app_line('data_transfer_step_clear')); ?></li>
			<li><?php echo html_escape(app_line('data_transfer_step_locations')); ?></li>
			<li><?php echo html_escape(app_line('data_transfer_step_organizations')); ?></li>
			<li><?php echo html_escape(app_line('data_transfer_step_products')); ?></li>
		</ol>

		<div id="dataTransferStatus" class="alert d-none" role="alert"></div>

		<div class="d-flex flex-wrap gap-2">
			<button type="button" class="btn btn-danger" id="dataTransferStartBtn" data-mdb-ripple-init>
				<i class="fas fa-database me-1"></i> <?php echo html_escape(app_line('data_transfer_start')); ?>
			</button>
			<button type="button" class="btn btn-outline-primary" id="dataTransferLocationsBtn" data-mdb-ripple-init>
				<i class="fas fa-map-marker-alt me-1"></i> <?php echo html_escape(app_line('data_transfer_locations_btn')); ?>
			</button>
			<button type="button" class="btn btn-outline-primary" id="dataTransferOrganizationsBtn" data-mdb-ripple-init>
				<i class="fas fa-building me-1"></i> <?php echo html_escape(app_line('data_transfer_organizations_btn')); ?>
			</button>
			<button type="button" class="btn btn-outline-primary" id="dataTransferProductsBtn" data-mdb-ripple-init>
				<i class="fas fa-box me-1"></i> <?php echo html_escape(app_line('data_transfer_products_btn')); ?>
			</button>
		</div>
	</div>
</div>

<script>
	window.DATA_TRANSFER_CONFIG = <?php echo json_encode(array(
		'startUrl'         => site_url('data_transfer/start'),
		'locationsUrl'     => site_url('data_transfer/locations'),
		'organizationsUrl' => site_url('data_transfer/organizations'),
		'productsUrl'      => site_url('data_transfer/products'),
		'chunkSize'        => 2000,
	)); ?>;
</script>
