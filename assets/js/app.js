(function () {
	'use strict';

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
			settings.metaEl.textContent = total > 0 ? 'Page ' + page + ' of ' + totalPages : '';
		}

		if (settings.listEl) {
			settings.listEl.innerHTML = showNav
				? renderPagination(meta, settings)
				: '';
		}
	}

	window.escapeAttr = escapeAttr;
	window.escapeHtml = escapeHtml;
	window.debounce = debounce;
	window.isAbortError = isAbortError;
	window.fetchJson = fetchJson;
	window.fetchApi = fetchApi;
	window.createAbortableRequest = createAbortableRequest;
	window.showToast = showToast;
	window.Pagination = {
		render: renderPagination,
		update: updatePaginationView,
	};
})();
