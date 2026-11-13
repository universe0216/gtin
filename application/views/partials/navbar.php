<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$CI =& get_instance();
$nav_active = $nav_active ?? '';
$auth_user = $CI->auth->user();

$admin_children = array(
	'admin'          => array('label' => app_line('nav_accounts'), 'url' => site_url('admin')),
	'locations'      => array('label' => app_line('nav_locations'), 'url' => site_url('locations')),
	'categories'     => array('label' => app_line('nav_categories'), 'url' => site_url('categories')),
	'data_transfer'  => array('label' => app_line('nav_data_transfer'), 'url' => site_url('data_transfer')),
);

$production_children = array(
	'products'              => array('label' => app_line('nav_list_productions'), 'url' => site_url('products'), 'permission' => 'product.view'),
	'product_registration'  => array('label' => app_line('nav_register_products'), 'url' => site_url('product_registration')),
	'registration_data'     => array('label' => app_line('nav_manage_processing_data'), 'url' => site_url('registration_data')),
);

$organization_children = array(
	'organizations'              => array('label' => app_line('nav_list_organizations'), 'url' => site_url('organizations'), 'permission' => 'organization.view'),
	'organization_registration'  => array('label' => app_line('nav_register_organizations'), 'url' => site_url('organization_registration'), 'permission' => 'organization.edit'),
);

$history_children = array(
	'history_products'       => array('label' => app_line('nav_products'), 'url' => site_url('history/products')),
	'history_organizations'  => array('label' => app_line('nav_organizations'), 'url' => site_url('history/organizations')),
	'products_archieve'      => array('label' => app_line('nav_products_archieve'), 'url' => site_url('products_archieve'), 'permission' => 'product.view'),
	'organization_archieve'  => array('label' => app_line('nav_organizations_archieve'), 'url' => site_url('organization_archieve'), 'permission' => 'organization.view'),
);

$books_children = array(
	'books_gtin_country_code' => array('label' => app_line('nav_gtin_country_code'), 'url' => site_url('books/gtin_country_code')),
);

$tools_children = array(
	'tools_verify_checksum' => array('label' => app_line('nav_verify_checksum'), 'url' => site_url('tools/verify_checksum')),
);

