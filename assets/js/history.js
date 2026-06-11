(function () {
	'use strict';

	if (typeof window.HISTORY_CONFIG === 'undefined') {
		return;
	}

	const config = window.HISTORY_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const modalEl = document.getElementById('historyProcedureModal');
	const modalTitle = document.getElementById('historyProcedureModalTitle');
	const modalMeta = document.getElementById('historyProcedureModalMeta');
	const modalTableWrap = document.getElementById('historyProcedureModalTableWrap');
	const modalLoading = document.getElementById('historyProcedureModalLoading');
	const modalFooterMeta = document.getElementById('historyProcedureModalFooterMeta');
	const toastContainer = document.getElementById('historyToastContainer');

	let modal;
	let activeRequest = null;

	function escapeHtml(value) {
		return String(value ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function showToast(message, type) {
		if (!toastContainer) {
			return;
		}

		const bgClass = type === 'error' ? 'bg-danger' : 'bg-success';
		const id = 'history-toast-' + Date.now();
		const html =
			'<div id="' + id + '" class="toast align-items-center text-white ' + bgClass + ' border-0" role="alert">' +
				'<div class="d-flex"><div class="toast-body">' + escapeHtml(message) + '</div>' +
				'<button type="button" class="btn-close btn-close-white me-2 m-auto" data-mdb-dismiss="toast"></button></div></div>';

		toastContainer.insertAdjacentHTML('beforeend', html);
		const toastEl = document.getElementById(id);

		if (typeof mdb !== 'undefined') {
			const toast = new mdb.Toast(toastEl, { delay: 4000 });
			toast.show();
			toastEl.addEventListener('hidden.mdb.toast', function () {
				toastEl.remove();
			});
		}
	}

	function initModal() {
		if (typeof mdb === 'undefined' || !modalEl) {
			return;
		}

		modal = mdb.Modal.getOrCreateInstance(modalEl);
		modalEl.addEventListener('hidden.mdb.modal', resetModal);
	}

	function resetModal() {
		if (activeRequest) {
			activeRequest.abort();
			activeRequest = null;
		}

		modalTitle.textContent = '—';
		modalMeta.innerHTML = '';
		modalTableWrap.innerHTML = '';
		modalFooterMeta.textContent = '';
		modalLoading.classList.add('d-none');
	}

	function setLoading(isLoading) {
		modalLoading.classList.toggle('d-none', !isLoading);
		modalTableWrap.classList.toggle('d-none', isLoading);
	}

	function renderMeta(tab) {
		modalTitle.textContent = tab.file_name || '—';
		modalMeta.innerHTML =
			'<span><strong>Procedure #:</strong> ' + escapeHtml(tab.procedure_number) + '</span>' +
			'<span><strong>Organization:</strong> ' + escapeHtml(tab.organization_name) + '</span>' +
			'<span><strong>Processor:</strong> ' + escapeHtml(tab.processor_name) + '</span>' +
			'<span><strong>Status:</strong> ' + escapeHtml(tab.status) + '</span>' +
			'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at) + '</span>' +
			'<span><strong>Products:</strong> ' + escapeHtml(tab.total_products) + '</span>';
		modalFooterMeta.textContent = (tab.rows || []).length + ' item(s)';
	}

	function renderTable(tab) {
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
		modalTableWrap.innerHTML = html;
	}

	function openProcedureModal(procedureId) {
		if (!modal || !procedureId) {
			return;
		}

		resetModal();
		modal.show();
		setLoading(true);

		if (activeRequest) {
			activeRequest.abort();
		}

		activeRequest = new AbortController();

		fetch(baseUrl + '/' + procedureId, {
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/json',
			},
			signal: activeRequest.signal,
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function (result) {
				activeRequest = null;

				if (!result.ok || !result.data.success) {
					throw new Error(result.data.message || 'Failed to load procedure items.');
				}

				renderMeta(result.data.tab);
				renderTable(result.data.tab);
				setLoading(false);
			})
			.catch(function (error) {
				if (error.name === 'AbortError') {
					return;
				}

				activeRequest = null;
				modal.hide();
				showToast(error.message || 'Failed to load procedure items.', 'error');
			});
	}

	function bindRows() {
		document.querySelectorAll('.history-procedure-row').forEach(function (row) {
			function openRow() {
				openProcedureModal(row.getAttribute('data-procedure-id'));
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
	bindRows();
})();
