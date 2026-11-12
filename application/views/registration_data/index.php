<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$editor_ids = array();

foreach ($editors as $editor)
{
	$editor_ids[(int) $editor['id']] = TRUE;
}
?>
<div class="registration-data-page">
	<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
		<h1 class="h4 mb-0"><?php echo html_escape($title); ?></h1>
		<button type="button" class="btn btn-sm btn-primary" id="registrationDataUploadBtn" data-mdb-ripple-init>
			<i class="fas fa-upload me-1"></i> <?php echo html_escape(app_line('common_upload_files')); ?>
		</button>
	</div>

	<div class="registration-data-split">
		<section class="app-panel registration-data-panel">
			<div class="registration-data-scroll">
				<table class="table table-hover table-sm procedure-data-table mb-0" id="registrationDataTable">
					<thead>
						<tr>
							<th class="registration-data-index-col">#</th>
							<th><?php echo html_escape(app_line('reg_file')); ?></th>
							<th><?php echo html_escape(app_line('reg_procedure')); ?></th>
							<th><?php echo html_escape(app_line('reg_organization')); ?></th>
							<th><?php echo html_escape(app_line('common_status')); ?></th>
							<th class="text-end"><?php echo html_escape(app_line('reg_products')); ?></th>
							<th class="registration-data-user-col"><?php echo html_escape(app_line('reg_data_user')); ?></th>
							<th class="registration-data-actions-col text-end"><?php echo html_escape(app_line('common_actions')); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($registrations)): ?>
							<tr>
								<td colspan="8" class="text-muted"><?php echo html_escape(app_line('reg_data_empty')); ?></td>
							</tr>
						<?php else: ?>
							<?php foreach ($registrations as $index => $registration): ?>
								<?php
									$account_id = (int) $registration['account_id'];
									$current_label = trim((string) ($registration['processor_name'] ?? ''));

									if ($current_label === '')
									{
										$current_label = trim((string) ($registration['processor_username'] ?? ''));
									}
								?>
								<tr class="registration-data-row" data-registration-id="<?php echo (int) $registration['id']; ?>">
									<td class="registration-data-index"><?php echo (int) $index + 1; ?></td>
									<td><?php echo html_escape($registration['file_name']); ?></td>
									<td><?php echo html_escape($registration['procedure_number']); ?></td>
									<td><?php echo html_escape($registration['organization_name']); ?></td>
									<td><?php echo html_escape($registration['status']); ?></td>
									<td class="text-end"><?php echo (int) $registration['total_products']; ?></td>
									<td>
										<select
											class="form-select form-select-sm registration-data-user"
											data-registration-id="<?php echo (int) $registration['id']; ?>"
											data-account-id="<?php echo $account_id; ?>"
											aria-label="<?php echo html_escape(app_line('reg_data_user')); ?>"
										>
											<?php if ($account_id && empty($editor_ids[$account_id])): ?>
												<option value="<?php echo $account_id; ?>" selected>
													<?php echo html_escape($current_label); ?>
												</option>
											<?php endif; ?>
											<?php foreach ($editors as $editor): ?>
												<?php
													$label = trim((string) $editor['full_name']) !== ''
														? $editor['full_name']
														: $editor['username'];
												?>
												<option value="<?php echo (int) $editor['id']; ?>"<?php echo (int) $editor['id'] === $account_id ? ' selected' : ''; ?>>
													<?php echo html_escape($label); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td class="text-end">
										<button
											type="button"
											class="btn btn-sm btn-outline-danger registration-data-delete"
											data-registration-id="<?php echo (int) $registration['id']; ?>"
											data-file-name="<?php echo html_escape($registration['file_name']); ?>"
											title="<?php echo html_escape(app_line('common_delete')); ?>"
											aria-label="<?php echo html_escape(app_line('common_delete')); ?>"
										>
											<i class="fas fa-trash"></i>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="app-panel registration-data-panel">
			<div class="registration-data-scroll">
				<table class="table table-sm procedure-data-table mb-0" id="registrationDataItemsTable">
					<thead>
						<tr>
							<th>#</th>
							<th><?php echo html_escape(app_line('reg_product_number')); ?></th>
							<th><?php echo html_escape(app_line('reg_product_name')); ?></th>
							<th><?php echo html_escape(app_line('common_status')); ?></th>
							<th><?php echo html_escape(app_line('reg_barcode_title')); ?></th>
							<th><?php echo html_escape(app_line('reg_data_message')); ?></th>
						</tr>
					</thead>
					<tbody id="registrationDataItemsBody">
						<tr>
							<td colspan="6" class="text-muted" id="registrationDataItemsEmpty"><?php echo html_escape(app_line('reg_data_select')); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</div>
</div>

<div class="modal fade" id="registrationDataUploadModal" tabindex="-1" aria-labelledby="registrationDataUploadModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="registrationDataUploadModalLabel"><?php echo html_escape(app_line('reg_upload_files_title')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<form class="procedure-upload-form" enctype="multipart/form-data">
				<div class="modal-body procedure-upload-form-body">
					<p class="text-muted small mb-3">
						<?php echo html_escape(app_line('reg_zip_rule_product')); ?>
					</p>

					<div class="procedure-upload-zone mb-3">
						<input type="file" name="zip_files[]" accept=".zip,.xls,.xlsx,application/zip,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" multiple class="procedure-upload-input">
						<div class="procedure-upload-content">
							<i class="fas fa-file-arrow-up fa-2x text-primary mb-3"></i>
							<p class="mb-1 fw-semibold"><?php echo html_escape(app_line('reg_drop_files')); ?></p>
							<p class="text-muted small mb-0"><?php echo html_escape(app_line('reg_multi_product')); ?></p>
						</div>
					</div>

					<div class="procedure-selected-files d-none mb-0"></div>
				</div>
				<div class="modal-footer procedure-upload-actions">
					<span class="text-muted small procedure-upload-hint me-auto"><?php echo html_escape(app_line('reg_select_files')); ?></span>
					<button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal" data-mdb-ripple-init><?php echo html_escape(app_line('common_cancel')); ?></button>
					<button type="submit" class="btn btn-primary procedure-upload-submit" data-mdb-ripple-init disabled>
						<i class="fas fa-upload me-1"></i> <?php echo html_escape(app_line('reg_upload_process')); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	window.REGISTRATION_DATA_CONFIG = <?php echo json_encode(array(
		'baseUrl'     => site_url('registration_data'),
		'uploadUrl'   => site_url('product_registration/upload'),
		'accountId'   => (int) ($account['id'] ?? 0),
		'accountName' => trim((string) ($account['full_name'] ?? '')) !== ''
			? $account['full_name']
			: ($account['username'] ?? ''),
		'editors'     => array_map(function ($editor) {
			return array(
				'id'        => (int) $editor['id'],
				'username'  => $editor['username'],
				'full_name' => $editor['full_name'],
			);
		}, $editors),
	)); ?>;
</script>
