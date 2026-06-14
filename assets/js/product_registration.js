(function () {
	'use strict';

	if (typeof window.PRODUCT_REGISTRATION_CONFIG === 'undefined') {
		return;
	}

	const config = window.PRODUCT_REGISTRATION_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');
	const ID_ATTR = 'data-product-registration-id';
	const BARCODE_CELL_INDEX = 7;

	const uploadForms = document.querySelectorAll('.procedure-upload-form');
	const uploadModalEl = document.getElementById('procedureUploadModal');
	const openUploadBtn = document.getElementById('btnOpenUploadModal');
	const openImportBtn = document.getElementById('btnOpenImportModal');
	const importModalEl = document.getElementById('procedureImportModal');
	const importLoadingEl = document.getElementById('procedureImportLoading');
	const importSearchInput = document.getElementById('procedureImportSearch');
	const tabsWrapper = document.getElementById('procedureTabsWrapper');
	const tabsNav = document.getElementById('procedureTabs');
	const tabsContent = document.getElementById('procedureTabContent');
	const initialUpload = document.getElementById('procedureInitialUpload');
	const tabCount = document.getElementById('procedureTabCount');
	const productModalEl = document.getElementById('procedureProductModal');
	const productModalTitle = document.getElementById('procedureProductModalLabel');
	const productDetailImageGallery = document.getElementById('procedureDetailImageGallery');
	const productDetailInfo = document.getElementById('procedureDetailInfo');
	const productDetailFooterMeta = document.getElementById('procedureDetailFooterMeta');
	const productDetailPrevBtn = document.getElementById('procedureDetailPrevBtn');
	const productDetailNextBtn = document.getElementById('procedureDetailNextBtn');
	const procedureBarcodeInput = document.getElementById('procedureBarcodeInput');
	const assignableBarcodeEl = document.getElementById('assignable_barcode');
	const procedureBarcodeFullValue = document.getElementById('procedureBarcodeFullValue');
	const procedureBarcodeSvg = document.getElementById('procedureBarcodeSvg');
	const procedureBarcodeEmpty = document.getElementById('procedureBarcodeEmpty');
	const procedureQrCode = document.getElementById('procedureQrCode');
	const procedureQrEmpty = document.getElementById('procedureQrEmpty');
	const procedureDetailTabsEl = document.getElementById('procedureDetailTabs');
	const rejectModalEl = document.getElementById('procedureRejectModal');
	const rejectModalMeta = document.getElementById('procedureRejectModalMeta');
	const rejectReasonInput = document.getElementById('procedureRejectReasonInput');
	const rejectReasonError = document.getElementById('procedureRejectReasonError');
	const confirmRejectBtn = document.getElementById('procedureConfirmRejectBtn');
	const productDetailRejectBtn = document.getElementById('procedureDetailRejectBtn');
	const productDetailAcceptBtn = document.getElementById('procedureDetailAcceptBtn');

	let importModal;
	let productModal;
	let rejectModal;
	let importGrid = null;
	let similarGrid = null;
	let productImageViewer = null;
	let productQrCodeInstance = null;
	let barcodeTypingTimer = null;

	// Per-tab data + grids; the grid is the single source of truth for the
	// visible rows, while tabData keeps the columns/meta/barcodes for each tab.
	const tabData = new Map();
	const tabGrids = new Map();

	// The product currently shown in the detail modal: { regId, rowIndex }.
	let activeDetail = null;

	// ---------------------------------------------------------- status helpers

	function normalizeItemStatus(itemStatus) {
		const status = String(itemStatus || 'pending').toLowerCase();
		return status === 'accepted' ? 'approved' : status;
	}

	function statusIconRenderer(row, columnfield, value) {
		const status = normalizeItemStatus(value);
		let icon = '';

		if (status === 'rejected') {
			icon = '<i class="fas fa-times procedure-item-status-icon procedure-item-status-icon-rejected" aria-label="Rejected"></i>';
		} else if (status === 'approved') {
			icon = '<i class="fas fa-check procedure-item-status-icon procedure-item-status-icon-approved" aria-label="Accepted"></i>';
		}

		return '<div style="text-align:center;margin-top:6px;">' + icon + '</div>';
	}

	function rowStateClass(row, columnfield, value, rowdata) {
		const status = normalizeItemStatus(rowdata.item_status);

		if (status === 'rejected') {
			return 'procedure-grid-row-rejected';
		}

		if (status === 'approved') {
			return 'procedure-grid-row-approved';
		}

		return '';
	}

	function getBarcodeCellIndex(columns) {
		if (Array.isArray(columns)) {
			for (let i = 0; i < columns.length; i++) {
				if (String(columns[i] || '').trim().toLowerCase() === 'barcode') {
					return i;
				}
			}
		}

		return BARCODE_CELL_INDEX;
	}

	// --------------------------------------------------------------- tab grids

	function registerTabData(tab) {
		if (tab && tab.product_registration_id !== undefined) {
			tabData.set(String(tab.product_registration_id), tab);
		}
	}

	function paneFor(registrationId) {
		return tabsContent
			? tabsContent.querySelector('.tab-pane[' + ID_ATTR + '="' + registrationId + '"]')
			: null;
	}

	function buildGridRowObject(tab, row, index) {
		const obj = {
			id: row.id,
			item_status: normalizeItemStatus(row.item_status || row.status || 'pending'),
			__num: index + 1,
			product_procedure_number: row.product_procedure_number,
		};

		(row.cells || []).forEach(function (cell, cellIndex) {
			obj['c' + cellIndex] = cell;
		});

		return obj;
	}

	function buildGrid(tab, container) {
		const regId = String(tab.product_registration_id);

		const columns = [
			{
				text: '', datafield: 'item_status', width: 44, pinned: true, sortable: false,
				filterable: false, cellsrenderer: statusIconRenderer, cellclassname: rowStateClass,
			},
			{
				text: '#', datafield: '__num', width: 56, pinned: true, sortable: false,
				filterable: false, cellsalign: 'right', cellclassname: rowStateClass,
			},
		];

		const datafields = [
			{ name: 'id', type: 'number' },
			{ name: 'item_status', type: 'string' },
			{ name: '__num', type: 'number' },
			{ name: 'product_procedure_number', type: 'string' },
		];

		(tab.columns || []).forEach(function (column, index) {
			const field = 'c' + index;
			datafields.push({ name: field, type: 'string' });
			columns.push({
				text: column,
				datafield: field,
				pinned: index < 3,
				width: index < 3 ? 180 : 160,
				cellclassname: rowStateClass,
			});
		});

		const data = (tab.rows || []).map(function (row, index) {
			return buildGridRowObject(tab, row, index);
		});

		const grid = JqxGrid.local(container, {
			id: 'id',
			data: data,
			datafields: datafields,
			columns: columns,
			sortable: false,
			selectionmode: 'singlerow',
			pageable: true,
			pagesize: 50,
			pagesizeoptions: ['50', '100', '200'],
			height: Math.max(360, window.innerHeight - 300),
			emptyMessage: 'No products found.',
		});

		if (grid) {
			grid.$el.on('rowclick', function (event) {
				openProductDetailModal(regId, event.args.rowindex);
			});
		}

		return grid;
	}

	function ensureGrid(registrationId) {
		const key = String(registrationId);
		const pane = paneFor(key);

		if (!pane) {
			return;
		}

		const container = pane.querySelector('.product-registration-grid');

		if (!container) {
			return;
		}

		if (container.getAttribute('data-grid-ready') === '1') {
			const existing = tabGrids.get(key);
			if (existing) {
				existing.render();
			}
			return;
		}

		if (!container.offsetParent) {
			return;
		}

		const tab = tabData.get(key);

		if (!tab) {
			return;
		}

		const grid = buildGrid(tab, container);

		if (grid) {
			tabGrids.set(key, grid);
			container.setAttribute('data-grid-ready', '1');
		}
	}

	function refreshGridRow(regId, rowIndex) {
		const key = String(regId);
		const grid = tabGrids.get(key);
		const tab = tabData.get(key);

		if (!grid || !tab) {
			return;
		}

		const row = tab.rows[rowIndex];

		if (!row) {
			return;
		}

		grid.$el.jqxGrid('updaterow', row.id, buildGridRowObject(tab, row, rowIndex));
	}

	function activeRegistrationId() {
		const activePane = tabsContent ? tabsContent.querySelector('.tab-pane.active') : null;
		return activePane ? activePane.getAttribute(ID_ATTR) : null;
	}

	// ----------------------------------------------------------- detail modal

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
			item_status: normalizeItemStatus(row.item_status || row.status || 'pending'),
			rejection_reason: row.message || '',
			created_at: tab.created_at || '',
		};
	}

	function getActiveTab() {
		return activeDetail ? tabData.get(String(activeDetail.regId)) : null;
	}

	function getActiveRow() {
		const tab = getActiveTab();
		return tab && activeDetail ? tab.rows[activeDetail.rowIndex] : null;
	}

	function activeRowCount() {
		const tab = getActiveTab();
		return tab ? (tab.rows || []).length : 0;
	}

	function isActiveRowPending() {
		const row = getActiveRow();
		return !!row && normalizeItemStatus(row.item_status || row.status) === 'pending';
	}

	function selectGridRow(regId, rowIndex) {
		const grid = tabGrids.get(String(regId));

		if (!grid) {
			return;
		}

		grid.$el.jqxGrid('clearselection');
		grid.$el.jqxGrid('selectrow', rowIndex);
		grid.$el.jqxGrid('ensurerowvisible', rowIndex);
	}

	function clearActiveDetail() {
		if (activeDetail) {
			const grid = tabGrids.get(String(activeDetail.regId));
			if (grid) {
				grid.$el.jqxGrid('clearselection');
			}
			activeDetail = null;
		}

		updateDetailNavButtons();
	}

	function updateReviewButtonState() {
		const pending = isActiveRowPending();

		if (productDetailRejectBtn) {
			productDetailRejectBtn.disabled = !pending;
			productDetailRejectBtn.classList.toggle('d-none', !pending);
		}

		if (productDetailAcceptBtn) {
			productDetailAcceptBtn.disabled = !pending;
			productDetailAcceptBtn.classList.toggle('d-none', !pending);
		}
	}

	function updateDetailNavButtons() {
		const count = activeRowCount();
		const currentIndex = activeDetail ? activeDetail.rowIndex : -1;

		if (productDetailPrevBtn) {
			productDetailPrevBtn.disabled = currentIndex <= 0;
		}

		if (productDetailNextBtn) {
			productDetailNextBtn.disabled = currentIndex < 0 || currentIndex >= count - 1;
		}

		updateReviewButtonState();

		if (productDetailFooterMeta) {
			productDetailFooterMeta.textContent = currentIndex >= 0
				? 'Row ' + (currentIndex + 1) + ' of ' + count
				: '';
		}
	}

	function getAssignableBarcode(regId, rowIndex) {
		const tab = tabData.get(String(regId));
		const barcodes = tab && tab.available_barcodes ? tab.available_barcodes : [];

		if (rowIndex < 0 || rowIndex >= barcodes.length) {
			return null;
		}

		return barcodes[rowIndex];
	}

	function updateAssignableBarcodeDisplay() {
		if (!assignableBarcodeEl) {
			return;
		}

		if (!activeDetail || !isActiveRowPending()) {
			assignableBarcodeEl.textContent = '';
			return;
		}

		const assignable = getAssignableBarcode(activeDetail.regId, activeDetail.rowIndex);

		if (!assignable || !assignable.ean13) {
			assignableBarcodeEl.innerHTML = '<span class="text-muted">No barcode available for this product.</span>';
			return;
		}

		assignableBarcodeEl.innerHTML =
			'EAN-13 to assign: <strong class="procedure-assignable-barcode-value">' +
			escapeHtml(assignable.ean13) +
			'</strong>';
	}

	function openProductDetailModal(regId, rowIndex) {
		const tab = tabData.get(String(regId));

		if (!productModal || !tab) {
			return;
		}

		const row = tab.rows[rowIndex];

		if (!row) {
			return;
		}

		cancelBarcodeTyping();

		activeDetail = { regId: String(regId), rowIndex: rowIndex };
		selectGridRow(regId, rowIndex);

		const payload = buildRowPayload(tab, row, rowIndex);

		renderModalHeader(payload);
		renderDetailImages(payload.image_urls || []);
		renderDetailInfo(payload);

		if (procedureBarcodeInput) {
			procedureBarcodeInput.value = '';
			renderBarcodePreview('');
		}

		updateAssignableBarcodeDisplay();
		updateDetailNavButtons();

		const imageTabBtn = document.getElementById('procedureDetailTabImageBtn');

		if (imageTabBtn && typeof mdb !== 'undefined') {
			mdb.Tab.getOrCreateInstance(imageTabBtn).show();
		}

		productModal.show();
	}

	function navigateDetailRow(direction) {
		if (!activeDetail) {
			return;
		}

		const nextIndex = activeDetail.rowIndex + direction;

		if (nextIndex < 0 || nextIndex >= activeRowCount()) {
			return;
		}

		openProductDetailModal(activeDetail.regId, nextIndex);
	}

	function advanceToNextRowAfterReview() {
		if (!activeDetail) {
			return;
		}

		const regId = activeDetail.regId;
		const nextIndex = activeDetail.rowIndex + 1;
		const count = activeRowCount();

		clearActiveDetail();

		if (nextIndex < count) {
			openProductDetailModal(regId, nextIndex);
			return;
		}

		if (productModal) {
			productModal.hide();
		}
	}

	// --------------------------------------------------------- accept / reject

	function markRowRejected(regId, rowIndex, reason) {
		const tab = tabData.get(String(regId));

		if (!tab || !tab.rows[rowIndex]) {
			return;
		}

		tab.rows[rowIndex].item_status = 'rejected';
		tab.rows[rowIndex].message = reason;
		refreshGridRow(regId, rowIndex);
	}

	function markRowAccepted(regId, rowIndex) {
		const tab = tabData.get(String(regId));

		if (!tab || !tab.rows[rowIndex]) {
			return;
		}

		tab.rows[rowIndex].item_status = 'approved';
		tab.rows[rowIndex].message = '';
		refreshGridRow(regId, rowIndex);
	}

	function updateRowBarcodeCell(regId, rowIndex, ean13) {
		const tab = tabData.get(String(regId));
		const barcodeValue = String(ean13 || '').replace(/\D/g, '');

		if (!tab || !tab.rows[rowIndex] || !barcodeValue) {
			return;
		}

		const cellIndex = getBarcodeCellIndex(tab.columns || []);
		const cells = (tab.rows[rowIndex].cells || []).slice();

		while (cells.length <= cellIndex) {
			cells.push('');
		}

		cells[cellIndex] = barcodeValue;
		tab.rows[rowIndex].cells = cells;
		refreshGridRow(regId, rowIndex);
	}

	function acceptProduct() {
		if (!activeDetail || !isActiveRowPending()) {
			return;
		}

		const regId = activeDetail.regId;
		const rowIndex = activeDetail.rowIndex;
		const row = getActiveRow();
		const itemId = row ? row.id : null;

		if (!itemId) {
			showToast('Unable to accept this product.', 'error');
			return;
		}

		if (productDetailAcceptBtn) {
			productDetailAcceptBtn.disabled = true;
		}

		const assignable = getAssignableBarcode(regId, rowIndex);
		const formData = new FormData();

		if (assignable && assignable.ean13) {
			formData.append('barcode', assignable.ean13);
		}

		fetchApi(baseUrl + '/accept_item/' + itemId, {
			method: 'POST',
			body: formData,
		})
			.then(function (result) {
				const savedBarcode = result.data && result.data.barcode
					? result.data.barcode
					: (assignable && assignable.ean13 ? assignable.ean13 : '');

				markRowAccepted(regId, rowIndex);

				if (savedBarcode) {
					updateRowBarcodeCell(regId, rowIndex, savedBarcode);
				}

				showBarcodesTab();

				const barcodeValue = assignable && assignable.barcode ? assignable.barcode : '';

				typeBarcodeIntoInput(barcodeValue, function () {
					setTimeout(advanceToNextRowAfterReview, 2000);
				});

				showToast(result.message, 'success');
			})
			.catch(function (error) {
				showToast(error.message || 'Failed to accept product.', 'error');
			})
			.finally(function () {
				updateReviewButtonState();
			});
	}

	function openRejectModal() {
		const modal = ensureRejectModal();

		if (!modal || !activeDetail || !isActiveRowPending()) {
			return;
		}

		const row = getActiveRow();
		const productLabel = row
			? (row.product_procedure_number || 'Product #' + (activeDetail.rowIndex + 1))
			: 'this product';

		if (rejectModalMeta) {
			rejectModalMeta.textContent = 'Provide a reason for rejecting ' + productLabel + '.';
		}

		resetRejectModal();
		modal.show();

		if (rejectReasonInput) {
			rejectReasonInput.focus();
		}
	}

	function confirmRejectProduct() {
		if (!activeDetail) {
			return;
		}

		const reason = rejectReasonInput ? rejectReasonInput.value.trim() : '';

		if (!reason) {
			if (rejectReasonError) {
				rejectReasonError.classList.remove('d-none');
			}

			if (rejectReasonInput) {
				rejectReasonInput.focus();
			}

			return;
		}

		if (rejectReasonError) {
			rejectReasonError.classList.add('d-none');
		}

		const regId = activeDetail.regId;
		const rowIndex = activeDetail.rowIndex;
		const row = getActiveRow();
		const itemId = row ? row.id : null;

		if (!itemId) {
			showToast('Unable to reject this product.', 'error');
			return;
		}

		if (confirmRejectBtn) {
			confirmRejectBtn.disabled = true;
		}

		const formData = new FormData();
		formData.append('reason', reason);

		fetchApi(baseUrl + '/reject_item/' + itemId, {
			method: 'POST',
			body: formData,
		})
			.then(function (result) {
				const reasonText = result.data && result.data.message ? result.data.message : reason;

				if (rejectModal) {
					rejectModal.hide();
				}

				markRowRejected(regId, rowIndex, reasonText);
				advanceToNextRowAfterReview();
				showToast(result.message, 'success');
			})
			.catch(function (error) {
				showToast(error.message || 'Failed to reject product.', 'error');
			})
			.finally(function () {
				if (confirmRejectBtn) {
					confirmRejectBtn.disabled = false;
				}
			});
	}

	// ------------------------------------------------------- detail modal init

	function initProductModal() {
		if (typeof mdb === 'undefined' || !productModalEl) {
			return;
		}

		productModal = mdb.Modal.getOrCreateInstance(productModalEl);
		productModalEl.addEventListener('hidden.mdb.modal', function () {
			cancelBarcodeTyping();
			clearActiveDetail();
		});
		initDetailTabs();
	}

	function ensureRejectModal() {
		if (rejectModal || typeof mdb === 'undefined' || !rejectModalEl) {
			return rejectModal;
		}

		rejectModal = mdb.Modal.getOrCreateInstance(rejectModalEl);
		rejectModalEl.addEventListener('hidden.mdb.modal', resetRejectModal);
		return rejectModal;
	}

	function resetRejectModal() {
		if (rejectReasonInput) {
			rejectReasonInput.value = '';
		}

		if (rejectReasonError) {
			rejectReasonError.classList.add('d-none');
		}

		if (confirmRejectBtn) {
			confirmRejectBtn.disabled = false;
		}
	}

	function initDetailTabs() {
		if (typeof mdb === 'undefined' || !procedureDetailTabsEl) {
			return;
		}

		procedureDetailTabsEl.querySelectorAll('[data-mdb-tab-init]').forEach(function (element) {
			mdb.Tab.getOrCreateInstance(element);
		});
	}

	function ensureSimilarGrid() {
		if (similarGrid || typeof JqxGrid === 'undefined' || !document.getElementById('procedureSimilarGrid')) {
			if (similarGrid) {
				similarGrid.render();
			}
			return;
		}

		similarGrid = JqxGrid.local('#procedureSimilarGrid', {
			data: [],
			datafields: [
				{ name: 'product_procedure_number', type: 'string' },
				{ name: 'product_name', type: 'string' },
				{ name: 'match', type: 'string' },
			],
			columns: [
				{ text: '#', datafield: '__rownum', sortable: false, filterable: false, width: 60, cellsrenderer: JqxGrid.rowNumberRenderer },
				{ text: 'Product #', datafield: 'product_procedure_number', width: 160 },
				{ text: 'Product Name', datafield: 'product_name' },
				{ text: 'Match', datafield: 'match', width: 120 },
			],
			height: Math.max(280, window.innerHeight - 320),
			emptyMessage: 'No similar products found.',
		});
	}

	// ------------------------------------------------------------- modal header

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

	function renderDetailInfo(payload) {
		const fields = [];
		const columns = payload.columns || [];
		const cells = payload.cells || [];

		columns.forEach(function (column, index) {
			fields.push({ label: column, value: cells[index] ?? '' });
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

		productDetailInfo.innerHTML = '<div class="procedure-detail-info-grid">' + fieldsHtml + '</div>';
	}

	// ---------------------------------------------------------------- images

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

	// --------------------------------------------------------------- barcodes

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

	function cancelBarcodeTyping() {
		if (barcodeTypingTimer) {
			clearTimeout(barcodeTypingTimer);
			barcodeTypingTimer = null;
		}

		if (procedureBarcodeInput) {
			procedureBarcodeInput.classList.remove('is-typing-barcode');
		}
	}

	function typeBarcodeIntoInput(value, onComplete) {
		cancelBarcodeTyping();

		if (!procedureBarcodeInput) {
			if (typeof onComplete === 'function') {
				onComplete();
			}
			return;
		}

		const target = String(value || '').replace(/\D/g, '').slice(0, 12);

		procedureBarcodeInput.value = '';
		procedureBarcodeInput.classList.add('is-typing-barcode');
		renderBarcodePreview('');

		if (!target) {
			procedureBarcodeInput.classList.remove('is-typing-barcode');
			renderBarcodePreview('');

			if (typeof onComplete === 'function') {
				onComplete();
			}
			return;
		}

		let index = 0;

		function typeNextCharacter() {
			if (index >= target.length) {
				procedureBarcodeInput.classList.remove('is-typing-barcode');
				renderBarcodePreview(procedureBarcodeInput.value);

				if (typeof onComplete === 'function') {
					onComplete();
				}
				return;
			}

			procedureBarcodeInput.value += target.charAt(index);
			renderBarcodePreview(procedureBarcodeInput.value);
			index += 1;
			barcodeTypingTimer = setTimeout(typeNextCharacter, 45 + Math.floor(Math.random() * 35));
		}

		typeNextCharacter();
	}

	function showBarcodesTab() {
		const barcodesTabBtn = document.getElementById('procedureDetailTabBarcodesBtn');

		if (barcodesTabBtn && typeof mdb !== 'undefined') {
			mdb.Tab.getOrCreateInstance(barcodesTabBtn).show();
		}
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

	// ---------------------------------------------------------- import modal

	function initImportModal() {
		if (typeof mdb === 'undefined' || !importModalEl) {
			return;
		}

		importModal = mdb.Modal.getOrCreateInstance(importModalEl);
		importModalEl.addEventListener('shown.mdb.modal', openImportGrid);
		importModalEl.addEventListener('hidden.mdb.modal', resetImportModal);
	}

	function setImportLoading(isLoading) {
		if (importLoadingEl) {
			importLoadingEl.classList.toggle('d-none', !isLoading);
		}
	}

	function fileRenderer(row, columnfield, value) {
		return JqxGrid.htmlCellRenderer(
			'<i class="fas fa-file-zipper me-1 text-primary"></i>' + JqxGrid.escapeAttr(value)
		);
	}

	function createImportGrid() {
		importGrid = JqxGrid.remote('#procedureImportGrid', {
			url: baseUrl + '/import_list',
			root: 'registrations',
			pagesize: 10,
			pagesizeoptions: ['10', '20', '50'],
			height: Math.max(320, window.innerHeight - 240),
			emptyMessage: 'No completed procedures found.',
			datafields: [
				{ name: 'id', type: 'number' },
				{ name: 'file_name', type: 'string' },
				{ name: 'procedure_number', type: 'string' },
				{ name: 'organization_name', type: 'string' },
				{ name: 'processor_name', type: 'string' },
				{ name: 'total_products', type: 'number' },
				{ name: 'approved', type: 'number' },
				{ name: 'rejected', type: 'number' },
				{ name: 'created_at', type: 'string' },
			],
			columns: [
				{ text: '#', datafield: '__rownum', sortable: false, filterable: false, width: 60, cellsrenderer: JqxGrid.rowNumberRenderer },
				{ text: 'File', datafield: 'file_name', width: 240, cellsrenderer: fileRenderer },
				{ text: 'Procedure #', datafield: 'procedure_number', width: 130 },
				{ text: 'Organization', datafield: 'organization_name' },
				{ text: 'Processor', datafield: 'processor_name', width: 150 },
				{ text: 'Products', datafield: 'total_products', width: 100, cellsalign: 'right' },
				{ text: 'Approved', datafield: 'approved', width: 100, cellsalign: 'right' },
				{ text: 'Rejected', datafield: 'rejected', width: 100, cellsalign: 'right' },
				{ text: 'Completed', datafield: 'created_at', width: 160 },
			],
			getParams: function () {
				const search = importSearchInput ? importSearchInput.value.trim() : '';
				return search ? { q: search } : {};
			},
			onLoad: function () {
				setImportLoading(false);
			},
			onError: function () {
				setImportLoading(false);
				showToast('Failed to load completed procedures.', 'error');
			},
		});

		if (importGrid) {
			importGrid.$el.on('rowclick', function (event) {
				const args = event.args;
				const rowdata = args.row && args.row.bounddata
					? args.row.bounddata
					: importGrid.$el.jqxGrid('getrowdata', args.rowindex);

				if (rowdata && rowdata.id) {
					importRegistrationTab(rowdata.id);
				}
			});
		}
	}

	function openImportGrid() {
		if (!importGrid) {
			createImportGrid();
		} else {
			importGrid.gotoFirst();
			importGrid.reload();
		}
	}

	function resetImportModal() {
		scheduleImportSearch.cancel();

		if (importSearchInput) {
			importSearchInput.value = '';
		}

		setImportLoading(false);
	}

	const scheduleImportSearch = debounce(function () {
		if (importGrid) {
			importGrid.gotoFirst();
			importGrid.reload();
		}
	}, 300);

	function importRegistrationTab(registrationId) {
		if (!registrationId) {
			return;
		}

		if (procedureTabs.tabExists(registrationId)) {
			if (importModal) {
				importModal.hide();
			}

			procedureTabs.activateTab(registrationId);
			showToast('Procedure is already open.', 'success');
			return;
		}

		setImportLoading(true);

		fetchApi(baseUrl + '/import_tab/' + registrationId)
			.then(function (result) {
				procedureTabs.prependTabs([result.tab], { imported: true });

				if (importModal) {
					importModal.hide();
				}

				showToast(result.message || 'Procedure imported successfully.', 'success');
			})
			.catch(function (error) {
				showToast(error.message || 'Failed to import procedure.', 'error');
			})
			.finally(function () {
				setImportLoading(false);
			});
	}

	// --------------------------------------------------------------- tab html

	function buildTableHtml() {
		return '<div class="product-registration-grid"></div>';
	}

	function buildTabNavCloseButton(tab, isCompleted) {
		if (isCompleted) {
			return '<button type="button" class="procedure-tab-nav-close product-registration-tab-close-btn"' +
				' data-product-registration-id="' + tab.product_registration_id + '"' +
				' aria-label="Close tab"' +
				' data-mdb-ripple-init>' +
				'<i class="fas fa-times" aria-hidden="true"></i>' +
			'</button>';
		}

		return '<button type="button" class="procedure-tab-nav-close product-registration-tab-delete-btn"' +
			' data-product-registration-id="' + tab.product_registration_id + '"' +
			' data-file-name="' + escapeAttr(tab.file_name) + '"' +
			' aria-label="Stop procedure"' +
			' data-mdb-ripple-init>' +
			'<i class="fas fa-times" aria-hidden="true"></i>' +
		'</button>';
	}

	function buildTabButton(tab, isActive, isImported) {
		const isCompleted = isImported || tab.status === 'completed';

		return '<li class="nav-item procedure-tab-nav-item" role="presentation">' +
			'<div class="procedure-tab-nav-wrap">' +
				'<button class="nav-link' + (isActive ? ' active' : '') + '" ' +
					'id="product-registration-tab-' + tab.product_registration_id + '-tab" ' +
					'data-mdb-tab-init ' +
					'data-mdb-target="#product-registration-tab-' + tab.product_registration_id + '" ' +
					'type="button" role="tab" ' +
					'aria-controls="product-registration-tab-' + tab.product_registration_id + '" ' +
					'aria-selected="' + (isActive ? 'true' : 'false') + '" ' +
					'data-product-registration-id="' + tab.product_registration_id + '"' +
					(isCompleted ? ' data-completed="true"' : '') + '>' +
					'<i class="fas fa-file-zipper me-1"></i>' + escapeHtml(tab.file_name) +
					'<span class="badge bg-secondary ms-2">' + (tab.rows || []).length + '</span>' +
					(isCompleted
						? '<i class="fas fa-check ms-2 procedure-tab-completed-icon" aria-hidden="true"></i>'
						: '') +
				'</button>' +
				buildTabNavCloseButton(tab, isCompleted) +
			'</div>' +
		'</li>';
	}

	function buildTabActionButton(tab, isImported) {
		if (isImported) {
			return '<button type="button" class="btn btn-sm btn-outline-secondary ms-auto product-registration-tab-close-btn"' +
				'data-product-registration-id="' + tab.product_registration_id + '" ' +
				'data-mdb-ripple-init>' +
				'<i class="fas fa-times me-1"></i> Close' +
			'</button>';
		}

		return '<button type="button" class="btn btn-sm btn-outline-danger ms-auto product-registration-tab-delete-btn"' +
			'data-product-registration-id="' + tab.product_registration_id + '" ' +
			'data-file-name="' + escapeAttr(tab.file_name) + '" ' +
			'data-mdb-ripple-init>' +
			'<i class="fas fa-trash me-1"></i> Delete' +
		'</button>';
	}

	function buildTabPane(tab, isActive, isImported) {
		return '<div class="tab-pane fade' + (isActive ? ' show active' : '') + '" ' +
			'id="product-registration-tab-' + tab.product_registration_id + '" role="tabpanel" ' +
			'aria-labelledby="product-registration-tab-' + tab.product_registration_id + '-tab" ' +
			'data-product-registration-id="' + tab.product_registration_id + '" ' +
			(isImported ? ' data-imported="true"' : '') + '>' +
			'<div class="procedure-tab-meta d-flex flex-wrap gap-3 mb-3 small text-muted align-items-center">' +
				'<span><strong>Procedure #:</strong> ' + escapeHtml(tab.procedure_number) + '</span>' +
				'<span><strong>Organization:</strong> ' + escapeHtml(tab.organization_name) + '</span>' +
				'<span><strong>Processor:</strong> ' + escapeHtml(tab.processor_name || '') + '</span>' +
				'<span><strong>Status:</strong> ' + escapeHtml(tab.status || 'uploaded') + '</span>' +
				'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at || '') + '</span>' +
				buildTabActionButton(tab, isImported) +
			'</div>' +
			buildTableHtml(tab) +
		'</div>';
	}

	const procedureTabs = ProcedureTabs.create({
		tabsWrapper: tabsWrapper,
		tabsNav: tabsNav,
		tabsContent: tabsContent,
		initialUpload: initialUpload,
		openUploadBtn: openUploadBtn,
		tabCountEl: tabCount,
		idAttribute: ID_ATTR,
		getTabId: function (tab) {
			return tab.product_registration_id;
		},
		buildTabButton: buildTabButton,
		buildTabPane: buildTabPane,
		deleteUrl: function (registrationId) {
			return baseUrl + '/delete/' + registrationId;
		},
		deleteConfirm: {
			message: 'This procedure is not completed yet, will you stop procedure for this factory?',
			confirmLabel: 'Stop Procedure',
			errorMessage: 'Failed to delete procedure.',
		},
		deleteBtnSelector: '.product-registration-tab-delete-btn',
		closeBtnSelector: '.product-registration-tab-close-btn',
		onBeforePrepend: registerTabData,
		onAfterPrepend: function (tab) {
			ensureGrid(tab.product_registration_id);
		},
	});

	const procedureUpload = ProcedureUpload.create({
		forms: uploadForms,
		uploadModalEl: uploadModalEl,
		openUploadBtn: openUploadBtn,
		uploadUrl: baseUrl + '/upload',
		fileItemStyle: 'span',
		clearInputAfterSelect: true,
		uploadErrorMessage: 'An unexpected error occurred during upload.',
		hints: {
			empty: 'Select one or more zip files to continue.',
			selected: function (count) {
				return count + ' file(s) ready to upload.';
			},
			uploading: 'Uploading and processing files...',
		},
		normalizeFiles: function (fileList) {
			const files = Array.from(fileList || []).filter(function (file) {
				return /\.zip$/i.test(file.name);
			});

			if (!files.length && fileList && fileList.length) {
				showToast('Please select valid .zip files.', 'error');
				return null;
			}

			return files;
		},
		onSuccess: function (result) {
			procedureTabs.prependTabs(result.tabs || []);
		},
	});

	procedureUpload.init();
	procedureTabs.init();
	initImportModal();
	initProductModal();
	ensureRejectModal();

	// Build a tab's grid when it becomes visible (and the similar-products grid).
	document.addEventListener('shown.mdb.tab', function (event) {
		const tabButton = event.target.closest('[' + ID_ATTR + ']');

		if (tabButton && tabsNav && tabsNav.contains(tabButton)) {
			ensureGrid(tabButton.getAttribute(ID_ATTR));
		}

		if (event.target.id === 'procedureDetailTabSimilarBtn') {
			ensureSimilarGrid();
		}
	});

	if (confirmRejectBtn) {
		confirmRejectBtn.addEventListener('click', confirmRejectProduct);
	}

	if (productDetailRejectBtn) {
		productDetailRejectBtn.addEventListener('click', openRejectModal);
	}

	if (productDetailAcceptBtn) {
		productDetailAcceptBtn.addEventListener('click', acceptProduct);
	}

	if (rejectReasonInput) {
		rejectReasonInput.addEventListener('input', function () {
			if (rejectReasonError && rejectReasonInput.value.trim()) {
				rejectReasonError.classList.add('d-none');
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

	if (openImportBtn) {
		openImportBtn.addEventListener('click', function () {
			if (importModal) {
				importModal.show();
			}
		});
	}

	if (importSearchInput) {
		importSearchInput.addEventListener('input', scheduleImportSearch);
	}

	// Register server-rendered tabs and render the active one.
	(config.initialTabs || []).forEach(registerTabData);

	const activeId = activeRegistrationId();

	if (activeId) {
		ensureGrid(activeId);
	}
})();
