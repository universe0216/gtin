<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function procedure_render_image_cell($image_urls) {
	if (empty($image_urls)) {
		return '<span class="text-muted small">No image</span>';
	}

	$html = '';

	foreach ($image_urls as $image_url) {
		$html .= '<a href="'.html_escape($image_url).'" target="_blank" rel="noopener" class="procedure-thumb-link me-1">'
			.'<img src="'.html_escape($image_url).'" alt="" class="procedure-thumb">'
			.'</a>';
	}

	return $html;
}
?>
<div class="procedure-page">
	<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
		<div>
			<h1 class="h3 mb-2">Procedure</h1>
			<p class="text-muted mb-0">Upload organization zip packages and review spreadsheet rows per zip file.</p>
		</div>
	</div>

	<div class="app-panel p-4 mb-4">
		<h2 class="h5 mb-3">Upload Zip Files</h2>
		<p class="text-muted small mb-3">
			Each zip filename must follow <code>[procedure_number]_[organization_name].zip</code>.
			Inside each zip: one spreadsheet and design images named with <code>[product_procedure_number]</code>.
		</p>

		<form id="procedureUploadForm" enctype="multipart/form-data">
			<div class="procedure-upload-zone mb-3" id="procedureUploadZone">
				<input type="file" id="zipFilesInput" name="zip_files[]" accept=".zip,application/zip" multiple class="procedure-upload-input">
				<div class="procedure-upload-content">
					<i class="fas fa-file-zipper fa-2x text-primary mb-3"></i>
					<p class="mb-1 fw-semibold">Drop zip files here or click to browse</p>
					<p class="text-muted small mb-0">You can upload multiple procedure packages at once.</p>
				</div>
			</div>

			<div id="selectedFilesList" class="procedure-selected-files d-none mb-3"></div>

			<div class="d-flex align-items-center gap-3 flex-wrap">
				<button type="submit" class="btn btn-primary" id="procedureUploadBtn" data-mdb-ripple-init disabled>
					<i class="fas fa-upload me-1"></i> Upload &amp; Process
				</button>
				<span class="text-muted small" id="procedureUploadHint">Select one or more zip files to continue.</span>
			</div>
		</form>
	</div>

	<div class="app-panel p-4">
		<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
			<h2 class="h5 mb-0">Imported Data</h2>
			<span class="badge bg-secondary" id="procedureTabCount"><?php echo count($tabs); ?> zip file(s)</span>
		</div>

		<div id="procedureTabsWrapper" class="<?php echo empty($tabs) ? 'd-none' : ''; ?>">
			<ul class="nav nav-tabs procedure-tabs mb-4" id="procedureTabs" role="tablist">
				<?php foreach ($tabs as $index => $tab): ?>
					<li class="nav-item" role="presentation">
						<button
							class="nav-link<?php echo $index === 0 ? ' active' : ''; ?>"
							id="procedure-tab-<?php echo (int) $tab['procedure_id']; ?>-tab"
							data-mdb-tab-init
							data-mdb-target="#procedure-tab-<?php echo (int) $tab['procedure_id']; ?>"
							type="button"
							role="tab"
							aria-controls="procedure-tab-<?php echo (int) $tab['procedure_id']; ?>"
							aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
							data-procedure-id="<?php echo (int) $tab['procedure_id']; ?>"
						>
							<i class="fas fa-file-zipper me-1"></i><?php echo html_escape($tab['file_name']); ?>
							<span class="badge bg-secondary ms-2"><?php echo count($tab['rows']); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="tab-content" id="procedureTabContent">
				<?php foreach ($tabs as $index => $tab): ?>
					<div
						class="tab-pane fade<?php echo $index === 0 ? ' show active' : ''; ?>"
						id="procedure-tab-<?php echo (int) $tab['procedure_id']; ?>"
						role="tabpanel"
						aria-labelledby="procedure-tab-<?php echo (int) $tab['procedure_id']; ?>-tab"
						data-procedure-id="<?php echo (int) $tab['procedure_id']; ?>"
					>
						<div class="procedure-tab-meta d-flex flex-wrap gap-3 mb-3 small text-muted">
							<span><strong>Procedure #:</strong> <?php echo html_escape($tab['procedure_number']); ?></span>
							<span><strong>Organization:</strong> <?php echo html_escape($tab['organization_name']); ?></span>
							<span><strong>Processor:</strong> <?php echo html_escape($tab['processor_name']); ?></span>
							<span><strong>Status:</strong> <?php echo html_escape($tab['status']); ?></span>
							<span><strong>Uploaded:</strong> <?php echo html_escape($tab['created_at']); ?></span>
						</div>

						<div class="table-responsive">
							<table class="table table-hover align-middle mb-0 procedure-data-table">
								<thead>
									<tr>
										<?php foreach ($tab['columns'] as $column): ?>
											<th><?php echo html_escape($column); ?></th>
										<?php endforeach; ?>
										<th>Image</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($tab['rows'] as $row): ?>
										<tr data-id="<?php echo (int) $row['id']; ?>" data-product-number="<?php echo html_escape($row['product_procedure_number']); ?>">
											<?php foreach ($row['cells'] as $cell): ?>
												<td><?php echo html_escape($cell); ?></td>
											<?php endforeach; ?>
											<td><?php echo procedure_render_image_cell($row['image_urls']); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div id="procedureEmptyState" class="text-center text-muted py-5 <?php echo empty($tabs) ? '' : 'd-none'; ?>">
			No zip files imported yet. Upload zip files to get started.
		</div>
	</div>
</div>

<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
	window.PROCEDURE_CONFIG = {
		baseUrl: <?php echo json_encode(site_url('procedure')); ?>,
		initialTabs: <?php echo json_encode($tabs); ?>
	};
</script>
