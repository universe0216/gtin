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

	const searchForm = document.getElementById('organizationsSearchForm');
	const searchInput = document.getElementById('organizationsSearchInput');
	const clearBtn = document.getElementById('organizationsClearBtn');
	const locationClearBtn = document.getElementById('organizationLocationFilterClear');
	const toolbarMeta = document.getElementById('organizationsToolbarMeta');
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

	let currentSearch = '';
	let currentLocationId = '';

	function hasActiveFilter() {
		return currentSearch !== '' || currentLocationId !== '';
	}

	function syncLocationClear() {
		if (!locationClearBtn) {
			return;
		}

		locationClearBtn.classList.toggle('d-none', currentLocationId === '');
	}

	function updateMeta(data) {
		if (!toolbarMeta) {
			return;
		}

		if (data && (data.total || 0) > 0) {
			toolbarMeta.textContent = data.range_start + '–' + data.range_end + ' of ' + data.total;
			toolbarMeta.classList.remove('d-none');
		} else {
			toolbarMeta.textContent = hasActiveFilter() ? config.emptySearchMessage : '';
			toolbarMeta.classList.toggle('d-none', !hasActiveFilter());
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
		},
	].concat(columns.map(function (column) {
		const gridColumn = {
			text: column.label,
			datafield: column.key,
			width: columnWidths[column.key] || 160,
		};

		if (column.key === 'currency') {
			gridColumn.cellsrenderer = currencyRenderer;
			gridColumn.cellclassname = function (row, columnfield, value) {
				const code = String(value == null ? '' : value);

				if (code === '1') {
					return 'organization-currency-foreign';
				}

				if (code === '2') {
					return 'organization-currency-domestic';
				}

				return '';
			};
		}

		return gridColumn;
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

	if (detailEnabled) {
		grid.$el.addClass('entity-list-grid-clickable');
		grid.$el.on('mousedown', '.entity-row-action', function (event) {
			event.stopPropagation();
		});

		grid.$el.on('rowdoubleclick', function (event) {
			const args = event.args || {};
			const original = args.originalEvent;
			const target = original && original.target
				? (original.target.nodeType === 3 ? original.target.parentElement : original.target)
				: null;

			if (target && target.closest && target.closest('.entity-row-action')) {
				return;
			}

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
