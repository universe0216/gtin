(function () {
	'use strict';

	if (typeof window.ORGANIZATIONS_CONFIG === 'undefined') {
		return;
	}

	const config = window.ORGANIZATIONS_CONFIG;
	const apiUrl = config.apiUrl.replace(/\/$/, '');
	const columns = config.columns || [];
	const perPage = config.perPage || 10;
	const perPageOptions = (config.perPageOptions || [5, 10, 20, 50]).map(String);
	const detailEnabled = !!(config.detail && config.detail.enabled);

	const searchForm = document.getElementById('organizationsSearchForm');
	const searchInput = document.getElementById('organizationsSearchInput');
	const clearBtn = document.getElementById('organizationsClearBtn');
	const toolbarMeta = document.getElementById('organizationsToolbarMeta');

	if (typeof JqxGrid === 'undefined' || !document.getElementById('organizationsGrid')) {
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

	const datafields = columns.map(function (column) {
		return { name: column.key, type: 'string' };
	});

	if (detailEnabled && !datafields.some(function (field) { return field.name === 'id'; })) {
		datafields.unshift({ name: 'id', type: 'number' });
	}

	const grid = JqxGrid.remote('#organizationsGrid', {
		url: apiUrl,
		columns: columns.map(function (column) {
			return { text: column.label, datafield: column.key };
		}),
		datafields: datafields,
		pagesize: perPage,
		pagesizeoptions: perPageOptions,
		selectionmode: detailEnabled ? 'singlerow' : 'none',
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

	if (detailEnabled) {
		grid.$el.addClass('entity-list-grid-clickable');
		grid.$el.on('rowdoubleclick', function (event) {
			const args = event.args || {};
			const row = args.row && args.row.bounddata ? args.row.bounddata : args.row;
			let organizationId = row && row.id;

			if (!organizationId) {
				const index = args.index !== undefined ? args.index : args.boundIndex;
				const rowdata = grid.$el.jqxGrid('getrowdata', index);
				organizationId = rowdata && rowdata.id;
			}

			if (organizationId) {
				document.dispatchEvent(new CustomEvent('organizationRowSelected', {
					detail: { id: organizationId },
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

	document.addEventListener('organizationsListRefresh', function () {
		grid.reload();
	});
})();
