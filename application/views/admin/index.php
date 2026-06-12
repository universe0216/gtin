<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
	<h1 class="h3 mb-0">User Management</h1>
	<button type="button" class="btn btn-primary" id="btnAddAccount" data-mdb-ripple-init>
		<i class="fas fa-user-plus me-1"></i> Add User
	</button>
</div>

<div class="app-panel">
	<div class="table-responsive">
		<table class="table table-hover table-dark mb-0" id="adminTable">
			<thead>
				<tr>
					<th scope="col">#</th>
					<th scope="col">Username</th>
					<th scope="col">Full Name</th>
					<th scope="col">Role</th>
					<th scope="col">Status</th>
					<th scope="col" class="text-end" style="width: 140px;">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($accounts)): ?>
					<tr id="emptyRow">
						<td colspan="6" class="text-center text-muted py-4">No users found.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($accounts as $account): ?>
						<tr data-id="<?php echo (int) $account['id']; ?>">
							<td><?php echo (int) $account['id']; ?></td>
							<td><?php echo html_escape($account['username']); ?></td>
							<td><?php echo html_escape($account['full_name']); ?></td>
							<td>
								<?php if ($account['is_admin']): ?>
									<span class="badge bg-primary">Admin</span>
								<?php else: ?>
									<span class="badge bg-secondary">User</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ($account['is_active']): ?>
									<span class="text-success">Active</span>
								<?php else: ?>
									<span class="text-muted">Inactive</span>
								<?php endif; ?>
							</td>
							<td class="text-end">
								<button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-mdb-ripple-init>
									<i class="fas fa-pen"></i>
								</button>
								<?php if ((int) $account['id'] !== (int) $current_user_id): ?>
									<button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-mdb-ripple-init>
										<i class="fas fa-trash"></i>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="accountModalLabel">Add User</h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="accountForm">
				<div class="modal-body">
					<input type="hidden" name="record_id" id="record_id" value="">
					<div id="accountModalAlert" class="alert alert-danger d-none" role="alert"></div>

					<div class="row g-3">
						<div class="col-md-6">
							<div class="form-outline" data-mdb-input-init>
								<input type="text" class="form-control" id="username" name="username" placeholder=" " required>
								<label class="form-label" for="username">Username</label>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-outline" data-mdb-input-init>
								<input type="text" class="form-control" id="full_name" name="full_name" placeholder=" " required>
								<label class="form-label" for="full_name">Full Name</label>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-outline" data-mdb-input-init>
								<input type="password" class="form-control" id="account_password" name="password" placeholder=" ">
								<label class="form-label" for="account_password">Password</label>
							</div>
							<small class="text-muted" id="passwordHint">Minimum 6 characters.</small>
						</div>
						<div class="col-md-6 d-flex align-items-center gap-4 pt-2">
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
								<label class="form-check-label" for="is_active">Active</label>
							</div>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1">
								<label class="form-check-label" for="is_admin">Administrator</label>
							</div>
						</div>
					</div>

					<hr class="border-secondary my-4">

					<h6 class="mb-3">Permissions</h6>
					<div id="permissionsPanel">
						<?php foreach ($permission_groups as $group_key => $group): ?>
							<div class="permission-group mb-3">
								<div class="text-muted small text-uppercase mb-2"><?php echo html_escape($group['label']); ?></div>
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
												<?php echo html_escape($perm_label); ?>
											</label>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="accountSaveBtn">
						<span class="save-label">Save</span>
						<span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Confirm Delete</h5>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">Are you sure you want to delete this user?</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger" id="confirmDeleteAccountBtn">Delete</button>
			</div>
		</div>
	</div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

<script>
	window.ADMIN_CONFIG = <?php echo json_encode(array(
		'baseUrl'         => site_url('admin'),
		'currentUserId'   => (int) $current_user_id,
	)); ?>;
</script>
