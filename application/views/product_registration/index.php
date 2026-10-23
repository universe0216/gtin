<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="procedure-page">
	<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-0"><?php echo html_escape(app_line('title_product_registration')); ?></h1>
		</div>
		<div class="d-flex flex-wrap gap-2">
			<button type="button" class="btn btn-sm btn-success" id="btnOpenImportModal" data-mdb-ripple-init>
				<i class="fas fa-file-import me-1"></i> <?php echo html_escape(app_line('common_import')); ?>
			</button>
			<button type="button" class="btn btn-sm btn-primary <?php echo empty($tabs) ? 'd-none' : ''; ?>" id="btnOpenUploadModal" data-mdb-ripple-init>
				<i class="fas fa-upload me-1"></i> <?php echo html_escape(app_line('common_upload_files')); ?>
			</button>
		</div>
	</div>

	<div class="app-panel p-2">
		<div id="procedureTabsWrapper" class="<?php echo empty($tabs) ? 'd-none' : ''; ?>">
			<ul class="nav nav-tabs procedure-tabs mb-3" id="procedureTabs" role="tablist">
				<?php foreach ($tabs as $index => $tab): ?>
					<li class="nav-item procedure-tab-nav-item" role="presentation">
						<div class="procedure-tab-nav-wrap">
							<button
								class="nav-link<?php echo $index === 0 ? ' active' : ''; ?>"
								id="product-registration-tab-<?php echo (int) $tab['product_registration_id']; ?>-tab"
								data-mdb-tab-init
								data-mdb-target="#product-registration-tab-<?php echo (int) $tab['product_registration_id']; ?>"
								type="button"
								role="tab"
								aria-controls="product-registration-tab-<?php echo (int) $tab['product_registration_id']; ?>"
								aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
								data-product-registration-id="<?php echo (int) $tab['product_registration_id']; ?>"
							>
								<i class="fas fa-file-zipper me-1"></i><?php echo html_escape($tab['file_name']); ?>
								<span class="badge bg-secondary ms-2"><?php echo count($tab['rows']); ?></span>
							</button>
							<button
								type="button"
								class="procedure-tab-nav-close product-registration-tab-delete-btn"
								data-product-registration-id="<?php echo (int) $tab['product_registration_id']; ?>"
								data-file-name="<?php echo html_escape($tab['file_name']); ?>"
								aria-label="<?php echo html_escape(app_line('reg_stop_procedure')); ?>"
								data-mdb-ripple-init
							>
								<i class="fas fa-times" aria-hidden="true"></i>
							</button>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="tab-content" id="procedureTabContent">
				<?php foreach ($tabs as $index => $tab): ?>
					<div
						class="tab-pane fade<?php echo $index === 0 ? ' show active' : ''; ?>"
						id="product-registration-tab-<?php echo (int) $tab['product_registration_id']; ?>"
						role="tabpanel"
						aria-labelledby="product-registration-tab-<?php echo (int) $tab['product_registration_id']; ?>-tab"
						data-product-registration-id="<?php echo (int) $tab['product_registration_id']; ?>"
						data-available-barcodes="<?php echo htmlspecialchars(json_encode($tab['available_barcodes'] ?? array()), ENT_QUOTES, 'UTF-8'); ?>"
					>
						<div class="procedure-tab-meta d-flex flex-wrap gap-3 mb-3 small text-muted align-items-center">
							<span><strong><?php echo html_escape(app_line('reg_procedure')); ?>:</strong> <?php echo html_escape($tab['procedure_number']); ?></span>
							<span><strong><?php echo html_escape(app_line('reg_organization')); ?>:</strong> <?php echo html_escape($tab['organization_name']); ?></span>
							<span><strong><?php echo html_escape(app_line('reg_processor')); ?>:</strong> <?php echo html_escape($tab['processor_name']); ?></span>
							<span><strong><?php echo html_escape(app_line('reg_status')); ?>:</strong> <?php echo html_escape($tab['status']); ?></span>
							<span><strong><?php echo html_escape(app_line('reg_uploaded')); ?>:</strong> <?php echo html_escape($tab['created_at']); ?></span>
							<button
								type="button"
								class="btn btn-sm btn-outline-danger ms-auto product-registration-tab-delete-btn"
								data-product-registration-id="<?php echo (int) $tab['product_registration_id']; ?>"
								data-file-name="<?php echo html_escape($tab['file_name']); ?>"
								data-mdb-ripple-init
							>
								<i class="fas fa-trash me-1"></i> <?php echo html_escape(app_line('common_delete')); ?>
							</button>
						</div>

						<div class="product-registration-grid"></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div id="procedureInitialUpload" class="procedure-initial-upload p-3 <?php echo empty($tabs) ? '' : 'd-none'; ?>">
			<form class="procedure-upload-form" enctype="multipart/form-data">
				<div class="procedure-upload-form-body">
					<p class="text-muted small mb-3">
						<?php echo html_escape(app_line('reg_zip_rule_product')); ?>
					</p>

					<div class="procedure-upload-zone mb-3">
						<input type="file" name="zip_files[]" accept=".zip,application/zip" multiple class="procedure-upload-input">
						<div class="procedure-upload-content">
							<i class="fas fa-file-zipper fa-2x text-primary mb-3"></i>
							<p class="mb-1 fw-semibold"><?php echo html_escape(app_line('reg_drop_zip')); ?></p>
							<p class="text-muted small mb-0"><?php echo html_escape(app_line('reg_multi_product')); ?></p>
						</div>
					</div>

					<div class="procedure-selected-files d-none mb-3"></div>
				</div>

				<div class="procedure-upload-actions">
					<span class="text-muted small procedure-upload-hint d-block mb-2"><?php echo html_escape(app_line('reg_select_zip')); ?></span>
					<div class="text-end">
						<button type="submit" class="btn btn-primary procedure-upload-submit" data-mdb-ripple-init disabled>
							<i class="fas fa-upload me-1"></i> <?php echo html_escape(app_line('reg_upload_process')); ?>
						</button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="procedureUploadModal" tabindex="-1" aria-labelledby="procedureUploadModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="procedureUploadModalLabel"><?php echo html_escape(app_line('reg_upload_zip_title')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<form class="procedure-upload-form" enctype="multipart/form-data">
				<div class="modal-body procedure-upload-form-body">
					<p class="text-muted small mb-3">
						<?php echo html_escape(app_line('reg_zip_rule_product')); ?>
					</p>

					<div class="procedure-upload-zone mb-3">
						<input type="file" name="zip_files[]" accept=".zip,application/zip" multiple class="procedure-upload-input">
						<div class="procedure-upload-content">
							<i class="fas fa-file-zipper fa-2x text-primary mb-3"></i>
							<p class="mb-1 fw-semibold"><?php echo html_escape(app_line('reg_drop_zip')); ?></p>
							<p class="text-muted small mb-0"><?php echo html_escape(app_line('reg_multi_product')); ?></p>
						</div>
					</div>

					<div class="procedure-selected-files d-none mb-0"></div>
				</div>
				<div class="modal-footer procedure-upload-actions">
					<span class="text-muted small procedure-upload-hint me-auto"><?php echo html_escape(app_line('reg_select_zip')); ?></span>
					<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_cancel')); ?></button>
					<button type="submit" class="btn btn-primary procedure-upload-submit" data-mdb-ripple-init disabled>
						<i class="fas fa-upload me-1"></i> <?php echo html_escape(app_line('reg_upload_process')); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade procedure-detail-modal" id="procedureProductModal" tabindex="-1" aria-labelledby="procedureProductModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header procedure-detail-header">
				<h6 class="modal-title mb-0 procedure-detail-header-title" id="procedureProductModalLabel">—</h6>
				<span class="text-muted small me-auto px-4" id="procedureDetailFooterMeta"></span>

				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body p-0 procedure-detail-body">
				<div class="procedure-detail-layout">
					<div class="procedure-detail-info-panel">

						<div id="procedureDetailInfo" class="procedure-detail-info"></div>
					</div>
					<div class="procedure-detail-side-panel">
						<ul class="nav nav-tabs procedure-detail-tabs" id="procedureDetailTabs" role="tablist">
							<li class="nav-item" role="presentation">
								<button class="nav-link active" id="procedureDetailTabImageBtn" data-mdb-tab-init data-mdb-target="#procedureDetailTabImage" type="button" role="tab" aria-controls="procedureDetailTabImage" aria-selected="true"><?php echo html_escape(app_line('reg_image')); ?></button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="procedureDetailTabSimilarBtn" data-mdb-tab-init data-mdb-target="#procedureDetailTabSimilar" type="button" role="tab" aria-controls="procedureDetailTabSimilar" aria-selected="false"><?php echo html_escape(app_line('reg_similar')); ?></button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="procedureDetailTabBarcodesBtn" data-mdb-tab-init data-mdb-target="#procedureDetailTabBarcodes" type="button" role="tab" aria-controls="procedureDetailTabBarcodes" aria-selected="false"><?php echo html_escape(app_line('reg_barcodes')); ?></button>
							</li>
						</ul>
						<div class="tab-content procedure-detail-tab-content" id="procedureDetailTabContent">
							<div class="tab-pane fade show active procedure-detail-tab-pane" id="procedureDetailTabImage" role="tabpanel" aria-labelledby="procedureDetailTabImageBtn">
								<div id="procedureDetailImageGallery" class="procedure-detail-image-gallery"></div>
							</div>
							<div class="tab-pane fade procedure-detail-tab-pane" id="procedureDetailTabSimilar" role="tabpanel" aria-labelledby="procedureDetailTabSimilarBtn">
								<div id="procedureSimilarGrid"></div>
							</div>
							<div class="tab-pane fade procedure-detail-tab-pane" id="procedureDetailTabBarcodes" role="tabpanel" aria-labelledby="procedureDetailTabBarcodesBtn">
								<div class="procedure-barcode-form mb-4">
									<label class="form-label" for="procedureBarcodeInput"><?php echo html_escape(app_line('reg_barcode_value')); ?></label>
									<input type="text" class="form-control" id="procedureBarcodeInput" placeholder="<?php echo html_escape(app_line('reg_barcode_placeholder')); ?>">
									<p class="text-muted small mt-2 mb-0" id="procedureBarcodeFullValue"><?php echo html_escape(app_line('reg_barcode_full')); ?></p>
								</div>
								<div class="procedure-barcode-preview-grid">
									<div class="procedure-barcode-preview-card">
										<h6 class="procedure-barcode-preview-title"><?php echo html_escape(app_line('reg_barcode_title')); ?></h6>
										<div class="procedure-barcode-preview-body">
											<svg id="procedureBarcodeSvg" class="procedure-barcode-svg d-none"></svg>
											<p class="text-muted small mb-0 procedure-barcode-empty" id="procedureBarcodeEmpty"><?php echo html_escape(app_line('reg_barcode_empty')); ?></p>
										</div>
									</div>
									<div class="procedure-barcode-preview-card">
										<h6 class="procedure-barcode-preview-title"><?php echo html_escape(app_line('reg_qr_title')); ?></h6>
										<div class="procedure-barcode-preview-body">
											<div id="procedureQrCode" class="procedure-qr-code"></div>
											<p class="text-muted small mb-0 procedure-barcode-empty" id="procedureQrEmpty"><?php echo html_escape(app_line('reg_barcode_empty')); ?></p>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer procedure-detail-footer">
				<span class="procedure-assignable-barcode text-muted small me-auto" id="assignable_barcode"></span>
				<div class="d-flex flex-wrap gap-2">
					<button type="button" class="btn btn-success" id="procedureDetailAcceptBtn" data-mdb-ripple-init><?php echo html_escape(app_line('common_accept')); ?></button>
					<button type="button" class="btn btn-outline-danger" id="procedureDetailRejectBtn" data-mdb-ripple-init><?php echo html_escape(app_line('common_reject')); ?></button>
				</div>
				<button type="button" class="btn btn-outline-secondary" id="procedureDetailPrevBtn" data-mdb-ripple-init disabled>
					<i class="fas fa-chevron-left me-1"></i> <?php echo html_escape(app_line('common_previous')); ?>
				</button>
				<button type="button" class="btn btn-outline-secondary" id="procedureDetailNextBtn" data-mdb-ripple-init disabled>
					<?php echo html_escape(app_line('common_next')); ?> <i class="fas fa-chevron-right ms-1"></i>
				</button>
				
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_close')); ?></button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade procedure-import-modal" id="procedureImportModal" tabindex="-1" aria-labelledby="procedureImportModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header procedure-import-modal-header">
				<h5 class="modal-title mb-0" id="procedureImportModalLabel"><?php echo html_escape(app_line('reg_import_title')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body procedure-import-modal-body p-0">
				<div class="procedure-import-toolbar px-3 py-3 border-bottom border-secondary border-opacity-25">
					<div class="procedure-import-search-wrap">
						<div class="input-group input-group-sm">
							<span class="input-group-text"><i class="fas fa-search"></i></span>
							<input
								type="search"
								class="form-control"
								id="procedureImportSearch"
								placeholder="<?php echo html_escape(app_line('reg_import_search')); ?>"
								autocomplete="off"
							>
						</div>
					</div>
				</div>
				<div id="procedureImportLoading" class="text-center text-muted py-5 d-none">
					<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
					<?php echo html_escape(app_line('reg_import_loading')); ?>
				</div>
				<div id="procedureImportTableWrap" class="procedure-import-modal-table p-2">
					<div id="procedureImportGrid"></div>
				</div>
			</div>
			<div class="modal-footer procedure-import-modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_cancel')); ?></button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="procedureRejectModal" tabindex="-1" aria-labelledby="procedureRejectModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="procedureRejectModalLabel"><?php echo html_escape(app_line('reg_reject_title')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small mb-3" id="procedureRejectModalMeta">—</p>
				<label class="form-label" for="procedureRejectReasonInput"><?php echo html_escape(app_line('common_reason')); ?></label>
				<textarea
					class="form-control"
					id="procedureRejectReasonInput"
					rows="4"
					placeholder="<?php echo html_escape(app_line('reg_reject_placeholder')); ?>"
					autocomplete="off"
				></textarea>
				<div class="text-danger small mt-2 d-none" id="procedureRejectReasonError"><?php echo html_escape(app_line('reg_reject_required')); ?></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_cancel')); ?></button>
				<button type="button" class="btn btn-danger" id="procedureConfirmRejectBtn" data-mdb-ripple-init><?php echo html_escape(app_line('common_reject')); ?></button>
			</div>
		</div>
	</div>
</div>

<script src="<?php echo base_url('assets/vendor/jsbarcode/JsBarcode.all.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/vendor/qrcode/qrcode.min.js'); ?>"></script>
<script>
	window.PRODUCT_REGISTRATION_CONFIG = {
		baseUrl: <?php echo json_encode(site_url('product_registration')); ?>,
		initialTabs: <?php echo json_encode($tabs); ?>
	};
</script>
