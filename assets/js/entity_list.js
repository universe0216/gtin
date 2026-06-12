(function () {
	'use strict';

	if (typeof window.ENTITY_LIST_CONFIG === 'undefined') {
		return;
	}

	const config = window.ENTITY_LIST_CONFIG;
	const apiUrl = config.apiUrl.replace(/\/$/, '');
	const columns = config.columns || [];
	const perPage = config.perPage || 10;
	const perPageOptions = config.perPageOptions || [5, 10, 20, 50];

	const searchForm = document.getElementById('entityListSearchForm');
	const searchInput = document.getElementById('entityListSearchInput');
	const clearBtn = document.getElementById('entityListClearBtn');
	const tableBody = document.getElementById('entityListTableBody');
	const toolbarMeta = document.getElementById('entityListToolbarMeta');
	const loadingEl = document.getElementById('entityListLoading');
	const paginationEl = document.getElementById('entityListPagination');
	const paginationNav = document.getElementById('entityListPaginationNav');
	const paginationList = document.getElementById('entityListPaginationList');
	const pageMeta = document.getElementById('entityListPageMeta');
	const perPageSelect = document.getElementById('entityListPerPageSelect');
	const entityListPage = document.getElementById('entityListPage');
	const tableHeadRow = document.getElementById('entityListTableHeadRow');
	const sortableTable = window.SortableTable;

	const state = {
		page: 1,
		search: '',
		perPage: perPage,
		sort: '',
		dir: '',
		request: null,
	};

	function setLoading(isLoading) {
		if (loadingEl) {
			loadingEl.classList.toggle('d-none', !isLoading);
		}
	}

	function normalizePerPage(value) {
		const parsed = parseInt(value || String(perPage), 10);
		return perPageOptions.indexOf(parsed) !== -1 ? parsed : perPage;
	}

	function syncPerPageSelect() {
		if (perPageSelect) {
			perPageSelect.value = String(state.perPage);
		}

		if (entityListPage) {
			entityListPage.style.setProperty('--entity-list-page-rows', String(state.perPage));
		}
	}

	function updateUrl() {
		const params = new URLSearchParams();

		if (state.page > 1) {
			params.set('page', String(state.page));
		}

		if (state.perPage !== perPage) {
			params.set('per_page', String(state.perPage));
		}

		if (state.search) {
			params.set('q', state.search);
		}

		if (sortableTable) {
			sortableTable.appendSortParams(params, state);
		}

		const query = params.toString();
		const nextUrl = query ? window.location.pathname + '?' + query : window.location.pathname;
		window.history.replaceState(null, '', nextUrl);
	}

	function readUrlState() {
		const params = new URLSearchParams(window.location.search);
		state.page = Math.max(1, parseInt(params.get('page') || '1', 10));
		state.search = (params.get('q') || '').trim();
		state.perPage = normalizePerPage(params.get('per_page'));

		if (sortableTable) {
			const sortState = sortableTable.readSortParams(params);
			state.sort = sortState.sort;
			state.dir = sortState.dir === 'asc' || sortState.dir === 'desc' ? sortState.dir : '';
		}

		if (searchInput) {
			searchInput.value = state.search;
		}

		if (clearBtn) {
			clearBtn.classList.toggle('d-none', state.search === '');
		}

		syncPerPageSelect();
		renderTableHead();
	}

	function buildListUrl() {
		const params = new URLSearchParams();
		params.set('page', String(state.page));
		params.set('per_page', String(state.perPage));

		if (state.search) {
			params.set('q', state.search);
		}

		if (sortableTable) {
			sortableTable.appendSortParams(params, state);
		}

		return apiUrl + '?' + params.toString();
	}

	function renderTableHead() {
		if (!tableHeadRow || !sortableTable) {
			return;
		}

		tableHeadRow.innerHTML = sortableTable.renderHeader(columns, state);
	}

	function renderPagination(meta) {
		if (!paginationList) {
			return;
		}

		Pagination.update({
			listEl: paginationList,
			navEl: paginationNav,
			metaEl: pageMeta,
			meta: meta,
			perPage: state.perPage,
			buttonClass: 'entity-list-page-btn',
		});
	}

	function renderRows(records, rowOffset) {
		if (!tableBody) {
			return;
		}

		if (!records.length) {
			const message = state.search ? config.emptySearchMessage : config.emptyMessage;
			tableBody.innerHTML =
				'<tr><td colspan="' + (columns.length + 1) + '" class="text-center text-muted py-4">' +
				escapeHtml(message) +
				'</td></tr>';
			return;
		}

		let html = '';

		const detailEnabled = config.detail && config.detail.enabled;

		records.forEach(function (record, index) {
			const rowAttrs = detailEnabled && record.id
				? ' class="entity-list-row-clickable" role="button" tabindex="0" data-record-id="' + parseInt(record.id, 10) + '"'
				: '';
			html += '<tr' + rowAttrs + '>';
			html += '<td class="procedure-row-index text-muted">' + (rowOffset + index + 1) + '</td>';

			columns.forEach(function (column) {
				html += '<td>' + escapeHtml(record[column.key] ?? '') + '</td>';
			});

			html += '</tr>';
		});

		tableBody.innerHTML = html;
	}

	function renderMeta(meta) {
		if (!toolbarMeta) {
			return;
		}

		if ((meta.total || 0) > 0) {
			toolbarMeta.textContent = meta.range_start + '\u2013' + meta.range_end + ' of ' + meta.total;
			toolbarMeta.classList.remove('d-none');
		} else {
			toolbarMeta.textContent = '';
			toolbarMeta.classList.add('d-none');
		}
	}

	function loadList() {
		setLoading(true);
		updateUrl();

		state.request = createAbortableRequest(state.request);

		fetchApi(buildListUrl(), {
			signal: state.request.signal,
		})
			.then(function (result) {
				state.perPage = normalizePerPage(result.per_page);
				state.sort = result.sort || '';
				state.dir = result.sort_dir === 'asc' || result.sort_dir === 'desc' ? result.sort_dir : '';
				syncPerPageSelect();
				renderTableHead();

				const meta = {
					total: result.total,
					page: result.page,
					per_page: state.perPage,
					total_pages: result.total_pages,
					range_start: result.range_start,
					range_end: result.range_end,
				};

				renderRows(result.data || [], result.row_offset || 0);
				renderMeta(meta);
				renderPagination(meta);
			})
			.catch(function (error) {
				if (isAbortError(error)) {
					return;
				}

				if (tableBody) {
					tableBody.innerHTML =
						'<tr><td colspan="' + (columns.length + 1) + '" class="text-center text-danger py-4">' +
						escapeHtml(error.message || 'Failed to load records.') +
						'</td></tr>';
				}
			})
			.finally(function () {
				setLoading(false);
				state.request = null;
			});
	}

	const scheduleSearch = debounce(function () {
		state.page = 1;
		loadList();
	}, 300);

	if (searchForm) {
		searchForm.addEventListener('submit', function (event) {
			event.preventDefault();
			state.search = searchInput ? searchInput.value.trim() : '';
			state.page = 1;

			if (clearBtn) {
				clearBtn.classList.toggle('d-none', state.search === '');
			}

			loadList();
		});
	}

	if (searchInput) {
		searchInput.addEventListener('input', function () {
			state.search = searchInput.value.trim();

			if (clearBtn) {
				clearBtn.classList.toggle('d-none', state.search === '');
			}

			scheduleSearch();
		});
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			state.search = '';
			state.page = 1;

			if (searchInput) {
				searchInput.value = '';
			}

			clearBtn.classList.add('d-none');
			loadList();
		});
	}

	if (paginationList) {
		paginationList.addEventListener('click', function (event) {
			const button = event.target.closest('.entity-list-page-btn');

			if (!button) {
				return;
			}

			state.page = parseInt(button.getAttribute('data-page') || '1', 10);
			loadList();
		});
	}

	if (perPageSelect) {
		perPageSelect.addEventListener('change', function () {
			state.perPage = normalizePerPage(perPageSelect.value);
			state.page = 1;
			syncPerPageSelect();
			loadList();
		});
	}

	if (sortableTable) {
		sortableTable.bindHeader(tableHeadRow, function (columnKey) {
			const nextSort = sortableTable.cycleSort(state, columnKey);
			state.sort = nextSort.sort;
			state.dir = nextSort.dir;
			state.page = 1;
			renderTableHead();
			loadList();
		});
	}

	document.addEventListener('entityListRefresh', function () {
		loadList();
	});

	readUrlState();
	loadList();
})();
