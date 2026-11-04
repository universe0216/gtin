(function () {
	'use strict';

	if (typeof window.CATEGORIES_CONFIG === 'undefined') {
		return;
	}

	const config = window.CATEGORIES_CONFIG;
	const canEdit = !!config.canEdit;
	const baseUrl = config.baseUrl.replace(/\/$/, '');
	const treeEl = document.getElementById('categoryTree');
	const emptyEl = document.getElementById('categoryTreeEmpty');
	const searchInput = document.getElementById('categorySearchInput');

	if (!treeEl) {
		return;
	}

	const LEVEL = {
		1: t('category_segment'),
		2: t('category_family'),
		3: t('category_class'),
		4: t('category_brick'),
	};

	let records = (config.records || []).slice();
	const expanded = {};
	let query = '';
	let childrenByParent = {};
	let byId = {};

	function rebuildIndex() {
		childrenByParent = { '': [] };
		byId = {};

		records.forEach(function (record) {
			byId[record.id] = record;
			const key = record.parent_id == null ? '' : String(record.parent_id);

			if (!childrenByParent[key]) {
				childrenByParent[key] = [];
			}

			childrenByParent[key].push(record);
		});

		Object.keys(childrenByParent).forEach(function (key) {
			childrenByParent[key].sort(function (left, right) {
				const order = (Number(left.sort_order) || 0) - (Number(right.sort_order) || 0);

				if (order !== 0) {
					return order;
				}

				return String(left.name).localeCompare(String(right.name));
			});
		});
	}

	function childrenOf(parentId) {
		const key = parentId == null ? '' : String(parentId);
		return childrenByParent[key] || [];
	}

	function matchesQuery(record) {
		if (!query) {
			return true;
		}

		const haystack = (record.name + ' ' + (record.original_name || '') + ' ' + (record.gpc_code || '')).toLowerCase();
		return haystack.indexOf(query) !== -1;
	}

	function visibleSet() {
		if (!query) {
			return null;
		}

		const visible = {};

		records.forEach(function (record) {
			if (!matchesQuery(record)) {
				return;
			}

			let current = record;

			while (current && !visible[current.id]) {
				visible[current.id] = true;
				current = current.parent_id == null ? null : byId[current.parent_id];
			}
		});

		return visible;
	}

	function levelLabel(level) {
		return LEVEL[Number(level)] || '';
	}

	function renderBranch(parentId, depth, visible) {
		return childrenOf(parentId).filter(function (record) {
			return !visible || visible[record.id];
		}).map(function (record) {
			return renderRow(record, depth, visible);
		}).join('');
	}

	function renderRow(record, depth, visible) {
		const children = childrenOf(record.id).filter(function (child) {
			return !visible || visible[child.id];
		});
		const isOpen = query ? children.length > 0 : expanded[record.id] === true;
		const toggleClass = 'location-toggle' + (children.length ? '' : ' location-toggle-spacer');
		let actions = '';

		if (canEdit) {
			actions = '<button type="button" class="btn btn-sm btn-secondary location-compact-btn category-edit-btn" data-id="' + escapeAttr(record.id) + '">' +
				'<i class="fas fa-pen me-1"></i>' + escapeHtml(t('common_edit')) +
			'</button>';
		}

		const row = '<div class="category-row" style="--location-depth:' + depth + '">' +
			'<button type="button" class="' + toggleClass + '" data-id="' + escapeAttr(record.id) + '" aria-expanded="' + (isOpen ? 'true' : 'false') + '"' + (children.length ? '' : ' disabled') + '>' +
				'<i class="fas fa-chevron-' + (isOpen ? 'down' : 'right') + '"></i>' +
			'</button>' +
			'<span class="location-name">' + escapeHtml(record.name) + '</span>' +
			'<span class="category-original">' + escapeHtml(record.original_name || '') + '</span>' +
			'<span class="location-tag">' + escapeHtml(record.gpc_code || '') + '</span>' +
			'<span class="category-level">' + escapeHtml(levelLabel(record.level)) + '</span>' +
			'<span class="location-actions">' + actions + '</span>' +
		'</div>';

		return row + (children.length && isOpen ? renderBranch(record.id, depth + 1, visible) : '');
	}

	function renderTree() {
		const visible = visibleSet();
		const header = '<div class="category-head">' +
			'<span></span>' +
			'<span>' + escapeHtml(t('col_name')) + '</span>' +
			'<span>' + escapeHtml(t('category_original_name')) + '</span>' +
			'<span>' + escapeHtml(t('category_code')) + '</span>' +
			'<span>' + escapeHtml(t('category_level')) + '</span>' +
			'<span>' + escapeHtml(t('common_actions')) + '</span>' +
		'</div>';
		const roots = renderBranch(null, 0, visible);
		treeEl.innerHTML = header + '<div class="location-body">' + roots + '</div>';

		if (!emptyEl) {
			return;
		}

		if (roots) {
			emptyEl.classList.add('d-none');
			emptyEl.textContent = '';
			return;
		}

		emptyEl.textContent = query ? t('category_no_matches') : t('category_empty');
		emptyEl.classList.remove('d-none');
	}

	rebuildIndex();
	renderTree();

	if (searchInput) {
		searchInput.addEventListener('input', debounce(function () {
			query = searchInput.value.trim().toLowerCase();
			renderTree();
		}, 200));
	}

	treeEl.addEventListener('click', function (event) {
		const toggle = event.target.closest('.location-toggle');

		if (toggle && !toggle.disabled && !query) {
			const id = toggle.getAttribute('data-id');
			expanded[id] = expanded[id] !== true;
			renderTree();
		}
	});

	if (!canEdit) {
		return;
	}

	const modalEl = document.getElementById('categoryModal');
	const formEl = document.getElementById('categoryForm');
	const alertEl = document.getElementById('categoryModalAlert');
	const recordInput = document.getElementById('categoryRecordId');
	const originalInput = document.getElementById('categoryOriginalName');
	const codeInput = document.getElementById('categoryCode');
	const nameInput = document.getElementById('categoryName');
	const saveBtn = document.getElementById('categorySaveBtn');
	const modal = modalEl && typeof mdb !== 'undefined' ? mdb.Modal.getOrCreateInstance(modalEl) : null;

	function hideAlert() {
		if (!alertEl) {
			return;
		}

		alertEl.classList.add('d-none');
		alertEl.textContent = '';
	}

	function showAlert(message) {
		if (!alertEl) {
			return;
		}

		alertEl.textContent = message;
		alertEl.classList.remove('d-none');
	}

	function setSaving(isSaving) {
		if (!saveBtn) {
			return;
		}

		saveBtn.disabled = isSaving;
		const spinner = saveBtn.querySelector('.spinner-border');

		if (spinner) {
			spinner.classList.toggle('d-none', !isSaving);
		}
	}

	function findRecord(id) {
		return byId[id] || byId[Number(id)] || null;
	}

	treeEl.addEventListener('click', function (event) {
		const editBtn = event.target.closest('.category-edit-btn');

		if (!editBtn) {
			return;
		}

		const record = findRecord(editBtn.getAttribute('data-id'));

		if (!record) {
			return;
		}

		hideAlert();
		recordInput.value = record.id;
		originalInput.value = record.original_name || '';
		codeInput.value = record.gpc_code || '';
		nameInput.value = record.name || '';

		if (modal) {
			modal.show();
		}

		window.setTimeout(function () {
			nameInput.focus();
			nameInput.select();
		}, 150);
	});

	formEl.addEventListener('submit', function (event) {
		event.preventDefault();
		hideAlert();
		setSaving(true);

		const id = recordInput.value;
		const body = new FormData(formEl);

		fetchApi(baseUrl + '/update/' + id, { method: 'POST', body: body }).then(function (result) {
			const record = result.data;

			if (record) {
				const index = records.findIndex(function (item) {
					return Number(item.id) === Number(record.id);
				});

				if (index >= 0) {
					records[index] = record;
					rebuildIndex();
				}
			}

			renderTree();
			showToast(result.message || t('common_record_updated'), 'success');

			if (modal) {
				modal.hide();
			}
		}).catch(function (error) {
			showAlert(error.message || t('common_failed_update'));
		}).finally(function () {
			setSaving(false);
		});
	});
})();
