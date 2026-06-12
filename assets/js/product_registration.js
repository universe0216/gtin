(function () {
	'use strict';

	if (typeof window.PRODUCT_REGISTRATION_CONFIG === 'undefined') {
		return;
	}

	const config = window.PRODUCT_REGISTRATION_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const uploadForms = document.querySelectorAll('.procedure-upload-form');
	const uploadModalEl = document.getElementById('procedureUploadModal');
	const openUploadBtn = document.getElementById('btnOpenUploadModal');
	const openImportBtn = document.getElementById('btnOpenImportModal');
	const importModalEl = document.getElementById('procedureImportModal');
	const importLoadingEl = document.getElementById('procedureImportLoading');
	const importTableWrap = document.getElementById('procedureImportTableWrap');
	const importTableBody = document.getElementById('procedureImportTableBody');
	const importFooterMeta = document.getElementById('procedureImportFooterMeta');
	const importPaginationEl = document.getElementById('procedureImportPagination');
	const importPaginationList = document.getElementById('procedureImportPaginationList');
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
	let importListRequest = null;
	const importState = {
		page: 1,
		search: '',
		perPage: 10,
	};
	let productImageViewer = null;
	let productQrCodeInstance = null;
	let activeDetailRow = null;
	let barcodeTypingTimer = null;
	const tabAvailableBarcodes = new Map();
	const BARCODE_CELL_INDEX = 7;

	function initImportModal() {
		if (typeof mdb === 'undefined' || !importModalEl) {
			return;
		}

		importModal = mdb.Modal.getOrCreateInstance(importModalEl);
		importModalEl.addEventListener('show.mdb.modal', loadImportList);
		importModalEl.addEventListener('hidden.mdb.modal', resetImportModal);
	}

	function resetImportModal() {
		if (importListRequest) {
			importListRequest.abort();
			importListRequest = null;
		}

		scheduleImportSearch.cancel();

		importState.page = 1;
		importState.search = '';

		if (importSearchInput) {
			importSearchInput.value = '';
		}

		if (importLoadingEl) {
			importLoadingEl.classList.add('d-none');
		}

		if (importTableWrap) {
			importTableWrap.classList.remove('d-none');
		}

		if (importPaginationEl) {
			importPaginationEl.classList.add('d-none');
		}

		if (importPaginationList) {
			importPaginationList.innerHTML = '';
		}

		if (importFooterMeta) {
			importFooterMeta.textContent = '';
		}
	}

	function openImportModal() {
		if (importModal) {
			importModal.show();
		}
	}

	function setImportLoading(isLoading) {
		if (importLoadingEl) {
			importLoadingEl.classList.toggle('d-none', !isLoading);
		}

		if (importTableWrap) {
			importTableWrap.classList.toggle('d-none', isLoading);
		}
	}

	function renderImportList(registrations, meta) {
		if (!importTableBody) {
			return;
		}

		const rowOffset = meta && meta.range_start > 0 ? meta.range_start - 1 : 0;

		if (!registrations.length) {
			importTableBody.innerHTML =
				'<tr><td colspan="9" class="text-center text-muted py-4">No completed procedures found.</td></tr>';
			renderImportPagination(meta || {});
			return;
		}

		importTableBody.innerHTML = registrations.map(function (registration, index) {
			return '<tr class="procedure-import-row" role="button" tabindex="0" data-product-registration-id="' + registration.id + '">' +
				'<td class="procedure-row-index text-muted">' + (rowOffset + index + 1) + '</td>' +
				'<td><i class="fas fa-file-zipper me-1 text-primary"></i>' + escapeHtml(registration.file_name) + '</td>' +
				'<td>' + escapeHtml(registration.procedure_number) + '</td>' +
				'<td>' + escapeHtml(registration.organization_name) + '</td>' +
				'<td>' + escapeHtml(registration.processor_name || '') + '</td>' +
				'<td>' + escapeHtml(registration.total_products) + '</td>' +
				'<td>' + escapeHtml(registration.approved) + '</td>' +
				'<td>' + escapeHtml(registration.rejected) + '</td>' +
				'<td>' + escapeHtml(registration.created_at) + '</td>' +
			'</tr>';
		}).join('');

		renderImportPagination(meta || {});
	}

	function renderImportPagination(meta) {
		const total = meta.total || 0;
		const perPage = meta.per_page || importState.perPage;
		const rangeStart = meta.range_start || 0;
		const rangeEnd = meta.range_end || 0;

		if (importFooterMeta) {
			if (total > 0) {
				importFooterMeta.textContent = 'Showing ' + rangeStart + '\u2013' + rangeEnd + ' of ' + total;
			} else {
				importFooterMeta.textContent = importState.search
					? 'No results for "' + importState.search + '"'
					: 'No completed procedures found';
			}
		}

		Pagination.update({
			listEl: importPaginationList,
			wrapperEl: importPaginationEl,
			meta: meta,
			perPage: perPage,
			buttonClass: 'procedure-import-page-btn',
		});
	}

	function buildImportListUrl() {
		const params = new URLSearchParams();
		params.set('page', String(importState.page));

		if (importState.search) {
			params.set('q', importState.search);
		}

		return baseUrl + '/import_list?' + params.toString();
	}

	function loadImportList() {
		setImportLoading(true);

		importListRequest = createAbortableRequest(importListRequest);

		fetchApi(buildImportListUrl(), {
			signal: importListRequest.signal,
		})
			.then(function (result) {
				importState.page = result.page || 1;
				importState.perPage = result.per_page || importState.perPage;
				importState.search = result.search || importState.search;

				renderImportList(result.registrations || [], {
					total: result.total,
					page: result.page,
					per_page: result.per_page,
					total_pages: result.total_pages,
					range_start: result.range_start,
					range_end: result.range_end,
				});
			})
			.catch(function (error) {
				if (isAbortError(error)) {
					return;
				}

				showToast(error.message || 'Failed to load completed procedures.', 'error');
				renderImportList([], {});
			})
			.finally(function () {
				importListRequest = null;
				setImportLoading(false);
			});
	}

	const scheduleImportSearch = debounce(function () {
		importState.search = importSearchInput ? importSearchInput.value.trim() : '';
		importState.page = 1;
		loadImportList();
	}, 300);

	function importRegistrationTab(registrationId) {
		if (!registrationId) {
			return;
		}

		if (tabExists(registrationId)) {
			if (importModal) {
				importModal.hide();
			}

			activateTab(registrationId);
			showToast('Procedure is already open.', 'success');
			return;
		}

		setImportLoading(true);

		fetchApi(baseUrl + '/import_tab/' + registrationId)
			.then(function (result) {
				prependTabs([result.tab], { imported: true });

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

	function initProductModal() {
		if (typeof mdb === 'undefined' || !productModalEl) {
			return;
		}

		productModal = mdb.Modal.getOrCreateInstance(productModalEl);
		productModalEl.addEventListener('hidden.mdb.modal', function () {
			cancelBarcodeTyping();
			clearActiveDetailRow();
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

	function initRejectModal() {
		ensureRejectModal();
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

	function openRejectModal() {
		const modal = ensureRejectModal();

		if (!modal || !activeDetailRow || !isRowPending(activeDetailRow)) {
			return;
		}

		const payload = parseRowPayload(activeDetailRow);
		const productLabel = payload
			? (payload.product_procedure_number || 'Product #' + payload.row_index)
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

	function normalizeItemStatus(itemStatus) {
		const status = String(itemStatus || 'pending').toLowerCase();

		if (status === 'accepted') {
			return 'approved';
		}

		return status;
	}

	function isRowRejected(row) {
		return !!row && normalizeItemStatus(row.getAttribute('data-item-status')) === 'rejected';
	}

	function isRowApproved(row) {
		return !!row && normalizeItemStatus(row.getAttribute('data-item-status')) === 'approved';
	}

	function isRowPending(row) {
		return !!row && normalizeItemStatus(row.getAttribute('data-item-status')) === 'pending';
	}

	function formatItemStatusIcon(itemStatus) {
		const status = normalizeItemStatus(itemStatus);

		if (status === 'rejected') {
			return '<i class="fas fa-times procedure-item-status-icon procedure-item-status-icon-rejected" aria-label="Rejected"></i>';
		}

		if (status === 'approved') {
			return '<i class="fas fa-check procedure-item-status-icon procedure-item-status-icon-approved" aria-label="Accepted"></i>';
		}

		return '';
	}

	function renderItemStatusCell(itemStatus) {
		return '<td class="procedure-item-status-cell">' + formatItemStatusIcon(itemStatus) + '</td>';
	}

	function updateRowStatusCell(row, itemStatus) {
		const statusCell = row ? row.querySelector('.procedure-item-status-cell') : null;

		if (statusCell) {
			statusCell.innerHTML = formatItemStatusIcon(itemStatus);
		}
	}

	function buildProcedureDataRowClass(row) {
		const itemStatus = normalizeItemStatus(row.item_status || row.status || 'pending');
		let rowClass = 'procedure-data-row';

		if (itemStatus === 'rejected') {
			rowClass += ' procedure-data-row-rejected';
		} else if (itemStatus === 'approved') {
			rowClass += ' procedure-data-row-approved';
		}

		return rowClass;
	}

	function updateRowPayload(row, updates) {
		const payload = parseRowPayload(row);

		if (!payload) {
			return null;
		}

		Object.keys(updates).forEach(function (key) {
			payload[key] = updates[key];
		});
		row.setAttribute('data-row-payload', JSON.stringify(payload));
		return payload;
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

	function updateRowBarcodeCell(row, ean13) {
		if (!row) {
			return;
		}

		const barcodeValue = String(ean13 || '').replace(/\D/g, '');

		if (!barcodeValue) {
			return;
		}

		const payload = parseRowPayload(row);
		const cellIndex = getBarcodeCellIndex(payload ? payload.columns : []);
		const cells = payload ? (payload.cells || []).slice() : [];

		while (cells.length <= cellIndex) {
			cells.push('');
		}

		cells[cellIndex] = barcodeValue;

		const dataCells = row.querySelectorAll('td');
		const domIndex = cellIndex + 2;

		if (dataCells[domIndex]) {
			dataCells[domIndex].textContent = barcodeValue;
		}

		if (payload) {
			updateRowPayload(row, { cells: cells });
		}
	}

	function markDetailRowRejected(row, reason) {
		if (!row) {
			return;
		}

		row.classList.add('procedure-data-row-rejected');
		row.classList.remove('procedure-data-row-approved', 'procedure-data-row-active');
		row.setAttribute('data-item-status', 'rejected');
		updateRowPayload(row, {
			item_status: 'rejected',
			rejection_reason: reason,
		});
		updateRowStatusCell(row, 'rejected');
	}

	function markDetailRowAccepted(row) {
		if (!row) {
			return;
		}

		row.classList.remove('procedure-data-row-rejected', 'procedure-data-row-active');
		row.classList.add('procedure-data-row-approved');
		row.setAttribute('data-item-status', 'approved');
		updateRowPayload(row, {
			item_status: 'approved',
			rejection_reason: '',
		});
		updateRowStatusCell(row, 'approved');
	}

	function updateReviewButtonState(row) {
		const pending = isRowPending(row);

		if (productDetailRejectBtn) {
			productDetailRejectBtn.disabled = !pending;
			productDetailRejectBtn.classList.toggle('d-none', !pending);
		}

		if (productDetailAcceptBtn) {
			productDetailAcceptBtn.disabled = !pending;
			productDetailAcceptBtn.classList.toggle('d-none', !pending);
		}
	}

	function advanceToNextRowAfterReview(currentRow) {
		const rows = getActiveTabRows();
		const currentIndex = rows.indexOf(currentRow);
		const nextRow = currentIndex >= 0 && currentIndex < rows.length - 1 ? rows[currentIndex + 1] : null;

		activeDetailRow = null;
		updateDetailNavButtons();

		if (nextRow) {
			const payload = parseRowPayload(nextRow);

			if (payload) {
				openProductDetailModal(payload, nextRow);
				return;
			}
		}

		if (productModal) {
			productModal.hide();
		}
	}

	function confirmRejectProduct() {
		if (!activeDetailRow) {
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

		const itemId = activeDetailRow.getAttribute('data-id');

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
				const rejectedRow = activeDetailRow;
				const reasonText = result.data && result.data.message
					? result.data.message
					: reason;

				if (rejectModal) {
					rejectModal.hide();
				}

				markDetailRowRejected(rejectedRow, reasonText);
				advanceToNextRowAfterReview(rejectedRow);
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

	function acceptProduct() {
		if (!activeDetailRow || !isRowPending(activeDetailRow)) {
			return;
		}

		const itemId = activeDetailRow.getAttribute('data-id');

		if (!itemId) {
			showToast('Unable to accept this product.', 'error');
			return;
		}

		if (productDetailAcceptBtn) {
			productDetailAcceptBtn.disabled = true;
		}

		const assignable = getAssignableBarcodeForRow(activeDetailRow);
		const formData = new FormData();

		if (assignable && assignable.ean13) {
			formData.append('barcode', assignable.ean13);
		}

		fetchApi(baseUrl + '/accept_item/' + itemId, {
			method: 'POST',
			body: formData,
		})
			.then(function (result) {
				const acceptedRow = activeDetailRow;
				const savedBarcode = result.data && result.data.barcode
					? result.data.barcode
					: (assignable && assignable.ean13 ? assignable.ean13 : '');

				markDetailRowAccepted(acceptedRow);

				if (savedBarcode) {
					updateRowBarcodeCell(acceptedRow, savedBarcode);
				}

				showBarcodesTab();

				const barcodeValue = assignable && assignable.barcode ? assignable.barcode : '';

				typeBarcodeIntoInput(barcodeValue, function () {
					setTimeout(function () {
						advanceToNextRowAfterReview(acceptedRow);
					}, 2000);
				});

				showToast(result.message, 'success');
			})
			.catch(function (error) {
				showToast(error.message || 'Failed to accept product.', 'error');
			})
			.finally(function () {
				updateReviewButtonState(activeDetailRow);
			});
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

	function registerTabBarcodes(tab) {
		if (!tab || !tab.product_registration_id) {
			return;
		}

		tabAvailableBarcodes.set(String(tab.product_registration_id), tab.available_barcodes || []);
	}

	function getTabAvailableBarcodes(registrationId) {
		const key = String(registrationId || '');

		if (!key) {
			return [];
		}

		if (tabAvailableBarcodes.has(key)) {
			return tabAvailableBarcodes.get(key);
		}

		const pane = tabsContent
			? tabsContent.querySelector('.tab-pane[data-product-registration-id="' + key + '"]')
			: null;

		if (!pane) {
			return [];
		}

		const raw = pane.getAttribute('data-available-barcodes');

		if (!raw) {
			return [];
		}

		try {
			const parsed = JSON.parse(raw);
			tabAvailableBarcodes.set(key, Array.isArray(parsed) ? parsed : []);
			return tabAvailableBarcodes.get(key);
		} catch (error) {
			return [];
		}
	}

	function getActiveTabRegistrationId() {
		const activePane = tabsContent ? tabsContent.querySelector('.tab-pane.active') : null;

		return activePane ? activePane.getAttribute('data-product-registration-id') : null;
	}

	function getAssignableBarcodeForRow(row) {
		if (!row) {
			return null;
		}

		const rows = getActiveTabRows();
		const rowIndex = rows.indexOf(row);
		const barcodes = getTabAvailableBarcodes(getActiveTabRegistrationId());

		if (rowIndex < 0 || rowIndex >= barcodes.length) {
			return null;
		}

		return barcodes[rowIndex];
	}

	function updateAssignableBarcodeDisplay(row) {
		if (!assignableBarcodeEl) {
			return;
		}

		if (!row || !isRowPending(row)) {
			assignableBarcodeEl.textContent = '';
			return;
		}

		const assignable = getAssignableBarcodeForRow(row);

		if (!assignable || !assignable.ean13) {
			assignableBarcodeEl.innerHTML = '<span class="text-muted">No barcode available for this product.</span>';
			return;
		}

		assignableBarcodeEl.innerHTML =
			'EAN-13 to assign: <strong class="procedure-assignable-barcode-value">' +
			escapeHtml(assignable.ean13) +
			'</strong>';
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
			item_status: row.item_status || row.status || 'pending',
			rejection_reason: row.message || '',
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

		updateReviewButtonState(activeDetailRow);

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

		cancelBarcodeTyping();

		if (row) {
			setActiveDetailRow(row);
		}

		renderModalHeader(payload);
		renderDetailImages(payload.image_urls || []);
		renderDetailInfo(payload);

		if (procedureBarcodeInput) {
			procedureBarcodeInput.value = '';
			renderBarcodePreview('');
		}

		updateAssignableBarcodeDisplay(row);

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

	function buildTableHtml(tab) {
		const headerHtml = '<th class="procedure-item-status-col"></th>' +
			'<th class="procedure-row-index">#</th>' +
			(tab.columns || []).map(function (column) {
				return '<th>' + escapeHtml(column) + '</th>';
			}).join('');

		const bodyHtml = (tab.rows || []).map(function (row, index) {
			const payload = buildRowPayload(tab, row, index);
			const itemStatus = payload.item_status || 'pending';
			const cellsHtml = (row.cells || []).map(function (cell) {
				return '<td>' + escapeHtml(cell) + '</td>';
			}).join('');

			return '<tr class="' + buildProcedureDataRowClass(row) + '" data-id="' + row.id + '" data-item-status="' + escapeAttr(itemStatus) + '" data-product-number="' + escapeAttr(row.product_procedure_number) + '" data-row-payload="' + escapeAttr(JSON.stringify(payload)) + '">' +
				renderItemStatusCell(itemStatus) +
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
			'data-available-barcodes="' + escapeAttr(JSON.stringify(tab.available_barcodes || [])) + '"' +
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

	function tabExists(registrationId) {
		return !!tabsNav.querySelector('.nav-link[data-product-registration-id="' + registrationId + '"]');
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

		const button = tabsNav.querySelector('.nav-link[data-product-registration-id="' + registrationId + '"]');
		const pane = tabsContent.querySelector('.tab-pane[data-product-registration-id="' + registrationId + '"]');

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

	function deleteRegistration(registrationId) {
		return fetchApi(baseUrl + '/delete/' + registrationId, {
			method: 'POST',
		}).then(function (result) {
			removeTabFromDom(registrationId);
			showToast(result.message, 'success');
		});
	}

	function openDeleteModal(registrationId, fileName) {
		if (!registrationId) {
			return;
		}

		showConfirm({
			title: fileName || '—',
			message: 'This procedure is not completed yet, will you stop procedure for this factory?',
			confirmLabel: 'Stop Procedure',
		})
			.then(function () {
				return deleteRegistration(registrationId);
			})
			.catch(function (error) {
				if (isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || 'Failed to delete procedure.', 'error');
			});
	}

	function removeTabFromDom(registrationId) {
		const tabBtn = tabsNav.querySelector('.nav-link[data-product-registration-id="' + registrationId + '"]');
		const navItem = tabBtn ? tabBtn.closest('.nav-item') : null;
		const pane = tabsContent.querySelector('.tab-pane[data-product-registration-id="' + registrationId + '"]');
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
				activateTab(nextTab.getAttribute('data-product-registration-id'));
			}
		}

		updateTabCount();
	}

	function closeTab(registrationId) {
		removeTabFromDom(registrationId);
	}

	function prependTabs(tabs, options) {
		const isImported = !!(options && options.imported);

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
			return !tabExists(tab.product_registration_id);
		});

		newTabs.slice().reverse().forEach(function (tab) {
			registerTabBarcodes(tab);
			tabsNav.insertAdjacentHTML('afterbegin', buildTabButton(tab, false, isImported));
			tabsContent.insertAdjacentHTML('afterbegin', buildTabPane(tab, false, isImported));
		});

		initTabControls(tabsWrapper);

		if (newTabs.length) {
			activateTab(newTabs[0].product_registration_id);
		}

		updateTabCount();
	}

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
			prependTabs(result.tabs || []);
		},
	});

	procedureUpload.init();
	initImportModal();
	initProductModal();
	initRejectModal();

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

	document.addEventListener('click', function (event) {
		const closeBtn = event.target.closest('.product-registration-tab-close-btn');

		if (closeBtn) {
			event.preventDefault();
			event.stopPropagation();
			closeTab(closeBtn.getAttribute('data-product-registration-id'));
			return;
		}

		const deleteBtn = event.target.closest('.product-registration-tab-delete-btn');

		if (deleteBtn) {
			event.preventDefault();
			event.stopPropagation();
			openDeleteModal(
				deleteBtn.getAttribute('data-product-registration-id'),
				deleteBtn.getAttribute('data-file-name')
			);
			return;
		}

		const importPageBtn = event.target.closest('.procedure-import-page-btn');

		if (importPageBtn) {
			event.preventDefault();
			importState.page = parseInt(importPageBtn.getAttribute('data-page'), 10) || 1;
			loadImportList();
			return;
		}

		const importRow = event.target.closest('.procedure-import-row');

		if (importRow) {
			event.preventDefault();
			importRegistrationTab(importRow.getAttribute('data-product-registration-id'));
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}

		const importRow = event.target.closest('.procedure-import-row');

		if (!importRow) {
			return;
		}

		event.preventDefault();
		importRegistrationTab(importRow.getAttribute('data-product-registration-id'));
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

	if (openImportBtn) {
		openImportBtn.addEventListener('click', openImportModal);
	}

	if (importSearchInput) {
		importSearchInput.addEventListener('input', scheduleImportSearch);
	}

	(config.initialTabs || []).forEach(registerTabBarcodes);

	initTabControls(tabsWrapper);
})();
