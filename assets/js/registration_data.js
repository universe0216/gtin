(function () {
	'use strict';

	if (typeof window.REGISTRATION_DATA_CONFIG === 'undefined') {
		return;
	}

	const config = window.REGISTRATION_DATA_CONFIG;
	const baseUrl = String(config.baseUrl || '').replace(/\/$/, '');
	const table = document.getElementById('registrationDataTable');
	const itemsBody = document.getElementById('registrationDataItemsBody');
	const uploadModalEl = document.getElementById('registrationDataUploadModal');
	const openUploadBtn = document.getElementById('registrationDataUploadBtn');
	const editors = Array.isArray(config.editors) ? config.editors : [];
	const currentAccountId = String(config.accountId || '');
	let activeRequest = null;
	let selectedId = null;

	function statusLabel(status) {
		const key = String(status || '').toLowerCase();

		if (key === 'approved' || key === 'accepted') {
			return t('reg_accepted');
		}

		if (key === 'rejected') {
			return t('reg_rejected');
		}

		if (key === 'pending' || key === '') {
			return t('reg_data_pending');
		}

		return status;
	}

	function renderItems(items) {
		if (!itemsBody) {
			return;
		}

		if (!items.length) {
			itemsBody.innerHTML = '<tr><td colspan="6" class="text-muted">' + escapeHtml(t('reg_data_items_empty')) + '</td></tr>';
			return;
		}

		itemsBody.innerHTML = items.map(function (item, index) {
			return '<tr>' +
				'<td>' + (index + 1) + '</td>' +
				'<td>' + escapeHtml(item.product_procedure_number || '') + '</td>' +
				'<td>' + escapeHtml(item.name || '') + '</td>' +
				'<td>' + escapeHtml(statusLabel(item.status)) + '</td>' +
				'<td>' + escapeHtml(item.barcode || '') + '</td>' +
				'<td>' + escapeHtml(item.message || '') + '</td>' +
			'</tr>';
		}).join('');
	}

	function selectRow(row) {
		if (!table) {
			return;
		}

		table.querySelectorAll('.registration-data-row.is-selected').forEach(function (selected) {
			selected.classList.remove('is-selected');
		});

		if (row) {
			row.classList.add('is-selected');
		}
	}

	function loadItems(registrationId) {
		if (!registrationId || !itemsBody) {
			return;
		}

		selectedId = String(registrationId);

		itemsBody.innerHTML = '<tr><td colspan="6" class="text-muted">' + escapeHtml(t('reg_data_select')) + '</td></tr>';

		activeRequest = window.createAbortableRequest
			? window.createAbortableRequest(activeRequest)
			: null;

		const options = activeRequest ? { signal: activeRequest.signal } : {};

		fetchApi(baseUrl + '/items/' + registrationId, options).then(function (result) {
			if (selectedId !== String(registrationId)) {
				return;
			}

			renderItems(result.items || []);
		}).catch(function (error) {
			if (error && error.name === 'AbortError') {
				return;
			}

			if (selectedId !== String(registrationId)) {
				return;
			}

			itemsBody.innerHTML = '<tr><td colspan="6" class="text-muted">' + escapeHtml(error.message || t('reg_not_found')) + '</td></tr>';
		});
	}

	function assignRegistration(select) {
		const registrationId = select.getAttribute('data-registration-id');
		const previousId = select.getAttribute('data-account-id');
		const accountId = select.value;

		if (!registrationId || !accountId || accountId === previousId) {
			return;
		}

		select.disabled = true;

		const formData = new FormData();
		formData.append('account_id', accountId);

		fetchApi(baseUrl + '/assign/' + registrationId, {
			method: 'POST',
			body: formData,
		}).then(function (result) {
			const assignedId = result.data && result.data.account_id ? String(result.data.account_id) : accountId;
			select.setAttribute('data-account-id', assignedId);
			select.value = assignedId;
			showToast(result.message || t('reg_send_success'), 'success');
		}).catch(function (error) {
			select.value = previousId || '';
			showAlert({
				message: error.message || t('reg_send_failed'),
				variant: 'danger',
			});
		}).finally(function () {
			select.disabled = false;
		});
	}

	function editorLabel(editor) {
		const name = String(editor.full_name || '').trim();

		return name || String(editor.username || '');
	}

	function userOptions(accountId) {
		const selected = String(accountId || '');
		let html = '';
		let found = false;

		editors.forEach(function (editor) {
			const id = String(editor.id);

			if (id === selected) {
				found = true;
			}

			html += '<option value="' + escapeAttr(id) + '"' + (id === selected ? ' selected' : '') + '>' +
				escapeHtml(editorLabel(editor)) +
			'</option>';
		});

		if (!found && selected) {
			html = '<option value="' + escapeAttr(selected) + '" selected>' +
				escapeHtml(config.accountName || selected) +
			'</option>' + html;
		}

		return html;
	}

	function reindexRows() {
		if (!table) {
			return;
		}

		table.querySelectorAll('.registration-data-row .registration-data-index').forEach(function (cell, index) {
			cell.textContent = String(index + 1);
		});
	}

	function clearItemsPanel() {
		if (!itemsBody) {
			return;
		}

		itemsBody.innerHTML = '<tr><td colspan="6" class="text-muted">' + escapeHtml(t('reg_data_select')) + '</td></tr>';
	}

	function deleteRegistration(button) {
		const registrationId = button.getAttribute('data-registration-id');
		const fileName = button.getAttribute('data-file-name') || '';

		if (!registrationId) {
			return;
		}

		showConfirm({
			title: fileName || t('common_delete'),
			message: t('reg_data_delete_confirm'),
			confirmLabel: t('common_delete'),
		}).then(function () {
			button.disabled = true;

			return fetchApi(baseUrl + '/delete/' + registrationId, {
				method: 'POST',
			}).then(function (result) {
				const row = table ? table.querySelector('.registration-data-row[data-registration-id="' + registrationId + '"]') : null;
				const wasSelected = selectedId === String(registrationId);

				if (row) {
					row.remove();
				}

				reindexRows();

				const tbody = table ? table.querySelector('tbody') : null;
				const remaining = tbody ? tbody.querySelectorAll('.registration-data-row') : [];

				if (!remaining.length && tbody) {
					tbody.innerHTML = '<tr><td colspan="8" class="text-muted">' + escapeHtml(t('reg_data_empty')) + '</td></tr>';
				}

				if (wasSelected) {
					selectedId = null;
					clearItemsPanel();
				}

				showToast(result.message || t('reg_data_deleted'), 'success');
			});
		}).catch(function (error) {
			if (window.isConfirmCancelled && window.isConfirmCancelled(error)) {
				return;
			}

			showAlert({
				message: error.message || t('reg_delete_save_failed'),
				variant: 'danger',
			});
		}).finally(function () {
			button.disabled = false;
		});
	}

	function insertUploadedRegistration(tab) {
		const tbody = table ? table.querySelector('tbody') : null;

		if (!tbody || !tab || !tab.product_registration_id) {
			return;
		}

		const existing = tbody.querySelector('[data-registration-id="' + tab.product_registration_id + '"]');

		if (existing) {
			return;
		}

		const emptyRow = tbody.querySelector('tr:not([data-registration-id])');

		if (emptyRow) {
			emptyRow.remove();
		}

		const id = tab.product_registration_id;
		const fileName = tab.file_name || '';

		tbody.insertAdjacentHTML('afterbegin',
			'<tr class="registration-data-row" data-registration-id="' + id + '">' +
				'<td class="registration-data-index">1</td>' +
				'<td>' + escapeHtml(fileName) + '</td>' +
				'<td>' + escapeHtml(tab.procedure_number || '') + '</td>' +
				'<td>' + escapeHtml(tab.organization_name || '') + '</td>' +
				'<td>' + escapeHtml(tab.status || 'uploaded') + '</td>' +
				'<td class="text-end">' + escapeHtml(tab.total_products || (tab.rows || []).length || 0) + '</td>' +
				'<td>' +
					'<select class="form-select form-select-sm registration-data-user"' +
						' data-registration-id="' + id + '"' +
						' data-account-id="' + escapeAttr(currentAccountId) + '"' +
						' aria-label="' + escapeAttr(t('reg_data_user')) + '">' +
						userOptions(currentAccountId) +
					'</select>' +
				'</td>' +
				'<td class="text-end">' +
					'<button type="button" class="btn btn-sm btn-outline-danger registration-data-delete"' +
						' data-registration-id="' + id + '"' +
						' data-file-name="' + escapeAttr(fileName) + '"' +
						' title="' + escapeAttr(t('common_delete')) + '"' +
						' aria-label="' + escapeAttr(t('common_delete')) + '">' +
						'<i class="fas fa-trash"></i>' +
					'</button>' +
				'</td>' +
			'</tr>'
		);

		reindexRows();
	}

	function addUploadedRegistrations(tabs) {
		const uploaded = tabs || [];

		uploaded.slice().reverse().forEach(insertUploadedRegistration);

		const first = uploaded[0];

		if (!first || !table) {
			return;
		}

		const row = table.querySelector('[data-registration-id="' + first.product_registration_id + '"]');
		selectRow(row);
		loadItems(first.product_registration_id);
	}

	if (typeof ProcedureUpload !== 'undefined' && uploadModalEl) {
		ProcedureUpload.create({
			forms: uploadModalEl.querySelectorAll('.procedure-upload-form'),
			uploadModalEl: uploadModalEl,
			openUploadBtn: openUploadBtn,
			uploadUrl: config.uploadUrl,
			fileItemStyle: 'span',
			clearInputAfterSelect: true,
			uploadErrorMessage: t('reg_upload_error'),
			hints: {
				empty: t('reg_select_files'),
				selected: function (count) {
					return t('reg_files_ready', { count: count });
				},
				uploading: t('reg_uploading'),
			},
			normalizeFiles: function (fileList) {
				const files = Array.from(fileList || []).filter(function (file) {
					return /\.(zip|xls|xlsx)$/i.test(file.name);
				});

				if (!files.length && fileList && fileList.length) {
					showToast(t('reg_files_only'), 'error');
					return null;
				}

				return files;
			},
			onSuccess: function (result) {
				addUploadedRegistrations(result.tabs || []);
			},
		}).init();
	}

	if (table) {
		table.addEventListener('click', function (event) {
			const deleteBtn = event.target.closest('.registration-data-delete');

			if (deleteBtn) {
				event.preventDefault();
				event.stopPropagation();
				deleteRegistration(deleteBtn);
				return;
			}

			if (event.target.closest('.registration-data-user')) {
				return;
			}

			const row = event.target.closest('.registration-data-row');

			if (!row) {
				return;
			}

			selectRow(row);
			loadItems(row.getAttribute('data-registration-id'));
		});

		table.addEventListener('change', function (event) {
			const select = event.target.closest('.registration-data-user');

			if (!select) {
				return;
			}

			assignRegistration(select);
		});
	}
})();
