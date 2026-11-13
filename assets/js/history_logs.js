(function () {
	'use strict';

	if (typeof window.HISTORY_LOGS_CONFIG === 'undefined') {
		return;
	}

	const config = window.HISTORY_LOGS_CONFIG;
	const type = config.type || 'activity';
	const searchInput = document.querySelector(config.searchSelector);
	const actionFilter = document.querySelector(config.actionFilterSelector);
	const entityFilter = config.entityFilterSelector
		? document.querySelector(config.entityFilterSelector)
		: null;
	const detailModalEl = document.getElementById('historyLogDetailModal');
	const detailTitle = document.getElementById('historyLogDetailTitle');
	const detailMeta = document.getElementById('historyLogDetailMeta');
	const detailBody = document.getElementById('historyLogDetailBody');
	const originalBody = document.getElementById('historyLogOriginalBody');
	const newBody = document.getElementById('historyLogNewBody');

	let detailModal = null;
	let searchTimer = null;

	function badge(text, cls) {
		return '<span class="badge ' + cls + '">' + escapeHtml(text) + '</span>';
	}

	function actionRenderer(row, columnfield, value) {
		const map = {
			visit: 'bg-info',
			api: 'bg-primary',
			create: 'bg-success',
			update: 'bg-warning text-dark',
			archive: 'bg-secondary',
			restore: 'bg-info text-dark',
		};
		return JqxGrid.htmlCellRenderer(badge(value || '', map[value] || 'bg-secondary'));
	}

	function actionsRenderer(row, columnfield, value, defaultHtml, props, rowdata) {
		return JqxGrid.actionButtons([
			{
				cls: 'btn-outline-primary btn-log-detail',
				icon: 'fa-eye',
				title: t('common_detail'),
			},
		], rowdata);
	}

	function getParams() {
		const params = {
			q: searchInput ? searchInput.value.trim() : '',
		};

		if (actionFilter && actionFilter.value) {
			params.action = actionFilter.value;
		}

		if (entityFilter && entityFilter.value) {
			params.entity = entityFilter.value;
		}

		return params;
	}

	function buildActivityColumns() {
		return {
			datafields: [
				{ name: 'id', type: 'number' },
				{ name: 'created_at', type: 'string' },
				{ name: 'account_label', type: 'string' },
				{ name: 'action', type: 'string' },
				{ name: 'method', type: 'string' },
				{ name: 'path', type: 'string' },
				{ name: 'controller', type: 'string' },
				{ name: 'method_name', type: 'string' },
				{ name: 'ip_address', type: 'string' },
				{ name: 'has_request_data', type: 'number' },
			],
			columns: [
				{ text: '#', datafield: 'id', width: 70 },
				{ text: t('log_when'), datafield: 'created_at', width: 160 },
				{ text: t('log_account'), datafield: 'account_label', width: 180 },
				{ text: t('log_action'), datafield: 'action', width: 90, cellsrenderer: actionRenderer },
				{ text: t('log_method'), datafield: 'method', width: 80 },
				{ text: t('log_path'), datafield: 'path' },
				{ text: t('log_ip'), datafield: 'ip_address', width: 130 },
				{
					text: t('common_actions'),
					datafield: '__actions',
					width: 90,
					sortable: false,
					filterable: false,
					align: 'right',
					cellsalign: 'right',
					cellsrenderer: actionsRenderer,
				},
			],
		};
	}

	function buildDataColumns() {
		return {
			datafields: [
				{ name: 'id', type: 'number' },
				{ name: 'created_at', type: 'string' },
				{ name: 'account_label', type: 'string' },
				{ name: 'entity', type: 'string' },
				{ name: 'entity_id', type: 'number' },
				{ name: 'action', type: 'string' },
				{ name: 'has_payload', type: 'number' },
			],
			columns: [
				{ text: '#', datafield: 'id', width: 70 },
				{ text: t('log_when'), datafield: 'created_at', width: 160 },
				{ text: t('log_account'), datafield: 'account_label', width: 180 },
				{ text: t('log_entity'), datafield: 'entity', width: 130 },
				{ text: t('log_entity_id'), datafield: 'entity_id', width: 100 },
				{ text: t('log_action'), datafield: 'action', width: 110, cellsrenderer: actionRenderer },
				{
					text: t('common_actions'),
					datafield: '__actions',
					width: 90,
					sortable: false,
					filterable: false,
					align: 'right',
					cellsalign: 'right',
					cellsrenderer: actionsRenderer,
				},
			],
		};
	}

	const defs = type === 'data' ? buildDataColumns() : buildActivityColumns();
	const grid = JqxGrid.remote(config.gridSelector, {
		url: config.listUrl,
		id: 'id',
		datafields: defs.datafields,
		columns: defs.columns,
		pagesize: 20,
		pagesizeoptions: ['10', '20', '50', '100'],
		pageable: true,
		sortable: false,
		height: Math.max(420, window.innerHeight - 260),
		autoheight: false,
		emptyMessage: t('history_log_empty'),
		getParams: getParams,
	});

	function reloadGrid() {
		if (grid && grid.$el) {
			grid.$el.jqxGrid('updatebounddata', 'data');
		}
	}

	function openDetail(id) {
		fetchApi(config.detailUrl + '/' + id)
			.then(function (result) {
				const row = result.data || {};

				if (typeof mdb !== 'undefined' && detailModalEl) {
					detailModal = mdb.Modal.getOrCreateInstance(detailModalEl);
				}

				if (detailTitle) {
					detailTitle.textContent = t('history_log_detail') + ' #' + (row.id || id);
				}

				if (detailMeta) {
					if (type === 'activity') {
						detailMeta.textContent = [
							row.created_at || '',
							row.account_label || '',
							row.action || '',
							row.method || '',
							row.path || '',
							row.ip_address || '',
						].filter(Boolean).join(' · ');
					} else {
						detailMeta.textContent = [
							row.created_at || '',
							row.account_label || '',
							row.entity || '',
							'#' + (row.entity_id || ''),
							row.action || '',
						].filter(Boolean).join(' · ');
					}
				}

				if (type === 'activity' && detailBody) {
					detailBody.textContent = row.request_data_pretty || t('history_log_no_payload');
				}

				if (type === 'data') {
					if (originalBody) {
						originalBody.textContent = row.original_data_pretty || t('history_log_no_payload');
					}
					if (newBody) {
						newBody.textContent = row.new_data_pretty || t('history_log_no_payload');
					}
				}

				if (detailModal) {
					detailModal.show();
				}
			})
			.catch(function (error) {
				showToast(error.message || t('history_load_failed'), 'error');
			});
	}

	if (grid) {
		JqxGrid.bindCellButtons(grid.$el, '.btn-log-detail', function (id) {
			openDetail(id);
		});
	}

	if (searchInput) {
		searchInput.addEventListener('input', function () {
			window.clearTimeout(searchTimer);
			searchTimer = window.setTimeout(reloadGrid, 300);
		});
	}

	if (actionFilter) {
		actionFilter.addEventListener('change', reloadGrid);
	}

	if (entityFilter) {
		entityFilter.addEventListener('change', reloadGrid);
	}
})();
