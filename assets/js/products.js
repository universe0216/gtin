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

	const searchForm = document.getElementById('productsSearchForm');
	const searchInput = document.getElementById('productsSearchInput');
	const clearBtn = document.getElementById('productsClearBtn');
	const locationClearBtn = document.getElementById('productsLocationClear');
	const toolbarMeta = document.getElementById('productsToolbarMeta');
	const locationNodes = config.locationNodes || [];
	const modalEl = document.getElementById('productDetailModal');
	const modalTitle = document.getElementById('productDetailModalLabel');
	const detailForm = document.getElementById('productDetailForm');
	const formWrap = document.getElementById('productDetailFormWrap');
	const loadingEl = document.getElementById('productDetailLoading');
	const alertEl = document.getElementById('productDetailAlert');
	const downloadBtn = document.getElementById('productDetailDownloadBtn');
	const saveBtn = document.getElementById('productDetailSaveBtn');
	const canEditProduct = !!(detail.canEdit && detail.updateUrl);
	const deleteBtn = document.getElementById('productDetailDeleteBtn');
	const restoreBtn = document.getElementById('productDetailRestoreBtn');
	const rowAction = detail.rowAction || {};

	if (typeof JqxGrid === 'undefined' || !document.getElementById('productsGrid')) {
		return;
	}

	const columnWidths = {
		name: 220,
		brand: 150,
		standard_number: 170,
		barcode_1d: 170,
		package_type: 150,
		organization_name: 200,
		price: 120,
		reg_date: 160,
		test_status: 140,
	};

	let currentSearch = '';
	let currentLocationId = '';
	let detailModal = null;
	let detailRequest = null;
	let currentProductId = null;

	function updateMeta(data) {
		if (!toolbarMeta) {
			return;
		}

		if (data && (data.total || 0) > 0) {
			toolbarMeta.textContent = data.range_start + '–' + data.range_end + ' of ' + data.total;
			toolbarMeta.classList.remove('d-none');
		} else {
			const filtered = currentSearch !== '' || currentLocationId !== '';
			toolbarMeta.textContent = filtered ? config.emptySearchMessage : '';
			toolbarMeta.classList.toggle('d-none', !filtered);
		}
	}

	function gridHeight() {
		const panel = document.querySelector('#productsPage .entity-list-panel');
		const toolbar = document.querySelector('#productsPage .entity-list-toolbar');

		if (!panel) {
			return 420;
		}

		return Math.max(240, panel.clientHeight - (toolbar ? toolbar.offsetHeight : 0) - 8);
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

	function rowActionRenderer(row, column, value, defaultHtml, props, rowdata) {
		const label = rowAction.type === 'restore' ? t('common_restore') : t('common_delete');
		const buttonClass = rowAction.type === 'restore' ? 'btn-outline-success' : 'btn-outline-danger';

		return '<div class="jqx-action-cell">' +
			'<button type="button" class="btn btn-sm ' + buttonClass + ' entity-row-action" data-id="' +
			escapeAttr(rowdata && rowdata.id) + '" data-name="' + escapeAttr(rowdata && rowdata.name) + '">' +
			escapeHtml(label) + '</button></div>';
	}

	if (rowAction.enabled) {
		datafields.push({ name: '__actions', type: 'string' });
		gridColumns.push({
			text: '',
			datafield: '__actions',
			width: 120,
			sortable: false,
			filterable: false,
			cellsrenderer: rowActionRenderer,
		});
	}

	const grid = JqxGrid.remote('#productsGrid', {
		url: apiUrl,
		columns: gridColumns,
		datafields: datafields,
		pagesize: perPage,
		pagesizeoptions: perPageOptions,
		selectionmode: detailEnabled ? 'singlerow' : 'none',
		height: gridHeight(),
		emptyMessage: config.emptyMessage,
		getParams: function () {
			const params = {};

			if (currentSearch) {
				params.q = currentSearch;
			}

			if (currentLocationId) {
				params.location_id = currentLocationId;
			}

			return params;
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
		grid.$el.on('mousedown', '.entity-row-action', function (event) {
			event.stopPropagation();
		});

		grid.$el.on('rowclick', function (event) {
			const args = event.args || {};
			const original = args.originalEvent;
			const target = original && original.target
				? (original.target.nodeType === 3 ? original.target.parentElement : original.target)
				: null;

			if (target && target.closest && target.closest('.entity-row-action')) {
				return;
			}

			const bound = args.row && args.row.bounddata ? args.row.bounddata : null;
			const row = bound || grid.$el.jqxGrid('getrowdata', args.rowindex);
			const productId = row && row.id;

			if (productId) {
				openDetail(productId);
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

	function initModal() {
		if (typeof mdb === 'undefined' || !modalEl) {
			return;
		}

		detailModal = mdb.Modal.getOrCreateInstance(modalEl);
	}

	function setDetailLoading(isLoading) {
		if (loadingEl) {
			loadingEl.classList.toggle('d-none', !isLoading);
		}

		if (formWrap) {
			formWrap.classList.toggle('d-none', isLoading);
		}
	}

	function hideDetailAlert() {
		if (!alertEl) {
			return;
		}

		alertEl.className = 'alert d-none';
		alertEl.textContent = '';
	}

	function showDetailAlert(message) {
		if (!alertEl) {
			return;
		}

		alertEl.className = 'alert alert-danger';
		alertEl.textContent = message;
	}

	function fieldValue(data, field) {
		const value = data[field.name];

		if (value === null || value === undefined) {
			return '';
		}

		if (field.type === 'date') {
			const text = String(value).trim();
			return text.length >= 10 ? text.slice(0, 10) : text;
		}

		return String(value);
	}

	function detailActionButtons() {
		return [downloadBtn, deleteBtn, restoreBtn, saveBtn];
	}

	function setDetailActionsDisabled(isDisabled) {
		detailActionButtons().forEach(function (button) {
			if (button) {
				button.disabled = isDisabled;
			}
		});
	}

	function setDetailSaving(isSaving) {
		if (!saveBtn) {
			return;
		}

		saveBtn.disabled = isSaving;
		const spinner = saveBtn.querySelector('.spinner-border');

		if (spinner) {
			spinner.classList.toggle('d-none', !isSaving);
		}
	}

	function renderDetailForm(data) {
		if (!formWrap) {
			return;
		}

		const fields = detail.fields || [];
		let html = '<div class="entity-detail-info-grid">';

		fields.forEach(function (field) {
			const inputId = 'productDetailField_' + field.name;
			const value = escapeHtml(fieldValue(data, field));
			const locked = !canEditProduct || field.readonly;
			const nameAttr = locked ? '' : ' name="' + escapeAttr(field.name) + '"';
			const readOnlyAttr = locked ? ' readonly' : '';
			let control = '';

			if (field.type === 'textarea') {
				control = '<textarea class="form-control form-control-sm" id="' + inputId + '"' + nameAttr + ' rows="3"' + readOnlyAttr + '>' + value + '</textarea>';
			} else {
				const type = field.type === 'date' ? 'date' : 'text';
				control = '<input type="' + type + '" class="form-control form-control-sm" id="' + inputId + '"' + nameAttr + ' value="' + value + '"' + readOnlyAttr + '>';
			}

			html +=
				'<div class="entity-detail-info-item">' +
					'<label class="entity-detail-info-label" for="' + inputId + '">' + escapeHtml(field.label) + '</label>' +
					control +
				'</div>';
		});

		html += '</div>';
		formWrap.innerHTML = html;
		setDetailActionsDisabled(false);
	}

	function openDetail(productId) {
		if (!detailEnabled || !detailModal) {
			return;
		}

		currentProductId = productId;
		hideDetailAlert();
		setDetailLoading(true);

		setDetailActionsDisabled(true);

		if (formWrap) {
			formWrap.innerHTML = '';
		}

		if (modalTitle) {
			modalTitle.textContent = t('title_products');
		}

		detailModal.show();

		if (detailRequest && detailRequest.abort) {
			detailRequest.abort();
		}

		const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		detailRequest = controller;

		fetchApi(detail.detailUrl.replace(/\/$/, '') + '/' + productId, {
			signal: controller ? controller.signal : undefined,
		}).then(function (result) {
			const record = result.data || {};
			currentProductId = record.id || productId;

			if (modalTitle) {
				modalTitle.textContent = record.name || t('title_products');
			}

			renderDetailForm(record);
			setDetailLoading(false);
		}).catch(function (error) {
			if (error && error.name === 'AbortError') {
				return;
			}

			setDetailLoading(false);
			showDetailAlert(error.message || t('common_failed_load_records'));
		});
	}

	function downloadFileName(disposition, fallback) {
		const header = String(disposition || '');
		const encoded = /filename\*=UTF-8''([^;]+)/i.exec(header);
		const plain = /filename="([^"]+)"/i.exec(header);

		if (encoded) {
			try {
				return decodeURIComponent(encoded[1]);
			} catch (error) {
				return encoded[1];
			}
		}

		if (plain) {
			return plain[1];
		}

		return fallback;
	}

	function downloadProduct(button) {
		if (!currentProductId || !detail.downloadUrl || button.disabled) {
			return;
		}

		button.disabled = true;

		fetch(detail.downloadUrl.replace(/\/$/, '') + '/' + currentProductId, {
			credentials: 'same-origin',
		}).then(function (response) {
			const type = response.headers.get('content-type') || '';

			if (type.indexOf('application/json') !== -1) {
				return response.json().then(function (data) {
					throw new Error((data && data.message) || t('product_download_failed'));
				});
			}

			if (!response.ok) {
				throw new Error(t('product_download_failed'));
			}

			return response.blob().then(function (blob) {
				return {
					blob: blob,
					filename: downloadFileName(response.headers.get('content-disposition'), 'product.zip'),
				};
			});
		}).then(function (file) {
			const url = URL.createObjectURL(file.blob);
			const link = document.createElement('a');
			link.href = url;
			link.download = file.filename;
			document.body.appendChild(link);
			link.click();
			link.remove();
			URL.revokeObjectURL(url);
		}).catch(function (error) {
			showToast(error.message || t('product_download_failed'), 'error');
		}).finally(function () {
			button.disabled = false;
		});
	}

	if (detailForm) {
		detailForm.addEventListener('submit', function (event) {
			event.preventDefault();

			if (!canEditProduct || !currentProductId || !saveBtn || saveBtn.disabled) {
				return;
			}

			hideDetailAlert();
			setDetailSaving(true);

			fetchApi(detail.updateUrl.replace(/\/$/, '') + '/' + currentProductId, {
				method: 'POST',
				body: new FormData(detailForm),
			}).then(function (result) {
				const record = result.data || {};
				currentProductId = record.id || currentProductId;

				if (modalTitle) {
					modalTitle.textContent = record.name || t('title_products');
				}

				renderDetailForm(record);
				grid.reload();
				showToast(result.message || t('common_record_updated'), 'success');
			}).catch(function (error) {
				showDetailAlert(error.message || t('common_failed_update'));
			}).finally(function () {
				setDetailSaving(false);
			});
		});
	}

	if (downloadBtn) {
		downloadBtn.addEventListener('click', function () {
			downloadProduct(downloadBtn);
		});
	}

	function deleteProduct(productId, productName) {
		if (!productId || !detail.deleteUrl) {
			return;
		}

		showConfirm({
			title: t('common_delete'),
			message: t('product_delete_confirm'),
			confirmLabel: t('common_delete'),
		}).then(function () {
			return fetchApi(detail.deleteUrl.replace(/\/$/, '') + '/' + productId, { method: 'POST' });
		}).then(function (result) {
			showToast(result.message || t('product_deleted'), 'success');

			if (String(currentProductId) === String(productId) && detailModal) {
				detailModal.hide();
			}

			grid.reload();
		}).catch(function (error) {
			if (window.isConfirmCancelled && isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('product_delete_failed'), 'error');
		});
	}

	function restoreProduct(productId) {
		if (!productId || !detail.restoreUrl) {
			return;
		}

		const previewUrl = (detail.restorePreviewUrl || (detail.rowAction && detail.rowAction.previewUrl) || '').replace(/\/$/, '');
		const restoreUrl = detail.restoreUrl.replace(/\/$/, '') + '/' + productId;

		fetchApi(previewUrl + '/' + productId).then(function (preview) {
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

				return fetchApi(restoreUrl, { method: 'POST', body: body });
			});
		}).then(function (result) {
			if (!result) {
				return;
			}

			showToast(result.message || t('product_restored'), 'success');

			if (String(currentProductId) === String(productId) && detailModal) {
				detailModal.hide();
			}

			grid.reload();
		}).catch(function (error) {
			if (window.isConfirmCancelled && isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('product_restore_failed'), 'error');
		});
	}

	if (deleteBtn) {
		deleteBtn.addEventListener('click', function () {
			deleteProduct(currentProductId);
		});
	}

	if (restoreBtn) {
		restoreBtn.addEventListener('click', function () {
			restoreProduct(currentProductId);
		});
	}

	initModal();
})();
