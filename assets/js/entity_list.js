(function () {
	'use strict';

	if (typeof window.ENTITY_LIST_CONFIG === 'undefined') {
		return;
	}

	const config = window.ENTITY_LIST_CONFIG;
	const apiUrl = config.apiUrl.replace(/\/$/, '');
	const columns = config.columns || [];
	const perPage = config.perPage || 10;

	const searchForm = document.getElementById('entityListSearchForm');
	const searchInput = document.getElementById('entityListSearchInput');
	const clearBtn = document.getElementById('entityListClearBtn');
	const tableBody = document.getElementById('entityListTableBody');
	const toolbarMeta = document.getElementById('entityListToolbarMeta');
	const loadingEl = document.getElementById('entityListLoading');
	const paginationEl = document.getElementById('entityListPagination');
	const paginationList = document.getElementById('entityListPaginationList');
	const pageMeta = document.getElementById('entityListPageMeta');

	const state = {
		page: 1,
		search: '',
		perPage: perPage,
		request: null,
		searchTimer: null,
	};

	function escapeHtml(value) {
		return String(value ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function setLoading(isLoading) {
		if (loadingEl) {
			loadingEl.classList.toggle('d-none', !isLoading);
		}
	}

	function updateUrl() {
		const params = new URLSearchParams();

		if (state.page > 1) {
			params.set('page', String(state.page));
		}

		if (state.search) {
			params.set('q', state.search);
		}

		const query = params.toString();
		const nextUrl = query ? window.location.pathname + '?' + query : window.location.pathname;
		window.history.replaceState(null, '', nextUrl);
	}

	function readUrlState() {
		const params = new URLSearchParams(window.location.search);
		state.page = Math.max(1, parseInt(params.get('page') || '1', 10));
		state.search = (params.get('q') || '').trim();

		if (searchInput) {
			searchInput.value = state.search;
		}

		if (clearBtn) {
			clearBtn.classList.toggle('d-none', state.search === '');
		}
	}

	function buildListUrl() {
		const params = new URLSearchParams();
		params.set('page', String(state.page));
		params.set('per_page', String(state.perPage));

		if (state.search) {
			params.set('q', state.search);
		}

		return apiUrl + '?' + params.toString();
	}

	function renderPagination(meta) {
		const total = meta.total || 0;
		const page = meta.page || 1;
		const totalPages = meta.total_pages || 1;

		if (!paginationEl || !paginationList) {
			return;
		}

		if (total <= state.perPage) {
			paginationEl.classList.add('d-none');
			paginationList.innerHTML = '';
			return;
		}

		paginationEl.classList.remove('d-none');

		if (pageMeta) {
			pageMeta.textContent = 'Page ' + page + ' of ' + totalPages;
		}

		let html = '';
		const addPageItem = function (label, targetPage, disabled, active) {
			html += '<li class="page-item' +
				(disabled ? ' disabled' : '') +
				(active ? ' active' : '') +
				'">';

			if (disabled || active) {
				html += '<span class="page-link">' + label + '</span>';
			} else {
				html += '<button type="button" class="page-link entity-list-page-btn" data-page="' + targetPage + '" data-mdb-ripple-init>' + label + '</button>';
			}

			html += '</li>';
		};

		addPageItem('&laquo;', page - 1, page <= 1, false);

		const windowSize = 5;
		let startPage = Math.max(1, page - Math.floor(windowSize / 2));
		let endPage = Math.min(totalPages, startPage + windowSize - 1);

		if (endPage - startPage + 1 < windowSize) {
			startPage = Math.max(1, endPage - windowSize + 1);
		}

		if (startPage > 1) {
			addPageItem('1', 1, false, page === 1);
			if (startPage > 2) {
				addPageItem('&hellip;', page, true, false);
			}
		}

		for (let i = startPage; i <= endPage; i++) {
			addPageItem(String(i), i, false, i === page);
		}

		if (endPage < totalPages) {
			if (endPage < totalPages - 1) {
				addPageItem('&hellip;', page, true, false);
			}
			addPageItem(String(totalPages), totalPages, false, page === totalPages);
		}

		addPageItem('&raquo;', page + 1, page >= totalPages, false);
		paginationList.innerHTML = html;
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

		records.forEach(function (record, index) {
			html += '<tr>';
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

		if (state.request) {
			state.request.abort();
		}

		state.request = new AbortController();

		fetch(buildListUrl(), {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal: state.request.signal,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (result) {
				if (!result.success) {
					throw new Error(result.message || 'Failed to load records.');
				}

				const meta = {
					total: result.total,
					page: result.page,
					per_page: result.per_page,
					total_pages: result.total_pages,
					range_start: result.range_start,
					range_end: result.range_end,
				};

				renderRows(result.data || [], result.row_offset || 0);
				renderMeta(meta);
				renderPagination(meta);
			})
			.catch(function (error) {
				if (error.name === 'AbortError') {
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

	function scheduleSearch() {
		if (state.searchTimer) {
			clearTimeout(state.searchTimer);
		}

		state.searchTimer = setTimeout(function () {
			state.page = 1;
			loadList();
		}, 300);
	}

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

	readUrlState();
	loadList();
})();
