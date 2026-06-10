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
	const tableBody = document.querySelector('#procedureItemsTable tbody');
	const itemCount = document.getElementById('procedureItemCount');
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

	function formatDateTime(value) {
		if (!value) {
			return '';
		}

		const date = new Date(value.replace(' ', 'T'));

		if (Number.isNaN(date.getTime())) {
			return value;
		}

		return date.toLocaleString();
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

	function buildRow(item) {
		return '<tr data-id="' + (item.id || '') + '">' +
			'<td>' + escapeHtml(item.procedure_number) + '</td>' +
			'<td>' + escapeHtml(item.organization_name) + '</td>' +
			'<td>' + escapeHtml(item.product_procedure_number) + '</td>' +
			'<td>' + escapeHtml(item.name) + '</td>' +
			'<td>' + buildImageCell(item.image_urls) + '</td>' +
			'<td class="small">' + escapeHtml(item.file_name) + '</td>' +
			'<td>' + escapeHtml(item.processor_name || '') + '</td>' +
			'<td><span class="badge bg-info text-dark">' + escapeHtml(item.status || 'uploaded') + '</span></td>' +
			'<td class="small text-muted">' + escapeHtml(formatDateTime(item.created_at)) + '</td>' +
		'</tr>';
	}

	function prependItems(items) {
		const emptyRow = document.getElementById('procedureEmptyRow');

		if (emptyRow) {
			emptyRow.remove();
		}

		const html = items.map(buildRow).join('');
		tableBody.insertAdjacentHTML('afterbegin', html);
		updateItemCount(items.length);
	}

	function updateItemCount(added) {
		const current = tableBody.querySelectorAll('tr[data-id]').length;
		itemCount.textContent = current + ' items';
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

				const items = (result.data.items || []).map(function (item) {
					const procedure = (result.data.procedures || []).find(function (entry) {
						return parseInt(entry.id, 10) === parseInt(item.procedure_id, 10);
					});

					return {
						id: item.id,
						procedure_id: item.procedure_id,
						product_procedure_number: item.product_procedure_number,
						name: item.name,
						procedure_number: item.procedure_number,
						organization_name: item.organization_name,
						file_name: item.file_name,
						processor_name: procedure ? procedure.processor_name : '',
						status: procedure ? procedure.status : 'uploaded',
						image_urls: item.image_urls || [],
						created_at: procedure ? procedure.created_at : '',
					};
				});

				prependItems(items);
				selectedFiles = [];
				fileInput.value = '';
				renderSelectedFiles();
				showToast(result.data.message, 'success');
			})
			.catch(function () {
				showToast('An unexpected error occurred during upload.', 'error');
			})
			.finally(function () {
				if (selectedFiles.length) {
					uploadBtn.disabled = false;
					uploadHint.textContent = selectedFiles.length + ' file(s) ready to upload.';
				}
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
	renderSelectedFiles();
})();
