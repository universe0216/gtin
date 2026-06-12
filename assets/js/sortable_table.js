(function (window) {
	'use strict';

	function sortIconClass(direction) {
		if (direction === 'asc') {
			return 'fas fa-sort-up';
		}

		if (direction === 'desc') {
			return 'fas fa-sort-down';
		}

		return 'fas fa-sort';
	}

	function cycleSort(state, columnKey) {
		const next = {
			sort: state.sort || '',
			dir: state.dir || '',
		};

		if (next.sort !== columnKey) {
			next.sort = columnKey;
			next.dir = 'asc';
			return next;
		}

		if (next.dir === 'asc') {
			next.dir = 'desc';
			return next;
		}

		if (next.dir === 'desc') {
			next.sort = '';
			next.dir = '';
			return next;
		}

		next.sort = columnKey;
		next.dir = 'asc';
		return next;
	}

	function renderHeader(columns, state, options) {
		const sortState = state || { sort: '', dir: '' };
		const indexHeader = options && options.indexHeader ? options.indexHeader : '#';
		const buttonClass = options && options.buttonClass ? options.buttonClass : 'entity-list-sort-btn';
		const indexClass = options && options.indexClass ? options.indexClass : 'procedure-row-index';

		let html = '<th class="' + indexClass + '">' + window.escapeHtml(indexHeader) + '</th>';

		columns.forEach(function (column) {
			const isSorted = sortState.sort === column.key;
			const direction = isSorted ? sortState.dir : '';
			const sortedClass = isSorted && direction ? ' is-sorted' : '';

			html +=
				'<th>' +
					'<button type="button" class="' + buttonClass + sortedClass + '" data-sort-key="' + window.escapeHtml(column.key) + '">' +
						'<span>' + window.escapeHtml(column.label) + '</span>' +
						'<i class="' + sortIconClass(direction) + ' entity-list-sort-icon" aria-hidden="true"></i>' +
					'</button>' +
				'</th>';
		});

		return html;
	}

	function appendSortParams(params, state) {
		if (state.sort && state.dir) {
			params.set('sort', state.sort);
			params.set('dir', state.dir);
		}
	}

	function readSortParams(params) {
		return {
			sort: (params.get('sort') || '').trim(),
			dir: (params.get('dir') || '').trim().toLowerCase(),
		};
	}

	function bindHeader(container, onSort) {
		if (!container) {
			return;
		}

		container.addEventListener('click', function (event) {
			const button = event.target.closest('.entity-list-sort-btn');

			if (!button) {
				return;
			}

			onSort(button.getAttribute('data-sort-key') || '');
		});
	}

	window.SortableTable = {
		cycleSort: cycleSort,
		renderHeader: renderHeader,
		appendSortParams: appendSortParams,
		readSortParams: readSortParams,
		bindHeader: bindHeader,
	};
})(window);
