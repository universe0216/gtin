<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="history-procedure-page">
	<div class="mb-4">
		<h1 class="h4 mb-1"><?php echo html_escape($title); ?></h1>
		<p class="text-muted small mb-0">Completed procedure files.</p>
	</div>

	<div class="app-panel p-0">
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
				<tbody>
					<?php if (empty($registrations)): ?>
						<tr>
							<td colspan="9" class="text-center text-muted py-4">No completed registrations found.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($registrations as $index => $registration): ?>
							<tr
								class="history-procedure-row"
								role="button"
								tabindex="0"
								data-product-registration-id="<?php echo (int) $registration['id']; ?>"
							>
								<td class="procedure-row-index text-muted"><?php echo (int) $row_offset + $index + 1; ?></td>
								<td>
									<i class="fas fa-file-zipper me-1 text-primary"></i>
									<?php echo html_escape($registration['file_name']); ?>
								</td>
								<td><?php echo html_escape($registration['procedure_number']); ?></td>
								<td><?php echo html_escape($registration['organization_name']); ?></td>
								<td><?php echo html_escape($registration['processor_name'] ?? ''); ?></td>
								<td><?php echo (int) $registration['total_products']; ?></td>
								<td><?php echo (int) $registration['approved']; ?></td>
								<td><?php echo (int) $registration['rejected']; ?></td>
								<td><?php echo html_escape($registration['created_at']); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ($total > 0): ?>
			<div class="app-pagination d-flex flex-wrap justify-content-between align-items-center gap-3 px-3 py-3 border-top border-secondary border-opacity-25">
				<p class="text-muted small mb-0">
					Showing <?php echo (int) $range_start; ?>–<?php echo (int) $range_end; ?> of <?php echo (int) $total; ?>
				</p>
				<?php if ($total > $per_page): ?>
					<?php echo $pagination; ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="modal fade history-procedure-modal" id="historyProcedureModal" tabindex="-1" aria-labelledby="historyProcedureModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header history-procedure-modal-header">
				<h6 class="modal-title mb-0" id="historyProcedureModalLabel">
					<i class="fas fa-file-zipper me-1 text-primary"></i>
					<span id="historyProcedureModalTitle">—</span>
				</h6>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-0 history-procedure-modal-body">
				<div id="historyProcedureModalMeta" class="history-procedure-modal-meta procedure-tab-meta d-flex flex-wrap gap-3 px-4 py-3 small text-muted border-bottom border-secondary border-opacity-25"></div>
				<div class="history-procedure-modal-table procedure-table-scroll">
					<div id="historyProcedureModalLoading" class="text-center text-muted py-5 d-none">
						<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
						Loading procedure items...
					</div>
					<div id="historyProcedureModalTableWrap"></div>
				</div>
			</div>
			<div class="modal-footer history-procedure-modal-footer">
				<span class="text-muted small me-auto" id="historyProcedureModalFooterMeta"></span>
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init>Close</button>
			</div>
		</div>
	</div>
</div>

<div id="historyToastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>

<script>
	window.HISTORY_CONFIG = {
		type: 'products',
		baseUrl: <?php echo json_encode(site_url($history_route)); ?>
	};
</script>
