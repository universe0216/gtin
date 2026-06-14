(function () {
	'use strict';

	if (typeof window.ENTITY_LIST_CONFIG === 'undefined') {
		return;
	}

	const config = window.ENTITY_LIST_CONFIG;
	const apiUrl = config.apiUrl.replace(/\/$/, '');
	const columns = config.columns || [];
	const perPage = config.perPage || 10;
	const perPageOptions = (config.perPageOptions || [5, 10, 20, 50]).map(String);
	const detailEnabled = !!(config.detail && config.detail.enabled);

	const searchForm = document.getElementById('entityListSearchForm');
	const searchInput = document.getElementById('entityListSearchInput');
	const clearBtn = document.getElementById('entityListClearBtn');
	const toolbarMeta = document.getElementById('entityListToolbarMeta');

	if (typeof JqxGrid === 'undefined' || !document.getElementById('entityListGrid')) {
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

	const grid = JqxGrid.remote('#entityListGrid', {
		url: apiUrl,
		columns: columns.map(function (column) {
			return { text: column.label, datafield: column.key };
		}),
		datafields: columns.map(function (column) {
			return { name: column.key, type: 'string' };
		}),
		pagesize: perPage,
		pagesizeoptions: perPageOptions,
		selectionmode: detailEnabled ? 'singlerow' : 'none',
		emptyMessage: config.emptyMessage,
		getParams: function () {
			return currentSearch ? { q: currentSearch } : {};
		},
		onLoad: updateMeta,
		onError: function () {
			showToast('Failed to load records.', 'error');
		},
	});

	if (!grid) {
		return;
	}

	if (detailEnabled) {
		grid.$el.addClass('entity-list-grid-clickable');
		grid.$el.on('rowclick', function (event) {
			const args = event.args;
			const rowdata = args.row && args.row.bounddata
				? args.row.bounddata
				: grid.$el.jqxGrid('getrowdata', args.rowindex);

			if (rowdata && rowdata.id) {
				document.dispatchEvent(new CustomEvent('entityListRowSelected', {
					detail: { id: rowdata.id },
				}));
			}
		});
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

	document.addEventListener('entityListRefresh', function () {
		grid.reload();
	});
})();
