<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="history-log-page">
	<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
		<div>
			<h1 class="h4 mb-1"><?php echo html_escape(app_line('title_activity_log')); ?></h1>
			<p class="text-muted small mb-0"><?php echo html_escape(app_line('history_activity_log_note')); ?></p>
		</div>
		<div class="d-flex flex-wrap gap-2 align-items-center">
			<select id="activityLogActionFilter" class="form-select form-select-sm" style="width: auto;">
				<option value=""><?php echo html_escape(app_line('common_all')); ?></option>
				<option value="visit"><?php echo html_escape(app_line('log_action_visit')); ?></option>
				<option value="api"><?php echo html_escape(app_line('log_action_api')); ?></option>
			</select>
			<div class="input-group input-group-sm" style="width: 16rem;">
				<span class="input-group-text"><i class="fas fa-search"></i></span>
				<input type="search" id="activityLogSearch" class="form-control" placeholder="<?php echo html_escape(app_line('history_log_search')); ?>">
			</div>
		</div>
	</div>

	<div class="app-panel p-2">
		<div id="activityLogGrid"></div>
	</div>
</div>

<div class="modal fade" id="historyLogDetailModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="historyLogDetailTitle"><?php echo html_escape(app_line('history_log_detail')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body">
				<div id="historyLogDetailMeta" class="small text-muted mb-3"></div>
				<pre id="historyLogDetailBody" class="history-log-json mb-0"></pre>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-mdb-dismiss="modal"><?php echo html_escape(app_line('common_close')); ?></button>
			</div>
		</div>
	</div>
</div>

<script>
	window.HISTORY_LOGS_CONFIG = {
		type: 'activity',
		listUrl: <?php echo json_encode(site_url('history/activity_log_list')); ?>,
		detailUrl: <?php echo json_encode(site_url('history/activity_log_detail')); ?>,
		gridSelector: '#activityLogGrid',
		searchSelector: '#activityLogSearch',
		actionFilterSelector: '#activityLogActionFilter'
	};
</script>