$production_active = isset($production_children[$nav_active]);
$organization_active = isset($organization_children[$nav_active]);
$history_active = isset($history_children[$nav_active]);
$books_active = isset($books_children[$nav_active]);
$tools_active = isset($tools_children[$nav_active]);
$admin_active = isset($admin_children[$nav_active]);
$show_admin = $CI->auth->is_admin();
?>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
	<div class="container-fluid px-4">
		<a class="navbar-brand fw-bold" href="<?php echo site_url('product_registration'); ?>">GTIN</a>
		<button
			class="navbar-toggler"
			type="button"
			data-mdb-collapse-init
			data-mdb-target="#mainNavbar"
			aria-controls="mainNavbar"
			aria-expanded="false"
			aria-label="<?php echo html_escape(app_line('nav_toggle')); ?>"
		>
			<i class="fas fa-bars"></i>
		</button>
		<div class="collapse navbar-collapse" id="mainNavbar">
			<ul class="navbar-nav ms-auto align-items-lg-center">
				
				<?php
					$show_productions = FALSE;
					foreach ($production_children as $item) {
						if ( ! isset($item['permission']) || $CI->auth->can($item['permission'])) {
							$show_productions = TRUE;
							break;
						}
					}
				?>
				<?php if ($show_productions): ?>
				<li class="nav-item dropdown">
					<a
						class="nav-link dropdown-toggle<?php echo $production_active ? ' active fw-semibold' : ''; ?>"
						href="#"
						id="productionsDropdown"
						role="button"
						data-mdb-dropdown-init
						aria-expanded="false"
					><?php echo html_escape(app_line('nav_productions')); ?></a>
					<ul class="dropdown-menu dropdown-menu-dark app-dropdown" aria-labelledby="productionsDropdown">
						<?php foreach ($production_children as $key => $item): ?>
							<?php if ( ! isset($item['permission']) || $CI->auth->can($item['permission'])): ?>
							<li>
								<a
									class="dropdown-item<?php echo ($nav_active === $key) ? ' active' : ''; ?>"
									href="<?php echo $item['url']; ?>"
								><?php echo html_escape($item['label']); ?></a>
							</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</li>
				<?php endif; ?>
				<?php
					$show_organizations = FALSE;
					foreach ($organization_children as $item) {
						if ( ! isset($item['permission']) || $CI->auth->can($item['permission'])) {
							$show_organizations = TRUE;
							break;
						}
					}
				?>
				<?php if ($show_organizations): ?>
				<li class="nav-item dropdown">
					<a
						class="nav-link dropdown-toggle<?php echo $organization_active ? ' active fw-semibold' : ''; ?>"
						href="#"
						id="organizationsDropdown"
						role="button"
						data-mdb-dropdown-init
						aria-expanded="false"
					><?php echo html_escape(app_line('nav_organizations')); ?></a>
					<ul class="dropdown-menu dropdown-menu-dark app-dropdown" aria-labelledby="organizationsDropdown">
						<?php foreach ($organization_children as $key => $item): ?>
							<?php if ( ! isset($item['permission']) || $CI->auth->can($item['permission'])): ?>
							<li>
								<a
									class="dropdown-item<?php echo ($nav_active === $key) ? ' active' : ''; ?>"
									href="<?php echo $item['url']; ?>"
								><?php echo html_escape($item['label']); ?></a>
							</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</li>
				<?php endif; ?>
				<li class="nav-item dropdown">
					<a
						class="nav-link dropdown-toggle<?php echo $history_active ? ' active fw-semibold' : ''; ?>"
						href="#"
						id="historyDropdown"
						role="button"
						data-mdb-dropdown-init
						aria-expanded="false"
					><?php echo html_escape(app_line('nav_history')); ?></a>
					<ul class="dropdown-menu dropdown-menu-dark app-dropdown" aria-labelledby="historyDropdown">
						<?php foreach ($history_children as $key => $item): ?>
							<?php if ( ! isset($item['permission']) || $CI->auth->can($item['permission'])): ?>
							<li>
								<a
									class="dropdown-item<?php echo ($nav_active === $key) ? ' active' : ''; ?>"
									href="<?php echo $item['url']; ?>"
								><?php echo html_escape($item['label']); ?></a>
							</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</li>
				<li class="nav-item dropdown">
					<a
						class="nav-link dropdown-toggle<?php echo $books_active ? ' active fw-semibold' : ''; ?>"
						href="#"
						id="booksDropdown"
						role="button"
						data-mdb-dropdown-init
						aria-expanded="false"
					><?php echo html_escape(app_line('nav_books')); ?></a>
					<ul class="dropdown-menu dropdown-menu-dark app-dropdown" aria-labelledby="booksDropdown">
						<?php foreach ($books_children as $key => $item): ?>
							<li>
								<a
									class="dropdown-item<?php echo ($nav_active === $key) ? ' active' : ''; ?>"
									href="<?php echo $item['url']; ?>"
								><?php echo html_escape($item['label']); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</li>
				<li class="nav-item dropdown">
					<a
						class="nav-link dropdown-toggle<?php echo $tools_active ? ' active fw-semibold' : ''; ?>"
						href="#"
						id="toolsDropdown"
						role="button"
						data-mdb-dropdown-init
						aria-expanded="false"
					><?php echo html_escape(app_line('nav_tools')); ?></a>
					<ul class="dropdown-menu dropdown-menu-dark app-dropdown" aria-labelledby="toolsDropdown">
						<?php foreach ($tools_children as $key => $item): ?>
							<li>
								<a
									class="dropdown-item<?php echo ($nav_active === $key) ? ' active' : ''; ?>"
									href="<?php echo $item['url']; ?>"
								><?php echo html_escape($item['label']); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</li>
				<?php if ($show_admin): ?>
					<li class="nav-item dropdown">
						<a
							class="nav-link dropdown-toggle<?php echo $admin_active ? ' active fw-semibold' : ''; ?>"
							href="#"
							id="adminDropdown"
							role="button"
							data-mdb-dropdown-init
							aria-expanded="false"
						><?php echo html_escape(app_line('nav_admin')); ?></a>
						<ul class="dropdown-menu dropdown-menu-dark app-dropdown" aria-labelledby="adminDropdown">
							<?php foreach ($admin_children as $key => $item): ?>
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
				<?php $this->load->view('partials/language_switcher'); ?>
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
						<li>
							<a class="dropdown-item<?php echo ($nav_active === 'account_password') ? ' active' : ''; ?>" href="<?php echo site_url('account/password'); ?>">
								<i class="fas fa-key me-2"></i><?php echo html_escape(app_line('nav_update_password')); ?>
							</a>
						</li>
						<li><hr class="dropdown-divider border-secondary"></li>
						<li><a class="dropdown-item" href="<?php echo site_url('login/logout'); ?>"><i class="fas fa-right-from-bracket me-2"></i><?php echo html_escape(app_line('nav_logout')); ?></a></li>
					</ul>
				</li>
			</ul>
		</div>
	</div>
</nav>
