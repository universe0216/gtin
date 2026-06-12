<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="en" data-mdb-theme="dark">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo html_escape($title ?? 'GTIN'); ?></title>

	<link href="<?php echo base_url('assets/vendor/font-awesome/css/all.min.css'); ?>" rel="stylesheet">
	<link href="<?php echo base_url('assets/vendor/roboto/css/roboto.css'); ?>" rel="stylesheet">
	<link href="<?php echo base_url('assets/vendor/mdb/css/mdb.min.css'); ?>" rel="stylesheet">
	<link href="<?php echo base_url('assets/css/app.css'); ?>" rel="stylesheet">
</head>
<body class="text-body">

<?php $this->load->view('partials/navbar', array('nav_active' => $nav_active ?? '')); ?>

<main class="container-fluid px-4 py-4">
	<?php echo $content; ?>
</main>

<script src="<?php echo base_url('assets/vendor/jquery/js/jquery.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/vendor/mdb/js/mdb.umd.min.js'); ?>"></script>
<script>
	window.APP_BASE_URL = <?php echo json_encode(base_url()); ?>;
</script>
<script src="<?php echo base_url('assets/js/app.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/crud.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/admin.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/image-viewer.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/product_registration.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/organization_registration.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/history.js'); ?>"></script>

</body>
</html>
