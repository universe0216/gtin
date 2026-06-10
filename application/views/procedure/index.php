<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="procedure-page">
	<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
		<div>
			<h1 class="h3 mb-2">Procedure</h1>
			<p class="text-muted mb-0">Standard workflow for managing goods data across the GTIN platform.</p>
		</div>
		<a href="<?php echo site_url('products'); ?>" class="btn btn-primary" data-mdb-ripple-init>
			<i class="fas fa-play me-1"></i> Start Procedure
		</a>
	</div>

	<div class="row g-4 mb-4">
		<div class="col-md-4">
			<div class="procedure-stat app-panel p-4 h-100">
				<div class="procedure-stat-icon mb-3">
					<i class="fas fa-list-check"></i>
				</div>
				<h2 class="h6 text-uppercase text-muted mb-1">Total Steps</h2>
				<p class="display-6 mb-0"><?php echo count($steps); ?></p>
			</div>
		</div>
		<div class="col-md-4">
			<div class="procedure-stat app-panel p-4 h-100">
				<div class="procedure-stat-icon mb-3">
					<i class="fas fa-clock"></i>
				</div>
				<h2 class="h6 text-uppercase text-muted mb-1">Estimated Time</h2>
				<p class="display-6 mb-0">~15 min</p>
			</div>
		</div>
		<div class="col-md-4">
			<div class="procedure-stat app-panel p-4 h-100">
				<div class="procedure-stat-icon mb-3">
					<i class="fas fa-shield-halved"></i>
				</div>
				<h2 class="h6 text-uppercase text-muted mb-1">Validation</h2>
				<p class="display-6 mb-0">Required</p>
			</div>
		</div>
	</div>

	<div class="app-panel p-4">
		<h2 class="h5 mb-4">Workflow Steps</h2>
		<div class="procedure-timeline">
			<?php foreach ($steps as $step): ?>
				<div class="procedure-step">
					<div class="procedure-step-marker">
						<span><?php echo (int) $step['number']; ?></span>
					</div>
					<div class="procedure-step-body">
						<div class="d-flex align-items-center gap-2 mb-2">
							<i class="fas <?php echo html_escape($step['icon']); ?> text-primary"></i>
							<h3 class="h6 mb-0"><?php echo html_escape($step['title']); ?></h3>
						</div>
						<p class="text-muted mb-0"><?php echo html_escape($step['description']); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
