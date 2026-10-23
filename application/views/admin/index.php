<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
	<h1 class="h3 mb-0"><?php echo html_escape(app_line('title_user_management')); ?></h1>
	<button type="button" class="btn btn-primary" id="btnAddAccount" data-mdb-ripple-init>
		<i class="fas fa-user-plus me-1"></i> <?php echo html_escape(app_line('admin_add_user')); ?>
	</button>
</div>

<div class="app-panel">
	<div id="adminGrid"></div>
</div>

<div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="accountModalLabel"><?php echo html_escape(app_line('title_add_user')); ?></h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="<?php echo html_escape(app_line('common_close')); ?>"></button>
			</div>
			<form id="accountForm">
				<div class="modal-body">
					<input type="hidden" name="record_id" id="record_id" value="">
					<div id="accountModalAlert" class="alert alert-danger d-none" role="alert"></div>

					<div class="row g-3">
						<div class="col-md-6">
							<div class="form-outline" data-mdb-input-init>
								<input type="text" class="form-control" id="username" name="username" placeholder=" " required>
								<label class="form-label" for="username"><?php echo html_escape(app_line('field_username')); ?></label>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-outline" data-mdb-input-init>
								<input type="text" class="form-control" id="full_name" name="full_name" placeholder=" " required>
								<label class="form-label" for="full_name"><?php echo html_escape(app_line('field_full_name')); ?></label>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-outline" data-mdb-input-init>
								<input type="password" class="form-control" id="account_password" name="password" placeholder=" ">
								<label class="form-label" for="account_password"><?php echo html_escape(app_line('field_password')); ?></label>
							</div>
							<small class="text-muted" id="passwordHint"><?php echo html_escape(app_line('admin_password_hint')); ?></small>
						</div>
						<div class="col-md-6 d-flex align-items-center gap-4 pt-2">
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
								<label class="form-check-label" for="is_active"><?php echo html_escape(app_line('common_active')); ?></label>
							</div>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1">
								<label class="form-check-label" for="is_admin"><?php echo html_escape(app_line('admin_administrator')); ?></label>
							</div>
						</div>
					</div>

					<hr class="border-secondary my-4">

					<h6 class="mb-3"><?php echo html_escape(app_line('admin_permissions')); ?></h6>
					<div id="permissionsPanel">
						<?php foreach ($permission_groups as $group_key => $group): ?>
							<div class="permission-group mb-3">
								<div class="text-muted small text-uppercase mb-2"><?php echo html_escape(app_line('perm_group_'.$group_key)); ?></div>
								<div class="d-flex flex-wrap gap-3">
									<?php foreach ($group['permissions'] as $perm_key => $perm_label): ?>
										<div class="form-check">
											<input
												class="form-check-input permission-check"
												type="checkbox"
												name="permissions[]"
												value="<?php echo html_escape($perm_key); ?>"
												id="perm_<?php echo html_escape(str_replace('.', '_', $perm_key)); ?>"
											>
											<label class="form-check-label" for="perm_<?php echo html_escape(str_replace('.', '_', $perm_key)); ?>">
												<?php echo html_escape(app_line('perm_'.str_replace('.', '_', $perm_key))); ?>
											</label>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-mdb-dismiss="modal"><?php echo html_escape(app_line('common_cancel')); ?></button>
					<button type="submit" class="btn btn-primary" id="accountSaveBtn">
						<span class="save-label"><?php echo html_escape(app_line('common_save')); ?></span>
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	window.ADMIN_CONFIG = <?php echo json_encode(array(
		'baseUrl'         => site_url('admin'),
		'currentUserId'   => (int) $current_user_id,
		'accounts'        => array_map(function ($account) {
			return array(
				'id'        => (int) $account['id'],
				'username'  => $account['username'],
				'full_name' => $account['full_name'],
				'is_admin'  => (int) $account['is_admin'],
				'is_active' => (int) $account['is_active'],
			);
		}, $accounts),
	)); ?>;
</script>
