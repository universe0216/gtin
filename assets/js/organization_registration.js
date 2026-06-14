(function () {
	'use strict';

	if (typeof window.ORGANIZATION_REGISTRATION_CONFIG === 'undefined') {
		return;
	}

	const config = window.ORGANIZATION_REGISTRATION_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');
	const ID_ATTR = 'data-organization-registration-id';

	const uploadForms = document.querySelectorAll('.org-registration-upload-form');
	const uploadModalEl = document.getElementById('orgRegistrationUploadModal');
	const openUploadBtn = document.getElementById('btnOpenUploadModal');
	const tabsWrapper = document.getElementById('orgRegistrationTabsWrapper');
	const tabsNav = document.getElementById('orgRegistrationTabs');
	const tabsContent = document.getElementById('orgRegistrationTabContent');
	const initialUpload = document.getElementById('orgRegistrationInitialUpload');

	// Tab data + grid controllers keyed by registration id.
	const tabData = new Map();
	const tabGrids = new Map();

	function registerTabData(tab) {
		if (tab && tab.organization_registration_id !== undefined) {
			tabData.set(String(tab.organization_registration_id), tab);
		}
	}

	function paneFor(registrationId) {
		return tabsContent
			? tabsContent.querySelector('.tab-pane[' + ID_ATTR + '="' + registrationId + '"]')
			: null;
	}

	function buildGrid(tab, container) {
		const columns = [
			{ text: '#', datafield: '__rownum', sortable: false, filterable: false, width: 60, cellsrenderer: JqxGrid.rowNumberRenderer },
		];
		const datafields = [];

		(tab.columns || []).forEach(function (column, index) {
			const field = 'c' + index;
			datafields.push({ name: field, type: 'string' });
			columns.push({ text: column, datafield: field });
		});

		const data = (tab.rows || []).map(function (row) {
			const obj = {};
			(row.cells || []).forEach(function (cell, index) {
				obj['c' + index] = cell;
			});
			return obj;
		});

		return JqxGrid.local(container, {
			data: data,
			datafields: datafields,
			columns: columns,
			pageable: true,
			pagesize: 25,
			pagesizeoptions: ['25', '50', '100'],
			height: Math.max(360, window.innerHeight - 320),
			emptyMessage: 'No rows found.',
		});
	}

	function ensureGrid(registrationId) {
		const key = String(registrationId);
		const pane = paneFor(key);

		if (!pane) {
			return;
		}

		const container = pane.querySelector('.org-registration-grid');

		if (!container) {
			return;
		}

		if (container.getAttribute('data-grid-ready') === '1') {
			const existing = tabGrids.get(key);
			if (existing) {
				existing.render();
			}
			return;
		}

		// Wait until the pane is actually visible so the grid can size itself.
		if (!container.offsetParent) {
			return;
		}

		const tab = tabData.get(key);

		if (!tab) {
			return;
		}

		const grid = buildGrid(tab, container);

		if (grid) {
			tabGrids.set(key, grid);
			container.setAttribute('data-grid-ready', '1');
		}
	}

	function activeRegistrationId() {
		const activePane = tabsContent ? tabsContent.querySelector('.tab-pane.active') : null;
		return activePane ? activePane.getAttribute(ID_ATTR) : null;
	}

	function buildTableHtml() {
		return '<div class="org-registration-grid"></div>';
	}

	function buildTabButton(tab, isActive) {
		return '<li class="nav-item procedure-tab-nav-item" role="presentation">' +
			'<div class="procedure-tab-nav-wrap">' +
				'<button class="nav-link' + (isActive ? ' active' : '') + '" ' +
					'id="org-registration-tab-' + tab.organization_registration_id + '-tab" ' +
					'data-mdb-tab-init ' +
					'data-mdb-target="#org-registration-tab-' + tab.organization_registration_id + '" ' +
					'type="button" role="tab" ' +
					'aria-controls="org-registration-tab-' + tab.organization_registration_id + '" ' +
					'aria-selected="' + (isActive ? 'true' : 'false') + '" ' +
					'data-organization-registration-id="' + tab.organization_registration_id + '">' +
					'<i class="fas fa-file-zipper me-1"></i>' + escapeHtml(tab.file_name) +
					'<span class="badge bg-secondary ms-2">' + (tab.rows || []).length + '</span>' +
				'</button>' +
				'<button type="button" class="procedure-tab-nav-close org-registration-tab-delete-btn"' +
					' data-organization-registration-id="' + tab.organization_registration_id + '"' +
					' data-file-name="' + escapeAttr(tab.file_name) + '"' +
					' aria-label="Stop registration" data-mdb-ripple-init>' +
					'<i class="fas fa-times" aria-hidden="true"></i>' +
				'</button>' +
			'</div>' +
		'</li>';
	}

	function buildTabPane(tab, isActive) {
		return '<div class="tab-pane fade' + (isActive ? ' show active' : '') + '" ' +
			'id="org-registration-tab-' + tab.organization_registration_id + '" role="tabpanel" ' +
			'aria-labelledby="org-registration-tab-' + tab.organization_registration_id + '-tab" ' +
			'data-organization-registration-id="' + tab.organization_registration_id + '">' +
			'<div class="procedure-tab-meta d-flex flex-wrap gap-3 mb-3 small text-muted align-items-center">' +
				'<span><strong>Procedure #:</strong> ' + escapeHtml(tab.procedure_number) + '</span>' +
				'<span><strong>Organization:</strong> ' + escapeHtml(tab.organization_name || '') + '</span>' +
				'<span><strong>Processor:</strong> ' + escapeHtml(tab.processor_name || '') + '</span>' +
				'<span><strong>Status:</strong> ' + escapeHtml(tab.status || 'uploaded') + '</span>' +
				'<span><strong>Uploaded:</strong> ' + escapeHtml(tab.created_at || '') + '</span>' +
				'<button type="button" class="btn btn-sm btn-outline-danger ms-auto org-registration-tab-delete-btn"' +
					' data-organization-registration-id="' + tab.organization_registration_id + '"' +
					' data-file-name="' + escapeAttr(tab.file_name) + '" data-mdb-ripple-init>' +
					'<i class="fas fa-trash me-1"></i> Delete' +
				'</button>' +
			'</div>' +
			buildTableHtml(tab) +
		'</div>';
	}

	const procedureTabs = ProcedureTabs.create({
		tabsWrapper: tabsWrapper,
		tabsNav: tabsNav,
		tabsContent: tabsContent,
		initialUpload: initialUpload,
		openUploadBtn: openUploadBtn,
		idAttribute: ID_ATTR,
		getTabId: function (tab) {
			return tab.organization_registration_id;
		},
		buildTabButton: buildTabButton,
		buildTabPane: buildTabPane,
		deleteUrl: function (registrationId) {
			return baseUrl + '/delete/' + registrationId;
		},
		deleteConfirm: {
			message: 'This registration is not completed yet. Stop registration for this file?',
			confirmLabel: 'Stop Registration',
			errorMessage: 'Failed to delete registration.',
		},
		deleteBtnSelector: '.org-registration-tab-delete-btn',
		onBeforePrepend: registerTabData,
		onAfterPrepend: function (tab) {
			ensureGrid(tab.organization_registration_id);
		},
	});

	const procedureUpload = ProcedureUpload.create({
		forms: uploadForms,
		uploadModalEl: uploadModalEl,
		openUploadBtn: openUploadBtn,
		uploadUrl: baseUrl + '/upload',
		fileItemStyle: 'div',
		dropFilter: function (files) {
			return files.filter(function (file) {
				return file.name.toLowerCase().endsWith('.zip');
			});
		},
		onSuccess: function (result) {
			procedureTabs.prependTabs(result.tabs || []);
		},
	});

	procedureUpload.init();
	procedureTabs.init();

	// Lazily (re)build a grid whenever its tab becomes visible.
	document.addEventListener('shown.mdb.tab', function (event) {
		const button = event.target.closest('[' + ID_ATTR + ']');

		if (button && tabsNav && tabsNav.contains(button)) {
			ensureGrid(button.getAttribute(ID_ATTR));
		}
	});

	// Register the server-rendered tabs and render the active one.
	(config.initialTabs || []).forEach(registerTabData);

	const activeId = activeRegistrationId();

	if (activeId) {
		ensureGrid(activeId);
	}
})();
