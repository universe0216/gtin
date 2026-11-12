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
	const detail = config.detail || {};
	const detailEnabled = !!(detail.enabled && detail.detailUrl);

	const locationClearBtn = document.getElementById('productsLocationClear');
	const exportBtn = document.getElementById('productsExportBtn');
	const deleteSelectedBtn = document.getElementById('productsDeleteSelectedBtn');
	const deleteBatchBtn = document.getElementById('productsDeleteBatchBtn');
	const deleteBatchModalEl = document.getElementById('productsDeleteBatchModal');
	const deleteBatchFileInput = document.getElementById('productsDeleteBatchFile');
	const deleteBatchFileName = document.getElementById('productsDeleteBatchFileName');
	const deleteBatchLoading = document.getElementById('productsDeleteBatchLoading');
	const deleteBatchPreview = document.getElementById('productsDeleteBatchPreview');
	const deleteBatchBody = document.getElementById('productsDeleteBatchBody');
	const deleteBatchEmpty = document.getElementById('productsDeleteBatchEmpty');
	const deleteBatchSubmit = document.getElementById('productsDeleteBatchSubmit');
	const generateKeyBtn = document.getElementById('productsGenerateKeyBtn');
	const locationNodes = config.locationNodes || [];
	const rowAction = detail.rowAction || {};
	let deleteBatchModal = null;
	let deleteBatchRows = [];

	if (typeof JqxGrid === 'undefined' || !document.getElementById('productsGrid')) {
		return;
	}

	if (typeof window.ProductDetailModal === 'undefined') {
		return;
	}

	const columnWidths = {
		name: 220,
		brand: 150,
		standard_number: 170,
		barcode_1d: 170,
		barcode_1d_type: 140,
		barcode_2d: 170,
		barcode_2d_type: 150,
		package_type: 150,
		organization_name: 200,
		processor_name: 160,
		price: 120,
		reg_date: 160,
		test_status: 140,
		deleted_at: 160,
	};

	let currentLocationId = '';
	let currentColumnFilters = {};
	const bulkDeleteEnabled = !!(deleteSelectedBtn && rowAction.enabled && rowAction.type === 'delete' && detail.deleteUrl);
	const selectedIds = new Set();
	let productDetail = null;

	function gridHeight() {
		const panel = document.querySelector('#productsPage .entity-list-panel');
		const toolbar = document.querySelector('#productsPage .entity-list-toolbar');

		if (!panel) {
			return 420;
		}

		return Math.max(240, panel.clientHeight - (toolbar ? toolbar.offsetHeight : 0) - 8) - 20;
	}

	function indexRenderer(row) {
		let number = row + 1;
		const host = $('#productsGrid');

		if (host.length) {
			const paging = host.jqxGrid('getpaginginformation');

			if (paging && paging.pagesize && row < paging.pagesize) {
				number = (paging.pagenum || 0) * paging.pagesize + row + 1;
			}
		}

		return '<div class="jqx-grid-cell-leftalign" style="margin-top:6px; text-align: center;">' + number + '</div>';
	}

	function selectRenderer(row, column, value, defaultHtml, props, rowdata) {
		const id = escapeAttr(rowdata && rowdata.id);
		const checked = selectedIds.has(String(rowdata && rowdata.id)) ? ' checked' : '';

		return '<div class="products-select-cell"><input type="checkbox" class="form-check-input products-row-check" data-id="' +
			id + '"' + checked + ' aria-label="' + escapeAttr(t('product_select_row')) + '"></div>';
	}

	function selectHeaderRenderer() {
		return '<div class="products-select-cell"><input type="checkbox" class="form-check-input products-row-check-all" aria-label="' +
			escapeAttr(t('product_select_page')) + '"></div>';
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
			cellsalign: 'center',
			align: 'right',
			cellsrenderer: indexRenderer,
		},
	].concat(columns.map(function (column) {
		const gridColumn = {
			text: column.label,
			datafield: column.key,
			width: columnWidths[column.key] || 160,
			filterable: true,
			filtertype: 'input',
		};

		if (column.pinned) {
			gridColumn.pinned = true;
		}

		if (column.key === 'name' && (detailEnabled || rowAction.enabled)) {
			gridColumn.cellsrenderer = nameCellRenderer;
		}

		return gridColumn;
	}));

	function nameActionButton(buttonClass, actionClass, icon, label, id, name) {
		return '<button type="button" class="btn btn-sm ' + buttonClass + ' products-name-icon ' + actionClass +
			'" data-id="' + id + '"' +
			(name === undefined ? '' : ' data-name="' + name + '"') +
			' title="' + escapeAttr(label) + '" aria-label="' + escapeAttr(label) + '">' +
			'<i class="fas ' + icon + '" aria-hidden="true"></i></button>';
	}

	function nameCellRenderer(row, column, value, defaultHtml, props, rowdata) {
		const id = escapeAttr(rowdata && rowdata.id);
		const name = escapeAttr(rowdata && rowdata.name);
		let actions = '';

		if (detailEnabled) {
			actions += nameActionButton('btn-outline-primary', 'entity-row-detail', 'fa-eye', t('common_detail'), id);
		}

		if (rowAction.enabled) {
			const restore = rowAction.type === 'restore';

			actions += nameActionButton(
				restore ? 'btn-outline-success' : 'btn-outline-danger',
				'entity-row-action',
				restore ? 'fa-rotate-left' : 'fa-trash',
				restore ? t('common_restore') : t('common_delete'),
				id,
				name
			);
		}

		return '<div class="products-name-cell"><span class="products-name-text">' + escapeHtml(value) +
			'</span><div class="products-name-actions">' + actions + '</div></div>';
	}

	if (bulkDeleteEnabled) {
		datafields.push({ name: '__select', type: 'string' });
		gridColumns.unshift({
			text: '',
			datafield: '__select',
			width: 42,
			pinned: true,
			sortable: false,
			filterable: false,
			menu: false,
			renderer: selectHeaderRenderer,
			cellsrenderer: selectRenderer,
		});
	}

	function readColumnFilters() {
		const filters = {};
		let info = [];

		try {
			info = $('#productsGrid').jqxGrid('getfilterinformation') || [];
		} catch (error) {
			return filters;
		}

		info.forEach(function (item) {
			const field = item && item.filtercolumn;
			const group = item && item.filter;

			if (!field || !group || typeof group.getfilters !== 'function') {
				return;
			}

			const parts = group.getfilters() || [];
			const value = parts.length ? String(parts[0].value == null ? '' : parts[0].value).trim() : '';

			if (value !== '') {
				filters[field] = value;
			}
		});

		return filters;
	}

	const grid = JqxGrid.remote('#productsGrid', {
		url: apiUrl,
		columns: gridColumns,
		datafields: datafields,
		pagesize: perPage,
		pagesizeoptions: perPageOptions,
		selectionmode: bulkDeleteEnabled ? 'none' : (detailEnabled ? 'singlerow' : 'none'),
		enablebrowserselection: true,
		clipboard: false,
		filterable: true,
		showfilterrow: true,
		height: gridHeight(),
		emptyMessage: config.emptyMessage,
		getParams: function () {
			const params = {};

			if (currentLocationId) {
				params.location_id = currentLocationId;
			}

			currentColumnFilters = readColumnFilters();
			Object.keys(currentColumnFilters).forEach(function (key) {
				params['filter[' + key + ']'] = currentColumnFilters[key];
			});

			return params;
		},
		onError: function () {
			showToast(t('common_failed_load_records'), 'error');
		},
	});

	if (!grid) {
		return;
	}

	productDetail = window.ProductDetailModal.create(detail, {
		onChanged: function () {
			grid.reload();
		},
	});

	function openDetail(productId) {
		productDetail.open(productId);
	}

	function deleteProduct(productId) {
		productDetail.deleteProduct(productId);
	}

	function restoreProduct(productId) {
		productDetail.restoreProduct(productId);
	}

	if (exportBtn && config.exportUrl) {
		exportBtn.addEventListener('click', function () {
			const params = new URLSearchParams();

			if (currentLocationId) {
				params.set('location_id', currentLocationId);
			}

			currentColumnFilters = readColumnFilters();
			Object.keys(currentColumnFilters).forEach(function (key) {
				params.set('filter[' + key + ']', currentColumnFilters[key]);
			});

			const sortInfo = $('#productsGrid').jqxGrid('getsortinformation');
			const direction = sortInfo && sortInfo.sortdirection;

			if (sortInfo && sortInfo.sortcolumn && direction && (direction.ascending || direction.descending)) {
				params.set('sort', sortInfo.sortcolumn);
				params.set('dir', direction.ascending ? 'asc' : 'desc');
			}

			const query = params.toString();
			window.location.href = config.exportUrl + (query ? '?' + query : '');
		});
	}

	if (generateKeyBtn && config.generateKeyUrl) {
		generateKeyBtn.addEventListener('click', function () {
			showConfirm({
				title: t('product_generate_key'),
				message: t('product_generate_key_confirm'),
				confirmLabel: t('product_generate_key'),
			}).then(function () {
				generateKeyBtn.disabled = true;

				return fetchApi(config.generateKeyUrl, { method: 'POST' });
			}).then(function (result) {
				if (!result) {
					return;
				}

				showToast(result.message || t('product_generate_key_done'), 'success');
				grid.reload();
			}).catch(function (error) {
				if (window.isConfirmCancelled && isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || t('product_generate_key_failed'), 'error');
			}).finally(function () {
				generateKeyBtn.disabled = false;
			});
		});
	}

	function currentPageIds() {
		const rows = grid.$el.jqxGrid('getrows') || [];

		return rows.map(function (row) {
			return row && row.id != null ? String(row.id) : '';
		}).filter(Boolean);
	}

	function syncBulkDeleteButton() {
		if (!deleteSelectedBtn) {
			return;
		}

		deleteSelectedBtn.classList.toggle('d-none', selectedIds.size < 2);
	}

	function syncHeaderCheckbox() {
		const header = document.querySelector('#productsGrid .products-row-check-all');

		if (!header) {
			return;
		}

		const ids = currentPageIds();
		const selectedOnPage = ids.filter(function (id) {
			return selectedIds.has(id);
		}).length;

		header.checked = ids.length > 0 && selectedOnPage === ids.length;
		header.indeterminate = selectedOnPage > 0 && selectedOnPage < ids.length;
	}

	function clearRowSelection() {
		selectedIds.clear();
		syncBulkDeleteButton();
		document.querySelectorAll('#productsGrid .products-row-check').forEach(function (box) {
			box.checked = false;
		});
		syncHeaderCheckbox();
	}

	if (bulkDeleteEnabled) {
		const gridEl = document.getElementById('productsGrid');

		function stopCheckboxEvent(event) {
			if (event.target.closest && event.target.closest('.products-row-check, .products-row-check-all')) {
				event.stopPropagation();
			}
		}

		gridEl.addEventListener('mousedown', stopCheckboxEvent, true);
		gridEl.addEventListener('click', stopCheckboxEvent, true);

		grid.$el.on('change', '.products-row-check', function () {
			const id = this.getAttribute('data-id');

			if (!id) {
				return;
			}

			if (this.checked) {
				selectedIds.add(id);
			} else {
				selectedIds.delete(id);
			}

			syncHeaderCheckbox();
			syncBulkDeleteButton();
		});

		grid.$el.on('change', '.products-row-check-all', function () {
			const ids = currentPageIds();
			const checked = this.checked;

			ids.forEach(function (id) {
				if (checked) {
					selectedIds.add(id);
				} else {
					selectedIds.delete(id);
				}
			});

			grid.$el.find('.products-row-check').each(function () {
				this.checked = selectedIds.has(this.getAttribute('data-id'));
			});
			this.indeterminate = false;
			syncBulkDeleteButton();
		});

		grid.$el.on('bindingcomplete', function () {
			clearRowSelection();
		});

		deleteSelectedBtn.addEventListener('click', function () {
			deleteSelectedProducts();
		});
	}

	if (detailEnabled || rowAction.enabled) {
		grid.$el.on('mouseover', '.jqx-grid-content .jqx-grid-cell', function () {
			const row = this.closest('.jqx-grid-row-csp, [role="row"]');

			if (!row) {
				return;
			}

			const current = grid.$el[0].querySelector('.products-row-hover');

			if (current === row) {
				return;
			}

			if (current) {
				current.classList.remove('products-row-hover');
			}

			row.classList.add('products-row-hover');
		});

		const gridContent = document.querySelector('#productsGrid .jqx-grid-content');

		if (gridContent) {
			gridContent.addEventListener('mouseleave', function () {
				const current = gridContent.querySelector('.products-row-hover');

				if (current) {
					current.classList.remove('products-row-hover');
				}
			});
		}
	}

	grid.$el.on('mousedown', '.entity-row-detail, .entity-row-action', function (event) {
		event.stopPropagation();
	});

	grid.$el.on('click', '.entity-row-detail', function (event) {
		event.preventDefault();
		event.stopPropagation();
		const id = this.getAttribute('data-id');

		if (id) {
			openDetail(id);
		}
	});

	if (detailEnabled) {
		grid.$el.on('rowdoubleclick', function (event) {
			const target = event.originalEvent && event.originalEvent.target;

			if (target && target.closest && target.closest('.entity-row-detail, .entity-row-action, .products-row-check, .products-row-check-all')) {
				return;
			}

			const rowIndex = event.args && event.args.rowindex;
			const row = rowIndex == null ? null : grid.$el.jqxGrid('getrowdata', rowIndex);

			if (row && row.id != null) {
				openDetail(row.id);
			}
		});
	}

	window.addEventListener('resize', function () {
		grid.$el.jqxGrid('height', gridHeight());
	});

	grid.$el.on('click', '.entity-row-action', function (event) {
		event.preventDefault();
		event.stopPropagation();
		const id = this.getAttribute('data-id');
		const name = this.getAttribute('data-name') || '';

		if (rowAction.type === 'restore') {
			restoreProduct(id, name);
			return;
		}

		deleteProduct(id, name);
	});

	document.addEventListener('productsListChanged', function () {
		grid.reload();
	});

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

	function locationChain(id) {
		const byId = locationChain.byId || (locationChain.byId = {});

		if (!locationChain.ready) {
			locationNodes.forEach(function (node) {
				byId[String(node.id)] = node;
			});
			locationChain.ready = true;
		}

		const chain = [];
		const seen = {};
		let current = byId[String(id)];

		while (current && !seen[current.id]) {
			seen[current.id] = true;
			chain.unshift(current.name);
			const parentId = current.parent_id == null ? '' : String(current.parent_id);
			current = parentId ? byId[parentId] : null;
		}

		return chain.length ? chain.join(' / ') : (id == null ? '' : String(id));
	}

	function locationDropContent(text) {
		return '<span class="organization-location-dropdown-label">' + escapeHtml(text || '') + '</span>';
	}

	function syncLocationClear() {
		if (!locationClearBtn) {
			return;
		}

		locationClearBtn.classList.toggle('d-none', currentLocationId === '');
	}

	function mountLocationFilter() {
		const $ = window.jQuery;
		const host = document.getElementById('productsLocationDropDown');

		if (!host || !$ || !$.fn || !$.fn.jqxDropDownButton || !$.fn.jqxTree) {
			return;
		}

		const $drop = $(host);
		const $tree = $('#productsLocationTree');
		let applyingSelection = false;

		function showSelection(id) {
			$drop.jqxDropDownButton('setContent', locationDropContent(id ? locationChain(id) : ''));
		}

		$drop.jqxDropDownButton({
			width: '100%',
			height: 31,
			theme: 'dark',
			popupZIndex: 2000,
			dropDownWidth: 320,
			dropDownHeight: 260,
			initContent: function () {
				$tree.jqxTree({
					source: locationTreeSource(''),
					width: 320,
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

					currentLocationId = String(item.value);
					showSelection(currentLocationId);
					syncLocationClear();
					$drop.jqxDropDownButton('close');
					applySearch();
				});

				const currentId = String(currentLocationId || '');

				if (currentId) {
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

		showSelection('');

		if (locationClearBtn) {
			locationClearBtn.addEventListener('click', function () {
				currentLocationId = '';
				showSelection('');
				syncLocationClear();

				if ($tree.data('jqxTree')) {
					applyingSelection = true;
					try {
						$tree.jqxTree('selectItem', null);
					} catch (error) {
						// The tree is created the first time the dropdown opens.
					}
					applyingSelection = false;
				}

				if ($drop.jqxDropDownButton('isOpened')) {
					$drop.jqxDropDownButton('close');
				}

				applySearch();
			});
		}
	}

	function applySearch() {
		grid.gotoFirst();
		grid.reload();
	}

	mountLocationFilter();

	function deleteSelectedProducts() {
		const ids = Array.from(selectedIds);

		if (!bulkDeleteEnabled || ids.length < 2) {
			return;
		}

		const url = detail.deleteUrl.replace(/\/delete\/?$/, '/delete_selected');
		const body = new FormData();

		ids.forEach(function (id) {
			body.append('ids[]', id);
		});

		deleteSelectedBtn.disabled = true;

		showConfirm({
			title: t('common_delete'),
			message: t('product_delete_selected_confirm', { count: ids.length }),
			confirmLabel: t('product_delete_selected'),
		}).then(function () {
			return fetchApi(url, { method: 'POST', body: body });
		}).then(function (result) {
			if (selectedIds.has(String(productDetail.currentId()))) {
				productDetail.hide();
			}

			clearRowSelection();
			showToast(result.message || t('product_deleted'), 'success');
			grid.reload();
		}).catch(function (error) {
			if (window.isConfirmCancelled && isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('product_delete_failed'), 'error');
		}).finally(function () {
			deleteSelectedBtn.disabled = false;
		});
	}

	function resetDeleteBatchModal() {
		deleteBatchRows = [];

		if (deleteBatchFileInput) {
			deleteBatchFileInput.value = '';
		}

		if (deleteBatchFileName) {
			deleteBatchFileName.textContent = '';
			deleteBatchFileName.classList.add('d-none');
		}

		if (deleteBatchLoading) {
			deleteBatchLoading.classList.add('d-none');
		}

		if (deleteBatchPreview) {
			deleteBatchPreview.classList.add('d-none');
		}

		if (deleteBatchBody) {
			deleteBatchBody.innerHTML = '';
		}

		if (deleteBatchEmpty) {
			deleteBatchEmpty.classList.add('d-none');
		}

		if (deleteBatchSubmit) {
			deleteBatchSubmit.disabled = true;
		}
	}

	function renderDeleteBatchRows(rows) {
		deleteBatchRows = Array.isArray(rows) ? rows : [];

		if (!deleteBatchBody) {
			return;
		}

		if (!deleteBatchRows.length) {
			deleteBatchBody.innerHTML = '';

			if (deleteBatchPreview) {
				deleteBatchPreview.classList.add('d-none');
			}

			if (deleteBatchEmpty) {
				deleteBatchEmpty.classList.remove('d-none');
			}

			if (deleteBatchSubmit) {
				deleteBatchSubmit.disabled = true;
			}

			return;
		}

		deleteBatchBody.innerHTML = deleteBatchRows.map(function (row) {
			return '<tr>' +
				'<td>' + escapeHtml(row.row_index || '') + '</td>' +
				'<td>' + escapeHtml(row.name || '') + '</td>' +
				'<td>' + escapeHtml(row.barcode_1d || '') + '</td>' +
			'</tr>';
		}).join('');

		if (deleteBatchEmpty) {
			deleteBatchEmpty.classList.add('d-none');
		}

		if (deleteBatchPreview) {
			deleteBatchPreview.classList.remove('d-none');
		}

		if (deleteBatchSubmit) {
			deleteBatchSubmit.disabled = false;
		}
	}

	function previewDeleteBatchFile(file) {
		if (!file || !config.batchDeletePreviewUrl) {
			return;
		}

		if (!/\.(xls|xlsx)$/i.test(file.name || '')) {
			showToast(t('product_delete_batch_invalid'), 'error');
			resetDeleteBatchModal();
			return;
		}

		const body = new FormData();
		body.append('file', file);

		if (deleteBatchLoading) {
			deleteBatchLoading.classList.remove('d-none');
		}

		if (deleteBatchPreview) {
			deleteBatchPreview.classList.add('d-none');
		}

		if (deleteBatchEmpty) {
			deleteBatchEmpty.classList.add('d-none');
		}

		if (deleteBatchSubmit) {
			deleteBatchSubmit.disabled = true;
		}

		if (deleteBatchFileName) {
			deleteBatchFileName.textContent = file.name;
			deleteBatchFileName.classList.remove('d-none');
		}

		fetchApi(config.batchDeletePreviewUrl, {
			method: 'POST',
			body: body,
		}).then(function (result) {
			renderDeleteBatchRows(result.rows || []);
		}).catch(function (error) {
			deleteBatchRows = [];

			if (deleteBatchBody) {
				deleteBatchBody.innerHTML = '';
			}

			if (deleteBatchPreview) {
				deleteBatchPreview.classList.add('d-none');
			}

			if (deleteBatchEmpty) {
				deleteBatchEmpty.classList.add('d-none');
			}

			if (deleteBatchSubmit) {
				deleteBatchSubmit.disabled = true;
			}

			showToast(error.message || t('product_delete_batch_failed'), 'error');
		}).finally(function () {
			if (deleteBatchLoading) {
				deleteBatchLoading.classList.add('d-none');
			}
		});
	}

	function submitDeleteBatch() {
		if (!config.batchDeleteUrl || !deleteBatchRows.length) {
			return;
		}

		const barcodes = deleteBatchRows.map(function (row) {
			return String(row.barcode_1d || '').trim();
		}).filter(Boolean);

		if (!barcodes.length) {
			showToast(t('product_delete_batch_empty'), 'error');
			return;
		}

		const body = new FormData();

		barcodes.forEach(function (barcode) {
			body.append('barcodes[]', barcode);
		});

		if (deleteBatchSubmit) {
			deleteBatchSubmit.disabled = true;
		}

		showConfirm({
			title: t('product_delete_batch'),
			message: t('product_delete_batch_confirm', { count: barcodes.length }),
			confirmLabel: t('common_delete'),
		}).then(function () {
			return fetchApi(config.batchDeleteUrl, { method: 'POST', body: body });
		}).then(function (result) {
			if (deleteBatchModal) {
				deleteBatchModal.hide();
			}

			resetDeleteBatchModal();
			clearRowSelection();
			showToast(result.message || t('product_delete_batch_done'), 'success');
			grid.reload();
		}).catch(function (error) {
			if (window.isConfirmCancelled && isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('product_delete_batch_failed'), 'error');
		}).finally(function () {
			if (deleteBatchSubmit) {
				deleteBatchSubmit.disabled = !deleteBatchRows.length;
			}
		});
	}

	if (deleteBatchBtn && deleteBatchModalEl && typeof mdb !== 'undefined' && config.batchDeletePreviewUrl && config.batchDeleteUrl) {
		deleteBatchModal = mdb.Modal.getOrCreateInstance(deleteBatchModalEl);

		deleteBatchBtn.addEventListener('click', function () {
			resetDeleteBatchModal();
			deleteBatchModal.show();
		});

		deleteBatchModalEl.addEventListener('hidden.mdb.modal', function () {
			resetDeleteBatchModal();
		});

		if (deleteBatchFileInput) {
			deleteBatchFileInput.addEventListener('change', function () {
				const file = this.files && this.files[0] ? this.files[0] : null;

				if (!file) {
					resetDeleteBatchModal();
					return;
				}

				previewDeleteBatchFile(file);
			});
		}

		if (deleteBatchSubmit) {
			deleteBatchSubmit.addEventListener('click', function () {
				submitDeleteBatch();
			});
		}
	}
})();
