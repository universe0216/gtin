/**
 * Shared change-history list for product / organization detail modals.
 */
(function () {
	'use strict';

	const ACTION_BADGE = {
		create: 'bg-success',
		update: 'bg-warning text-dark',
		archive: 'bg-secondary',
		restore: 'bg-info text-dark',
	};

	function actionLabel(action) {
		const key = 'log_action_' + String(action || '').toLowerCase();
		const translated = typeof t === 'function' ? t(key) : '';

		if (translated && translated !== key) {
			return translated;
		}

		return action || '—';
	}

	function humanizeField(field) {
		return String(field || '')
			.replace(/_/g, ' ')
			.replace(/\b\w/g, function (ch) {
				return ch.toUpperCase();
			});
	}

	function fieldLabel(field, labels) {
		if (labels && labels[field]) {
			return labels[field];
		}

		return humanizeField(field);
	}

	function displayValue(value) {
		if (value === null || value === undefined || value === '') {
			return '—';
		}

		return String(value);
	}

	function buildFieldLabels(fields) {
		const labels = {};

		(fields || []).forEach(function (field) {
			const key = field.name || field.key;

			if (key && field.label) {
				labels[key] = field.label;
			}
		});

		return labels;
	}

	function renderChanges(changes, labels) {
		if (!changes || !changes.length) {
			return '';
		}

		let html = '<ul class="entity-history-changes list-unstyled mb-0">';

		changes.forEach(function (change) {
			html +=
				'<li class="entity-history-change">' +
					'<span class="entity-history-field">' + escapeHtml(fieldLabel(change.field, labels)) + '</span>' +
					'<span class="entity-history-values">' +
						'<span class="entity-history-from">' + escapeHtml(displayValue(change.from)) + '</span>' +
						'<span class="entity-history-arrow" aria-hidden="true">→</span>' +
						'<span class="entity-history-to">' + escapeHtml(displayValue(change.to)) + '</span>' +
					'</span>' +
				'</li>';
		});

		html += '</ul>';

		return html;
	}

	function renderRows(rows, labels) {
		if (!rows || !rows.length) {
			return '<div class="entity-history-empty text-muted py-4 text-center">' +
				escapeHtml(t('entity_history_empty')) +
			'</div>';
		}

		let html = '<div class="entity-history-list">';

		rows.forEach(function (row) {
			const action = String(row.action || '');
			const badgeClass = ACTION_BADGE[action] || 'bg-secondary';
			const changesHtml = renderChanges(row.changes, labels);

			html +=
				'<article class="entity-history-item">' +
					'<div class="entity-history-meta">' +
						'<span class="badge ' + badgeClass + '">' + escapeHtml(actionLabel(action)) + '</span>' +
						'<span class="entity-history-who">' + escapeHtml(row.account_label || '—') + '</span>' +
						'<time class="entity-history-when" datetime="' + escapeAttr(row.created_at || '') + '">' +
							escapeHtml(row.created_at || '—') +
						'</time>' +
					'</div>' +
					(changesHtml
						? '<div class="entity-history-body">' + changesHtml + '</div>'
						: '<div class="entity-history-body text-muted small">' +
							escapeHtml(t('entity_history_no_field_changes')) +
						'</div>') +
				'</article>';
		});

		html += '</div>';

		return html;
	}

	function setLoading(container, loadingEl, isLoading) {
		if (loadingEl) {
			loadingEl.classList.toggle('d-none', !isLoading);
		}

		if (container) {
			container.classList.toggle('d-none', isLoading);
		}
	}

	function clear(container) {
		if (container) {
			container.innerHTML = '';
		}
	}

	function load(options) {
		options = options || {};
		const url = options.url;
		const container = options.container;
		const loadingEl = options.loadingEl;
		const fieldLabels = options.fieldLabels || {};
		const signal = options.signal;

		if (!url || !container) {
			return Promise.resolve([]);
		}

		setLoading(container, loadingEl, true);
		clear(container);

		return fetchApi(url, { signal: signal }).then(function (result) {
			const rows = result.data || [];
			container.innerHTML = renderRows(rows, fieldLabels);
			setLoading(container, loadingEl, false);
			return rows;
		}).catch(function (error) {
			if (error && error.name === 'AbortError') {
				return [];
			}

			setLoading(container, loadingEl, false);
			container.innerHTML =
				'<div class="alert alert-danger mb-0">' +
					escapeHtml(error.message || t('entity_history_load_failed')) +
				'</div>';
			throw error;
		});
	}

	window.EntityHistory = {
		buildFieldLabels: buildFieldLabels,
		clear: clear,
		load: load,
		renderRows: renderRows,
		setLoading: setLoading,
	};
})();
