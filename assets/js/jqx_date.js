/**
 * jqxDateTimeInput helper (Globalize culture + hidden form value sync).
 *
 * Host element should be a DIV. Optional options.valueInput keeps YYYY-MM-DD for forms.
 */
(function ($) {
	'use strict';

	function pad(value) {
		return String(value).padStart(2, '0');
	}

	function formatYmd(date) {
		if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
			return '';
		}

		return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
	}

	function parseYmd(value) {
		const raw = String(value == null ? '' : value).trim().slice(0, 10);

		if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
			return null;
		}

		const parts = raw.split('-').map(function (part) {
			return parseInt(part, 10);
		});
		const date = new Date(parts[0], parts[1] - 1, parts[2]);

		if (
			date.getFullYear() !== parts[0] ||
			date.getMonth() !== parts[1] - 1 ||
			date.getDate() !== parts[2]
		) {
			return null;
		}

		return date;
	}

	function cultureName() {
		return String(window.APP_LANGUAGE || 'english').toLowerCase() === 'chinese'
			? 'zh-CN'
			: 'en-US';
	}

	function footerStrings() {
		if (cultureName() === 'zh-CN') {
			return { todayString: '今天', clearString: '清除' };
		}

		return { todayString: 'Today', clearString: 'Clear' };
	}

	function resolveHost(target) {
		if (!target) {
			return null;
		}

		if (target.jquery) {
			return target[0];
		}

		if (typeof target === 'string') {
			return document.querySelector(target);
		}

		return target;
	}

	function resolveValueInput(host, options) {
		if (options && options.valueInput) {
			return options.valueInput.jquery ? options.valueInput[0] : options.valueInput;
		}

		const id = host.getAttribute('data-value-input');
		return id ? document.getElementById(id) : null;
	}

	if (!$ || !$.fn || !$.fn.jqxDateTimeInput) {
		window.JqxDate = {
			mount: function () { return null; },
			setValue: function (target, value) {
				const host = resolveHost(target);
				const valueInput = host ? resolveValueInput(host, {}) : null;
				const ymd = parseYmd(value) ? formatYmd(parseYmd(value)) : '';

				if (valueInput) {
					valueInput.value = ymd;
				}
			},
			getValue: function (target) {
				const host = resolveHost(target);
				const valueInput = host ? resolveValueInput(host, {}) : null;
				return valueInput ? String(valueInput.value || '').slice(0, 10) : '';
			},
			sync: function () { return ''; },
			syncForm: function () {},
			destroy: function () {},
			destroyIn: function () {},
			parseYmd: parseYmd,
			formatYmd: formatYmd,
		};
		return;
	}

	const THEME = 'dark';

	function mount(target, options) {
		options = options || {};
		const host = resolveHost(target);

		if (!host) {
			return null;
		}

		const $host = $(host);
		const valueInput = resolveValueInput(host, options);
		const initial = options.value != null
			? options.value
			: (valueInput ? valueInput.value : '');
		const date = parseYmd(initial);
		const footer = footerStrings();

		if ($host.data('jqxDateTimeInput')) {
			destroy(host);
		}

		$host.addClass('jqx-app-date-host');
		$host.jqxDateTimeInput({
			theme: THEME,
			width: options.width || '100%',
			height: options.height || 31,
			formatString: 'yyyy-MM-dd',
			culture: cultureName(),
			showCalendarButton: true,
			showTimeButton: false,
			showFooter: true,
			allowNullDate: options.allowNull !== false,
			disabled: !!options.disabled,
			readonly: !!options.disabled,
			enableBrowserBoundsDetection: true,
			todayString: footer.todayString,
			clearString: footer.clearString,
			value: date,
		});

		if (!date && options.allowNull !== false) {
			try {
				$host.jqxDateTimeInput('val', null);
			} catch (error) {
				try {
					$host.jqxDateTimeInput('setDate', null);
				} catch (innerError) {
					// Ignore.
				}
			}
		}

		$host.off('change.jqxAppDate valueChanged.jqxAppDate close.jqxAppDate');
		$host.on('change.jqxAppDate valueChanged.jqxAppDate close.jqxAppDate', function () {
			sync(host, { valueInput: valueInput });
		});

		sync(host, { valueInput: valueInput });

		return $host;
	}

	function destroy(target) {
		const host = resolveHost(target);

		if (!host) {
			return;
		}

		const $host = $(host);

		if ($host.data('jqxDateTimeInput')) {
			$host.off('.jqxAppDate');
			$host.jqxDateTimeInput('destroy');
		}
	}

	function getValue(target, options) {
		const host = resolveHost(target);

		if (!host) {
			return '';
		}

		const $host = $(host);

		if ($host.data('jqxDateTimeInput')) {
			return formatYmd($host.jqxDateTimeInput('getDate'));
		}

		const valueInput = resolveValueInput(host, options || {});
		return valueInput ? String(valueInput.value || '').slice(0, 10) : '';
	}

	function setValue(target, value, options) {
		const host = resolveHost(target);

		if (!host) {
			return;
		}

		const $host = $(host);
		const valueInput = resolveValueInput(host, options || {});
		const date = parseYmd(value);
		const ymd = date ? formatYmd(date) : '';

		if ($host.data('jqxDateTimeInput')) {
			if (date) {
				$host.jqxDateTimeInput('setDate', date);
			} else {
				try {
					$host.jqxDateTimeInput('val', null);
				} catch (error) {
					try {
						$host.jqxDateTimeInput('setDate', null);
					} catch (innerError) {
						$host.val('');
					}
				}
			}
		}

		if (valueInput) {
			valueInput.value = ymd;
		}
	}

	function sync(target, options) {
		const host = resolveHost(target);

		if (!host) {
			return '';
		}

		const valueInput = resolveValueInput(host, options || {});
		const value = getValue(host, { valueInput: valueInput });

		if (valueInput) {
			valueInput.value = value;
		}

		return value;
	}

	function syncForm(form) {
		if (!form) {
			return;
		}

		form.querySelectorAll('.jqx-app-date-host').forEach(function (host) {
			sync(host);
		});
	}

	function destroyIn(container) {
		if (!container) {
			return;
		}

		container.querySelectorAll('.jqx-app-date-host').forEach(function (host) {
			destroy(host);
		});
	}

	window.JqxDate = {
		mount: mount,
		setValue: setValue,
		getValue: getValue,
		sync: sync,
		syncForm: syncForm,
		destroy: destroy,
		destroyIn: destroyIn,
		parseYmd: parseYmd,
		formatYmd: formatYmd,
	};
})(window.jQuery);
