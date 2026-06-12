(function () {
	'use strict';

	if (typeof window.ORGANIZATION_REGISTRATION_CONFIG === 'undefined') {
		return;
	}

	const config = window.ORGANIZATION_REGISTRATION_CONFIG;
	const baseUrl = config.baseUrl.replace(/\/$/, '');

	const uploadForms = document.querySelectorAll('.org-registration-upload-form');
	const uploadModalEl = document.getElementById('orgRegistrationUploadModal');
	const openUploadBtn = document.getElementById('btnOpenUploadModal');
	const tabsWrapper = document.getElementById('orgRegistrationTabsWrapper');
	const tabsNav = document.getElementById('orgRegistrationTabs');
	const tabsContent = document.getElementById('orgRegistrationTabContent');
	const initialUpload = document.getElementById('orgRegistrationInitialUpload');

	function buildTableHtml(tab) {
		const headerHtml = '<th class="procedure-row-index">#</th>' +
			(tab.columns || []).map(function (column) {
				return '<th>' + escapeHtml(column) + '</th>';
			}).join('');

		const bodyHtml = (tab.rows || []).map(function (row, index) {
			const cellsHtml = (row.cells || []).map(function (cell) {
				return '<td>' + escapeHtml(cell) + '</td>';
			}).join('');

			return '<tr data-id="' + row.id + '">' +
				'<td class="procedure-row-index text-muted">' + (index + 1) + '</td>' +
				cellsHtml +
			'</tr>';
		}).join('');

		return '<div class="procedure-table-scroll">' +
			'<table class="table table-hover align-middle mb-0 procedure-data-table">' +
				'<thead><tr>' + headerHtml + '</tr></thead>' +
				'<tbody>' + bodyHtml + '</tbody>' +
			'</table>' +
		'</div>';
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
		idAttribute: 'data-organization-registration-id',
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
})();
