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
		const columns = tab.columns || [];
		const rows = tab.rows || [];
		const colSpan = columns.length + 4;
		let html =
			'<table class="table table-hover align-middle mb-0 procedure-data-table">' +
				'<thead><tr>' +
					'<th class="procedure-row-index">#</th>';

		columns.forEach(function (column) {
			html += '<th>' + escapeHtml(column) + '</th>';
		});

		html +=
					'<th>Status</th>' +
					'<th>Message</th>' +
					'<th>Barcode</th>' +
				'</tr></thead><tbody>';

		if (!rows.length) {
			html += '<tr><td colspan="' + colSpan + '" class="text-center text-muted py-4">No procedure items found.</td></tr>';
		} else {
			rows.forEach(function (row, index) {
				html += '<tr><td class="procedure-row-index text-muted">' + (index + 1) + '</td>';

				(row.cells || []).forEach(function (cell) {
					html += '<td>' + escapeHtml(cell) + '</td>';
				});

				html +=
					'<td>' + escapeHtml(row.status) + '</td>' +
					'<td>' + escapeHtml(row.message) + '</td>' +
					'<td>' + escapeHtml(row.barcode) + '</td>' +
				'</tr>';
			});
		}

		html += '</tbody></table>';
		elements.tableWrapEl.innerHTML = html;
	}

	function renderOrganizationTable(tab, elements) {
		const columns = tab.columns || [];
		const rows = tab.rows || [];
		const colSpan = columns.length + 1;
		let html =
			'<table class="table table-hover align-middle mb-0 procedure-data-table">' +
				'<thead><tr>' +
					'<th class="procedure-row-index">#</th>';

		columns.forEach(function (column) {
			html += '<th>' + escapeHtml(column) + '</th>';
		});

		html += '</tr></thead><tbody>';

		if (!rows.length) {
			html += '<tr><td colspan="' + colSpan + '" class="text-center text-muted py-4">No registration items found.</td></tr>';
		} else {
			rows.forEach(function (row, index) {
				html += '<tr><td class="procedure-row-index text-muted">' + (index + 1) + '</td>';

				(row.cells || []).forEach(function (cell) {
					html += '<td>' + escapeHtml(cell) + '</td>';
				});

				html += '</tr>';
			});
		}

		html += '</tbody></table>';
		elements.tableWrapEl.innerHTML = html;
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

	function bindRows(selector, attributeName) {
		document.querySelectorAll(selector).forEach(function (row) {
			function openRow() {
				openHistoryModal(row.getAttribute(attributeName));
			}

			row.addEventListener('click', openRow);
			row.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					openRow();
				}
			});
		});
	}

	initModal();

	if (historyType === 'organizations') {
		bindRows('.history-organization-row', 'data-organization-registration-id');
	} else {
		bindRows('.history-procedure-row', 'data-product-registration-id');
	}
})();
