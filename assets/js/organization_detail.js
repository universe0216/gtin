(function () {
	'use strict';

	const listConfig = window.ORGANIZATIONS_CONFIG || {};
	const detailConfig = listConfig.detail;

	if (!detailConfig || !detailConfig.enabled) {
		return;
	}

	const detailUrl = detailConfig.detailUrl.replace(/\/$/, '');
	const productsUrl = detailConfig.productsUrl.replace(/\/$/, '');
	const updateUrl = (detailConfig.updateUrl || '').replace(/\/$/, '');
	const infoFields = detailConfig.infoFields || [];
	const editableFields = detailConfig.editableFields || [];
	const locationNodes = detailConfig.locationNodes || [];
	const locationById = {};

	locationNodes.forEach(function (node) {
		locationById[String(node.id)] = node;
	});
	const readOnlyFields = detailConfig.readOnlyFields || [];
	const canEdit = !!detailConfig.canEdit;
	const productColumns = detailConfig.productColumns || [];
	const productDeleteUrl = (detailConfig.productDeleteUrl || '').replace(/\/$/, '');
	const canDeleteProduct = !!detailConfig.canDeleteProduct;
	const canRestoreProduct = !!detailConfig.canRestoreProduct;
	const productRestoreUrl = (detailConfig.productRestoreUrl || '').replace(/\/$/, '');
	const productRestorePreviewUrl = (detailConfig.productRestorePreviewUrl || '').replace(/\/$/, '');
	const organizationDeleteUrl = (detailConfig.deleteUrl || '').replace(/\/$/, '');
	const organizationDeletePreviewUrl = (detailConfig.deletePreviewUrl || '').replace(/\/$/, '');
	const organizationRestoreUrl = (detailConfig.restoreUrl || '').replace(/\/$/, '');
	const organizationDeleteBtn = document.getElementById('organizationDetailDeleteBtn');
	const organizationRestoreBtn = document.getElementById('organizationDetailRestoreBtn');
	const perPage = detailConfig.perPage || 10;

	const modalEl = document.getElementById('organizationDetailModal');
	const modalTitle = document.getElementById('organizationDetailModalTitle');
	const footerMeta = document.getElementById('organizationDetailFooterMeta');
	const infoLoading = document.getElementById('organizationDetailInfoLoading');
	const infoWrap = document.getElementById('organizationDetailInfoWrap');
	const productsTabBtn = document.getElementById('organizationProductsTabBtn');
	const productsSearchInput = document.getElementById('organizationProductsSearchInput');
	const productsClearBtn = document.getElementById('organizationProductsClearBtn');
	const productsDeleteSelectedBtn = document.getElementById('organizationProductsDeleteSelectedBtn');
	const productsMeta = document.getElementById('organizationProductsMeta');
	const bulkDeleteProducts = !!(canDeleteProduct && productsDeleteSelectedBtn && productDeleteUrl);
	const selectedProductIds = new Set();
	const productDetailConfig = detailConfig.productDetail || null;
	const productDetailEnabled = !!(productDetailConfig && productDetailConfig.enabled && productDetailConfig.detailUrl && window.ProductDetailModal);

	let modal;
	let detailRequest = null;
	let updateRequest = null;
	let productDeleteRequest = null;
	let productsLoaded = false;
	let productsGrid = null;
	let productDetail = null;

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

	function padDatePart(value) {
		return String(value).padStart(2, '0');
	}

	function todayDateValue() {
		const now = new Date();

		return now.getFullYear() + '-' + padDatePart(now.getMonth() + 1) + '-' + padDatePart(now.getDate());
	}

	function addYearsToDate(value, years) {
		const base = normalizeDateValue(value) || todayDateValue();
		const parts = base.split('-').map(function (part) {
			return parseInt(part, 10);
		});

		if (parts.length < 3 || parts.some(function (part) { return Number.isNaN(part); })) {
			return todayDateValue();
		}

		const date = new Date(parts[0], parts[1] - 1, parts[2]);
		date.setFullYear(date.getFullYear() + years);

		return date.getFullYear() + '-' + padDatePart(date.getMonth() + 1) + '-' + padDatePart(date.getDate());
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

	function currencyLabel(value) {
		const code = String(value == null ? '' : value);

		if (code === '1') {
			return t('currency_foreign');
		}

		if (code === '2') {
			return t('currency_domestic');
		}

		return '';
	}

	function locationChildren(parentId) {
		const key = parentId == null || parentId === '' ? '' : String(parentId);

		return locationNodes.filter(function (node) {
			return String(node.parent_id || '') === key;
		}).sort(function (left, right) {
			const order = (Number(left.sort_order) || 0) - (Number(right.sort_order) || 0);

			if (order !== 0) {
				return order;
			}

			return String(left.name).localeCompare(String(right.name));
		});
	}

	function locationChain(id) {
		const chain = [];
		const seen = {};
		let current = locationById[String(id)];

		while (current && !seen[current.id]) {
			seen[current.id] = true;
			chain.unshift(String(current.id));
			const parentId = current.parent_id == null ? '' : String(current.parent_id);
			current = parentId ? locationById[parentId] : null;
		}

		return chain;
	}

	function locationLabel(value) {
		const id = String(value == null ? '' : value);

		if (!id) {
			return '';
		}

		const names = locationChain(id).map(function (nodeId) {
			return locationById[nodeId].name;
		});

		return names.length ? names.join(' / ') : id;
	}

	function locationTreeSource(parentId) {
		return locationChildren(parentId).map(function (node) {
			const children = locationTreeSource(node.id);
			const item = {
				label: escapeHtml(node.name),
				value: String(node.id),
			};

			if (children.length) {
				item.items = children;
			}

			return item;
		});
	}

	function locationDropContent(text) {
		return '<span class="organization-location-dropdown-label">' + escapeHtml(text || '') + '</span>';
	}

	function destroyLocationPicker() {
		const $ = window.jQuery;

		if (!$ || !$.fn) {
			return;
		}

		const $tree = $('#organizationLocationTree');
		const $drop = $('#organizationLocationDropDown');

		if ($tree.length && $tree.data('jqxTree')) {
			$tree.jqxTree('destroy');
		}

		if ($drop.length && $drop.data('jqxDropDownButton')) {
			$drop.jqxDropDownButton('destroy');
		}
	}

	function mountLocationPicker() {
		const $ = window.jQuery;
		const hidden = document.getElementById('organizationDetailField_location_id');
		const clearBtn = document.getElementById('organizationLocationClear');

		if (!$ || !$.fn || !$.fn.jqxDropDownButton || !$.fn.jqxTree || !hidden) {
			return;
		}

		const $drop = $('#organizationLocationDropDown');
		const $tree = $('#organizationLocationTree');
		let applyingSelection = false;

		function showSelection(id) {
			$drop.jqxDropDownButton('setContent', locationDropContent(id ? locationLabel(id) : ''));
		}

		$drop.jqxDropDownButton({
			width: '100%',
			height: 32,
			theme: 'dark',
			popupZIndex: 2100,
			dropDownWidth: 360,
			dropDownHeight: 260,
			initContent: function () {
				$tree.jqxTree({
					source: locationTreeSource(''),
					width: 360,
					height: 260,
					theme: 'dark',
					allowDrag: false,
					allowDrop: false,
					toggleMode: 'dblclick',
					incrementalSearch: true,
				});
				$tree.on('select', function (event) {
					if (applyingSelection || !event.args) {
						return;
					}

					const item = $tree.jqxTree('getItem', event.args.element);

					if (!item || item.value == null || item.value === '') {
						return;
					}

					hidden.value = String(item.value);
					showSelection(hidden.value);
					$drop.jqxDropDownButton('close');
				});

				const currentId = String(hidden.value || '');

				if (currentId && locationById[currentId]) {
					const items = $tree.jqxTree('getItems') || [];
					const match = items.filter(function (item) {
						return String(item.value) === currentId;
					})[0];

					if (match) {
						applyingSelection = true;
						let parent = match.parentElement;

						while (parent) {
							$tree.jqxTree('expandItem', parent);
							const parentItem = $tree.jqxTree('getItem', parent);
							parent = parentItem ? parentItem.parentElement : null;
						}

						$tree.jqxTree('selectItem', match.element);
						applyingSelection = false;
					}
				}
			},
		});

		const initialId = String(hidden.value || '');
		showSelection(initialId);

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				hidden.value = '';
				showSelection('');

				if ($tree.data('jqxTree')) {
					applyingSelection = true;
					$tree.jqxTree('selectItem', null);
					applyingSelection = false;
				}

				if ($drop.jqxDropDownButton('isOpened')) {
					$drop.jqxDropDownButton('close');
				}
			});
		}
	}

	function renderLocationPicker(selectedId) {
		const id = selectedId == null ? '' : String(selectedId);

		return (
			'<div class="organization-location-picker">' +
				'<input type="hidden" name="location_id" id="organizationDetailField_location_id" value="' + escapeAttr(id) + '">' +
				'<div id="organizationLocationDropDown" class="organization-location-dropdown" aria-label="' + escapeAttr(t('col_location_id')) + '">' +
					'<div id="organizationLocationTree"></div>' +
				'</div>' +
				'<button type="button" class="btn btn-secondary btn-sm organization-location-clear" id="organizationLocationClear">' +
					escapeHtml(t('common_clear')) +
				'</button>' +
			'</div>'
		);
	}

	function renderReadOnlyItems(fields, data, gridClass) {
		let html = '<div class="' + gridClass + '">';

		fields.forEach(function (field) {
			const raw = data[field.key] ?? '';
			let value = raw;

			if (field.key === 'currency') {
				value = currencyLabel(raw);
			} else if (field.key === 'location_id') {
				value = locationLabel(raw);
			}
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

		if (fieldName === 'location_id') {
			return (
				'<div class="entity-detail-info-item organization-location-field">' +
					'<div class="entity-detail-info-label">' + escapeHtml(field.label) + '</div>' +
					renderLocationPicker(value) +
				'</div>'
			);
		}

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
		} else if (field.type === 'select') {
			const options = field.options || [];
			inputHtml = '<select class="form-select form-select-sm" id="' + inputId + '" name="' + escapeHtml(fieldName) + '">';
			options.forEach(function (option) {
				const selected = String(value) === String(option.value) ? ' selected' : '';
				inputHtml += '<option value="' + escapeAttr(option.value) + '"' + selected + '>' + escapeHtml(option.label) + '</option>';
			});
			inputHtml += '</select>';
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
		const extendButtons = document.querySelectorAll('.organization-detail-extend-btn');

		if (updateBtn) {
			updateBtn.disabled = isLoading;
			const spinner = updateBtn.querySelector('.organization-detail-update-spinner');
			const label = updateBtn.querySelector('.organization-detail-update-label');

			if (spinner) {
				spinner.classList.toggle('d-none', !isLoading);
			}

			if (label) {
				label.textContent = isLoading ? t('common_updating') : t('common_update');
			}
		}

		extendButtons.forEach(function (button) {
			button.disabled = isLoading;
		});
	}

	function renderInfoFields(data) {
		if (!infoWrap) {
			return;
		}

		hideInfoAlert();
		destroyLocationPicker();

		let html = '';

		if (canEdit && updateUrl && editableFields.length) {
			html += '<form id="organizationDetailInfoForm" class="entity-detail-info-form">';

			html += '<div class="entity-detail-info-grid">';

			editableFields.forEach(function (field) {
				html += renderEditableField(field, data);
			});

			html += '</div>';
			html += '<div class="entity-detail-info-actions mt-4">';
			html +=
				'<button type="button" class="btn btn-outline-primary btn-sm organization-detail-extend-btn" data-years="2" id="organizationDetailExtend2Btn" data-mdb-ripple-init>' +
					escapeHtml(t('org_extend_2_years')) +
				'</button>';
			html +=
				'<button type="button" class="btn btn-outline-primary btn-sm organization-detail-extend-btn" data-years="1" id="organizationDetailExtend1Btn" data-mdb-ripple-init>' +
					escapeHtml(t('org_extend_1_year')) +
				'</button>';
			html +=
				'<button type="submit" class="btn btn-primary btn-sm" id="organizationDetailUpdateBtn" data-mdb-ripple-init>' +
					'<span class="organization-detail-update-label">' + escapeHtml(t('common_update')) + '</span>' +
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
			form.querySelectorAll('.organization-detail-extend-btn').forEach(function (button) {
				button.addEventListener('click', function () {
					const years = parseInt(button.getAttribute('data-years'), 10) || 0;
					extendOrganization(years);
				});
			});
			mountLocationPicker();
		}
	}

	function saveInfoForm() {
		if (!canEdit || !updateUrl || !detailState.organizationId) {
			return Promise.reject(new Error(t('org_update_failed')));
		}

		const form = document.getElementById('organizationDetailInfoForm');

		if (!form) {
			return Promise.reject(new Error(t('org_update_failed')));
		}

		hideInfoAlert();
		setInfoUpdateLoading(true);

		updateRequest = createAbortableRequest(updateRequest);

		return fetchApi(updateUrl + '/' + detailState.organizationId, {
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
				showToast(result.message || t('org_updated'), 'success');
				document.dispatchEvent(new CustomEvent('organizationsListRefresh'));

				if (productsGrid) {
					productsGrid.reload();
				}

				return result;
			})
			.finally(function () {
				setInfoUpdateLoading(false);
				updateRequest = null;
			});
	}

	function submitInfoUpdate(event) {
		event.preventDefault();

		saveInfoForm().catch(function (error) {
			if (isAbortError(error)) {
				return;
			}

			showInfoAlert(error.message || t('org_update_failed'), 'error');
		});
	}

	function extendOrganization(years) {
		if (!canEdit || !updateUrl || !detailState.organizationId || (years !== 1 && years !== 2)) {
			return;
		}

		const form = document.getElementById('organizationDetailInfoForm');
		const regDateInput = document.getElementById('organizationDetailField_reg_date');
		const expiryDateInput = document.getElementById('organizationDetailField_expiry_date');

		if (!form || !regDateInput || !expiryDateInput) {
			showInfoAlert(t('org_extend_failed'), 'error');
			return;
		}

		const confirmMessage = years === 2 ? t('org_extend_confirm_2') : t('org_extend_confirm_1');
		const confirmLabel = years === 2 ? t('org_extend_2_years') : t('org_extend_1_year');

		showConfirm({
			title: confirmLabel,
			message: confirmMessage,
			confirmLabel: confirmLabel,
			confirmClass: 'btn btn-primary',
		}).then(function () {
			regDateInput.value = todayDateValue();
			expiryDateInput.value = addYearsToDate(expiryDateInput.value, years);

			return saveInfoForm();
		}).catch(function (error) {
			if (window.isConfirmCancelled && isConfirmCancelled(error)) {
				return;
			}

			if (isAbortError(error)) {
				return;
			}

			showInfoAlert(error.message || t('org_extend_failed'), 'error');
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

	function productActionRenderer(kind) {
		return function (row, columnfield, value, defaultHtml, props, rowdata) {
			const restore = kind === 'restore';
			const label = restore ? t('common_restore') : t('common_delete');
			const buttonClass = restore ? 'btn-outline-success organization-product-restore-btn' : 'btn-outline-danger organization-product-delete-btn';

			return '<div class="jqx-action-cell">' +
				'<button type="button" class="btn btn-sm ' + buttonClass + '" ' +
					'data-product-id="' + JqxGrid.escapeAttr(rowdata.id) + '" ' +
					'data-product-name="' + JqxGrid.escapeAttr(rowdata.name || '') + '">' +
					escapeHtml(label) +
				'</button></div>';
		};
	}

	const productColumnWidths = {
		name: 220,
		organization_name: 200,
		brand: 150,
		standard_number: 170,
		barcode_1d: 170,
		barcode_1d_type: 140,
		barcode_2d: 170,
		barcode_2d_type: 150,
		package_type: 150,
		price: 120,
		reg_date: 160,
		processor_name: 160,
	};

	function productIndexRenderer(row) {
		let number = row + 1;
		const host = $('#organizationProductsGrid');

		if (host.length) {
			const paging = host.jqxGrid('getpaginginformation');

			if (paging && paging.pagesize && row < paging.pagesize) {
				number = (paging.pagenum || 0) * paging.pagesize + row + 1;
			}
		}

		return '<div class="jqx-grid-cell-leftalign" style="margin-top:6px;">' + number + '</div>';
	}

	function productSelectRenderer(row, column, value, defaultHtml, props, rowdata) {
		const id = JqxGrid.escapeAttr(rowdata && rowdata.id);
		const checked = selectedProductIds.has(String(rowdata && rowdata.id)) ? ' checked' : '';

		return '<div class="products-select-cell"><input type="checkbox" class="form-check-input products-row-check" data-id="' +
			id + '"' + checked + ' aria-label="' + JqxGrid.escapeAttr(t('product_select_row')) + '"></div>';
	}

	function productSelectHeaderRenderer() {
		return '<div class="products-select-cell"><input type="checkbox" class="form-check-input products-row-check-all" aria-label="' +
			JqxGrid.escapeAttr(t('product_select_page')) + '"></div>';
	}

	function productsGridHeight() {
		const table = document.querySelector('#organizationProductsTab .entity-detail-products-table');

		if (table && table.clientHeight > 200) {
			return table.clientHeight;
		}

		return Math.max(320, window.innerHeight - 280);
	}

	function buildProductColumns() {
		const columns = [
			{
				text: '#',
				datafield: '__rownum',
				sortable: false,
				filterable: false,
				width: 56,
				pinned: true,
				cellsalign: 'right',
				align: 'right',
				cellsrenderer: productIndexRenderer,
			},
		];

		if (bulkDeleteProducts) {
			columns.unshift({
				text: '',
				datafield: '__select',
				width: 42,
				pinned: true,
				sortable: false,
				filterable: false,
				menu: false,
				renderer: productSelectHeaderRenderer,
				cellsrenderer: productSelectRenderer,
			});
		}

		productColumns.forEach(function (column) {
			const gridColumn = {
				text: column.label,
				datafield: column.key,
				width: productColumnWidths[column.key] || 160,
			};

			if (column.pinned) {
				gridColumn.pinned = true;
			}

			columns.push(gridColumn);
		});

		if (canDeleteProduct) {
			columns.push({
				text: '', datafield: '__actions', width: 110, sortable: false, filterable: false,
				align: 'right', cellsalign: 'right', cellsrenderer: productActionRenderer('delete'),
			});
		}

		if (canRestoreProduct) {
			columns.push({
				text: '', datafield: '__restore', width: 110, sortable: false, filterable: false,
				align: 'right', cellsalign: 'right', cellsrenderer: productActionRenderer('restore'),
			});
		}

		return columns;
	}

	function buildProductDatafields() {
		const names = { id: 'number', name: 'string', __rownum: 'number', __select: 'string', __actions: 'string', __restore: 'string' };

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
			height: productsGridHeight(),
			emptyMessage: t('org_products_empty'),
			getParams: function () {
				return productsState.search ? { q: productsState.search } : {};
			},
			onLoad: updateProductsMeta,
			onError: function () {
				showToast(t('org_products_load_failed'), 'error');
			},
		});

		if (productsGrid) {
			window.addEventListener('resize', function () {
				if (productsGrid) {
					productsGrid.$el.jqxGrid('height', productsGridHeight());
				}
			});
			$(productsGrid.$el).on('click', '.organization-product-delete-btn', function (event) {
				event.preventDefault();
				event.stopPropagation();
				openProductDeleteModal(
					$(this).attr('data-product-id'),
					$(this).attr('data-product-name')
				);
			});
			$(productsGrid.$el).on('click', '.organization-product-restore-btn', function (event) {
				event.preventDefault();
				event.stopPropagation();
				restoreArchivedProduct($(this).attr('data-product-id'));
			});

			if (productDetailEnabled) {
				if (!productDetail) {
					productDetail = window.ProductDetailModal.create(productDetailConfig, {
						onChanged: function () {
							if (productsGrid) {
								productsGrid.reload();
							}
						},
					});
				}

				productsGrid.$el.on('rowdoubleclick', function (event) {
					const target = event.originalEvent && event.originalEvent.target;

					if (target && target.closest && target.closest('.organization-product-delete-btn, .organization-product-restore-btn, .products-row-check, .products-row-check-all')) {
						return;
					}

					const rowIndex = event.args && event.args.rowindex;
					const row = rowIndex == null ? null : productsGrid.$el.jqxGrid('getrowdata', rowIndex);

					if (row && row.id != null) {
						productDetail.open(row.id);
					}
				});
			}
		}

		if (bulkDeleteProducts && productsGrid) {
			const gridEl = document.getElementById('organizationProductsGrid');

			function stopCheckboxEvent(event) {
				if (event.target.closest && event.target.closest('.products-row-check, .products-row-check-all')) {
					event.stopPropagation();
				}
			}

			gridEl.addEventListener('mousedown', stopCheckboxEvent, true);
			gridEl.addEventListener('click', stopCheckboxEvent, true);

			productsGrid.$el.on('change', '.products-row-check', function () {
				const id = this.getAttribute('data-id');

				if (!id) {
					return;
				}

				if (this.checked) {
					selectedProductIds.add(id);
				} else {
					selectedProductIds.delete(id);
				}

				syncProductHeaderCheckbox();
				syncProductBulkDeleteButton();
			});

			productsGrid.$el.on('change', '.products-row-check-all', function () {
				const ids = currentOrganizationProductIds();
				const checked = this.checked;

				ids.forEach(function (id) {
					if (checked) {
						selectedProductIds.add(id);
					} else {
						selectedProductIds.delete(id);
					}
				});

				productsGrid.$el.find('.products-row-check').each(function () {
					this.checked = selectedProductIds.has(this.getAttribute('data-id'));
				});
				this.indeterminate = false;
				syncProductBulkDeleteButton();
			});

			productsGrid.$el.on('bindingcomplete', function () {
				clearProductSelection();
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
					infoWrap.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(error.message || t('org_load_failed')) + '</p>';
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

		if (productDetail) {
			productDetail.hide();
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

		clearProductSelection();

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

	function currentOrganizationProductIds() {
		if (!productsGrid) {
			return [];
		}

		const rows = productsGrid.$el.jqxGrid('getrows') || [];

		return rows.map(function (row) {
			return row && row.id != null ? String(row.id) : '';
		}).filter(Boolean);
	}

	function syncProductBulkDeleteButton() {
		if (!productsDeleteSelectedBtn) {
			return;
		}

		productsDeleteSelectedBtn.classList.toggle('d-none', selectedProductIds.size < 2);
	}

	function syncProductHeaderCheckbox() {
		const header = document.querySelector('#organizationProductsGrid .products-row-check-all');

		if (!header) {
			return;
		}

		const ids = currentOrganizationProductIds();
		const selectedOnPage = ids.filter(function (id) {
			return selectedProductIds.has(id);
		}).length;

		header.checked = ids.length > 0 && selectedOnPage === ids.length;
		header.indeterminate = selectedOnPage > 0 && selectedOnPage < ids.length;
	}

	function clearProductSelection() {
		selectedProductIds.clear();
		syncProductBulkDeleteButton();
		document.querySelectorAll('#organizationProductsGrid .products-row-check').forEach(function (box) {
			box.checked = false;
		});
		syncProductHeaderCheckbox();
	}

	function deleteSelectedProducts() {
		const ids = Array.from(selectedProductIds);

		if (!bulkDeleteProducts || ids.length < 2) {
			return;
		}

		const url = productDeleteUrl.replace(/\/delete\/?$/, '/delete_selected');
		const body = new FormData();

		ids.forEach(function (id) {
			body.append('ids[]', id);
		});

		productsDeleteSelectedBtn.disabled = true;

		showConfirm({
			title: t('common_delete'),
			message: t('product_delete_selected_confirm', { count: ids.length }),
			confirmLabel: t('product_delete_selected'),
		}).then(function () {
			return fetchApi(url, { method: 'POST', body: body });
		}).then(function (result) {
			if (productDetail && selectedProductIds.has(String(productDetail.currentId()))) {
				productDetail.hide();
			}

			clearProductSelection();
			showToast(result.message || t('org_product_deleted'), 'success');

			if (productsGrid) {
				productsGrid.reload();
			}
		}).catch(function (error) {
			if (isConfirmCancelled(error) || isAbortError(error)) {
				return;
			}

			showToast(error.message || t('org_product_delete_failed'), 'error');
		}).finally(function () {
			productsDeleteSelectedBtn.disabled = false;
		});
	}

	if (bulkDeleteProducts) {
		productsDeleteSelectedBtn.addEventListener('click', function () {
			deleteSelectedProducts();
		});
	}

	function deleteProduct(productId) {
		if (!canDeleteProduct || !productDeleteUrl || !productId) {
			return Promise.reject(new Error(t('org_product_delete_unable')));
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
			title: t('org_product_delete_title'),
			message: t('org_product_delete_message', { name: '<strong>' + label + '</strong>' }),
			allowHtml: true,
			confirmLabel: t('common_delete'),
		})
			.then(function () {
				return deleteProduct(productId);
			})
			.then(function (result) {
				showToast(result.message || t('org_product_deleted'), 'success');

				if (productDetail && String(productDetail.currentId()) === String(productId)) {
					productDetail.hide();
				}

				if (productsGrid) {
					productsGrid.reload();
				}
			})
			.catch(function (error) {
				if (isConfirmCancelled(error) || isAbortError(error)) {
					return;
				}

				showToast(error.message || t('org_product_delete_failed'), 'error');
			});
	}

	function finishOrganizationChange(message) {
		showToast(message, 'success');

		if (modal) {
			modal.hide();
		}

		document.dispatchEvent(new CustomEvent('organizationsListChanged'));
	}

	function deleteCurrentOrganization() {
		const organizationId = detailState.organizationId;

		if (!organizationId || !organizationDeleteUrl || !organizationDeletePreviewUrl) {
			return;
		}

		fetchApi(organizationDeletePreviewUrl + '/' + organizationId).then(function (preview) {
			const count = preview.data ? Number(preview.data.product_count) || 0 : 0;

			return showConfirm({
				title: t('common_delete'),
				message: count > 0 ? t('org_delete_with_products', { count: count }) : t('org_delete_confirm'),
				confirmLabel: t('common_delete'),
			});
		}).then(function () {
			return fetchApi(organizationDeleteUrl + '/' + organizationId, { method: 'POST' });
		}).then(function (result) {
			if (!result) {
				return;
			}

			finishOrganizationChange(result.message || t('org_deleted'));
		}).catch(function (error) {
			if (isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('org_delete_failed'), 'error');
		});
	}

	function restoreCurrentOrganization() {
		const organizationId = detailState.organizationId;

		if (!organizationId || !organizationRestoreUrl) {
			return;
		}

		showConfirm({
			title: t('common_restore'),
			message: t('org_restore_confirm'),
			confirmLabel: t('common_restore'),
			confirmClass: 'btn btn-success',
		}).then(function () {
			return fetchApi(organizationRestoreUrl + '/' + organizationId, { method: 'POST' });
		}).then(function (result) {
			finishOrganizationChange(result.message || t('org_restored'));
		}).catch(function (error) {
			if (isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('org_restore_failed'), 'error');
		});
	}

	function restoreArchivedProduct(productId) {
		if (!productId || !productRestoreUrl || !productRestorePreviewUrl) {
			return;
		}

		fetchApi(productRestorePreviewUrl + '/' + productId).then(function (preview) {
			const data = preview.data || {};
			const message = data.needs_organization
				? t('product_restore_with_org', { name: data.organization_name || '' })
				: t('product_restore_confirm');

			return showConfirm({
				title: t('common_restore'),
				message: message,
				confirmLabel: t('common_restore'),
				confirmClass: 'btn btn-success',
			}).then(function () {
				const body = new FormData();

				if (data.needs_organization) {
					body.append('restore_organization', '1');
				}

				return fetchApi(productRestoreUrl + '/' + productId, { method: 'POST', body: body });
			});
		}).then(function (result) {
			if (!result) {
				return;
			}

			if (result.data && result.data.restored_organization) {
				finishOrganizationChange(result.message || t('product_restored'));
				return;
			}

			showToast(result.message || t('product_restored'), 'success');

			if (productsGrid) {
				productsGrid.reload();
			}
		}).catch(function (error) {
			if (isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('product_restore_failed'), 'error');
		});
	}

	if (organizationDeleteBtn) {
		organizationDeleteBtn.addEventListener('click', deleteCurrentOrganization);
	}

	if (organizationRestoreBtn) {
		organizationRestoreBtn.addEventListener('click', restoreCurrentOrganization);
	}

	const scheduleProductsSearch = debounce(function () {
		if (!productsGrid) {
			loadProducts();
			return;
		}

		productsGrid.gotoFirst();
		productsGrid.reload();
	}, 300);

	document.addEventListener('organizationRowSelected', function (event) {
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
