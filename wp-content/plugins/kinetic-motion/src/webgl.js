/*!
 * Kinetic Motion for Elementor — WebGL effects (three.js)
 * Built into assets/js/kmo-webgl.js with esbuild (see src/build.sh). Loaded on demand.
 */
import {
	WebGLRenderer,
	Scene,
	OrthographicCamera,
	PerspectiveCamera,
	Mesh,
	PlaneGeometry,
	ShaderMaterial,
	Points,
	BufferGeometry,
	Float32BufferAttribute,
	Vector2,
	Vector3,
	Texture,
	CanvasTexture,
	LinearFilter,
	ClampToEdgeWrapping,
	AdditiveBlending,
	NormalBlending,
	Group,
	ColorManagement
} from 'three';

// This bundle owns its copy of three — keep colors exactly as picked in Elementor.
ColorManagement.enabled = false;

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

function color(str, fallback) {
	const v = new Vector3();
	str = (str || fallback || '#000').trim();
	let m = str.match(/^#([0-9a-f]{3,8})$/i);
	if (m) {
		let h = m[1];
		if (h.length === 3 || h.length === 4) { h = h.split('').map((c) => c + c).join(''); }
		v.set(parseInt(h.slice(0, 2), 16) / 255, parseInt(h.slice(2, 4), 16) / 255, parseInt(h.slice(4, 6), 16) / 255);
		return v;
	}
	m = str.match(/rgba?\(\s*([\d.]+)[ ,]+([\d.]+)[ ,]+([\d.]+)/i);
	if (m) {
		v.set(+m[1] / 255, +m[2] / 255, +m[3] / 255);
		return v;
	}
	return fallback ? color(fallback) : v;
}

const POINTER = { x: -1e4, y: -1e4, active: false };
let pointerBound = false;
function bindPointer() {
	if (pointerBound) { return; }
	pointerBound = true;
	window.addEventListener('pointermove', (e) => {
		POINTER.x = e.clientX;
		POINTER.y = e.clientY;
		POINTER.active = true;
	}, { passive: true });
	document.documentElement.addEventListener('mouseleave', () => { POINTER.active = false; });
}

// Pixel-ratio caps. Full-screen shaders are soft by nature, so they render below retina
// resolution; dot-based scenes and texture planes need sharper pixels.
const DPR = {
	shader: { low: 0.6, medium: 1, high: 1.5 },
	points: { low: 1, medium: 1.5, high: 2 },
	plane: { low: 1, medium: 1.5, high: 2 }
};

/* Software rendering (no GPU) makes shaders freeze the page — skip WebGL there. */
let softwareCache = null;
function isSoftwareGL() {
	if (window.KMO_FORCE_GL) { return false; }
	if (softwareCache !== null) { return softwareCache; }
	softwareCache = false;
	try {
		const c = document.createElement('canvas');
		const gl = c.getContext('webgl') || c.getContext('experimental-webgl');
		if (gl) {
			const ext = gl.getExtension('WEBGL_debug_renderer_info');
			const name = String(ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER));
			softwareCache = /swiftshader|llvmpipe|softpipe|software|basic render/i.test(name);
			const lose = gl.getExtension('WEBGL_lose_context');
			if (lose) { lose.loseContext(); }
		}
	} catch (e) { /* keep false */ }
	return softwareCache;
}

const NOISE = /* glsl */ `
vec3 mod289(vec3 x){return x-floor(x*(1.0/289.0))*289.0;}
vec4 mod289(vec4 x){return x-floor(x*(1.0/289.0))*289.0;}
vec4 permute(vec4 x){return mod289(((x*34.0)+1.0)*x);}
vec4 taylorInvSqrt(vec4 r){return 1.79284291400159-0.85373472095314*r;}
float snoise(vec3 v){
  const vec2 C=vec2(1.0/6.0,1.0/3.0);
  const vec4 D=vec4(0.0,0.5,1.0,2.0);
  vec3 i=floor(v+dot(v,C.yyy));
  vec3 x0=v-i+dot(i,C.xxx);
  vec3 g=step(x0.yzx,x0.xyz);
  vec3 l=1.0-g;
  vec3 i1=min(g.xyz,l.zxy);
  vec3 i2=max(g.xyz,l.zxy);
  vec3 x1=x0-i1+C.xxx;
  vec3 x2=x0-i2+C.yyy;
  vec3 x3=x0-D.yyy;
  i=mod289(i);
  vec4 p=permute(permute(permute(i.z+vec4(0.0,i1.z,i2.z,1.0))+i.y+vec4(0.0,i1.y,i2.y,1.0))+i.x+vec4(0.0,i1.x,i2.x,1.0));
  float n_=0.142857142857;
  vec3 ns=n_*D.wyz-D.xzx;
  vec4 j=p-49.0*floor(p*ns.z*ns.z);
  vec4 x_=floor(j*ns.z);
  vec4 y_=floor(j-7.0*x_);
  vec4 x=x_*ns.x+ns.yyyy;
  vec4 y=y_*ns.x+ns.yyyy;
  vec4 h=1.0-abs(x)-abs(y);
  vec4 b0=vec4(x.xy,y.xy);
  vec4 b1=vec4(x.zw,y.zw);
  vec4 s0=floor(b0)*2.0+1.0;
  vec4 s1=floor(b1)*2.0+1.0;
  vec4 sh=-step(h,vec4(0.0));
  vec4 a0=b0.xzyw+s0.xzyw*sh.xxyy;
  vec4 a1=b1.xzyw+s1.xzyw*sh.zzww;
  vec3 p0=vec3(a0.xy,h.x);
  vec3 p1=vec3(a0.zw,h.y);
  vec3 p2=vec3(a1.xy,h.z);
  vec3 p3=vec3(a1.zw,h.w);
  vec4 norm=taylorInvSqrt(vec4(dot(p0,p0),dot(p1,p1),dot(p2,p2),dot(p3,p3)));
  p0*=norm.x;p1*=norm.y;p2*=norm.z;p3*=norm.w;
  vec4 m=max(0.6-vec4(dot(x0,x0),dot(x1,x1),dot(x2,x2),dot(x3,x3)),0.0);
  m=m*m;
  return 42.0*dot(m*m,vec4(dot(p0,x0),dot(p1,x1),dot(p2,x2),dot(p3,x3)));
}
float hash12(vec2 p){vec3 p3=fract(vec3(p.xyx)*0.1031);p3+=dot(p3,p3.yzx+33.33);return fract((p3.x+p3.y)*p3.z);}
`;

const QUAD_VERT = /* glsl */ `
varying vec2 vUv;
void main(){ vUv = uv; gl_Position = vec4(position.xy, 0.0, 1.0); }
`;

/* ------------------------------------------------------------------ */
/* Stage: renderer + loop that only runs while visible                  */
/* ------------------------------------------------------------------ */

class Stage {
	constructor(host, opts, alpha, kind) {
		if (isSoftwareGL()) { throw new Error('Software WebGL — using the still fallback'); }
		this.host = host;
		this.opts = opts || {};
		this.kind = kind || 'shader';
		this.frameAvg = 1 / 60;
		this.frames = 0;
		this.slowStrikes = 0;
		this.time = Math.random() * 100;
		this.running = false;
		this.visible = false;
		this.dead = false;
		this.vel = 0;
		this.mouse = new Vector2(0.5, 0.5);
		this.mouseTarget = new Vector2(0.5, 0.5);
		this.hover = 0;
		this.hoverTarget = 0;
		this.disposables = [];

		this.renderer = new WebGLRenderer({ antialias: false, alpha: !!alpha, premultipliedAlpha: true, powerPreference: 'default' });
		const caps = DPR[this.kind] || DPR.shader;
		this.maxDpr = Math.min(window.devicePixelRatio || 1, caps[this.opts.quality] || caps.medium);
		this.renderer.setPixelRatio(this.maxDpr);
		this.canvas = this.renderer.domElement;
		this.canvas.className = 'kmo-gl-canvas';
		this.canvas.setAttribute('aria-hidden', 'true');
		host.appendChild(this.canvas);

		this.loop = this.loop.bind(this);
		this.onLost = (e) => {
			e.preventDefault();
			this.fail();
		};
		this.canvas.addEventListener('webglcontextlost', this.onLost);

		this.onVis = () => this.sync();
		document.addEventListener('visibilitychange', this.onVis);

		if ('IntersectionObserver' in window) {
			this.io = new IntersectionObserver((en) => {
				this.visible = en[0].isIntersecting;
				this.sync();
			}, { rootMargin: '100px' });
			this.io.observe(host);
		} else {
			this.visible = true;
		}
		if ('ResizeObserver' in window) {
			this.ro = new ResizeObserver(() => this.resize());
			this.ro.observe(host);
		}
		bindPointer();
	}

	size() {
		const r = this.host.getBoundingClientRect();
		return { w: Math.max(1, Math.round(r.width)), h: Math.max(1, Math.round(r.height)) };
	}

	resize() {
		if (this.dead) { return; }
		const { w, h } = this.size();
		this.renderer.setSize(w, h, false);
		if (this.onResize) { this.onResize(w, h); }
		if (!this.running) { this.render(); }
	}

	sync() {
		const go = this.visible && !document.hidden && !this.dead;
		if (go && !this.running) {
			this.running = true;
			this.last = performance.now();
			requestAnimationFrame(this.loop);
		} else if (!go) {
			this.running = false;
		}
	}

	updatePointer(dt) {
		const r = this.host.getBoundingClientRect();
		const inside = POINTER.active && POINTER.x >= r.left && POINTER.x <= r.right && POINTER.y >= r.top && POINTER.y <= r.bottom;
		if (POINTER.active && r.width && r.height) {
			this.mouseTarget.set((POINTER.x - r.left) / r.width, 1 - (POINTER.y - r.top) / r.height);
		}
		this.hoverTarget = inside ? 1 : 0;
		const k = 1 - Math.pow(0.001, dt);
		this.mouse.lerp(this.mouseTarget, Math.min(1, k * 2.5));
		this.hover += (this.hoverTarget - this.hover) * Math.min(1, k * 1.6);
		const v = typeof this.opts.velocity === 'function' ? this.opts.velocity() : 0;
		const vn = Math.max(-1, Math.min(1, v / 3000));
		this.vel += (vn - this.vel) * Math.min(1, k * 1.5);
	}

	loop(now) {
		if (!this.running || this.dead) { return; }
		const raw = (now - this.last) / 1000;
		const dt = Math.min(0.05, raw) || 0.016;
		this.last = now;
		this.time += dt * (this.opts.speed === undefined ? 1 : this.opts.speed);
		this.updatePointer(dt);
		this.render(dt);
		this.govern(raw);
		if (!this.dead) { requestAnimationFrame(this.loop); }
	}

	/**
	 * Performance guard: if frames get slow, lower the resolution step by step;
	 * if it is still slow at the lowest step, stop and keep the still fallback.
	 */
	govern(raw) {
		// The clock restarts whenever the loop resumes, so long gaps here are real slow frames.
		if (!(raw > 0) || window.KMO_FORCE_GL) { return; } // KMO_FORCE_GL: developer override, no guard
		raw = Math.min(raw, 1);
		this.frameAvg += (raw - this.frameAvg) * 0.2;
		this.veryslow = raw > 0.1 ? (this.veryslow || 0) + 1 : 0;
		this.frames++;
		if (this.veryslow < 3 && this.frames < 30) { return; }
		this.frames = 0;
		this.veryslow = 0;
		if (this.frameAvg < 1 / 24) { return; } // 30 fps (e.g. phone low-power mode) is fine
		const pr = this.renderer.getPixelRatio();
		if (pr > 0.55) {
			this.renderer.setPixelRatio(Math.max(0.5, pr * 0.7));
			this.resize();
			return;
		}
		if (++this.slowStrikes >= 2) { this.fail(); }
	}

	render() {
		if (this.dead || !this.scene) { return; }
		if (this.update) { this.update(); }
		this.renderer.render(this.scene, this.camera);
	}

	fail() {
		const root = this.opts.root || this.host;
		root.classList.add('kmo-gl-off');
		root.classList.remove('kmo-gl-live');
		this.destroy();
	}

	destroy() {
		if (this.dead) { return; }
		this.dead = true;
		this.running = false;
		if (this.io) { this.io.disconnect(); }
		if (this.ro) { this.ro.disconnect(); }
		document.removeEventListener('visibilitychange', this.onVis);
		this.canvas.removeEventListener('webglcontextlost', this.onLost);
		this.disposables.forEach((d) => { try { d.dispose(); } catch (e) { /* ignore */ } });
		try { this.renderer.dispose(); this.renderer.forceContextLoss(); } catch (e) { /* ignore */ }
		this.canvas.remove();
	}
}

/* ------------------------------------------------------------------ */
/* Backgrounds                                                         */
/* ------------------------------------------------------------------ */

const BG_FRAG = {
	gradient: /* glsl */ `
	void main(){
		vec2 uv = vUv; vec2 p = uv; p.x *= uRes.x / uRes.y;
		float t = uTime * 0.06;
		vec2 m = (uMouse - 0.5) * 0.35 * uMouseOn;
		float n1 = snoise(vec3(p * 0.9 * uScale + m, t));
		float n2 = snoise(vec3(p * 1.3 * uScale - m + 7.3, t * 1.3));
		float n3 = snoise(vec3(p * 0.6 * uScale + 3.1, t * 0.7 + uScroll));
		vec3 col = mix(uC1, uC2, smoothstep(-0.6, 0.6, n1));
		col = mix(col, uC3, smoothstep(-0.2, 0.8, n2) * 0.85 * uIntensity);
		col = mix(col, uBg, smoothstep(0.35, 1.0, n3) * 0.55);
		col += (hash12(gl_FragCoord.xy + fract(uTime) * 100.0) - 0.5) * uGrain;
		gl_FragColor = vec4(col, 1.0);
	}`,
	aurora: /* glsl */ `
	void main(){
		vec2 uv = vUv; vec2 p = uv; p.x *= uRes.x / uRes.y;
		float t = uTime * 0.12;
		vec3 col = uBg;
		for (int i = 0; i < 3; i++) {
			float fi = float(i);
			float y0 = 0.62 - fi * 0.13 + 0.16 * snoise(vec3(p.x * 0.7 * uScale + fi * 3.1, t * 0.6, fi)) + (uMouse.y - 0.5) * 0.15 * uMouseOn;
			float d = uv.y - y0;
			float band = exp(-d * d * (90.0 / (0.6 + uIntensity))) * (0.55 + 0.45 * snoise(vec3(p.x * 5.0 * uScale + fi, t * 2.0 + uScroll, fi * 2.0)));
			float rays = smoothstep(0.0, 1.0, (uv.y - y0 + 0.25) * 2.0) * smoothstep(0.6, 0.0, uv.y - y0);
			vec3 c = fi < 0.5 ? uC1 : (fi < 1.5 ? uC2 : uC3);
			col += c * band * 0.9 * uIntensity + c * rays * band * 0.25;
		}
		col += (hash12(gl_FragCoord.xy + fract(uTime) * 100.0) - 0.5) * uGrain;
		gl_FragColor = vec4(col, 1.0);
	}`,
	liquid: /* glsl */ `
	float fbm(vec3 p){ float a = 0.5, s = 0.0; for (int i = 0; i < 4; i++) { s += a * snoise(p); p *= 2.02; a *= 0.5; } return s; }
	void main(){
		vec2 uv = vUv; vec2 p = uv * 1.6 * uScale; p.x *= uRes.x / uRes.y;
		float t = uTime * 0.08;
		vec2 m = (uMouse - 0.5) * uMouseOn;
		vec2 q = vec2(fbm(vec3(p + m * 0.4, t)), fbm(vec3(p + vec2(5.2, 1.3), t)));
		vec2 r = vec2(fbm(vec3(p + 3.0 * q + vec2(1.7, 9.2) + uScroll, t * 1.4)), fbm(vec3(p + 3.0 * q + vec2(8.3, 2.8), t * 1.2)));
		float f = fbm(vec3(p + 3.0 * r, t));
		vec3 col = mix(uBg, uC1, clamp(f * f * 3.5 + 0.2, 0.0, 1.0));
		col = mix(col, uC2, clamp(length(q) * 0.9, 0.0, 1.0));
		col = mix(col, uC3, clamp(abs(r.x) * uIntensity, 0.0, 1.0));
		col *= 0.75 + 0.5 * f * uIntensity;
		col += (hash12(gl_FragCoord.xy + fract(uTime) * 100.0) - 0.5) * uGrain;
		gl_FragColor = vec4(col, 1.0);
	}`,
	blobs: /* glsl */ `
	void main(){
		vec2 uv = vUv; float a = uRes.x / uRes.y; vec2 p = vec2(uv.x * a, uv.y);
		float t = uTime * 0.35;
		float field = 0.0; vec3 acc = vec3(0.0);
		for (int i = 0; i < 6; i++) {
			float fi = float(i);
			vec2 c = vec2(0.5 * a, 0.5) + vec2(0.36 * a, 0.32) * vec2(sin(t * (0.6 + fi * 0.13) + fi * 1.7), cos(t * (0.5 + fi * 0.11) + fi * 2.3));
			float r = 0.17 * uScale * (0.85 + 0.25 * sin(fi * 1.3 + t));
			vec2 d = p - c;
			float v = r * r / max(dot(d, d), 1e-4);
			field += v;
			float k = mod(fi, 3.0);
			acc += (k < 0.5 ? uC1 : (k < 1.5 ? uC2 : uC3)) * v;
		}
		vec2 mp = vec2(uMouse.x * a, uMouse.y);
		vec2 dm = p - mp; float vm = 0.012 * uScale * uMouseOn / max(dot(dm, dm), 1e-4);
		field += vm; acc += uC1 * vm;
		vec3 blobCol = acc / max(field, 1e-4);
		float edge = smoothstep(0.85, 1.15, field);
		float glow = smoothstep(0.2, 0.9, field) * 0.35 * uIntensity;
		vec3 col = mix(uBg, blobCol, edge) + blobCol * glow * (1.0 - edge);
		col += (hash12(gl_FragCoord.xy + fract(uTime) * 100.0) - 0.5) * uGrain;
		gl_FragColor = vec4(col, 1.0);
	}`
};

function bgUniforms(o) {
	return {
		uTime: { value: 0 },
		uRes: { value: new Vector2(1, 1) },
		uMouse: { value: new Vector2(0.5, 0.5) },
		uMouseOn: { value: o.mouse ? 1 : 0 },
		uScroll: { value: 0 },
		uC1: { value: color(o.colors[0], '#F2636F') },
		uC2: { value: color(o.colors[1], '#6C5CE7') },
		uC3: { value: color(o.colors[2], '#00C2FF') },
		uBg: { value: color(o.bg, '#0B0B1A') },
		uScale: { value: o.scale || 1 },
		uIntensity: { value: o.intensity === undefined ? 1 : o.intensity },
		uGrain: { value: o.grain === undefined ? 0.05 : o.grain },
		uOpacity: { value: 1 },
		uSize: { value: o.size || 1 },
		uPixelRatio: { value: 1 }
	};
}

const BG_HEAD = /* glsl */ `
precision highp float;
varying vec2 vUv;
uniform float uTime, uMouseOn, uScroll, uScale, uIntensity, uGrain;
uniform vec2 uRes, uMouse;
uniform vec3 uC1, uC2, uC3, uBg;
`;

function shaderBackground(host, o) {
	const st = new Stage(host, o, false, 'shader');
	const u = bgUniforms(o);
	const mat = new ShaderMaterial({ vertexShader: QUAD_VERT, fragmentShader: BG_HEAD + NOISE + BG_FRAG[o.type], uniforms: u, depthTest: false, depthWrite: false });
	const geo = new PlaneGeometry(2, 2);
	st.scene = new Scene();
	st.scene.add(new Mesh(geo, mat));
	st.camera = new OrthographicCamera(-1, 1, 1, -1, 0, 1);
	st.disposables.push(mat, geo);
	let scroll = 0;
	st.onResize = (w, h) => { u.uRes.value.set(w, h); };
	st.update = () => {
		u.uTime.value = st.time;
		u.uMouse.value.copy(st.mouse);
		if (o.scroll) {
			scroll += st.vel * 0.02;
			u.uScroll.value = scroll + (window.pageYOffset || 0) * 0.0004;
		}
	};
	st.resize();
	return st;
}

/* Particle field (3D). */
function particles(host, o) {
	const st = new Stage(host, o, false, 'points');
	const count = Math.round(Math.min(12000, 2600 * (o.density || 1) * (o.mobile ? 0.5 : 1)));
	const pos = new Float32Array(count * 3);
	const rnd = new Float32Array(count * 3);
	for (let i = 0; i < count; i++) {
		pos[i * 3] = (Math.random() - 0.5) * 6 * (o.scale || 1);
		pos[i * 3 + 1] = (Math.random() - 0.5) * 4 * (o.scale || 1);
		pos[i * 3 + 2] = (Math.random() - 0.5) * 3;
		rnd[i * 3] = Math.random();
		rnd[i * 3 + 1] = Math.random();
		rnd[i * 3 + 2] = Math.random();
	}
	const geo = new BufferGeometry();
	geo.setAttribute('position', new Float32BufferAttribute(pos, 3));
	geo.setAttribute('aRand', new Float32BufferAttribute(rnd, 3));
	const u = bgUniforms(o);
	u.uPixelRatio.value = st.renderer.getPixelRatio();
	const mat = new ShaderMaterial({
		uniforms: u,
		transparent: true,
		depthWrite: false,
		blending: AdditiveBlending,
		vertexShader: /* glsl */ `
			uniform float uTime, uSize, uPixelRatio, uMouseOn, uScroll, uIntensity;
			uniform vec2 uMouse, uRes;
			attribute vec3 aRand;
			varying float vMix; varying float vAlpha;
			void main(){
				vec3 p = position;
				float t = uTime * 0.25;
				p.x += sin(t + aRand.x * 6.2831) * 0.12 * uIntensity;
				p.y += cos(t * 1.3 + aRand.y * 6.2831) * 0.12 * uIntensity + uScroll * (0.2 + aRand.z * 0.6);
				p.z += sin(t * 0.7 + aRand.z * 6.2831) * 0.15;
				p.y = mod(p.y + 2.0, 4.0) - 2.0;
				vec4 mv = modelViewMatrix * vec4(p, 1.0);
				vec4 pr = projectionMatrix * mv;
				vec2 ndc = pr.xy / pr.w;
				vec2 dm = ndc - (uMouse * 2.0 - 1.0);
				dm.x *= uRes.x / uRes.y;
				float dist = length(dm);
				float push = smoothstep(0.45, 0.0, dist) * 0.35 * uMouseOn;
				mv.xy += normalize(dm + 1e-5) * push;
				gl_Position = projectionMatrix * mv;
				gl_PointSize = uSize * uPixelRatio * (26.0 / -mv.z) * (0.4 + aRand.x);
				vMix = aRand.y; vAlpha = 0.35 + 0.65 * aRand.z;
			}`,
		fragmentShader: /* glsl */ `
			precision highp float;
			uniform vec3 uC1, uC2, uC3;
			varying float vMix; varying float vAlpha;
			void main(){
				float d = length(gl_PointCoord - 0.5);
				float a = smoothstep(0.5, 0.0, d);
				vec3 c = vMix < 0.4 ? uC1 : (vMix < 0.75 ? uC2 : uC3);
				gl_FragColor = vec4(c * a * vAlpha, a * vAlpha);
			}`
	});
	const pts = new Points(geo, mat);
	const group = new Group();
	group.add(pts);
	st.scene = new Scene();
	st.scene.add(group);
	st.camera = new PerspectiveCamera(60, 1, 0.1, 50);
	st.camera.position.z = 3.2;
	const bg = color(o.bg, '#0B0B1A');
	st.renderer.setClearColor((Math.round(bg.x * 255) << 16) | (Math.round(bg.y * 255) << 8) | Math.round(bg.z * 255), 1);
	st.disposables.push(geo, mat);
	let scroll = 0;
	st.onResize = (w, h) => {
		st.camera.aspect = w / h;
		st.camera.updateProjectionMatrix();
		u.uRes.value.set(w, h);
	};
	st.update = () => {
		u.uTime.value = st.time;
		u.uMouse.value.copy(st.mouse);
		if (o.scroll) { scroll += st.vel * 0.03; u.uScroll.value = scroll; }
		group.rotation.y = st.time * 0.03 + (o.mouse ? (st.mouse.x - 0.5) * 0.25 : 0);
		group.rotation.x = o.mouse ? -(st.mouse.y - 0.5) * 0.15 : 0;
	};
	st.resize();
	return st;
}

/* Wave grid (3D). */
function waves(host, o) {
	const st = new Stage(host, o, false, 'points');
	const d = o.density || 1;
	const cols = Math.round(Math.min(260, 150 * d * (o.mobile ? 0.6 : 1)));
	const rows = Math.round(Math.min(140, 70 * d * (o.mobile ? 0.6 : 1)));
	const pos = new Float32Array(cols * rows * 3);
	const uvs = new Float32Array(cols * rows * 2);
	let k = 0;
	for (let y = 0; y < rows; y++) {
		for (let x = 0; x < cols; x++) {
			const u = x / (cols - 1);
			const v = y / (rows - 1);
			pos[k * 3] = (u - 0.5) * 8;
			pos[k * 3 + 1] = 0;
			pos[k * 3 + 2] = (v - 0.5) * 5;
			uvs[k * 2] = u;
			uvs[k * 2 + 1] = v;
			k++;
		}
	}
	const geo = new BufferGeometry();
	geo.setAttribute('position', new Float32BufferAttribute(pos, 3));
	geo.setAttribute('aUv', new Float32BufferAttribute(uvs, 2));
	const u = bgUniforms(o);
	u.uPixelRatio.value = st.renderer.getPixelRatio();
	const mat = new ShaderMaterial({
		uniforms: u,
		transparent: true,
		depthWrite: false,
		blending: NormalBlending,
		vertexShader: BG_HEAD.replace('varying vec2 vUv;', '') + NOISE + /* glsl */ `
			uniform float uSize, uPixelRatio;
			attribute vec2 aUv;
			varying float vH; varying float vFade;
			void main(){
				vec3 p = position;
				float t = uTime * 0.5;
				float s = uScale;
				float h = sin(p.x * 0.9 * s + t) * 0.18 + cos(p.z * 1.4 * s + t * 0.8) * 0.16 + snoise(vec3(p.x * 0.35 * s, p.z * 0.35 * s, t * 0.25 + uScroll)) * 0.35;
				vec2 dm = aUv - vec2(uMouse.x, 1.0 - uMouse.y);
				float dist = length(dm * vec2(1.6, 1.0));
				h += sin(dist * 26.0 - uTime * 5.0) * exp(-dist * 5.0) * 0.22 * uMouseOn;
				p.y = h * uIntensity;
				vH = clamp(h * 1.4 + 0.5, 0.0, 1.0);
				vec4 mv = modelViewMatrix * vec4(p, 1.0);
				gl_Position = projectionMatrix * mv;
				gl_PointSize = uSize * uPixelRatio * (9.0 / -mv.z);
				vFade = smoothstep(9.0, 2.0, -mv.z);
			}`,
		fragmentShader: /* glsl */ `
			precision highp float;
			uniform vec3 uC1, uC2, uC3;
			varying float vH; varying float vFade;
			void main(){
				float d = length(gl_PointCoord - 0.5);
				float a = smoothstep(0.5, 0.1, d) * vFade;
				vec3 c = mix(uC2, uC1, vH);
				c = mix(c, uC3, smoothstep(0.75, 1.0, vH));
				gl_FragColor = vec4(c * a, a);
			}`
	});
	const pts = new Points(geo, mat);
	st.scene = new Scene();
	st.scene.add(pts);
	st.camera = new PerspectiveCamera(55, 1, 0.1, 50);
	st.camera.position.set(0, 1.5, 3.4);
	st.camera.lookAt(0, -0.2, 0);
	const bg = color(o.bg, '#0B0B1A');
	st.renderer.setClearColor((Math.round(bg.x * 255) << 16) | (Math.round(bg.y * 255) << 8) | Math.round(bg.z * 255), 1);
	st.disposables.push(geo, mat);
	let scroll = 0;
	st.onResize = (w, h) => {
		st.camera.aspect = w / h;
		st.camera.updateProjectionMatrix();
	};
	st.update = () => {
		u.uTime.value = st.time;
		u.uMouse.value.copy(st.mouse);
		if (o.scroll) { scroll += st.vel * 0.02; u.uScroll.value = scroll; }
		st.camera.position.x = o.mouse ? (st.mouse.x - 0.5) * 0.6 : 0;
		st.camera.lookAt(0, -0.2, 0);
	};
	st.resize();
	return st;
}

function background(host, o) {
	try {
		if (o.type === 'particles') { return particles(host, o); }
		if (o.type === 'waves') { return waves(host, o); }
		if (BG_FRAG[o.type]) { return shaderBackground(host, o); }
	} catch (e) {
		if (window.console && !/Software WebGL/.test(e && e.message)) { console.warn('[Kinetic Motion] WebGL background failed', e); }
	}
	return null;
}

/* ------------------------------------------------------------------ */
/* Texture planes: WebGL Image & WebGL Text                            */
/* ------------------------------------------------------------------ */

const PLANE_FRAG = /* glsl */ `
precision highp float;
varying vec2 vUv;
uniform sampler2D uTex, uTex2;
uniform vec2 uRes, uImg, uImg2, uMouse;
uniform float uTime, uHover, uIntensity, uReveal, uVel, uIdle, uHas2, uPad;
uniform int uEffect, uRevealType;
${NOISE}
vec2 cover(vec2 uv, vec2 res, vec2 img){
	float rs = res.x / res.y; float ri = img.x / img.y;
	vec2 s = rs > ri ? vec2(1.0, ri / rs) : vec2(rs / ri, 1.0);
	return (uv - 0.5) * s + 0.5;
}
void main(){
	vec2 uv = vUv;
	float aspect = uRes.x / uRes.y;
	vec2 m = uMouse;
	vec2 dv = uv - m; dv.x *= aspect;
	float d = length(dv);
	float I = uIntensity;
	float t = uTime;

	// Idle wave & scroll bend.
	uv.y += sin(uv.x * 8.0 + t) * 0.003 * uIdle * I;
	uv.x += cos(uv.y * 6.0 + t * 0.8) * 0.003 * uIdle * I;
	uv.y += sin(uv.x * 3.14159) * uVel * 0.03;

	vec2 off = vec2(0.0);
	if (uEffect == 0) { // ripple
		float w = sin(d * 38.0 - t * 6.0) * exp(-d * 5.5) * 0.022 * I * uHover;
		uv += normalize(dv + 1e-5) * w * vec2(1.0 / aspect, 1.0);
	} else if (uEffect == 1) { // liquid
		float f = smoothstep(0.55, 0.0, d) * uHover;
		uv += vec2(snoise(vec3(uv * 3.0, t * 0.5)), snoise(vec3(uv * 3.0 + 5.0, t * 0.5))) * 0.04 * I * f;
	} else if (uEffect == 2) { // rgb
		off = (uv - m) * 0.025 * I * uHover + vec2(uVel * 0.02, 0.0);
	} else if (uEffect == 3) { // bulge
		float k = 1.0 - smoothstep(0.0, 0.4, d) * 0.35 * I * uHover;
		uv = m + (uv - m) * k;
	} else if (uEffect == 4) { // pixel
		float block = 1.0 + uHover * 28.0 * I;
		uv = (floor(uv * uRes / block) + 0.5) * block / uRes;
	} else if (uEffect == 7) { // wave (text)
		uv.x += sin(uv.y * 10.0 + t * 2.0) * 0.008 * I;
		uv.y += sin(uv.x * 6.0 + t * 1.6) * 0.018 * I;
		float w2 = sin(d * 30.0 - t * 5.0) * exp(-d * 6.0) * 0.015 * I * uHover;
		uv += normalize(dv + 1e-5) * w2 * vec2(1.0 / aspect, 1.0);
	} else if (uEffect == 8) { // glitch (text)
		float g = step(0.9, hash12(vec2(floor(t * 6.0), 1.0))) + uHover * 0.6;
		float band = floor(uv.y * 18.0);
		uv.x += (hash12(vec2(band, floor(t * 12.0))) - 0.5) * 0.06 * I * g;
		off = vec2(0.012 * I * (g + 0.2), 0.0);
	} else if (uEffect == 9) { // heat
		uv += vec2(snoise(vec3(uv * vec2(4.0, 9.0), t * 0.9)), snoise(vec3(uv * vec2(5.0, 8.0) + 3.0, t * 0.8))) * 0.007 * I * (1.0 + uHover);
	} else if (uEffect == 10) { // stretch
		uv.y = (uv.y - 0.5) / (1.0 + abs(uVel) * 0.5 * I) + 0.5;
		uv.x += (uv.y - 0.5) * uVel * 0.25 * I;
		off = vec2(0.0, uVel * 0.01 * I);
	}

	// Reveal.
	float mask = 1.0;
	float r = clamp(uReveal, 0.0, 1.0);
	if (uRevealType == 1) {
		float n = snoise(vec3(vUv * 3.5, 0.5)) * 0.5 + 0.5;
		mask = smoothstep(n - 0.08, n + 0.08, r * 1.2 - 0.1);
	} else if (uRevealType == 2) {
		float edge = vUv.y + snoise(vec3(vUv.x * 3.0, 0.0, t * 0.3)) * 0.08;
		mask = smoothstep(edge - 0.06, edge, r * 1.25 - 0.1);
		uv.y += (1.0 - mask) * 0.06;
	} else if (uRevealType == 3) {
		float block = mix(70.0, 1.0, smoothstep(0.0, 0.9, r));
		uv = (floor(uv * uRes / block) + 0.5) * block / uRes;
		mask = smoothstep(0.0, 0.25, r);
	} else if (uRevealType == 4) {
		uv = (uv - 0.5) * mix(0.6, 1.0, r) + 0.5;
		uv += vec2(snoise(vec3(uv * 2.0, t)), snoise(vec3(uv * 2.0 + 4.0, t))) * 0.05 * (1.0 - r);
		mask = smoothstep(0.0, 0.6, r);
	}

	vec2 tuv = cover(uv, uRes, uImg);
	vec4 col;
	if (uEffect == 5 && uHas2 > 0.5) { // swap
		float n = snoise(vec3(vUv * 4.0, t * 0.2)) * 0.5 + 0.5;
		float p = smoothstep(n - 0.25, n + 0.25, uHover * 1.5 - 0.25);
		vec2 a = cover(uv + vec2(p * 0.12 * I * n), uRes, uImg);
		vec2 b = cover(uv - vec2((1.0 - p) * 0.12 * I * n), uRes, uImg2);
		col = mix(texture2D(uTex, a), texture2D(uTex2, b), p);
	} else if (length(off) > 0.0) {
		vec4 cr = texture2D(uTex, cover(uv + off, uRes, uImg));
		vec4 cg = texture2D(uTex, tuv);
		vec4 cb = texture2D(uTex, cover(uv - off, uRes, uImg));
		col = vec4(cr.r, cg.g, cb.b, max(cg.a, max(cr.a, cb.a)));
	} else {
		col = texture2D(uTex, tuv);
	}
	// Outside the texture → transparent (images use clamp, text has padding).
	if (tuv.x < 0.0 || tuv.x > 1.0 || tuv.y < 0.0 || tuv.y > 1.0) { col.a *= step(0.5, uPad); }
	gl_FragColor = vec4(col.rgb * mask, col.a * mask);
}
`;

const IMAGE_FX = { ripple: 0, liquid: 1, rgb: 2, bulge: 3, pixel: 4, swap: 5, none: 6 };
const TEXT_FX = { wave: 7, ripple: 0, rgb: 8, heat: 9, stretch: 10 };
const REVEAL = { none: 0, noise: 1, wipe: 2, pixel: 3, zoom: 4 };

function makeTexture(src) {
	const t = new Texture(src);
	t.minFilter = LinearFilter;
	t.magFilter = LinearFilter;
	t.wrapS = ClampToEdgeWrapping;
	t.wrapT = ClampToEdgeWrapping;
	t.generateMipmaps = false;
	t.needsUpdate = true;
	return t;
}

function loadImage(url) {
	return new Promise((resolve, reject) => {
		const img = new Image();
		img.decoding = 'async';
		try {
			if (new URL(url, location.href).origin !== location.origin) { img.crossOrigin = 'anonymous'; }
		} catch (e) { /* relative url */ }
		img.onload = () => resolve(img);
		img.onerror = reject;
		img.src = url;
	});
}

function plane(host, root, o, effect, revealType) {
	const st = new Stage(host, Object.assign({}, o, { root: root, quality: 'high' }), true, 'plane');
	const u = {
		uTex: { value: null },
		uTex2: { value: null },
		uRes: { value: new Vector2(1, 1) },
		uImg: { value: new Vector2(1, 1) },
		uImg2: { value: new Vector2(1, 1) },
		uMouse: { value: new Vector2(0.5, 0.5) },
		uTime: { value: 0 },
		uHover: { value: 0 },
		uIntensity: { value: o.intensity || 1 },
		uReveal: { value: 1 },
		uVel: { value: 0 },
		uIdle: { value: o.idle ? 1 : 0 },
		uHas2: { value: 0 },
		uPad: { value: 0 },
		uEffect: { value: effect },
		uRevealType: { value: revealType }
	};
	const mat = new ShaderMaterial({ vertexShader: QUAD_VERT, fragmentShader: PLANE_FRAG, uniforms: u, transparent: true, depthTest: false, depthWrite: false });
	const geo = new PlaneGeometry(2, 2);
	st.scene = new Scene();
	st.scene.add(new Mesh(geo, mat));
	st.camera = new OrthographicCamera(-1, 1, 1, -1, 0, 1);
	st.disposables.push(mat, geo);
	st.u = u;
	st.onResize = (w, h) => { u.uRes.value.set(w, h); };
	st.update = () => {
		u.uTime.value = st.time;
		u.uMouse.value.copy(st.mouse);
		u.uHover.value = st.hover;
		u.uVel.value = o.scroll === false ? 0 : st.vel;
	};
	st.reveal = (p) => { u.uReveal.value = p; if (!st.running) { st.render(); } };
	return st;
}

function image(root, o) {
	if (!o.src) { return null; }
	let st;
	try {
		st = plane(root, root, o, IMAGE_FX[o.effect] === undefined ? 0 : IMAGE_FX[o.effect], REVEAL[o.reveal] || 0);
	} catch (e) {
		root.classList.add('kmo-gl-off');
		return null;
	}
	const loads = [loadImage(o.src)];
	if (o.effect === 'swap' && o.src2) { loads.push(loadImage(o.src2)); }
	Promise.all(loads).then((imgs) => {
		if (st.dead) { return; }
		const t1 = makeTexture(imgs[0]);
		st.u.uTex.value = t1;
		st.u.uImg.value.set(imgs[0].naturalWidth || 1, imgs[0].naturalHeight || 1);
		st.disposables.push(t1);
		if (imgs[1]) {
			const t2 = makeTexture(imgs[1]);
			st.u.uTex2.value = t2;
			st.u.uImg2.value.set(imgs[1].naturalWidth || 1, imgs[1].naturalHeight || 1);
			st.u.uHas2.value = 1;
			st.disposables.push(t2);
		} else {
			st.u.uTex2.value = t1;
		}
		st.resize();
		st.render();
		root.classList.add('kmo-gl-live');
	}).catch(() => st.fail());

	return {
		reveal: (p) => st.reveal(p),
		destroy: () => {
			root.classList.remove('kmo-gl-live');
			st.destroy();
		}
	};
}

/* Draw the real, browser-laid-out text into a canvas, word by word. */
function drawText(src, pad) {
	const cs = getComputedStyle(src);
	const box = src.getBoundingClientRect();
	const dpr = Math.min(window.devicePixelRatio || 1, 2);
	const W = Math.ceil(box.width + pad * 2);
	const H = Math.ceil(box.height + pad * 2);
	const c = document.createElement('canvas');
	c.width = Math.max(1, Math.round(W * dpr));
	c.height = Math.max(1, Math.round(H * dpr));
	const ctx = c.getContext('2d');
	ctx.scale(dpr, dpr);
	ctx.font = cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
	ctx.fillStyle = src.dataset.kmoColor || cs.color;
	ctx.textBaseline = 'alphabetic';
	if ('letterSpacing' in ctx && cs.letterSpacing && cs.letterSpacing !== 'normal') { ctx.letterSpacing = cs.letterSpacing; }
	const metrics = ctx.measureText('Hg');
	const ascent = metrics.fontBoundingBoxAscent || metrics.actualBoundingBoxAscent || parseFloat(cs.fontSize) * 0.8;
	const tt = cs.textTransform;
	const tf = (s) => (tt === 'uppercase' ? s.toUpperCase() : tt === 'lowercase' ? s.toLowerCase() : tt === 'capitalize' ? s.replace(/\b\w/g, (ch) => ch.toUpperCase()) : s);
	const walker = document.createTreeWalker(src, NodeFilter.SHOW_TEXT);
	const range = document.createRange();
	let node;
	while ((node = walker.nextNode())) {
		const text = node.nodeValue;
		const re = /\S+/g;
		let m;
		while ((m = re.exec(text))) {
			range.setStart(node, m.index);
			range.setEnd(node, m.index + m[0].length);
			const rects = range.getClientRects();
			if (!rects.length) { continue; }
			// A word can wrap across lines only when it is hyphenated; draw it on its first line.
			const r = rects[0];
			ctx.fillText(tf(m[0]), r.left - box.left + pad, r.top - box.top + pad + ascent);
		}
	}
	return { canvas: c, W, H };
}

function text(root, o) {
	const src = root.querySelector('.kmo-gltext-src');
	if (!src) { return null; }
	const host = document.createElement('div');
	host.className = 'kmo-gltext-stage';
	host.setAttribute('aria-hidden', 'true');
	root.appendChild(host);

	let st;
	try {
		st = plane(host, root, Object.assign({}, o, { scroll: true }), TEXT_FX[o.effect] === undefined ? 7 : TEXT_FX[o.effect], o.reveal ? REVEAL.noise : 0);
	} catch (e) {
		host.remove();
		root.classList.add('kmo-gl-off');
		return null;
	}
	st.u.uPad.value = 1;

	let tex = null;
	let pad = 0;
	const redraw = () => {
		if (st.dead) { return; }
		if (!src.dataset.kmoColor) { src.dataset.kmoColor = getComputedStyle(src).color; }
		const fs = parseFloat(getComputedStyle(src).fontSize) || 40;
		pad = Math.round(Math.min(120, fs * 0.45));
		// Align the stage with the text box (plus padding so distortions are not clipped).
		host.style.left = (src.offsetLeft - pad) + 'px';
		host.style.top = (src.offsetTop - pad) + 'px';
		host.style.width = (src.offsetWidth + pad * 2) + 'px';
		host.style.height = (src.offsetHeight + pad * 2) + 'px';
		const out = drawText(src, pad);
		if (tex) { tex.dispose(); }
		tex = new CanvasTexture(out.canvas);
		tex.minFilter = LinearFilter;
		tex.generateMipmaps = false;
		st.u.uTex.value = tex;
		st.u.uTex2.value = tex;
		st.u.uImg.value.set(out.W, out.H);
		st.resize();
		st.render();
		root.classList.add('kmo-gl-live');
	};
	const ready = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
	ready.then(() => requestAnimationFrame(redraw));

	let t = 0;
	const ro = 'ResizeObserver' in window ? new ResizeObserver(() => { clearTimeout(t); t = setTimeout(redraw, 120); }) : null;
	if (ro) { ro.observe(src); }

	return {
		reveal: (p) => st.reveal(p),
		destroy: () => {
			if (ro) { ro.disconnect(); }
			clearTimeout(t);
			if (tex) { tex.dispose(); }
			root.classList.remove('kmo-gl-live');
			delete src.dataset.kmoColor;
			st.destroy();
			host.remove();
		}
	};
}

window.KMO_WEBGL = { background, image, text };
