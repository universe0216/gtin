<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="en" data-mdb-theme="dark">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo html_escape($title ?? 'Login'); ?> - GTIN</title>
	<link href="<?php echo base_url('assets/vendor/font-awesome/css/all.min.css'); ?>" rel="stylesheet">
	<link href="<?php echo base_url('assets/vendor/roboto/css/roboto.css'); ?>" rel="stylesheet">
	<link href="<?php echo base_url('assets/vendor/mdb/css/mdb.min.css'); ?>" rel="stylesheet">
	<link href="<?php echo base_url('assets/css/app.css'); ?>" rel="stylesheet">
</head>
<body class="text-body auth-page">
	<div class="auth-wrapper">
		<div class="auth-card app-panel p-4 p-md-5">
			<div class="text-center mb-4">
				<h1 class="h4 mb-1">GTIN</h1>
				<p class="text-muted mb-0">Sign in to your account</p>
			</div>

			<?php if ( ! empty($error)): ?>
				<div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div>
			<?php endif; ?>

			<?php echo form_open('login/authenticate'); ?>
				<input type="hidden" name="redirect" value="<?php echo html_escape($redirect ?? ''); ?>">

				<div class="form-outline mb-4" data-mdb-input-init>
					<input type="text" id="username" name="username" class="form-control" placeholder=" " required autofocus>
					<label class="form-label" for="username">Username</label>
				</div>

				<div class="form-outline mb-4" data-mdb-input-init>
					<input type="password" id="password" name="password" class="form-control" placeholder=" " required>
					<label class="form-label" for="password">Password</label>
				</div>

				<button type="submit" class="btn btn-primary btn-block w-100" data-mdb-ripple-init>
					<i class="fas fa-right-to-bracket me-1"></i> Login
				</button>
			<?php echo form_close(); ?>
		</div>
	</div>
	<script src="<?php echo base_url('assets/vendor/mdb/js/mdb.umd.min.js'); ?>"></script>
</body>
</html>
