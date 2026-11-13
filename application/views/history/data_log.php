<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="history-log-page">
	<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
		<div>
			<h1 class="h4 mb-1"><?php echo html_escape(app_line('title_data_log')); ?></h1>
			<p class="text-muted small mb-0"><?php echo html_escape(app_line('history_data_log_note')); ?></p>
		</div>
		<div class="d-flex flex-wrap gap-2 align-items-center">
			<select id="dataLogEntityFilter" class="form-select form-select-sm" style="width: auto;">
				<option value=""><?php echo html_escape(app_line('common_all')); ?></option>
				<option value="product"><?php echo html_escape(app_line('log_entity_product')); ?></option>
				<option value="organization"><?php echo html_escape(app_line('log_entity_organization')); ?></option>
			</select>
			<select id="dataLogActionFilter" class="form-select form-select-sm" style="width: auto;">
				<option value=""><?php echo html_escape(app_line('common_all')); ?></option>
				<option value="create"><?php echo html_escape(app_line('log_action_create')); ?></option>
				<option value="update"><?php echo html_escape(app_line('log_action_update')); ?></option>
				<option value="archive"><?php echo html_escape(app_line('log_action_archive')); ?></option>
				<option value="restore"><?php echo html_escape(app_line('log_action_restore')); ?></option>
			</select>
			<div class="input-group input-group-sm" style="width: 16rem;">
				<span class="input-group-text"><i class="fas fa-search"></i></span>
				<input type="search" id="dataLogSearch" class="form-control" placeholder="<?php echo html_escape(app_line('history_log_search')); ?>">
			</div>
		</div>
	</div>

	<div class="app-panel p-2">
		<div id="dataLogGrid"></div>
	</div>
</div>

<div class="modal fade" id="historyLogDetailModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="historyLogDetailTitle"><?php echo html_escape(app_line('history_log_detail')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body">
				<div id="historyLogDetailMeta" class="small text-muted mb-3"></div>
				<div class="row g-3">
					<div class="col-md-6">
						<div class="fw-semibold mb-2"><?php echo html_escape(app_line('log_original_data')); ?></div>
						<pre id="historyLogOriginalBody" class="history-log-json mb-0"></pre>
					</div>
					<div class="col-md-6">
						<div class="fw-semibold mb-2"><?php echo html_escape(app_line('log_new_data')); ?></div>
						<pre id="historyLogNewBody" class="history-log-json mb-0"></pre>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-mdb-dismiss="modal"><?php echo html_escape(app_line('common_close')); ?></button>
			</div>
		</div>
	</div>
</div>

<script>
	window.HISTORY_LOGS_CONFIG = {
		type: 'data',
		listUrl: <?php echo json_encode(site_url('history/data_log_list')); ?>,
		detailUrl: <?php echo json_encode(site_url('history/data_log_detail')); ?>,
		gridSelector: '#dataLogGrid',
		searchSelector: '#dataLogSearch',
		actionFilterSelector: '#dataLogActionFilter',
		entityFilterSelector: '#dataLogEntityFilter'
	};
</script>
