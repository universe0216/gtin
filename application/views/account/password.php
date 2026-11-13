<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="account-password-page">
	<h1 class="h4 mb-3"><?php echo html_escape(app_line('title_update_password')); ?></h1>
	<div class="app-panel p-4 account-password-panel">
		<?php if ( ! empty($success)): ?>
			<div class="alert alert-success" role="alert"><?php echo html_escape($success); ?></div>
		<?php endif; ?>

		<?php if ( ! empty($error)): ?>
			<div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div>
		<?php endif; ?>

		<?php echo form_open('account/update_password'); ?>
			<div class="form-outline mb-4" data-mdb-input-init>
				<input
					type="password"
					id="current_password"
					name="current_password"
					class="form-control"
					placeholder=" "
					required
					autocomplete="current-password"
				>
				<label class="form-label" for="current_password"><?php echo html_escape(app_line('field_current_password')); ?></label>
			</div>

			<div class="form-outline mb-4" data-mdb-input-init>
				<input
					type="password"
					id="new_password"
					name="new_password"
					class="form-control"
					placeholder=" "
					required
					minlength="6"
					autocomplete="new-password"
				>
				<label class="form-label" for="new_password"><?php echo html_escape(app_line('field_new_password')); ?></label>
			</div>

			<div class="form-outline mb-4" data-mdb-input-init>
				<input
					type="password"
					id="confirm_password"
					name="confirm_password"
					class="form-control"
					placeholder=" "
					required
					minlength="6"
					autocomplete="new-password"
				>
				<label class="form-label" for="confirm_password"><?php echo html_escape(app_line('field_confirm_password')); ?></label>
			</div>

			<p class="text-muted small mb-4"><?php echo html_escape(app_line('admin_password_hint')); ?></p>

			<button type="submit" class="btn btn-primary" data-mdb-ripple-init>
				<i class="fas fa-key me-1"></i> <?php echo html_escape(app_line('account_password_save')); ?>
			</button>
		<?php echo form_close(); ?>
	</div>
</div>
