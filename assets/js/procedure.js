(function () {
	'use strict';

	if (typeof window.PROCEDURE_CONFIG === 'undefined') {
		return;
	}

	const config = window.PROCEDURE_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const formEl = document.getElementById('procedureUploadForm');
	const uploadZone = document.getElementById('procedureUploadZone');
	const fileInput = document.getElementById('zipFilesInput');
	const selectedFilesList = document.getElementById('selectedFilesList');
	const uploadBtn = document.getElementById('procedureUploadBtn');
	const uploadHint = document.getElementById('procedureUploadHint');
	const tabsWrapper = document.getElementById('procedureTabsWrapper');
	const tabsNav = document.getElementById('procedureTabs');
	const tabsContent = document.getElementById('procedureTabContent');
	const emptyState = document.getElementById('procedureEmptyState');
	const tabCount = document.getElementById('procedureTabCount');
	const toastContainer = document.getElementById('toastContainer');

	let selectedFiles = [];

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text ?? '';
		return div.innerHTML;
	}

	function showToast(message, type) {
		const id = 'toast-' + Date.now();
		const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
		const html =
			'<div id="' + id + '" class="toast align-items-center text-white ' + bgClass + ' border-0" role="alert">' +
				'<div class="d-flex"><div class="toast-body">' + escapeHtml(message) + '</div>' +
				'<button type="button" class="btn-close btn-close-white me-2 m-auto" data-mdb-dismiss="toast"></button></div></div>';
		toastContainer.insertAdjacentHTML('beforeend', html);
		const toastEl = document.getElementById(id);

		if (typeof mdb !== 'undefined') {
			const toast = new mdb.Toast(toastEl, { delay: 4000 });
			toast.show();
			toastEl.addEventListener('hidden.mdb.toast', function () { toastEl.remove(); });
		}
	}

	function renderSelectedFiles() {
		if (!selectedFiles.length) {
			selectedFilesList.classList.add('d-none');
			selectedFilesList.innerHTML = '';
			uploadBtn.disabled = true;
			uploadHint.textContent = 'Select one or more zip files to continue.';
			return;
		}

		selectedFilesList.classList.remove('d-none');
		selectedFilesList.innerHTML = selectedFiles.map(function (file, index) {
			return '<div class="procedure-selected-file">' +
				'<span><i class="fas fa-file-zipper me-2 text-primary"></i>' + escapeHtml(file.name) + '</span>' +
				'<button type="button" class="btn btn-sm btn-outline-danger" data-index="' + index + '" data-mdb-ripple-init>' +
					'<i class="fas fa-times"></i>' +
				'</button>' +
			'</div>';
		}).join('');

		uploadBtn.disabled = false;
		uploadHint.textContent = selectedFiles.length + ' file(s) ready to upload.';
	}

	function setSelectedFiles(fileList) {
		const files = Array.from(fileList || []).filter(function (file) {
			return /\.zip$/i.test(file.name);
		});

		if (!files.length) {
			showToast('Please select valid .zip files.', 'error');
			return;
		}

		selectedFiles = files;
		renderSelectedFiles();
	}

	function buildImageCell(imageUrls) {
		if (!Array.isArray(imageUrls) || !imageUrls.length) {
			return '<span class="text-muted small">No image</span>';
		}

		return imageUrls.map(function (url) {
			return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener" class="procedure-thumb-link me-1">' +
				'<img src="' + escapeHtml(url) + '" alt="" class="procedure-thumb">' +
			'</a>';
		}).join('');
	}

	function buildTableHtml(tab) {
		const headerHtml = (tab.columns || []).map(function (column) {
			return '<th>' + escapeHtml(column) + '</th>';
		}).join('') + '<th>Image</th>';

		const bodyHtml = (tab.rows || []).map(function (row) {
			const cellsHtml = (row.cells || []).map(function (cell) {
				return '<td>' + escapeHtml(cell) + '</td>';
			}).join('');

			return '<tr data-id="' + row.id + '" data-product-number="' + escapeHtml(row.product_procedure_number) + '">' +
				cellsHtml +
				'<td>' + buildImageCell(row.image_urls) + '</td>' +
			'</tr>';
		}).join('');

		return '<div class="table-responsive">' +
			'<table class="table table-hover align-middle mb-0 procedure-data-table">' +
				'<thead><tr>' + headerHtml + '</tr></thead>' +
				'<tbody>' + bodyHtml + '</tbody>' +
			'</table>' +
		'</div>';
	}

	function buildTabButton(tab, isActive) {
		return '<li class="nav-item" role="presentation">' +
			'<button class="nav-link' + (isActive ? ' active' : '') + '" ' +
				'id="procedure-tab-' + tab.procedure_id + '-tab" ' +
				'data-mdb-tab-init ' +
				'data-mdb-target="#procedure-tab-' + tab.procedure_id + '" ' +
				'type="button" role="tab" ' +
				'aria-controls="procedure-tab-' + tab.procedure_id + '" ' +
				'aria-selected="' + (isActive ? 'true' : 'false') + '" ' +
				'data-procedure-id="' + tab.procedure_id + '">' +
				'<i class="fas fa-file-zipper me-1"></i>' + escapeHtml(tab.file_name) +
				'<span class="badge bg-secondary ms-2">' + (tab.rows || []).length + '</span>' +
			'</button>' +
		'</li>';
	}

	function buildTabPane(tab, isActive) {
		return '<div class="tab-pane fade' + (isActive ? ' show active' : '') + '" ' +
			'id="procedure-tab-' + tab.procedure_id + '" role="tabpanel" ' +
			'aria-labelledby="procedure-tab-' + tab.procedure_id + '-tab" ' +
			'data-procedure-id="' + tab.procedure_id + '">' +
			'<div class="procedure-tab-meta d-flex flex-wrap gap-3 mb-3 small text-muted">' +
				'<span><strong>Procedure #:</strong> ' + escapeHtml(tab.procedure_number) + '</span>' +
				'<span><strong>Organization:</strong> ' + escapeHtml(tab.organization_name) + '</span>' +
				'<span><strong>Processor:</strong> ' + escapeHtml(tab.processor_name || '') + '</span>' +
				'<span><strong>Status:</strong> ' + escapeHtml(tab.status || 'uploaded') + '</span>' +
				'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at || '') + '</span>' +
			'</div>' +
			buildTableHtml(tab) +
		'</div>';
	}

	function tabExists(procedureId) {
		return !!tabsNav.querySelector('[data-procedure-id="' + procedureId + '"]');
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

	function activateTab(procedureId) {
		deactivateTabs();

		const button = tabsNav.querySelector('[data-procedure-id="' + procedureId + '"]');
		const pane = tabsContent.querySelector('[data-procedure-id="' + procedureId + '"]');

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

	function updateTabCount() {
		const count = tabsNav.querySelectorAll('.nav-item').length;
		tabCount.textContent = count + ' zip file(s)';
	}

	function prependTabs(tabs) {
		if (!tabs.length) {
			return;
		}

		emptyState.classList.add('d-none');
		tabsWrapper.classList.remove('d-none');

		const newTabs = tabs.filter(function (tab) {
			return !tabExists(tab.procedure_id);
		});

		newTabs.slice().reverse().forEach(function (tab) {
			tabsNav.insertAdjacentHTML('afterbegin', buildTabButton(tab, false));
			tabsContent.insertAdjacentHTML('afterbegin', buildTabPane(tab, false));
		});

		initTabControls(tabsWrapper);

		if (newTabs.length) {
			activateTab(newTabs[0].procedure_id);
		}

		updateTabCount();
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

		uploadBtn.disabled = true;
		uploadHint.textContent = 'Uploading and processing files...';

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
				selectedFiles = [];
				fileInput.value = '';
				renderSelectedFiles();
				showToast(result.data.message, 'success');
			})
			.catch(function () {
				showToast('An unexpected error occurred during upload.', 'error');
			})
			.finally(function () {
				uploadBtn.disabled = false;
				uploadHint.textContent = selectedFiles.length
					? selectedFiles.length + ' file(s) ready to upload.'
					: 'Select one or more zip files to continue.';
			});
	}

	uploadZone.addEventListener('click', function () {
		fileInput.click();
	});

	fileInput.addEventListener('change', function () {
		setSelectedFiles(fileInput.files);
	});

	selectedFilesList.addEventListener('click', function (event) {
		const button = event.target.closest('button[data-index]');

		if (!button) {
			return;
		}

		const index = parseInt(button.getAttribute('data-index'), 10);
		selectedFiles.splice(index, 1);
		renderSelectedFiles();
	});

	['dragenter', 'dragover'].forEach(function (eventName) {
		uploadZone.addEventListener(eventName, function (event) {
			event.preventDefault();
			uploadZone.classList.add('is-dragover');
		});
	});

	['dragleave', 'drop'].forEach(function (eventName) {
		uploadZone.addEventListener(eventName, function (event) {
			event.preventDefault();
			uploadZone.classList.remove('is-dragover');
		});
	});

	uploadZone.addEventListener('drop', function (event) {
		setSelectedFiles(event.dataTransfer.files);
	});

	formEl.addEventListener('submit', submitUpload);
	initTabControls(tabsWrapper);
	renderSelectedFiles();
})();
