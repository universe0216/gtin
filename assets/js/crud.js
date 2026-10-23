(function () {
	'use strict';

	if (typeof window.CRUD_CONFIG === 'undefined') {
		return;
	}

	const config = window.CRUD_CONFIG;
	const canEdit = !!config.canEdit;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	let grid = null;
	let records = (config.records || []).slice();

	function actionsRenderer(row, columnfield, value, defaultHtml, props, rowdata) {
		return JqxGrid.actionButtons([
			{ cls: 'btn-outline-primary btn-edit', icon: 'fa-pen', title: t('common_edit') },
			{ cls: 'btn-outline-danger btn-delete', icon: 'fa-trash', title: t('common_delete') },
		], rowdata);
	}

	function buildColumns() {
		const columns = [
			{ text: '#', datafield: 'id', width: 70, cellsalign: 'left' },
		];

		config.fields.forEach(function (field) {
			columns.push({ text: field.label, datafield: field.name });
		});

		if (canEdit) {
			columns.push({
				text: t('common_actions'), datafield: '__actions', width: 130, sortable: false,
				filterable: false, editable: false, align: 'right', cellsalign: 'right',
				cellsrenderer: actionsRenderer,
			});
		}

		return columns;
	}

	function buildDatafields() {
		const datafields = [{ name: 'id', type: 'number' }];

		config.fields.forEach(function (field) {
			datafields.push({ name: field.name, type: 'string' });
		});

		return datafields;
	}

	function initGrid() {
		grid = JqxGrid.local('#crudGrid', {
			id: 'id',
			data: records,
			datafields: buildDatafields(),
			columns: buildColumns(),
			emptyMessage: canEdit
				? t('common_no_records_add')
				: t('common_no_records'),
		});

		if (grid && canEdit) {
			JqxGrid.bindCellButtons(grid.$el, '.btn-edit', function (id) {
				openEditModal(id);
			});
			JqxGrid.bindCellButtons(grid.$el, '.btn-delete', function (id) {
				confirmDeleteRecord(id);
			});
		}
	}

	function upsertRow(record) {
		const index = records.findIndex(function (item) {
			return Number(item.id) === Number(record.id);
		});

		if (index >= 0) {
			records[index] = record;
		} else {
			records.unshift(record);
		}

		if (grid) {
			grid.setData(records);
		}
	}

	function removeRow(id) {
		records = records.filter(function (item) {
			return Number(item.id) !== Number(id);
		});

		if (grid) {
			grid.setData(records);
		}
	}

	// The grid renders for every list (read-only included); editing is gated below.
	initGrid();

	if (!canEdit) {
		return;
	}

	const modalEl = document.getElementById('basicModal');
	const formEl = document.getElementById('basicModalForm');
	const alertEl = document.getElementById('basicModalAlert');
	const saveBtn = document.getElementById('basicModalSaveBtn');
	const modalTitle = document.getElementById('basicModalLabel');
	const recordIdInput = document.getElementById('record_id');
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

		fetchApi(baseUrl + '/get/' + id)
			.then(function (result) {
				fillForm(result.data);
				basicModal.show();
			})
			.catch(function (error) {
				showToast(error.message || t('common_failed_load_record'), 'error');
			});
	}

	function buildListRecord(record) {
		const row = { id: parseInt(record.id, 10) };

		config.fields.forEach(function (field) {
			row[field.name] = record[field.name] ?? '';
		});

		return row;
	}

	function submitForm(event) {
		event.preventDefault();
		hideAlert();
		setLoading(true);

		const id = recordIdInput.value;
		const url = id ? baseUrl + '/update/' + id : baseUrl + '/create';
		const formData = new FormData(formEl);

		fetchApi(url, {
			method: 'POST',
			body: formData,
		})
			.then(function (result) {
				upsertRow(buildListRecord(result.data));
				basicModal.hide();
				showToast(result.message, 'success');
			})
			.catch(function (error) {
				showAlert(error.message || 'An unexpected error occurred.');
			})
			.finally(function () {
				setLoading(false);
			});
	}

	function confirmDeleteRecord(id) {
		showConfirm({
			title: t('common_confirm_delete_title'),
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

				showToast(error.message || t('common_failed_delete_record'), 'error');
			});
	}

	if (addBtn) {
		addBtn.addEventListener('click', openCreateModal);
	}

	if (formEl) {
		formEl.addEventListener('submit', submitForm);
	}

	document.addEventListener('DOMContentLoaded', initModals);
	initModals();
})();
