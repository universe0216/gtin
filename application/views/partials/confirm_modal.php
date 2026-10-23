<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="modal fade" id="appConfirmModal" tabindex="-1" aria-labelledby="appConfirmModalTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appConfirmModalTitle"><?php echo html_escape(app_line('common_confirm')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<div class="modal-body" id="appConfirmModalBody"><?php echo html_escape(app_line('common_are_you_sure')); ?></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" id="appConfirmModalCancelBtn" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_cancel')); ?></button>
				<button type="button" class="btn btn-danger" id="appConfirmModalConfirmBtn" data-mdb-ripple-init><?php echo html_escape(app_line('common_confirm')); ?></button>
			</div>
		</div>
	</div>
</div>
