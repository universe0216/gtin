(function () {
	'use strict';

	const listConfig = window.ENTITY_LIST_CONFIG || {};
	const detailConfig = listConfig.detail;

	if (!detailConfig || !detailConfig.enabled) {
		return;
	}

	const detailUrl = detailConfig.detailUrl.replace(/\/$/, '');
	const productsUrl = detailConfig.productsUrl.replace(/\/$/, '');
	const updateUrl = (detailConfig.updateUrl || '').replace(/\/$/, '');
	const infoFields = detailConfig.infoFields || [];
	const editableFields = detailConfig.editableFields || [];
	const readOnlyFields = detailConfig.readOnlyFields || [];
	const canEdit = !!detailConfig.canEdit;
	const productColumns = detailConfig.productColumns || [];
	const productDeleteUrl = (detailConfig.productDeleteUrl || '').replace(/\/$/, '');
	const canDeleteProduct = !!detailConfig.canDeleteProduct;
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
	const productDeleteModalEl = document.getElementById('organizationProductDeleteModal');
	const productDeleteNameEl = document.getElementById('organizationProductDeleteName');
	const productConfirmDeleteBtn = document.getElementById('organizationProductConfirmDeleteBtn');
	const tableBody = document.getElementById('entityListTableBody');
	const sortableTable = window.SortableTable;

	let modal;
	let productDeleteModal;
	let detailRequest = null;
	let updateRequest = null;
	let productsRequest = null;
	let productDeleteRequest = null;
	let productsSearchTimer = null;
	let productsLoaded = false;
	let pendingDeleteProductId = null;

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

	function initModal() {
		if (typeof mdb === 'undefined' || !modalEl) {
			return;
		}

		modal = mdb.Modal.getOrCreateInstance(modalEl);
		modalEl.addEventListener('hidden.mdb.modal', resetModal);

		if (productDeleteModalEl) {
			productDeleteModal = mdb.Modal.getOrCreateInstance(productDeleteModalEl);
			productDeleteModalEl.addEventListener('hidden.mdb.modal', function () {
				pendingDeleteProductId = null;
			});
		}
	}

	function getProductsColSpan() {
		return productColumns.length + 1 + (canDeleteProduct ? 1 : 0);
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

	function normalizeDateValue(value) {
		const raw = String(value ?? '').trim();

		if (raw === '') {
			return '';
		}

		return raw.length >= 10 ? raw.slice(0, 10) : raw;
	}

	function getInfoAlert() {
		return document.getElementById('organizationDetailInfoAlert');
	}

	function hideInfoAlert() {
		const alertEl = getInfoAlert();

		if (!alertEl) {
			return;
		}

		alertEl.className = 'alert d-none mx-4 mt-4 mb-0';
		alertEl.textContent = '';
	}

	function showInfoAlert(message, type) {
		const alertEl = getInfoAlert();

		if (!alertEl) {
			return;
		}

		const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
		alertEl.className = 'alert ' + alertClass + ' mx-4 mt-4 mb-0';
		alertEl.textContent = message;
	}

	function renderReadOnlyItems(fields, data, gridClass) {
		let html = '<div class="' + gridClass + '">';

		fields.forEach(function (field) {
			const value = data[field.key] ?? '';
			html +=
				'<div class="entity-detail-info-item">' +
					'<div class="entity-detail-info-label">' + escapeHtml(field.label) + '</div>' +
					'<div class="entity-detail-info-value">' + escapeHtml(value) + '</div>' +
				'</div>';
		});

		html += '</div>';
		return html;
	}

	function renderEditableField(field, data) {
		const fieldName = field.name;
		const value = data[fieldName] ?? '';
		const inputId = 'organizationDetailField_' + fieldName;
		let inputHtml = '';

		if (field.type === 'textarea') {
			inputHtml =
				'<textarea class="form-control form-control-sm" id="' + inputId + '" name="' + escapeHtml(fieldName) + '" rows="3">' +
				escapeHtml(value) +
				'</textarea>';
		} else if (field.type === 'date') {
			inputHtml =
				'<input type="date" class="form-control form-control-sm" id="' + inputId + '" name="' + escapeHtml(fieldName) + '" value="' +
				escapeHtml(normalizeDateValue(value)) +
				'">';
		} else {
			inputHtml =
				'<input type="text" class="form-control form-control-sm" id="' + inputId + '" name="' + escapeHtml(fieldName) + '" value="' +
				escapeHtml(value) +
				'">';
		}

		return (
			'<div class="entity-detail-info-item">' +
				'<label class="entity-detail-info-label" for="' + inputId + '">' +
					escapeHtml(field.label) +
					(field.required ? ' <span class="text-danger">*</span>' : '') +
				'</label>' +
				inputHtml +
			'</div>'
		);
	}

	function setInfoUpdateLoading(isLoading) {
		const updateBtn = document.getElementById('organizationDetailUpdateBtn');

		if (!updateBtn) {
			return;
		}

		updateBtn.disabled = isLoading;
		const spinner = updateBtn.querySelector('.organization-detail-update-spinner');
		const label = updateBtn.querySelector('.organization-detail-update-label');

		if (spinner) {
			spinner.classList.toggle('d-none', !isLoading);
		}

		if (label) {
			label.textContent = isLoading ? 'Updating...' : 'Update';
		}
	}

	function renderInfoFields(data) {
		if (!infoWrap) {
			return;
		}

		hideInfoAlert();

		let html = '';

		if (canEdit && updateUrl && editableFields.length) {
			html += '<form id="organizationDetailInfoForm" class="entity-detail-info-form">';

			if (readOnlyFields.length) {
				html += renderReadOnlyItems(readOnlyFields, data, 'entity-detail-info-grid entity-detail-info-readonly-grid');
			}

			html += '<div class="entity-detail-info-grid">';

			editableFields.forEach(function (field) {
				html += renderEditableField(field, data);
			});

			html += '</div>';
			html += '<div class="entity-detail-info-actions mt-4">';
			html +=
				'<button type="submit" class="btn btn-primary btn-sm" id="organizationDetailUpdateBtn" data-mdb-ripple-init>' +
					'<span class="organization-detail-update-label">Update</span>' +
					'<span class="organization-detail-update-spinner spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>' +
				'</button>';
			html += '</div>';
			html += '</form>';
		} else {
			html += renderReadOnlyItems(infoFields, data, 'entity-detail-info-grid');
		}

		infoWrap.innerHTML = html;

		const form = document.getElementById('organizationDetailInfoForm');

		if (form) {
			form.addEventListener('submit', submitInfoUpdate);
		}
	}

	function submitInfoUpdate(event) {
		event.preventDefault();

		if (!canEdit || !updateUrl || !detailState.organizationId) {
			return;
		}

		const form = document.getElementById('organizationDetailInfoForm');

		if (!form) {
			return;
		}

		hideInfoAlert();
		setInfoUpdateLoading(true);

		if (updateRequest) {
			updateRequest.abort();
		}

		updateRequest = new AbortController();

		fetch(updateUrl + '/' + detailState.organizationId, {
			method: 'POST',
			body: new FormData(form),
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal: updateRequest.signal,
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function (result) {
				if (!result.ok || !result.data.success) {
					throw new Error(result.data.message || 'Failed to update organization.');
				}

				const record = result.data.data || {};
				detailState.organizationName = record.name || detailState.organizationName;

				if (modalTitle) {
					modalTitle.textContent = detailState.organizationName;
				}

				renderInfoFields(record);
				showInfoAlert(result.data.message || 'Organization updated successfully.', 'success');
				document.dispatchEvent(new CustomEvent('entityListRefresh'));
			})
			.catch(function (error) {
				if (error.name === 'AbortError') {
					return;
				}

				showInfoAlert(error.message || 'Failed to update organization.', 'error');
			})
			.finally(function () {
				setInfoUpdateLoading(false);
				updateRequest = null;
			});
	}

	function renderProductTableHead() {
		if (!productsTableHead || !sortableTable) {
			return;
		}

		let html = sortableTable.renderHeader(productColumns, productsState);

		if (canDeleteProduct) {
			html += '<th class="procedure-table-action-col" aria-label="Actions"></th>';
		}

		productsTableHead.innerHTML = html;
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

		const colSpan = getProductsColSpan();

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

			if (canDeleteProduct) {
				html +=
					'<td class="procedure-table-action-col">' +
						'<button type="button" class="btn btn-sm btn-outline-danger organization-product-delete-btn" ' +
						'data-product-id="' + escapeHtml(record.id) + '" ' +
						'data-product-name="' + escapeHtml(record.name || '') + '" ' +
						'data-mdb-ripple-init aria-label="Delete product">' +
							'<i class="fas fa-trash" aria-hidden="true"></i>' +
						'</button>' +
					'</td>';
			}

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
						'<tr><td colspan="' + getProductsColSpan() + '" class="text-center text-danger py-4">' +
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

		if (updateRequest) {
			updateRequest.abort();
			updateRequest = null;
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
		hideInfoAlert();
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
				'<tr><td colspan="' + getProductsColSpan() + '" class="text-center text-muted py-4">Select an organization to view products.</td></tr>';
		}

		pendingDeleteProductId = null;
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

	function openProductDeleteModal(productId, productName) {
		if (!productDeleteModal || !productId) {
			return;
		}

		pendingDeleteProductId = productId;

		if (productDeleteNameEl) {
			productDeleteNameEl.textContent = productName || 'this product';
		}

		productDeleteModal.show();
	}

	function deleteProduct() {
		if (!canDeleteProduct || !productDeleteUrl || !pendingDeleteProductId) {
			return;
		}

		if (productConfirmDeleteBtn) {
			productConfirmDeleteBtn.disabled = true;
		}

		if (productDeleteRequest) {
			productDeleteRequest.abort();
		}

		productDeleteRequest = new AbortController();

		fetch(productDeleteUrl + '/' + pendingDeleteProductId, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
			signal: productDeleteRequest.signal,
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function (result) {
				if (!result.ok || !result.data.success) {
					throw new Error(result.data.message || 'Failed to delete product.');
				}

				if (productDeleteModal) {
					productDeleteModal.hide();
				}

				showToast(result.data.message || 'Product deleted successfully.', 'success');
				productsLoaded = false;
				loadProducts();
			})
			.catch(function (error) {
				if (error.name === 'AbortError') {
					return;
				}

				showToast(error.message || 'Failed to delete product.', 'error');
			})
			.finally(function () {
				if (productConfirmDeleteBtn) {
					productConfirmDeleteBtn.disabled = false;
				}

				productDeleteRequest = null;
				pendingDeleteProductId = null;
			});
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

	if (productsTableBody) {
		productsTableBody.addEventListener('click', function (event) {
			const deleteBtn = event.target.closest('.organization-product-delete-btn');

			if (!deleteBtn) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			openProductDeleteModal(
				deleteBtn.getAttribute('data-product-id'),
				deleteBtn.getAttribute('data-product-name')
			);
		});
	}

	if (productConfirmDeleteBtn) {
		productConfirmDeleteBtn.addEventListener('click', deleteProduct);
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
