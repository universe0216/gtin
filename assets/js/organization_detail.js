(function () {
	'use strict';

	const listConfig = window.ENTITY_LIST_CONFIG || {};
	const detailConfig = listConfig.detail;

	if (!detailConfig || !detailConfig.enabled) {
		return;
	}

	const detailUrl = detailConfig.detailUrl.replace(/\/$/, '');
	const productsUrl = detailConfig.productsUrl.replace(/\/$/, '');
	const infoFields = detailConfig.infoFields || [];
	const productColumns = detailConfig.productColumns || [];
	const perPage = detailConfig.perPage || 10;

	const modalEl = document.getElementById('organizationDetailModal');
	const modalTitle = document.getElementById('organizationDetailModalTitle');
	const footerMeta = document.getElementById('organizationDetailFooterMeta');
	const infoLoading = document.getElementById('organizationDetailInfoLoading');
	const infoWrap = document.getElementById('organizationDetailInfoWrap');
	const productsTabBtn = document.getElementById('organizationProductsTabBtn');
	const productsSearchInput = document.getElementById('organizationProductsSearchInput');
	const productsClearBtn = document.getElementById('organizationProductsClearBtn');
	const productsMeta = document.getElementById('organizationProductsMeta');
	const productsLoading = document.getElementById('organizationProductsLoading');
	const productsTableHead = document.getElementById('organizationProductsTableHead');
	const productsTableBody = document.getElementById('organizationProductsTableBody');
	const productsPaginationNav = document.getElementById('organizationProductsPaginationNav');
	const productsPaginationList = document.getElementById('organizationProductsPaginationList');
	const productsPageMeta = document.getElementById('organizationProductsPageMeta');
	const tableBody = document.getElementById('entityListTableBody');
	const sortableTable = window.SortableTable;

	let modal;
	let detailRequest = null;
	let productsRequest = null;
	let productsSearchTimer = null;
	let productsLoaded = false;

	const detailState = {
		organizationId: null,
		organizationName: '',
	};

	const productsState = {
		page: 1,
		search: '',
		perPage: perPage,
		sort: '',
		dir: '',
	};

	function escapeHtml(value) {
		return String(value ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function initModal() {
		if (typeof mdb === 'undefined' || !modalEl) {
			return;
		}

		modal = mdb.Modal.getOrCreateInstance(modalEl);
		modalEl.addEventListener('hidden.mdb.modal', resetModal);
	}

	function setInfoLoading(isLoading) {
		if (infoLoading) {
			infoLoading.classList.toggle('d-none', !isLoading);
		}
		if (infoWrap) {
			infoWrap.classList.toggle('d-none', isLoading);
		}
	}

	function setProductsLoading(isLoading) {
		if (productsLoading) {
			productsLoading.classList.toggle('d-none', !isLoading);
		}
	}

	function renderInfoFields(data) {
		if (!infoWrap) {
			return;
		}

		let html = '<div class="entity-detail-info-grid">';

		infoFields.forEach(function (field) {
			const value = data[field.key] ?? '';
			html +=
				'<div class="entity-detail-info-item">' +
					'<div class="entity-detail-info-label">' + escapeHtml(field.label) + '</div>' +
					'<div class="entity-detail-info-value">' + escapeHtml(value) + '</div>' +
				'</div>';
		});

		html += '</div>';
		infoWrap.innerHTML = html;
	}

	function renderProductTableHead() {
		if (!productsTableHead || !sortableTable) {
			return;
		}

		productsTableHead.innerHTML = sortableTable.renderHeader(productColumns, productsState);
	}

	function renderProductsPagination(meta) {
		const total = meta.total || 0;
		const page = meta.page || 1;
		const totalPages = meta.total_pages || 1;

		if (!productsPaginationList) {
			return;
		}

		if (productsPaginationNav) {
			productsPaginationNav.classList.toggle('d-none', total <= productsState.perPage);
		}

		if (productsPageMeta) {
			productsPageMeta.textContent = total > 0 ? 'Page ' + page + ' of ' + totalPages : '';
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
				html += '<button type="button" class="page-link organization-products-page-btn" data-page="' + targetPage + '" data-mdb-ripple-init>' + label + '</button>';
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

		for (let i = startPage; i <= endPage; i++) {
			addPageItem(String(i), i, false, i === page);
		}

		addPageItem('&raquo;', page + 1, page >= totalPages, false);
		productsPaginationList.innerHTML = html;
	}

	function renderProductsRows(records, rowOffset) {
		if (!productsTableBody) {
			return;
		}

		const colSpan = productColumns.length + 1;

		if (!records.length) {
			const message = productsState.search ? 'No products match your search.' : 'No products found for this organization.';
			productsTableBody.innerHTML =
				'<tr><td colspan="' + colSpan + '" class="text-center text-muted py-4">' +
				escapeHtml(message) +
				'</td></tr>';
			return;
		}

		let html = '';
		records.forEach(function (record, index) {
			html += '<tr>';
			html += '<td class="procedure-row-index text-muted">' + (rowOffset + index + 1) + '</td>';
			productColumns.forEach(function (column) {
				html += '<td>' + escapeHtml(record[column.key] ?? '') + '</td>';
			});
			html += '</tr>';
		});

		productsTableBody.innerHTML = html;
	}

	function buildProductsUrl() {
		const params = new URLSearchParams();
		params.set('page', String(productsState.page));
		params.set('per_page', String(productsState.perPage));

		if (productsState.search) {
			params.set('q', productsState.search);
		}

		if (sortableTable) {
			sortableTable.appendSortParams(params, productsState);
		}

		return productsUrl + '/' + detailState.organizationId + '?' + params.toString();
	}

	function loadProducts() {
		if (!detailState.organizationId) {
			return;
		}

		setProductsLoading(true);

		if (productsRequest) {
			productsRequest.abort();
		}

		productsRequest = new AbortController();

		fetch(buildProductsUrl(), {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal: productsRequest.signal,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (result) {
				if (!result.success) {
					throw new Error(result.message || 'Failed to load products.');
				}

				productsState.sort = result.sort || '';
				productsState.dir = result.sort_dir === 'asc' || result.sort_dir === 'desc' ? result.sort_dir : '';
				renderProductTableHead();

				const meta = {
					total: result.total,
					page: result.page,
					total_pages: result.total_pages,
					range_start: result.range_start,
					range_end: result.range_end,
				};

				renderProductsRows(result.data || [], result.row_offset || 0);
				renderProductsPagination(meta);

				if (productsMeta) {
					if ((meta.total || 0) > 0) {
						productsMeta.textContent = meta.range_start + '\u2013' + meta.range_end + ' of ' + meta.total;
						productsMeta.classList.remove('d-none');
					} else {
						productsMeta.textContent = '';
						productsMeta.classList.add('d-none');
					}
				}

				if (footerMeta) {
					footerMeta.textContent = (meta.total || 0) + ' product(s)';
				}

				productsLoaded = true;
			})
			.catch(function (error) {
				if (error.name === 'AbortError') {
					return;
				}

				if (productsTableBody) {
					productsTableBody.innerHTML =
						'<tr><td colspan="' + (productColumns.length + 1) + '" class="text-center text-danger py-4">' +
						escapeHtml(error.message || 'Failed to load products.') +
						'</td></tr>';
				}
			})
			.finally(function () {
				setProductsLoading(false);
				productsRequest = null;
			});
	}

	function loadDetail(organizationId) {
		setInfoLoading(true);

		if (detailRequest) {
			detailRequest.abort();
		}

		detailRequest = new AbortController();

		fetch(detailUrl + '/' + organizationId, {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal: detailRequest.signal,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (result) {
				if (!result.success) {
					throw new Error(result.message || 'Failed to load organization.');
				}

				detailState.organizationName = result.data.name || '—';
				if (modalTitle) {
					modalTitle.textContent = detailState.organizationName;
				}

				renderInfoFields(result.data);
			})
			.catch(function (error) {
				if (error.name === 'AbortError') {
					return;
				}

				if (infoWrap) {
					infoWrap.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(error.message || 'Failed to load organization.') + '</p>';
					infoWrap.classList.remove('d-none');
				}
			})
			.finally(function () {
				setInfoLoading(false);
				detailRequest = null;
			});
	}

	function resetModal() {
		if (detailRequest) {
			detailRequest.abort();
			detailRequest = null;
		}

		if (productsRequest) {
			productsRequest.abort();
			productsRequest = null;
		}

		detailState.organizationId = null;
		detailState.organizationName = '';
		productsState.page = 1;
		productsState.search = '';
		productsState.sort = '';
		productsState.dir = '';
		productsLoaded = false;

		if (modalTitle) {
			modalTitle.textContent = '—';
		}
		if (infoWrap) {
			infoWrap.innerHTML = '';
		}
		if (footerMeta) {
			footerMeta.textContent = '';
		}
		if (productsSearchInput) {
			productsSearchInput.value = '';
		}
		if (productsClearBtn) {
			productsClearBtn.classList.add('d-none');
		}
		if (productsMeta) {
			productsMeta.textContent = '';
			productsMeta.classList.add('d-none');
		}
		if (productsTableBody) {
			productsTableBody.innerHTML =
				'<tr><td colspan="' + (productColumns.length + 1) + '" class="text-center text-muted py-4">Select an organization to view products.</td></tr>';
		}
		if (productsPaginationList) {
			productsPaginationList.innerHTML = '';
		}
		if (productsPaginationNav) {
			productsPaginationNav.classList.add('d-none');
		}
		if (productsPageMeta) {
			productsPageMeta.textContent = '';
		}

		const infoTabBtn = document.getElementById('organizationInfoTabBtn');
		if (infoTabBtn && typeof mdb !== 'undefined') {
			const tab = mdb.Tab.getOrCreateInstance(infoTabBtn);
			tab.show();
		}
	}

	function openDetail(organizationId) {
		if (!modal || !organizationId) {
			return;
		}

		detailState.organizationId = organizationId;
		productsState.page = 1;
		productsState.search = '';
		productsState.sort = '';
		productsState.dir = '';
		productsLoaded = false;

		if (productsSearchInput) {
			productsSearchInput.value = '';
		}
		if (productsClearBtn) {
			productsClearBtn.classList.add('d-none');
		}

		renderProductTableHead();
		modal.show();
		loadDetail(organizationId);
	}

	function scheduleProductsSearch() {
		if (productsSearchTimer) {
			clearTimeout(productsSearchTimer);
		}

		productsSearchTimer = setTimeout(function () {
			productsState.page = 1;
			loadProducts();
		}, 300);
	}

	if (tableBody) {
		tableBody.addEventListener('click', function (event) {
			const row = event.target.closest('.entity-list-row-clickable');

			if (!row) {
				return;
			}

			openDetail(row.getAttribute('data-record-id'));
		});

		tableBody.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' && event.key !== ' ') {
				return;
			}

			const row = event.target.closest('.entity-list-row-clickable');

			if (!row) {
				return;
			}

			event.preventDefault();
			openDetail(row.getAttribute('data-record-id'));
		});
	}

	if (productsTabBtn) {
		productsTabBtn.addEventListener('shown.mdb.tab', function () {
			if (!productsLoaded) {
				loadProducts();
			}
		});
	}

	if (productsSearchInput) {
		productsSearchInput.addEventListener('input', function () {
			productsState.search = productsSearchInput.value.trim();

			if (productsClearBtn) {
				productsClearBtn.classList.toggle('d-none', productsState.search === '');
			}

			scheduleProductsSearch();
		});
	}

	if (productsClearBtn) {
		productsClearBtn.addEventListener('click', function () {
			productsState.search = '';
			productsState.page = 1;
			productsSearchInput.value = '';
			productsClearBtn.classList.add('d-none');
			loadProducts();
		});
	}

	if (productsPaginationList) {
		productsPaginationList.addEventListener('click', function (event) {
			const button = event.target.closest('.organization-products-page-btn');

			if (!button) {
				return;
			}

			productsState.page = parseInt(button.getAttribute('data-page') || '1', 10);
			loadProducts();
		});
	}

	if (sortableTable) {
		sortableTable.bindHeader(productsTableHead, function (columnKey) {
			const nextSort = sortableTable.cycleSort(productsState, columnKey);
			productsState.sort = nextSort.sort;
			productsState.dir = nextSort.dir;
			productsState.page = 1;
			renderProductTableHead();
			loadProducts();
		});
	}

	initModal();
	renderProductTableHead();
})();
