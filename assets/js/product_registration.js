(function () {
	'use strict';

	if (typeof window.PRODUCT_REGISTRATION_CONFIG === 'undefined') {
		return;
	}

	const config = window.PRODUCT_REGISTRATION_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');
	const ID_ATTR = 'data-product-registration-id';
	const BARCODE_FIELD_COLUMNS = {
		barcode_1d_type: ['type of 1d barcode'],
		barcode_1d: ['1d barcode'],
		barcode_2d_type: ['type of 2d barcode'],
		barcode_2d: ['2d barcode'],
	};
	const BARCODE_1D_TYPES = ['EAN-13', 'EAN-8', 'UPC-A', 'UPC-E', 'ITF-14', 'GS1-128', 'Code 128', 'Code 39'];
	const BARCODE_2D_TYPES = ['QR', 'Data Matrix', 'GS1 DataMatrix', 'PDF417', 'Aztec'];

	const uploadForms = document.querySelectorAll('.procedure-upload-form');
	const uploadModalEl = document.getElementById('procedureUploadModal');
	const openUploadBtn = document.getElementById('btnOpenUploadModal');
	const openImportBtn = document.getElementById('btnOpenImportModal');
	const importModalEl = document.getElementById('procedureImportModal');
	const sendModalEl = document.getElementById('procedureSendModal');
	const sendUserList = document.getElementById('procedureSendUserList');
	const sendEmpty = document.getElementById('procedureSendEmpty');
	const sendLoading = document.getElementById('procedureSendLoading');
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
			icon = '<i class="fas fa-times procedure-item-status-icon procedure-item-status-icon-rejected" aria-label="' + t('reg_rejected') + '"></i>';
		} else if (status === 'approved') {
			icon = '<i class="fas fa-check procedure-item-status-icon procedure-item-status-icon-approved" aria-label="' + t('reg_accepted') + '"></i>';
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

	function normalizeColumnName(column) {
		return String(column || '').trim().toLowerCase().replace(/\s+/g, ' ');
	}

	function findColumnIndex(columns, names) {
		if (!Array.isArray(columns) || !Array.isArray(names)) {
			return -1;
		}

		const wanted = names.map(normalizeColumnName);

		for (let nameIndex = 0; nameIndex < wanted.length; nameIndex++) {
			for (let i = 0; i < columns.length; i++) {
				if (normalizeColumnName(columns[i]) === wanted[nameIndex]) {
					return i;
				}
			}
		}

		return -1;
	}

	function barcodeFieldForColumn(column) {
		const name = normalizeColumnName(column);
		const fields = Object.keys(BARCODE_FIELD_COLUMNS);

		for (let i = 0; i < fields.length; i++) {
			const aliases = BARCODE_FIELD_COLUMNS[fields[i]];

			for (let aliasIndex = 0; aliasIndex < aliases.length; aliasIndex++) {
				if (name === aliases[aliasIndex]) {
					return fields[i];
				}
			}
		}

		return '';
	}

	function getBarcodeCellIndex(columns) {
		return findColumnIndex(columns, ['1d barcode', 'barcode']);
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

		const spreadsheetColumns = tab.columns || [];
		const fixedNames = ['product name', 'standard'];
		const fixedIndexes = [];
		const scrollingIndexes = [];

		spreadsheetColumns.forEach(function (column, index) {
			datafields.push({ name: 'c' + index, type: 'string' });

			if (fixedNames.indexOf(normalizeColumnName(column)) !== -1) {
				fixedIndexes.push(index);
			} else {
				scrollingIndexes.push(index);
			}
		});

		fixedIndexes.sort(function (left, right) {
			return fixedNames.indexOf(normalizeColumnName(spreadsheetColumns[left]))
				- fixedNames.indexOf(normalizeColumnName(spreadsheetColumns[right]));
		});

		function pushSpreadsheetColumn(index, pinned) {
			columns.push({
				text: spreadsheetColumns[index],
				datafield: 'c' + index,
				pinned: pinned,
				width: pinned ? 200 : 160,
				cellclassname: rowStateClass,
			});
		}

		fixedIndexes.forEach(function (index) {
			pushSpreadsheetColumn(index, true);
		});
		scrollingIndexes.forEach(function (index) {
			pushSpreadsheetColumn(index, false);
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
			emptyMessage: t('reg_no_products'),
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

	function activeItemStatus() {
		const row = getActiveRow();
		return row ? normalizeItemStatus(row.item_status || row.status) : '';
	}

	function isActiveRowPending() {
		return activeItemStatus() === 'pending';
	}

	function canReviewActiveRow() {
		const tab = getActiveTab();

		if (!tab || tab.status === 'completed') {
			return false;
		}

		const status = activeItemStatus();
		return status === 'pending' || status === 'approved';
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
		const reviewable = canReviewActiveRow();

		if (productDetailRejectBtn) {
			productDetailRejectBtn.disabled = !reviewable;
			productDetailRejectBtn.classList.toggle('d-none', !reviewable);
		}

		if (productDetailAcceptBtn) {
			productDetailAcceptBtn.disabled = !reviewable;
			productDetailAcceptBtn.classList.toggle('d-none', !reviewable);
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
				? t('reg_row_position', { current: currentIndex + 1, count: count })
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
			assignableBarcodeEl.innerHTML = '<span class="text-muted">' + t('reg_barcode_none') + '</span>';
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

		flushBarcodeFieldSaves();
		cancelBarcodeTyping();

		activeDetail = { regId: String(regId), rowIndex: rowIndex };
		selectGridRow(regId, rowIndex);

		const payload = buildRowPayload(tab, row, rowIndex);

		renderModalHeader(payload);
		renderDetailImages(payload.image_urls || []);
		renderDetailInfo(payload);

		if (procedureBarcodeInput) {
			const barcodeValue = (barcodeFieldInput('barcode_1d') || { value: '' }).value;
			procedureBarcodeInput.value = barcodeValue;
			renderBarcodePreview(barcodeValue);
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

		if (cellIndex < 0) {
			return;
		}

		const cells = (tab.rows[rowIndex].cells || []).slice();

		while (cells.length <= cellIndex) {
			cells.push('');
		}

		cells[cellIndex] = barcodeValue;
		tab.rows[rowIndex].cells = cells;
		refreshGridRow(regId, rowIndex);

		if (productDetailInfo) {
			const input = productDetailInfo.querySelector('[data-barcode-field="barcode_1d"]');

			if (input && String(input.getAttribute('data-item-id')) === String(tab.rows[rowIndex].id)) {
				input.value = barcodeValue;
				input.setAttribute('data-saved-value', barcodeValue);
			}
		}
	}

	function normalizeBarcodeType(type) {
		return String(type || '').toLowerCase().replace(/[\s-]+/g, '');
	}

	function gs1CheckDigit(dataDigits) {
		const digits = String(dataDigits);
		let sum = 0;

		for (let i = 0; i < digits.length; i++) {
			const digit = parseInt(digits.charAt(digits.length - 1 - i), 10);
			sum += digit * (i % 2 === 0 ? 3 : 1);
		}

		return String((10 - (sum % 10)) % 10);
	}

	function gs1CheckValid(value) {
		if (!/^\d{2,}$/.test(value)) {
			return false;
		}

		return gs1CheckDigit(value.slice(0, -1)) === value.charAt(value.length - 1);
	}

	function upcEValid(value) {
		if (!/^[01]\d{7}$/.test(value)) {
			return false;
		}

		const numberSystem = value.charAt(0);
		const body = value.slice(1, 7);
		const check = value.charAt(7);
		const last = body.charAt(5);
		let manufacturer;
		let product;

		if (last === '0' || last === '1' || last === '2') {
			manufacturer = body.slice(0, 2) + last + '00';
			product = '00' + body.slice(2, 5);
		} else if (last === '3') {
			manufacturer = body.slice(0, 3) + '00';
			product = '000' + body.slice(3, 5);
		} else if (last === '4') {
			manufacturer = body.slice(0, 4) + '0';
			product = '0000' + body.charAt(4);
		} else {
			manufacturer = body.slice(0, 5);
			product = '0000' + last;
		}

		return gs1CheckDigit(numberSystem + manufacturer + product) === check;
	}

	function barcode1dMatches(typeKey, value) {
		switch (typeKey) {
			case 'ean13':
				return /^\d{13}$/.test(value) && gs1CheckValid(value);
			case 'ean8':
				return /^\d{8}$/.test(value) && gs1CheckValid(value);
			case 'upca':
				return /^\d{12}$/.test(value) && gs1CheckValid(value);
			case 'upce':
				return upcEValid(value);
			case 'itf14':
				return /^\d{14}$/.test(value) && gs1CheckValid(value);
			case 'gs1128':
				return /^(\(\d{2,4}\)[^\s()]{1,90})+$/.test(value) || /^\d{4,48}$/.test(value);
			case 'code128':
				return /^[\x20-\x7E]{1,80}$/.test(value) && value.trim() !== '';
			case 'code39':
				return /^[0-9A-Z\-. $\/+%]{1,80}$/.test(value) && value.trim() !== '';
			default:
				return false;
		}
	}

	function barcode1dProblem(type, value) {
		if (!value) {
			return { code: 'empty' };
		}

		const typeKey = normalizeBarcodeType(type);

		if (!typeKey) {
			return { code: 'type' };
		}

		if (!barcode1dMatches(typeKey, value)) {
			return { code: 'format', type: type, typeKey: typeKey };
		}

		return null;
	}

	function barcodeFieldInput(field) {
		return productDetailInfo
			? productDetailInfo.querySelector('[data-barcode-field="' + field + '"]')
			: null;
	}

	function barcodeAcceptMessage(problem) {
		if (!problem || problem.code === 'empty') {
			return t('reg_barcode_1d_required');
		}

		if (problem.code === 'type') {
			return t('reg_barcode_1d_type_required');
		}

		const hintKeys = {
			ean13: 'reg_barcode_hint_ean13',
			ean8: 'reg_barcode_hint_ean8',
			upca: 'reg_barcode_hint_upca',
			upce: 'reg_barcode_hint_upce',
			itf14: 'reg_barcode_hint_itf14',
			gs1128: 'reg_barcode_hint_gs1128',
			code128: 'reg_barcode_hint_code128',
			code39: 'reg_barcode_hint_code39',
		};

		return t('reg_barcode_1d_format', {
			type: problem.type,
			hint: t(hintKeys[problem.typeKey] || 'reg_barcode_hint_unknown'),
		});
	}

	function clearBarcodeFieldInvalid() {
		if (!productDetailInfo) {
			return;
		}

		productDetailInfo.querySelectorAll('.is-invalid').forEach(function (input) {
			input.classList.remove('is-invalid');
		});
	}

	function rejectAcceptBarcode(problem) {
		clearBarcodeFieldInvalid();

		const field = problem && problem.code === 'type' ? 'barcode_1d_type' : 'barcode_1d';
		const input = barcodeFieldInput(field);

		if (input) {
			input.classList.add('is-invalid');
		}

		const notify = window.showAlert
			? showAlert({ message: barcodeAcceptMessage(problem), variant: 'danger' })
			: Promise.resolve(window.alert(barcodeAcceptMessage(problem)));

		notify.then(function () {
			if (input) {
				input.focus();
			}
		});
	}

	function acceptProduct() {
		if (!activeDetail || !canReviewActiveRow()) {
			return;
		}

		const regId = activeDetail.regId;
		const rowIndex = activeDetail.rowIndex;
		const row = getActiveRow();
		const itemId = row ? row.id : null;

		if (!itemId) {
			showToast(t('reg_unable_accept'), 'error');
			return;
		}

		const barcodeType = (barcodeFieldInput('barcode_1d_type') || { value: '' }).value.trim();
		const barcodeValue = (barcodeFieldInput('barcode_1d') || { value: '' }).value.trim();
		const problem = barcode1dProblem(barcodeType, barcodeValue);

		if (problem) {
			rejectAcceptBarcode(problem);
			return;
		}

		clearBarcodeFieldInvalid();

		if (productDetailAcceptBtn) {
			productDetailAcceptBtn.disabled = true;
		}

		const assignable = getAssignableBarcode(regId, rowIndex);
		const formData = new FormData();
		formData.append('barcode_1d_type', barcodeType);
		formData.append('barcode_1d', barcodeValue);

		if (assignable && assignable.ean13) {
			formData.append('barcode', assignable.ean13);
		}

		['barcode_1d_type', 'barcode_1d'].forEach(function (field) {
			const key = String(itemId) + ':' + field;
			clearTimeout(barcodeSaveTimers.get(key));
			barcodeSaveTimers.delete(key);
			applyBarcodeFieldLocally(
				regId,
				itemId,
				field,
				field === 'barcode_1d' ? barcodeValue : barcodeType
			);
		});

		barcodeSaveQueue = barcodeSaveQueue.then(function () {
			return fetchApi(baseUrl + '/accept_item/' + itemId, {
				method: 'POST',
				body: formData,
			});
		})
			.then(function (result) {
				['barcode_1d_type', 'barcode_1d'].forEach(function (field) {
					const input = barcodeFieldInput(field);

					if (input && String(input.getAttribute('data-item-id')) === String(itemId)) {
						input.setAttribute('data-saved-value', input.value.trim());
					}
				});

				markRowAccepted(regId, rowIndex);
				setRegistrationStatus(regId, result.data && result.data.registration_status);
				showBarcodesTab();

				const previewValue = assignable && assignable.barcode ? assignable.barcode : '';

				typeBarcodeIntoInput(previewValue, function () {
					setTimeout(advanceToNextRowAfterReview, 2000);
				});

				showToast(result.message, 'success');
			})
			.catch(function (error) {
				const message = error.message || t('reg_accept_failed');

				if (error.response && error.response.status === 422) {
					showAlert({ message: message, variant: 'danger' });
				} else {
					showToast(message, 'error');
				}
			})
			.finally(function () {
				updateReviewButtonState();
			});
	}

	function openRejectModal() {
		const modal = ensureRejectModal();

		if (!modal || !activeDetail || !canReviewActiveRow()) {
			return;
		}

		const row = getActiveRow();
		const productLabel = row
			? (row.product_procedure_number || 'Product #' + (activeDetail.rowIndex + 1))
			: 'this product';

		if (rejectModalMeta) {
			rejectModalMeta.textContent = t('reg_reject_meta', { product: productLabel });
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
			showToast(t('reg_unable_reject'), 'error');
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
				setRegistrationStatus(regId, result.data && result.data.registration_status);
				advanceToNextRowAfterReview();
				showToast(result.message, 'success');
			})
			.catch(function (error) {
				showToast(error.message || t('reg_reject_failed'), 'error');
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
			flushBarcodeFieldSaves();
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
				{ text: t('reg_product_number'), datafield: 'product_procedure_number', width: 160 },
				{ text: t('reg_product_name'), datafield: 'product_name' },
				{ text: t('reg_match'), datafield: 'match', width: 120 },
			],
			height: Math.max(280, window.innerHeight - 320),
			emptyMessage: t('reg_no_similar'),
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

	function barcodeTypeOptions(field, currentValue) {
		const options = (field === 'barcode_2d_type' ? BARCODE_2D_TYPES : BARCODE_1D_TYPES).slice();
		const value = String(currentValue || '');

		if (value && options.indexOf(value) === -1) {
			options.unshift(value);
		}

		return options;
	}

	function renderBarcodeFieldControl(field, value, editable, itemId, regId) {
		const inputId = 'procedureBarcodeField_' + field;
		const saved = escapeAttr(value);
		const disabled = editable ? '' : ' disabled';
		const dataAttrs =
			' id="' + inputId + '"' +
			' data-barcode-field="' + field + '"' +
			' data-item-id="' + escapeAttr(itemId) + '"' +
			' data-reg-id="' + escapeAttr(regId) + '"' +
			' data-saved-value="' + saved + '"' +
			disabled;

		if (field === 'barcode_1d' || field === 'barcode_2d') {
			const input = '<input type="text" maxlength="255" class="form-control form-control-sm procedure-detail-info-input"' +
				dataAttrs + ' value="' + saved + '">';

			if (field === 'barcode_1d' && editable) {
				return '<div class="procedure-barcode-1d-control">' +
					input +
					'<button type="button" class="btn btn-sm btn-outline-primary procedure-barcode-auto-assign">' +
						escapeHtml(t('reg_auto_assign')) +
					'</button>' +
				'</div>';
			}

			return input;
		}

		const options = barcodeTypeOptions(field, value).map(function (option) {
			const selected = option === String(value || '') ? ' selected' : '';
			return '<option value="' + escapeAttr(option) + '"' + selected + '>' + escapeHtml(option) + '</option>';
		}).join('');

		return '<select class="form-select form-select-sm procedure-detail-info-input"' + dataAttrs + '>' +
			'<option value=""></option>' +
			options +
		'</select>';
	}

	const barcodeSaveTimers = new Map();
	let barcodeSaveQueue = Promise.resolve();

	function applyBarcodeFieldLocally(regId, itemId, field, value) {
		const tab = tabData.get(String(regId));

		if (!tab) {
			return;
		}

		const rowIndex = (tab.rows || []).findIndex(function (row) {
			return String(row.id) === String(itemId);
		});

		if (rowIndex < 0) {
			return;
		}

		const cellIndex = findColumnIndex(tab.columns || [], BARCODE_FIELD_COLUMNS[field] || []);

		if (cellIndex < 0) {
			return;
		}

		const cells = (tab.rows[rowIndex].cells || []).slice();

		while (cells.length <= cellIndex) {
			cells.push('');
		}

		cells[cellIndex] = value;
		tab.rows[rowIndex].cells = cells;
		refreshGridRow(regId, rowIndex);
	}

	function saveBarcodeField(input) {
		const field = input.getAttribute('data-barcode-field');
		const itemId = input.getAttribute('data-item-id');
		const regId = input.getAttribute('data-reg-id');
		const value = input.value;

		if (!field || !itemId || value === (input.getAttribute('data-saved-value') || '')) {
			return;
		}

		applyBarcodeFieldLocally(regId, itemId, field, value);

		if (field === 'barcode_1d' && procedureBarcodeInput && !procedureBarcodeInput.classList.contains('is-typing-barcode')) {
			procedureBarcodeInput.value = value;
			renderBarcodePreview(value);
			showBarcodesTab();
		}

		const formData = new FormData();
		formData.append(field, value);

		barcodeSaveQueue = barcodeSaveQueue.then(function () {
			return fetchApi(baseUrl + '/update_item_barcodes/' + itemId, {
				method: 'POST',
				body: formData,
			});
		}).then(function () {
			if (input.getAttribute('data-saved-value') !== null && input.value === value) {
				input.setAttribute('data-saved-value', value);
			}
		}).catch(function (error) {
			showToast(error.message || t('reg_barcode_save_failed'), 'error');
		});
	}

	function reservedBarcodes(exceptItemId) {
		const tab = getActiveTab();
		const values = [];

		if (!tab) {
			return values;
		}

		const cellIndex = findColumnIndex(tab.columns || [], ['1d barcode', 'barcode']);

		if (cellIndex < 0) {
			return values;
		}

		(tab.rows || []).forEach(function (row) {
			if (String(row.id) === String(exceptItemId)) {
				return;
			}

			const value = String((row.cells || [])[cellIndex] || '').trim();

			if (value) {
				values.push(value);
			}
		});

		return values;
	}

	function autoAssignBarcode(button) {
		const typeInput = barcodeFieldInput('barcode_1d_type');
		const valueInput = barcodeFieldInput('barcode_1d');
		const barcodeType = typeInput ? typeInput.value.trim() : '';

		if (!valueInput || !activeDetail) {
			return;
		}

		if (!barcodeType) {
			showAlert({
				message: t('reg_barcode_1d_type_required'),
				variant: 'danger',
			});
			return;
		}

		const itemId = valueInput.getAttribute('data-item-id');
		const saveKey = (itemId || '') + ':barcode_1d';
		const formData = new FormData();

		formData.append('barcode_1d_type', barcodeType);
		reservedBarcodes(itemId).forEach(function (barcode) {
			formData.append('reserved[]', barcode);
		});

		button.disabled = true;
		clearTimeout(barcodeSaveTimers.get(saveKey));
		barcodeSaveTimers.delete(saveKey);

		fetchApi(baseUrl + '/auto_assign_barcode/' + itemId, {
			method: 'POST',
			body: formData,
		}).then(function (result) {
			const barcode = result.data && result.data.barcode ? result.data.barcode : '';

			if (!barcode || valueInput.getAttribute('data-item-id') !== itemId) {
				return;
			}

			valueInput.value = barcode;
			valueInput.classList.remove('is-invalid');
			saveBarcodeField(valueInput);
		}).catch(function (error) {
			showAlert({
				message: error.message || t('reg_barcode_save_failed'),
				variant: 'danger',
			});
		}).finally(function () {
			button.disabled = false;
		});
	}

	function scheduleBarcodeFieldSave(input) {
		const key = (input.getAttribute('data-item-id') || '') + ':' + (input.getAttribute('data-barcode-field') || '');
		const delay = input.tagName === 'SELECT' ? 0 : 400;

		clearTimeout(barcodeSaveTimers.get(key));
		barcodeSaveTimers.set(key, setTimeout(function () {
			barcodeSaveTimers.delete(key);
			saveBarcodeField(input);
		}, delay));
	}

	function flushBarcodeFieldSaves() {
		const pendingKeys = [];

		barcodeSaveTimers.forEach(function (timer, key) {
			clearTimeout(timer);
			pendingKeys.push(key);
		});
		barcodeSaveTimers.clear();

		if (!productDetailInfo) {
			return;
		}

		productDetailInfo.querySelectorAll('[data-barcode-field]').forEach(function (input) {
			const key = (input.getAttribute('data-item-id') || '') + ':' + (input.getAttribute('data-barcode-field') || '');

			if (pendingKeys.indexOf(key) !== -1) {
				saveBarcodeField(input);
			}
		});
	}

	function renderDetailInfo(payload) {
		const columns = payload.columns || [];
		const cells = payload.cells || [];
		const editable = payload.status !== 'completed';
		const fields = [];

		columns.forEach(function (column, index) {
			fields.push({
				label: column,
				value: cells[index] ?? '',
				field: barcodeFieldForColumn(column),
			});
		});

		fields.push(
			{ label: t('reg_procedure'), value: payload.procedure_number || '', field: '' },
			{ label: t('reg_zip_file'), value: payload.file_name || '', field: '' },
			{ label: t('reg_processor'), value: payload.processor_name || '', field: '' },
			{ label: t('reg_uploaded'), value: payload.created_at || '', field: '' }
		);

		const fieldsHtml = fields.map(function (field) {
			const control = field.field
				? renderBarcodeFieldControl(field.field, field.value, editable, payload.id, activeDetail ? activeDetail.regId : '')
				: '<span class="procedure-detail-info-value">' + escapeHtml(field.value) + '</span>';
			const labelTag = field.field ? 'label' : 'span';
			const labelFor = field.field ? ' for="procedureBarcodeField_' + field.field + '"' : '';

			return '<div class="procedure-detail-info-item">' +
				'<' + labelTag + ' class="procedure-detail-info-label"' + labelFor + '>' + escapeHtml(field.label) + '</' + labelTag + '>' +
				control +
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
			procedureBarcodeEmpty.textContent = t('reg_barcode_empty');
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

	function currentBarcode1dType() {
		const input = barcodeFieldInput('barcode_1d_type');

		return input ? input.value.trim() : '';
	}

	function barcodePreviewSpec(type, value) {
		const typeKey = normalizeBarcodeType(type);
		const text = String(value || '').trim();
		const formats = {
			ean13: 'EAN13',
			ean8: 'EAN8',
			upca: 'UPC',
			upce: 'UPCE',
			itf14: 'ITF14',
			code128: 'CODE128',
			code39: 'CODE39',
			gs1128: 'CODE128',
		};
		const bodyLengths = { ean13: 12, ean8: 7, upca: 11, itf14: 13 };
		const format = formats[typeKey];

		if (!format || !text) {
			return null;
		}

		const valid = barcode1dMatches(typeKey, text);
		const bodyReady = !!bodyLengths[typeKey] && new RegExp('^\\d{' + bodyLengths[typeKey] + '}$').test(text);

		if (!valid && !bodyReady) {
			return null;
		}

		if (typeKey === 'gs1128') {
			return {
				format: format,
				value: text.replace(/[()]/g, ''),
				text: text,
				ean128: true,
			};
		}

		return {
			format: format,
			value: text,
			wideText: typeKey === 'ean13' || typeKey === 'ean8' || typeKey === 'upca' || typeKey === 'upce' || typeKey === 'itf14',
		};
	}

	function barcodeValueStillShort(type, value) {
		const fullLengths = { ean13: 13, ean8: 8, upca: 12, upce: 8, itf14: 14 };
		const fullLength = fullLengths[normalizeBarcodeType(type)];

		return !!fullLength && String(value || '').length < fullLength;
	}

	function renderBarcodePreview(value) {
		const barcodeValue = (value || '').trim();
		const barcodeType = currentBarcode1dType();
		const spec = barcodePreviewSpec(barcodeType, barcodeValue);

		resetBarcodePreview();

		if (procedureBarcodeFullValue) {
			if (barcodeType && barcodeValue) {
				procedureBarcodeFullValue.textContent = t('reg_barcode_typed', {
					type: barcodeType,
					value: barcodeValue,
				});
			} else if (barcodeType) {
				procedureBarcodeFullValue.textContent = t('reg_barcode_typed_empty', { type: barcodeType });
			} else {
				procedureBarcodeFullValue.textContent = t('reg_barcode_full');
			}
		}

		if (!barcodeValue) {
			return;
		}

		if (typeof JsBarcode !== 'undefined' && procedureBarcodeSvg && spec) {
			try {
				const options = {
					format: spec.format,
					lineColor: '#000000',
					background: '#ffffff',
					width: 2,
					height: 72,
					displayValue: true,
					font: 'Courier New, monospace',
					fontSize: 16,
					textMargin: 6,
					margin: 8,
				};

				if (spec.text) {
					options.text = spec.text;
				}

				if (spec.ean128) {
					options.ean128 = true;
				}

				JsBarcode(procedureBarcodeSvg, spec.value, options);

				if (spec.wideText) {
					applyBarcodeTextSpacing(procedureBarcodeSvg);
				}

				procedureBarcodeSvg.classList.remove('d-none');

				if (procedureBarcodeEmpty) {
					procedureBarcodeEmpty.classList.add('d-none');
				}
			} catch (error) {
				if (procedureBarcodeEmpty) {
					procedureBarcodeEmpty.textContent = t('reg_barcode_render_failed');
					procedureBarcodeEmpty.classList.remove('d-none');
				}
			}
		} else if (procedureBarcodeEmpty && barcodeType && !barcodeValueStillShort(barcodeType, barcodeValue)) {
			procedureBarcodeEmpty.textContent = t('reg_barcode_render_failed');
			procedureBarcodeEmpty.classList.remove('d-none');
		}

		const qrText = spec ? (spec.text || spec.value) : '';

		if (typeof QRCode !== 'undefined' && procedureQrCode && qrText) {
			procedureQrCode.innerHTML = '';
			productQrCodeInstance = new QRCode(procedureQrCode, {
				text: qrText,
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
			emptyMessage: t('reg_no_completed'),
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
				{ text: t('reg_file'), datafield: 'file_name', width: 240, cellsrenderer: fileRenderer },
				{ text: t('reg_procedure'), datafield: 'procedure_number', width: 130 },
				{ text: t('reg_organization'), datafield: 'organization_name' },
				{ text: t('reg_processor'), datafield: 'processor_name', width: 150 },
				{ text: t('reg_products'), datafield: 'total_products', width: 100, cellsalign: 'right' },
				{ text: t('reg_approved'), datafield: 'approved', width: 100, cellsalign: 'right' },
				{ text: t('reg_rejected'), datafield: 'rejected', width: 100, cellsalign: 'right' },
				{ text: t('reg_completed'), datafield: 'created_at', width: 160 },
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
				showToast(t('reg_load_completed_failed'), 'error');
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
			showToast(t('reg_already_open'), 'success');
			return;
		}

		setImportLoading(true);

		fetchApi(baseUrl + '/import_tab/' + registrationId)
			.then(function (result) {
				procedureTabs.prependTabs([result.tab], { imported: true });

				if (importModal) {
					importModal.hide();
				}

				showToast(result.message || t('reg_imported'), 'success');
			})
			.catch(function (error) {
				showToast(error.message || t('reg_import_failed'), 'error');
			})
			.finally(function () {
				setImportLoading(false);
			});
	}

	// --------------------------------------------------------------- tab html

	function buildTableHtml() {
		return '<div class="product-registration-grid"></div>';
	}

	function buildFactoryAlert(tab) {
		if (!tab || !Array.isArray(tab.unregistered_factories) || !tab.unregistered_factories.length) {
			return '';
		}

		return '<div class="procedure-factory-alert" role="alert">' +
			'<i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>' +
			escapeHtml(t('reg_factory_not_registered')) +
		'</div>';
	}

	function buildTabNavCloseButton(tab, isCompleted) {
		if (isCompleted) {
			return '<button type="button" class="procedure-tab-nav-close product-registration-tab-close-btn"' +
				' data-product-registration-id="' + tab.product_registration_id + '"' +
				' aria-label="' + t('reg_close_tab') + '"' +
				' data-mdb-ripple-init>' +
				'<i class="fas fa-times" aria-hidden="true"></i>' +
			'</button>';
		}

		return '<button type="button" class="procedure-tab-nav-close product-registration-tab-delete-btn"' +
			' data-product-registration-id="' + tab.product_registration_id + '"' +
			' data-file-name="' + escapeAttr(tab.file_name) + '"' +
			' aria-label="' + t('reg_stop_procedure') + '"' +
			' data-mdb-ripple-init>' +
			'<i class="fas fa-times" aria-hidden="true"></i>' +
		'</button>';
	}

	function buildTabButton(tab, isActive, isImported) {
		const isCompleted = isImported || tab.status === 'completed';
		const showSavedCheck = isCompleted || String(tab.status || '').toLowerCase() === 'saved';

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
					'<i class="fas ' + (/\.(xls|xlsx)$/i.test(tab.file_name || '') ? 'fa-file-excel' : 'fa-file-zipper') + ' me-1"></i>' + escapeHtml(tab.file_name) +
					'<span class="badge bg-secondary ms-2">' + (tab.rows || []).length + '</span>' +
					(showSavedCheck
						? '<i class="fas fa-check ms-2 procedure-tab-completed-icon" aria-hidden="true"></i>'
						: '') +
				'</button>' +
				buildTabNavCloseButton(tab, isCompleted) +
			'</div>' +
		'</li>';
	}

	function setRegistrationStatus(registrationId, status) {
		const nextStatus = String(status || '');

		if (!nextStatus) {
			return;
		}

		const key = String(registrationId);
		const tab = tabData.get(key);

		if (tab) {
			tab.status = nextStatus;
		}

		const pane = paneFor(key);
		const statusEl = pane ? pane.querySelector('.procedure-tab-status-value') : null;

		if (statusEl) {
			statusEl.textContent = nextStatus;
		}
	}

	function markRegistrationSaved(registrationId, status) {
		const key = String(registrationId);
		const savedStatus = status || 'completed';
		const tab = tabData.get(key);

		if (tab) {
			tab.status = savedStatus;
		}

		const pane = paneFor(key);
		const statusEl = pane ? pane.querySelector('.procedure-tab-status-value') : null;

		if (statusEl) {
			statusEl.textContent = savedStatus;
		}

		const tabButton = document.getElementById('product-registration-tab-' + key + '-tab');

		if (tabButton) {
			tabButton.setAttribute('data-completed', 'true');

			if (!tabButton.querySelector('.procedure-tab-completed-icon')) {
				const icon = document.createElement('i');
				icon.className = 'fas fa-check ms-2 procedure-tab-completed-icon';
				icon.setAttribute('aria-hidden', 'true');
				tabButton.appendChild(icon);
			}
		}

		document.querySelectorAll('.product-registration-tab-delete-btn[' + ID_ATTR + '="' + key + '"]').forEach(function (button) {
			button.classList.remove('product-registration-tab-delete-btn', 'btn-outline-danger');
			button.classList.add('product-registration-tab-close-btn');
			button.removeAttribute('data-file-name');

			if (button.classList.contains('procedure-tab-nav-close')) {
				button.setAttribute('aria-label', t('reg_close_tab'));
				return;
			}

			button.classList.add('btn-outline-secondary');
			button.innerHTML = '<i class="fas fa-times me-1"></i> ' + escapeHtml(t('common_close'));
		});

		if (activeDetail && String(activeDetail.regId) === key && productModalTitle) {
			const badge = productModalTitle.querySelector('.badge');

			if (badge) {
				badge.textContent = savedStatus;
			}

			updateReviewButtonState();
		}
	}

	function rowIndexForItem(registrationId, itemId) {
		const tab = tabData.get(String(registrationId));

		if (!tab) {
			return -1;
		}

		return (tab.rows || []).findIndex(function (row) {
			return String(row.id) === String(itemId);
		});
	}

	function syncOpenBarcodeInputs(itemId, typeValue, barcodeValue) {
		const typeInput = barcodeFieldInput('barcode_1d_type');
		const valueInput = barcodeFieldInput('barcode_1d');

		if (typeInput && typeInput.getAttribute('data-item-id') === String(itemId)) {
			typeInput.value = typeValue;
			typeInput.setAttribute('data-saved-value', typeValue);
		}

		if (valueInput && valueInput.getAttribute('data-item-id') === String(itemId)) {
			valueInput.value = barcodeValue;
			valueInput.setAttribute('data-saved-value', barcodeValue);

			if (procedureBarcodeInput) {
				procedureBarcodeInput.value = barcodeValue;
				renderBarcodePreview(barcodeValue);
			}

			showBarcodesTab();
		}
	}

	function applyAutoProcessResult(registrationId, data) {
		const payload = data || {};

		(payload.assigned || []).forEach(function (item) {
			applyBarcodeFieldLocally(registrationId, item.id, 'barcode_1d_type', 'EAN-13');
			applyBarcodeFieldLocally(registrationId, item.id, 'barcode_1d', item.barcode);
			syncOpenBarcodeInputs(item.id, 'EAN-13', item.barcode);

			const rowIndex = rowIndexForItem(registrationId, item.id);

			if (rowIndex >= 0) {
				markRowAccepted(registrationId, rowIndex);
			}
		});

		(payload.cleared || []).forEach(function (item) {
			applyBarcodeFieldLocally(registrationId, item.id, 'barcode_1d_type', '');
			applyBarcodeFieldLocally(registrationId, item.id, 'barcode_1d', '');
			syncOpenBarcodeInputs(item.id, '', '');
		});

		if (payload.registration_status) {
			setRegistrationStatus(registrationId, payload.registration_status);
		}

		if (activeDetail && String(activeDetail.regId) === String(registrationId) && productModalTitle) {
			const badge = productModalTitle.querySelector('.badge');
			const row = getActiveRow();

			if (badge && row) {
				badge.textContent = normalizeItemStatus(row.item_status || row.status || '');
			}

			updateReviewButtonState();
		}
	}

	function autoProcessPayload(errorOrResult) {
		const body = errorOrResult && errorOrResult.response && errorOrResult.response.data;

		if (body && body.data) {
			return body.data;
		}

		return errorOrResult && errorOrResult.data ? errorOrResult.data : {};
	}

	const AUTO_PROCESS_STEP_MS = 100;

	function wait(ms) {
		return new Promise(function (resolve) {
			setTimeout(resolve, ms);
		});
	}

	function focusAutoProcessRow(registrationId, data) {
		const payload = data || {};
		const changed = (payload.assigned && payload.assigned[0]) || (payload.cleared && payload.cleared[0]);

		if (!changed) {
			return;
		}

		const rowIndex = rowIndexForItem(registrationId, changed.id);

		if (rowIndex >= 0) {
			selectGridRow(registrationId, rowIndex);
		}
	}

	function runAutoProcess(button) {
		const registrationId = button.getAttribute(ID_ATTR);
		const tab = tabData.get(String(registrationId));

		if (!registrationId) {
			return Promise.resolve();
		}

		const maxSteps = ((tab && tab.rows) ? tab.rows.length : 0) + 1;
		let assignedCount = 0;
		let clearedCount = 0;
		let step = 0;

		button.disabled = true;

		function finish() {
			if (assignedCount) {
				showToast(t('reg_auto_process_done').replace('%d', String(assignedCount)), 'success');
				return;
			}

			if (clearedCount) {
				showToast(t('reg_auto_process_cleared'), 'success');
				return;
			}

			showToast(t('reg_auto_process_none'), 'success');
		}

		function next() {
			if (step >= maxSteps) {
				finish();
				return Promise.resolve();
			}

			step += 1;

			return fetchApi(baseUrl + '/auto_process/' + registrationId, {
				method: 'POST',
			}).then(function (result) {
				const data = result.data || {};

				if (data.done) {
					finish();
					return;
				}

				focusAutoProcessRow(registrationId, data);

				return wait(160).then(function () {
					applyAutoProcessResult(registrationId, data);
					assignedCount += (data.assigned || []).length;
					clearedCount += (data.cleared || []).length;
					return wait(AUTO_PROCESS_STEP_MS).then(next);
				});
			});
		}

		return next().catch(function (error) {
			applyAutoProcessResult(registrationId, autoProcessPayload(error));
			showAlert({
				message: error.message || t('reg_barcode_save_failed'),
				variant: 'danger',
			});
		}).finally(function () {
			button.disabled = false;
		});
	}

	function confirmAutoProcess(button) {
		const registrationId = button.getAttribute(ID_ATTR);
		const tab = tabData.get(String(registrationId));

		showConfirm({
			title: (tab && tab.file_name) || t('reg_auto_process'),
			message: t('reg_auto_process_confirm'),
			confirmLabel: t('reg_auto_process'),
			confirmClass: 'btn btn-primary',
		}).then(function () {
			return runAutoProcess(button);
		}).catch(function (error) {
			if (window.isConfirmCancelled(error)) {
				return;
			}

			showAlert({
				message: error.message || t('reg_barcode_save_failed'),
				variant: 'danger',
			});
		});
	}

	function saveRegistrationProducts(button) {
		const registrationId = button.getAttribute(ID_ATTR);

		if (!registrationId) {
			return;
		}

		button.disabled = true;

		fetchApi(baseUrl + '/save_products/' + registrationId, {
			method: 'POST',
		}).then(function (result) {
			markRegistrationSaved(registrationId, result.data && result.data.status);
			showToast(result.message || t('reg_save_success', { count: result.data && result.data.saved }), 'success');
		}).catch(function (error) {
			button.disabled = false;
			showAlert({
				message: error.message || t('reg_save_failed'),
				variant: 'danger',
			});
		});
	}

	function buildSendButton(tab) {
		return '<button type="button" class="btn btn-sm btn-outline-success product-registration-tab-send-btn"' +
			' data-product-registration-id="' + tab.product_registration_id + '"' +
			' data-mdb-ripple-init>' +
			'<i class="fas fa-paper-plane me-1"></i> ' + escapeHtml(t('reg_send')) +
		'</button>';
	}

	function buildTabActionButton(tab, isImported) {
		const isCompleted = isImported || String(tab.status || '').toLowerCase() === 'completed';

		if (isCompleted) {
			return '<div class="ms-auto d-flex gap-2">' +
				buildSendButton(tab) +
				'<button type="button" class="btn btn-sm btn-outline-secondary product-registration-tab-close-btn"' +
					' data-product-registration-id="' + tab.product_registration_id + '"' +
					' data-mdb-ripple-init>' +
					'<i class="fas fa-times me-1"></i> ' + escapeHtml(t('common_close')) +
				'</button>' +
			'</div>';
		}

		return '<div class="ms-auto d-flex gap-2">' +
			buildSendButton(tab) +
			'<button type="button" class="btn btn-sm btn-outline-secondary product-registration-tab-auto-process-btn"' +
				' data-product-registration-id="' + tab.product_registration_id + '"' +
				' data-mdb-ripple-init>' +
				'<i class="fas fa-barcode me-1"></i> ' + escapeHtml(t('reg_auto_process')) +
			'</button>' +
			'<button type="button" class="btn btn-sm btn-outline-primary product-registration-tab-save-btn"' +
				' data-product-registration-id="' + tab.product_registration_id + '"' +
				' data-mdb-ripple-init>' +
				'<i class="fas fa-save me-1"></i> ' + escapeHtml(t('common_save')) +
			'</button>' +
			'<button type="button" class="btn btn-sm btn-outline-danger product-registration-tab-delete-btn"' +
				' data-product-registration-id="' + tab.product_registration_id + '"' +
				' data-file-name="' + escapeAttr(tab.file_name) + '"' +
				' data-mdb-ripple-init>' +
				'<i class="fas fa-trash me-1"></i> ' + escapeHtml(t('common_delete')) +
			'</button>' +
		'</div>';
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
				'<span><strong>Status:</strong> <span class="procedure-tab-status-value">' + escapeHtml(tab.status || 'uploaded') + '</span></span>' +
				'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at || '') + '</span>' +
				buildTabActionButton(tab, isImported) +
			'</div>' +
			buildFactoryAlert(tab) +
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
			message: t('reg_stop_confirm'),
			confirmLabel: t('reg_stop_label'),
			errorMessage: t('reg_delete_failed'),
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
		uploadErrorMessage: t('reg_upload_error'),
		hints: {
			empty: t('reg_select_files'),
			selected: function (count) {
				return t('reg_files_ready', { count: count });
			},
			uploading: t('reg_uploading'),
		},
		normalizeFiles: function (fileList) {
			const files = Array.from(fileList || []).filter(function (file) {
				return /\.(zip|xls|xlsx)$/i.test(file.name);
			});

			if (!files.length && fileList && fileList.length) {
				showToast(t('reg_files_only'), 'error');
				return null;
			}

			return files;
		},
		onSuccess: function (result) {
			procedureTabs.prependTabs(result.tabs || []);
		},
	});

	let sendModal = null;
	let pendingSendId = null;
	let incomingRequest = null;

	function sendUserLabel(user) {
		const name = String(user.full_name || '').trim();

		return name || String(user.username || '');
	}

	function renderSendUser(user) {
		const label = sendUserLabel(user);
		const username = String(user.username || '');

		return '<button type="button" class="btn btn-outline-secondary text-start procedure-send-user"' +
			' data-account-id="' + escapeAttr(user.id) + '"' +
			' data-user-name="' + escapeAttr(label) + '">' +
			'<span class="d-block fw-semibold">' + escapeHtml(label) + '</span>' +
			(username && username !== label
				? '<span class="small text-muted">' + escapeHtml(username) + '</span>'
				: '') +
		'</button>';
	}

	function initSendModal() {
		if (typeof mdb === 'undefined' || !sendModalEl) {
			return;
		}

		sendModal = mdb.Modal.getOrCreateInstance(sendModalEl);
	}

	function openSendModal(registrationId) {
		if (!sendModal || !registrationId) {
			return;
		}

		pendingSendId = String(registrationId);

		if (sendUserList) {
			sendUserList.innerHTML = '';
		}

		if (sendEmpty) {
			sendEmpty.classList.add('d-none');
		}

		if (sendLoading) {
			sendLoading.classList.remove('d-none');
		}

		sendModal.show();

		fetchApi(baseUrl + '/assignees').then(function (result) {
			if (pendingSendId !== String(registrationId)) {
				return;
			}

			const users = result.users || [];

			if (sendLoading) {
				sendLoading.classList.add('d-none');
			}

			if (!users.length) {
				if (sendEmpty) {
					sendEmpty.classList.remove('d-none');
				}
				return;
			}

			if (sendUserList) {
				sendUserList.innerHTML = users.map(renderSendUser).join('');
			}
		}).catch(function (error) {
			if (sendModal) {
				sendModal.hide();
			}

			showAlert({
				message: error.message || t('reg_send_failed'),
				variant: 'danger',
			});
		});
	}

	function finishSend(registrationId) {
		if (activeDetail && String(activeDetail.regId) === String(registrationId) && productModal) {
			productModal.hide();
		}

		tabData.delete(String(registrationId));
		procedureTabs.closeTab(registrationId);
	}

	function confirmSend(accountId, userName) {
		const registrationId = pendingSendId;

		if (!registrationId || !accountId || !sendModalEl) {
			return;
		}

		function ask() {
			showConfirm({
				title: t('reg_send'),
				message: t('reg_send_confirm', { name: userName }),
				confirmLabel: t('reg_send'),
				confirmClass: 'btn btn-primary',
			}).then(function () {
				const formData = new FormData();
				formData.append('account_id', accountId);

				return fetchApi(baseUrl + '/assign/' + registrationId, {
					method: 'POST',
					body: formData,
				});
			}).then(function (result) {
				if (!result) {
					return;
				}

				finishSend(registrationId);
				showToast(result.message || t('reg_send_success'), 'success');
			}).catch(function (error) {
				if (window.isConfirmCancelled(error)) {
					return;
				}

				showAlert({
					message: error.message || t('reg_send_failed'),
					variant: 'danger',
				});
			});
		}

		sendModalEl.addEventListener('hidden.mdb.modal', function onHidden() {
			sendModalEl.removeEventListener('hidden.mdb.modal', onHidden);
			ask();
		});
		sendModal.hide();
	}

	function currentRegistrationIds() {
		if (!tabsNav) {
			return [];
		}

		return Array.from(tabsNav.querySelectorAll('.nav-link[' + ID_ATTR + ']')).map(function (button) {
			return button.getAttribute(ID_ATTR);
		}).filter(Boolean);
	}

	function pullIncomingRegistrations() {
		if (incomingRequest || document.hidden) {
			return;
		}

		const ids = currentRegistrationIds().join(',');
		incomingRequest = fetchApi(baseUrl + '/incoming?ids=' + encodeURIComponent(ids)).then(function (result) {
			const tabs = result.tabs || [];

			if (!tabs.length) {
				return;
			}

			procedureTabs.prependTabs(tabs, { activate: false });
			showToast(t('reg_send_received'), 'success');
		}).catch(function () {
			return null;
		}).finally(function () {
			incomingRequest = null;
		});
	}

	function watchIncomingRegistrations() {
		window.setInterval(pullIncomingRegistrations, 10000);
		window.addEventListener('focus', pullIncomingRegistrations);
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				pullIncomingRegistrations();
			}
		});
	}

	if (sendUserList) {
		sendUserList.addEventListener('click', function (event) {
			const button = event.target.closest('.procedure-send-user');

			if (!button) {
				return;
			}

			confirmSend(button.getAttribute('data-account-id'), button.getAttribute('data-user-name') || '');
		});
	}

	if (tabsContent) {
		tabsContent.addEventListener('click', function (event) {
			const sendButton = event.target.closest('.product-registration-tab-send-btn');

			if (sendButton) {
				openSendModal(sendButton.getAttribute(ID_ATTR));
				return;
			}

			const autoButton = event.target.closest('.product-registration-tab-auto-process-btn');

			if (autoButton) {
				if (!autoButton.disabled) {
					confirmAutoProcess(autoButton);
				}

				return;
			}

			const button = event.target.closest('.product-registration-tab-save-btn');

			if (!button || button.disabled) {
				return;
			}

			saveRegistrationProducts(button);
		});
	}

	procedureUpload.init();
	procedureTabs.init();
	initSendModal();
	watchIncomingRegistrations();

	// A finished registration stays in the catalog. Its tab control only closes the tab.
	document.addEventListener('click', function (event) {
		const deleteBtn = event.target.closest('.product-registration-tab-delete-btn');

		if (!deleteBtn) {
			return;
		}

		const registrationId = deleteBtn.getAttribute(ID_ATTR);
		const tab = tabData.get(String(registrationId));

		if (!tab || String(tab.status || '').toLowerCase() !== 'completed') {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		procedureTabs.closeTab(registrationId);
	}, true);

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

	if (productDetailInfo) {
		productDetailInfo.addEventListener('input', function (event) {
			const input = event.target.closest('[data-barcode-field]');

			if (!input || input.disabled || input.tagName === 'SELECT') {
				return;
			}

			input.classList.remove('is-invalid');

			if (input.getAttribute('data-barcode-field') === 'barcode_1d') {
				showBarcodesTab();
			}

			scheduleBarcodeFieldSave(input);
		});

		productDetailInfo.addEventListener('click', function (event) {
			const button = event.target.closest('.procedure-barcode-auto-assign');

			if (!button || button.disabled) {
				return;
			}

			autoAssignBarcode(button);
		});

		productDetailInfo.addEventListener('change', function (event) {
			const input = event.target.closest('[data-barcode-field]');

			if (!input || input.disabled || input.tagName !== 'SELECT') {
				return;
			}

			input.classList.remove('is-invalid');
			scheduleBarcodeFieldSave(input);

			if (input.getAttribute('data-barcode-field') === 'barcode_1d_type') {
				const valueInput = barcodeFieldInput('barcode_1d');
				const barcodeValue = valueInput ? valueInput.value : '';

				if (procedureBarcodeInput && !procedureBarcodeInput.classList.contains('is-typing-barcode')) {
					procedureBarcodeInput.value = barcodeValue;
				}

				renderBarcodePreview(barcodeValue);
			}
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
