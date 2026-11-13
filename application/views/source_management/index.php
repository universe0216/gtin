<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="source-management-page">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h1 class="h4 mb-0"><?php echo html_escape(app_line('title_source_management')); ?></h1>
	</div>

	<div class="row g-3 source-management-layout">
		<div class="col-lg-5">
			<div class="app-panel source-tree-panel p-3">
				<div class="fw-semibold mb-2"><?php echo html_escape(app_line('source_files')); ?></div>
				<div id="sourceFileTree" class="source-file-tree"></div>
			</div>
		</div>
		<div class="col-lg-7">
			<div class="app-panel source-detail-panel p-3">
				<div id="sourceDetailEmpty" class="text-muted py-5 text-center">
					<?php echo html_escape(app_line('source_select_file')); ?>
				</div>
				<div id="sourceDetailContent" class="d-none">
					<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
						<div>
							<div class="text-muted small"><?php echo html_escape(app_line('source_selected_file')); ?></div>
							<div class="fw-semibold" id="sourceSelectedPath"></div>
							<div class="text-muted small mt-1" id="sourceSelectedMeta"></div>
						</div>
						<button type="button" class="btn btn-primary" id="sourceReplaceBtn" disabled data-mdb-ripple-init>
							<i class="fas fa-upload me-1"></i><?php echo html_escape(app_line('source_replace_file')); ?>
						</button>
					</div>

					<form id="sourceReplaceForm" class="d-none">
						<input type="hidden" name="path" id="sourceReplacePath" value="">
						<input type="file" name="source_file" id="sourceReplaceInput" accept=".php,.js,.css,text/plain,application/javascript,text/css,text/x-php">
					</form>

					<hr class="border-secondary">

					<div class="fw-semibold mb-2"><?php echo html_escape(app_line('source_update_history')); ?></div>
					<div id="sourceHistoryEmpty" class="text-muted small d-none">
						<?php echo html_escape(app_line('source_history_empty')); ?>
					</div>
					<div class="table-responsive">
						<table class="table table-sm table-hover align-middle mb-0" id="sourceHistoryTable">
							<thead>
								<tr>
									<th><?php echo html_escape(app_line('source_history_when')); ?></th>
									<th><?php echo html_escape(app_line('source_history_by')); ?></th>
									<th><?php echo html_escape(app_line('source_history_uploaded')); ?></th>
									<th><?php echo html_escape(app_line('source_history_backup')); ?></th>
									<th class="text-end"><?php echo html_escape(app_line('common_actions')); ?></th>
								</tr>
							</thead>
							<tbody id="sourceHistoryBody"></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	window.SOURCE_MANAGEMENT_CONFIG = <?php echo json_encode(array(
		'baseUrl'        => site_url('source_management'),
		'tree'           => $tree,
		'sizeDiffRatio'  => 0.2,
	), JSON_UNESCAPED_UNICODE); ?>;
</script>
