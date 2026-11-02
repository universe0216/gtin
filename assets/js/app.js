(function () {
	'use strict';

	window.t = function (key, replacements) {
		const dict = window.APP_I18N || {};
		let text = Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : key;

		if (replacements) {
			Object.keys(replacements).forEach(function (name) {
				text = String(text).split('{' + name + '}').join(replacements[name]);
			});
		}

		return text;
	};

	function escapeAttr(text) {
		return String(text ?? '')
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function escapeHtml(text) {
		return String(text ?? '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function debounce(fn, delay) {
		let timer = null;

		const debounced = function () {
			const args = arguments;
			const context = this;

			if (timer) {
				clearTimeout(timer);
			}

			timer = setTimeout(function () {
				timer = null;
				fn.apply(context, args);
			}, delay);
		};

		debounced.cancel = function () {
			if (timer) {
				clearTimeout(timer);
				timer = null;
			}
		};

		return debounced;
	}

	function isAbortError(error) {
		return !!(error && error.name === 'AbortError');
	}

	function mergeHeaders(headers) {
		return Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, headers || {});
	}

	function fetchJson(url, options) {
		const settings = Object.assign({}, options || {});
		settings.headers = mergeHeaders(settings.headers);

		return fetch(url, settings).then(function (response) {
			return response.json().then(function (data) {
				return {
					ok: response.ok,
					status: response.status,
					data: data,
				};
			});
		});
	}

	function fetchApi(url, options) {
		return fetchJson(url, options).then(function (result) {
			if (!result.ok || !result.data || result.data.success !== true) {
				const message = (result.data && result.data.message) || 'Request failed.';
				const error = new Error(message);
				error.response = result;
				throw error;
			}

			return result.data;
		});
	}

	function createAbortableRequest(existingController) {
		if (existingController) {
			existingController.abort();
		}

		return new AbortController();
	}

	function getToastContainer() {
		let container = document.getElementById('toastContainer');

		if (!container) {
			container = document.createElement('div');
			container.id = 'toastContainer';
			container.className = 'toast-container position-fixed top-0 end-0 p-3';
			document.body.appendChild(container);
		}

		return container;
	}

	function showToast(message, type, options) {
		const settings = options || {};
		const delay = typeof settings.delay === 'number' ? settings.delay : 3500;
		const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
		const id = 'app-toast-' + Date.now();
		const html =
			'<div id="' + id + '" class="toast align-items-center text-white ' + bgClass + ' border-0" role="alert">' +
				'<div class="d-flex">' +
					'<div class="toast-body">' + escapeHtml(message) + '</div>' +
					'<button type="button" class="btn-close btn-close-white me-2 m-auto" data-mdb-dismiss="toast"></button>' +
				'</div>' +
			'</div>';

		const container = getToastContainer();
		container.insertAdjacentHTML('beforeend', html);
		const toastEl = document.getElementById(id);

		if (typeof mdb !== 'undefined') {
			const toast = new mdb.Toast(toastEl, { delay: delay });
			toast.show();
			toastEl.addEventListener('hidden.mdb.toast', function () {
				toastEl.remove();
			});
		}
	}

	function renderPagination(meta, options) {
		const settings = options || {};
		const page = meta.page || 1;
		const totalPages = Math.max(1, meta.total_pages || 1);
		const windowSize = settings.windowSize || 5;
		const showEllipsis = settings.showEllipsis !== false;
		const buttonClass = settings.buttonClass || 'page-btn';
		let html = '';

		const addPageItem = function (label, targetPage, disabled, active) {
			html += '<li class="page-item' +
				(disabled ? ' disabled' : '') +
				(active ? ' active' : '') +
				'">';

			if (disabled || active) {
				html += '<span class="page-link">' + label + '</span>';
			} else {
				html += '<button type="button" class="page-link ' + buttonClass + '" data-page="' + targetPage + '" data-mdb-ripple-init>' + label + '</button>';
			}

			html += '</li>';
		};

		addPageItem('&laquo;', page - 1, page <= 1, false);

		let startPage = Math.max(1, page - Math.floor(windowSize / 2));
		let endPage = Math.min(totalPages, startPage + windowSize - 1);

		if (endPage - startPage + 1 < windowSize) {
			startPage = Math.max(1, endPage - windowSize + 1);
		}

		if (showEllipsis && startPage > 1) {
			addPageItem('1', 1, false, page === 1);

			if (startPage > 2) {
				addPageItem('&hellip;', page, true, false);
			}
		}

		for (let i = startPage; i <= endPage; i++) {
			addPageItem(String(i), i, false, i === page);
		}

		if (showEllipsis && endPage < totalPages) {
			if (endPage < totalPages - 1) {
				addPageItem('&hellip;', page, true, false);
			}

			addPageItem(String(totalPages), totalPages, false, page === totalPages);
		}

		addPageItem('&raquo;', page + 1, page >= totalPages, false);

		return html;
	}

	function updatePaginationView(options) {
		const settings = options || {};
		const meta = settings.meta || {};
		const total = meta.total || 0;
		const perPage = settings.perPage || meta.per_page || 10;
		const page = meta.page || 1;
		const totalPages = meta.total_pages || 1;
		const showNav = typeof settings.showNav === 'boolean'
			? settings.showNav
			: total > perPage;

		if (settings.navEl) {
			settings.navEl.classList.toggle('d-none', !showNav);
		}

		if (settings.wrapperEl) {
			settings.wrapperEl.classList.toggle('d-none', !showNav);
		}

		if (settings.metaEl) {
			settings.metaEl.textContent = total > 0
				? t('common_page_of', { page: page, pages: totalPages })
				: '';
		}

		if (settings.listEl) {
			settings.listEl.innerHTML = showNav
				? renderPagination(meta, settings)
				: '';
		}
	}

	let confirmModalInstance = null;

	function getConfirmElements() {
		return {
			modalEl: document.getElementById('appConfirmModal'),
			dialogEl: document.querySelector('#appConfirmModal .modal-dialog'),
			titleEl: document.getElementById('appConfirmModalTitle'),
			bodyEl: document.getElementById('appConfirmModalBody'),
			confirmBtn: document.getElementById('appConfirmModalConfirmBtn'),
			cancelBtn: document.getElementById('appConfirmModalCancelBtn'),
		};
	}

	function initConfirmModal() {
		const els = getConfirmElements();

		if (!els.modalEl || typeof mdb === 'undefined') {
			return null;
		}

		if (!confirmModalInstance) {
			confirmModalInstance = mdb.Modal.getOrCreateInstance(els.modalEl);
		}

		return confirmModalInstance;
	}

	function isConfirmCancelled(error) {
		return !!(error && error.cancelled === true);
	}

	function showConfirm(options) {
		const settings = Object.assign({
			title: t('common_confirm'),
			message: t('common_are_you_sure'),
			confirmLabel: t('common_confirm'),
			cancelLabel: t('common_cancel'),
			confirmClass: 'btn btn-danger',
			size: '',
			allowHtml: false,
		}, options || {});

		const modal = initConfirmModal();
		const els = getConfirmElements();

		if (!modal || !els.confirmBtn || !els.bodyEl) {
			return Promise.reject(new Error(t('common_confirm_unavailable')));
		}

		return new Promise(function (resolve, reject) {
			let settled = false;

			function cleanup() {
				els.confirmBtn.disabled = false;
				els.confirmBtn.removeEventListener('click', onConfirm);
				els.modalEl.removeEventListener('hidden.mdb.modal', onHidden);
			}

			function settle(type, value) {
				if (settled) {
					return;
				}

				settled = true;
				cleanup();

				if (type === 'resolve') {
					resolve(value);
				} else {
					reject(value);
				}
			}

			function onConfirm() {
				modal.hide();
				settle('resolve');
			}

			function onHidden() {
				if (!settled) {
					settle('reject', { cancelled: true });
				}
			}

			els.titleEl.textContent = settings.title;

			if (settings.allowHtml) {
				els.bodyEl.innerHTML = settings.message;
			} else {
				els.bodyEl.textContent = settings.message;
			}

			els.confirmBtn.textContent = settings.confirmLabel;
			els.cancelBtn.textContent = settings.cancelLabel;
			els.confirmBtn.className = settings.confirmClass.indexOf('btn') === 0
				? settings.confirmClass
				: 'btn ' + settings.confirmClass;

			const dialogClasses = ['modal-dialog', 'modal-dialog-centered'];

			if (settings.size === 'sm') {
				dialogClasses.push('modal-sm');
			}

			if (els.dialogEl) {
				els.dialogEl.className = dialogClasses.join(' ');
			}

			els.confirmBtn.addEventListener('click', onConfirm);
			els.modalEl.addEventListener('hidden.mdb.modal', onHidden);
			modal.show();
		});
	}

	window.escapeAttr = escapeAttr;
	window.escapeHtml = escapeHtml;
	window.debounce = debounce;
	window.isAbortError = isAbortError;
	window.isConfirmCancelled = isConfirmCancelled;
	window.fetchJson = fetchJson;
	window.fetchApi = fetchApi;
	window.createAbortableRequest = createAbortableRequest;
	let alertModalInstance = null;
	let alertQueue = Promise.resolve();

	const ALERT_VARIANTS = {
		warning: { icon: 'fa-triangle-exclamation', iconClass: 'text-warning', button: 'btn btn-warning' },
		danger: { icon: 'fa-circle-exclamation', iconClass: 'text-danger', button: 'btn btn-danger' },
		info: { icon: 'fa-circle-info', iconClass: 'text-info', button: 'btn btn-info' },
		success: { icon: 'fa-circle-check', iconClass: 'text-success', button: 'btn btn-success' },
	};

	function getAlertElements() {
		return {
			modalEl: document.getElementById('appAlertModal'),
			titleEl: document.getElementById('appAlertModalTitleText'),
			iconEl: document.getElementById('appAlertModalIcon'),
			bodyEl: document.getElementById('appAlertModalBody'),
			okBtn: document.getElementById('appAlertModalOkBtn'),
		};
	}

	function initAlertModal() {
		const els = getAlertElements();

		if (!els.modalEl || typeof mdb === 'undefined') {
			return null;
		}

		if (!alertModalInstance) {
			alertModalInstance = mdb.Modal.getOrCreateInstance(els.modalEl);
		}

		return alertModalInstance;
	}

	function normalizeAlertOptions(options) {
		if (typeof options === 'string') {
			options = { message: options };
		}

		return Object.assign({
			title: t('common_alert'),
			message: '',
			okLabel: t('common_ok'),
			variant: 'warning',
			allowHtml: false,
		}, options || {});
	}

	function liftAlertLayer(modalEl) {
		const backdrops = document.querySelectorAll('.modal-backdrop');
		const backdrop = backdrops[backdrops.length - 1];

		if (!backdrop || backdrop.dataset.appAlert === '1') {
			return;
		}

		backdrop.dataset.appAlert = '1';
		backdrop.classList.add('app-alert-backdrop');
		backdrop.style.zIndex = '1990';
		modalEl.style.zIndex = '2000';
	}

	function keepParentModalOpen() {
		if (!document.querySelector('.modal.show')) {
			return;
		}

		document.body.classList.add('modal-open');
	}

	function presentAlert(settings) {
		const modal = initAlertModal();
		const els = getAlertElements();

		if (!modal || !els.okBtn || !els.bodyEl || !els.titleEl) {
			return Promise.reject(new Error(t('common_alert')));
		}

		const variant = ALERT_VARIANTS[settings.variant] || ALERT_VARIANTS.warning;

		return new Promise(function (resolve) {
			let settled = false;

			function finish() {
				if (settled) {
					return;
				}

				settled = true;
				els.okBtn.removeEventListener('click', onOk);
				els.modalEl.removeEventListener('shown.mdb.modal', onShown);
				els.modalEl.removeEventListener('hidden.mdb.modal', onHidden);
				document.removeEventListener('keydown', onKeyDown, true);
				keepParentModalOpen();
				resolve();
			}

			function onOk() {
				modal.hide();
			}

			function onShown() {
				liftAlertLayer(els.modalEl);
				els.okBtn.focus();
			}

			function onHidden() {
				finish();
			}

			function onKeyDown(event) {
				if (event.key !== 'Escape' || !els.modalEl.classList.contains('show')) {
					return;
				}

				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();
				modal.hide();
			}

			els.titleEl.textContent = settings.title;
			els.iconEl.className = 'fas ' + variant.icon + ' ' + variant.iconClass;
			els.okBtn.textContent = settings.okLabel;
			els.okBtn.className = variant.button;

			if (settings.allowHtml) {
				els.bodyEl.innerHTML = settings.message;
			} else {
				els.bodyEl.textContent = settings.message;
			}

			els.okBtn.addEventListener('click', onOk);
			els.modalEl.addEventListener('shown.mdb.modal', onShown);
			els.modalEl.addEventListener('hidden.mdb.modal', onHidden);
			document.addEventListener('keydown', onKeyDown, true);
			modal.show();
		});
	}

	// showAlert('Message') or showAlert({ title, message, okLabel, variant, allowHtml })
	// variant is warning, danger, info, or success. Resolves when the dialog closes.
	function showAlert(options) {
		const settings = normalizeAlertOptions(options);
		const task = function () {
			return presentAlert(settings).catch(function () {
				window.alert(settings.message);
			});
		};
		const result = alertQueue.then(task, task);

		alertQueue = result.then(function () {
			return null;
		}, function () {
			return null;
		});

		return result;
	}

	window.showToast = showToast;
	window.showConfirm = showConfirm;
	window.showAlert = showAlert;
	window.Pagination = {
		render: renderPagination,
		update: updatePaginationView,
	};
})();
