<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$modal_id = 'basicModal';
$form_id = 'basicModalForm';
?>
<div
	class="modal fade"
	id="<?php echo $modal_id; ?>"
	tabindex="-1"
	aria-labelledby="<?php echo $modal_id; ?>Label"
	aria-hidden="true"
	data-entity="<?php echo html_escape($entity); ?>"
>
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="<?php echo $modal_id; ?>Label"><?php echo html_escape($title); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="<?php echo $form_id; ?>">
				<div class="modal-body">
					<input type="hidden" name="record_id" id="record_id" value="">
					<div id="basicModalAlert" class="alert alert-danger d-none" role="alert"></div>

					<?php foreach ($fields as $field): ?>
						<div class="form-outline mb-3" data-mdb-input-init>
							<?php if (($field['type'] ?? 'text') === 'textarea'): ?>
								<textarea
									class="form-control"
									id="field_<?php echo html_escape($field['name']); ?>"
									name="<?php echo html_escape($field['name']); ?>"
									rows="3"
									placeholder=" "
									<?php echo ! empty($field['required']) ? 'required' : ''; ?>
								></textarea>
							<?php else: ?>
								<input
									type="<?php echo html_escape($field['type'] ?? 'text'); ?>"
									class="form-control"
									id="field_<?php echo html_escape($field['name']); ?>"
									name="<?php echo html_escape($field['name']); ?>"
									placeholder=" "
									<?php echo ! empty($field['required']) ? 'required' : ''; ?>
								>
							<?php endif; ?>
							<label class="form-label" for="field_<?php echo html_escape($field['name']); ?>">
								<?php echo html_escape($field['label']); ?>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="basicModalSaveBtn">
						<span class="save-label">Save</span>
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>
