/**
 * Skyboot Custom Icons for Elementor — Admin Dashboard interactions.
 * Vanilla JS, no dependencies. Progressive enhancement.
 * WP.org compliant: no eval, no inline scripts, no external calls.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		var root = document.querySelector('.skb-admin');
		if (!root) { return; }

		// Remove the inline style injected to prevent flickering
		var initialStyle = document.getElementById('skb-initial-tab-style');
		if (initialStyle) {
			initialStyle.parentNode.removeChild(initialStyle);
		}

		/* ================================================================
		 * Tabs — no slide animation on panels (plain display toggle)
		 * ================================================================ */
		var tabs = root.querySelectorAll('.skb-tab');
		var panels = root.querySelectorAll('.skb-panel');
		var STORAGE_KEY = 'skb_active_tab';

		function activateTab(id) {
			var matched = false;
			tabs.forEach(function (t) {
				var on = t.getAttribute('data-tab') === id;
				t.classList.toggle('is-active', on);
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				if (on) { matched = true; }
			});
			panels.forEach(function (p) {
				var on = p.getAttribute('data-panel') === id;
				/* No animation — just toggle display immediately */
				p.classList.toggle('is-active', on);
			});
			return matched;
		}

		tabs.forEach(function (t) {
			t.addEventListener('click', function () {
				var id = t.getAttribute('data-tab');
				activateTab(id);
				try { window.localStorage.setItem(STORAGE_KEY, id); } catch (e) { }
			});
		});

		/* In-page links that jump to a tab (e.g. href="#skb-manage"). */
		root.querySelectorAll('a[href^="#skb-"]').forEach(function (link) {
			link.addEventListener('click', function (e) {
				var id = link.getAttribute('href').replace('#skb-', '');
				if (activateTab(id)) {
					e.preventDefault();
					try { window.localStorage.setItem(STORAGE_KEY, id); } catch (err) { }
					window.scrollTo({ top: 0, behavior: 'smooth' });
				}
			});
		});

		/* Restore last-active tab or honour URL hash */
		var hash = window.location.hash || '';
		var initial = '';
		if (hash.indexOf('#skb-') === 0) {
			initial = hash.replace('#skb-', '');
		}
		if (!initial) {
			try { initial = window.localStorage.getItem(STORAGE_KEY) || ''; } catch (e) { }
		}
		if (!initial || !activateTab(initial)) {
			var first = tabs[0];
			if (first) { activateTab(first.getAttribute('data-tab')); }
		}

		/* ================================================================
		 * Manage Icons: text search
		 * ================================================================ */
		var search = root.querySelector('#skb-pack-search');
		var packs = Array.prototype.slice.call(root.querySelectorAll('.skb-pack'));
		var empty = root.querySelector('.skb-empty');
		var activeFilter = 'all';

		function packMatches(pack) {
			var q = search ? search.value.trim().toLowerCase() : '';
			var name = (pack.getAttribute('data-name') || '').toLowerCase();
			if (q && name.indexOf(q) === -1) { return false; }
			if (activeFilter === 'enabled' && pack.getAttribute('data-state') !== 'on') { return false; }
			if (activeFilter === 'disabled' && pack.getAttribute('data-state') !== 'off') { return false; }
			if (activeFilter === 'pro' && pack.getAttribute('data-pro') !== '1') { return false; }
			return true;
		}

		function applyFilters() {
			var visible = 0;
			packs.forEach(function (pack) {
				var show = packMatches(pack);
				pack.style.display = show ? '' : 'none';
				if (show) { visible++; }
			});
			if (empty) { empty.classList.toggle('is-visible', visible === 0); }
		}

		if (search) { search.addEventListener('input', applyFilters); }

		/* Filter chips */
		root.querySelectorAll('.skb-chip').forEach(function (chip) {
			chip.addEventListener('click', function () {
				root.querySelectorAll('.skb-chip').forEach(function (c) { c.classList.remove('is-active'); });
				chip.classList.add('is-active');
				activeFilter = chip.getAttribute('data-filter') || 'all';
				applyFilters();
			});
		});

		/* Keep data-state in sync when user toggles */
		packs.forEach(function (pack) {
			var input = pack.querySelector('.skb-toggle input[type="checkbox"]');
			if (input) {
				input.addEventListener('change', function () {
					pack.setAttribute('data-state', input.checked ? 'on' : 'off');
				});
			}
		});

		/* Enable / Disable all (free packs only) */
		function setAll(on) {
			packs.forEach(function (pack) {
				if (pack.classList.contains('is-locked')) { return; }
				var inp = pack.querySelector('.skb-toggle input[type="checkbox"]');
				if (inp) {
					inp.checked = on;
					pack.setAttribute('data-state', on ? 'on' : 'off');
				}
			});
			applyFilters();
		}
		var enableAllBtn = root.querySelector('#skb-enable-all');
		var disableAllBtn = root.querySelector('#skb-disable-all');
		if (enableAllBtn) { enableAllBtn.addEventListener('click', function () { setAll(true); }); }
		if (disableAllBtn) { disableAllBtn.addEventListener('click', function () { setAll(false); }); }

		/* ================================================================
		 * PRO upgrade modal
		 * Only closes via the ✕ close button or the "Maybe later" link.
		 * Clicking the backdrop does NOT close it (per requirement).
		 * ================================================================ */
		var cfg = (typeof window.skbCifeAdmin !== 'undefined') ? window.skbCifeAdmin : {
			upgradeUrl: 'https://skybootstrap.com/custom-icons-for-elementor/',
			i18n: {
				proTitle: 'Unlock Skyboot Pro',
				proBody: 'Everything in Free, plus Custom SVG Icon Packs (.zip upload), advanced gradients, and dynamic tags for Elementor.',
				proCta: 'Upgrade to Pro',
				close: 'Maybe later',
			},
		};

		var modal = null;

		function buildModal() {
			if (modal) { return modal; }
			modal = document.createElement('div');
			modal.className = 'skb-modal';
			modal.setAttribute('role', 'dialog');
			modal.setAttribute('aria-modal', 'true');
			modal.setAttribute('aria-label', cfg.i18n.proTitle);

			modal.innerHTML =
				'<div class="skb-modal__box">' +
				'<div class="skb-modal__header">' +
				'<button type="button" class="skb-modal__close-btn" aria-label="Close">&times;</button>' +
				'<div class="skb-modal__crown"><span class="dashicons dashicons-awards"></span></div>' +
				'<h3>' + escHtml(cfg.i18n.proTitle) + '</h3>' +
				'<p>' + escHtml(cfg.i18n.proBody) + '</p>' +
				'</div>' +
				'<div class="skb-modal__body">' +
				'<div class="skb-modal__highlight">' +
				'<span class="dashicons dashicons-lock"></span>' +
				'<span>More icon packs will be available in Pro.</span>' +
				'</div>' +
				'<div class="skb-modal__features">' +
				featureItem('More icon packs', 'Get additional icon libraries beyond the Free version.') +
				featureItem('Custom SVG Icon Packs', 'Upload .zip packs (IcoMoon, Fontello) without cluttering the Media Library.') +
				featureItem('Gradient &amp; dynamic tags', 'Advanced styling and dynamic-tag support inside Elementor.') +
				featureItem('Priority support', 'Fast, dedicated help from the Skybootstrap team.') +
				'</div>' +
				'<a class="skb-btn skb-modal__cta" href="' + escAttr(cfg.upgradeUrl) + '">' +
				'<span class="dashicons dashicons-clock"></span>' + escHtml(cfg.i18n.proCta) +
				'</a>' +
				'</div>' +
				'</div>';

			/* Close ONLY on the close button — NOT on backdrop click */
			var closeBtn = modal.querySelector('.skb-modal__close-btn');
			if (closeBtn) {
				closeBtn.addEventListener('click', function () {
					closeModal();
				});
			}

			root.appendChild(modal);
			return modal;
		}

		function featureItem(title, desc) {
			return '<div class="skb-modal__feature">' +
				'<div class="skb-modal__feature-check"><span class="dashicons dashicons-yes"></span></div>' +
				'<div class="skb-modal__feature-text"><strong>' + title + '</strong><span>' + desc + '</span></div>' +
				'</div>';
		}

		function openModal() { buildModal().classList.add('is-open'); }
		function closeModal() { if (modal) { modal.classList.remove('is-open'); } }

		root.querySelectorAll('[data-locked="1"]').forEach(function (el) {
			el.addEventListener('click', function (e) {
				e.preventDefault();
				openModal();
			});
		});

		/* Escape key still closes for accessibility */
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal) { closeModal(); }
		});

		/* ================================================================
		 * Auto-update toggle
		 * ================================================================ */
		var autoUpdateToggle = document.getElementById('skb-auto-update-toggle');
		if (autoUpdateToggle) {
			autoUpdateToggle.addEventListener('change', function () {
				var isEnabled = autoUpdateToggle.checked;

				// Disable temporarily while processing
				autoUpdateToggle.disabled = true;

				var data = new FormData();
				data.append('action', 'skb_toggle_auto_update');
				data.append('nonce', (typeof skbCifeAdmin !== 'undefined' && skbCifeAdmin.nonce) ? skbCifeAdmin.nonce : '');
				data.append('enable', isEnabled ? 'true' : 'false');

				fetch(ajaxurl, {
					method: 'POST',
					body: data
				}).then(function (response) {
					return response.json();
				}).then(function (result) {
					autoUpdateToggle.disabled = false;
					if (!result.success) {
						// Revert on failure
						autoUpdateToggle.checked = !isEnabled;
						alert('Failed to update auto-update setting.');
					}
				}).catch(function () {
					autoUpdateToggle.disabled = false;
					autoUpdateToggle.checked = !isEnabled;
					alert('An error occurred.');
				});
			});
		}

		/* ================================================================
		 * Auto-hide success notices after 3 seconds
		 * ================================================================ */
		var notices = root.querySelectorAll('.skb-notice-saved');
		if (notices.length > 0) {
			setTimeout(function () {
				notices.forEach(function (notice) {
					notice.style.transition = 'opacity 0.5s ease';
					notice.style.opacity = '0';
					setTimeout(function () {
						notice.style.display = 'none';
					}, 500);
				});
			}, 3000);
		}

		/* ================================================================
		 * AJAX Save Settings Form
		 * ================================================================ */
		var settingsForm = root.querySelector('form[action="options.php"]');
		if (settingsForm) {
			settingsForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var submitBtn = settingsForm.querySelector('.skb-btn-save');
				var btnText = submitBtn ? submitBtn.querySelector('.skb-btn-text') : null;
				var notice = settingsForm.querySelector('.skb-notice-saved');
				
				if (submitBtn) {
					submitBtn.classList.add('is-saving');
					if (btnText) btnText.textContent = 'Saving...';
				}
				if (notice) {
					notice.style.transition = 'none';
					notice.style.display = 'none';
					notice.style.opacity = '0';
				}

				var formData = new FormData(settingsForm);
				fetch('options.php', {
					method: 'POST',
					body: formData
				}).then(function () {
					// Update the active counts dynamically
					var activeCount = root.querySelectorAll('.skb-pack[data-state="on"]').length;
					var statCountEl = document.getElementById('skb-active-count');
					var inlineCountEl = document.getElementById('skb-active-inline');
					if (statCountEl) statCountEl.textContent = activeCount;
					if (inlineCountEl) inlineCountEl.textContent = activeCount;

					if (submitBtn) {
						submitBtn.classList.remove('is-saving');
						if (btnText) btnText.textContent = 'Save Changes';
					}
					if (notice) {
						notice.style.display = 'inline-flex';
						notice.style.opacity = '1';
						setTimeout(function () {
							notice.style.transition = 'opacity 0.5s ease';
							notice.style.opacity = '0';
							setTimeout(function () { notice.style.display = 'none'; }, 500);
						}, 3000);
					}
				}).catch(function () {
					if (submitBtn) {
						submitBtn.classList.remove('is-saving');
						if (btnText) btnText.textContent = 'Save Changes';
					}
					alert('An error occurred while saving.');
				});
			});
		}

		/* ================================================================
		 * Helpers
		 * ================================================================ */
		function escHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}
		function escAttr(str) { return escHtml(str); }
	});
})();
