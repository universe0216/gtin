<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<div class="books-gtin-page">
	<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
		<div>
			<h1 class="h4 mb-1"><?php echo html_escape(app_line('title_gtin_country_codes')); ?></h1>
		</div>
		<div class="d-flex flex-wrap align-items-center gap-2 books-gtin-actions">
			
			<a href="<?php echo site_url('books/gtin_country_code_export'); ?>" class="btn btn-sm btn-outline-primary" data-mdb-ripple-init>
				<i class="fas fa-file-excel me-1"></i> <?php echo html_escape(app_line('books_download')); ?>
			</a>
		</div>
	</div>

	<div class="app-panel p-0">
		<div class="books-gtin-search-wrap p-2">
				<div class="input-group input-group-sm">
					<span class="input-group-text"><i class="fas fa-search"></i></span>
					<input
						type="search"
						class="form-control"
						id="gtinCountryCodeSearch"
						placeholder="<?php echo html_escape(app_line('books_search')); ?>"
						autocomplete="off"
					>
				</div>
			</div>
		<div class="books-gtin-table-scroll">
			<div id="gtinCountryCodeGrid"></div>
		</div>
		<div class="books-gtin-footer d-flex justify-content-between align-items-center gap-3 px-3 py-3 border-top border-secondary border-opacity-25">
			<p class="text-muted small mb-0">
				<?php echo sprintf(app_line('books_visible_count'), '<span id="gtinCountryCodeVisibleCount">'.count($country_codes).'</span>', count($country_codes)); ?>
			</p>
			<p class="text-muted small mb-0"><?php echo html_escape(app_line('books_note')); ?></p>
		</div>
	</div>
</div>

<script>
	window.GTIN_COUNTRY_CODES = <?php echo json_encode(array_map(function ($entry) {
		return array(
			'prefix'  => $entry['prefix'],
			'country' => $entry['country'],
		);
	}, $country_codes)); ?>;
</script>
<script>
	(function () {
		function initCountryCodeGrid() {
			const searchInput = document.getElementById('gtinCountryCodeSearch');
			const visibleCount = document.getElementById('gtinCountryCodeVisibleCount');
			const allEntries = window.GTIN_COUNTRY_CODES || [];

			if (typeof JqxGrid === 'undefined' || !document.getElementById('gtinCountryCodeGrid')) {
				return;
			}

			function prefixRenderer(row, columnfield, value) {
				return JqxGrid.htmlCellRenderer('<code>' + JqxGrid.escapeAttr(value) + '</code>');
			}

			const grid = JqxGrid.local('#gtinCountryCodeGrid', {
				data: allEntries.slice(),
				height: 600,
				pageable: true,
				pagesize: 50,
				pagesizeoptions: ['25', '50', '100', '200'],
				emptyMessage: t('books_empty'),
				datafields: [
					{ name: 'prefix', type: 'string' },
					{ name: 'country', type: 'string' },
				],
				columns: [
					{ text: '#', datafield: '__rownum', sortable: false, filterable: false, width: 70, cellsrenderer: JqxGrid.rowNumberRenderer },
				{ text: t('col_prefix'), datafield: 'prefix', width: 140, cellsrenderer: prefixRenderer },
				{ text: t('col_country'), datafield: 'country' },
				],
			});

			function updateCount(count) {
				if (visibleCount) {
					visibleCount.textContent = String(count);
				}
			}

			function filterEntries() {
				const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

				if (!query) {
					grid.setData(allEntries.slice());
					updateCount(allEntries.length);
					return;
				}

				const filtered = allEntries.filter(function (entry) {
					return (entry.prefix + ' ' + entry.country).toLowerCase().indexOf(query) !== -1;
				});

				grid.setData(filtered);
				updateCount(filtered.length);
			}

			if (searchInput) {
				searchInput.addEventListener('input', filterEntries);
			}
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initCountryCodeGrid);
		} else {
			initCountryCodeGrid();
		}
	})();
</script>
