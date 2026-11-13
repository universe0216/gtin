(function () {
	'use strict';

	if (typeof window.SETTINGS_CONFIG === 'undefined') {
		return;
	}

	const config = window.SETTINGS_CONFIG;
	const keysList = document.getElementById('settingsKeysList');
	const editorEmpty = document.getElementById('settingsEditorEmpty');
	const editor = document.getElementById('settingsEditor');
	const selectedKeyEl = document.getElementById('settingsSelectedKey');
	const descriptionInput = document.getElementById('settingsDescriptionInput');
	const pairsBody = document.getElementById('settingsPairsBody');
	const editorAlert = document.getElementById('settingsEditorAlert');
	const addPairBtn = document.getElementById('settingsAddPairBtn');
	const saveBtn = document.getElementById('settingsSaveBtn');
	const deleteKeyBtn = document.getElementById('settingsDeleteKeyBtn');
	const addKeyBtn = document.getElementById('settingsAddKeyBtn');

	let currentKey = null;

	function hideAlert() {
		if (!editorAlert) {
			return;
		}

		editorAlert.className = 'alert d-none mb-0';
		editorAlert.textContent = '';
	}

	function showAlert(message, variant) {
		if (!editorAlert) {
			return;
		}

		editorAlert.className = 'alert alert-' + (variant || 'danger') + ' mb-0';
		editorAlert.textContent = message || '';
	}

	function postForm(url, fields) {
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

	function pairRowHtml(field, value) {
		return (
			'<tr class="settings-pair-row">' +
				'<td><input type="text" class="form-control form-control-sm settings-pair-field" value="' + escapeAttr(field || '') + '"></td>' +
				'<td><input type="text" class="form-control form-control-sm settings-pair-value" value="' + escapeAttr(value || '') + '"></td>' +
				'<td class="text-end">' +
					'<button type="button" class="btn btn-sm btn-outline-danger settings-pair-remove" title="' + escapeAttr(t('common_delete')) + '">' +
						'<i class="fas fa-times"></i>' +
					'</button>' +
				'</td>' +
			'</tr>'
		);
	}

	function collectPairs() {
		const pairs = [];

		if (!pairsBody) {
			return pairs;
		}

		pairsBody.querySelectorAll('.settings-pair-row').forEach(function (row) {
			const fieldInput = row.querySelector('.settings-pair-field');
			const valueInput = row.querySelector('.settings-pair-value');
			pairs.push({
				field: fieldInput ? fieldInput.value.trim() : '',
				value: valueInput ? valueInput.value : '',
			});
		});

		return pairs;
	}

	function setActiveKey(key) {
		if (!keysList) {
			return;
		}

		keysList.querySelectorAll('.settings-key-item').forEach(function (item) {
			item.classList.toggle('active', item.getAttribute('data-key') === key);
		});
	}

	function renderKeys(items, selectKey) {
		if (!keysList) {
			return;
		}

		if (!items || !items.length) {
			keysList.innerHTML = '<div class="text-muted small px-3 py-4" id="settingsKeysEmpty">' +
				escapeHtml(t('settings_empty')) +
			'</div>';
			return;
		}

		keysList.innerHTML = items.map(function (item) {
			return (
				'<button type="button" class="list-group-item list-group-item-action settings-key-item" data-key="' +
					escapeAttr(item.key) +
				'">' +
					'<div class="settings-key-name fw-semibold">' + escapeHtml(item.key) + '</div>' +
					'<div class="settings-key-desc text-muted small">' + escapeHtml(item.description || '—') + '</div>' +
				'</button>'
			);
		}).join('');

		if (selectKey) {
			setActiveKey(selectKey);
		}
	}

	function updateKeyDescriptionInList(key, description) {
		if (!keysList) {
			return;
		}

		const item = Array.prototype.find.call(
			keysList.querySelectorAll('.settings-key-item'),
			function (node) {
				return node.getAttribute('data-key') === key;
			}
		);

		if (!item) {
			return;
		}

		const desc = item.querySelector('.settings-key-desc');

		if (desc) {
			desc.textContent = description || '—';
		}
	}

	function showEditor(data) {
		currentKey = data.key;

		if (editorEmpty) {
			editorEmpty.classList.add('d-none');
		}

		if (editor) {
			editor.classList.remove('d-none');
		}

		if (selectedKeyEl) {
			selectedKeyEl.textContent = data.key;
		}

		if (descriptionInput) {
			descriptionInput.value = data.description || '';
		}

		if (pairsBody) {
			const pairs = data.pairs || [];
			pairsBody.innerHTML = pairs.length
				? pairs.map(function (pair) {
					return pairRowHtml(pair.field, pair.value);
				}).join('')
				: pairRowHtml('', '');
		}

		hideAlert();
		setActiveKey(data.key);
	}

	function hideEditor() {
		currentKey = null;

		if (editor) {
			editor.classList.add('d-none');
		}

		if (editorEmpty) {
			editorEmpty.classList.remove('d-none');
		}

		hideAlert();
		setActiveKey(null);
	}

	function loadItem(key) {
		if (!key) {
			return;
		}

		hideAlert();

		fetchApi(config.getUrl + '?key=' + encodeURIComponent(key)).then(function (result) {
			showEditor(result.data || {});
		}).catch(function (error) {
			showAlert(error.message || t('settings_load_failed'), 'danger');
			showToast(error.message || t('settings_load_failed'), 'error');
		});
	}

	function reloadKeys(selectKey) {
		return fetchApi(config.listUrl).then(function (result) {
			renderKeys(result.data || [], selectKey || null);

			if (selectKey) {
				loadItem(selectKey);
			} else {
				hideEditor();
			}
		});
	}

	if (keysList) {
		keysList.addEventListener('click', function (event) {
			const button = event.target.closest('.settings-key-item');

			if (!button) {
				return;
			}

			loadItem(button.getAttribute('data-key'));
		});
	}

	if (addPairBtn) {
		addPairBtn.addEventListener('click', function () {
			if (!pairsBody) {
				return;
			}

			pairsBody.insertAdjacentHTML('beforeend', pairRowHtml('', ''));
		});
	}

	if (pairsBody) {
		pairsBody.addEventListener('click', function (event) {
			const button = event.target.closest('.settings-pair-remove');

			if (!button) {
				return;
			}

			const row = button.closest('.settings-pair-row');

			if (row) {
				row.remove();
			}

			if (!pairsBody.querySelector('.settings-pair-row')) {
				pairsBody.insertAdjacentHTML('beforeend', pairRowHtml('', ''));
			}
		});
	}

	if (saveBtn) {
		saveBtn.addEventListener('click', function () {
			if (!currentKey) {
				return;
			}

			hideAlert();
			saveBtn.disabled = true;

			postForm(config.saveUrl, {
				key: currentKey,
				description: descriptionInput ? descriptionInput.value : '',
				pairs: JSON.stringify(collectPairs()),
			}).then(function (result) {
				showAlert(result.message || t('settings_saved'), 'success');
				showToast(result.message || t('settings_saved'), 'success');
				updateKeyDescriptionInList(currentKey, descriptionInput ? descriptionInput.value : '');
			}).catch(function (error) {
				showAlert(error.message || t('settings_save_failed'), 'danger');
				showToast(error.message || t('settings_save_failed'), 'error');
			}).finally(function () {
				saveBtn.disabled = false;
			});
		});
	}

	if (deleteKeyBtn) {
		deleteKeyBtn.addEventListener('click', function () {
			if (!currentKey) {
				return;
			}

			const key = currentKey;

			showConfirm({
				title: t('common_delete'),
				message: t('settings_delete_confirm', { key: key }),
				confirmLabel: t('common_delete'),
			}).then(function () {
				return postForm(config.deleteUrl, { key: key });
			}).then(function (result) {
				showToast(result.message || t('settings_deleted'), 'success');
				return reloadKeys(null);
			}).catch(function (error) {
				if (window.isConfirmCancelled && isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || t('settings_delete_failed'), 'error');
			});
		});
	}

	if (addKeyBtn) {
		addKeyBtn.addEventListener('click', function () {
			const key = window.prompt(t('settings_prompt_key'), '');

			if (key === null) {
				return;
			}

			const trimmed = String(key).trim();

			if (!trimmed) {
				showToast(t('settings_invalid_key'), 'error');
				return;
			}

			const description = window.prompt(t('settings_prompt_description'), '') || '';

			postForm(config.createUrl, {
				key: trimmed,
				description: description,
			}).then(function (result) {
				showToast(result.message || t('settings_created'), 'success');
				return reloadKeys(trimmed);
			}).catch(function (error) {
				showToast(error.message || t('settings_create_failed'), 'error');
			});
		});
	}
})();
