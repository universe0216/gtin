(function () {
	'use strict';

	if (typeof window.LOCATIONS_CONFIG === 'undefined') {
		return;
	}

	const config = window.LOCATIONS_CONFIG;
	const canEdit = !!config.canEdit;
	const baseUrl = config.baseUrl.replace(/\/$/, '');
	const treeEl = document.getElementById('locationTree');
	const emptyEl = document.getElementById('locationTreeEmpty');
	const searchInput = document.getElementById('locationSearchInput');

	if (!treeEl) {
		return;
	}

	const LEVEL = {
		1: { addLabel: t('location_add'), addLevel: 2 },
		2: { addLabel: t('location_add'), addLevel: 3 },
		3: { addLabel: '', addLevel: 0 },
	};

	let records = (config.records || []).slice();
	const expanded = {};
	let query = '';

	records.forEach(function (record) {
		expanded[record.id] = Number(record.level) !== 1;
	});

	function sameParent(record, parentId) {
		const current = record.parent_id == null ? '' : String(record.parent_id);
		const wanted = parentId == null ? '' : String(parentId);
		return current === wanted;
	}

	function childrenOf(parentId) {
		return records
			.filter(function (record) {
				return sameParent(record, parentId);
			})
			.sort(function (left, right) {
				const order = (Number(left.sort_order) || 0) - (Number(right.sort_order) || 0);

				if (order !== 0) {
					return order;
				}

				return String(left.name).localeCompare(String(right.name));
			});
	}

	function matchesQuery(record) {
		if (!query) {
			return true;
		}

		const haystack = (record.name + ' ' + (record.tag || '')).toLowerCase();
		return haystack.indexOf(query) !== -1;
	}

	function branchMatches(record) {
		if (matchesQuery(record)) {
			return true;
		}

		return childrenOf(record.id).some(branchMatches);
	}

	function levelMeta(level) {
		return LEVEL[Number(level)] || LEVEL[1];
	}

	function actionButton(className, icon, label, attrs) {
		return '<button type="button" class="btn btn-sm ' + className + '" ' + attrs + '>' +
			'<i class="fas ' + icon + ' me-1"></i>' + escapeHtml(label) +
		'</button>';
	}

	function iconButton(className, icon, label, attrs, disabled) {
		return '<button type="button" class="btn btn-sm ' + className + '" ' + attrs + (disabled ? ' disabled' : '') +
			' title="' + escapeAttr(label) + '" aria-label="' + escapeAttr(label) + '">' +
			'<i class="fas ' + icon + '"></i></button>';
	}

	function renderBranch(parentId, depth) {
		return childrenOf(parentId).filter(function (record) {
			return !query || branchMatches(record);
		}).map(function (record) {
			return renderRow(record, depth);
		}).join('');
	}

	function renderRow(record, depth) {
		const meta = levelMeta(record.level);
		const children = childrenOf(record.id);
		const visibleChildren = children.filter(function (child) {
			return !query || branchMatches(child);
		});
		const isOpen = query ? true : expanded[record.id] !== false;
		const siblings = childrenOf(record.parent_id);
		const index = siblings.findIndex(function (item) {
			return String(item.id) === String(record.id);
		});
		let actions = '';
		let orderButtons = '';

		if (canEdit) {
			orderButtons =
				iconButton('btn-secondary location-compact-btn location-move-btn', 'fa-chevron-up', t('location_move_up'), 'data-id="' + escapeAttr(record.id) + '" data-direction="up"', index <= 0) +
				iconButton('btn-secondary location-compact-btn location-move-btn', 'fa-chevron-down', t('location_move_down'), 'data-id="' + escapeAttr(record.id) + '" data-direction="down"', index < 0 || index >= siblings.length - 1);

			if (meta.addLevel) {
				actions += actionButton(
					'btn-primary location-compact-btn location-add-btn',
					'fa-plus',
					meta.addLabel,
					'data-parent-id="' + escapeAttr(record.id) + '" data-parent-name="' + escapeAttr(record.name) + '" data-level="' + meta.addLevel + '"'
				);
			}

			actions += actionButton(
				'btn-secondary location-compact-btn location-edit-btn',
				'fa-pen',
				t('common_edit'),
				'data-id="' + escapeAttr(record.id) + '"'
			);
			actions += actionButton(
				'btn-danger location-compact-btn location-delete-btn',
				'fa-trash',
				t('common_delete'),
				'data-id="' + escapeAttr(record.id) + '"'
			);
		}

		const row = '<div class="location-row" style="--location-depth:' + depth + '">' +
			'<button type="button" class="location-toggle' + (visibleChildren.length ? '' : ' location-toggle-spacer') + '" data-id="' + escapeAttr(record.id) + '" aria-expanded="' + (isOpen ? 'true' : 'false') + '"' + (visibleChildren.length ? '' : ' disabled') + '>' +
				'<i class="fas fa-chevron-' + (isOpen ? 'down' : 'right') + '"></i>' +
			'</button>' +
			'<span class="location-name">' + escapeHtml(record.name) + '</span>' +
			'<span class="location-tag">' + escapeHtml(record.tag || '') + '</span>' +
			'<span class="location-order">' + orderButtons + '</span>' +
			'<span class="location-actions">' + actions + '</span>' +
		'</div>';

		return row + (visibleChildren.length && isOpen ? renderBranch(record.id, depth + 1) : '');
	}

	function renderTree() {
		const header = '<div class="location-head">' +
			'<span></span>' +
			'<span>' + escapeHtml(t('col_name')) + '</span>' +
			'<span>' + escapeHtml(t('col_tag')) + '</span>' +
			'<span>' + escapeHtml(t('location_order')) + '</span>' +
			'<span>' + escapeHtml(t('common_actions')) + '</span>' +
		'</div>';
		const roots = renderBranch(null, 0);
		treeEl.innerHTML = header + '<div class="location-body">' + roots + '</div>';

		if (!emptyEl) {
			return;
		}

		if (roots) {
			emptyEl.classList.add('d-none');
			emptyEl.textContent = '';
			return;
		}

		emptyEl.textContent = query ? t('location_no_matches') : t('location_empty');
		emptyEl.classList.remove('d-none');
	}

	function upsert(record) {
		const index = records.findIndex(function (item) {
			return Number(item.id) === Number(record.id);
		});

		if (index >= 0) {
			records[index] = record;
		} else {
			records.push(record);
			expanded[record.id] = Number(record.level) !== 1;

			if (record.parent_id != null) {
				expanded[record.parent_id] = true;
			}
		}

		renderTree();
	}

	renderTree();

	if (searchInput) {
		searchInput.addEventListener('input', debounce(function () {
			query = searchInput.value.trim().toLowerCase();
			renderTree();
		}, 200));
	}

	treeEl.addEventListener('click', function (event) {
		const toggle = event.target.closest('.location-toggle');

		if (toggle && !toggle.disabled) {
			const id = toggle.getAttribute('data-id');
			expanded[id] = expanded[id] === false;
			renderTree();
		}
	});

	if (!canEdit) {
		return;
	}

	const modalEl = document.getElementById('locationModal');
	const formEl = document.getElementById('locationForm');
	const alertEl = document.getElementById('locationModalAlert');
	const titleEl = document.getElementById('locationModalLabel');
	const hintEl = document.getElementById('locationParentHint');
	const recordInput = document.getElementById('locationRecordId');
	const parentInput = document.getElementById('locationParentId');
	const nameInput = document.getElementById('locationName');
	const saveBtn = document.getElementById('locationSaveBtn');
	const addProvinceBtn = document.getElementById('locationAddProvinceBtn');
	const modal = modalEl && typeof mdb !== 'undefined' ? mdb.Modal.getOrCreateInstance(modalEl) : null;
	const addTitles = {
		1: t('location_add_province'),
		2: t('location_add_county'),
		3: t('location_add_city'),
	};

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

	function openModal(options) {
		hideAlert();
		recordInput.value = options.id || '';
		parentInput.value = options.parentId || '';
		nameInput.value = options.name || '';
		titleEl.textContent = options.title;

		if (options.parentName) {
			hintEl.textContent = t('location_under', { name: options.parentName });
			hintEl.classList.remove('d-none');
		} else {
			hintEl.textContent = '';
			hintEl.classList.add('d-none');
		}

		if (modal) {
			modal.show();
		}

		window.setTimeout(function () {
			nameInput.focus();
		}, 150);
	}

	function findRecord(id) {
		return records.find(function (record) {
			return String(record.id) === String(id);
		});
	}

	if (addProvinceBtn) {
		addProvinceBtn.addEventListener('click', function () {
			openModal({ title: addTitles[1], parentId: '' });
		});
	}

	treeEl.addEventListener('click', function (event) {
		const addBtn = event.target.closest('.location-add-btn');
		const editBtn = event.target.closest('.location-edit-btn');
		const deleteBtn = event.target.closest('.location-delete-btn');
		const moveBtn = event.target.closest('.location-move-btn');

		if (moveBtn && !moveBtn.disabled) {
			const id = moveBtn.getAttribute('data-id');
			const direction = moveBtn.getAttribute('data-direction');
			const body = new FormData();
			body.append('direction', direction);

			fetchApi(baseUrl + '/move/' + id, { method: 'POST', body: body }).then(function (result) {
				(result.data || []).forEach(function (record) {
					const index = records.findIndex(function (item) {
						return Number(item.id) === Number(record.id);
					});

					if (index >= 0) {
						records[index] = record;
					}
				});
				renderTree();
			}).catch(function (error) {
				showToast(error.message || t('common_failed_update'), 'error');
			});
			return;
		}

		if (addBtn) {
			const level = Number(addBtn.getAttribute('data-level'));
			openModal({
				title: addTitles[level] || t('common_add_new'),
				parentId: addBtn.getAttribute('data-parent-id'),
				parentName: addBtn.getAttribute('data-parent-name'),
			});
			return;
		}

		if (editBtn) {
			const record = findRecord(editBtn.getAttribute('data-id'));

			if (!record) {
				return;
			}

			openModal({
				title: t('common_edit'),
				id: record.id,
				name: record.name,
			});
			return;
		}

		if (deleteBtn) {
			const id = deleteBtn.getAttribute('data-id');

			showConfirm({
				title: t('common_delete'),
				message: t('location_delete_confirm'),
				confirmLabel: t('common_delete'),
			}).then(function () {
				return fetchApi(baseUrl + '/delete/' + id, { method: 'POST' });
			}).then(function (result) {
				if (!result) {
					return;
				}

				records = records.filter(function (record) {
					return String(record.id) !== String(id);
				});
				renderTree();
				showToast(result.message || t('common_record_deleted'), 'success');
			}).catch(function (error) {
				if (isConfirmCancelled(error)) {
					return;
				}

				showToast(error.message || t('common_failed_delete'), 'error');
			});
		}
	});

	formEl.addEventListener('submit', function (event) {
		event.preventDefault();
		hideAlert();
		setSaving(true);

		const id = recordInput.value;
		const url = id ? baseUrl + '/update/' + id : baseUrl + '/create';
		const body = new FormData(formEl);

		if (id) {
			body.delete('parent_id');
		}

		fetchApi(url, { method: 'POST', body: body }).then(function (result) {
			upsert(result.data);
			showToast(result.message || (id ? t('common_record_updated') : t('common_record_created')), 'success');

			if (modal) {
				modal.hide();
			}
		}).catch(function (error) {
			showAlert(error.message || t('common_failed_create'));
		}).finally(function () {
			setSaving(false);
		});
	});
})();
