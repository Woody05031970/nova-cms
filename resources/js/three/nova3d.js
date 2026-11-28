/**
 * 3D-Hintergründe mit three.js (Modul „3D-Effekte“).
 *
 * Jedes Element mit data-nova-3d="<szene>" bekommt eine eigene, durchsichtige WebGL-Fläche.
 * Die Hintergrundfarbe kommt weiter aus CSS, three.js zeichnet nur die Bewegung darüber.
 * Rücksicht auf Besucher: ruht außerhalb des Bildschirms, steht bei „Bewegung reduzieren“ still,
 * begrenzt die Pixeldichte und fällt ohne WebGL einfach weg (die Farbfläche bleibt).
 * three.js liegt auf dem eigenen Server, es werden keine fremden Dienste geladen.
 */
import {
    AdditiveBlending, BufferAttribute, BufferGeometry, Color, DirectionalLight, Group, HemisphereLight, IcosahedronGeometry,
    LineBasicMaterial, LineSegments, Mesh, MeshStandardMaterial, OctahedronGeometry, PerspectiveCamera, PointLight, Points,
    PointsMaterial, Scene, TorusGeometry, TorusKnotGeometry, WebGLRenderer, CanvasTexture, SRGBColorSpace,
} from 'three';

const reduce = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const instances = new Map();

/** CSS-Farbe (auch var(--brand)) in eine three.js-Farbe umrechnen */
function cssColor(expr, host, fallback) {
    if (!expr) return new Color(fallback);
    const probe = document.createElement('span');
    probe.style.color = expr;
    probe.style.display = 'none';
    host.appendChild(probe);
    const css = getComputedStyle(probe).color;
    probe.remove();
    // Über eine 1x1-Zeichenfläche in RGB umrechnen: versteht auch oklch() und color-mix() aus dem Design
    try {
        const g = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
        g.fillStyle = fallback;
        g.fillStyle = css || fallback;
        g.fillRect(0, 0, 1, 1);
        const [r, gr, b] = g.getImageData(0, 0, 1, 1).data;
        return new Color().setRGB(r / 255, gr / 255, b / 255, SRGBColorSpace);
    } catch {
        return new Color(fallback);
    }
}

/** Runder, weicher Punkt statt eckiger Pixel */
let dotTexture = null;
function dot() {
    if (dotTexture) return dotTexture;
    const c = document.createElement('canvas');
    c.width = c.height = 64;
    const g = c.getContext('2d');
    const grad = g.createRadialGradient(32, 32, 0, 32, 32, 32);
    grad.addColorStop(0, 'rgba(255,255,255,1)');
    grad.addColorStop(0.45, 'rgba(255,255,255,0.85)');
    grad.addColorStop(1, 'rgba(255,255,255,0)');
    g.fillStyle = grad;
    g.fillRect(0, 0, 64, 64);
    dotTexture = new CanvasTexture(c);
    return dotTexture;
}

// --- Szenen -------------------------------------------------------------------
// Jede Szene bekommt { scene, camera, c1, c2 } und liefert update(t, pointer, aspect).

