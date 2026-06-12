(function () {
	'use strict';

	if (typeof window.ADMIN_CONFIG === 'undefined') {
		return;
	}

	const config = window.ADMIN_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const modalEl = document.getElementById('accountModal');
	const deleteModalEl = document.getElementById('deleteAccountModal');
	const formEl = document.getElementById('accountForm');
	const alertEl = document.getElementById('accountModalAlert');
	const saveBtn = document.getElementById('accountSaveBtn');
	const modalTitle = document.getElementById('accountModalLabel');
	const recordIdInput = document.getElementById('record_id');
	const tableBody = document.querySelector('#adminTable tbody');
	const addBtn = document.getElementById('btnAddAccount');
	const confirmDeleteBtn = document.getElementById('confirmDeleteAccountBtn');
	const isAdminCheck = document.getElementById('is_admin');
	const permissionsPanel = document.getElementById('permissionsPanel');
	const passwordInput = document.getElementById('account_password');
	const passwordHint = document.getElementById('passwordHint');

	let accountModal;
	let deleteModal;
	let pendingDeleteId = null;

	function initModals() {
		if (typeof mdb !== 'undefined') {
			accountModal = mdb.Modal.getOrCreateInstance(modalEl);
			deleteModal = mdb.Modal.getOrCreateInstance(deleteModalEl);
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

		fetch(baseUrl + '/get/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (response) { return response.json(); })
			.then(function (result) {
				if (!result.success) {
					showToast(result.message || 'Failed to load user.', 'error');
					return;
				}
				fillForm(result.data);
				accountModal.show();
			})
			.catch(function () { showToast('Failed to load user.', 'error'); });
	}

	function buildRow(account) {
		const roleBadge = account.is_admin
			? '<span class="badge bg-primary">Admin</span>'
			: '<span class="badge bg-secondary">User</span>';
		const status = account.is_active
			? '<span class="text-success">Active</span>'
			: '<span class="text-muted">Inactive</span>';
		const deleteBtn = parseInt(account.id, 10) === config.currentUserId
			? ''
			: '<button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-mdb-ripple-init><i class="fas fa-trash"></i></button>';

		return '<tr data-id="' + account.id + '">' +
			'<td>' + account.id + '</td>' +
			'<td>' + escapeHtml(account.username) + '</td>' +
			'<td>' + escapeHtml(account.full_name) + '</td>' +
			'<td>' + roleBadge + '</td>' +
			'<td>' + status + '</td>' +
			'<td class="text-end">' +
				'<button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-mdb-ripple-init><i class="fas fa-pen"></i></button>' +
				deleteBtn +
			'</td></tr>';
	}

	function upsertRow(account) {
		const existing = tableBody.querySelector('tr[data-id="' + account.id + '"]');
		const emptyRow = document.getElementById('emptyRow');
		if (emptyRow) {
			emptyRow.remove();
		}
		if (existing) {
			existing.outerHTML = buildRow(account);
		} else {
			tableBody.insertAdjacentHTML('afterbegin', buildRow(account));
		}
	}

	function removeRow(id) {
		const row = tableBody.querySelector('tr[data-id="' + id + '"]');
		if (row) {
			row.remove();
		}
		if (!tableBody.querySelector('tr')) {
			tableBody.innerHTML = '<tr id="emptyRow"><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>';
		}
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
				accountModal.hide();
				showToast(result.data.message, 'success');
			})
			.catch(function () { showAlert('An unexpected error occurred.'); })
			.finally(function () { saveBtn.disabled = false; });
	}

	function confirmDelete() {
		if (!pendingDeleteId) {
			return;
		}

		fetch(baseUrl + '/delete/' + pendingDeleteId, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (response) { return response.json(); })
			.then(function (result) {
				if (!result.success) {
					showToast(result.message || 'Failed to delete user.', 'error');
					return;
				}
				removeRow(pendingDeleteId);
				deleteModal.hide();
				showToast(result.message, 'success');
			})
			.catch(function () { showToast('Failed to delete user.', 'error'); })
			.finally(function () { pendingDeleteId = null; });
	}

	addBtn.addEventListener('click', openCreateModal);
	formEl.addEventListener('submit', submitForm);
	confirmDeleteBtn.addEventListener('click', confirmDelete);
	isAdminCheck.addEventListener('change', togglePermissionsPanel);

	tableBody.addEventListener('click', function (event) {
		const row = event.target.closest('tr[data-id]');
		if (!row) {
			return;
		}
		const id = row.getAttribute('data-id');
		if (event.target.closest('.btn-edit')) {
			openEditModal(id);
		}
		if (event.target.closest('.btn-delete')) {
			pendingDeleteId = id;
			deleteModal.show();
		}
	});

	document.addEventListener('DOMContentLoaded', initModals);
	initModals();
})();
