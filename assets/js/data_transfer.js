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
	const progressWrap = document.getElementById('dataTransferProgress');
	const progressLabel = document.getElementById('dataTransferProgressLabel');
	const progressPercent = document.getElementById('dataTransferProgressPercent');
	const progressBar = document.getElementById('dataTransferProgressBar');
	const progressMeta = document.getElementById('dataTransferProgressMeta');
	const progressRoot = progressWrap
		? progressWrap.querySelector('.data-transfer-progress-bar')
		: null;
	const actionButtons = [startBtn, locationsBtn, organizationsBtn, productsBtn].filter(Boolean);
	const chunkSize = Number(config.chunkSize) || 5000;

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

	function hideProgress() {
		if (progressWrap) {
			progressWrap.classList.add('d-none');
		}

		if (progressBar) {
			progressBar.style.width = '0%';
			progressBar.classList.add('progress-bar-animated', 'progress-bar-striped');
			progressBar.classList.remove('bg-success');
		}

		if (progressRoot) {
			progressRoot.setAttribute('aria-valuenow', '0');
		}

		if (progressLabel) {
			progressLabel.textContent = '';
		}

		if (progressPercent) {
			progressPercent.textContent = '0%';
		}

		if (progressMeta) {
			progressMeta.textContent = '';
		}
	}

	function showProgress(label, done, total) {
		const safeDone = Math.max(0, Number(done) || 0);
		const safeTotal = Math.max(0, Number(total) || 0);
		const pct = safeTotal > 0
			? Math.min(100, Math.round((safeDone / safeTotal) * 100))
			: (safeDone > 0 ? 100 : 0);

		if (progressWrap) {
			progressWrap.classList.remove('d-none');
		}

		if (progressLabel) {
			progressLabel.textContent = label || '';
		}

		if (progressPercent) {
			progressPercent.textContent = pct + '%';
		}

		if (progressBar) {
			progressBar.style.width = pct + '%';
			progressBar.classList.add('progress-bar-animated', 'progress-bar-striped');
			progressBar.classList.remove('bg-success');
		}

		if (progressRoot) {
			progressRoot.setAttribute('aria-valuenow', String(pct));
		}

		if (progressMeta) {
			progressMeta.textContent = safeTotal > 0
				? t('data_transfer_progress_counts', { done: safeDone, total: safeTotal })
				: t('data_transfer_progress_counts', { done: safeDone, total: '?' });
		}
	}

	function completeProgress(label, done, total) {
		showProgress(label, done, total || done);

		if (progressBar) {
			progressBar.style.width = '100%';
			progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
			progressBar.classList.add('bg-success');
		}

		if (progressPercent) {
			progressPercent.textContent = '100%';
		}

		if (progressRoot) {
			progressRoot.setAttribute('aria-valuenow', '100');
		}
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
		let inserted = 0;
		let first = true;
		let sourceTotal = Number(settings.total || 0);

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
				inserted += Number(data.count || 0);
				offset = Number(data.next_offset || (offset + chunkSize));

				if (Number(data.total || 0) > 0) {
					sourceTotal = Number(data.total);
				}

				const processed = Math.min(offset, sourceTotal || offset);

				if (typeof onProgress === 'function') {
					onProgress({
						inserted: inserted,
						processed: processed,
						total: sourceTotal,
						done: !!data.done,
						data: data,
						message: result.message,
					});
				}

				if (data.done) {
					return {
						total: inserted,
						processed: processed,
						sourceTotal: sourceTotal,
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
		showProgress(t('data_transfer_running'), 0, 0);

		return postStep(config.startUrl).then(function (result) {
			source = (result.data && result.data.source) || {};
			setStatus(result.message || t('data_transfer_cleared'), 'info');
			showProgress(t('data_transfer_locations_running'), 0, Number(source.locations || 0));

			return runChunked(config.locationsUrl, {
				total: Number(source.locations || 0),
			}, function (state) {
				showProgress(
					t('data_transfer_locations_running'),
					state.processed,
					state.total || Number(source.locations || 0)
				);
				setStatus(
					t('data_transfer_locations_chunk', {
						done: state.processed,
						total: state.total || Number(source.locations || 0) || '?',
					}),
					'info'
				);
			});
		}).then(function (result) {
			locations = Number(result.total || 0);
			setStatus(formatDone('data_transfer_locations_only_done', [locations]), 'info');
			showProgress(
				t('data_transfer_organizations_running', { done: 0, total: Number(source.organizations || 0) }),
				0,
				Number(source.organizations || 0)
			);

			return runChunked(config.organizationsUrl, {
				total: Number(source.organizations || 0),
			}, function (state) {
				showProgress(
					t('data_transfer_organizations_running', {
						done: state.processed,
						total: state.total || Number(source.organizations || 0) || '?',
					}),
					state.processed,
					state.total || Number(source.organizations || 0)
				);
				setStatus(
					t('data_transfer_organizations_running', {
						done: state.processed,
						total: state.total || Number(source.organizations || 0) || '?',
					}),
					'info'
				);
			});
		}).then(function (result) {
			organizations = Number(result.total || 0);
			showProgress(
				t('data_transfer_products_running', { done: 0, total: Number(source.products || 0) }),
				0,
				Number(source.products || 0)
			);

			return runChunked(config.productsUrl, {
				total: Number(source.products || 0),
			}, function (state) {
				showProgress(
					t('data_transfer_products_running', {
						done: state.processed,
						total: state.total || Number(source.products || 0) || '?',
					}),
					state.processed,
					state.total || Number(source.products || 0)
				);
				setStatus(
					t('data_transfer_products_running', {
						done: state.processed,
						total: state.total || Number(source.products || 0) || '?',
					}),
					'info'
				);
			});
		}).then(function (result) {
			products = Number(result.total || 0);
			const pretty = formatDone('data_transfer_done', [locations, organizations, products]);
			completeProgress(pretty, products, Number(source.products || products));
			setStatus(pretty, 'success');
			showToast(pretty, 'success');
		});
	}

	function runLocationsOnly() {
		setStatus(t('data_transfer_locations_running'), 'info');
		showProgress(t('data_transfer_locations_running'), 0, 0);

		return runChunked(config.locationsUrl, { clear: true }, function (state) {
			showProgress(t('data_transfer_locations_running'), state.processed, state.total);
			setStatus(
				t('data_transfer_locations_chunk', {
					done: state.processed,
					total: state.total || '?',
				}),
				'info'
			);
		}).then(function (result) {
			const pretty = formatDone('data_transfer_locations_only_done', [result.total || 0]);
			completeProgress(pretty, result.processed || result.total || 0, result.sourceTotal || result.total || 0);
			setStatus(pretty, 'success');
			showToast(pretty, 'success');
		});
	}

	function runOrganizationsOnly() {
		setStatus(t('data_transfer_organizations_running', { done: 0, total: '?' }), 'info');
		showProgress(t('data_transfer_organizations_running', { done: 0, total: '?' }), 0, 0);

		return runChunked(config.organizationsUrl, { clear: true }, function (state) {
			showProgress(
				t('data_transfer_organizations_running', {
					done: state.processed,
					total: state.total || '?',
				}),
				state.processed,
				state.total
			);
			setStatus(
				t('data_transfer_organizations_running', {
					done: state.processed,
					total: state.total || '?',
				}),
				'info'
			);
		}).then(function (result) {
			const pretty = formatDone('data_transfer_organizations_only_done', [result.total || 0]);
			completeProgress(pretty, result.processed || result.total || 0, result.sourceTotal || result.total || 0);
			setStatus(pretty, 'success');
			showToast(pretty, 'success');
		});
	}

	function runProductsOnly() {
		setStatus(t('data_transfer_products_running', { done: 0, total: '?' }), 'info');
		showProgress(t('data_transfer_products_running', { done: 0, total: '?' }), 0, 0);

		return runChunked(config.productsUrl, { clear: true }, function (state) {
			showProgress(
				t('data_transfer_products_running', {
					done: state.processed,
					total: state.total || '?',
				}),
				state.processed,
				state.total
			);
			setStatus(
				t('data_transfer_products_running', {
					done: state.processed,
					total: state.total || '?',
				}),
				'info'
			);
		}).then(function (result) {
			const pretty = formatDone('data_transfer_products_only_done', [result.total || 0]);
			completeProgress(pretty, result.processed || result.total || 0, result.sourceTotal || result.total || 0);
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
				hideProgress();

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
