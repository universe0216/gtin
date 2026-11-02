<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="modal fade" id="appAlertModal" tabindex="-1" aria-labelledby="appAlertModalTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appAlertModalTitle">
					<i class="fas fa-triangle-exclamation" id="appAlertModalIcon" aria-hidden="true"></i>
					<span id="appAlertModalTitleText"><?php echo html_escape(app_line('common_alert')); ?></span>
				</h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body app-alert-message" id="appAlertModalBody"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" id="appAlertModalOkBtn" data-mdb-ripple-init><?php echo html_escape(app_line('common_ok')); ?></button>
			</div>
		</div>
	</div>
</div>
