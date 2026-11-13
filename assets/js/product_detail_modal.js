(function () {
	'use strict';

	function createProductDetailModal(config, options) {
		const settings = config || {};
		const hooks = options || {};
		const enabled = !!(settings.enabled && settings.detailUrl);
		const canEdit = !!(settings.canEdit && settings.updateUrl);
		const modalEl = document.getElementById('productDetailModal');
		const modalTitle = document.getElementById('productDetailModalLabel');
		const detailForm = document.getElementById('productDetailForm');
		const formWrap = document.getElementById('productDetailFormWrap');
		const loadingEl = document.getElementById('productDetailLoading');
		const alertEl = document.getElementById('productDetailAlert');
		const downloadBtn = document.getElementById('productDetailDownloadBtn');
		const saveBtn = document.getElementById('productDetailSaveBtn');
		const deleteBtn = document.getElementById('productDetailDeleteBtn');
		const restoreBtn = document.getElementById('productDetailRestoreBtn');
		const infoTabBtn = document.getElementById('productInfoTabBtn');
		const historyTabBtn = document.getElementById('productHistoryTabBtn');
		const historyLoading = document.getElementById('productHistoryLoading');
		const historyWrap = document.getElementById('productHistoryWrap');
		const historyUrl = (settings.historyUrl || '').replace(/\/$/, '');
		const historyFieldLabels = window.EntityHistory
			? window.EntityHistory.buildFieldLabels(settings.fields || [])
			: {};

		if (!enabled || !modalEl || typeof mdb === 'undefined') {
			return {
				open: function () {},
				deleteProduct: function () {},
				restoreProduct: function () {},
				currentId: function () { return null; },
				hide: function () {},
			};
		}

		const detailModal = mdb.Modal.getOrCreateInstance(modalEl);
		let detailRequest = null;
		let historyRequest = null;
		let currentProductId = null;
		let historyLoaded = false;
		let historyDirty = true;

		function notifyChanged() {
			if (typeof hooks.onChanged === 'function') {
				hooks.onChanged(currentProductId);
			}

			document.dispatchEvent(new CustomEvent('productsListChanged'));
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

		function detailActionButtons() {
			return [downloadBtn, deleteBtn, restoreBtn, saveBtn];
		}

		function setDetailActionsDisabled(isDisabled) {
			detailActionButtons().forEach(function (button) {
				if (button) {
					button.disabled = isDisabled;
				}
			});
		}

		function setDetailSaving(isSaving) {
			if (!saveBtn) {
				return;
			}

			saveBtn.disabled = isSaving;
			const spinner = saveBtn.querySelector('.spinner-border');

			if (spinner) {
				spinner.classList.toggle('d-none', !isSaving);
			}
		}

		function showInfoTab() {
			if (infoTabBtn && typeof mdb !== 'undefined') {
				mdb.Tab.getOrCreateInstance(infoTabBtn).show();
			}
		}

		function loadHistory(force) {
			if (!historyUrl || !currentProductId || !window.EntityHistory || !historyWrap) {
				return;
			}

			if (!force && historyLoaded && !historyDirty) {
				return;
			}

			if (historyRequest && historyRequest.abort) {
				historyRequest.abort();
			}

			const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
			historyRequest = controller;

			EntityHistory.load({
				url: historyUrl + '/' + currentProductId,
				container: historyWrap,
				loadingEl: historyLoading,
				fieldLabels: historyFieldLabels,
				signal: controller ? controller.signal : undefined,
			}).then(function () {
				historyLoaded = true;
				historyDirty = false;
			}).catch(function () {
				historyLoaded = false;
			});
		}

		function destroyDatePickers() {
			if (window.JqxDate && typeof window.JqxDate.destroyIn === 'function' && formWrap) {
				window.JqxDate.destroyIn(formWrap);
			}
		}

		function mountDatePickers() {
			if (!formWrap || !window.JqxDate || typeof window.JqxDate.mount !== 'function') {
				return;
			}

			formWrap.querySelectorAll('.jqx-app-date-host').forEach(function (host) {
				const valueInputId = host.getAttribute('data-value-input');
				const valueInput = valueInputId ? document.getElementById(valueInputId) : null;
				const locked = host.getAttribute('data-disabled') === '1';

				window.JqxDate.mount(host, {
					value: valueInput ? valueInput.value : '',
					valueInput: valueInput,
					allowNull: true,
					disabled: locked,
				});
			});
		}

		function renderDetailForm(data) {
			if (!formWrap) {
				return;
			}

			destroyDatePickers();

			const fields = settings.fields || [];
			let html = '<div class="entity-detail-info-grid">';

			fields.forEach(function (field) {
				const inputId = 'productDetailField_' + field.name;
				const rawValue = fieldValue(data, field);
				const value = escapeHtml(rawValue);
				const locked = !canEdit || field.readonly;
				const nameAttr = locked ? '' : ' name="' + escapeAttr(field.name) + '"';
				const readOnlyAttr = locked ? ' readonly' : '';
				let control = '';

				if (field.type === 'select') {
					const options = (field.options || []).slice();

					if (rawValue && options.indexOf(rawValue) === -1) {
						options.unshift(rawValue);
					}

					const optionHtml = options.map(function (option) {
						const selected = option === rawValue ? ' selected' : '';

						return '<option value="' + escapeAttr(option) + '"' + selected + '>' + escapeHtml(option) + '</option>';
					}).join('');

					control = '<select class="form-select form-select-sm" id="' + inputId + '"' + nameAttr + (locked ? ' disabled' : '') + '>' +
						'<option value=""></option>' +
						optionHtml +
					'</select>';
				} else if (field.type === 'textarea') {
					control = '<textarea class="form-control form-control-sm" id="' + inputId + '"' + nameAttr + ' rows="3"' + readOnlyAttr + '>' + value + '</textarea>';
				} else if (field.type === 'date') {
					control =
						'<div class="jqx-app-date-field">' +
							'<input type="hidden" id="' + inputId + '"' + nameAttr + ' class="jqx-app-date-value" value="' + value + '">' +
							'<div id="' + inputId + '_picker" class="jqx-app-date-host" data-value-input="' + inputId + '"' +
								(locked ? ' data-disabled="1"' : '') +
							'></div>' +
						'</div>';
				} else {
					control = '<input type="text" class="form-control form-control-sm" id="' + inputId + '"' + nameAttr + ' value="' + value + '"' + readOnlyAttr + '>';
				}

				html +=
					'<div class="entity-detail-info-item">' +
						'<label class="entity-detail-info-label" for="' + inputId + '">' + escapeHtml(field.label) + '</label>' +
						control +
					'</div>';
			});

			html += '</div>';
			formWrap.innerHTML = html;
			mountDatePickers();
			setDetailActionsDisabled(false);
		}

		function openDetail(productId) {
			if (!productId) {
				return;
			}

			currentProductId = productId;
			historyLoaded = false;
			historyDirty = true;
			hideDetailAlert();
			setDetailLoading(true);
			setDetailActionsDisabled(true);
			showInfoTab();

			destroyDatePickers();

			if (formWrap) {
				formWrap.innerHTML = '';
			}

			if (window.EntityHistory) {
				EntityHistory.clear(historyWrap);
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

			fetchApi(settings.detailUrl.replace(/\/$/, '') + '/' + productId, {
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
			if (!currentProductId || !settings.downloadUrl || button.disabled) {
				return;
			}

			button.disabled = true;

			fetch(settings.downloadUrl.replace(/\/$/, '') + '/' + currentProductId, {
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

		function deleteProduct(productId) {
			if (!productId || !settings.deleteUrl) {
				return;
			}

			showConfirm({
				title: t('common_delete'),
				message: t('product_delete_confirm'),
				confirmLabel: t('common_delete'),
			}).then(function () {
				return fetchApi(settings.deleteUrl.replace(/\/$/, '') + '/' + productId, { method: 'POST' });
			}).then(function (result) {
				showToast(result.message || t('product_deleted'), 'success');

				if (String(currentProductId) === String(productId)) {
					detailModal.hide();
				}

				notifyChanged();
			}).catch(function (error) {
				if (window.isConfirmCancelled && isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || t('product_delete_failed'), 'error');
			});
		}

		function restoreProduct(productId) {
			if (!productId || !settings.restoreUrl) {
				return;
			}

			const previewUrl = (settings.restorePreviewUrl || (settings.rowAction && settings.rowAction.previewUrl) || '').replace(/\/$/, '');
			const restoreUrl = settings.restoreUrl.replace(/\/$/, '') + '/' + productId;

			fetchApi(previewUrl + '/' + productId).then(function (preview) {
				const data = preview.data || {};
				const message = data.needs_organization
					? t('product_restore_with_org', { name: data.organization_name || '' })
					: t('product_restore_confirm');

				return showConfirm({
					title: t('common_restore'),
					message: message,
					confirmLabel: t('common_restore'),
					confirmClass: 'btn btn-success',
				}).then(function () {
					const body = new FormData();

					if (data.needs_organization) {
						body.append('restore_organization', '1');
					}

					return fetchApi(restoreUrl, { method: 'POST', body: body });
				});
			}).then(function (result) {
				if (!result) {
					return;
				}

				showToast(result.message || t('product_restored'), 'success');

				if (String(currentProductId) === String(productId)) {
					detailModal.hide();
				}

				notifyChanged();
			}).catch(function (error) {
				if (window.isConfirmCancelled && isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || t('product_restore_failed'), 'error');
			});
		}

		if (detailForm) {
			detailForm.addEventListener('submit', function (event) {
				event.preventDefault();

				if (!canEdit || !currentProductId || !saveBtn || saveBtn.disabled) {
					return;
				}

				hideDetailAlert();
				setDetailSaving(true);

				if (window.JqxDate && typeof window.JqxDate.syncForm === 'function') {
					window.JqxDate.syncForm(detailForm);
				}

				fetchApi(settings.updateUrl.replace(/\/$/, '') + '/' + currentProductId, {
					method: 'POST',
					body: new FormData(detailForm),
				}).then(function (result) {
					const record = result.data || {};
					currentProductId = record.id || currentProductId;

					if (modalTitle) {
						modalTitle.textContent = record.name || t('title_products');
					}

					renderDetailForm(record);
					historyDirty = true;
					notifyChanged();
					showToast(result.message || t('common_record_updated'), 'success');
				}).catch(function (error) {
					showDetailAlert(error.message || t('common_failed_update'));
				}).finally(function () {
					setDetailSaving(false);
				});
			});
		}

		if (historyTabBtn) {
			historyTabBtn.addEventListener('shown.mdb.tab', function () {
				loadHistory(false);
			});
		}

		if (downloadBtn) {
			downloadBtn.addEventListener('click', function () {
				downloadProduct(downloadBtn);
			});
		}

		if (deleteBtn) {
			deleteBtn.addEventListener('click', function () {
				deleteProduct(currentProductId);
			});
		}

		if (restoreBtn) {
			restoreBtn.addEventListener('click', function () {
				restoreProduct(currentProductId);
			});
		}

		return {
			open: openDetail,
			deleteProduct: deleteProduct,
			restoreProduct: restoreProduct,
			currentId: function () {
				return currentProductId;
			},
			hide: function () {
				detailModal.hide();
			},
		};
	}

	window.ProductDetailModal = {
		create: createProductDetailModal,
	};
})();
