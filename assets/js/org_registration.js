(function () {
	'use strict';

	if (typeof window.ORG_REGISTRATION_CONFIG === 'undefined') {
		return;
	}

	const config = window.ORG_REGISTRATION_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const uploadForms = document.querySelectorAll('.org-registration-upload-form');
	const uploadModalEl = document.getElementById('orgRegistrationUploadModal');
	const openUploadBtn = document.getElementById('btnOpenUploadModal');
	const tabsWrapper = document.getElementById('orgRegistrationTabsWrapper');
	const tabsNav = document.getElementById('orgRegistrationTabs');
	const tabsContent = document.getElementById('orgRegistrationTabContent');
	const initialUpload = document.getElementById('orgRegistrationInitialUpload');
	const toastContainer = document.getElementById('toastContainer');
	const deleteModalEl = document.getElementById('orgRegistrationDeleteModal');
	const deleteModalTitle = document.getElementById('orgRegistrationDeleteModalLabel');
	const confirmDeleteBtn = document.getElementById('orgRegistrationConfirmDeleteBtn');

	let selectedFiles = [];
	let filePickerOpenUntil = 0;
	let pendingDeleteRegistrationId = null;
	let uploadModal;
	let deleteModal;

	function initUploadModal() {
		if (typeof mdb === 'undefined' || !uploadModalEl) {
			return;
		}

		uploadModal = mdb.Modal.getOrCreateInstance(uploadModalEl);
		uploadModalEl.addEventListener('hidden.mdb.modal', resetUploadForm);
	}

	function initDeleteModal() {
		if (typeof mdb === 'undefined' || !deleteModalEl) {
			return;
		}

		deleteModal = mdb.Modal.getOrCreateInstance(deleteModalEl);
	}

	function getUploadControls(form) {
		return {
			form: form,
			zone: form.querySelector('.procedure-upload-zone'),
			fileInput: form.querySelector('.procedure-upload-input'),
			selectedFilesList: form.querySelector('.procedure-selected-files'),
			uploadBtn: form.querySelector('.procedure-upload-submit'),
			uploadHint: form.querySelector('.procedure-upload-hint'),
		};
	}

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text ?? '';
		return div.innerHTML;
	}

	function escapeAttr(text) {
		return escapeHtml(text).replace(/"/g, '&quot;');
	}

	function showToast(message, type) {
		if (!toastContainer) {
			return;
		}

		const id = 'toast-' + Date.now();
		const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
		const html =
			'<div id="' + id + '" class="toast align-items-center text-white ' + bgClass + ' border-0" role="alert">' +
				'<div class="d-flex">' +
					'<div class="toast-body">' + escapeHtml(message) + '</div>' +
					'<button type="button" class="btn-close btn-close-white me-2 m-auto" data-mdb-dismiss="toast"></button>' +
				'</div>' +
			'</div>';

		toastContainer.insertAdjacentHTML('beforeend', html);
		const toastEl = document.getElementById(id);

		if (typeof mdb !== 'undefined') {
			const toast = new mdb.Toast(toastEl, { delay: 3500 });
			toast.show();
			toastEl.addEventListener('hidden.mdb.toast', function () {
				toastEl.remove();
			});
		}
	}

	function resetUploadForm() {
		selectedFiles = [];
		uploadForms.forEach(function (form) {
			const controls = getUploadControls(form);
			form.reset();
			if (controls.zone) {
				controls.zone.classList.remove('is-dragover');
			}
		});
		renderSelectedFiles();
	}

	function openUploadModal() {
		if (uploadModal) {
			uploadModal.show();
		}
	}

	function renderSelectedFiles() {
		uploadForms.forEach(function (form) {
			const controls = getUploadControls(form);

			if (!controls.selectedFilesList || !controls.uploadBtn) {
				return;
			}

			if (!selectedFiles.length) {
				controls.selectedFilesList.classList.add('d-none');
				controls.selectedFilesList.innerHTML = '';
				controls.uploadBtn.disabled = true;
				if (controls.uploadHint) {
					controls.uploadHint.textContent = 'Select one or more zip files to continue.';
				}
				return;
			}

			controls.selectedFilesList.classList.remove('d-none');
			controls.selectedFilesList.innerHTML = selectedFiles.map(function (file, index) {
				return '<div class="procedure-selected-file">' +
					'<span class="procedure-selected-file-icon"><i class="fas fa-file-zipper"></i></span>' +
					'<span class="procedure-selected-file-name">' + escapeHtml(file.name) + '</span>' +
					'<button type="button" class="procedure-selected-file-remove" data-index="' + index + '" aria-label="Remove file">&times;</button>' +
				'</div>';
			}).join('');
			controls.uploadBtn.disabled = false;
			if (controls.uploadHint) {
				controls.uploadHint.textContent = selectedFiles.length + ' file(s) selected.';
			}
		});
	}

	function setSelectedFiles(files) {
		selectedFiles = files;
		renderSelectedFiles();
	}

	function openFilePicker(fileInput) {
		if (!fileInput) {
			return;
		}

		const now = Date.now();

		if (now < filePickerOpenUntil) {
			return;
		}

		filePickerOpenUntil = now + 1000;
		fileInput.click();
	}

	function buildTableHtml(tab) {
		const headerHtml = '<th class="procedure-row-index">#</th>' +
			(tab.columns || []).map(function (column) {
				return '<th>' + escapeHtml(column) + '</th>';
			}).join('');

		const bodyHtml = (tab.rows || []).map(function (row, index) {
			const cellsHtml = (row.cells || []).map(function (cell) {
				return '<td>' + escapeHtml(cell) + '</td>';
			}).join('');

			return '<tr data-id="' + row.id + '">' +
				'<td class="procedure-row-index text-muted">' + (index + 1) + '</td>' +
				cellsHtml +
			'</tr>';
		}).join('');

		return '<div class="procedure-table-scroll">' +
			'<table class="table table-hover align-middle mb-0 procedure-data-table">' +
				'<thead><tr>' + headerHtml + '</tr></thead>' +
				'<tbody>' + bodyHtml + '</tbody>' +
			'</table>' +
		'</div>';
	}

	function buildTabButton(tab, isActive) {
		return '<li class="nav-item procedure-tab-nav-item" role="presentation">' +
			'<div class="procedure-tab-nav-wrap">' +
				'<button class="nav-link' + (isActive ? ' active' : '') + '" ' +
					'id="org-registration-tab-' + tab.registration_id + '-tab" ' +
					'data-mdb-tab-init ' +
					'data-mdb-target="#org-registration-tab-' + tab.registration_id + '" ' +
					'type="button" role="tab" ' +
					'aria-controls="org-registration-tab-' + tab.registration_id + '" ' +
					'aria-selected="' + (isActive ? 'true' : 'false') + '" ' +
					'data-registration-id="' + tab.registration_id + '">' +
					'<i class="fas fa-file-zipper me-1"></i>' + escapeHtml(tab.file_name) +
					'<span class="badge bg-secondary ms-2">' + (tab.rows || []).length + '</span>' +
				'</button>' +
				'<button type="button" class="procedure-tab-nav-close org-registration-tab-delete-btn"' +
					' data-registration-id="' + tab.registration_id + '"' +
					' data-file-name="' + escapeAttr(tab.file_name) + '"' +
					' aria-label="Stop registration" data-mdb-ripple-init>' +
					'<i class="fas fa-times" aria-hidden="true"></i>' +
				'</button>' +
			'</div>' +
		'</li>';
	}

	function buildTabPane(tab, isActive) {
		return '<div class="tab-pane fade' + (isActive ? ' show active' : '') + '" ' +
			'id="org-registration-tab-' + tab.registration_id + '" role="tabpanel" ' +
			'aria-labelledby="org-registration-tab-' + tab.registration_id + '-tab" ' +
			'data-registration-id="' + tab.registration_id + '">' +
			'<div class="procedure-tab-meta d-flex flex-wrap gap-3 mb-3 small text-muted align-items-center">' +
				'<span><strong>Procedure #:</strong> ' + escapeHtml(tab.procedure_number) + '</span>' +
				'<span><strong>Organization:</strong> ' + escapeHtml(tab.organization_name || '') + '</span>' +
				'<span><strong>Processor:</strong> ' + escapeHtml(tab.processor_name || '') + '</span>' +
				'<span><strong>Status:</strong> ' + escapeHtml(tab.status || 'uploaded') + '</span>' +
				'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at || '') + '</span>' +
				'<button type="button" class="btn btn-sm btn-outline-danger ms-auto org-registration-tab-delete-btn"' +
					' data-registration-id="' + tab.registration_id + '"' +
					' data-file-name="' + escapeAttr(tab.file_name) + '" data-mdb-ripple-init>' +
					'<i class="fas fa-trash me-1"></i> Delete' +
				'</button>' +
			'</div>' +
			buildTableHtml(tab) +
		'</div>';
	}

	function tabExists(registrationId) {
		return !!tabsNav.querySelector('.nav-link[data-registration-id="' + registrationId + '"]');
	}

	function deactivateTabs() {
		tabsNav.querySelectorAll('.nav-link').forEach(function (button) {
			button.classList.remove('active');
			button.setAttribute('aria-selected', 'false');
		});
		tabsContent.querySelectorAll('.tab-pane').forEach(function (pane) {
			pane.classList.remove('show', 'active');
		});
	}

	function activateTab(registrationId) {
		deactivateTabs();

		const button = tabsNav.querySelector('.nav-link[data-registration-id="' + registrationId + '"]');
		const pane = tabsContent.querySelector('.tab-pane[data-registration-id="' + registrationId + '"]');

		if (button) {
			button.classList.add('active');
			button.setAttribute('aria-selected', 'true');
		}

		if (pane) {
			pane.classList.add('show', 'active');
		}
	}

	function initTabControls(scope) {
		if (typeof mdb === 'undefined') {
			return;
		}

		(scope || document).querySelectorAll('[data-mdb-tab-init]').forEach(function (element) {
			mdb.Tab.getOrCreateInstance(element);
		});
	}

	function openDeleteModal(registrationId, fileName) {
		if (!deleteModal || !registrationId) {
			return;
		}

		pendingDeleteRegistrationId = registrationId;

		if (deleteModalTitle) {
			deleteModalTitle.textContent = fileName || '—';
		}

		deleteModal.show();
	}

	function removeTabFromDom(registrationId) {
		const tabBtn = tabsNav.querySelector('.nav-link[data-registration-id="' + registrationId + '"]');
		const navItem = tabBtn ? tabBtn.closest('.nav-item') : null;
		const pane = tabsContent.querySelector('.tab-pane[data-registration-id="' + registrationId + '"]');
		const wasActive = tabBtn && tabBtn.classList.contains('active');

		if (navItem) {
			navItem.remove();
		}

		if (pane) {
			pane.remove();
		}

		const remainingTabs = tabsNav.querySelectorAll('.nav-item');

		if (!remainingTabs.length) {
			tabsWrapper.classList.add('d-none');
			if (initialUpload) {
				initialUpload.classList.remove('d-none');
			}
			if (openUploadBtn) {
				openUploadBtn.classList.add('d-none');
			}
		} else if (wasActive) {
			const nextTab = tabsNav.querySelector('.nav-link');

			if (nextTab) {
				activateTab(nextTab.getAttribute('data-registration-id'));
			}
		}
	}

	function confirmDeleteRegistration() {
		if (!pendingDeleteRegistrationId) {
			return;
		}

		const registrationId = pendingDeleteRegistrationId;

		if (confirmDeleteBtn) {
			confirmDeleteBtn.disabled = true;
		}

		fetch(baseUrl + '/delete/' + registrationId, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function (result) {
				if (!result.ok || !result.data.success) {
					showToast(result.data.message || 'Failed to delete registration.', 'error');
					return;
				}

				removeTabFromDom(registrationId);

				if (deleteModal) {
					deleteModal.hide();
				}

				showToast(result.data.message, 'success');
			})
			.catch(function () {
				showToast('Failed to delete registration.', 'error');
			})
			.finally(function () {
				if (confirmDeleteBtn) {
					confirmDeleteBtn.disabled = false;
				}
				pendingDeleteRegistrationId = null;
			});
	}

	function prependTabs(tabs) {
		if (!tabs.length) {
			return;
		}

		if (initialUpload) {
			initialUpload.classList.add('d-none');
		}
		if (openUploadBtn) {
			openUploadBtn.classList.remove('d-none');
		}
		tabsWrapper.classList.remove('d-none');

		const newTabs = tabs.filter(function (tab) {
			return !tabExists(tab.registration_id);
		});

		newTabs.slice().reverse().forEach(function (tab) {
			tabsNav.insertAdjacentHTML('afterbegin', buildTabButton(tab, false));
			tabsContent.insertAdjacentHTML('afterbegin', buildTabPane(tab, false));
		});

		initTabControls(tabsWrapper);

		if (newTabs.length) {
			activateTab(newTabs[0].registration_id);
		}
	}

	function submitUpload(event) {
		event.preventDefault();

		if (!selectedFiles.length) {
			showToast('Please select at least one zip file.', 'error');
			return;
		}

		const formData = new FormData();
		selectedFiles.forEach(function (file) {
			formData.append('zip_files[]', file);
		});

		uploadForms.forEach(function (form) {
			const controls = getUploadControls(form);
			controls.uploadBtn.disabled = true;
			controls.uploadHint.textContent = 'Uploading and processing files...';
		});

		fetch(baseUrl + '/upload', {
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
					showToast(result.data.message || 'Upload failed.', 'error');
					return;
				}

				prependTabs(result.data.tabs || []);
				resetUploadForm();

				if (uploadModal) {
					uploadModal.hide();
				}

				showToast(result.data.message, 'success');
			})
			.catch(function () {
				showToast('Upload failed.', 'error');
			})
			.finally(function () {
				uploadForms.forEach(function (form) {
					const controls = getUploadControls(form);
					controls.uploadBtn.disabled = selectedFiles.length === 0;
					controls.uploadHint.textContent = selectedFiles.length
						? selectedFiles.length + ' file(s) selected.'
						: 'Select one or more zip files to continue.';
				});
			});
	}

	function bindUploadForm(form) {
		const controls = getUploadControls(form);

		if (!controls.zone || !controls.fileInput) {
			return;
		}

		controls.zone.addEventListener('click', function () {
			openFilePicker(controls.fileInput);
		});

		controls.fileInput.addEventListener('change', function () {
			setSelectedFiles(Array.from(controls.fileInput.files || []));
		});

		controls.zone.addEventListener('dragover', function (event) {
			event.preventDefault();
			controls.zone.classList.add('is-dragover');
		});

		controls.zone.addEventListener('dragleave', function () {
			controls.zone.classList.remove('is-dragover');
		});

		controls.zone.addEventListener('drop', function (event) {
			event.preventDefault();
			controls.zone.classList.remove('is-dragover');
			const files = Array.from(event.dataTransfer.files || []).filter(function (file) {
				return file.name.toLowerCase().endsWith('.zip');
			});
			setSelectedFiles(files);
		});

		form.addEventListener('submit', submitUpload);

		form.addEventListener('click', function (event) {
			const removeBtn = event.target.closest('.procedure-selected-file-remove');

			if (!removeBtn) {
				return;
			}

			const index = parseInt(removeBtn.getAttribute('data-index'), 10);
			selectedFiles = selectedFiles.filter(function (_, fileIndex) {
				return fileIndex !== index;
			});
			renderSelectedFiles();
		});
	}

	document.addEventListener('click', function (event) {
		const deleteBtn = event.target.closest('.org-registration-tab-delete-btn');

		if (deleteBtn) {
			openDeleteModal(
				deleteBtn.getAttribute('data-registration-id'),
				deleteBtn.getAttribute('data-file-name')
			);
		}
	});

	if (openUploadBtn) {
		openUploadBtn.addEventListener('click', openUploadModal);
	}

	if (confirmDeleteBtn) {
		confirmDeleteBtn.addEventListener('click', confirmDeleteRegistration);
	}

	uploadForms.forEach(bindUploadForm);
	initUploadModal();
	initDeleteModal();
	initTabControls(tabsWrapper);
})();
