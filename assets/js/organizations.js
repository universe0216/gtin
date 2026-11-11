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
	const rowAction = (config.detail && config.detail.rowAction) || {};

	const expiryFilter = document.getElementById('organizationsExpiryFilter');
	const locationClearBtn = document.getElementById('organizationLocationFilterClear');
	const exportBtn = document.getElementById('organizationsExportBtn');
	const locationNodes = (config.detail && config.detail.locationNodes) || [];

	if (typeof JqxGrid === 'undefined' || !document.getElementById('organizationsGrid')) {
		return;
	}

	const columnWidths = {
		name: 240,
		reg_number: 180,
		gs1_prefix: 140,
		parent_org: 200,
		phone: 150,
		currency: 130,
		init_date: 150,
		reg_date: 160,
		expiry_date: 150,
	};

	let currentExpiryStatus = '';
	let currentLocationId = '';
	let currentColumnFilters = {};

	function hasActiveFilter() {
		return currentExpiryStatus !== '' || currentLocationId !== '' || Object.keys(currentColumnFilters).length > 0;
	}

	function todayDateValue() {
		const now = new Date();
		const month = String(now.getMonth() + 1).padStart(2, '0');
		const day = String(now.getDate()).padStart(2, '0');

		return now.getFullYear() + '-' + month + '-' + day;
	}

	function normalizeExpiryDate(value) {
		const raw = String(value == null ? '' : value).trim();

		return raw.length >= 10 ? raw.slice(0, 10) : '';
	}

	function isExpiredOrganization(rowdata) {
		const expiry = normalizeExpiryDate(rowdata && rowdata.expiry_date);

		return expiry !== '' && expiry < todayDateValue();
	}

	function organizationRowClass(row, columnfield, value, rowdata) {
		const data = rowdata || ($('#organizationsGrid').jqxGrid('getrowdata', row) || {});

		if (isExpiredOrganization(data)) {
			return 'organization-grid-row-expired';
		}

		if (columnfield === 'currency') {
			const code = String(value == null ? '' : value);

			if (code === '1') {
				return 'organization-currency-foreign';
			}

			if (code === '2') {
				return 'organization-currency-domestic';
			}
		}

		return '';
	}

	function readColumnFilters() {
		const filters = {};
		let info = [];

		try {
			info = $('#organizationsGrid').jqxGrid('getfilterinformation') || [];
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
			let value = parts.length ? String(parts[0].value == null ? '' : parts[0].value).trim() : '';

			if (field === 'currency') {
				if (value === '1' || value === t('currency_foreign')) {
					value = '1';
				} else if (value === '2' || value === t('currency_domestic')) {
					value = '2';
				} else {
					value = '';
				}
			}

			if (value !== '') {
				filters[field] = value;
			}
		});

		return filters;
	}

	function syncLocationClear() {
		if (!locationClearBtn) {
			return;
		}

		locationClearBtn.classList.toggle('d-none', currentLocationId === '');
	}

	function updateMeta(data) {

	}

	function gridHeight() {
		const panel = document.querySelector('#organizationsPage .entity-list-panel');
		const toolbar = document.querySelector('#organizationsPage .entity-list-toolbar');

		if (!panel) {
			return 420;
		}

		return Math.max(240, panel.clientHeight - (toolbar ? toolbar.offsetHeight : 0) - 8) - 15;
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

	function currencyRenderer(row, column, value) {
		return '<div class="jqx-grid-cell-leftalign" style="margin-top:6px;">' + escapeHtml(currencyLabel(value)) + '</div>';
	}

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
			cellclassname: organizationRowClass,
		},
	].concat(columns.map(function (column) {
		const gridColumn = {
			text: column.label,
			datafield: column.key,
			width: columnWidths[column.key] || 160,
			filterable: true,
			filtertype: 'input',
			cellclassname: organizationRowClass,
		};

		if (column.pinned) {
			gridColumn.pinned = true;
		}

		if (column.key === 'currency') {
			gridColumn.filtertype = 'list';
			gridColumn.filteritems = [
				{ label: t('currency_foreign'), value: '1' },
				{ label: t('currency_domestic'), value: '2' },
			];
			gridColumn.createfilterwidget = function (columnInfo, columnElement, widget) {
				widget.jqxDropDownList({
					autoDropDownHeight: true,
					placeHolder: ' ',
					source: [
						{ label: t('currency_foreign'), value: '1' },
						{ label: t('currency_domestic'), value: '2' },
					],
					displayMember: 'label',
					valueMember: 'value',
					popupZIndex: 999999,
				});
			};
			gridColumn.cellsrenderer = currencyRenderer;
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

	const grid = JqxGrid.remote('#organizationsGrid', {
		url: apiUrl,
		columns: gridColumns,
		datafields: datafields,
		pagesize: perPage,
		pagesizeoptions: perPageOptions,
		selectionmode: detailEnabled ? 'singlerow' : 'none',
		enablebrowserselection: true,
		clipboard: false,
		filterable: true,
		showfilterrow: true,
		height: gridHeight(),
		emptyMessage: config.emptyMessage,
		getParams: function () {
			const params = {};

			if (currentExpiryStatus) {
				params.expiry_status = currentExpiryStatus;
			}

			if (currentLocationId) {
				params.location_id = currentLocationId;
			}

			currentColumnFilters = readColumnFilters();
			Object.keys(currentColumnFilters).forEach(function (key) {
				params['filter[' + key + ']'] = currentColumnFilters[key];
			});

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

	if (exportBtn && config.exportUrl) {
		exportBtn.addEventListener('click', function () {
			const params = new URLSearchParams();

			if (currentExpiryStatus) {
				params.set('expiry_status', currentExpiryStatus);
			}

			if (currentLocationId) {
				params.set('location_id', currentLocationId);
			}

			currentColumnFilters = readColumnFilters();
			Object.keys(currentColumnFilters).forEach(function (key) {
				params.set('filter[' + key + ']', currentColumnFilters[key]);
			});

			const sortInfo = $('#organizationsGrid').jqxGrid('getsortinformation');
			const direction = sortInfo && sortInfo.sortdirection;

			if (sortInfo && sortInfo.sortcolumn && direction && (direction.ascending || direction.descending)) {
				params.set('sort', sortInfo.sortcolumn);
				params.set('dir', direction.ascending ? 'asc' : 'desc');
			}

			const query = params.toString();
			window.location.href = config.exportUrl + (query ? '?' + query : '');
		});
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

	function locationTreeHeight() {
		const panel = document.querySelector('#organizationsPage .organizations-location-panel');
		const header = document.querySelector('#organizationsPage .organizations-location-header');

		if (!panel) {
			return 320;
		}

		return Math.max(160, panel.clientHeight - (header ? header.offsetHeight : 0) - 12);
	}

	function mountLocationFilter() {
		const host = document.getElementById('organizationLocationFilterTree');
		const $ = window.jQuery;

		if (!host || !$ || !$.fn || !$.fn.jqxTree) {
			return null;
		}

		const $tree = $(host);
		let applyingSelection = false;

		$tree.jqxTree({
			source: locationTreeSource(''),
			width: '100%',
			height: locationTreeHeight(),
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
			const id = item && item.value != null ? String(item.value) : '';

			if (id === '' || id === currentLocationId) {
				return;
			}

			currentLocationId = id;
			syncLocationClear();
			applySearch();
		});

		if (locationClearBtn) {
			locationClearBtn.addEventListener('click', function () {
				currentLocationId = '';
				applyingSelection = true;

				try {
					$tree.jqxTree('selectItem', null);
				} catch (error) {
					// Clearing the highlight is optional; the list filter is already reset.
				}

				applyingSelection = false;
				syncLocationClear();
				applySearch();
			});
		}

		return $tree;
	}

	const locationTree = mountLocationFilter();

	window.addEventListener('resize', function () {
		grid.$el.jqxGrid('height', gridHeight());

		if (locationTree) {
			locationTree.jqxTree('height', locationTreeHeight());
		}
	});

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

		const gridContent = document.querySelector('#organizationsGrid .jqx-grid-content');

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
			document.dispatchEvent(new CustomEvent('organizationRowSelected', {
				detail: { id: id },
			}));
		}
	});

	if (detailEnabled) {
		grid.$el.on('rowdoubleclick', function (event) {
			const target = event.originalEvent && event.originalEvent.target;

			if (target && target.closest && target.closest('.entity-row-detail, .entity-row-action')) {
				return;
			}

			const rowIndex = event.args && event.args.rowindex;
			const row = rowIndex == null ? null : grid.$el.jqxGrid('getrowdata', rowIndex);

			if (row && row.id != null) {
				document.dispatchEvent(new CustomEvent('organizationRowSelected', {
					detail: { id: row.id },
				}));
			}
		});
	}

	grid.$el.on('click', '.entity-row-action', function (event) {
		event.preventDefault();
		event.stopPropagation();
		const id = this.getAttribute('data-id');
		const name = this.getAttribute('data-name') || '';

		if (rowAction.type === 'restore') {
			restoreOrganization(id, name);
			return;
		}

		deleteOrganization(id, name);
	});

	document.addEventListener('organizationsListChanged', function () {
		grid.reload();
	});

	function deleteOrganization(organizationId) {
		const previewUrl = (config.detail.deletePreviewUrl || '').replace(/\/$/, '');
		const deleteUrl = (config.detail.deleteUrl || rowAction.url || '').replace(/\/$/, '');

		if (!organizationId || !deleteUrl) {
			return;
		}

		fetchApi(previewUrl + '/' + organizationId).then(function (preview) {
			const count = preview.data ? Number(preview.data.product_count) || 0 : 0;
			const message = count > 0
				? t('org_delete_with_products', { count: count })
				: t('org_delete_confirm');

			return showConfirm({
				title: t('common_delete'),
				message: message,
				confirmLabel: t('common_delete'),
			});
		}).then(function () {
			return fetchApi(deleteUrl + '/' + organizationId, { method: 'POST' });
		}).then(function (result) {
			if (!result) {
				return;
			}

			showToast(result.message || t('org_deleted'), 'success');
			grid.reload();
		}).catch(function (error) {
			if (isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('org_delete_failed'), 'error');
		});
	}

	function restoreOrganization(organizationId) {
		const restoreUrl = (config.detail.restoreUrl || rowAction.url || '').replace(/\/$/, '');

		if (!organizationId || !restoreUrl) {
			return;
		}

		showConfirm({
			title: t('common_restore'),
			message: t('org_restore_confirm'),
			confirmLabel: t('common_restore'),
			confirmClass: 'btn btn-success',
		}).then(function () {
			return fetchApi(restoreUrl + '/' + organizationId, { method: 'POST' });
		}).then(function (result) {
			showToast(result.message || t('org_restored'), 'success');
			grid.reload();
		}).catch(function (error) {
			if (isConfirmCancelled(error)) {
				return;
			}

			showToast(error.message || t('org_restore_failed'), 'error');
		});
	}

	function applySearch() {
		grid.gotoFirst();
		grid.reload();
	}

	if (expiryFilter) {
		expiryFilter.addEventListener('change', function () {
			currentExpiryStatus = expiryFilter.value || '';
			applySearch();
		});
	}

	document.addEventListener('organizationsListRefresh', function () {
		grid.reload();
	});
})();
