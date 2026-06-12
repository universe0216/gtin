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

	window.escapeAttr = escapeAttr;
	window.escapeHtml = escapeHtml;
	window.showToast = showToast;
})();
