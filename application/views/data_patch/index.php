<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$has_export = ! empty($has_export);
$last_export = $last_export ?? NULL;
$history = $history ?? array();
?>
<div class="data-patch-page">
	<h1 class="h4 mb-3"><?php echo html_escape($title); ?></h1>

	<div class="app-panel p-4 mb-4">
		<p class="mb-3"><?php echo html_escape(app_line('data_patch_intro')); ?></p>
		<ul class="text-muted mb-4">
			<li><?php echo html_escape(app_line('data_patch_note_full')); ?></li>
			<li><?php echo html_escape(app_line('data_patch_note_patch')); ?></li>
			<li><?php echo html_escape(app_line('data_patch_note_files')); ?></li>
		</ul>

		<?php if ($last_export): ?>
			<p class="small text-muted mb-3" id="dataPatchLastMeta">
				<?php echo html_escape(app_line('data_patch_last_export')); ?>:
				<strong><?php echo html_escape($last_export['finished_at'] ?: $last_export['created_at']); ?></strong>
				(<?php echo html_escape($last_export['type']); ?>,
				<?php echo (int) $last_export['file_count']; ?> <?php echo html_escape(app_line('data_patch_files')); ?>)
			</p>
		<?php else: ?>
			<p class="small text-muted mb-3" id="dataPatchLastMeta">
				<?php echo html_escape(app_line('data_patch_no_export_yet')); ?>
			</p>
		<?php endif; ?>

		<div id="dataPatchStatus" class="alert d-none" role="alert"></div>

		<div id="dataPatchProgress" class="data-transfer-progress d-none mb-4" aria-live="polite">
			<div class="d-flex justify-content-between align-items-center gap-2 mb-2">
				<div class="data-transfer-progress-label text-muted small" id="dataPatchProgressLabel"></div>
				<div class="data-transfer-progress-percent fw-semibold" id="dataPatchProgressPercent">0%</div>
			</div>
			<div class="progress data-transfer-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
				<div class="progress-bar progress-bar-striped progress-bar-animated" id="dataPatchProgressBar" style="width: 0%"></div>
			</div>
			<div class="data-transfer-progress-meta text-muted small mt-2" id="dataPatchProgressMeta"></div>
		</div>

		<div class="d-flex flex-wrap gap-2">
			<button type="button" class="btn btn-primary" id="dataPatchFullBtn" data-mdb-ripple-init>
				<i class="fas fa-file-export me-1"></i> <?php echo html_escape(app_line('data_patch_export_full')); ?>
			</button>
			<button
				type="button"
				class="btn btn-outline-primary"
				id="dataPatchPatchBtn"
				data-mdb-ripple-init
				<?php echo $has_export ? '' : ' disabled'; ?>
			>
				<i class="fas fa-code-branch me-1"></i> <?php echo html_escape(app_line('data_patch_export_patch')); ?>
			</button>
		</div>
	</div>

	<div class="app-panel p-4">
		<h2 class="h6 mb-3"><?php echo html_escape(app_line('data_patch_history')); ?></h2>
		<div class="table-responsive">
			<table class="table table-sm align-middle mb-0">
				<thead>
					<tr>
						<th>#</th>
						<th><?php echo html_escape(app_line('log_action')); ?></th>
						<th><?php echo html_escape(app_line('common_status')); ?></th>
						<th><?php echo html_escape(app_line('data_patch_files')); ?></th>
						<th><?php echo html_escape(app_line('nav_organizations')); ?></th>
						<th><?php echo html_escape(app_line('nav_products')); ?></th>
						<th><?php echo html_escape(app_line('log_when')); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="dataPatchHistoryBody">
					<?php if (empty($history)): ?>
						<tr>
							<td colspan="8" class="text-muted"><?php echo html_escape(app_line('data_patch_history_empty')); ?></td>
						</tr>
					<?php else: ?>
						<?php foreach ($history as $row): ?>
							<tr>
								<td><?php echo (int) $row['id']; ?></td>
								<td><?php echo html_escape($row['type']); ?></td>
								<td><?php echo html_escape($row['status']); ?></td>
								<td><?php echo (int) $row['file_count']; ?></td>
								<td><?php echo (int) $row['company_count']; ?></td>
								<td><?php echo (int) $row['product_count']; ?></td>
								<td><?php echo html_escape($row['finished_at'] ?: $row['created_at']); ?></td>
								<td class="text-end">
									<?php if ($row['status'] === 'completed'): ?>
										<a class="btn btn-sm btn-outline-secondary" href="<?php echo site_url('data_patch/download/'.$row['id']); ?>">
											<i class="fas fa-download"></i>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<script>
	window.DATA_PATCH_CONFIG = <?php echo json_encode(array(
		'startUrl'  => site_url('data_patch/export_start'),
		'chunkUrl'  => site_url('data_patch/export_chunk'),
		'statusUrl' => site_url('data_patch/status'),
		'hasExport' => $has_export ? TRUE : FALSE,
	)); ?>;
</script>
