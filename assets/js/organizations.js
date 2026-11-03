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

	const columnWidths = {
		name: 240,
		reg_number: 180,
		gs1_prefix: 140,
		parent_org: 200,
		phone: 150,
		init_date: 150,
		reg_date: 160,
		expiry_date: 150,
	};

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

	function gridHeight() {
		const panel = document.querySelector('#organizationsPage .entity-list-panel');
		const toolbar = document.querySelector('#organizationsPage .entity-list-toolbar');

		if (!panel) {
			return 420;
		}

		return Math.max(240, panel.clientHeight - (toolbar ? toolbar.offsetHeight : 0) - 8);
	}

	function indexRenderer(row) {
		let number = row + 1;
		const host = $('#organizationsGrid');

		if (host.length) {
			const paging = host.jqxGrid('getpaginginformation');

			if (paging && paging.pagesize && row < paging.pagesize) {
				number = (paging.pagenum || 0) * paging.pagesize + row + 1;
			}
		}

		return '<div class="jqx-grid-cell-leftalign" style="margin-top:6px;">' + number + '</div>';
	}

	const datafields = [
		{ name: 'id', type: 'number' },
		{ name: '__rownum', type: 'number' },
	].concat(columns.map(function (column) {
		return { name: column.key, type: 'string' };
	}));

	const gridColumns = [
		{
			text: '#',
			datafield: '__rownum',
			width: 56,
			pinned: true,
			sortable: false,
			filterable: false,
			cellsalign: 'right',
			align: 'right',
			cellsrenderer: indexRenderer,
		},
	].concat(columns.map(function (column) {
		return {
			text: column.label,
			datafield: column.key,
			width: columnWidths[column.key] || 160,
		};
	}));

	const grid = JqxGrid.remote('#organizationsGrid', {
		url: apiUrl,
		columns: gridColumns,
		datafields: datafields,
		pagesize: perPage,
		pagesizeoptions: perPageOptions,
		selectionmode: detailEnabled ? 'singlerow' : 'none',
		height: gridHeight(),
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

	window.addEventListener('resize', function () {
		grid.$el.jqxGrid('height', gridHeight());
	});

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
