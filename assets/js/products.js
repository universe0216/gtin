(function () {
	'use strict';

	if (typeof window.PRODUCTS_CONFIG === 'undefined') {
		return;
	}

	const config = window.PRODUCTS_CONFIG;
	const apiUrl = config.apiUrl.replace(/\/$/, '');
	const columns = config.columns || [];
	const perPage = config.perPage || 10;
	const perPageOptions = (config.perPageOptions || [5, 10, 20, 50]).map(String);
	const detail = config.detail || {};
	const detailEnabled = !!(detail.enabled && detail.detailUrl);

	const searchForm = document.getElementById('productsSearchForm');
	const searchInput = document.getElementById('productsSearchInput');
	const clearBtn = document.getElementById('productsClearBtn');
	const toolbarMeta = document.getElementById('productsToolbarMeta');
	const modalEl = document.getElementById('productDetailModal');
	const modalTitle = document.getElementById('productDetailModalLabel');
	const detailForm = document.getElementById('productDetailForm');
	const formWrap = document.getElementById('productDetailFormWrap');
	const loadingEl = document.getElementById('productDetailLoading');
	const alertEl = document.getElementById('productDetailAlert');
	const downloadBtn = document.getElementById('productDetailDownloadBtn');

	if (typeof JqxGrid === 'undefined' || !document.getElementById('productsGrid')) {
		return;
	}

	const columnWidths = {
		name: 220,
		brand: 150,
		standard_number: 170,
		barcode_1d: 170,
		package_type: 150,
		organization_name: 200,
		price: 120,
		reg_date: 160,
		test_status: 140,
	};

	let currentSearch = '';
	let detailModal = null;
	let detailRequest = null;
	let currentProductId = null;

	function updateMeta(data) {
		if (!toolbarMeta) {
			return;
		}

		if (data && (data.total || 0) > 0) {
			toolbarMeta.textContent = data.range_start + '–' + data.range_end + ' of ' + data.total;
			toolbarMeta.classList.remove('d-none');
		} else {
			toolbarMeta.textContent = currentSearch ? config.emptySearchMessage : '';
			toolbarMeta.classList.toggle('d-none', !currentSearch);
		}
	}

	function gridHeight() {
		const panel = document.querySelector('#productsPage .entity-list-panel');
		const toolbar = document.querySelector('#productsPage .entity-list-toolbar');

		if (!panel) {
			return 420;
		}

		return Math.max(240, panel.clientHeight - (toolbar ? toolbar.offsetHeight : 0) - 8);
	}

	function indexRenderer(row) {
		let number = row + 1;
		const host = $('#productsGrid');

		if (host.length) {
			const paging = host.jqxGrid('getpaginginformation');

			if (paging && paging.pagesize && row < paging.pagesize) {
				number = (paging.pagenum || 0) * paging.pagesize + row + 1;
			}
		}

		return '<div class="jqx-grid-cell-leftalign" style="margin-top:6px;">' + number + '</div>';
	}

	const datafields = [
		{ name: 'id', type: 'number' },
		{ name: '__rownum', type: 'number' },
	].concat(columns.map(function (column) {
		return { name: column.key, type: 'string' };
	}));

	const gridColumns = [
		{
			text: '#',
			datafield: '__rownum',
			width: 56,
			pinned: true,
			sortable: false,
			filterable: false,
			cellsalign: 'right',
			align: 'right',
			cellsrenderer: indexRenderer,
		},
	].concat(columns.map(function (column) {
		return {
			text: column.label,
			datafield: column.key,
			width: columnWidths[column.key] || 160,
		};
	}));

	const grid = JqxGrid.remote('#productsGrid', {
		url: apiUrl,
		columns: gridColumns,
		datafields: datafields,
		pagesize: perPage,
		pagesizeoptions: perPageOptions,
		selectionmode: detailEnabled ? 'singlerow' : 'none',
		height: gridHeight(),
		emptyMessage: config.emptyMessage,
		getParams: function () {
			return currentSearch ? { q: currentSearch } : {};
		},
		onLoad: updateMeta,
		onError: function () {
			showToast(t('common_failed_load_records'), 'error');
		},
	});

	if (!grid) {
		return;
	}

	if (detailEnabled) {
		grid.$el.addClass('entity-list-grid-clickable');
		grid.$el.on('rowclick', function (event) {
			const args = event.args || {};
			const bound = args.row && args.row.bounddata ? args.row.bounddata : null;
			const row = bound || grid.$el.jqxGrid('getrowdata', args.rowindex);
			const productId = row && row.id;

			if (productId) {
				openDetail(productId);
			}
		});
	}

	window.addEventListener('resize', function () {
		grid.$el.jqxGrid('height', gridHeight());
	});

	function applySearch() {
		grid.gotoFirst();
		grid.reload();
	}

	const scheduleSearch = debounce(applySearch, 300);

	if (searchForm) {
		searchForm.addEventListener('submit', function (event) {
			event.preventDefault();
			currentSearch = searchInput ? searchInput.value.trim() : '';

			if (clearBtn) {
				clearBtn.classList.toggle('d-none', currentSearch === '');
			}

			applySearch();
		});
	}

	if (searchInput) {
		searchInput.addEventListener('input', function () {
			currentSearch = searchInput.value.trim();

			if (clearBtn) {
				clearBtn.classList.toggle('d-none', currentSearch === '');
			}

			scheduleSearch();
		});
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			currentSearch = '';

			if (searchInput) {
				searchInput.value = '';
			}

			clearBtn.classList.add('d-none');
			applySearch();
		});
	}

	function initModal() {
		if (typeof mdb === 'undefined' || !modalEl) {
			return;
		}

		detailModal = mdb.Modal.getOrCreateInstance(modalEl);
	}

	function setDetailLoading(isLoading) {
		if (loadingEl) {
			loadingEl.classList.toggle('d-none', !isLoading);
		}

		if (formWrap) {
			formWrap.classList.toggle('d-none', isLoading);
		}
	}

	function hideDetailAlert() {
		if (!alertEl) {
			return;
		}

		alertEl.className = 'alert d-none';
		alertEl.textContent = '';
	}

	function showDetailAlert(message) {
		if (!alertEl) {
			return;
		}

		alertEl.className = 'alert alert-danger';
		alertEl.textContent = message;
	}

	function fieldValue(data, field) {
		const value = data[field.name];

		if (value === null || value === undefined) {
			return '';
		}

		if (field.type === 'date') {
			const text = String(value).trim();
			return text.length >= 10 ? text.slice(0, 10) : text;
		}

		return String(value);
	}

	function renderDetailForm(data) {
		if (!formWrap) {
			return;
		}

		const fields = detail.fields || [];
		let html = '<div class="entity-detail-info-grid">';

		fields.forEach(function (field) {
			const inputId = 'productDetailField_' + field.name;
			const value = escapeHtml(fieldValue(data, field));
			let control = '';

			if (field.type === 'textarea') {
				control = '<textarea class="form-control form-control-sm" id="' + inputId + '" rows="3" readonly>' + value + '</textarea>';
			} else {
				const type = field.type === 'date' ? 'date' : 'text';
				control = '<input type="' + type + '" class="form-control form-control-sm" id="' + inputId + '" value="' + value + '" readonly>';
			}

			html +=
				'<div class="entity-detail-info-item">' +
					'<label class="entity-detail-info-label" for="' + inputId + '">' + escapeHtml(field.label) + '</label>' +
					control +
				'</div>';
		});

		html += '</div>';
		formWrap.innerHTML = html;

		if (downloadBtn) {
			downloadBtn.disabled = false;
		}
	}

	function openDetail(productId) {
		if (!detailEnabled || !detailModal) {
			return;
		}

		currentProductId = productId;
		hideDetailAlert();
		setDetailLoading(true);

		if (downloadBtn) {
			downloadBtn.disabled = true;
		}

		if (formWrap) {
			formWrap.innerHTML = '';
		}

		if (modalTitle) {
			modalTitle.textContent = t('title_products');
		}

		detailModal.show();

		if (detailRequest && detailRequest.abort) {
			detailRequest.abort();
		}

		const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		detailRequest = controller;

		fetchApi(detail.detailUrl.replace(/\/$/, '') + '/' + productId, {
			signal: controller ? controller.signal : undefined,
		}).then(function (result) {
			const record = result.data || {};
			currentProductId = record.id || productId;

			if (modalTitle) {
				modalTitle.textContent = record.name || t('title_products');
			}

			renderDetailForm(record);
			setDetailLoading(false);
		}).catch(function (error) {
			if (error && error.name === 'AbortError') {
				return;
			}

			setDetailLoading(false);
			showDetailAlert(error.message || t('common_failed_load_records'));
		});
	}

	function downloadFileName(disposition, fallback) {
		const header = String(disposition || '');
		const encoded = /filename\*=UTF-8''([^;]+)/i.exec(header);
		const plain = /filename="([^"]+)"/i.exec(header);

		if (encoded) {
			try {
				return decodeURIComponent(encoded[1]);
			} catch (error) {
				return encoded[1];
			}
		}

		if (plain) {
			return plain[1];
		}

		return fallback;
	}

	function downloadProduct(button) {
		if (!currentProductId || !detail.downloadUrl || button.disabled) {
			return;
		}

		button.disabled = true;

		fetch(detail.downloadUrl.replace(/\/$/, '') + '/' + currentProductId, {
			credentials: 'same-origin',
		}).then(function (response) {
			const type = response.headers.get('content-type') || '';

			if (type.indexOf('application/json') !== -1) {
				return response.json().then(function (data) {
					throw new Error((data && data.message) || t('product_download_failed'));
				});
			}

			if (!response.ok) {
				throw new Error(t('product_download_failed'));
			}

			return response.blob().then(function (blob) {
				return {
					blob: blob,
					filename: downloadFileName(response.headers.get('content-disposition'), 'product.zip'),
				};
			});
		}).then(function (file) {
			const url = URL.createObjectURL(file.blob);
			const link = document.createElement('a');
			link.href = url;
			link.download = file.filename;
			document.body.appendChild(link);
			link.click();
			link.remove();
			URL.revokeObjectURL(url);
		}).catch(function (error) {
			showToast(error.message || t('product_download_failed'), 'error');
		}).finally(function () {
			button.disabled = false;
		});
	}

	if (detailForm) {
		detailForm.addEventListener('submit', function (event) {
			event.preventDefault();
		});
	}

	if (downloadBtn) {
		downloadBtn.addEventListener('click', function () {
			downloadProduct(downloadBtn);
		});
	}

	initModal();
})();
