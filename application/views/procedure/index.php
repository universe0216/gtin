<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="procedure-page">
	<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
		<div>
			<h1 class="h3 mb-2">Procedure</h1>
			<p class="text-muted mb-0">Upload organization zip packages and review imported product rows.</p>
		</div>
	</div>

	<div class="app-panel p-4 mb-4">
		<h2 class="h5 mb-3">Upload Zip Files</h2>
		<p class="text-muted small mb-3">
			Each zip filename must follow <code>[procedure_number]_[organization_name].zip</code>.
			Inside each zip: one spreadsheet (column 1 = product process number, column 2 = product name) and design images named <code>[product_process_number]_[product name]</code>.
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
			<h2 class="h5 mb-0">Imported Products</h2>
			<span class="badge bg-secondary" id="procedureItemCount"><?php echo count($items); ?> items</span>
		</div>

		<div class="table-responsive">
			<table class="table table-hover align-middle mb-0" id="procedureItemsTable">
				<thead>
					<tr>
						<th>Procedure #</th>
						<th>Organization</th>
						<th>Product #</th>
						<th>Product Name</th>
						<th>Image</th>
						<th>Zip File</th>
						<th>Processor</th>
						<th>Status</th>
						<th>Uploaded</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($items)): ?>
						<tr id="procedureEmptyRow">
							<td colspan="9" class="text-center text-muted py-4">No products imported yet. Upload zip files to get started.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($items as $item): ?>
							<tr data-id="<?php echo (int) $item['id']; ?>">
								<td><?php echo html_escape($item['procedure_number']); ?></td>
								<td><?php echo html_escape($item['organization_name']); ?></td>
								<td><?php echo html_escape($item['product_procedure_number']); ?></td>
								<td><?php echo html_escape($item['name']); ?></td>
								<td>
									<?php if (!empty($item['image_urls'])): ?>
										<?php foreach ($item['image_urls'] as $image_url): ?>
											<a href="<?php echo html_escape($image_url); ?>" target="_blank" rel="noopener" class="procedure-thumb-link me-1">
												<img src="<?php echo html_escape($image_url); ?>" alt="" class="procedure-thumb">
											</a>
										<?php endforeach; ?>
									<?php else: ?>
										<span class="text-muted small">No image</span>
									<?php endif; ?>
								</td>
								<td class="small"><?php echo html_escape($item['file_name']); ?></td>
								<td><?php echo html_escape($item['processor_name']); ?></td>
								<td><span class="badge bg-info text-dark"><?php echo html_escape($item['status']); ?></span></td>
								<td class="small text-muted"><?php echo html_escape($item['created_at']); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
	window.PROCEDURE_CONFIG = {
		baseUrl: <?php echo json_encode(site_url('procedure')); ?>,
		initialCount: <?php echo (int) count($items); ?>
	};
</script>