const SCENES = {
    /** Wellenfeld aus Lichtpunkten, das sich sanft hebt und senkt */
    wellen({ scene, camera, c1, c2 }) {
        const nx = 90;
        const nz = 60;
        const pos = new Float32Array(nx * nz * 3);
        const col = new Float32Array(nx * nz * 3);
        const mix = new Color();
        for (let i = 0; i < nx; i++) {
            for (let j = 0; j < nz; j++) {
                const k = (i * nz + j) * 3;
                pos[k] = (i - nx / 2) * 0.55;
                pos[k + 2] = (j - nz / 2) * 0.55;
                mix.copy(c1).lerp(c2, i / nx);
                col[k] = mix.r; col[k + 1] = mix.g; col[k + 2] = mix.b;
            }
        }
        const geo = new BufferGeometry();
        geo.setAttribute('position', new BufferAttribute(pos, 3));
        geo.setAttribute('color', new BufferAttribute(col, 3));
        const pts = new Points(geo, new PointsMaterial({ size: 0.32, map: dot(), vertexColors: true, transparent: true, depthWrite: false, blending: AdditiveBlending, opacity: 0.95 }));
        scene.add(pts);
        camera.position.set(0, 7, 18);
        camera.lookAt(0, 0, 0);
        return (t, p) => {
            const a = geo.attributes.position.array;
            for (let i = 0; i < nx; i++) {
                for (let j = 0; j < nz; j++) {
                    const k = (i * nz + j) * 3;
                    a[k + 1] = Math.sin(i * 0.18 + t * 0.9) * 0.9 + Math.cos(j * 0.22 + t * 0.7) * 0.7;
                }
            }
            geo.attributes.position.needsUpdate = true;
            pts.rotation.y = p.x * 0.15;
            camera.position.y = 7 - p.y * 1.5;
            camera.lookAt(0, 0, 0);
        };
    },

    /** Netz aus Punkten, die treiben und sich verbinden, wenn sie nah sind */
    netz({ scene, camera, c1, c2 }) {
        const n = 110;
        const box = { x: 22, y: 12, z: 8 };
        const pos = new Float32Array(n * 3);
        const vel = [];
        for (let i = 0; i < n; i++) {
            pos[i * 3] = (Math.random() - 0.5) * box.x;
            pos[i * 3 + 1] = (Math.random() - 0.5) * box.y;
            pos[i * 3 + 2] = (Math.random() - 0.5) * box.z;
            vel.push([(Math.random() - 0.5) * 0.02, (Math.random() - 0.5) * 0.02, (Math.random() - 0.5) * 0.01]);
        }
        const geo = new BufferGeometry();
        geo.setAttribute('position', new BufferAttribute(pos, 3));
        scene.add(new Points(geo, new PointsMaterial({ size: 0.38, map: dot(), color: c2, transparent: true, depthWrite: false, blending: AdditiveBlending })));
        const maxLines = n * 6;
        const lpos = new Float32Array(maxLines * 6);
        const lgeo = new BufferGeometry();
        lgeo.setAttribute('position', new BufferAttribute(lpos, 3));
        const lines = new LineSegments(lgeo, new LineBasicMaterial({ color: c2, transparent: true, opacity: 0.32, blending: AdditiveBlending, depthWrite: false }));
        scene.add(lines);
        camera.position.set(0, 0, 16);
        return (t, p, _a, still) => {
            if (!still) {
                for (let i = 0; i < n; i++) {
                    for (let d = 0; d < 3; d++) {
                        const k = i * 3 + d;
                        pos[k] += vel[i][d];
                        const lim = [box.x, box.y, box.z][d] / 2;
                        if (pos[k] > lim || pos[k] < -lim) vel[i][d] *= -1;
                    }
                }
                geo.attributes.position.needsUpdate = true;
            }
            let m = 0;
            for (let i = 0; i < n && m < maxLines; i++) {
                for (let j = i + 1; j < n && m < maxLines; j++) {
                    const dx = pos[i * 3] - pos[j * 3];
                    const dy = pos[i * 3 + 1] - pos[j * 3 + 1];
                    const dz = pos[i * 3 + 2] - pos[j * 3 + 2];
                    if (dx * dx + dy * dy + dz * dz < 9) {
                        lpos.set([pos[i * 3], pos[i * 3 + 1], pos[i * 3 + 2], pos[j * 3], pos[j * 3 + 1], pos[j * 3 + 2]], m * 6);
                        m++;
                    }
                }
            }
            lgeo.setDrawRange(0, m * 2);
            lgeo.attributes.position.needsUpdate = true;
            scene.rotation.y = p.x * 0.25;
            scene.rotation.x = p.y * 0.15;
        };
    },

    /** Kugel aus Lichtpunkten mit Umlaufbahn, steht rechts neben dem Text */
    kugel({ scene, camera, c1, c2 }) {
        const n = 1600;
        const pos = new Float32Array(n * 3);
        const golden = Math.PI * (3 - Math.sqrt(5));
        for (let i = 0; i < n; i++) {
            const y = 1 - (i / (n - 1)) * 2;
            const r = Math.sqrt(1 - y * y);
            const th = golden * i;
            pos[i * 3] = Math.cos(th) * r * 5;
            pos[i * 3 + 1] = y * 5;
            pos[i * 3 + 2] = Math.sin(th) * r * 5;
        }
        const geo = new BufferGeometry();
        geo.setAttribute('position', new BufferAttribute(pos, 3));
        const globe = new Group();
        globe.add(new Points(geo, new PointsMaterial({ size: 0.16, map: dot(), color: c2, transparent: true, depthWrite: false, blending: AdditiveBlending })));
        const ring = new Mesh(new TorusGeometry(7, 0.03, 8, 160), new MeshStandardMaterial({ color: c1.clone().lerp(new Color('#ffffff'), 0.4), emissive: c2, emissiveIntensity: 0.6 }));
        ring.rotation.x = Math.PI / 2.4;
        globe.add(ring);
        const moon = new Mesh(new IcosahedronGeometry(0.35, 2), new MeshStandardMaterial({ color: c2, emissive: c2, emissiveIntensity: 0.8 }));
        ring.add(moon);
        scene.add(globe, new HemisphereLight(0xffffff, 0x222244, 1.2));
        camera.position.set(0, 0, 18);
        return (t, p, aspect) => {
            globe.position.x = aspect > 1.2 ? 5.5 : 0;
            globe.position.y = aspect > 1.2 ? 0 : -1;
            globe.rotation.y = t * 0.15 + p.x * 0.4;
            globe.rotation.x = 0.25 + p.y * 0.2;
            moon.position.set(Math.cos(t * 0.6) * 7, Math.sin(t * 0.6) * 7, 0);
        };
    },

    /** Schwebende, glänzende Formen im Licht der Markenfarben */
    formen({ scene, camera, c1, c2 }) {
        const shapes = [];
        const geos = [new IcosahedronGeometry(1.4, 0), new TorusKnotGeometry(0.9, 0.32, 140, 18), new OctahedronGeometry(1.2, 0), new TorusGeometry(1.1, 0.38, 24, 80), new IcosahedronGeometry(0.8, 1)];
        const spots = [[-7, 2.5, -2], [6.5, 1.8, -1], [-4, -3, 0], [3.5, -2.8, 1], [8.5, -1, -4], [-9, -0.5, -5], [0.5, 3.8, -6]];
        spots.forEach((s, i) => {
            // Abwechselnd Leuchtfarbe, ein verwandter Farbton und helles Metall
            const tones = [c2, c2.clone().offsetHSL(0.08, 0, 0.12), new Color('#e2e8f0')];
            const mat = new MeshStandardMaterial({ color: tones[i % 3], metalness: 0.45, roughness: 0.28, flatShading: i % 3 === 0 });
            const m = new Mesh(geos[i % geos.length], mat);
            m.position.set(...s);
            m.userData = { x: s[0], base: s[1], speed: 0.4 + (i % 4) * 0.15, phase: i * 1.3 };
            shapes.push(m);
            scene.add(m);
        });
        const key = new PointLight(c2, 60, 40);
        key.position.set(6, 6, 8);
        const fill = new PointLight(c1.clone().lerp(new Color('#ffffff'), 0.5), 40, 40);
        fill.position.set(-8, -4, 6);
        const sun = new DirectionalLight(0xffffff, 1.2);
        sun.position.set(0, 10, 10);
        scene.add(key, fill, sun, new HemisphereLight(0xffffff, 0x111122, 0.6));
        camera.position.set(0, 0, 14);
        return (t, p, aspect) => {
            // Auf breiten Flächen weiter nach außen, damit der Text in der Mitte frei bleibt
            const spread = Math.max(1, aspect / 1.4);
            shapes.forEach((m) => {
                const u = m.userData;
                m.position.x = u.x * spread;
                m.rotation.x = t * u.speed * 0.6 + u.phase;
                m.rotation.y = t * u.speed + u.phase;
                m.position.y = u.base + Math.sin(t * u.speed + u.phase) * 0.45;
            });
            camera.position.x = p.x * 1.2;
            camera.position.y = -p.y * 0.8;
            camera.lookAt(0, 0, 0);
        };
    },
};

