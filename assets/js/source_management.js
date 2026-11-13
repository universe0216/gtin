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
	const folderEl = document.getElementById('sourceFolderContent');
	const contentEl = document.getElementById('sourceDetailContent');
	const folderPathEl = document.getElementById('sourceSelectedFolderPath');
	const pathEl = document.getElementById('sourceSelectedPath');
	const metaEl = document.getElementById('sourceSelectedMeta');
	const replaceBtn = document.getElementById('sourceReplaceBtn');
	const addBtn = document.getElementById('sourceAddBtn');
	const replaceForm = document.getElementById('sourceReplaceForm');
	const addForm = document.getElementById('sourceAddForm');
	const replacePath = document.getElementById('sourceReplacePath');
	const addFolder = document.getElementById('sourceAddFolder');
	const replaceInput = document.getElementById('sourceReplaceInput');
	const addInput = document.getElementById('sourceAddInput');
	const historyBody = document.getElementById('sourceHistoryBody');
	const historyEmpty = document.getElementById('sourceHistoryEmpty');
	const historyTable = document.getElementById('sourceHistoryTable');

	let selectedPath = '';
	let selectedFolder = '';
	let selectedBasename = '';
	let selectedExtension = '';
	let selectedSize = 0;
	let treeSource = config.tree || [];

	if (!treeHost || !$ || !$.fn || !$.fn.jqxTree) {
		return;
	}

	function mapTreeNodes(nodes) {
		return (nodes || []).map(function (node) {
			const mapped = {
				label: node.label,
				value: node.value || '',
				type: node.type || (node.items ? 'folder' : 'file'),
				icon: node.icon || (node.items ? 'fas fa-folder' : 'fas fa-file-code'),
				expanded: !!node.expanded,
			};

			if (node.items && node.items.length) {
				mapped.items = mapTreeNodes(node.items);
			}

			return mapped;
		});
	}

	function initTree(source, selectValue) {
		treeSource = source || [];
		const $tree = $(treeHost);

		if ($tree.data('jqxTree')) {
			$tree.jqxTree('destroy');
		}

		$tree.jqxTree({
			source: mapTreeNodes(treeSource),
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

			if (!item || !item.value) {
				return;
			}

			if (isFolderItem(item, event.args.element)) {
				showFolder(String(item.value));
				return;
			}

			if (!isLeafFile(item, event.args.element)) {
				return;
			}

			loadFile(String(item.value));
		});

		if (selectValue) {
			selectTreeValue(selectValue);
		}
	}

	function selectTreeValue(value) {
		const $tree = $(treeHost);
		const items = $tree.jqxTree('getItems') || [];
		const match = items.filter(function (item) {
			return String(item.value) === String(value);
		})[0];

		if (!match) {
			return;
		}

		let parent = match.parentElement;

		while (parent) {
			$tree.jqxTree('expandItem', parent);
			const parentItem = $tree.jqxTree('getItem', parent);
			parent = parentItem ? parentItem.parentElement : null;
		}

		$tree.jqxTree('selectItem', match.element);
	}

	function isFolderItem(item, element) {
		if (!item || !item.value) {
			return false;
		}

		if (item.type === 'folder') {
			return true;
		}

		if (item.hasItems || (item.items && item.items.length)) {
			return true;
		}

		if (element && $(element).children('ul').children('li').length > 0) {
			return true;
		}

		return !/\.(php|js|css)$/i.test(String(item.value));
	}

	function isLeafFile(item, element) {
		if (!item || !item.value) {
			return false;
		}

		if (isFolderItem(item, element)) {
			return false;
		}

		return /\.(php|js|css)$/i.test(String(item.value));
	}

	function clearSelection() {
		selectedPath = '';
		selectedFolder = '';
		selectedBasename = '';
		selectedExtension = '';
		selectedSize = 0;
		emptyEl.classList.remove('d-none');
		folderEl.classList.add('d-none');
		contentEl.classList.add('d-none');
		replaceBtn.disabled = true;
		replacePath.value = '';
		addFolder.value = '';
		replaceInput.value = '';
		addInput.value = '';
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
			const canRestore = !!(row.backup_path && String(row.backup_path).trim());
			const tr = document.createElement('tr');
			tr.innerHTML =
				'<td>' + escapeHtml(row.created_at || '') + '</td>' +
				'<td>' + escapeHtml(row.account_name || row.username || '') + '</td>' +
				'<td>' + escapeHtml(row.uploaded_name || '') + '</td>' +
				'<td><code class="source-backup-path">' + escapeHtml(row.backup_path || '-') + '</code></td>' +
				'<td class="text-end">' +
					(canRestore
						? '<button type="button" class="btn btn-sm btn-outline-secondary source-restore-btn" data-history-id="' +
							escapeHtml(String(row.id || '')) +
						'">' +
							'<i class="fas fa-clock-rotate-left me-1"></i>' + escapeHtml(t('source_restore')) +
						'</button>'
						: '') +
				'</td>';
			historyBody.appendChild(tr);
		});
	}

	function showFolder(path) {
		selectedFolder = path;
		selectedPath = '';
		selectedBasename = '';
		selectedExtension = '';
		selectedSize = 0;
		emptyEl.classList.add('d-none');
		contentEl.classList.add('d-none');
		folderEl.classList.remove('d-none');
		folderPathEl.textContent = path;
		addFolder.value = path;
		replaceBtn.disabled = true;
		replacePath.value = '';
	}

	function showFile(file, history) {
		selectedPath = file.relative_path;
		selectedFolder = '';
		selectedBasename = file.basename || selectedPath.split('/').pop() || '';
		selectedExtension = file.extension || '';
		selectedSize = Number(file.size) || 0;
		emptyEl.classList.add('d-none');
		folderEl.classList.add('d-none');
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

	function startAdd() {
		if (!selectedFolder) {
			return;
		}

		addInput.value = '';
		addInput.click();
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

	function uploadAdd() {
		const file = addInput.files && addInput.files[0];

		if (!file || !selectedFolder) {
			return;
		}

		if (!/^[A-Za-z0-9][A-Za-z0-9._-]*\.(php|js|css)$/.test(file.name)) {
			showToast(t('source_invalid_filename'), 'error');
			addInput.value = '';
			return;
		}

		showConfirm({
			title: t('source_add_confirm_title'),
			message: t('source_add_confirm', {
				file: file.name,
				folder: selectedFolder,
			}),
			confirmLabel: t('source_add_file'),
		})
			.then(function () {
				const formData = new FormData(addForm);
				addBtn.disabled = true;

				return fetchApi(baseUrl + '/add', {
					method: 'POST',
					body: formData,
				});
			})
			.then(function (result) {
				showToast(result.message || t('source_add_success'), 'success');
				initTree(result.tree || treeSource, result.file && result.file.relative_path);
				if (result.file) {
					showFile(result.file, result.history || []);
				}
			})
			.catch(function (error) {
				if (window.isConfirmCancelled && window.isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || t('source_add_failed'), 'error');
			})
			.finally(function () {
				addBtn.disabled = false;
				addInput.value = '';
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

	initTree(treeSource);
	clearSelection();

	replaceBtn.addEventListener('click', startReplace);
	addBtn.addEventListener('click', startAdd);
	replaceInput.addEventListener('change', uploadReplace);
	addInput.addEventListener('change', uploadAdd);
})();
