(function () {
	'use strict';

	if (typeof window.SOURCE_MANAGEMENT_CONFIG === 'undefined') {
		return;
	}

	const config = window.SOURCE_MANAGEMENT_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');
	const sizeDiffRatio = Number(config.sizeDiffRatio) > 0 ? Number(config.sizeDiffRatio) : 0.2;
	const $ = window.jQuery;

	const treeHost = document.getElementById('sourceFileTree');
	const emptyEl = document.getElementById('sourceDetailEmpty');
	const contentEl = document.getElementById('sourceDetailContent');
	const pathEl = document.getElementById('sourceSelectedPath');
	const metaEl = document.getElementById('sourceSelectedMeta');
	const replaceBtn = document.getElementById('sourceReplaceBtn');
	const replaceForm = document.getElementById('sourceReplaceForm');
	const replacePath = document.getElementById('sourceReplacePath');
	const replaceInput = document.getElementById('sourceReplaceInput');
	const historyBody = document.getElementById('sourceHistoryBody');
	const historyEmpty = document.getElementById('sourceHistoryEmpty');
	const historyTable = document.getElementById('sourceHistoryTable');

	let selectedPath = '';
	let selectedBasename = '';
	let selectedExtension = '';
	let selectedSize = 0;

	if (!treeHost || !$ || !$.fn || !$.fn.jqxTree) {
		return;
	}

	function mapTreeNodes(nodes) {
		return (nodes || []).map(function (node) {
			const mapped = {
				label: node.label,
				value: node.value || '',
				icon: node.icon || (node.items ? 'fas fa-folder' : 'fas fa-file-code'),
				expanded: !!node.expanded,
			};

			if (node.items && node.items.length) {
				mapped.items = mapTreeNodes(node.items);
			}

			return mapped;
		});
	}

	function initTree(source) {
		const $tree = $(treeHost);

		if ($tree.data('jqxTree')) {
			$tree.jqxTree('destroy');
		}

		$tree.jqxTree({
			source: mapTreeNodes(source),
			width: '100%',
			height: '100%',
			theme: 'dark',
			allowDrag: false,
			allowDrop: false,
			toggleMode: 'click',
		});

		$tree.off('select.sourceManagement');
		$tree.on('select.sourceManagement', function (event) {
			if (!event.args || !event.args.element) {
				return;
			}

			const item = $tree.jqxTree('getItem', event.args.element);

			if (!isLeafFile(item, event.args.element)) {
				return;
			}

			loadFile(String(item.value));
		});
	}

	function isLeafFile(item, element) {
		if (!item || !item.value) {
			return false;
		}

		if (item.hasItems) {
			return false;
		}

		if (item.items && item.items.length) {
			return false;
		}

		if (element && $(element).children('ul').children('li').length > 0) {
			return false;
		}

		return /\.(php|js|css)$/i.test(String(item.value));
	}

	function clearSelection() {
		selectedPath = '';
		selectedBasename = '';
		selectedExtension = '';
		selectedSize = 0;
		emptyEl.classList.remove('d-none');
		contentEl.classList.add('d-none');
		replaceBtn.disabled = true;
		replacePath.value = '';
		replaceInput.value = '';
	}

	function formatSize(bytes) {
		const size = Number(bytes) || 0;

		if (size < 1024) {
			return size + ' B';
		}

		if (size < 1024 * 1024) {
			return (size / 1024).toFixed(1) + ' KB';
		}

		return (size / (1024 * 1024)).toFixed(1) + ' MB';
	}

	function sizeDiffersMuch(originalSize, uploadSize) {
		const original = Math.max(Number(originalSize) || 0, 1);
		const uploaded = Math.max(Number(uploadSize) || 0, 0);
		const ratio = Math.abs(uploaded - original) / original;
		return ratio >= sizeDiffRatio;
	}

	function renderHistory(rows) {
		historyBody.innerHTML = '';

		if (!rows || !rows.length) {
			historyEmpty.classList.remove('d-none');
			historyTable.classList.add('d-none');
			return;
		}

		historyEmpty.classList.add('d-none');
		historyTable.classList.remove('d-none');

		rows.forEach(function (row) {
			const tr = document.createElement('tr');
			tr.innerHTML =
				'<td>' + escapeHtml(row.created_at || '') + '</td>' +
				'<td>' + escapeHtml(row.account_name || row.username || '') + '</td>' +
				'<td>' + escapeHtml(row.uploaded_name || '') + '</td>' +
				'<td><code class="source-backup-path">' + escapeHtml(row.backup_path || '') + '</code></td>' +
				'<td class="text-end">' +
					'<button type="button" class="btn btn-sm btn-outline-secondary source-restore-btn" data-history-id="' +
						escapeHtml(String(row.id || '')) +
					'">' +
						'<i class="fas fa-clock-rotate-left me-1"></i>' + escapeHtml(t('source_restore')) +
					'</button>' +
				'</td>';
			historyBody.appendChild(tr);
		});
	}

	function showFile(file, history) {
		selectedPath = file.relative_path;
		selectedBasename = file.basename || selectedPath.split('/').pop() || '';
		selectedExtension = file.extension || '';
		selectedSize = Number(file.size) || 0;
		emptyEl.classList.add('d-none');
		contentEl.classList.remove('d-none');
		pathEl.textContent = file.relative_path;
		metaEl.textContent = t('source_file_meta', {
			size: formatSize(file.size),
			modified: file.modified_at || '',
		});
		replacePath.value = file.relative_path;
		replaceBtn.disabled = false;
		replaceInput.accept = '.' + selectedExtension;
		renderHistory(history || []);
	}

	function loadFile(path) {
		fetchApi(baseUrl + '/file_info?path=' + encodeURIComponent(path))
			.then(function (result) {
				showFile(result.file, result.history);
			})
			.catch(function (error) {
				clearSelection();
				showToast(error.message || t('source_file_not_found'), 'error');
			});
	}

	function startReplace() {
		if (!selectedPath || replaceBtn.disabled) {
			return;
		}

		replaceInput.value = '';
		replaceInput.click();
	}

	function confirmReplace(message) {
		return showConfirm({
			title: t('source_replace_confirm_title'),
			message: message,
			confirmLabel: t('source_replace_file'),
		});
	}

	function uploadReplace() {
		const file = replaceInput.files && replaceInput.files[0];

		if (!file || !selectedPath) {
			return;
		}

		if (file.name !== selectedBasename) {
			showToast(t('source_name_mismatch'), 'error');
			replaceInput.value = '';
			return;
		}

		const uploadedExt = (file.name.split('.').pop() || '').toLowerCase();

		if (selectedExtension && uploadedExt !== selectedExtension) {
			showToast(t('source_extension_mismatch'), 'error');
			replaceInput.value = '';
			return;
		}

		let confirmPromise;

		if (sizeDiffersMuch(selectedSize, file.size)) {
			confirmPromise = confirmReplace(t('source_size_diff_confirm', {
				file: selectedPath,
				original: formatSize(selectedSize),
				uploaded: formatSize(file.size),
			}));
		} else {
			confirmPromise = confirmReplace(t('source_replace_confirm', { file: selectedPath }));
		}

		confirmPromise
			.then(function () {
				const formData = new FormData(replaceForm);
				replaceBtn.disabled = true;

				return fetchApi(baseUrl + '/replace', {
					method: 'POST',
					body: formData,
				});
			})
			.then(function (result) {
				showToast(result.message || t('source_replace_success'), 'success');
				showFile(result.file || {
					relative_path: selectedPath,
					basename: selectedBasename,
					extension: selectedExtension,
					size: file.size,
					modified_at: '',
				}, result.history || []);
			})
			.catch(function (error) {
				if (window.isConfirmCancelled && window.isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || t('source_replace_failed'), 'error');
			})
			.finally(function () {
				replaceBtn.disabled = !selectedPath;
				replaceInput.value = '';
			});
	}

	function restoreHistory(historyId) {
		if (!historyId || !selectedPath) {
			return;
		}

		showConfirm({
			title: t('source_restore_confirm_title'),
			message: t('source_restore_confirm', { file: selectedPath }),
			confirmLabel: t('source_restore'),
		})
			.then(function () {
				const formData = new FormData();
				formData.append('history_id', historyId);

				return fetchApi(baseUrl + '/restore', {
					method: 'POST',
					body: formData,
				});
			})
			.then(function (result) {
				showToast(result.message || t('source_restore_success'), 'success');
				showFile(result.file, result.history || []);
			})
			.catch(function (error) {
				if (window.isConfirmCancelled && window.isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || t('source_restore_failed'), 'error');
			});
	}

	historyBody.addEventListener('click', function (event) {
		const button = event.target.closest('.source-restore-btn');

		if (!button) {
			return;
		}

		restoreHistory(button.getAttribute('data-history-id'));
	});

	initTree(config.tree || []);
	clearSelection();

	replaceBtn.addEventListener('click', startReplace);
	replaceInput.addEventListener('change', uploadReplace);
})();
