(function () {
	'use strict';

	if (typeof window.CRUD_CONFIG === 'undefined') {
		return;
	}

	const config = window.CRUD_CONFIG;

	if (!config.canEdit) {
		return;
	}

	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const modalEl = document.getElementById('basicModal');
	const formEl = document.getElementById('basicModalForm');
	const alertEl = document.getElementById('basicModalAlert');
	const saveBtn = document.getElementById('basicModalSaveBtn');
	const modalTitle = document.getElementById('basicModalLabel');
	const recordIdInput = document.getElementById('record_id');
	const tableBody = document.querySelector('#crudTable tbody');
	const addBtn = document.getElementById('btnAddRecord');

	let basicModal;

	function initModals() {
		if (typeof mdb !== 'undefined') {
			basicModal = mdb.Modal.getOrCreateInstance(modalEl);
			modalEl.addEventListener('shown.mdb.modal', syncFormOutlines);
		}
	}

	function setLoading(isLoading) {
		const spinner = saveBtn.querySelector('.spinner-border');
		saveBtn.disabled = isLoading;

		if (isLoading) {
			spinner.classList.remove('d-none');
		} else {
			spinner.classList.add('d-none');
		}
	}

	function hideAlert() {
		alertEl.classList.add('d-none');
		alertEl.textContent = '';
	}

	function showAlert(message) {
		alertEl.textContent = message;
		alertEl.classList.remove('d-none');
	}

	function syncFormOutlines() {
		if (typeof mdb === 'undefined' || !mdb.Input) {
			return;
		}

		formEl.querySelectorAll('.form-outline').forEach(function (wrapper) {
			const instance = mdb.Input.getOrCreateInstance(wrapper);
			instance.update();
		});
	}

	function resetForm() {
		formEl.reset();
		recordIdInput.value = '';
		hideAlert();
		syncFormOutlines();
	}

	function fillForm(data) {
		config.fields.forEach(function (field) {
			const input = formEl.querySelector('[name="' + field.name + '"]');
			if (input) {
				input.value = data[field.name] ?? '';
			}
		});
		syncFormOutlines();
	}

	function openCreateModal() {
		resetForm();
		modalTitle.textContent = 'Add ' + config.entityLabel;
		basicModal.show();
	}

	function openEditModal(id) {
		resetForm();
		modalTitle.textContent = 'Edit ' + config.entityLabel;
		recordIdInput.value = id;

		fetch(baseUrl + '/get/' + id, {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (response) { return response.json(); })
			.then(function (result) {
				if (!result.success) {
					showToast(result.message || 'Failed to load record.', 'error');
					return;
				}
				fillForm(result.data);
				basicModal.show();
			})
			.catch(function () {
				showToast('Failed to load record.', 'error');
			});
	}

	function buildRow(record) {
		const cells = config.fields.map(function (field) {
			return '<td>' + escapeHtml(record[field.name]) + '</td>';
		}).join('');

		return (
			'<tr data-id="' + record.id + '">' +
				'<td>' + record.id + '</td>' +
				cells +
				'<td class="text-end">' +
					'<button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-mdb-ripple-init>' +
						'<i class="fas fa-pen"></i>' +
					'</button>' +
					'<button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-mdb-ripple-init>' +
						'<i class="fas fa-trash"></i>' +
					'</button>' +
				'</td>' +
			'</tr>'
		);
	}

	function removeEmptyRow() {
		const emptyRow = document.getElementById('emptyRow');
		if (emptyRow) {
			emptyRow.remove();
		}
	}

	function upsertRow(record) {
		removeEmptyRow();
		const existing = tableBody.querySelector('tr[data-id="' + record.id + '"]');

		if (existing) {
			existing.outerHTML = buildRow(record);
		} else {
			tableBody.insertAdjacentHTML('afterbegin', buildRow(record));
		}
	}

	function removeRow(id) {
		const row = tableBody.querySelector('tr[data-id="' + id + '"]');
		if (row) {
			row.remove();
		}

		if (!tableBody.querySelector('tr')) {
			const colspan = config.fields.length + 2;
			tableBody.innerHTML =
				'<tr id="emptyRow">' +
					'<td colspan="' + colspan + '" class="text-center text-muted py-4">' +
						'No records found. Click "Add New" to create one.' +
					'</td>' +
				'</tr>';
		}
	}

	function submitForm(event) {
		event.preventDefault();
		hideAlert();
		setLoading(true);

		const id = recordIdInput.value;
		const url = id ? baseUrl + '/update/' + id : baseUrl + '/create';
		const formData = new FormData(formEl);

		fetch(url, {
			method: 'POST',
			body: formData,
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function (result) {
				if (!result.ok || !result.data.success) {
					showAlert(result.data.message || 'Request failed.');
					return;
				}

				upsertRow(result.data.data);
				basicModal.hide();
				showToast(result.data.message, 'success');
			})
			.catch(function () {
				showAlert('An unexpected error occurred.');
			})
			.finally(function () {
				setLoading(false);
			});
	}

	function confirmDeleteRecord(id) {
		showConfirm({
			title: 'Confirm Delete',
			message: 'Are you sure you want to delete this record?',
			confirmLabel: 'Delete',
			size: 'sm',
		})
			.then(function () {
				return fetchApi(baseUrl + '/delete/' + id, { method: 'POST' });
			})
			.then(function (result) {
				removeRow(id);
				showToast(result.message, 'success');
			})
			.catch(function (error) {
				if (isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || 'Failed to delete record.', 'error');
			});
	}

	if (addBtn) {
		addBtn.addEventListener('click', openCreateModal);
	}

	if (formEl) {
		formEl.addEventListener('submit', submitForm);
	}

	tableBody.addEventListener('click', function (event) {
		const editBtn = event.target.closest('.btn-edit');
		const deleteBtn = event.target.closest('.btn-delete');
		const row = event.target.closest('tr[data-id]');

		if (!row) {
			return;
		}

		const id = row.getAttribute('data-id');

		if (editBtn) {
			openEditModal(id);
		}

		if (deleteBtn) {
			confirmDeleteRecord(id);
		}
	});

	document.addEventListener('DOMContentLoaded', initModals);
	initModals();
})();
