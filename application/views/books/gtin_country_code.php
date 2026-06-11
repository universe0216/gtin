<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="books-gtin-page">
	<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
		<div>
			<h1 class="h4 mb-1">GTIN Country Codes</h1>
			<p class="text-muted small mb-0">GS1 prefix ranges used in GTIN-13 / EAN-13 barcodes.</p>
		</div>
		<div class="books-gtin-search-wrap">
			<div class="input-group input-group-sm">
				<span class="input-group-text"><i class="fas fa-search"></i></span>
				<input
					type="search"
					class="form-control"
					id="gtinCountryCodeSearch"
					placeholder="Search prefix or country..."
					autocomplete="off"
				>
			</div>
		</div>
	</div>

	<div class="app-panel p-0">
		<div class="table-responsive books-gtin-table-scroll">
			<table class="table table-hover align-middle mb-0 procedure-data-table" id="gtinCountryCodeTable">
				<thead>
					<tr>
						<th class="procedure-row-index">#</th>
						<th style="width: 8rem;">Prefix</th>
						<th>Country / Region</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($country_codes as $index => $entry): ?>
						<tr data-search="<?php echo html_escape(strtolower($entry['prefix'].' '.$entry['country'])); ?>">
							<td class="procedure-row-index text-muted"><?php echo (int) $index + 1; ?></td>
							<td><code><?php echo html_escape($entry['prefix']); ?></code></td>
							<td><?php echo html_escape($entry['country']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="books-gtin-footer d-flex justify-content-between align-items-center gap-3 px-3 py-3 border-top border-secondary border-opacity-25">
			<p class="text-muted small mb-0">
				<span id="gtinCountryCodeVisibleCount"><?php echo count($country_codes); ?></span> of <?php echo count($country_codes); ?> entries
			</p>
			<p class="text-muted small mb-0">First 3 digits identify the GS1 Member Organisation.</p>
		</div>
	</div>
</div>

<script>
	(function () {
		const searchInput = document.getElementById('gtinCountryCodeSearch');
		const table = document.getElementById('gtinCountryCodeTable');
		const visibleCount = document.getElementById('gtinCountryCodeVisibleCount');

		if (!searchInput || !table) {
			return;
		}

		const rows = Array.from(table.querySelectorAll('tbody tr'));
		const total = rows.length;

		function filterRows() {
			const query = searchInput.value.trim().toLowerCase();
			let shown = 0;

			rows.forEach(function (row) {
				const matches = !query || (row.getAttribute('data-search') || '').indexOf(query) !== -1;
				row.classList.toggle('d-none', !matches);

				if (matches) {
					shown += 1;
				}
			});

			visibleCount.textContent = String(shown);
		}

		searchInput.addEventListener('input', filterRows);
	})();
</script>
