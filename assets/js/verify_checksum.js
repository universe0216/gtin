(function () {
	'use strict';

	const typeInput = document.getElementById('checksumType');
	const bodyInput = document.getElementById('checksumBody');
	const digitInput = document.getElementById('checksumDigit');
	const hint = document.getElementById('checksumHint');

	if (!typeInput || !bodyInput || !digitInput) {
		return;
	}

	function gs1CheckDigit(dataDigits) {
		const digits = String(dataDigits);
		let sum = 0;

		for (let i = 0; i < digits.length; i++) {
			const digit = Number(digits.charAt(digits.length - 1 - i));
			sum += digit * (i % 2 === 0 ? 3 : 1);
		}

		return String((10 - (sum % 10)) % 10);
	}

	function upcEExpand(body) {
		if (!/^[01]\d{6}$/.test(body)) {
			return '';
		}

		const numberSystem = body.charAt(0);
		const compressed = body.slice(1);
		const last = compressed.charAt(5);
		let manufacturer = '';
		let product = '';

		if (last === '0' || last === '1' || last === '2') {
			manufacturer = compressed.slice(0, 2) + last + '00';
			product = '00' + compressed.slice(2, 5);
		} else if (last === '3') {
			manufacturer = compressed.slice(0, 3) + '00';
			product = '000' + compressed.slice(3, 5);
		} else if (last === '4') {
			manufacturer = compressed.slice(0, 4) + '0';
			product = '0000' + compressed.charAt(4);
		} else {
			manufacturer = compressed.slice(0, 5);
			product = '0000' + last;
		}

		return numberSystem + manufacturer + product;
	}

	function checksum(type, body) {
		if (type === 'gs1128') {
			return body === '' ? '' : gs1CheckDigit(body);
		}

		if (type === 'upce') {
			const expanded = upcEExpand(body);
			return expanded === '' ? '' : gs1CheckDigit(expanded);
		}

		return body === '' ? '' : gs1CheckDigit(body);
	}

	function selectedLength() {
		const option = typeInput.options[typeInput.selectedIndex];
		return option ? Number(option.getAttribute('data-length') || 0) : 0;
	}

	function updateHint() {
		if (!hint) {
			return;
		}

		const length = selectedLength();

		if (typeInput.value === 'upce') {
			hint.textContent = t('checksum_hint_upce');
			return;
		}

		if (length > 0) {
			hint.textContent = t('checksum_hint_length', { count: length });
			return;
		}

		hint.textContent = t('checksum_hint_digits');
	}

	function updateChecksum() {
		const type = typeInput.value;
		const length = selectedLength();
		let body = bodyInput.value.replace(/\D/g, '');

		if (length > 0 && body.length > length) {
			body = body.slice(0, length);
		} else if (body.length > 48) {
			body = body.slice(0, 48);
		}

		if (bodyInput.value !== body) {
			bodyInput.value = body;
		}

		bodyInput.maxLength = length > 0 ? length : 48;
		updateHint();

		if (length > 0 && body.length !== length) {
			digitInput.value = '';
			return;
		}

		digitInput.value = checksum(type, body);
	}

	typeInput.addEventListener('change', updateChecksum);
	bodyInput.addEventListener('input', updateChecksum);
	updateChecksum();
}());
