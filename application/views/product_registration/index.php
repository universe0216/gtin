<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="procedure-page">
	<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-0">Product Registration</h1>
		</div>
		<div class="d-flex flex-wrap gap-2">
			<button type="button" class="btn btn-sm btn-success" id="btnOpenImportModal" data-mdb-ripple-init>
				<i class="fas fa-file-import me-1"></i> Import
			</button>
			<button type="button" class="btn btn-sm btn-primary <?php echo empty($tabs) ? 'd-none' : ''; ?>" id="btnOpenUploadModal" data-mdb-ripple-init>
				<i class="fas fa-upload me-1"></i> Upload Files
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
								aria-label="Stop procedure"
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
							<span><strong>Procedure #:</strong> <?php echo html_escape($tab['procedure_number']); ?></span>
							<span><strong>Organization:</strong> <?php echo html_escape($tab['organization_name']); ?></span>
							<span><strong>Processor:</strong> <?php echo html_escape($tab['processor_name']); ?></span>
							<span><strong>Status:</strong> <?php echo html_escape($tab['status']); ?></span>
							<span><strong>Uploaded:</strong> <?php echo html_escape($tab['created_at']); ?></span>
							<button
								type="button"
								class="btn btn-sm btn-outline-danger ms-auto product-registration-tab-delete-btn"
								data-product-registration-id="<?php echo (int) $tab['product_registration_id']; ?>"
								data-file-name="<?php echo html_escape($tab['file_name']); ?>"
								data-mdb-ripple-init
							>
								<i class="fas fa-trash me-1"></i> Delete
							</button>
						</div>

						<div class="procedure-table-scroll">
							<table class="table table-hover align-middle mb-0 procedure-data-table">
								<thead>
									<tr>
										<th class="procedure-item-status-col"></th>
										<th class="procedure-row-index">#</th>
										<?php foreach ($tab['columns'] as $column): ?>
											<th><?php echo html_escape($column); ?></th>
										<?php endforeach; ?>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($tab['rows'] as $row_index => $row): ?>
										<?php
										$row_payload = array(
											'id'                       => (int) $row['id'],
											'row_index'                => (int) $row_index + 1,
											'product_procedure_number' => $row['product_procedure_number'],
											'cells'                    => $row['cells'],
											'columns'                  => $tab['columns'],
											'image_urls'               => $row['image_urls'] ?? array(),
											'procedure_number'         => $tab['procedure_number'],
											'organization_name'        => $tab['organization_name'],
											'file_name'                => $tab['file_name'],
											'processor_name'           => $tab['processor_name'],
											'status'                   => $tab['status'],
											'item_status'              => $row['item_status'] ?? 'pending',
											'rejection_reason'         => $row['message'] ?? '',
											'created_at'               => $tab['created_at'],
										);
										$item_status = $row['item_status'] ?? 'pending';
										$is_rejected = $item_status === 'rejected';
										$is_approved = $item_status === 'approved' || $item_status === 'accepted';
										$row_state_class = $is_rejected
											? ' procedure-data-row-rejected'
											: ($is_approved ? ' procedure-data-row-approved' : '');
										$status_icon_html = '';

										if ($item_status === 'rejected')
										{
											$status_icon_html = '<i class="fas fa-times procedure-item-status-icon procedure-item-status-icon-rejected" aria-label="Rejected"></i>';
										}
										elseif ($item_status === 'approved' || $item_status === 'accepted')
										{
											$status_icon_html = '<i class="fas fa-check procedure-item-status-icon procedure-item-status-icon-approved" aria-label="Accepted"></i>';
										}
										?>
										<tr
											class="procedure-data-row<?php echo $row_state_class; ?>"
											data-id="<?php echo (int) $row['id']; ?>"
											data-item-status="<?php echo html_escape($row['item_status'] ?? 'pending'); ?>"
											data-product-number="<?php echo html_escape($row['product_procedure_number']); ?>"
											data-row-payload="<?php echo html_escape(json_encode($row_payload), ENT_QUOTES, 'UTF-8'); ?>"
										>
											<td class="procedure-item-status-cell"><?php echo $status_icon_html; ?></td>
											<td class="procedure-row-index text-muted"><?php echo (int) $row_index + 1; ?></td>
											<?php foreach ($row['cells'] as $cell): ?>
												<td><?php echo html_escape($cell); ?></td>
											<?php endforeach; ?>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div id="procedureInitialUpload" class="procedure-initial-upload p-3 <?php echo empty($tabs) ? '' : 'd-none'; ?>">
			<form class="procedure-upload-form" enctype="multipart/form-data">
				<div class="procedure-upload-form-body">
					<p class="text-muted small mb-3">
						Each zip filename must follow <code>[procedure_number]_[organization_name].zip</code>.
						Inside each zip: one spreadsheet and design images named with <code>[product_procedure_number]</code>.
					</p>

					<div class="procedure-upload-zone mb-3">
						<input type="file" name="zip_files[]" accept=".zip,application/zip" multiple class="procedure-upload-input">
						<div class="procedure-upload-content">
							<i class="fas fa-file-zipper fa-2x text-primary mb-3"></i>
							<p class="mb-1 fw-semibold">Drop zip files here or click to browse</p>
							<p class="text-muted small mb-0">You can upload multiple procedure packages at once.</p>
						</div>
					</div>

					<div class="procedure-selected-files d-none mb-3"></div>
				</div>

				<div class="procedure-upload-actions">
					<span class="text-muted small procedure-upload-hint d-block mb-2">Select one or more zip files to continue.</span>
					<div class="text-end">
						<button type="submit" class="btn btn-primary procedure-upload-submit" data-mdb-ripple-init disabled>
							<i class="fas fa-upload me-1"></i> Upload &amp; Process
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
				<h5 class="modal-title" id="procedureUploadModalLabel">Upload Zip Files</h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<form class="procedure-upload-form" enctype="multipart/form-data">
				<div class="modal-body procedure-upload-form-body">
					<p class="text-muted small mb-3">
						Each zip filename must follow <code>[procedure_number]_[organization_name].zip</code>.
						Inside each zip: one spreadsheet and design images named with <code>[product_procedure_number]</code>.
					</p>

					<div class="procedure-upload-zone mb-3">
						<input type="file" name="zip_files[]" accept=".zip,application/zip" multiple class="procedure-upload-input">
						<div class="procedure-upload-content">
							<i class="fas fa-file-zipper fa-2x text-primary mb-3"></i>
							<p class="mb-1 fw-semibold">Drop zip files here or click to browse</p>
							<p class="text-muted small mb-0">You can upload multiple procedure packages at once.</p>
						</div>
					</div>

					<div class="procedure-selected-files d-none mb-0"></div>
				</div>
				<div class="modal-footer procedure-upload-actions">
					<span class="text-muted small procedure-upload-hint me-auto">Select one or more zip files to continue.</span>
					<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init>Cancel</button>
					<button type="submit" class="btn btn-primary procedure-upload-submit" data-mdb-ripple-init disabled>
						<i class="fas fa-upload me-1"></i> Upload &amp; Process
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

				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-0 procedure-detail-body">
				<div class="procedure-detail-layout">
					<div class="procedure-detail-info-panel">

						<div id="procedureDetailInfo" class="procedure-detail-info"></div>
					</div>
					<div class="procedure-detail-side-panel">
						<ul class="nav nav-tabs procedure-detail-tabs" id="procedureDetailTabs" role="tablist">
							<li class="nav-item" role="presentation">
								<button class="nav-link active" id="procedureDetailTabImageBtn" data-mdb-tab-init data-mdb-target="#procedureDetailTabImage" type="button" role="tab" aria-controls="procedureDetailTabImage" aria-selected="true">Image</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="procedureDetailTabSimilarBtn" data-mdb-tab-init data-mdb-target="#procedureDetailTabSimilar" type="button" role="tab" aria-controls="procedureDetailTabSimilar" aria-selected="false">Similar Products</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="procedureDetailTabBarcodesBtn" data-mdb-tab-init data-mdb-target="#procedureDetailTabBarcodes" type="button" role="tab" aria-controls="procedureDetailTabBarcodes" aria-selected="false">Barcodes</button>
							</li>
						</ul>
						<div class="tab-content procedure-detail-tab-content" id="procedureDetailTabContent">
							<div class="tab-pane fade show active procedure-detail-tab-pane" id="procedureDetailTabImage" role="tabpanel" aria-labelledby="procedureDetailTabImageBtn">
								<div id="procedureDetailImageGallery" class="procedure-detail-image-gallery"></div>
							</div>
							<div class="tab-pane fade procedure-detail-tab-pane" id="procedureDetailTabSimilar" role="tabpanel" aria-labelledby="procedureDetailTabSimilarBtn">
								<div class="table-responsive">
									<table class="table table-hover align-middle mb-0 procedure-data-table">
										<thead>
											<tr>
												<th class="procedure-row-index">#</th>
												<th>Product #</th>
												<th>Product Name</th>
												<th>Match</th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td colspan="4" class="text-center text-muted py-4">No similar products found.</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
							<div class="tab-pane fade procedure-detail-tab-pane" id="procedureDetailTabBarcodes" role="tabpanel" aria-labelledby="procedureDetailTabBarcodesBtn">
								<div class="procedure-barcode-form mb-4">
									<label class="form-label" for="procedureBarcodeInput">Barcode Value</label>
									<input type="text" class="form-control" id="procedureBarcodeInput" placeholder="Enter up to 12 digits">
									<p class="text-muted small mt-2 mb-0" id="procedureBarcodeFullValue">Full EAN-13 (with checksum): —</p>
								</div>
								<div class="procedure-barcode-preview-grid">
									<div class="procedure-barcode-preview-card">
										<h6 class="procedure-barcode-preview-title">Barcode</h6>
										<div class="procedure-barcode-preview-body">
											<svg id="procedureBarcodeSvg" class="procedure-barcode-svg d-none"></svg>
											<p class="text-muted small mb-0 procedure-barcode-empty" id="procedureBarcodeEmpty">Enter a barcode value above.</p>
										</div>
									</div>
									<div class="procedure-barcode-preview-card">
										<h6 class="procedure-barcode-preview-title">QR Code</h6>
										<div class="procedure-barcode-preview-body">
											<div id="procedureQrCode" class="procedure-qr-code"></div>
											<p class="text-muted small mb-0 procedure-barcode-empty" id="procedureQrEmpty">Enter a barcode value above.</p>
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
					<button type="button" class="btn btn-success" id="procedureDetailAcceptBtn" data-mdb-ripple-init>Accept</button>
					<button type="button" class="btn btn-outline-danger" id="procedureDetailRejectBtn" data-mdb-ripple-init>Reject</button>
				</div>
				<button type="button" class="btn btn-outline-secondary" id="procedureDetailPrevBtn" data-mdb-ripple-init disabled>
					<i class="fas fa-chevron-left me-1"></i> Previous
				</button>
				<button type="button" class="btn btn-outline-secondary" id="procedureDetailNextBtn" data-mdb-ripple-init disabled>
					Next <i class="fas fa-chevron-right ms-1"></i>
				</button>
				
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init>Close</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade procedure-import-modal" id="procedureImportModal" tabindex="-1" aria-labelledby="procedureImportModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header procedure-import-modal-header">
				<h5 class="modal-title mb-0" id="procedureImportModalLabel">Import Completed Procedure</h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
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
								placeholder="Search file, procedure #, organization, processor..."
								autocomplete="off"
							>
						</div>
					</div>
				</div>
				<div id="procedureImportLoading" class="text-center text-muted py-5 d-none">
					<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
					Loading completed procedures...
				</div>
				<div id="procedureImportTableWrap" class="procedure-import-modal-table">
					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0 procedure-data-table">
							<thead>
								<tr>
									<th class="procedure-row-index">#</th>
									<th>File</th>
									<th>Procedure #</th>
									<th>Organization</th>
									<th>Processor</th>
									<th>Products</th>
									<th>Approved</th>
									<th>Rejected</th>
									<th>Completed</th>
								</tr>
							</thead>
							<tbody id="procedureImportTableBody">
								<tr>
									<td colspan="9" class="text-center text-muted py-4">No completed procedures found.</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
				<div id="procedureImportPagination" class="procedure-import-pagination app-pagination d-none d-flex flex-wrap justify-content-between align-items-center gap-3 px-3 py-3 border-top border-secondary border-opacity-25">
					<p class="text-muted small mb-0" id="procedureImportFooterMeta"></p>
					<nav class="app-pagination-nav" aria-label="Import procedure pages">
						<ul class="pagination pagination-sm pagination-circle mb-0" id="procedureImportPaginationList"></ul>
					</nav>
				</div>
			</div>
			<div class="modal-footer procedure-import-modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init>Cancel</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="procedureRejectModal" tabindex="-1" aria-labelledby="procedureRejectModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="procedureRejectModalLabel">Reject Product</h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small mb-3" id="procedureRejectModalMeta">—</p>
				<label class="form-label" for="procedureRejectReasonInput">Reason</label>
				<textarea
					class="form-control"
					id="procedureRejectReasonInput"
					rows="4"
					placeholder="Enter rejection reason..."
					autocomplete="off"
				></textarea>
				<div class="text-danger small mt-2 d-none" id="procedureRejectReasonError">Please enter a rejection reason.</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init>Cancel</button>
				<button type="button" class="btn btn-danger" id="procedureConfirmRejectBtn" data-mdb-ripple-init>Reject</button>
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
