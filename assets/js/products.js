(function () {
	'use strict';

	if (typeof window.PRODUCTS_CONFIG === 'undefined') {
		return;
	}

	const config = window.PRODUCTS_CONFIG;
	const apiUrl = config.apiUrl.replace(/\/$/, '');
	const columns = config.columns || [];
	const perPage = config.perPage || 10;
	const perPageOptions = (config.perPageOptions || [5, 10, 20, 50]).map(String);

	const searchForm = document.getElementById('productsSearchForm');
	const searchInput = document.getElementById('productsSearchInput');
	const clearBtn = document.getElementById('productsClearBtn');
	const toolbarMeta = document.getElementById('productsToolbarMeta');

	if (typeof JqxGrid === 'undefined' || !document.getElementById('productsGrid')) {
		return;
	}

	let currentSearch = '';

	function updateMeta(data) {
		if (!toolbarMeta) {
			return;
		}

		if (data && (data.total || 0) > 0) {
			toolbarMeta.textContent = data.range_start + '–' + data.range_end + ' of ' + data.total;
			toolbarMeta.classList.remove('d-none');
		} else {
			toolbarMeta.textContent = currentSearch ? config.emptySearchMessage : '';
			toolbarMeta.classList.toggle('d-none', !currentSearch);
		}
	}

	const grid = JqxGrid.remote('#productsGrid', {
		url: apiUrl,
		columns: columns.map(function (column) {
			return { text: column.label, datafield: column.key };
		}),
		datafields: columns.map(function (column) {
			return { name: column.key, type: 'string' };
		}),
		pagesize: perPage,
		pagesizeoptions: perPageOptions,
		selectionmode: 'none',
		emptyMessage: config.emptyMessage,
		getParams: function () {
			return currentSearch ? { q: currentSearch } : {};
		},
		onLoad: updateMeta,
		onError: function () {
			showToast(t('common_failed_load_records'), 'error');
		},
	});

	if (!grid) {
		return;
	}

	function applySearch() {
		grid.gotoFirst();
		grid.reload();
	}

	const scheduleSearch = debounce(applySearch, 300);

	if (searchForm) {
		searchForm.addEventListener('submit', function (event) {
			event.preventDefault();
			currentSearch = searchInput ? searchInput.value.trim() : '';

			if (clearBtn) {
				clearBtn.classList.toggle('d-none', currentSearch === '');
			}

			applySearch();
		});
	}

	if (searchInput) {
		searchInput.addEventListener('input', function () {
			currentSearch = searchInput.value.trim();

			if (clearBtn) {
				clearBtn.classList.toggle('d-none', currentSearch === '');
			}

			scheduleSearch();
		});
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			currentSearch = '';

			if (searchInput) {
				searchInput.value = '';
			}

			clearBtn.classList.add('d-none');
			applySearch();
		});
	}
})();
