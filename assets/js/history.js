(function () {
	'use strict';

	if (typeof window.HISTORY_CONFIG === 'undefined') {
		return;
	}

	const config = window.HISTORY_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');
	const historyType = config.type || 'products';

	let modal;
	let activeRequest = null;
	let modalGrid = null;

	const procedureModalEl = document.getElementById('historyProcedureModal');
	const procedureModalTitle = document.getElementById('historyProcedureModalTitle');
	const procedureModalMeta = document.getElementById('historyProcedureModalMeta');
	const procedureModalTableWrap = document.getElementById('historyProcedureModalTableWrap');
	const procedureModalLoading = document.getElementById('historyProcedureModalLoading');
	const procedureModalFooterMeta = document.getElementById('historyProcedureModalFooterMeta');

	const organizationModalEl = document.getElementById('historyOrganizationModal');
	const organizationModalTitle = document.getElementById('historyOrganizationModalTitle');
	const organizationModalMeta = document.getElementById('historyOrganizationModalMeta');
	const organizationModalTableWrap = document.getElementById('historyOrganizationModalTableWrap');
	const organizationModalLoading = document.getElementById('historyOrganizationModalLoading');
	const organizationModalFooterMeta = document.getElementById('historyOrganizationModalFooterMeta');

	function getModalElements() {
		if (historyType === 'organizations') {
			return {
				modalEl: organizationModalEl,
				titleEl: organizationModalTitle,
				metaEl: organizationModalMeta,
				tableWrapEl: organizationModalTableWrap,
				loadingEl: organizationModalLoading,
				footerMetaEl: organizationModalFooterMeta,
			};
		}

		return {
			modalEl: procedureModalEl,
			titleEl: procedureModalTitle,
			metaEl: procedureModalMeta,
			tableWrapEl: procedureModalTableWrap,
			loadingEl: procedureModalLoading,
			footerMetaEl: procedureModalFooterMeta,
		};
	}

	// ---------------------------------------------------------------- List grid

	function fileRenderer(row, columnfield, value) {
		return JqxGrid.htmlCellRenderer(
			'<i class="fas fa-file-zipper me-1 text-primary"></i>' + JqxGrid.escapeAttr(value)
		);
	}

	function buildListColumns() {
		if (historyType === 'organizations') {
			return {
				datafields: [
					{ name: 'id', type: 'number' },
					{ name: 'file_name', type: 'string' },
					{ name: 'procedure_number', type: 'string' },
					{ name: 'organization_name', type: 'string' },
					{ name: 'processor_name', type: 'string' },
					{ name: 'total_items', type: 'number' },
					{ name: 'status', type: 'string' },
					{ name: 'created_at', type: 'string' },
				],
				columns: [
					{ text: '#', datafield: '__rownum', sortable: false, filterable: false, width: 60, cellsrenderer: JqxGrid.rowNumberRenderer },
					{ text: 'File', datafield: 'file_name', width: 240, cellsrenderer: fileRenderer },
					{ text: 'Procedure #', datafield: 'procedure_number', width: 130 },
					{ text: 'Organization', datafield: 'organization_name' },
					{ text: 'Processor', datafield: 'processor_name', width: 160 },
					{ text: 'Items', datafield: 'total_items', width: 90, cellsalign: 'right' },
					{ text: 'Status', datafield: 'status', width: 120 },
					{ text: 'Uploaded', datafield: 'created_at', width: 170 },
				],
			};
		}

		return {
			datafields: [
				{ name: 'id', type: 'number' },
				{ name: 'file_name', type: 'string' },
				{ name: 'procedure_number', type: 'string' },
				{ name: 'organization_name', type: 'string' },
				{ name: 'processor_name', type: 'string' },
				{ name: 'total_products', type: 'number' },
				{ name: 'approved', type: 'number' },
				{ name: 'rejected', type: 'number' },
				{ name: 'created_at', type: 'string' },
			],
			columns: [
				{ text: '#', datafield: '__rownum', sortable: false, filterable: false, width: 60, cellsrenderer: JqxGrid.rowNumberRenderer },
				{ text: 'File', datafield: 'file_name', width: 240, cellsrenderer: fileRenderer },
				{ text: 'Procedure #', datafield: 'procedure_number', width: 130 },
				{ text: 'Organization', datafield: 'organization_name' },
				{ text: 'Processor', datafield: 'processor_name', width: 160 },
				{ text: 'Products', datafield: 'total_products', width: 100, cellsalign: 'right' },
				{ text: 'Approved', datafield: 'approved', width: 100, cellsalign: 'right' },
				{ text: 'Rejected', datafield: 'rejected', width: 100, cellsalign: 'right' },
				{ text: 'Completed', datafield: 'created_at', width: 170 },
			],
		};
	}

	function initListGrid() {
		const selector = config.gridSelector;

		if (!selector || !document.querySelector(selector)) {
			return;
		}

		const setup = buildListColumns();

		const grid = JqxGrid.local(selector, {
			id: 'id',
			data: config.rows || [],
			datafields: setup.datafields,
			columns: setup.columns,
			pageable: true,
			pagesize: 15,
			pagesizeoptions: ['15', '30', '50', '100'],
			selectionmode: 'singlerow',
			emptyMessage: historyType === 'organizations'
				? 'No organization registrations found.'
				: 'No completed registrations found.',
		});

		if (grid) {
			grid.$el.on('rowclick', function (event) {
				const args = event.args;
				const rowdata = args.row && args.row.bounddata
					? args.row.bounddata
					: grid.$el.jqxGrid('getrowdata', args.rowindex);

				if (rowdata && rowdata.id) {
					openHistoryModal(rowdata.id);
				}
			});
		}
	}

	// --------------------------------------------------------------- Modal grid

	function destroyModalGrid() {
		if (modalGrid) {
			try {
				modalGrid.destroy();
			} catch (error) {
				/* element may already be detached */
			}
			modalGrid = null;
		}
	}

	function renderModalGrid(wrapEl, columns, rows, extraColumns) {
		destroyModalGrid();

		wrapEl.innerHTML = '<div class="history-modal-grid"></div>';
		const inner = wrapEl.firstElementChild;

		const datafields = [];
		const gridColumns = [
			{ text: '#', datafield: '__rownum', sortable: false, filterable: false, width: 60, cellsrenderer: JqxGrid.rowNumberRenderer },
		];

		(columns || []).forEach(function (column, index) {
			const field = 'c' + index;
			datafields.push({ name: field, type: 'string' });
			gridColumns.push({ text: column, datafield: field });
		});

		(extraColumns || []).forEach(function (extra) {
			datafields.push({ name: extra.key, type: 'string' });
			gridColumns.push({ text: extra.text, datafield: extra.key, width: extra.width });
		});

		const data = (rows || []).map(function (row) {
			const obj = {};

			(row.cells || []).forEach(function (cell, index) {
				obj['c' + index] = cell;
			});

			(extraColumns || []).forEach(function (extra) {
				obj[extra.key] = row[extra.key] ?? '';
			});

			return obj;
		});

		modalGrid = JqxGrid.local(inner, {
			data: data,
			datafields: datafields,
			columns: gridColumns,
			height: Math.max(320, window.innerHeight - 260),
			emptyMessage: historyType === 'organizations'
				? 'No registration items found.'
				: 'No procedure items found.',
		});
	}

	function initModal() {
		const elements = getModalElements();

		if (typeof mdb === 'undefined' || !elements.modalEl) {
			return;
		}

		modal = mdb.Modal.getOrCreateInstance(elements.modalEl);
		elements.modalEl.addEventListener('hidden.mdb.modal', resetModal);
	}

	function resetModal() {
		const elements = getModalElements();

		if (activeRequest) {
			activeRequest.abort();
			activeRequest = null;
		}

		destroyModalGrid();

		if (elements.titleEl) {
			elements.titleEl.textContent = '—';
		}
		if (elements.metaEl) {
			elements.metaEl.innerHTML = '';
		}
		if (elements.tableWrapEl) {
			elements.tableWrapEl.innerHTML = '';
		}
		if (elements.footerMetaEl) {
			elements.footerMetaEl.textContent = '';
		}
		if (elements.loadingEl) {
			elements.loadingEl.classList.add('d-none');
		}
	}

	function setLoading(isLoading) {
		const elements = getModalElements();

		if (elements.loadingEl) {
			elements.loadingEl.classList.toggle('d-none', !isLoading);
		}
		if (elements.tableWrapEl) {
			elements.tableWrapEl.classList.toggle('d-none', isLoading);
		}
	}

	function renderProcedureMeta(tab, elements) {
		elements.titleEl.textContent = tab.file_name || '—';
		elements.metaEl.innerHTML =
			'<span><strong>Procedure #:</strong> ' + escapeHtml(tab.procedure_number) + '</span>' +
			'<span><strong>Organization:</strong> ' + escapeHtml(tab.organization_name) + '</span>' +
			'<span><strong>Processor:</strong> ' + escapeHtml(tab.processor_name) + '</span>' +
			'<span><strong>Status:</strong> ' + escapeHtml(tab.status) + '</span>' +
			'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at) + '</span>' +
			'<span><strong>Products:</strong> ' + escapeHtml(tab.total_products) + '</span>';
		elements.footerMetaEl.textContent = (tab.rows || []).length + ' item(s)';
	}

	function renderOrganizationMeta(tab, elements) {
		elements.titleEl.textContent = tab.file_name || '—';
		elements.metaEl.innerHTML =
			'<span><strong>Procedure #:</strong> ' + escapeHtml(tab.procedure_number) + '</span>' +
			'<span><strong>Organization:</strong> ' + escapeHtml(tab.organization_name) + '</span>' +
			'<span><strong>Processor:</strong> ' + escapeHtml(tab.processor_name) + '</span>' +
			'<span><strong>Status:</strong> ' + escapeHtml(tab.status) + '</span>' +
			'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at) + '</span>' +
			'<span><strong>Items:</strong> ' + escapeHtml(tab.total_items) + '</span>';
		elements.footerMetaEl.textContent = (tab.rows || []).length + ' item(s)';
	}

	function renderProcedureTable(tab, elements) {
		renderModalGrid(elements.tableWrapEl, tab.columns || [], tab.rows || [], [
			{ text: 'Status', key: 'status', width: 120 },
			{ text: 'Message', key: 'message', width: 220 },
			{ text: 'Barcode', key: 'barcode', width: 160 },
		]);
	}

	function renderOrganizationTable(tab, elements) {
		renderModalGrid(elements.tableWrapEl, tab.columns || [], tab.rows || [], []);
	}

	function openHistoryModal(recordId) {
		const elements = getModalElements();

		if (!modal || !recordId) {
			return;
		}

		resetModal();
		modal.show();
		setLoading(true);

		activeRequest = createAbortableRequest(activeRequest);

		fetchApi(baseUrl + '/' + recordId, {
			headers: { Accept: 'application/json' },
			signal: activeRequest.signal,
		})
			.then(function (result) {
				activeRequest = null;

				const tab = result.tab;

				if (historyType === 'organizations') {
					renderOrganizationMeta(tab, elements);
					renderOrganizationTable(tab, elements);
				} else {
					renderProcedureMeta(tab, elements);
					renderProcedureTable(tab, elements);
				}

				setLoading(false);
			})
			.catch(function (error) {
				if (isAbortError(error)) {
					return;
				}

				activeRequest = null;
				modal.hide();
				showToast(error.message || 'Failed to load history items.', 'error');
			});
	}

	initModal();
	initListGrid();
})();
