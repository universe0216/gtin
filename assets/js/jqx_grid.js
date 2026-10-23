/**
 * Shared jqxGrid (jQWidgets) helper for the GTIN app.
 *
 * Wraps jqxGrid with the project defaults (dark theme, full width, resizable
 * columns) and exposes small builders so each page can spin up a grid with a
 * few lines instead of repeating boilerplate.
 *
 *   JqxGrid.local($el, { columns, data, id })      -> local array source
 *   JqxGrid.remote($el, { url, columns, ... })     -> server-paged source
 *   JqxGrid.actionButtons([...], rowdata)          -> HTML for an actions cell
 *   JqxGrid.bindCellButtons($el, '.btn', handler)  -> delegate button clicks
 */
(function ($) {
	'use strict';

	if (!$ || !$.jqx) {
		// jqx-all.js failed to load; nothing we can do.
		return;
	}

	const THEME = 'dark';

	const DEFAULTS = {
		theme: THEME,
		width: '100%',
		altrows: true,
		sortable: true,
		columnsresize: true,
		columnsreorder: false,
		enabletooltips: true,
		filterable: false,
		showfilterrow: false,
		pageable: false,
		autoheight: true,
		localization: {
			emptydatastring: window.t ? t('jqx_empty') : 'No records found.',
			loadtext: window.t ? t('jqx_load') : 'Loading...',
			pagergotopagestring: window.t ? t('jqx_go_to_page') : 'Go to page:',
			pagershowrowsstring: window.t ? t('jqx_show_rows') : 'Show rows:',
			pagerrangestring: window.t ? t('jqx_range') : 'of',
			pagerfirstbuttonstring: window.t ? t('jqx_first') : 'First',
			pagerlastbuttonstring: window.t ? t('jqx_last') : 'Last',
			pagernextbuttonstring: window.t ? t('jqx_next') : 'Next',
			pagerpreviousbuttonstring: window.t ? t('jqx_previous') : 'Previous',
			sortascendingstring: window.t ? t('jqx_sort_asc') : 'Sort Ascending',
			sortdescendingstring: window.t ? t('jqx_sort_desc') : 'Sort Descending',
		},
	};

	function escapeAttr(value) {
		return String(value === null || value === undefined ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	/**
	 * Build jqxGrid datafields from a column definition list when the caller
	 * does not provide an explicit datafields array.
	 */
	function deriveDatafields(columns) {
		return (columns || []).map(function (column) {
			return {
				name: column.datafield,
				type: column.datatype || 'string',
			};
		});
	}

	function buildSettings(opts, adapter) {
		const settings = $.extend(true, {}, DEFAULTS, opts.grid || {});

		settings.source = adapter;
		settings.columns = opts.columns || [];

		if (opts.height !== undefined && opts.height !== null) {
			settings.autoheight = false;
			settings.height = opts.height;
		} else if (opts.autoheight === false) {
			settings.autoheight = false;
		}

		if (opts.width !== undefined) {
			settings.width = opts.width;
		}

		[
			'pageable', 'pagesize', 'pagesizeoptions', 'sortable', 'filterable',
			'showfilterrow', 'altrows', 'columnsresize', 'rowsheight',
			'columnsheight', 'autorowheight', 'editable', 'selectionmode',
			'pinnedcolumns', 'columnsautoresize', 'enablebrowserselection',
		].forEach(function (key) {
			if (opts[key] !== undefined) {
				settings[key] = opts[key];
			}
		});

		if (opts.emptyMessage) {
			settings.localization = $.extend({}, settings.localization, {
				emptydatastring: opts.emptyMessage,
			});
		}

		return settings;
	}

	/**
	 * Create a grid backed by a local array. Returns a small controller that
	 * lets callers swap the data without re-initialising the widget.
	 */
	function local($el, opts) {
		$el = $($el);

		if (!$el.length) {
			return null;
		}

		const source = {
			localdata: opts.data || [],
			datatype: 'array',
			datafields: opts.datafields || deriveDatafields(opts.columns),
		};

		if (opts.id) {
			source.id = opts.id;
		}

		const adapter = new $.jqx.dataAdapter(source);

		$el.jqxGrid(buildSettings(opts, adapter));

		return {
			$el: $el,
			source: source,
			adapter: adapter,
			getData: function () {
				return source.localdata;
			},
			setData: function (data) {
				source.localdata = data || [];
				adapter.dataBind();
				$el.jqxGrid('updatebounddata', 'data');
			},
			refresh: function () {
				$el.jqxGrid('updatebounddata', 'data');
			},
			render: function () {
				$el.jqxGrid('render');
			},
			destroy: function () {
				$el.jqxGrid('destroy');
			},
		};
	}

	/**
	 * Create a grid backed by a remote JSON endpoint with server-side paging,
	 * sorting and search. The endpoint contract matches BasicController::list
	 * ({ data: [...], total, page, per_page, sort, sort_dir }).
	 *
	 * opts.getParams() should return any extra query params (e.g. { q: '...' }).
	 */
	function remote($el, opts) {
		$el = $($el);

		if (!$el.length) {
			return null;
		}

		const perPage = opts.pagesize || 10;

		const source = {
			datatype: 'json',
			datafields: opts.datafields || deriveDatafields(opts.columns),
			url: opts.url,
			root: opts.root || 'data',
			cache: false,
			pagesize: perPage,
			beforeprocessing: function (data) {
				source.totalrecords = (data && data.total) || 0;

				if (typeof opts.onLoad === 'function') {
					opts.onLoad(data);
				}
			},
			formatdata: function (data) {
				const params = {};
				params.page = (data.pagenum || 0) + 1;
				params.per_page = data.pagesize || perPage;

				if (data.sortdatafield) {
					params.sort = data.sortdatafield;
					params.dir = data.sortorder || 'asc';
				}

				const extra = typeof opts.getParams === 'function' ? opts.getParams() : null;

				if (extra) {
					$.each(extra, function (key, value) {
						if (value !== undefined && value !== null && value !== '') {
							params[key] = value;
						}
					});
				}

				return params;
			},
		};

		const adapter = new $.jqx.dataAdapter(source, {
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			},
			loadError: function (xhr) {
				if (typeof opts.onError === 'function') {
					opts.onError(xhr);
				}
			},
		});

		const settings = buildSettings($.extend({}, opts, {
			pageable: opts.pageable !== undefined ? opts.pageable : true,
			pagesize: perPage,
			pagesizeoptions: opts.pagesizeoptions || ['5', '10', '20', '50'],
		}), adapter);

		// Server-side paging/sorting: jqxGrid re-requests a page at a time and
		// reads totalrecords (set in beforeprocessing) for the pager.
		settings.virtualmode = true;
		settings.rendergridrows = function (params) {
			return params.data;
		};

		$el.jqxGrid(settings);

		return {
			$el: $el,
			source: source,
			adapter: adapter,
			reload: function () {
				$el.jqxGrid('updatebounddata', 'data');
			},
			gotoFirst: function () {
				$el.jqxGrid('gotopage', 0);
			},
			render: function () {
				$el.jqxGrid('render');
			},
		};
	}

	/**
	 * Render the HTML for an "actions" cell. Each button definition carries the
	 * row id as data-id so a delegated handler can resolve the record.
	 *
	 * buttons: [{ cls, icon, title, hidden(rowdata) }]
	 */
	function actionButtons(buttons, rowdata) {
		const id = rowdata && (rowdata.id !== undefined ? rowdata.id : rowdata.ID);

		const html = (buttons || []).map(function (button) {
			if (typeof button.hidden === 'function' && button.hidden(rowdata)) {
				return '';
			}

			return '<button type="button" class="btn btn-sm ' + button.cls + ' jqx-action-btn" ' +
				'data-id="' + escapeAttr(id) + '"' +
				(button.title ? ' title="' + escapeAttr(button.title) + '"' : '') +
				'><i class="fas ' + button.icon + '"></i></button>';
		}).join('');

		return '<div class="jqx-action-cell">' + html + '</div>';
	}

	/**
	 * Delegate clicks for buttons rendered inside grid cells. The handler is
	 * called with (rowId, originalEvent).
	 */
	function bindCellButtons($el, selector, handler) {
		$($el).on('click', selector, function (event) {
			event.preventDefault();
			event.stopPropagation();
			handler($(this).attr('data-id'), event, this);
		});
	}

	/** cellsrenderer that prints a 1-based row number. */
	function rowNumberRenderer(row) {
		return '<div class="jqx-grid-cell-leftalign procedure-row-index" style="margin-top:6px;">' + (row + 1) + '</div>';
	}

	/** cellsrenderer that prints escaped HTML left-aligned with the cell. */
	function htmlCellRenderer(html) {
		return '<div style="margin:6px 8px;">' + html + '</div>';
	}

	window.JqxGrid = {
		THEME: THEME,
		local: local,
		remote: remote,
		actionButtons: actionButtons,
		bindCellButtons: bindCellButtons,
		rowNumberRenderer: rowNumberRenderer,
		htmlCellRenderer: htmlCellRenderer,
		escapeAttr: escapeAttr,
	};
})(window.jQuery);
