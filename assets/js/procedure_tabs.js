(function (window) {
	'use strict';

	function create(options) {
		const settings = Object.assign({
			tabsWrapper: null,
			tabsNav: null,
			tabsContent: null,
			initialUpload: null,
			openUploadBtn: null,
			tabCountEl: null,
			tabCountSuffix: ' zip file(s)',
			idAttribute: 'data-registration-id',
			getTabId: function (tab) {
				return tab.id;
			},
			buildTabButton: function () {
				return '';
			},
			buildTabPane: function () {
				return '';
			},
			deleteUrl: function () {
				return '';
			},
			deleteConfirm: {
				message: 'Stop this procedure?',
				confirmLabel: 'Stop',
				errorMessage: 'Failed to delete.',
			},
			deleteBtnSelector: null,
			closeBtnSelector: null,
			onBeforePrepend: null,
		}, options);

		function tabExists(registrationId) {
			return !!settings.tabsNav.querySelector('.nav-link[' + settings.idAttribute + '="' + registrationId + '"]');
		}

		function deactivateTabs() {
			settings.tabsNav.querySelectorAll('.nav-link').forEach(function (button) {
				button.classList.remove('active');
				button.setAttribute('aria-selected', 'false');
			});
			settings.tabsContent.querySelectorAll('.tab-pane').forEach(function (pane) {
				pane.classList.remove('show', 'active');
			});
		}

		function activateTab(registrationId) {
			deactivateTabs();

			const button = settings.tabsNav.querySelector('.nav-link[' + settings.idAttribute + '="' + registrationId + '"]');
			const pane = settings.tabsContent.querySelector('.tab-pane[' + settings.idAttribute + '="' + registrationId + '"]');

			if (button) {
				button.classList.add('active');
				button.setAttribute('aria-selected', 'true');
			}

			if (pane) {
				pane.classList.add('show', 'active');
			}
		}

		function initTabControls(scope) {
			if (typeof mdb === 'undefined') {
				return;
			}

			(scope || document).querySelectorAll('[data-mdb-tab-init]').forEach(function (element) {
				mdb.Tab.getOrCreateInstance(element);
			});
		}

		function updateTabCount() {
			if (!settings.tabCountEl) {
				return;
			}

			const count = settings.tabsNav.querySelectorAll('.nav-item').length;
			settings.tabCountEl.textContent = count + settings.tabCountSuffix;
		}

		function removeTabFromDom(registrationId) {
			const tabBtn = settings.tabsNav.querySelector('.nav-link[' + settings.idAttribute + '="' + registrationId + '"]');
			const navItem = tabBtn ? tabBtn.closest('.nav-item') : null;
			const pane = settings.tabsContent.querySelector('.tab-pane[' + settings.idAttribute + '="' + registrationId + '"]');
			const wasActive = tabBtn && tabBtn.classList.contains('active');

			if (navItem) {
				navItem.remove();
			}

			if (pane) {
				pane.remove();
			}

			const remainingTabs = settings.tabsNav.querySelectorAll('.nav-item');

			if (!remainingTabs.length) {
				if (settings.tabsWrapper) {
					settings.tabsWrapper.classList.add('d-none');
				}

				if (settings.initialUpload) {
					settings.initialUpload.classList.remove('d-none');
				}

				if (settings.openUploadBtn) {
					settings.openUploadBtn.classList.add('d-none');
				}
			} else if (wasActive) {
				const nextTab = settings.tabsNav.querySelector('.nav-link');

				if (nextTab) {
					activateTab(nextTab.getAttribute(settings.idAttribute));
				}
			}

			updateTabCount();
		}

		function closeTab(registrationId) {
			removeTabFromDom(registrationId);
		}

		function deleteRegistration(registrationId) {
			return window.fetchApi(settings.deleteUrl(registrationId), {
				method: 'POST',
			}).then(function (result) {
				removeTabFromDom(registrationId);
				window.showToast(result.message, 'success');
			});
		}

		function openDeleteModal(registrationId, fileName) {
			if (!registrationId) {
				return;
			}

			window.showConfirm({
				title: fileName || '—',
				message: settings.deleteConfirm.message,
				confirmLabel: settings.deleteConfirm.confirmLabel,
			})
				.then(function () {
					return deleteRegistration(registrationId);
				})
				.catch(function (error) {
					if (window.isConfirmCancelled(error)) {
						return;
					}

					window.showToast(error.message || settings.deleteConfirm.errorMessage, 'error');
				});
		}

		function prependTabs(tabs, prependOptions) {
			const context = prependOptions || {};

			if (!tabs.length) {
				return;
			}

			if (settings.initialUpload) {
				settings.initialUpload.classList.add('d-none');
			}

			if (settings.openUploadBtn) {
				settings.openUploadBtn.classList.remove('d-none');
			}

			if (settings.tabsWrapper) {
				settings.tabsWrapper.classList.remove('d-none');
			}

			const newTabs = tabs.filter(function (tab) {
				return !tabExists(settings.getTabId(tab));
			});

			const isImported = !!context.imported;

			newTabs.slice().reverse().forEach(function (tab) {
				if (typeof settings.onBeforePrepend === 'function') {
					settings.onBeforePrepend(tab, context);
				}

				settings.tabsNav.insertAdjacentHTML('afterbegin', settings.buildTabButton(tab, false, isImported));
				settings.tabsContent.insertAdjacentHTML('afterbegin', settings.buildTabPane(tab, false, isImported));
			});

			initTabControls(settings.tabsWrapper);

			if (newTabs.length) {
				activateTab(settings.getTabId(newTabs[0]));
			}

			updateTabCount();
		}

		function bindClickHandlers() {
			document.addEventListener('click', function (event) {
				if (settings.closeBtnSelector) {
					const closeBtn = event.target.closest(settings.closeBtnSelector);

					if (closeBtn) {
						event.preventDefault();
						event.stopPropagation();
						closeTab(closeBtn.getAttribute(settings.idAttribute));
						return;
					}
				}

				if (settings.deleteBtnSelector) {
					const deleteBtn = event.target.closest(settings.deleteBtnSelector);

					if (deleteBtn) {
						if (settings.closeBtnSelector) {
							event.preventDefault();
							event.stopPropagation();
						}

						openDeleteModal(
							deleteBtn.getAttribute(settings.idAttribute),
							deleteBtn.getAttribute('data-file-name')
						);
					}
				}
			});
		}

		function init() {
			bindClickHandlers();
			initTabControls(settings.tabsWrapper);
			updateTabCount();
		}

		return {
			init: init,
			prependTabs: prependTabs,
			activateTab: activateTab,
			tabExists: tabExists,
			removeTabFromDom: removeTabFromDom,
			closeTab: closeTab,
			openDeleteModal: openDeleteModal,
			initTabControls: initTabControls,
			updateTabCount: updateTabCount,
		};
	}

	window.ProcedureTabs = {
		create: create,
	};
})(window);
