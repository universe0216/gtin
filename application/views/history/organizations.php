<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="history-procedure-page">
	<div class="mb-4">
		<h1 class="h4 mb-1"><?php echo html_escape(app_line('history_organizations_title')); ?></h1>
		<p class="text-muted small mb-0"><?php echo html_escape(app_line('history_organizations_note')); ?></p>
	</div>

	<div class="app-panel p-2">
		<div id="historyOrganizationsGrid"></div>
	</div>
</div>

<div class="modal fade history-procedure-modal" id="historyOrganizationModal" tabindex="-1" aria-labelledby="historyOrganizationModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header history-procedure-modal-header">
				<h6 class="modal-title mb-0" id="historyOrganizationModalLabel">
					<i class="fas fa-file-zipper me-1 text-primary"></i>
					<span id="historyOrganizationModalTitle">—</span>
				</h6>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body p-0 history-procedure-modal-body">
				<div id="historyOrganizationModalMeta" class="history-procedure-modal-meta procedure-tab-meta d-flex flex-wrap gap-3 px-4 py-3 small text-muted border-bottom border-secondary border-opacity-25"></div>
				<div class="history-procedure-modal-table procedure-table-scroll">
					<div id="historyOrganizationModalLoading" class="text-center text-muted py-5 d-none">
						<div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
						<?php echo html_escape(app_line('history_loading_org_items')); ?>
					</div>
					<div id="historyOrganizationModalTableWrap"></div>
				</div>
			</div>
			<div class="modal-footer history-procedure-modal-footer">
				<span class="text-muted small me-auto" id="historyOrganizationModalFooterMeta"></span>
				<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_close')); ?></button>
			</div>
		</div>
	</div>
</div>

<script>
	window.HISTORY_CONFIG = {
		type: 'organizations',
		baseUrl: <?php echo json_encode(site_url('history/organizations')); ?>,
		gridSelector: '#historyOrganizationsGrid',
		rows: <?php echo json_encode(array_map(function ($registration) {
			return array(
				'id'                => (int) $registration['id'],
				'file_name'         => $registration['file_name'],
				'procedure_number'  => $registration['procedure_number'],
				'organization_name' => $registration['organization_name'] ?? '',
				'processor_name'    => $registration['processor_name'] ?? '',
				'total_items'       => (int) $registration['total_items'],
				'status'            => $registration['status'],
				'created_at'        => $registration['created_at'],
			);
		}, $registrations)); ?>
	};
</script>
