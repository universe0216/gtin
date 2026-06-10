<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$nav_active = $nav_active ?? '';

$primary_children = array(
	'users'     => array('label' => 'Users', 'url' => site_url('users')),
	'locations' => array('label' => 'Locations', 'url' => site_url('locations')),
);

$primary_active = ($nav_active === 'primary' || isset($primary_children[$nav_active]));

$nav_items = array(
	'products'      => array('label' => 'Products', 'url' => site_url('products')),
	'organizations' => array('label' => 'Organizations', 'url' => site_url('organizations')),
	'procedure'     => array('label' => 'Procedure', 'url' => site_url('procedure')),
);
?>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
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
				<li class="nav-item dropdown">
					<a
						class="nav-link dropdown-toggle<?php echo $primary_active ? ' active fw-semibold' : ''; ?>"
						href="#"
						id="primaryDropdown"
						role="button"
						data-mdb-dropdown-init
						aria-expanded="false"
					>Primary</a>
					<ul class="dropdown-menu dropdown-menu-dark app-dropdown" aria-labelledby="primaryDropdown">
						<?php foreach ($primary_children as $key => $item): ?>
							<li>
								<a
									class="dropdown-item<?php echo ($nav_active === $key) ? ' active' : ''; ?>"
									href="<?php echo $item['url']; ?>"
								><?php echo html_escape($item['label']); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</li>
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
