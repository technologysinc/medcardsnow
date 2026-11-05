=== Kinetic Motion for Elementor ===
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: elementor
Stable tag: 1.0.0
License: GPLv2 or later

Scroll animations, smooth scrolling, kinetic typography, marquees and WebGL / three.js effects for Elementor — powered by GSAP.

== What you get ==

In every container, section, column and widget (Elementor → select element → Advanced tab):

* Kinetic · Entrance — 21 scroll-in animations (fades, blur, zoom, elastic, masked reveals, circle reveal, 3D flips, tilt, swing, skew), duration, delay, 12 easings + custom cubic-bezier, start point, play once / replay / reverse / in-and-out, animate inner elements one by one (order: first→last, last→first, center, edges, random), off on phones.
* Kinetic · Scroll Effects — scrubbed vertical parallax, horizontal move, rotate, scale, fade, blur (combine any), start/end points, smoothing; Pin while scrolling (length, top offset); Horizontal scroll section for containers (card width, scroll length, stacks on phones).
* Kinetic · Text Animation (widgets) — letters / words / lines effects (rise, fade, blur, 3D flip, elastic, scatter, looping wave, slide, rotate, skew), scramble/decode, typewriter, fill-in-while-scrolling. Start on scroll, on load, on hover, or linked to scrolling. Works on headings, text editor, buttons or any CSS selector.
* Kinetic · Mouse Effects — magnetic, 3D tilt with glare, cursor-follow depth parallax (desktop only).
* Kinetic · WebGL Background (containers & sections) — flowing gradient, aurora, liquid marble, morphing blobs, 3D particle field, 3D wave grid. Colors, speed, scale, intensity, density, dot size, grain, opacity, mouse & scroll reactions, quality, phones on/off.

New widgets (category "Kinetic Motion"):

* Kinetic Marquee — infinite text/logo band; speed, direction, slow on hover, follows scroll direction, speeds up and skews with scroll velocity, second row in reverse, tilt, edge fade, outline text, separators (symbol/icon, optional spin), grayscale logos.
* Kinetic Heading — rotating words (slide, flip, blur, letters cascade, wipe, typewriter, scramble), split-text reveal, fill while scrolling, hand-drawn highlights (marker, underline, double, zigzag, circle, strike, cross-out); gradient or outline fill.
* WebGL Image — three.js hover effects (water ripple, liquid, RGB shift, magnify bulge, pixelate, liquid image swap), idle wave, bend with scroll speed, scroll reveals (noise dissolve, liquid wipe, pixels, zoom-warp).
* WebGL Text — real text drawn through a shader: liquid wave, cursor ripple, RGB glitch, heat haze, stretch with scroll speed, dissolve-in. The real text stays in the page for SEO and screen readers.

Site-wide (Elementor → Kinetic Motion):

* Smooth scrolling (Lenis) with smoothness, wheel speed, optional touch smoothing and #anchor offset for sticky headers.
* Scroll progress bar, page transitions (fade / curtain up / down / left), custom cursor (dot, ring, dot + ring, difference blend).
* Load only where used (default) or everywhere, phone behaviour, reduced-motion respect, debug markers.

== Built to be stable ==

* Scripts load only on pages that use them. A page with no Kinetic settings loads nothing from this plugin.
* WebGL (three.js) loads only when a WebGL element is about to scroll into view, pauses off-screen and in background tabs, lowers its resolution automatically if frames get slow, and falls back to a still gradient on devices without a graphics processor or when it cannot keep up.
* Elements waiting for an entrance animation are hidden without a flash; if scripts are blocked, delayed or fail, everything is revealed after 3 seconds.
* Visitors who ask their device to reduce motion see a still page.
* Scroll effects use the separate CSS translate/rotate/scale properties, so they combine with entrance animations, mouse effects and Elementor's own Transform settings instead of fighting them.
* Live editing: changes preview instantly in the Elementor editor; pinning and horizontal scroll are skipped in the editor so the canvas stays editable.
* Works with LiteSpeed Cache: the tiny head snippet is excluded from JS optimization, settings changes purge the cache.

== Installation ==

1. Plugins → Add New → Upload Plugin → kinetic-motion.zip → Install → Activate (Elementor must be active).
2. Optional: Elementor → Kinetic Motion for smooth scrolling, progress bar, page transitions and cursor.
3. In Elementor, select any container or widget → Advanced → "Kinetic" sections.

== Tips ==

* Horizontal scroll: give the container a row direction and a height (e.g. 100vh); put each card in its own inner container.
* Pinning a section that contains a horizontal scroll section is not supported — use one or the other.
* If you use LiteSpeed "Load JS Delayed", add kmo- to "JS Deferred/Delayed Excludes" so animations start without waiting for a click.
* Developers: window.KMO.refresh() re-measures all scroll triggers (call it after injecting content); window.KMO.scan(element) initialises new markup.

== Licenses ==

* GSAP 3.15 (assets/vendor) — GreenSock "Standard No Charge" license, https://gsap.com/standard-license — free for use on your websites. The license restricts using GSAP in tools that compete with Webflow's visual animation builder; check it before selling or publicly distributing this plugin.
* Lenis 1.3 — MIT (assets/vendor/LICENSE-lenis.txt).
* three.js r186 — MIT, bundled into assets/js/kmo-webgl.js (assets/vendor/LICENSE-three.txt).

== Developer notes ==

Readable sources are in src/ (motion.js, site.js, webgl.js). Rebuild the minified files with:
THREE=/path/to/three/build/three.module.js sh src/build.sh   (needs esbuild)

== Changelog ==

= 1.0.0 =
* First release.
