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

	let modal;
	let detailRequest = null;
	let updateRequest = null;
	let productDeleteRequest = null;
	let productsLoaded = false;
	let productsGrid = null;

	const detailState = {
		organizationId: null,
		organizationName: '',
	};

	const productsState = {
		search: '',
	};

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

		updateRequest = createAbortableRequest(updateRequest);

		fetchApi(updateUrl + '/' + detailState.organizationId, {
			method: 'POST',
			body: new FormData(form),
			signal: updateRequest.signal,
		})
			.then(function (result) {
				const record = result.data || {};
				detailState.organizationName = record.name || detailState.organizationName;

				if (modalTitle) {
					modalTitle.textContent = detailState.organizationName;
				}

				renderInfoFields(record);
				showInfoAlert(result.message || 'Organization updated successfully.', 'success');
				document.dispatchEvent(new CustomEvent('entityListRefresh'));

				// Refresh products in case the rename affected them.
				if (productsGrid) {
					productsGrid.reload();
				}
			})
			.catch(function (error) {
				if (isAbortError(error)) {
					return;
				}

				showInfoAlert(error.message || 'Failed to update organization.', 'error');
			})
			.finally(function () {
				setInfoUpdateLoading(false);
				updateRequest = null;
			});
	}

	// ------------------------------------------------------------- Products grid

	function updateProductsMeta(data) {
		if (!productsMeta) {
			return;
		}

		if (data && (data.total || 0) > 0) {
			productsMeta.textContent = data.range_start + '–' + data.range_end + ' of ' + data.total;
			productsMeta.classList.remove('d-none');
		} else {
			productsMeta.textContent = '';
			productsMeta.classList.add('d-none');
		}

		if (footerMeta) {
			footerMeta.textContent = ((data && data.total) || 0) + ' product(s)';
		}
	}

	function productDeleteRenderer(row, columnfield, value, defaultHtml, props, rowdata) {
		return '<div class="jqx-action-cell">' +
			'<button type="button" class="btn btn-sm btn-outline-danger organization-product-delete-btn" ' +
				'data-product-id="' + JqxGrid.escapeAttr(rowdata.id) + '" ' +
				'data-product-name="' + JqxGrid.escapeAttr(rowdata.name || '') + '" ' +
				'title="Delete product"><i class="fas fa-trash"></i></button>' +
		'</div>';
	}

	function buildProductColumns() {
		const columns = [
			{ text: '#', datafield: '__rownum', sortable: false, filterable: false, width: 60, cellsrenderer: JqxGrid.rowNumberRenderer },
		];

		productColumns.forEach(function (column) {
			columns.push({ text: column.label, datafield: column.key });
		});

		if (canDeleteProduct) {
			columns.push({
				text: '', datafield: '__actions', width: 70, sortable: false, filterable: false,
				align: 'right', cellsalign: 'right', cellsrenderer: productDeleteRenderer,
			});
		}

		return columns;
	}

	function buildProductDatafields() {
		const names = { id: 'number', name: 'string' };

		productColumns.forEach(function (column) {
			if (!names[column.key]) {
				names[column.key] = 'string';
			}
		});

		return Object.keys(names).map(function (name) {
			return { name: name, type: names[name] };
		});
	}

	function productsEndpoint() {
		return productsUrl + '/' + detailState.organizationId;
	}

	function createProductsGrid() {
		productsGrid = JqxGrid.remote('#organizationProductsGrid', {
			url: productsEndpoint(),
			columns: buildProductColumns(),
			datafields: buildProductDatafields(),
			pagesize: perPage,
			pagesizeoptions: ['10', '20', '50'],
			height: Math.max(320, window.innerHeight - 280),
			emptyMessage: 'No products found for this organization.',
			getParams: function () {
				return productsState.search ? { q: productsState.search } : {};
			},
			onLoad: updateProductsMeta,
			onError: function () {
				showToast('Failed to load products.', 'error');
			},
		});

		if (productsGrid) {
			$(productsGrid.$el).on('click', '.organization-product-delete-btn', function (event) {
				event.preventDefault();
				event.stopPropagation();
				openProductDeleteModal(
					$(this).attr('data-product-id'),
					$(this).attr('data-product-name')
				);
			});
		}
	}

	function loadProducts() {
		if (!detailState.organizationId) {
			return;
		}

		if (!productsGrid) {
			createProductsGrid();
		} else {
			productsGrid.source.url = productsEndpoint();
			productsGrid.gotoFirst();
			productsGrid.reload();
		}

		productsLoaded = true;
	}

	function loadDetail(organizationId) {
		setInfoLoading(true);

		detailRequest = createAbortableRequest(detailRequest);

		fetchApi(detailUrl + '/' + organizationId, {
			signal: detailRequest.signal,
		})
			.then(function (result) {
				detailState.organizationName = result.data.name || '—';
				if (modalTitle) {
					modalTitle.textContent = detailState.organizationName;
				}

				renderInfoFields(result.data);
			})
			.catch(function (error) {
				if (isAbortError(error)) {
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

		detailState.organizationId = null;
		detailState.organizationName = '';
		productsState.search = '';
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
		productsState.search = '';
		productsLoaded = false;

		if (productsSearchInput) {
			productsSearchInput.value = '';
		}
		if (productsClearBtn) {
			productsClearBtn.classList.add('d-none');
		}

		modal.show();
		loadDetail(organizationId);
	}

	function deleteProduct(productId) {
		if (!canDeleteProduct || !productDeleteUrl || !productId) {
			return Promise.reject(new Error('Unable to delete this product.'));
		}

		productDeleteRequest = createAbortableRequest(productDeleteRequest);

		return fetchApi(productDeleteUrl + '/' + productId, {
			method: 'POST',
			signal: productDeleteRequest.signal,
		}).finally(function () {
			productDeleteRequest = null;
		});
	}

	function openProductDeleteModal(productId, productName) {
		if (!productId) {
			return;
		}

		const label = escapeHtml(productName || 'this product');

		showConfirm({
			title: 'Delete product',
			message: 'Are you sure you want to delete <strong>' + label + '</strong>?',
			allowHtml: true,
			confirmLabel: 'Delete',
		})
			.then(function () {
				return deleteProduct(productId);
			})
			.then(function (result) {
				showToast(result.message || 'Product deleted successfully.', 'success');

				if (productsGrid) {
					productsGrid.reload();
				}
			})
			.catch(function (error) {
				if (isConfirmCancelled(error) || isAbortError(error)) {
					return;
				}

				showToast(error.message || 'Failed to delete product.', 'error');
			});
	}

	const scheduleProductsSearch = debounce(function () {
		if (!productsGrid) {
			loadProducts();
			return;
		}

		productsGrid.gotoFirst();
		productsGrid.reload();
	}, 300);

	document.addEventListener('entityListRowSelected', function (event) {
		if (event.detail && event.detail.id) {
			openDetail(String(event.detail.id));
		}
	});

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
			productsSearchInput.value = '';
			productsClearBtn.classList.add('d-none');

			if (productsGrid) {
				productsGrid.gotoFirst();
				productsGrid.reload();
			}
		});
	}

	initModal();
})();
