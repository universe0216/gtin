(function () {
	'use strict';

	if (typeof window.DATA_PATCH_CONFIG === 'undefined') {
		return;
	}

	const config = window.DATA_PATCH_CONFIG;
	const fullBtn = document.getElementById('dataPatchFullBtn');
	const patchBtn = document.getElementById('dataPatchPatchBtn');
	const statusEl = document.getElementById('dataPatchStatus');
	const progressWrap = document.getElementById('dataPatchProgress');
	const progressLabel = document.getElementById('dataPatchProgressLabel');
	const progressPercent = document.getElementById('dataPatchProgressPercent');
	const progressBar = document.getElementById('dataPatchProgressBar');
	const progressMeta = document.getElementById('dataPatchProgressMeta');
	const progressRoot = progressWrap
		? progressWrap.querySelector('.data-transfer-progress-bar')
		: null;
	const buttons = [fullBtn, patchBtn].filter(Boolean);

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
		buttons.forEach(function (button) {
			if (button === patchBtn && !config.hasExport) {
				button.disabled = true;
				return;
			}

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
			progressMeta.textContent = t('data_patch_progress_counts', {
				done: safeDone,
				total: safeTotal || '?',
			});
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

	function post(url, fields) {
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

	function runExport(type) {
		const label = type === 'patch'
			? t('data_patch_export_patch')
			: t('data_patch_export_full');

		setStatus(t('data_patch_running'), 'info');
		showProgress(label, 0, 0);

		return post(config.startUrl, { type: type }).then(function (startResult) {
			const start = startResult.data || {};

			if (start.empty) {
				hideProgress();
				const message = startResult.message || t('data_patch_no_changes');
				setStatus(message, 'warning');

				return { empty: true };
			}

			const exportId = start.export_id;
			const total = Number(start.company_total || 0) + Number(start.product_total || 0);

			showProgress(label, 0, total);

			function nextChunk() {
				return post(config.chunkUrl, { export_id: exportId }).then(function (chunkResult) {
					const data = chunkResult.data || {};
					const processed = Number(data.processed || 0);
					const chunkTotal = Number(data.total || total);

					showProgress(label, processed, chunkTotal);
					setStatus(
						t('data_patch_progress_counts', {
							done: processed,
							total: chunkTotal || '?',
						}),
						'info'
					);

					if (data.done) {
						return data;
					}

					return nextChunk();
				});
			}

			return nextChunk();
		}).then(function (data) {
			if (!data || data.empty) {
				return;
			}

			const pretty = t('data_patch_complete_detail', {
				files: data.file_count || 0,
				companies: data.company_count || 0,
				products: data.product_count || 0,
			});

			completeProgress(pretty, data.processed || 0, data.total || 0);
			setStatus(pretty, 'success');
			showToast(pretty, 'success');
			config.hasExport = true;

			if (patchBtn) {
				patchBtn.disabled = false;
			}

			if (data.download_url) {
				window.location.href = data.download_url;
			}

			window.setTimeout(function () {
				window.location.reload();
			}, 1200);
		});
	}

	function bind(button, type, titleKey, messageKey) {
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

				return runExport(type);
			}).catch(function (error) {
				if (window.isConfirmCancelled && isConfirmCancelled(error)) {
					return;
				}

				const message = error.message || t('data_patch_failed');
				setStatus(message, 'danger');
				showToast(message, 'error');
			}).finally(function () {
				setBusy(false);
			});
		});
	}

	bind(fullBtn, 'full', 'data_patch_export_full', 'data_patch_full_confirm');
	bind(patchBtn, 'patch', 'data_patch_export_patch', 'data_patch_patch_confirm');
})();
