(function () {
	'use strict';

	if (typeof window.DATA_TRANSFER_CONFIG === 'undefined') {
		return;
	}

	const config = window.DATA_TRANSFER_CONFIG;
	const startBtn = document.getElementById('dataTransferStartBtn');
	const locationsBtn = document.getElementById('dataTransferLocationsBtn');
	const organizationsBtn = document.getElementById('dataTransferOrganizationsBtn');
	const productsBtn = document.getElementById('dataTransferProductsBtn');
	const statusEl = document.getElementById('dataTransferStatus');
	const actionButtons = [startBtn, locationsBtn, organizationsBtn, productsBtn].filter(Boolean);
	const chunkSize = Number(config.chunkSize) || 2000;

	if (!config.startUrl) {
		return;
	}

	function setStatus(message, variant) {
		if (!statusEl) {
			return;
		}

		statusEl.className = 'alert alert-' + (variant || 'info');
		statusEl.textContent = message || '';
		statusEl.classList.remove('d-none');
	}

	function clearStatus() {
		if (!statusEl) {
			return;
		}

		statusEl.classList.add('d-none');
		statusEl.textContent = '';
	}

	function setBusy(busy) {
		actionButtons.forEach(function (button) {
			button.disabled = !!busy;
		});
	}

	function formatDone(templateKey, values) {
		const template = (window.APP_I18N && window.APP_I18N[templateKey]) || t(templateKey);
		let text = String(template);

		(values || []).forEach(function (value) {
			text = text.replace('%d', String(value));
		});

		return text;
	}

	function postStep(url, fields) {
		const body = new FormData();
		const data = fields || {};

		Object.keys(data).forEach(function (key) {
			body.append(key, String(data[key]));
		});

		return fetchApi(url, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
		});
	}

	function runChunked(url, options, onProgress) {
		const settings = options || {};
		let offset = 0;
		let total = 0;
		let first = true;

		function next() {
			const fields = {
				offset: offset,
				limit: chunkSize,
			};

			if (first && settings.clear) {
				fields.clear = 1;
			}

			first = false;

			return postStep(url, fields).then(function (result) {
				const data = result.data || {};
				total += Number(data.count || 0);
				offset = Number(data.next_offset || (offset + chunkSize));

				if (typeof onProgress === 'function') {
					onProgress(total, offset, data, result.message);
				}

				if (data.done) {
					return {
						total: total,
						message: result.message,
						data: data,
					};
				}

				return next();
			});
		}

		return next();
	}

	function runTransfer() {
		let source = {};
		let locations = 0;
		let organizations = 0;
		let products = 0;

		setStatus(t('data_transfer_running'), 'info');

		return postStep(config.startUrl).then(function (result) {
			source = (result.data && result.data.source) || {};
			setStatus(result.message || t('data_transfer_cleared'), 'info');

			return postStep(config.locationsUrl);
		}).then(function (result) {
			locations = Number((result.data && result.data.count) || 0);
			setStatus(formatDone('data_transfer_locations_only_done', [locations]), 'info');

			return runChunked(config.organizationsUrl, {}, function (total, offset) {
				const target = Number(source.organizations || 0);
				setStatus(
					t('data_transfer_organizations_running', {
						done: total,
						total: target || offset,
					}),
					'info'
				);
			});
		}).then(function (result) {
			organizations = Number(result.total || 0);

			return runChunked(config.productsUrl, {}, function (total, offset) {
				const target = Number(source.products || 0);
				setStatus(
					t('data_transfer_products_running', {
						done: total,
						total: target || offset,
					}),
					'info'
				);
			});
		}).then(function (result) {
			products = Number(result.total || 0);
			const pretty = formatDone('data_transfer_done', [locations, organizations, products]);
			setStatus(pretty, 'success');
			showToast(pretty, 'success');
		});
	}

	function runLocationsOnly() {
		setStatus(t('data_transfer_locations_running'), 'info');

		return postStep(config.locationsUrl, { clear: 1 }).then(function (result) {
			const count = Number((result.data && result.data.count) || 0);
			const pretty = formatDone('data_transfer_locations_only_done', [count]);
			setStatus(pretty, 'success');
			showToast(pretty, 'success');
		});
	}

	function runOrganizationsOnly() {
		setStatus(t('data_transfer_organizations_running', { done: 0, total: '?' }), 'info');

		return runChunked(config.organizationsUrl, { clear: true }, function (total) {
			setStatus(
				t('data_transfer_organizations_running', {
					done: total,
					total: total,
				}),
				'info'
			);
		}).then(function (result) {
			const pretty = formatDone('data_transfer_organizations_only_done', [result.total || 0]);
			setStatus(pretty, 'success');
			showToast(pretty, 'success');
		});
	}

	function runProductsOnly() {
		setStatus(t('data_transfer_products_running', { done: 0, total: '?' }), 'info');

		return runChunked(config.productsUrl, { clear: true }, function (total) {
			setStatus(
				t('data_transfer_products_running', {
					done: total,
					total: total,
				}),
				'info'
			);
		}).then(function (result) {
			const pretty = formatDone('data_transfer_products_only_done', [result.total || 0]);
			setStatus(pretty, 'success');
			showToast(pretty, 'success');
		});
	}

	function bindAction(button, titleKey, messageKey, runner) {
		if (!button) {
			return;
		}

		button.addEventListener('click', function () {
			showConfirm({
				title: t(titleKey),
				message: t(messageKey),
				confirmLabel: t(titleKey),
			}).then(function () {
				setBusy(true);
				clearStatus();

				return runner();
			}).catch(function (error) {
				if (window.isConfirmCancelled && isConfirmCancelled(error)) {
					return;
				}

				const message = error.message || t('data_transfer_failed');
				setStatus(message, 'danger');
				showToast(message, 'error');
			}).finally(function () {
				setBusy(false);
			});
		});
	}

	bindAction(startBtn, 'data_transfer_start', 'data_transfer_confirm', runTransfer);
	bindAction(locationsBtn, 'data_transfer_locations_btn', 'data_transfer_locations_confirm', runLocationsOnly);
	bindAction(organizationsBtn, 'data_transfer_organizations_btn', 'data_transfer_organizations_confirm', runOrganizationsOnly);
	bindAction(productsBtn, 'data_transfer_products_btn', 'data_transfer_products_confirm', runProductsOnly);
})();
