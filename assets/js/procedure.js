(function () {
	'use strict';

	if (typeof window.PROCEDURE_CONFIG === 'undefined') {
		return;
	}

	const config = window.PROCEDURE_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const uploadForms = document.querySelectorAll('.procedure-upload-form');
	const uploadModalEl = document.getElementById('procedureUploadModal');
	const openUploadBtn = document.getElementById('btnOpenUploadModal');
	const tabsWrapper = document.getElementById('procedureTabsWrapper');
	const tabsNav = document.getElementById('procedureTabs');
	const tabsContent = document.getElementById('procedureTabContent');
	const initialUpload = document.getElementById('procedureInitialUpload');
	const tabCount = document.getElementById('procedureTabCount');
	const toastContainer = document.getElementById('toastContainer');
	const productModalEl = document.getElementById('procedureProductModal');
	const productModalTitle = document.getElementById('procedureProductModalLabel');
	const productDetailImageGallery = document.getElementById('procedureDetailImageGallery');
	const productDetailInfo = document.getElementById('procedureDetailInfo');
	const productDetailFooterMeta = document.getElementById('procedureDetailFooterMeta');
	const productDetailPrevBtn = document.getElementById('procedureDetailPrevBtn');
	const productDetailNextBtn = document.getElementById('procedureDetailNextBtn');
	const procedureBarcodeInput = document.getElementById('procedureBarcodeInput');
	const procedureBarcodeFullValue = document.getElementById('procedureBarcodeFullValue');
	const procedureBarcodeSvg = document.getElementById('procedureBarcodeSvg');
	const procedureBarcodeEmpty = document.getElementById('procedureBarcodeEmpty');
	const procedureQrCode = document.getElementById('procedureQrCode');
	const procedureQrEmpty = document.getElementById('procedureQrEmpty');
	const procedureDetailTabsEl = document.getElementById('procedureDetailTabs');
	const deleteModalEl = document.getElementById('procedureDeleteModal');
	const deleteModalTitle = document.getElementById('procedureDeleteModalLabel');
	const confirmDeleteBtn = document.getElementById('procedureConfirmDeleteBtn');

	let selectedFiles = [];
	let filePickerOpenUntil = 0;
	let pendingDeleteProcedureId = null;
	let uploadModal;
	let productModal;
	let deleteModal;
	let productImageViewer = null;
	let productQrCodeInstance = null;
	let activeDetailRow = null;

	function initUploadModal() {
		if (typeof mdb === 'undefined' || !uploadModalEl) {
			return;
		}

		uploadModal = mdb.Modal.getOrCreateInstance(uploadModalEl);
		uploadModalEl.addEventListener('hidden.mdb.modal', resetUploadForm);
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

	function initProductModal() {
		if (typeof mdb === 'undefined' || !productModalEl) {
			return;
		}

		productModal = mdb.Modal.getOrCreateInstance(productModalEl);
		productModalEl.addEventListener('hidden.mdb.modal', clearActiveDetailRow);
		initDetailTabs();
	}

	function ensureDeleteModal() {
		if (deleteModal || typeof mdb === 'undefined' || !deleteModalEl) {
			return deleteModal;
		}

		deleteModal = mdb.Modal.getOrCreateInstance(deleteModalEl);
		return deleteModal;
	}

	function initDeleteModal() {
		ensureDeleteModal();
	}

	function initDetailTabs() {
		if (typeof mdb === 'undefined' || !procedureDetailTabsEl) {
			return;
		}

		procedureDetailTabsEl.querySelectorAll('[data-mdb-tab-init]').forEach(function (element) {
			mdb.Tab.getOrCreateInstance(element);
		});
	}

	function resetBarcodePreview() {
		if (procedureBarcodeSvg) {
			procedureBarcodeSvg.innerHTML = '';
			procedureBarcodeSvg.classList.add('d-none');
		}

		if (procedureBarcodeEmpty) {
			procedureBarcodeEmpty.classList.remove('d-none');
		}

		if (procedureQrCode) {
			procedureQrCode.innerHTML = '';
		}

		if (procedureQrEmpty) {
			procedureQrEmpty.classList.remove('d-none');
		}

		productQrCodeInstance = null;
	}

	function calculateEan13Checksum(twelveDigits) {
		let sum = 0;

		for (let i = 0; i < 12; i++) {
			const digit = parseInt(twelveDigits.charAt(i), 10);
			sum += (i % 2 === 0) ? digit : digit * 3;
		}

		return String((10 - (sum % 10)) % 10);
	}

	function toEan13Full(value) {
		const digits = String(value || '').replace(/\D/g, '');

		if (!digits) {
			return null;
		}

		const dataDigits = digits.slice(0, 12).padStart(12, '0');

		return dataDigits + calculateEan13Checksum(dataDigits);
	}

	function applyBarcodeTextSpacing(svgEl) {
		if (!svgEl) {
			return;
		}

		svgEl.querySelectorAll('text').forEach(function (textEl) {
			textEl.setAttribute('font-family', 'Courier New, monospace');
			textEl.setAttribute('letter-spacing', '0.32em');
			textEl.setAttribute('fill', '#000000');
		});
	}

	function renderBarcodePreview(value) {
		const barcodeValue = (value || '').trim();
		const ean13Full = toEan13Full(barcodeValue);
		resetBarcodePreview();

		if (!barcodeValue) {
			if (procedureBarcodeFullValue) {
				procedureBarcodeFullValue.textContent = 'Full EAN-13 (with checksum): —';
			}
			return;
		}

		if (procedureBarcodeFullValue) {
			procedureBarcodeFullValue.textContent = ean13Full
				? 'Full EAN-13 (with checksum): ' + ean13Full
				: 'Full EAN-13 (with checksum): —';
		}

		if (typeof JsBarcode !== 'undefined' && procedureBarcodeSvg && ean13Full) {
			try {
				// Pass 12 data digits only; JsBarcode calculates and displays the checksum digit.
				JsBarcode(procedureBarcodeSvg, ean13Full.slice(0, 12), {
					format: 'EAN13',
					lineColor: '#000000',
					background: '#ffffff',
					width: 2,
					height: 72,
					displayValue: true,
					font: 'Courier New, monospace',
					fontSize: 16,
					textMargin: 6,
					marginRight: 10,
					marginLeft: 3,
					marginTop: 3,
					marginBottom: 3,
				});
				applyBarcodeTextSpacing(procedureBarcodeSvg);
				procedureBarcodeSvg.classList.remove('d-none');

				if (procedureBarcodeEmpty) {
					procedureBarcodeEmpty.classList.add('d-none');
				}
			} catch (error) {
				if (procedureBarcodeEmpty) {
					procedureBarcodeEmpty.textContent = 'Unable to render barcode for this value.';
					procedureBarcodeEmpty.classList.remove('d-none');
				}
			}
		}

		if (typeof QRCode !== 'undefined' && procedureQrCode && ean13Full) {
			procedureQrCode.innerHTML = '';
			productQrCodeInstance = new QRCode(procedureQrCode, {
				text: ean13Full,
				width: 180,
				height: 180,
				colorDark: '#000000',
				colorLight: '#ffffff',
				correctLevel: QRCode.CorrectLevel.M,
			});

			if (procedureQrEmpty) {
				procedureQrEmpty.classList.add('d-none');
			}
		}
	}

	function buildRowPayload(tab, row, index) {
		return {
			id: row.id,
			row_index: index + 1,
			product_procedure_number: row.product_procedure_number,
			cells: row.cells || [],
			columns: tab.columns || [],
			image_urls: row.image_urls || [],
			procedure_number: tab.procedure_number,
			organization_name: tab.organization_name,
			file_name: tab.file_name,
			processor_name: tab.processor_name || '',
			status: tab.status || 'uploaded',
			created_at: tab.created_at || '',
		};
	}

	function destroyProductImageViewer() {
		if (productImageViewer) {
			productImageViewer.destroy();
			productImageViewer = null;
		}
	}

	function initProductImageViewer() {
		destroyProductImageViewer();

		const viewport = document.getElementById('procedureImageViewport');

		if (!viewport || typeof ImageViewer === 'undefined') {
			return;
		}

		productImageViewer = new ImageViewer(viewport);
	}

	function renderDetailImages(imageUrls) {
		destroyProductImageViewer();

		if (!Array.isArray(imageUrls) || !imageUrls.length) {
			productDetailImageGallery.innerHTML =
				'<div class="procedure-detail-empty-image">' +
					'<i class="fas fa-image d-block"></i>' +
					'<p class="mb-0">No design image found for this product.</p>' +
				'</div>';
			return;
		}

		const mainUrl = imageUrls[0];
		let thumbsHtml = '';

		imageUrls.forEach(function (url, index) {
			thumbsHtml +=
				'<button type="button" class="procedure-detail-thumb-btn' + (index === 0 ? ' active' : '') + '" data-image-url="' + escapeAttr(url) + '">' +
					'<img src="' + escapeAttr(url) + '" alt="">' +
				'</button>';
		});

		productDetailImageGallery.innerHTML =
			'<div class="procedure-image-viewport image-viewer" id="procedureImageViewport">' +
				'<div class="image-viewer-stage procedure-image-stage">' +
					'<img src="' + escapeAttr(mainUrl) + '" alt="" class="image-viewer-target procedure-detail-main-image" id="procedureDetailMainImage" draggable="false">' +
				'</div>' +
			'</div>' +
			'<div class="procedure-image-viewer-toolbar">' +
				'<span class="text-muted small"><i class="fas fa-mouse me-1"></i>Scroll to zoom</span>' +
				'<span class="text-muted small"><i class="fas fa-hand-pointer me-1"></i>Drag to move</span>' +
				'<button type="button" class="btn btn-sm btn-outline-secondary" id="procedureImageResetBtn" data-mdb-ripple-init>Reset View</button>' +
			'</div>' +
			(imageUrls.length > 1
				? '<div class="procedure-detail-thumb-list" id="procedureDetailThumbList">' + thumbsHtml + '</div>'
				: '');

		initProductImageViewer();
	}

	function renderDetailInfo(payload) {
		const fields = [];
		const columns = payload.columns || [];
		const cells = payload.cells || [];

		columns.forEach(function (column, index) {
			fields.push({
				label: column,
				value: cells[index] ?? '',
			});
		});

		fields.push(
			{ label: 'Procedure #', value: payload.procedure_number || '' },
			{ label: 'Zip File', value: payload.file_name || '' },
			{ label: 'Processor', value: payload.processor_name || '' },
			{ label: 'Uploaded', value: payload.created_at || '' }
		);

		const fieldsHtml = fields.map(function (field) {
			return '<div class="procedure-detail-info-item">' +
				'<span class="procedure-detail-info-label">' + escapeHtml(field.label) + '</span>' +
				'<span class="procedure-detail-info-value">' + escapeHtml(field.value) + '</span>' +
			'</div>';
		}).join('');

		productDetailInfo.innerHTML =
			'<div class="procedure-detail-info-grid">' + fieldsHtml + '</div>';
	}

	function renderModalHeader(payload) {
		if (!productModalTitle) {
			return;
		}

		const orgName = escapeHtml(payload.organization_name || '');
		const productNumber = escapeHtml(payload.product_procedure_number || 'Product');
		const status = escapeHtml(payload.status || 'uploaded');

		productModalTitle.innerHTML =
			(orgName ? '<span class="procedure-detail-header-org">' + orgName + '</span>' : '') +
			'<span class="procedure-detail-header-product">' + productNumber + '</span>' +
			'<span class="badge bg-info text-dark">' + status + '</span>';
	}

	function getActiveTabRows() {
		const activePane = tabsContent ? tabsContent.querySelector('.tab-pane.active') : null;

		if (!activePane) {
			return [];
		}

		return Array.from(activePane.querySelectorAll('tr.procedure-data-row'));
	}

	function clearActiveDetailRow() {
		if (activeDetailRow) {
			activeDetailRow.classList.remove('procedure-data-row-active');
			activeDetailRow = null;
		}

		updateDetailNavButtons();
	}

	function setActiveDetailRow(row) {
		clearActiveDetailRow();

		if (!row) {
			return;
		}

		row.classList.add('procedure-data-row-active');
		activeDetailRow = row;
		row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		updateDetailNavButtons();
	}

	function updateDetailNavButtons() {
		const rows = getActiveTabRows();
		const currentIndex = activeDetailRow ? rows.indexOf(activeDetailRow) : -1;

		if (productDetailPrevBtn) {
			productDetailPrevBtn.disabled = currentIndex <= 0;
		}

		if (productDetailNextBtn) {
			productDetailNextBtn.disabled = currentIndex < 0 || currentIndex >= rows.length - 1;
		}

		if (productDetailFooterMeta) {
			if (currentIndex >= 0) {
				productDetailFooterMeta.textContent = 'Row ' + (currentIndex + 1) + ' of ' + rows.length;
			} else {
				productDetailFooterMeta.textContent = '';
			}
		}
	}

	function navigateDetailRow(direction) {
		const rows = getActiveTabRows();
		const currentIndex = activeDetailRow ? rows.indexOf(activeDetailRow) : -1;
		const nextIndex = currentIndex + direction;

		if (nextIndex < 0 || nextIndex >= rows.length) {
			return;
		}

		const row = rows[nextIndex];
		const payload = parseRowPayload(row);

		if (payload) {
			openProductDetailModal(payload, row);
		}
	}

	function openProductDetailModal(payload, row) {
		if (!productModal || !payload) {
			return;
		}

		if (row) {
			setActiveDetailRow(row);
		}

		renderModalHeader(payload);
		renderDetailImages(payload.image_urls || []);
		renderDetailInfo(payload);

		if (procedureBarcodeInput) {
			procedureBarcodeInput.value = payload.product_procedure_number || '';
			renderBarcodePreview(procedureBarcodeInput.value);
		}

		const imageTabBtn = document.getElementById('procedureDetailTabImageBtn');

		if (imageTabBtn && typeof mdb !== 'undefined') {
			mdb.Tab.getOrCreateInstance(imageTabBtn).show();
		}

		productModal.show();
	}

	function parseRowPayload(row) {
		const raw = row.getAttribute('data-row-payload');

		if (!raw) {
			return null;
		}

		try {
			return JSON.parse(raw);
		} catch (error) {
			return null;
		}
	}

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
		const filesHtml = selectedFiles.map(function (file, index) {
			return '<span class="procedure-selected-file">' +
				'<i class="fas fa-file-zipper procedure-selected-file-icon"></i>' +
				'<span class="procedure-selected-file-name">' + escapeHtml(file.name) + '</span>' +
				'<button type="button" class="procedure-selected-file-remove" data-index="' + index + '" aria-label="Remove ' + escapeAttr(file.name) + '">' +
					'<i class="fas fa-times"></i>' +
				'</button>' +
			'</span>';
		}).join('');

		uploadForms.forEach(function (form) {
			const controls = getUploadControls(form);

			if (!selectedFiles.length) {
				controls.selectedFilesList.classList.add('d-none');
				controls.selectedFilesList.innerHTML = '';
				controls.uploadBtn.disabled = true;
				controls.uploadHint.textContent = 'Select one or more zip files to continue.';
				return;
			}

			controls.selectedFilesList.classList.remove('d-none');
			controls.selectedFilesList.innerHTML = filesHtml;
			controls.uploadBtn.disabled = false;
			controls.uploadHint.textContent = selectedFiles.length + ' file(s) ready to upload.';
		});
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
			const payload = buildRowPayload(tab, row, index);
			const cellsHtml = (row.cells || []).map(function (cell) {
				return '<td>' + escapeHtml(cell) + '</td>';
			}).join('');

			return '<tr class="procedure-data-row" data-id="' + row.id + '" data-product-number="' + escapeAttr(row.product_procedure_number) + '" data-row-payload="' + escapeAttr(JSON.stringify(payload)) + '">' +
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
			'<div class="procedure-tab-meta d-flex flex-wrap gap-3 mb-3 small text-muted align-items-center">' +
				'<span><strong>Procedure #:</strong> ' + escapeHtml(tab.procedure_number) + '</span>' +
				'<span><strong>Organization:</strong> ' + escapeHtml(tab.organization_name) + '</span>' +
				'<span><strong>Processor:</strong> ' + escapeHtml(tab.processor_name || '') + '</span>' +
				'<span><strong>Status:</strong> ' + escapeHtml(tab.status || 'uploaded') + '</span>' +
				'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at || '') + '</span>' +
				'<button type="button" class="btn btn-sm btn-outline-danger ms-auto procedure-tab-delete-btn"' +
					'data-procedure-id="' + tab.procedure_id + '" ' +
					'data-file-name="' + escapeAttr(tab.file_name) + '" ' +
					'data-mdb-ripple-init>' +
					'<i class="fas fa-trash me-1"></i> Delete' +
				'</button>' +
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
		if (!tabCount) {
			return;
		}

		const count = tabsNav.querySelectorAll('.nav-item').length;
		tabCount.textContent = count + ' zip file(s)';
	}

	function openDeleteModal(procedureId, fileName) {
		const modal = ensureDeleteModal();

		if (!modal || !procedureId) {
			return;
		}

		pendingDeleteProcedureId = procedureId;

		if (deleteModalTitle) {
			deleteModalTitle.textContent = fileName || '—';
		}

		modal.show();
	}

	function removeTab(procedureId) {
		const tabBtn = tabsNav.querySelector('.nav-link[data-procedure-id="' + procedureId + '"]');
		const navItem = tabBtn ? tabBtn.closest('.nav-item') : null;
		const pane = tabsContent.querySelector('.tab-pane[data-procedure-id="' + procedureId + '"]');
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
				activateTab(nextTab.getAttribute('data-procedure-id'));
			}
		}

		updateTabCount();
	}

	function confirmDeleteProcedure() {
		if (!pendingDeleteProcedureId) {
			return;
		}

		const procedureId = pendingDeleteProcedureId;

		if (confirmDeleteBtn) {
			confirmDeleteBtn.disabled = true;
		}

		fetch(baseUrl + '/delete/' + procedureId, {
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
					showToast(result.data.message || 'Failed to delete procedure.', 'error');
					return;
				}

				removeTab(procedureId);

				if (deleteModal) {
					deleteModal.hide();
				}

				showToast(result.data.message, 'success');
			})
			.catch(function () {
				showToast('Failed to delete procedure.', 'error');
			})
			.finally(function () {
				if (confirmDeleteBtn) {
					confirmDeleteBtn.disabled = false;
				}
				pendingDeleteProcedureId = null;
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
				showToast('An unexpected error occurred during upload.', 'error');
			})
			.finally(function () {
				renderSelectedFiles();
			});
	}

	initUploadModal();
	initProductModal();
	initDeleteModal();

	if (confirmDeleteBtn) {
		confirmDeleteBtn.addEventListener('click', confirmDeleteProcedure);
	}

	document.addEventListener('click', function (event) {
		const deleteBtn = event.target.closest('.procedure-tab-delete-btn');

		if (deleteBtn) {
			event.preventDefault();
			event.stopPropagation();
			openDeleteModal(
				deleteBtn.getAttribute('data-procedure-id'),
				deleteBtn.getAttribute('data-file-name')
			);
		}
	});

	uploadForms.forEach(function (form) {
		const controls = getUploadControls(form);

		if (controls.zone) {
			controls.zone.addEventListener('click', function () {
				openFilePicker(controls.fileInput);
			});

			['dragenter', 'dragover'].forEach(function (eventName) {
				controls.zone.addEventListener(eventName, function (event) {
					event.preventDefault();
					controls.zone.classList.add('is-dragover');
				});
			});

			['dragleave', 'drop'].forEach(function (eventName) {
				controls.zone.addEventListener(eventName, function (event) {
					event.preventDefault();
					controls.zone.classList.remove('is-dragover');
				});
			});

			controls.zone.addEventListener('drop', function (event) {
				setSelectedFiles(event.dataTransfer.files);
			});
		}

		if (controls.fileInput) {
			controls.fileInput.addEventListener('change', function () {
				setSelectedFiles(controls.fileInput.files);
				controls.fileInput.value = '';
			});
		}

		if (controls.selectedFilesList) {
			controls.selectedFilesList.addEventListener('click', function (event) {
				const button = event.target.closest('button[data-index]');

				if (!button) {
					return;
				}

				const index = parseInt(button.getAttribute('data-index'), 10);
				selectedFiles.splice(index, 1);
				renderSelectedFiles();
			});
		}

		form.addEventListener('submit', submitUpload);
	});

	if (tabsWrapper) {
		tabsWrapper.addEventListener('click', function (event) {
			const row = event.target.closest('tr.procedure-data-row');

			if (!row) {
				return;
			}

			const payload = parseRowPayload(row);

			if (payload) {
				openProductDetailModal(payload, row);
			}
		});
	}

	if (productDetailPrevBtn) {
		productDetailPrevBtn.addEventListener('click', function () {
			navigateDetailRow(-1);
		});
	}

	if (productDetailNextBtn) {
		productDetailNextBtn.addEventListener('click', function () {
			navigateDetailRow(1);
		});
	}

	if (productDetailImageGallery) {
		productDetailImageGallery.addEventListener('click', function (event) {
		const resetBtn = event.target.closest('#procedureImageResetBtn');

		if (resetBtn) {
			if (productImageViewer) {
				productImageViewer.reset();
			}
			return;
		}

		const thumbBtn = event.target.closest('.procedure-detail-thumb-btn');

		if (!thumbBtn) {
			return;
		}

		const imageUrl = thumbBtn.getAttribute('data-image-url');

		if (productImageViewer && imageUrl) {
			productImageViewer.setImage(imageUrl);
		}

		productDetailImageGallery.querySelectorAll('.procedure-detail-thumb-btn').forEach(function (button) {
			button.classList.remove('active');
		});
		thumbBtn.classList.add('active');
		});
	}

	if (procedureBarcodeInput) {
		procedureBarcodeInput.addEventListener('input', function () {
			renderBarcodePreview(procedureBarcodeInput.value);
		});
	}

	if (openUploadBtn) {
		openUploadBtn.addEventListener('click', openUploadModal);
	}

	initTabControls(tabsWrapper);
	renderSelectedFiles();
})();
