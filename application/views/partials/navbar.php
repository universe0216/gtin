<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$CI =& get_instance();
$nav_active = $nav_active ?? '';
$auth_user = $CI->auth->user();

$primary_children = array(
	'primary'   => array('label' => 'Overview', 'url' => site_url('primary')),
	'users'     => array('label' => 'Users', 'url' => site_url('users')),
	'locations' => array('label' => 'Locations', 'url' => site_url('locations')),
);

$nav_items = array(
	'products'      => array('label' => 'Products', 'url' => site_url('products'), 'permission' => 'product.view'),
	'organizations' => array('label' => 'Organizations', 'url' => site_url('organizations'), 'permission' => 'organization.view'),
	'procedure'     => array('label' => 'Procedure', 'url' => site_url('procedure'), 'permission' => NULL),
);

$primary_active = ($nav_active === 'primary' || isset($primary_children[$nav_active]));
$show_primary = $CI->auth->can('primary');
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
			<ul class="navbar-nav ms-auto align-items-lg-center">
				<?php if ($show_primary): ?>
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
				<?php endif; ?>
				<?php foreach ($nav_items as $key => $item): ?>
					<?php if ($item['permission'] === NULL || $CI->auth->can($item['permission'])): ?>
						<li class="nav-item">
							<a
								class="nav-link<?php echo ($nav_active === $key) ? ' active fw-semibold' : ''; ?>"
								href="<?php echo $item['url']; ?>"
							><?php echo html_escape($item['label']); ?></a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ($CI->auth->is_admin()): ?>
					<li class="nav-item">
						<a
							class="nav-link<?php echo ($nav_active === 'admin') ? ' active fw-semibold' : ''; ?>"
							href="<?php echo site_url('admin'); ?>"
						>Accounts</a>
					</li>
				<?php endif; ?>
				<li class="nav-item dropdown ms-lg-2">
					<a
						class="nav-link dropdown-toggle"
						href="#"
						id="userDropdown"
						role="button"
						data-mdb-dropdown-init
						aria-expanded="false"
					>
						<i class="fas fa-user-circle me-1"></i><?php echo html_escape($auth_user['full_name']); ?>
					</a>
					<ul class="dropdown-menu dropdown-menu-dark app-dropdown dropdown-menu-end" aria-labelledby="userDropdown">
						<li><span class="dropdown-item-text text-muted small"><?php echo html_escape($auth_user['username']); ?></span></li>
						<li><hr class="dropdown-divider border-secondary"></li>
						<li><a class="dropdown-item" href="<?php echo site_url('login/logout'); ?>"><i class="fas fa-right-from-bracket me-2"></i>Logout</a></li>
					</ul>
				</li>
			</ul>
		</div>
	</div>
</nav>
