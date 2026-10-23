<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$languages = app_languages();
$current = app_language();
?>
<li class="nav-item dropdown">
	<a
		class="nav-link dropdown-toggle"
		href="#"
		id="languageDropdown"
		role="button"
		data-mdb-dropdown-init
		aria-expanded="false"
	><?php echo html_escape($languages[$current]); ?></a>
	<ul class="dropdown-menu dropdown-menu-dark app-dropdown" aria-labelledby="languageDropdown">
		<?php foreach ($languages as $code => $label): ?>
			<li>
				<a
					class="dropdown-item<?php echo $code === $current ? ' active' : ''; ?>"
					href="<?php echo site_url('language/set/'.$code); ?>"
				><?php echo html_escape($label); ?></a>
			</li>
		<?php endforeach; ?>
	</ul>
</li>
