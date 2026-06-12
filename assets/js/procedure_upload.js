(function (window) {
	'use strict';

	function getUploadControls(form) {
		return {
			form: form,
			zone: form.querySelector('.procedure-upload-zone'),
			fileInput: form.querySelector('.procedure-upload-input'),
			selectedFilesList: form.querySelector('.procedure-selected-files'),
			uploadBtn: form.querySelector('.procedure-upload-submit'),
			uploadHint: form.querySelector('.procedure-upload-hint'),
		};
	}

	function defaultRenderFileItem(file, index, style) {
		if (style === 'span') {
			return '<span class="procedure-selected-file">' +
				'<i class="fas fa-file-zipper procedure-selected-file-icon"></i>' +
				'<span class="procedure-selected-file-name">' + window.escapeHtml(file.name) + '</span>' +
				'<button type="button" class="procedure-selected-file-remove" data-index="' + index + '" aria-label="Remove ' + window.escapeAttr(file.name) + '">' +
					'<i class="fas fa-times"></i>' +
				'</button>' +
			'</span>';
		}

		return '<div class="procedure-selected-file">' +
			'<span class="procedure-selected-file-icon"><i class="fas fa-file-zipper"></i></span>' +
			'<span class="procedure-selected-file-name">' + window.escapeHtml(file.name) + '</span>' +
			'<button type="button" class="procedure-selected-file-remove" data-index="' + index + '" aria-label="Remove file">&times;</button>' +
		'</div>';
	}

	function create(options) {
		const settings = Object.assign({
			forms: [],
			uploadModalEl: null,
			openUploadBtn: null,
			uploadUrl: '',
			fileItemStyle: 'div',
			renderFileItem: null,
			normalizeFiles: function (fileList) {
				return Array.from(fileList || []);
			},
			dropFilter: null,
			clearInputAfterSelect: false,
			emptyFilesMessage: 'Please select at least one zip file.',
			uploadErrorMessage: 'Upload failed.',
			hints: {
				empty: 'Select one or more zip files to continue.',
				selected: function (count) {
					return count + ' file(s) selected.';
				},
				uploading: 'Uploading and processing files...',
			},
			onSuccess: function () {},
		}, options);

		const forms = settings.forms.length ? Array.from(settings.forms) : [];
		let selectedFiles = [];
		let filePickerOpenUntil = 0;
		let uploadModal = null;

		function renderFileItem(file, index) {
			if (typeof settings.renderFileItem === 'function') {
				return settings.renderFileItem(file, index);
			}

			return defaultRenderFileItem(file, index, settings.fileItemStyle);
		}

		function renderSelectedFiles() {
			const filesHtml = selectedFiles.map(function (file, index) {
				return renderFileItem(file, index);
			}).join('');

			forms.forEach(function (form) {
				const controls = getUploadControls(form);

				if (!controls.selectedFilesList || !controls.uploadBtn) {
					return;
				}

				if (!selectedFiles.length) {
					controls.selectedFilesList.classList.add('d-none');
					controls.selectedFilesList.innerHTML = '';
					controls.uploadBtn.disabled = true;

					if (controls.uploadHint) {
						controls.uploadHint.textContent = settings.hints.empty;
					}

					return;
				}

				controls.selectedFilesList.classList.remove('d-none');
				controls.selectedFilesList.innerHTML = filesHtml;
				controls.uploadBtn.disabled = false;

				if (controls.uploadHint) {
					controls.uploadHint.textContent = settings.hints.selected(selectedFiles.length);
				}
			});
		}

		function setSelectedFiles(fileList) {
			const files = settings.normalizeFiles(fileList);

			if (files === null) {
				return;
			}

			selectedFiles = files;
			renderSelectedFiles();
		}

		function removeFileAt(index) {
			selectedFiles = selectedFiles.filter(function (_, fileIndex) {
				return fileIndex !== index;
			});
			renderSelectedFiles();
		}

		function resetUploadForm() {
			selectedFiles = [];

			forms.forEach(function (form) {
				const controls = getUploadControls(form);
				form.reset();

				if (controls.zone) {
					controls.zone.classList.remove('is-dragover');
				}
			});

			renderSelectedFiles();
		}

		function openUploadModal() {
			if (uploadModal) {
				uploadModal.show();
			}
		}

		function openFilePicker(fileInput) {
			if (!fileInput) {
				return;
			}

			const now = Date.now();

			if (now < filePickerOpenUntil) {
				return;
			}

			filePickerOpenUntil = now + 1000;
			fileInput.click();
		}

		function setUploadingState() {
			forms.forEach(function (form) {
				const controls = getUploadControls(form);
				controls.uploadBtn.disabled = true;
				controls.uploadHint.textContent = settings.hints.uploading;
			});
		}

		function submitUpload(event) {
			event.preventDefault();

			if (!selectedFiles.length) {
				window.showToast(settings.emptyFilesMessage, 'error');
				return;
			}

			const formData = new FormData();
			selectedFiles.forEach(function (file) {
				formData.append('zip_files[]', file);
			});

			setUploadingState();

			window.fetchApi(settings.uploadUrl, {
				method: 'POST',
				body: formData,
			})
				.then(function (result) {
					settings.onSuccess(result);
					resetUploadForm();

					if (uploadModal) {
						uploadModal.hide();
					}

					window.showToast(result.message, 'success');
				})
				.catch(function (error) {
					window.showToast(error.message || settings.uploadErrorMessage, 'error');
				})
				.finally(function () {
					renderSelectedFiles();
				});
		}

		function bindUploadForm(form) {
			const controls = getUploadControls(form);

			if (!controls.zone || !controls.fileInput) {
				return;
			}

			controls.zone.addEventListener('click', function () {
				openFilePicker(controls.fileInput);
			});

			['dragenter', 'dragover'].forEach(function (eventName) {
				controls.zone.addEventListener(eventName, function (event) {
					event.preventDefault();
					controls.zone.classList.add('is-dragover');
				});
			});

			['dragleave', 'drop'].forEach(function (eventName) {
				controls.zone.addEventListener(eventName, function (event) {
					event.preventDefault();
					controls.zone.classList.remove('is-dragover');
				});
			});

			controls.zone.addEventListener('drop', function (event) {
				let files = Array.from(event.dataTransfer.files || []);

				if (typeof settings.dropFilter === 'function') {
					files = settings.dropFilter(files);
				}

				setSelectedFiles(files);
			});

			controls.fileInput.addEventListener('change', function () {
				setSelectedFiles(controls.fileInput.files);

				if (settings.clearInputAfterSelect) {
					controls.fileInput.value = '';
				}
			});

			const removeTarget = controls.selectedFilesList || form;

			removeTarget.addEventListener('click', function (event) {
				const removeBtn = event.target.closest('.procedure-selected-file-remove');

				if (!removeBtn) {
					return;
				}

				const index = parseInt(removeBtn.getAttribute('data-index'), 10);

				if (Number.isNaN(index)) {
					return;
				}

				removeFileAt(index);
			});

			form.addEventListener('submit', submitUpload);
		}

		function init() {
			if (typeof mdb !== 'undefined' && settings.uploadModalEl) {
				uploadModal = mdb.Modal.getOrCreateInstance(settings.uploadModalEl);
				settings.uploadModalEl.addEventListener('hidden.mdb.modal', resetUploadForm);
			}

			forms.forEach(bindUploadForm);

			if (settings.openUploadBtn) {
				settings.openUploadBtn.addEventListener('click', openUploadModal);
			}

			renderSelectedFiles();
		}

		return {
			init: init,
			openModal: openUploadModal,
			reset: resetUploadForm,
			renderSelectedFiles: renderSelectedFiles,
		};
	}

	window.ProcedureUpload = {
		create: create,
	};
})(window);
