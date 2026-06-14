(function () {
	'use strict';

	if (typeof window.ADMIN_CONFIG === 'undefined') {
		return;
	}

	const config = window.ADMIN_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const modalEl = document.getElementById('accountModal');
	const formEl = document.getElementById('accountForm');
	const alertEl = document.getElementById('accountModalAlert');
	const saveBtn = document.getElementById('accountSaveBtn');
	const modalTitle = document.getElementById('accountModalLabel');
	const recordIdInput = document.getElementById('record_id');
	const addBtn = document.getElementById('btnAddAccount');
	const isAdminCheck = document.getElementById('is_admin');
	const permissionsPanel = document.getElementById('permissionsPanel');
	const passwordInput = document.getElementById('account_password');
	const passwordHint = document.getElementById('passwordHint');

	let accountModal;
	let grid = null;
	let accounts = (config.accounts || []).map(normalizeAccount);

	function normalizeAccount(account) {
		return {
			id: parseInt(account.id, 10),
			username: account.username || '',
			full_name: account.full_name || '',
			is_admin: parseInt(account.is_admin, 10) ? 1 : 0,
			is_active: parseInt(account.is_active, 10) ? 1 : 0,
		};
	}

	function roleRenderer(row, columnfield, value) {
		const badge = Number(value) === 1
			? '<span class="badge bg-primary">Admin</span>'
			: '<span class="badge bg-secondary">User</span>';
		return JqxGrid.htmlCellRenderer(badge);
	}

	function statusRenderer(row, columnfield, value) {
		const label = Number(value) === 1
			? '<span class="text-success">Active</span>'
			: '<span class="text-muted">Inactive</span>';
		return JqxGrid.htmlCellRenderer(label);
	}

	function actionsRenderer(row, columnfield, value, defaultHtml, props, rowdata) {
		return JqxGrid.actionButtons([
			{ cls: 'btn-outline-primary btn-edit', icon: 'fa-pen', title: 'Edit' },
			{
				cls: 'btn-outline-danger btn-delete',
				icon: 'fa-trash',
				title: 'Delete',
				hidden: function (record) {
					return parseInt(record.id, 10) === config.currentUserId;
				},
			},
		], rowdata);
	}

	function initGrid() {
		grid = JqxGrid.local('#adminGrid', {
			id: 'id',
			data: accounts,
			emptyMessage: 'No users found.',
			columns: [
				{ text: '#', datafield: 'id', width: 70, cellsalign: 'left' },
				{ text: 'Username', datafield: 'username', width: 200 },
				{ text: 'Full Name', datafield: 'full_name' },
				{ text: 'Role', datafield: 'is_admin', width: 120, cellsrenderer: roleRenderer },
				{ text: 'Status', datafield: 'is_active', width: 120, cellsrenderer: statusRenderer },
				{
					text: 'Actions', datafield: '__actions', width: 130, sortable: false,
					filterable: false, editable: false, align: 'right', cellsalign: 'right',
					cellsrenderer: actionsRenderer,
				},
			],
		});

		if (grid) {
			JqxGrid.bindCellButtons(grid.$el, '.btn-edit', function (id) {
				openEditModal(id);
			});
			JqxGrid.bindCellButtons(grid.$el, '.btn-delete', function (id) {
				confirmDeleteAccount(id);
			});
		}
	}

	function upsertRow(account) {
		const record = normalizeAccount(account);
		const index = accounts.findIndex(function (item) {
			return Number(item.id) === Number(record.id);
		});

		if (index >= 0) {
			accounts[index] = record;
		} else {
			accounts.unshift(record);
		}

		if (grid) {
			grid.setData(accounts);
		}
	}

	function removeRow(id) {
		accounts = accounts.filter(function (item) {
			return Number(item.id) !== Number(id);
		});

		if (grid) {
			grid.setData(accounts);
		}
	}

	function initModals() {
		if (typeof mdb !== 'undefined') {
			accountModal = mdb.Modal.getOrCreateInstance(modalEl);
			modalEl.addEventListener('shown.mdb.modal', syncFormOutlines);
		}
	}

	function syncFormOutlines() {
		if (typeof mdb === 'undefined' || !mdb.Input) {
			return;
		}

		formEl.querySelectorAll('.form-outline').forEach(function (wrapper) {
			mdb.Input.getOrCreateInstance(wrapper).update();
		});
	}

	function togglePermissionsPanel() {
		const disabled = isAdminCheck.checked;
		permissionsPanel.querySelectorAll('.permission-check').forEach(function (input) {
			input.disabled = disabled;
			if (disabled) {
				input.checked = false;
			}
		});
		permissionsPanel.style.opacity = disabled ? '0.5' : '1';
	}

	function hideAlert() {
		alertEl.classList.add('d-none');
		alertEl.textContent = '';
	}

	function showAlert(message) {
		alertEl.textContent = message;
		alertEl.classList.remove('d-none');
	}

	function resetForm() {
		formEl.reset();
		recordIdInput.value = '';
		document.getElementById('is_active').checked = true;
		passwordInput.required = true;
		passwordHint.textContent = 'Minimum 6 characters.';
		hideAlert();
		togglePermissionsPanel();
		syncFormOutlines();
	}

	function fillForm(data) {
		document.getElementById('username').value = data.username ?? '';
		document.getElementById('full_name').value = data.full_name ?? '';
		passwordInput.value = '';
		passwordInput.required = false;
		passwordHint.textContent = 'Leave blank to keep current password.';
		document.getElementById('is_active').checked = !!data.is_active;
		isAdminCheck.checked = !!data.is_admin;

		formEl.querySelectorAll('.permission-check').forEach(function (input) {
			input.checked = Array.isArray(data.permissions) && data.permissions.includes(input.value);
		});

		togglePermissionsPanel();
		syncFormOutlines();
	}

	function openCreateModal() {
		resetForm();
		modalTitle.textContent = 'Add User';
		accountModal.show();
	}

	function openEditModal(id) {
		resetForm();
		modalTitle.textContent = 'Edit User';
		recordIdInput.value = id;

		fetchApi(baseUrl + '/get/' + id)
			.then(function (result) {
				fillForm(result.data);
				accountModal.show();
			})
			.catch(function (error) {
				showToast(error.message || 'Failed to load user.', 'error');
			});
	}

	function submitForm(event) {
		event.preventDefault();
		hideAlert();
		saveBtn.disabled = true;

		const id = recordIdInput.value;
		const url = id ? baseUrl + '/update/' + id : baseUrl + '/create';
		const formData = new FormData(formEl);

		if (!formData.get('is_active')) {
			formData.append('is_active', '0');
		}

		if (!formData.get('is_admin')) {
			formData.append('is_admin', '0');
		}

		fetchApi(url, {
			method: 'POST',
			body: formData,
		})
			.then(function (result) {
				upsertRow(result.data);
				accountModal.hide();
				showToast(result.message, 'success');
			})
			.catch(function (error) {
				showAlert(error.message || 'An unexpected error occurred.');
			})
			.finally(function () { saveBtn.disabled = false; });
	}

	function confirmDeleteAccount(id) {
		showConfirm({
			title: 'Confirm Delete',
			message: 'Are you sure you want to delete this user?',
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

				showToast(error.message || 'Failed to delete user.', 'error');
			});
	}

	addBtn.addEventListener('click', openCreateModal);
	formEl.addEventListener('submit', submitForm);
	isAdminCheck.addEventListener('change', togglePermissionsPanel);

	initGrid();
	document.addEventListener('DOMContentLoaded', initModals);
	initModals();
})();
