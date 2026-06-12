<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="procedure-page">
	<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-0">Organization Registration</h1>
		</div>
		<div class="d-flex flex-wrap gap-2">
		<p class="text-muted small mb-0 mt-1">
				<a href="<?php echo site_url('organizations'); ?>"><i class="fas fa-arrow-left me-1"></i>Back to Organizations</a>
			</p>
			<button type="button" class="btn btn-sm btn-primary <?php echo empty($tabs) ? 'd-none' : ''; ?>" id="btnOpenUploadModal" data-mdb-ripple-init>
				<i class="fas fa-upload me-1"></i> Upload Files
			</button>
		</div>
	</div>

	<div class="app-panel p-2">
		<div id="orgRegistrationTabsWrapper" class="<?php echo empty($tabs) ? 'd-none' : ''; ?>">
			<ul class="nav nav-tabs procedure-tabs mb-4" id="orgRegistrationTabs" role="tablist">
				<?php foreach ($tabs as $index => $tab): ?>
					<li class="nav-item procedure-tab-nav-item" role="presentation">
						<div class="procedure-tab-nav-wrap">
							<button
								class="nav-link<?php echo $index === 0 ? ' active' : ''; ?>"
								id="org-registration-tab-<?php echo (int) $tab['organization_registration_id']; ?>-tab"
								data-mdb-tab-init
								data-mdb-target="#org-registration-tab-<?php echo (int) $tab['organization_registration_id']; ?>"
								type="button"
								role="tab"
								aria-controls="org-registration-tab-<?php echo (int) $tab['organization_registration_id']; ?>"
								aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
								data-organization-registration-id="<?php echo (int) $tab['organization_registration_id']; ?>"
							>
								<i class="fas fa-file-zipper me-1"></i><?php echo html_escape($tab['file_name']); ?>
								<span class="badge bg-secondary ms-2"><?php echo count($tab['rows']); ?></span>
							</button>
							<button
								type="button"
								class="procedure-tab-nav-close org-registration-tab-delete-btn"
								data-organization-registration-id="<?php echo (int) $tab['organization_registration_id']; ?>"
								data-file-name="<?php echo html_escape($tab['file_name']); ?>"
								aria-label="Stop registration"
								data-mdb-ripple-init
							>
								<i class="fas fa-times" aria-hidden="true"></i>
							</button>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="tab-content" id="orgRegistrationTabContent">
				<?php foreach ($tabs as $index => $tab): ?>
					<div
						class="tab-pane fade<?php echo $index === 0 ? ' show active' : ''; ?>"
						id="org-registration-tab-<?php echo (int) $tab['organization_registration_id']; ?>"
						role="tabpanel"
						aria-labelledby="org-registration-tab-<?php echo (int) $tab['organization_registration_id']; ?>-tab"
						data-organization-registration-id="<?php echo (int) $tab['organization_registration_id']; ?>"
					>
						<div class="procedure-tab-meta d-flex flex-wrap gap-3 mb-3 small text-muted align-items-center">
							<span><strong>Procedure #:</strong> <?php echo html_escape($tab['procedure_number']); ?></span>
							<span><strong>Organization:</strong> <?php echo html_escape($tab['organization_name']); ?></span>
							<span><strong>Processor:</strong> <?php echo html_escape($tab['processor_name']); ?></span>
							<span><strong>Status:</strong> <?php echo html_escape($tab['status']); ?></span>
							<span><strong>Uploaded:</strong> <?php echo html_escape($tab['created_at']); ?></span>
							<button
								type="button"
								class="btn btn-sm btn-outline-danger ms-auto org-registration-tab-delete-btn"
								data-organization-registration-id="<?php echo (int) $tab['organization_registration_id']; ?>"
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
										<th class="procedure-row-index">#</th>
										<?php foreach ($tab['columns'] as $column): ?>
											<th><?php echo html_escape($column); ?></th>
										<?php endforeach; ?>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($tab['rows'] as $row_index => $row): ?>
										<tr data-id="<?php echo (int) $row['id']; ?>">
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

		<div id="orgRegistrationInitialUpload" class="procedure-initial-upload p-3 <?php echo empty($tabs) ? '' : 'd-none'; ?>">
			<form class="org-registration-upload-form" enctype="multipart/form-data">
				<div class="procedure-upload-form-body">
					<p class="text-muted small mb-3">
						Each zip filename must follow <code>[procedure_number]_[organization_name].zip</code>.
						Inside each zip: one spreadsheet with organization rows.
					</p>

					<div class="procedure-upload-zone mb-3">
						<input type="file" name="zip_files[]" accept=".zip,application/zip" multiple class="procedure-upload-input">
						<div class="procedure-upload-content">
							<i class="fas fa-file-zipper fa-2x text-primary mb-3"></i>
							<p class="mb-1 fw-semibold">Drop zip files here or click to browse</p>
							<p class="text-muted small mb-0">You can upload multiple registration packages at once.</p>
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

<div class="modal fade" id="orgRegistrationUploadModal" tabindex="-1" aria-labelledby="orgRegistrationUploadModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="orgRegistrationUploadModalLabel">Upload Zip Files</h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<form class="org-registration-upload-form" enctype="multipart/form-data">
				<div class="modal-body procedure-upload-form-body">
					<p class="text-muted small mb-3">
						Each zip filename must follow <code>[procedure_number]_[organization_name].zip</code>.
						Inside each zip: one spreadsheet with organization rows.
					</p>

					<div class="procedure-upload-zone mb-3">
						<input type="file" name="zip_files[]" accept=".zip,application/zip" multiple class="procedure-upload-input">
						<div class="procedure-upload-content">
							<i class="fas fa-file-zipper fa-2x text-primary mb-3"></i>
							<p class="mb-1 fw-semibold">Drop zip files here or click to browse</p>
							<p class="text-muted small mb-0">You can upload multiple registration packages at once.</p>
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

<div class="modal fade" id="orgRegistrationDeleteModal" tabindex="-1" aria-labelledby="orgRegistrationDeleteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="orgRegistrationDeleteModalLabel">—</h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				This registration is not completed yet. Stop registration for this file?
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init>Cancel</button>
				<button type="button" class="btn btn-danger" id="orgRegistrationConfirmDeleteBtn" data-mdb-ripple-init>Stop Registration</button>
			</div>
		</div>
	</div>
</div>

<script>
	window.ORGANIZATION_REGISTRATION_CONFIG = {
		baseUrl: <?php echo json_encode(site_url('organization_registration')); ?>,
		initialTabs: <?php echo json_encode($tabs); ?>
	};
</script>
