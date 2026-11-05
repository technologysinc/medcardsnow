/*!
 * Kinetic Motion for Elementor — site-wide features
 * Smooth scrolling (Lenis), anchor links, scroll progress bar, page transitions, custom cursor.
 * No GSAP required.
 */
(function (window, document) {
	'use strict';

	var CFG = window.KMO_CONFIG || {};
	var html = document.documentElement;
	if (CFG.edit) { return; }

	var reduce = !!CFG.reduce && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var lenis = null;

	/* ------------------------------------------------------------------ */
	/* Smooth scrolling                                                    */
	/* ------------------------------------------------------------------ */

	function headerOffset() {
		return -(CFG.smooth ? CFG.smooth.offset || 0 : 0);
	}

	function initSmooth() {
		var o = CFG.smooth;
		if (!o || reduce || typeof window.Lenis !== 'function') { return; }
		try {
			lenis = new window.Lenis({
				lerp: o.lerp || 0.1,
				wheelMultiplier: o.wheel || 1,
				smoothWheel: true,
				syncTouch: !!o.touch,
				autoRaf: true,
				anchors: false,
				allowNestedScroll: true,
				stopInertiaOnNavigate: true,
				prevent: function (node) {
					return !!(node && node.closest && node.closest('[data-lenis-prevent], .elementor-popup-modal, .dialog-widget-content, .select2-results, iframe, textarea'));
				}
			});
		} catch (e) {
			lenis = null;
			return;
		}
		window.KMO_LENIS = lenis;
		try { window.dispatchEvent(new CustomEvent('kmo:lenis')); } catch (e) { /* old browsers */ }

		// Elementor popups that lock page scrolling.
		if (window.jQuery) {
			window.jQuery(document).on('elementor/popup/show', function () {
				if (document.body.classList.contains('dialog-prevent-scroll') || html.classList.contains('dialog-prevent-scroll')) { lenis.stop(); }
			});
			window.jQuery(document).on('elementor/popup/hide', function () { lenis.start(); });
		}
	}

	/* Same-page #anchor links — smooth with an offset for sticky headers. */
	function initAnchors() {
		var offset = headerOffset();
		if (!lenis && !offset) { return; }
		window.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
			var a = e.target && e.target.closest ? e.target.closest('a[href*="#"]') : null;
			if (!a || a.target === '_blank') { return; }
			var url;
			try { url = new URL(a.href, window.location.href); } catch (err) { return; }
			if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || url.search !== window.location.search) { return; }
			var hash = url.hash;
			if (!hash || hash === '#' || hash.indexOf('#elementor-action') === 0 || hash.length < 2) { return; }
			var id = decodeURIComponent(hash.slice(1));
			var target = document.getElementById(id) || document.querySelector('[name="' + id.replace(/"/g, '') + '"]');
			if (!target) { return; }
			e.preventDefault();
			e.stopPropagation();
			if (lenis) {
				lenis.scrollTo(target, { offset: offset });
			} else {
				var top = target.getBoundingClientRect().top + window.pageYOffset + offset;
				window.scrollTo({ top: top, behavior: reduce ? 'auto' : 'smooth' });
			}
			if (window.history && history.pushState) { history.pushState(null, '', hash); }
		}, true);

		// Arriving on a page with a #hash.
		if (window.location.hash && offset) {
			window.addEventListener('load', function () {
				var t = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
				if (!t) { return; }
				var top = t.getBoundingClientRect().top + window.pageYOffset + offset;
				if (lenis) { lenis.scrollTo(top, { immediate: true }); } else { window.scrollTo(0, top); }
			}, { once: true });
		}
	}

	/* ------------------------------------------------------------------ */
	/* Scroll progress bar                                                 */
	/* ------------------------------------------------------------------ */

	function initProgress() {
		var o = CFG.progress;
		if (!o) { return; }
		var bar = document.createElement('div');
		bar.className = 'kmo-progress kmo-progress--' + o.pos;
		bar.setAttribute('aria-hidden', 'true');
		bar.style.setProperty('--kmo-progress-color', o.color);
		bar.style.setProperty('--kmo-progress-h', o.height + 'px');
		document.body.appendChild(bar);

		var ticking = false;
		function update() {
			ticking = false;
			var max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
			var y = lenis ? lenis.scroll : window.pageYOffset;
			bar.style.transform = 'scaleX(' + Math.min(1, Math.max(0, y / max)).toFixed(4) + ')';
		}
		function request() {
			if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
		}
		if (lenis) { lenis.on('scroll', request); }
		window.addEventListener('scroll', request, { passive: true });
		window.addEventListener('resize', request, { passive: true });
		update();
	}

	/* ------------------------------------------------------------------ */
	/* Page transitions (the reveal half is pure CSS, started in <head>)   */
	/* ------------------------------------------------------------------ */

	function skipLink(a, e) {
		if (!a || !a.href || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return true; }
		if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-kmo-no-transition')) { return true; }
		if (a.closest('[data-kmo-no-transition], .elementor-editor-active, #wpadminbar')) { return true; }
		if (a.hasAttribute('data-elementor-open-lightbox') && a.getAttribute('data-elementor-open-lightbox') !== 'no') { return true; }
		if (/^(mailto|tel|sms|javascript|whatsapp):/i.test(a.getAttribute('href') || '')) { return true; }
		var url;
		try { url = new URL(a.href, window.location.href); } catch (err) { return true; }
		if (url.origin !== window.location.origin) { return true; }
		if (url.pathname === window.location.pathname && url.search === window.location.search) { return true; } // same page / #anchor
		if (/\/wp-(admin|login)/.test(url.pathname) || /[?&](add-to-cart|elementor-preview|preview)=/.test(url.search)) { return true; }
		if (/\.(pdf|zip|rar|7z|jpe?g|png|gif|webp|svg|mp4|mp3|webm|docx?|xlsx?|pptx?|csv)$/i.test(url.pathname)) { return true; }
		return false;
	}

	function initTransitions() {
		var o = CFG.transition;
		if (!o || reduce) { return; }

		// Remove the reveal overlay once it has played.
		if (html.classList.contains('kmo-pt-enter')) {
			setTimeout(function () { html.classList.remove('kmo-pt-enter', 'kmo-pt-go'); }, (o.ms || 600) + 400);
		}

		document.addEventListener('click', function (e) {
			var a = e.target && e.target.closest ? e.target.closest('a') : null;
			if (skipLink(a, e)) { return; }
			e.preventDefault();
			var href = a.href;
			html.classList.remove('kmo-pt-enter', 'kmo-pt-go');
			html.classList.add('kmo-pt-exit');
			try { sessionStorage.setItem('kmo-pt', '1'); } catch (err) { /* private mode */ }
			setTimeout(function () { window.location.href = href; }, o.ms || 600);
		});

		// Coming back with the browser's back button (page restored from cache).
		window.addEventListener('pageshow', function (e) {
			if (e.persisted) { html.classList.remove('kmo-pt-exit', 'kmo-pt-enter', 'kmo-pt-go'); }
		});
	}

	/* ------------------------------------------------------------------ */
	/* Custom cursor                                                       */
	/* ------------------------------------------------------------------ */

	function initCursor() {
		var o = CFG.cursor;
		if (!o || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) { return; }

		var wrap = document.createElement('div');
		wrap.className = 'kmo-cursor kmo-cursor--' + o.type + (o.blend ? ' is-blend' : '');
		wrap.setAttribute('aria-hidden', 'true');
		wrap.style.setProperty('--kmo-cursor-color', o.color);
		wrap.innerHTML = '<span class="kmo-cursor-dot"></span><span class="kmo-cursor-ring"></span>';
		document.body.appendChild(wrap);
		if (o.hide) { html.classList.add('kmo-cursor-hide'); }

		var dot = wrap.firstChild;
		var ring = wrap.lastChild;
		var mx = -100;
		var my = -100;
		var rx = mx;
		var ry = my;
		var shown = false;
		var lerp = reduce ? 1 : 0.18;

		var interactive = 'a, button, [role="button"], input[type="submit"], input[type="button"], label[for], select, summary, .elementor-button, .kmo-mq-item';

		document.addEventListener('pointermove', function (e) {
			if (e.pointerType && e.pointerType !== 'mouse') { return; }
			mx = e.clientX;
			my = e.clientY;
			if (!shown) {
				shown = true;
				rx = mx;
				ry = my;
				wrap.classList.add('is-visible');
			}
			var t = e.target && e.target.closest ? e.target.closest(interactive) : null;
			wrap.classList.toggle('is-hover', !!t);
			var field = e.target && e.target.closest ? e.target.closest('input[type="text"], input[type="email"], input[type="tel"], input[type="search"], input[type="number"], input[type="password"], textarea, [contenteditable="true"]') : null;
			wrap.classList.toggle('is-text', !!field);
		}, { passive: true });
		document.addEventListener('pointerdown', function () { wrap.classList.add('is-down'); }, { passive: true });
		document.addEventListener('pointerup', function () { wrap.classList.remove('is-down'); }, { passive: true });
		document.documentElement.addEventListener('mouseleave', function () { wrap.classList.remove('is-visible'); shown = false; });

		// Animate only while the ring is catching up, then rest.
		var running = false;
		function loop() {
			rx += (mx - rx) * lerp;
			ry += (my - ry) * lerp;
			dot.style.transform = 'translate3d(' + mx + 'px,' + my + 'px,0)';
			ring.style.transform = 'translate3d(' + rx.toFixed(1) + 'px,' + ry.toFixed(1) + 'px,0)';
			if (Math.abs(mx - rx) < 0.1 && Math.abs(my - ry) < 0.1) { running = false; return; }
			window.requestAnimationFrame(loop);
		}
		document.addEventListener('pointermove', function () {
			if (!running) { running = true; window.requestAnimationFrame(loop); }
		}, { passive: true });
	}

	function boot() {
		initSmooth();
		initAnchors();
		initProgress();
		initTransitions();
		initCursor();
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window, document);
