<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$nav_items = array(
	'primary'       => array('label' => 'Primary', 'url' => site_url('primary')),
	'products'      => array('label' => 'Products', 'url' => site_url('products')),
	'organizations' => array('label' => 'Organizations', 'url' => site_url('organizations')),
);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
	<div class="container">
		<a class="navbar-brand fw-bold" href="<?php echo site_url('primary'); ?>">GTIN</a>
		<button
			class="navbar-toggler"
			type="button"
			data-mdb-collapse-init
			data-mdb-target="#mainNavbar"
			aria-controls="mainNavbar"
			aria-expanded="false"
			aria-label="Toggle navigation"
		>
			<i class="fas fa-bars"></i>
		</button>
		<div class="collapse navbar-collapse" id="mainNavbar">
			<ul class="navbar-nav ms-auto">
				<?php foreach ($nav_items as $key => $item): ?>
					<li class="nav-item">
						<a
							class="nav-link<?php echo ($nav_active === $key) ? ' active fw-semibold' : ''; ?>"
							href="<?php echo $item['url']; ?>"
						><?php echo html_escape($item['label']); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</nav>
