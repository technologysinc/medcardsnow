/*!
 * Kinetic Motion for Elementor — motion engine
 * Entrance, scroll effects, pinning, horizontal scroll, kinetic text, mouse effects,
 * WebGL loader and the Kinetic widgets. Uses GSAP + ScrollTrigger + SplitText.
 */
(function (window, document) {
	'use strict';

	var CFG = window.KMO_CONFIG || {};
	var html = document.documentElement;
	var gsap = window.gsap;
	var ScrollTrigger = window.ScrollTrigger;

	// Whatever happens below, never leave content hidden.
	if (window.KMO_FS) {
		clearTimeout(window.KMO_FS);
	}
	if (!gsap || !ScrollTrigger) {
		html.classList.add('kmo-failsafe');
		return;
	}

	var SplitText = window.SplitText;
	var CustomEase = window.CustomEase;
	var plugins = [ScrollTrigger];
	if (SplitText) { plugins.push(SplitText); }
	if (window.ScrambleTextPlugin) { plugins.push(window.ScrambleTextPlugin); }
	if (CustomEase) { plugins.push(CustomEase); }
	gsap.registerPlugin.apply(gsap, plugins);

	var failsafeHit = html.classList.contains('kmo-failsafe');
	var MQ_MOBILE = '(max-width: 767px)';
	var MQ_REDUCE = '(prefers-reduced-motion: reduce)';
	var CAN_HOVER = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
	var EDIT = !!CFG.edit;

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	function noop() {}
	function qsa(root, sel) {
		try { return Array.prototype.slice.call(root.querySelectorAll(sel)); } catch (e) { return []; }
	}
	function num(v, d) {
		if (v && typeof v === 'object') { v = v.size; }
		if (v === '' || v === null || v === undefined) { return d; }
		var n = parseFloat(v);
		return isNaN(n) ? d : n;
	}
	function on(v) { return v === 'yes' || v === true; }
	function parseJSON(str) {
		if (!str) { return null; }
		try { return JSON.parse(str); } catch (e) { return null; }
	}
	function isMobile() { return window.matchMedia(MQ_MOBILE).matches; }
	function reduceMotion() { return !!CFG.reduce && window.matchMedia(MQ_REDUCE).matches; }
	function motionOff() { return reduceMotion() || (CFG.mobile === 'none' && isMobile()); }
	function inViewport(el) {
		var r = el.getBoundingClientRect();
		return r.bottom > 0 && r.top < (window.innerHeight || html.clientHeight);
	}
	function run(list) {
		for (var i = 0; i < list.length; i++) {
			try { if (typeof list[i] === 'function') { list[i](); } } catch (e) { warn(e); }
		}
	}
	function warn(e) {
		if (window.console && CFG.debug) { console.warn('[Kinetic Motion]', e); }
	}
	function isEditorChrome(node) {
		var c = node.className && typeof node.className === 'string' ? node.className : '';
		return /elementor-element-overlay|elementor-editor-element-settings|ui-resizable|elementor-background-overlay|elementor-shape|kmo-gl\b/.test(c) ||
			/^(STYLE|SCRIPT|LINK|TEMPLATE)$/.test(node.tagName);
	}

	var easeCache = {};
	var easeCount = 0;
	function ease(name, custom) {
		if (name !== 'custom') { return name || 'power3.out'; }
		custom = (custom || '').trim();
		if (!custom) { return 'power3.out'; }
		if (/^-?[\d.]+\s*,\s*-?[\d.]+\s*,\s*-?[\d.]+\s*,\s*-?[\d.]+$/.test(custom)) {
			if (!CustomEase) { return 'power3.out'; }
			if (!easeCache[custom]) {
				var p = custom.split(',').map(parseFloat);
				easeCache[custom] = CustomEase.create('kmoEase' + (easeCount++), 'M0,0 C' + p[0] + ',' + p[1] + ' ' + p[2] + ',' + p[3] + ' 1,1');
			}
			return easeCache[custom];
		}
		try { return gsap.parseEase(custom) ? custom : 'power3.out'; } catch (e) { return 'power3.out'; }
	}

	/**
	 * Start position for "play when it scrolls into view" triggers.
	 * Like 'top 88%', but never beyond the end of the page (so elements at the very bottom still play)
	 * and immediate for anything already on screen when the page loads.
	 */
	function reach(el, pct) {
		return function () {
			var vh = window.innerHeight || html.clientHeight;
			var top = el.getBoundingClientRect().top + (window.pageYOffset || 0);
			var pos = top - vh * pct / 100;
			var max = ScrollTrigger.maxScroll(window) - 2;
			if (pos > max) { pos = max; }
			return pos <= 0 ? -1 : pos;
		};
	}

	function toggleActions(mode) {
		return {
			once: 'play none none none',
			replay: 'play none none reset',
			reverse: 'play none none reverse',
			inout: 'play reverse play reverse'
		}[mode] || 'play none none none';
	}

	/* Scroll velocity, shared by marquees and WebGL. */
	var VEL = { v: 0, dir: 1, t: 0, st: null };
	function trackVelocity() {
		if (VEL.st) { return; }
		VEL.st = ScrollTrigger.create({
			start: 0,
			end: 'max',
			onUpdate: function (self) {
				VEL.v = self.getVelocity();
				VEL.dir = self.direction || 1;
				VEL.t = performance.now();
			}
		});
	}
	function velocity() {
		var age = performance.now() - VEL.t;
		if (age < 100) { return VEL.v; }
		return VEL.v * Math.max(0, 1 - (age - 100) / 350);
	}

	/* Lenis smooth scroll ↔ ScrollTrigger. */
	function bridgeLenis() {
		var l = window.KMO_LENIS;
		if (l && !l.__kmoST) {
			l.__kmoST = true;
			l.on('scroll', ScrollTrigger.update);
		}
	}
	bridgeLenis();
	window.addEventListener('kmo:lenis', bridgeLenis);

	/* WebGL module, loaded on demand. */
	var glPromise = null;
	function webglOK() {
		try {
			var c = document.createElement('canvas');
			return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
		} catch (e) { return false; }
	}
	function loadGL() {
		if (window.KMO_WEBGL) { return Promise.resolve(window.KMO_WEBGL); }
		if (!glPromise) {
			glPromise = new Promise(function (resolve, reject) {
				var s = document.createElement('script');
				s.src = CFG.glUrl;
				s.async = true;
				s.onload = function () { window.KMO_WEBGL ? resolve(window.KMO_WEBGL) : reject(new Error('KMO_WEBGL missing')); };
				s.onerror = function () { glPromise = null; reject(new Error('WebGL script failed to load')); };
				document.head.appendChild(s);
			});
		}
		return glPromise;
	}
	function whenNear(el, margin, cb) {
		if (!('IntersectionObserver' in window)) { cb(); return noop; }
		var io = new IntersectionObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				if (entries[i].isIntersecting) { io.disconnect(); cb(); return; }
			}
		}, { rootMargin: margin });
		io.observe(el);
		return function () { io.disconnect(); };
	}

	/* ------------------------------------------------------------------ */
	/* 1. Entrance                                                         */
	/* ------------------------------------------------------------------ */

	var PRESETS = {
		'fade': function () { return { opacity: 0 }; },
		'fade-up': function (d) { return { opacity: 0, y: d }; },
		'fade-down': function (d) { return { opacity: 0, y: -d }; },
		'fade-left': function (d) { return { opacity: 0, x: d }; },
		'fade-right': function (d) { return { opacity: 0, x: -d }; },
		'blur-in': function () { return { opacity: 0, filter: 'blur(16px)', _to: { opacity: 1, filter: 'blur(0px)' } }; },
		'zoom-in': function () { return { opacity: 0, scale: 0.85 }; },
		'zoom-out': function () { return { opacity: 0, scale: 1.15 }; },
		'elastic-pop': function () { return { opacity: 0, scale: 0.4, _ease: 'elastic.out(1,0.5)' }; },
		'clip-up': function () { return { clipPath: 'inset(100% 0% 0% 0%)', _to: { clipPath: 'inset(0% 0% 0% 0%)' } }; },
		'clip-down': function () { return { clipPath: 'inset(0% 0% 100% 0%)', _to: { clipPath: 'inset(0% 0% 0% 0%)' } }; },
		'clip-left': function () { return { clipPath: 'inset(0% 0% 0% 100%)', _to: { clipPath: 'inset(0% 0% 0% 0%)' } }; },
		'clip-right': function () { return { clipPath: 'inset(0% 100% 0% 0%)', _to: { clipPath: 'inset(0% 0% 0% 0%)' } }; },
		'clip-center': function () { return { clipPath: 'inset(50% 50% 50% 50%)', _to: { clipPath: 'inset(0% 0% 0% 0%)' } }; },
		'clip-circle': function () { return { clipPath: 'circle(0% at 50% 50%)', _to: { clipPath: 'circle(75% at 50% 50%)' } }; },
		'flip-up': function () { return { opacity: 0, rotationX: -75, transformPerspective: 1000, transformOrigin: '50% 100%' }; },
		'flip-left': function () { return { opacity: 0, rotationY: 75, transformPerspective: 1000, transformOrigin: '0% 50%' }; },
		'tilt-3d': function (d) { return { opacity: 0, y: d, rotationX: 22, rotationY: -12, transformPerspective: 1200 }; },
		'rotate-in': function (d) { return { opacity: 0, y: d * 0.6, rotation: -8, transformOrigin: '0% 100%' }; },
		'skew-up': function (d) { return { opacity: 0, y: d, skewY: 7 }; },
		'swing': function () { return { opacity: 0, rotationX: -60, transformPerspective: 800, transformOrigin: '50% 0%', _ease: 'back.out(2)' }; }
	};

	function childTargets(el, selector) {
		var list = [];
		selector = (selector || '').trim();
		if (selector) {
			list = qsa(el, selector);
		} else {
			var type = el.getAttribute('data-element_type');
			if (type === 'container') {
				list = qsa(el, ':scope > .e-con-inner > .elementor-element, :scope > .elementor-element');
			} else if (type === 'section') {
				list = qsa(el, ':scope > .elementor-container > .elementor-column, :scope > .elementor-container > .elementor-row > .elementor-column');
			} else if (type === 'column') {
				list = qsa(el, ':scope > .elementor-widget-wrap > .elementor-element, :scope > .elementor-column-wrap > .elementor-widget-wrap > .elementor-element');
			} else {
				var root = el.querySelector(':scope > .elementor-widget-container') || el;
				list = Array.prototype.slice.call(root.children).filter(function (n) { return !isEditorChrome(n); });
				// A single wrapper (e.g. <ul>) → use its children (the list items).
				if (list.length === 1 && list[0].children.length > 1) {
					list = Array.prototype.slice.call(list[0].children);
				}
			}
		}
		return list.filter(function (n) { return n.nodeType === 1 && !isEditorChrome(n); });
	}

	function clearList(vars) {
		var props = [];
		var t = false;
		Object.keys(vars).forEach(function (k) {
			if (/^(x|y|scale|rotation|rotationX|rotationY|skewY|transformPerspective|transformOrigin)$/.test(k)) { t = true; }
			if (k === 'opacity') { props.push('opacity'); }
			if (k === 'filter') { props.push('filter'); }
			if (k === 'clipPath') { props.push('clipPath'); }
		});
		if (t) { props.push('transform', 'transformOrigin'); }
		return props.join(',');
	}

	function setupEntrance(m, mobile) {
		var s = m.s;
		var el = m.el;
		var make = PRESETS[s.kmo_in];
		if (!make || (mobile && on(s.kmo_in_off_mobile))) { return; }

		var mode = s.kmo_in_mode || 'once';
		// Content already on screen when the safety timer revealed it: leave it as is.
		if ((failsafeHit && !m.edit && inViewport(el)) || (mode === 'once' && m.played.in && !m.edit)) { return; }

		var targets = on(s.kmo_in_stagger) ? childTargets(el, s.kmo_in_stagger_selector) : [];
		if (!targets.length) { targets = [el]; }

		var from = make(num(s.kmo_in_distance, 60));
		var to = from._to || null;
		var ez = from._ease || ease(s.kmo_in_ease, s.kmo_in_ease_custom);
		delete from._to;
		delete from._ease;

		var props = clearList(from);
		var vars = {
			duration: num(s.kmo_in_duration, 1),
			delay: num(s.kmo_in_delay, 0),
			ease: ez,
			immediateRender: true,
			stagger: targets.length > 1 ? { each: num(s.kmo_in_stagger_each, 0.12), from: s.kmo_in_stagger_from || 'start' } : 0,
			scrollTrigger: {
				trigger: el,
				start: reach(el, num(s.kmo_in_start, 88)),
				toggleActions: toggleActions(mode),
				markers: !!CFG.debug
			},
			onComplete: function () {
				m.played.in = true;
				if (to && to.clipPath) { gsap.set(targets, { clipPath: 'none' }); }
			}
		};
		if (mode === 'once') {
			vars.scrollTrigger.once = true;
			vars.clearProps = props;
		}

		if (to) {
			gsap.fromTo(targets, from, Object.assign({}, to, vars));
		} else {
			gsap.from(targets, Object.assign(from, vars));
		}
	}

	/* ------------------------------------------------------------------ */
	/* 2. Scroll effects & pin                                             */
	/* ------------------------------------------------------------------ */

	function setupScroll(m, mobile) {
		var s = m.s;
		var el = m.el;
		var pin = on(s.kmo_pin) && !m.edit && !(mobile && on(s.kmo_pin_off_mobile));
		var fx = on(s.kmo_sc) && !(mobile && on(s.kmo_sc_off_mobile));
		if (!pin && !fx) { return noop; }

		var from = {};
		var to = {};
		var filter = false;
		if (fx) {
			if (on(s.kmo_sc_y)) {
				var a = num(s.kmo_sc_y_amount, 150);
				from['--kmo-y'] = (a / 2) + 'px';
				to['--kmo-y'] = (-a / 2) + 'px';
			}
			if (on(s.kmo_sc_x)) {
				var b = num(s.kmo_sc_x_amount, 200);
				from['--kmo-x'] = (-b / 2) + 'px';
				to['--kmo-x'] = (b / 2) + 'px';
			}
			if (on(s.kmo_sc_rotate)) {
				from['--kmo-r'] = num(s.kmo_sc_rotate_from, -10) + 'deg';
				to['--kmo-r'] = num(s.kmo_sc_rotate_to, 10) + 'deg';
			}
			if (on(s.kmo_sc_scale)) {
				from['--kmo-s'] = num(s.kmo_sc_scale_from, 0.8);
				to['--kmo-s'] = num(s.kmo_sc_scale_to, 1.1);
			}
			if (on(s.kmo_sc_fade)) {
				from['--kmo-o'] = num(s.kmo_sc_fade_from, 0.2);
				to['--kmo-o'] = num(s.kmo_sc_fade_to, 1);
				filter = true;
			}
			if (on(s.kmo_sc_blur)) {
				from['--kmo-b'] = num(s.kmo_sc_blur_from, 12) + 'px';
				to['--kmo-b'] = num(s.kmo_sc_blur_to, 0) + 'px';
				filter = true;
			}
		}
		var hasFx = Object.keys(from).length > 0;
		var smooth = num(s.kmo_sc_smooth, 0.6);
		var pinLen = num(s.kmo_pin_length, 100);
		var pinTop = num(s.kmo_pin_offset, 0);

		var st = {
			trigger: el,
			scrub: hasFx ? (smooth > 0 ? smooth : true) : false,
			invalidateOnRefresh: true,
			markers: !!CFG.debug
		};
		if (pin) {
			st.start = 'top ' + pinTop + 'px';
			st.end = function () { return '+=' + Math.round((window.innerHeight || 800) * pinLen / 100); };
			st.pin = true;
			st.pinSpacing = true;
			st.anticipatePin = 1;
		} else {
			st.start = s.kmo_sc_start || 'top bottom';
			st.end = s.kmo_sc_end || 'bottom top';
		}

		if (hasFx) {
			el.classList.add('kmo-sc');
			if (filter) { el.classList.add('kmo-sc-f'); }
			gsap.fromTo(el, from, Object.assign({}, to, { ease: 'none', scrollTrigger: st }));
		} else {
			ScrollTrigger.create(st);
		}
		return function () {
			el.classList.remove('kmo-sc', 'kmo-sc-f');
		};
	}

	/* Horizontal scroll section (containers). */
	function relLeft(node, ancestor) {
		var x = 0;
		while (node && node !== ancestor) {
			x += node.offsetLeft;
			var parent = node.offsetParent;
			if (!parent || !ancestor.contains(parent) && parent !== ancestor) { break; }
			node = parent;
		}
		return x;
	}

	function setupHScroll(m, mobile) {
		var s = m.s;
		var el = m.el;
		if (mobile && on(s.kmo_hs_off_mobile)) {
			el.classList.add('kmo-hs-stack');
			return function () { el.classList.remove('kmo-hs-stack'); };
		}
		if (m.edit) { return noop; }
		var inner = el.querySelector(':scope > .e-con-inner');
		var track = inner || el;
		var items = qsa(track, ':scope > .elementor-element');
		if (!items.length) { return noop; }

		el.classList.add('kmo-hs');
		var w = num(s.kmo_hs_item, 40);
		if (w > 0) {
			el.classList.add('kmo-hs-w');
			el.style.setProperty('--kmo-hs-w', w + 'vw');
		}

		var dist = function () {
			var last = items[items.length - 1];
			var right = relLeft(last, el) + last.offsetWidth;
			var cs = window.getComputedStyle(track);
			var end = inner ? relLeft(inner, el) + inner.clientWidth - parseFloat(cs.paddingRight || 0) : el.clientWidth - parseFloat(cs.paddingRight || 0);
			return Math.max(0, right - end);
		};

		gsap.to(inner ? [inner] : items, {
			x: function () { return -dist(); },
			ease: 'none',
			scrollTrigger: {
				trigger: el,
				start: 'top top',
				end: function () { return '+=' + Math.max(1, dist() * num(s.kmo_hs_speed, 1)); },
				pin: true,
				scrub: 0.6,
				anticipatePin: 1,
				invalidateOnRefresh: true,
				markers: !!CFG.debug
			}
		});

		return function () {
			el.classList.remove('kmo-hs', 'kmo-hs-w');
			el.style.removeProperty('--kmo-hs-w');
		};
	}

	/* ------------------------------------------------------------------ */
	/* 3. Kinetic text (shared by the element controls and Kinetic Heading) */
	/* ------------------------------------------------------------------ */

	function rand(a, b) { return function () { return gsap.utils.random(a, b); }; }

	var TEXT = {
		'chars-rise': { type: 'lines,words,chars', mask: 'lines', key: 'chars', from: function () { return { yPercent: 115 }; } },
		'chars-fade': { type: 'words,chars', key: 'chars', from: function () { return { opacity: 0 }; } },
		'chars-blur': { type: 'words,chars', key: 'chars', from: function () { return { opacity: 0, filter: 'blur(10px)', y: 6 }; }, to: { opacity: 1, filter: 'blur(0px)', y: 0 } },
		'chars-flip': { type: 'words,chars', key: 'chars', persp: true, from: function () { return { opacity: 0, rotationX: -90, transformOrigin: '50% 50% -0.4em' }; } },
		'chars-elastic': { type: 'words,chars', key: 'chars', ease: 'elastic.out(1,0.45)', from: function () { return { opacity: 0, scale: 0, transformOrigin: '50% 70%' }; } },
		'chars-scatter': { type: 'words,chars', key: 'chars', from: function () { return { opacity: 0, x: rand(-180, 180), y: rand(-100, 100), rotation: rand(-90, 90) }; } },
		'chars-wave': { type: 'words,chars', key: 'chars', loop: true, from: function () { return { opacity: 0, y: '0.4em' }; } },
		'words-rise': { type: 'words', mask: 'words', key: 'words', from: function () { return { yPercent: 115 }; } },
		'words-fade': { type: 'words', key: 'words', from: function () { return { opacity: 0 }; } },
		'words-blur': { type: 'words', key: 'words', from: function () { return { opacity: 0, filter: 'blur(12px)' }; }, to: { opacity: 1, filter: 'blur(0px)' } },
		'words-slide': { type: 'words', key: 'words', from: function () { return { opacity: 0, x: 40 }; } },
		'words-rotate': { type: 'words', mask: 'words', key: 'words', from: function () { return { yPercent: 100, rotation: 10, transformOrigin: '0% 100%' }; } },
		'lines-rise': { type: 'lines', mask: 'lines', key: 'lines', from: function () { return { yPercent: 115 }; } },
		'lines-fade': { type: 'lines', key: 'lines', from: function () { return { opacity: 0, y: 30 }; } },
		'lines-skew': { type: 'lines', mask: 'lines', key: 'lines', from: function () { return { yPercent: 115, skewY: 7, transformOrigin: '0% 0%' }; } }
	};

	function textTarget(el, selector) {
		selector = (selector || '').trim();
		if (selector) {
			var t = qsa(el, selector);
			if (t.length) { return t; }
		}
		var picks = ['.elementor-heading-title', '.kmo-kh-title', '.elementor-button-text', '.elementor-text-editor', '.elementor-widget-container'];
		for (var i = 0; i < picks.length; i++) {
			var found = el.querySelector(picks[i]);
			if (found) { return [found]; }
		}
		var kids = Array.prototype.slice.call(el.children).filter(function (n) { return !isEditorChrome(n); });
		return kids.length ? kids : [el];
	}

	function scrollVars(scope, o) {
		if (o.trigger === 'load') { return null; }
		var start = reach(scope, num(o.start, 85));
		if (o.trigger === 'scrub') {
			return { trigger: scope, start: start, end: 'clamp(bottom 35%)', scrub: 0.6, markers: !!CFG.debug };
		}
		return {
			trigger: scope,
			start: start,
			toggleActions: o.trigger === 'scroll' ? toggleActions(o.mode) : 'play none none none',
			once: o.trigger === 'scroll' && (o.mode || 'once') === 'once',
			markers: !!CFG.debug
		};
	}

	function hoverReplay(scope, getTween) {
		if (!CAN_HOVER) { return noop; }
		var fn = function () {
			var t = getTween();
			if (t && !t.isActive() && t.progress() === 1) { t.restart(); }
		};
		scope.addEventListener('mouseenter', fn);
		return function () { scope.removeEventListener('mouseenter', fn); };
	}

	/**
	 * @param {Element} scope  element used as the scroll trigger
	 * @param {Element[]} targets text elements
	 * @param {Object} o { effect, trigger, mode, duration, stagger, delay, ease, start, chars, cursor, dim, fillBy }
	 * @return {Function} cleanup
	 */
	function textFx(scope, targets, o) {
		var cleanups = [];
		var effect = o.effect;

		if (effect === 'scramble') { return scramble(scope, targets, o); }
		if (!SplitText) { return noop; }
		if (effect === 'typewriter') { return typewriter(scope, targets, o); }
		if (effect === 'fill') { return fill(scope, targets, o); }

		var def = TEXT[effect];
		if (!def) { return noop; }

		var current = null;
		var split = SplitText.create(targets, {
			type: def.type,
			mask: def.mask,
			linesClass: 'kmo-line',
			wordsClass: 'kmo-word',
			charsClass: 'kmo-char',
			autoSplit: def.type.indexOf('lines') > -1,
			aria: 'auto',
			onSplit: function (self) {
				var pieces = self[def.key];
				if (def.persp) { gsap.set(targets, { perspective: 600 }); }
				var vars = {
					duration: num(o.duration, 0.8),
					delay: num(o.delay, 0),
					ease: def.ease || o.ease || 'power3.out',
					stagger: def.key === 'lines' ? Math.max(0.06, num(o.stagger, 0.03) * 3) : num(o.stagger, 0.03),
					scrollTrigger: scrollVars(scope, o)
				};
				if (!vars.scrollTrigger) { delete vars.scrollTrigger; }
				var fromVars = def.from();
				var tl = gsap.timeline(vars.scrollTrigger ? { scrollTrigger: vars.scrollTrigger, delay: vars.delay } : { delay: vars.delay });
				delete vars.scrollTrigger;
				delete vars.delay;
				if (def.to) {
					tl.fromTo(pieces, fromVars, Object.assign({}, def.to, vars));
				} else {
					tl.from(pieces, Object.assign(fromVars, vars));
				}
				if (def.loop) {
					tl.to(pieces, { y: '-0.18em', duration: 0.6, ease: 'sine.inOut', stagger: { each: 0.07, repeat: -1, yoyo: true } }, '>-0.1');
				}
				current = tl;
				return tl;
			}
		});
		cleanups.push(function () { split.revert(); });
		if (o.trigger === 'hover') {
			cleanups.push(hoverReplay(scope, function () { return current; }));
		}
		return function () { run(cleanups); };
	}

	function textLeaf(node) {
		while (node.children.length === 1 && node.textContent.trim() === node.children[0].textContent.trim()) {
			node = node.children[0];
		}
		return node.children.length ? null : node;
	}

	function scramble(scope, targets, o) {
		if (!window.ScrambleTextPlugin) { return noop; }
		var leaves = targets.map(textLeaf).filter(Boolean);
		if (!leaves.length) { return textFx(scope, targets, Object.assign({}, o, { effect: 'chars-fade' })); }
		var originals = leaves.map(function (l) { return l.textContent; });
		var tween = null;
		leaves.forEach(function (leaf, i) {
			var vars = {
				duration: Math.max(0.6, num(o.duration, 0.8) * 1.6),
				delay: num(o.delay, 0) + i * 0.1,
				ease: 'none',
				scrambleText: { text: originals[i], chars: o.chars || 'upperCase', revealDelay: 0.25, speed: 0.6 },
				scrollTrigger: scrollVars(scope, o)
			};
			if (!vars.scrollTrigger) { delete vars.scrollTrigger; }
			tween = gsap.to(leaf, vars);
		});
		var off = o.trigger === 'hover' ? hoverReplay(scope, function () { return tween; }) : noop;
		return function () {
			off();
			leaves.forEach(function (l, i) { l.textContent = originals[i]; });
		};
	}

	function typewriter(scope, targets, o) {
		var split = SplitText.create(targets, { type: 'chars', charsClass: 'kmo-char', aria: 'auto' });
		var heights = targets.map(function (t) { return t.offsetHeight; });
		targets.forEach(function (t, i) {
			t.style.minHeight = heights[i] + 'px';
			if (o.cursor !== false) { t.classList.add('kmo-typing'); }
		});
		gsap.set(split.chars, { display: 'none' });
		var vars = {
			display: 'inline-block',
			duration: 0.001,
			delay: num(o.delay, 0),
			stagger: Math.max(0.015, num(o.stagger, 0.05)),
			scrollTrigger: scrollVars(scope, o)
		};
		if (!vars.scrollTrigger) { delete vars.scrollTrigger; }
		var tween = gsap.to(split.chars, vars);
		var off = o.trigger === 'hover' ? hoverReplay(scope, function () { return tween; }) : noop;
		return function () {
			off();
			split.revert();
			targets.forEach(function (t) {
				t.style.minHeight = '';
				t.classList.remove('kmo-typing');
			});
		};
	}

	function fill(scope, targets, o) {
		var by = o.fillBy === 'chars' ? 'chars' : 'words';
		var split = SplitText.create(targets, { type: by === 'chars' ? 'words,chars' : 'words', wordsClass: 'kmo-word', charsClass: 'kmo-char', aria: 'auto' });
		gsap.fromTo(split[by], { opacity: num(o.dim, 0.15) }, {
			opacity: 1,
			ease: 'none',
			stagger: 0.1,
			scrollTrigger: { trigger: scope, start: reach(scope, num(o.start, 85)), end: 'clamp(bottom 45%)', scrub: 0.5, markers: !!CFG.debug }
		});
		return function () { split.revert(); };
	}

	function setupText(m, mobile) {
		var s = m.s;
		if (mobile && on(s.kmo_tx_off_mobile)) { return noop; }
		if (failsafeHit && !m.edit && inViewport(m.el)) { return noop; }
		var targets = textTarget(m.el, s.kmo_tx_selector);
		return textFx(m.el, targets, {
			effect: s.kmo_tx,
			trigger: s.kmo_tx === 'chars-wave' ? 'scroll' : (s.kmo_tx_trigger || 'scroll'),
			mode: s.kmo_tx_mode || 'once',
			duration: num(s.kmo_tx_duration, 0.8),
			stagger: num(s.kmo_tx_stagger, 0.03),
			delay: num(s.kmo_tx_delay, 0),
			ease: ease(s.kmo_tx_ease, s.kmo_tx_ease_custom),
			start: num(s.kmo_tx_start, 85),
			chars: s.kmo_tx_chars,
			cursor: on(s.kmo_tx_cursor),
			dim: num(s.kmo_tx_dim, 0.15),
			fillBy: s.kmo_tx_fill_by
		});
	}

	/* ------------------------------------------------------------------ */
	/* 4. Mouse effects                                                    */
	/* ------------------------------------------------------------------ */

	function setupMouse(m) {
		if (!CAN_HOVER) { return noop; }
		var s = m.s;
		var el = m.el;
		var type = s.kmo_mouse;
		var str = num(s.kmo_mouse_strength, 30) / 100;
		var dur = num(s.kmo_mouse_speed, 0.6);
		var opt = { duration: dur, ease: 'power3' };
		var off = [];

		function listen(target, ev, fn) {
			target.addEventListener(ev, fn, { passive: true });
			off.push(function () { target.removeEventListener(ev, fn); });
		}

		if (type === 'magnetic') {
			var mx = gsap.quickTo(el, 'x', opt);
			var my = gsap.quickTo(el, 'y', opt);
			listen(el, 'pointermove', function (e) {
				var r = el.getBoundingClientRect();
				mx((e.clientX - (r.left + r.width / 2)) * str);
				my((e.clientY - (r.top + r.height / 2)) * str);
			});
			listen(el, 'pointerleave', function () { mx(0); my(0); });
		} else if (type === 'tilt') {
			var max = 4 + 26 * str;
			var scale = num(s.kmo_mouse_scale, 1.03);
			gsap.set(el, { transformPerspective: 900 });
			var rx = gsap.quickTo(el, 'rotationX', opt);
			var ry = gsap.quickTo(el, 'rotationY', opt);
			var sc = gsap.quickTo(el, 'scale', opt);
			var glare = null;
			if (on(s.kmo_mouse_glare)) {
				glare = document.createElement('span');
				glare.className = 'kmo-glare';
				glare.setAttribute('aria-hidden', 'true');
				el.classList.add('kmo-tilt');
				el.appendChild(glare);
			}
			listen(el, 'pointermove', function (e) {
				var r = el.getBoundingClientRect();
				var px = (e.clientX - r.left) / r.width - 0.5;
				var py = (e.clientY - r.top) / r.height - 0.5;
				ry(px * max);
				rx(-py * max);
				sc(scale);
				if (glare) {
					glare.style.opacity = '1';
					glare.style.background = 'radial-gradient(circle at ' + ((px + 0.5) * 100) + '% ' + ((py + 0.5) * 100) + '%, rgba(255,255,255,.35), rgba(255,255,255,0) 60%)';
				}
			});
			listen(el, 'pointerleave', function () {
				rx(0); ry(0); sc(1);
				if (glare) { glare.style.opacity = '0'; }
			});
			off.push(function () {
				if (glare) { glare.remove(); }
				el.classList.remove('kmo-tilt');
			});
		} else if (type === 'parallax') {
			var amount = 80 * str;
			var px2 = gsap.quickTo(el, 'x', opt);
			var py2 = gsap.quickTo(el, 'y', opt);
			listen(window, 'pointermove', function (e) {
				px2((e.clientX / window.innerWidth - 0.5) * amount);
				py2((e.clientY / window.innerHeight - 0.5) * amount);
			});
		}
		return function () { run(off); };
	}

	/* ------------------------------------------------------------------ */
	/* 5. WebGL background                                                 */
	/* ------------------------------------------------------------------ */

	function setupGL(m, mobile, still) {
		var s = m.s;
		var el = m.el;
		var host = document.createElement('div');
		var c1 = s.kmo_gl_c1 || '#F2636F';
		var c2 = s.kmo_gl_c2 || '#6C5CE7';
		var c3 = s.kmo_gl_c3 || '#00C2FF';
		var bg = s.kmo_gl_bg || '#0B0B1A';
		host.className = 'kmo-gl kmo-gl--' + s.kmo_gl;
		host.setAttribute('aria-hidden', 'true');
		host.style.setProperty('--kmo-c1', c1);
		host.style.setProperty('--kmo-c2', c2);
		host.style.setProperty('--kmo-c3', c3);
		host.style.setProperty('--kmo-bg', bg);
		host.style.opacity = String(num(s.kmo_gl_opacity, 1));
		el.classList.add('kmo-has-gl');
		el.insertBefore(host, el.firstChild);

		var useGL = !still && webglOK() && !(mobile && (!on(s.kmo_gl_mobile) || CFG.mobile === 'no-gl'));
		var inst = null;
		var dead = false;
		var stop = noop;

		if (useGL) {
			trackVelocity();
			stop = whenNear(el, '300px', function () {
				loadGL().then(function (api) {
					if (dead) { return; }
					inst = api.background(host, {
						type: s.kmo_gl,
						colors: [c1, c2, c3],
						bg: bg,
						speed: num(s.kmo_gl_speed, 1),
						scale: num(s.kmo_gl_scale, 1),
						intensity: num(s.kmo_gl_intensity, 1),
						density: num(s.kmo_gl_density, 1),
						size: num(s.kmo_gl_size, 1),
						grain: num(s.kmo_gl_grain, 0.05),
						mouse: on(s.kmo_gl_mouse),
						scroll: on(s.kmo_gl_scroll),
						quality: s.kmo_gl_quality || 'medium',
						mobile: mobile,
						velocity: velocity
					});
					if (inst) { host.classList.add('is-live'); }
				}).catch(warn);
			});
		}

		return function () {
			dead = true;
			stop();
			if (inst) { inst.destroy(); }
			host.remove();
			el.classList.remove('kmo-has-gl');
		};
	}

	/* ------------------------------------------------------------------ */
	/* Element instance                                                    */
	/* ------------------------------------------------------------------ */

	function hasMotion(s) {
		return !!(s && (s.kmo_in || on(s.kmo_sc) || on(s.kmo_pin) || on(s.kmo_hs) || s.kmo_tx || s.kmo_mouse || s.kmo_gl));
	}

	function Motion(el, settings, edit) {
		this.el = el;
		this.s = settings || {};
		this.edit = !!edit;
		this.played = {};
		this.mm = gsap.matchMedia();
		this.build();
	}

	Motion.prototype.build = function () {
		var self = this;
		var s = this.s;
		var el = this.el;
		// "always" guarantees the callback runs (GSAP only calls it when at least one query matches);
		// it re-runs automatically when the phone/desktop or reduced-motion state changes.
		this.mm.add({ always: 'all', mobile: MQ_MOBILE, reduce: MQ_REDUCE }, function (c) {
			var mobile = c.conditions.mobile;
			var off = (c.conditions.reduce && CFG.reduce) || (mobile && CFG.mobile === 'none');
			var cleanups = [];
			el.classList.add('kmo-init');
			try {
				if (off) {
					if (s.kmo_gl) { cleanups.push(setupGL(self, mobile, true)); }
				} else {
					if (s.kmo_in) { setupEntrance(self, mobile); }
					if (on(s.kmo_sc) || on(s.kmo_pin)) { cleanups.push(setupScroll(self, mobile)); }
					if (on(s.kmo_hs)) { cleanups.push(setupHScroll(self, mobile)); }
					if (s.kmo_tx) { cleanups.push(setupText(self, mobile)); }
					if (s.kmo_mouse) { cleanups.push(setupMouse(self)); }
					if (s.kmo_gl) { cleanups.push(setupGL(self, mobile, false)); }
				}
			} catch (e) {
				warn(e);
			}
			return function () { run(cleanups); };
		});
	};

	Motion.prototype.destroy = function () {
		try { this.mm.revert(); } catch (e) { warn(e); }
	};

	/* ------------------------------------------------------------------ */
	/* Widgets                                                             */
	/* ------------------------------------------------------------------ */

	var WIDGETS = {};

	/* ---- Marquee ---- */
	WIDGETS.marquee = function (root, d) {
		var rows = qsa(root, ':scope > .kmo-mq-row');
		if (!rows.length) { return noop; }
		var still = motionOff();
		trackVelocity();

		var states = rows.map(function (row, i) {
			var track = row.querySelector('.kmo-mq-track');
			return {
				row: row,
				track: track,
				group: track.querySelector('.kmo-mq-group'),
				dir: (i % 2 ? -1 : 1) * (d.dir || -1),
				pos: 0,
				period: 0,
				mul: 1,
				skew: 0,
				visible: true
			};
		});

		function build(st) {
			qsa(st.track, ':scope > .is-clone').forEach(function (n) { n.remove(); });
			var w = st.group.getBoundingClientRect().width;
			var gap = parseFloat(window.getComputedStyle(st.track).columnGap) || 0;
			st.period = w + gap;
			if (!w) { return; }
			var need = Math.max(1, Math.ceil(st.row.clientWidth / st.period) + 1);
			for (var i = 0; i < need && i < 40; i++) {
				var c = st.group.cloneNode(true);
				c.classList.add('is-clone');
				c.setAttribute('aria-hidden', 'true');
				qsa(c, 'a, button').forEach(function (a) { a.setAttribute('tabindex', '-1'); });
				st.track.appendChild(c);
			}
		}
		states.forEach(build);
		root.classList.add('is-ready');
		if (still) { return function () { states.forEach(function (st) { qsa(st.track, ':scope > .is-clone').forEach(function (n) { n.remove(); }); }); }; }

		var hoverTarget = 1;
		function enter() { if (d.hover) { hoverTarget = 0.2; } }
		function leave() { hoverTarget = 1; }
		root.addEventListener('mouseenter', enter);
		root.addEventListener('mouseleave', leave);

		var io = null;
		if ('IntersectionObserver' in window) {
			io = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					states.forEach(function (st) { if (st.row === en.target) { st.visible = en.isIntersecting; } });
				});
			}, { rootMargin: '100px' });
			states.forEach(function (st) { io.observe(st.row); });
		}

		var timer = null;
		var ro = null;
		function rebuild() {
			clearTimeout(timer);
			timer = setTimeout(function () { states.forEach(build); }, 150);
		}
		if ('ResizeObserver' in window) {
			ro = new ResizeObserver(rebuild);
			ro.observe(root);
		}
		qsa(root, 'img').forEach(function (img) { if (!img.complete) { img.addEventListener('load', rebuild, { once: true }); } });
		if (document.fonts && document.fonts.ready) { document.fonts.ready.then(rebuild); }

		var maxSkew = num(d.skew, 0);
		function tick(time, dt) {
			var v = velocity();
			var boost = 1 + Math.min(Math.abs(v) / 1000 * num(d.boost, 3), 12);
			var scrollDir = d.reverse ? (VEL.dir || 1) : 1;
			for (var i = 0; i < states.length; i++) {
				var st = states[i];
				if (!st.visible || !st.period) { continue; }
				st.mul += (boost * hoverTarget - st.mul) * 0.08;
				st.pos += st.dir * scrollDir * num(d.speed, 80) * st.mul * Math.min(dt, 64) / 1000;
				var p = st.period;
				var x = ((st.pos % p) + p) % p - p;
				var tr = 'translate3d(' + x.toFixed(2) + 'px,0,0)';
				if (maxSkew) {
					var target = Math.max(-maxSkew, Math.min(maxSkew, -v / 120));
					st.skew += (target - st.skew) * 0.1;
					tr += ' skewX(' + st.skew.toFixed(2) + 'deg)';
				}
				st.track.style.transform = tr;
			}
		}
		gsap.ticker.add(tick);

		return function () {
			gsap.ticker.remove(tick);
			root.removeEventListener('mouseenter', enter);
			root.removeEventListener('mouseleave', leave);
			if (io) { io.disconnect(); }
			if (ro) { ro.disconnect(); }
			clearTimeout(timer);
			states.forEach(function (st) {
				st.track.style.transform = '';
				qsa(st.track, ':scope > .is-clone').forEach(function (n) { n.remove(); });
			});
			root.classList.remove('is-ready');
		};
	};

	/* ---- Kinetic Heading ---- */
	function rotator(root, d) {
		var wrap = root.querySelector('.kmo-kh-rot');
		var words = wrap ? qsa(wrap, '.kmo-kh-word') : [];
		if (words.length < 2) { return noop; }

		var effect = d.effect || 'slide-up';
		var D = Math.max(0.2, num(d.duration, 0.8));
		var ez = d.ease || 'power3.out';
		var index = 0;
		var visible = true;
		var call = null;
		var tl = null;
		var splits = [];
		var cleanups = [];

		root.classList.add('kmo-kh-rot--' + effect);

		// Typewriter & scramble change one word in place.
		var inPlace = effect === 'typewriter' || effect === 'scramble';
		var texts = words.map(function (w) { return w.textContent; });
		if (inPlace) {
			words.forEach(function (w, i) { if (i) { w.classList.remove('is-active'); } });
			if (effect === 'typewriter') { wrap.classList.add('kmo-typing'); }
		} else {
			gsap.set(wrap, { width: words[0].offsetWidth });
		}
		if (effect === 'letters' && SplitText) {
			words.forEach(function (w) { splits.push(SplitText.create(w, { type: 'chars', charsClass: 'kmo-char', mask: 'chars' })); });
		}

		function schedule() {
			call = gsap.delayedCall(Math.max(0.3, num(d.interval, 2.2)), next);
		}

		function next() {
			if (!visible || document.hidden) { schedule(); return; }
			var cur = words[index];
			var ni = (index + 1) % words.length;
			var nxt = words[ni];
			tl = gsap.timeline({ onComplete: schedule });

			if (inPlace) {
				var el = words[0];
				var from = texts[index];
				var to = texts[ni];
				if (effect === 'scramble' && window.ScrambleTextPlugin) {
					tl.to(el, { duration: D * 1.4, ease: 'none', scrambleText: { text: to, chars: 'upperCase', speed: 0.7 } });
				} else {
					var p = { n: from.length };
					tl.to(p, { n: 0, duration: Math.max(0.2, from.length * 0.035), ease: 'none', onUpdate: function () { el.textContent = from.slice(0, Math.round(p.n)); } });
					tl.set(p, { n: 0 }, '+=0.25');
					tl.to(p, { n: to.length, duration: Math.max(0.25, to.length * 0.06), ease: 'none', onUpdate: function () { el.textContent = to.slice(0, Math.round(p.n)); } });
				}
				index = ni;
				return;
			}

			cur.classList.add('is-leaving');
			nxt.classList.add('is-active');
			tl.to(wrap, { width: nxt.offsetWidth, duration: D, ease: 'power3.inOut' }, 0);

			var out;
			var inn;
			switch (effect) {
				case 'slide-down':
					out = { yPercent: 100, opacity: 0 }; inn = { yPercent: -100, opacity: 0 }; break;
				case 'flip':
					out = { rotationX: 90, opacity: 0, transformOrigin: '50% 50% -0.5em' }; inn = { rotationX: -90, opacity: 0, transformOrigin: '50% 50% -0.5em' }; break;
				case 'blur':
					out = { filter: 'blur(12px)', opacity: 0, scale: 1.08 }; inn = { filter: 'blur(12px)', opacity: 0, scale: 0.92 }; break;
				case 'clip':
					out = { clipPath: 'inset(0% 0% 0% 100%)' }; inn = { clipPath: 'inset(0% 100% 0% 0%)' }; break;
				case 'letters':
					out = null; break;
				default:
					out = { yPercent: -100, opacity: 0 }; inn = { yPercent: 100, opacity: 0 };
			}

			if (effect === 'letters' && splits.length) {
				var oc = splits[index].chars;
				var nc = splits[ni].chars;
				tl.to(oc, { yPercent: -110, duration: D * 0.7, ease: 'power3.in', stagger: num(d.stagger, 0.03) }, 0);
				tl.fromTo(nc, { yPercent: 110 }, { yPercent: 0, duration: D, ease: ez, stagger: num(d.stagger, 0.03) }, D * 0.3);
			} else {
				var reset = { yPercent: 0, opacity: 1, rotationX: 0, scale: 1, filter: 'blur(0px)', clipPath: 'inset(0% 0% 0% 0%)' };
				var keys = Object.keys(inn);
				var target = {};
				keys.forEach(function (k) { if (k !== 'transformOrigin') { target[k] = reset[k]; } });
				tl.to(cur, Object.assign({ duration: D * 0.8, ease: 'power2.in' }, out), 0);
				tl.fromTo(nxt, inn, Object.assign({ duration: D, ease: ez }, target), D * 0.15);
			}
			tl.call(function () {
				cur.classList.remove('is-leaving', 'is-active');
				gsap.set(cur, { clearProps: 'transform,opacity,filter,clipPath' });
			});
			index = ni;
		}

		if ('IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (en) { visible = en[0].isIntersecting; });
			io.observe(root);
			cleanups.push(function () { io.disconnect(); });
		}
		schedule();

		return function () {
			if (call) { call.kill(); }
			if (tl) { tl.kill(); }
			run(cleanups);
			splits.forEach(function (sp) { sp.revert(); });
			words.forEach(function (w, i) {
				w.textContent = texts[i];
				w.classList.toggle('is-active', i === 0);
				w.classList.remove('is-leaving');
				gsap.set(w, { clearProps: 'all' });
			});
			wrap.classList.remove('kmo-typing');
			gsap.set(wrap, { clearProps: 'width' });
			root.classList.remove('kmo-kh-rot--' + effect);
		};
	}

	function highlight(root, d) {
		var marker = root.querySelector('.kmo-kh-hl-marker');
		var path = root.querySelector('.kmo-kh-hl-svg path');
		var target = marker || path;
		if (!target) { return noop; }
		var st = scrollVars(root, { trigger: d.trigger, mode: d.playback, start: 85 });
		var vars = { duration: Math.max(0.3, num(d.duration, 0.8) * 1.3), delay: num(d.delay, 0) + 0.15, ease: 'power2.inOut' };
		if (st) { vars.scrollTrigger = st; }
		var tween = marker ?
			gsap.fromTo(marker, { scaleX: 0 }, Object.assign({ scaleX: 1 }, vars)) :
			gsap.fromTo(path, { strokeDashoffset: 1 }, Object.assign({ strokeDashoffset: 0 }, vars));
		root.classList.add('kmo-ready');
		return d.trigger === 'hover' ? hoverReplay(root, function () { return tween; }) : noop;
	}

	WIDGETS.heading = function (root, d) {
		var title = root.querySelector('.kmo-kh-title');
		if (!title) { return noop; }
		if (motionOff()) {
			root.classList.add('kmo-ready');
			return noop;
		}
		var cleanup = noop;
		if (d.mode === 'rotate') {
			cleanup = rotator(root, d);
		} else if (d.mode === 'highlight') {
			cleanup = highlight(root, d);
		} else if (d.mode === 'fill') {
			cleanup = fill(root, [title], { dim: d.dim, fillBy: d.fillBy, start: 85 });
		} else if (failsafeHit && inViewport(root)) {
			cleanup = noop;
		} else {
			cleanup = textFx(root, [title], {
				effect: d.effect || 'chars-rise',
				trigger: d.trigger || 'scroll',
				mode: d.playback || 'once',
				duration: d.duration,
				stagger: d.stagger,
				delay: d.delay,
				ease: d.ease,
				start: 85,
				cursor: true
			});
		}
		root.classList.add('kmo-ready');
		return function () {
			cleanup();
			root.classList.remove('kmo-ready');
		};
	};

	/* ---- WebGL Image & Text ---- */
	function glWidget(kind) {
		return function (root, d) {
			if (motionOff() || !webglOK() || (isMobile() && !d.mobile)) {
				root.classList.add('kmo-gl-off');
				return noop;
			}
			trackVelocity();
			var inst = null;
			var dead = false;
			var revealSt = null;
			var stop = whenNear(root, '250px', function () {
				loadGL().then(function (api) {
					if (dead) { return; }
					var wasVisible = inViewport(root);
					var opts = Object.assign({}, d, { velocity: velocity, edit: EDIT });
					inst = api[kind](root, opts);
					if (!inst) { root.classList.add('kmo-gl-off'); return; }
					var wantsReveal = kind === 'image' ? (d.reveal && d.reveal !== 'none') : !!d.reveal;
					if (!wantsReveal || (wasVisible && !EDIT)) {
						inst.reveal(1);
						return;
					}
					inst.reveal(0);
					var p = { v: 0 };
					var tw = gsap.to(p, {
						v: 1,
						duration: num(d.revealDur, 1.6),
						ease: 'power2.inOut',
						paused: true,
						onUpdate: function () { if (inst) { inst.reveal(p.v); } }
					});
					revealSt = ScrollTrigger.create({ trigger: root, start: reach(root, 85), once: true, onEnter: function () { tw.play(); } });
				}).catch(function (e) {
					warn(e);
					root.classList.add('kmo-gl-off');
				});
			});
			return function () {
				dead = true;
				stop();
				if (revealSt) { revealSt.kill(); }
				if (inst) { inst.destroy(); }
				root.classList.remove('kmo-gl-off');
			};
		};
	}
	WIDGETS.glimage = glWidget('image');
	WIDGETS.gltext = glWidget('text');

	/* ------------------------------------------------------------------ */
	/* Registry & boot                                                     */
	/* ------------------------------------------------------------------ */

	var elements = new WeakMap();
	var widgets = new WeakMap();

	function initElement(el, settings, edit) {
		var old = elements.get(el);
		if (old) { old.destroy(); elements.delete(el); }
		if (!hasMotion(settings)) { return null; }
		var inst = new Motion(el, settings, edit);
		elements.set(el, inst);
		return inst;
	}

	function destroyElement(el) {
		var inst = elements.get(el);
		if (inst) { inst.destroy(); elements.delete(el); }
	}

	function initWidget(root) {
		if (widgets.has(root)) { return; }
		var data = parseJSON(root.getAttribute('data-kmo'));
		var fn = data && WIDGETS[data.type];
		if (!fn) { return; }
		var extra = noop;
		var ctx = gsap.context(function () {
			try { extra = fn(root, data) || noop; } catch (e) { warn(e); }
		}, root);
		widgets.set(root, function () {
			try { extra(); } catch (e) { warn(e); }
			ctx.revert();
		});
	}

	function destroyWidget(root) {
		var fn = widgets.get(root);
		if (fn) { fn(); widgets.delete(root); }
	}

	/* Widgets that belong to this element (not to a nested element). */
	function ownWidgets(el) {
		return qsa(el, '.kmo-widget[data-kmo]').filter(function (w) {
			var owner = w.parentElement && w.parentElement.closest('.elementor-element');
			return owner === el;
		});
	}

	function scan(root) {
		root = root || document;
		var nodes = qsa(root, '[data-settings*="kmo_"]');
		if (root.nodeType === 1 && root.matches && root.matches('[data-settings*="kmo_"]')) { nodes.unshift(root); }
		nodes.forEach(function (el) {
			if (elements.has(el)) { return; }
			initElement(el, parseJSON(el.getAttribute('data-settings')), false);
		});
		qsa(root, '.kmo-widget[data-kmo]').forEach(initWidget);
	}

	/* Elementor editor: one handler per element, rebuilt on every change. */
	function bootEditor() {
		var Base = window.elementorModules && elementorModules.frontend && elementorModules.frontend.handlers && elementorModules.frontend.handlers.Base;
		if (!Base) { return; }
		var Handler = Base.extend({
			// Handlers made with .extend() are all named "child"; a unique ID stops Elementor
			// from mixing ours up with other add-ons' handlers on the same element.
			getConstructorID: function () { return 'KineticMotionHandler'; },
			getWidgetType: function () { return 'global'; },
			onInit: function () {
				Base.prototype.onInit.apply(this, arguments);
				this.kmoBuild();
			},
			kmoBuild: function () {
				var el = this.$element && this.$element[0];
				if (!el) { return; }
				initElement(el, this.getElementSettings(), true);
				ownWidgets(el).forEach(function (w) { destroyWidget(w); initWidget(w); });
			},
			onElementChange: function (name) {
				if (!name || name.indexOf('kmo_') !== 0) { return; }
				var self = this;
				clearTimeout(this.kmoTimer);
				this.kmoTimer = setTimeout(function () {
					self.kmoBuild();
					ScrollTrigger.refresh();
				}, 200);
			},
			onDestroy: function () {
				Base.prototype.onDestroy.apply(this, arguments);
				clearTimeout(this.kmoTimer);
				var el = this.$element && this.$element[0];
				if (!el) { return; }
				destroyElement(el);
				ownWidgets(el).forEach(destroyWidget);
			}
		});
		elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
			elementorFrontend.elementsHandler.addHandler(Handler, { $element: $scope });
		});
	}

	var hooked = false;
	function hookElementor() {
		if (hooked || !window.elementorFrontend || !elementorFrontend.hooks) { return; }
		hooked = true;
		var editMode = EDIT || (typeof elementorFrontend.isEditMode === 'function' && elementorFrontend.isEditMode());
		if (editMode) {
			bootEditor();
			return;
		}
		// Popups, loop grids and other content Elementor adds later.
		elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
			if ($scope && $scope[0]) { scan($scope[0]); }
		});
	}

	window.addEventListener('elementor/frontend/init', hookElementor);
	if (window.jQuery) { window.jQuery(window).on('elementor/frontend/init', hookElementor); }
	hookElementor();

	function boot() {
		if (!EDIT) { scan(document); }
		hookElementor();
		// Fonts and lazy images change heights — re-measure once everything settled.
		window.addEventListener('load', function () { ScrollTrigger.refresh(); }, { once: true });
		if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { ScrollTrigger.refresh(); }); }
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	// Small public API for custom code: KMO.refresh(), KMO.scan(el)
	window.KMO = {
		version: CFG.v,
		refresh: function () { ScrollTrigger.refresh(); },
		scan: scan,
		init: initElement,
		destroy: destroyElement,
		velocity: velocity
	};
})(window, document);
