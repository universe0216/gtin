(function () {
	'use strict';

	function escapeAttr(text) {
		return String(text ?? '')
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	window.escapeAttr = escapeAttr;
})();