export const SCENE_NAMES = Object.keys(SCENES);

// --- Einbinden ----------------------------------------------------------------

function mount(el) {
    const attr = el.getAttribute('data-nova-3d');
    const name = SCENES[attr] ? attr : 'wellen';
    let renderer;
    try {
        renderer = new WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    } catch {
        return null; // kein WebGL: die Farbfläche bleibt einfach stehen
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
    renderer.setClearColor(0x000000, 0);
    renderer.domElement.setAttribute('aria-hidden', 'true');
    el.appendChild(renderer.domElement);

    const scene = new Scene();
    const camera = new PerspectiveCamera(50, 1, 0.1, 100);
    const c1 = cssColor(el.dataset.c1, el, '#0a4b5a');
    const c2 = cssColor(el.dataset.c2, el, '#19c3dc');
    const update = SCENES[name]({ scene, camera, c1, c2 });

    const pointer = { x: 0, y: 0, tx: 0, ty: 0 };
    const still = reduce();
    let visible = true;
    let aspect = 1;
    let raf = 0;
    const t0 = performance.now();

    const size = () => {
        const w = el.clientWidth || 1;
        const h = el.clientHeight || 1;
        aspect = w / h;
        renderer.setSize(w, h, false);
        camera.aspect = aspect;
        camera.updateProjectionMatrix();
    };
    const frame = () => {
        raf = 0;
        if (!el.isConnected) return destroy();
        pointer.x += (pointer.tx - pointer.x) * 0.05;
        pointer.y += (pointer.ty - pointer.y) * 0.05;
        update(still ? 2 : (performance.now() - t0) / 1000, pointer, aspect, still);
        renderer.render(scene, camera);
        if (visible && !still) raf = requestAnimationFrame(frame);
    };
    const start = () => { if (!raf) raf = requestAnimationFrame(frame); };
    const onMove = (e) => {
        const r = el.getBoundingClientRect();
        pointer.tx = ((e.clientX - r.left) / r.width - 0.5) * 2;
        pointer.ty = ((e.clientY - r.top) / r.height - 0.5) * 2;
    };
    const ro = new ResizeObserver(() => { size(); if (still) start(); });
    ro.observe(el);
    const io = new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; if (visible) start(); });
    io.observe(el);
    if (!still) window.addEventListener('pointermove', onMove, { passive: true });
    size();
    start();

    function destroy() {
        cancelAnimationFrame(raf);
        ro.disconnect();
        io.disconnect();
        window.removeEventListener('pointermove', onMove);
        scene.traverse((o) => { o.geometry?.dispose(); o.material?.dispose?.(); });
        renderer.dispose();
        renderer.domElement.remove();
        instances.delete(el);
    }
    return { destroy, key: keyOf(el) };
}

const keyOf = (el) => ['data-nova-3d', 'data-c1', 'data-c2'].map((a) => el.getAttribute(a)).join('|');

/** Alle 3D-Flächen auf der Seite starten (neue) oder neu aufbauen (geänderte Szene oder Farbe) */
export function scan(root = document) {
    for (const [el, inst] of instances) {
        if (!el.isConnected) inst.destroy();
    }
    root.querySelectorAll('[data-nova-3d]').forEach((el) => {
        const key = keyOf(el);
        const inst = instances.get(el);
        if (inst && inst.key === key && el.querySelector('canvas')) return;
        inst?.destroy();
        const created = mount(el);
        if (created) instances.set(el, created);
    });
}

window.Nova3D = { scan, scenes: SCENE_NAMES };
