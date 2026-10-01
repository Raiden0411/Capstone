// Bare import — Cropper.js v2 registers <cropper-canvas>, <cropper-image>,
// <cropper-selection>, <cropper-shade>, <cropper-handle>, <cropper-grid>,
// <cropper-crosshair> and <cropper-viewer> as custom elements on module load.
// A bare (side-effect) import cannot be tree-shaken away by Vite/Rollup.
import 'cropperjs';

const MAX_OUTPUT  = 4096;
const EXT_BY_MIME = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };

/* ═══════════════════════════════════════════════════════════
   Picker — one per upload input
   ═══════════════════════════════════════════════════════════ */

export function imageCropper(opts = {}) {
    return {
        dragging: false,

        _token:   null,
        _handler: null,

        _queue:          [],
        _croppedResults: [],
        _totalCount:     0,

        init() {
            this._token   = 'crop-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
            this._handler = (e) => this._onResult(e);
            window.addEventListener('crop:result', this._handler);
        },

        destroy() {
            if (this._handler) {
                window.removeEventListener('crop:result', this._handler);
            }
        },

        pick(event) {
            const input = event.target;
            const files = Array.from(input.files || []);
            input.value = '';
            this._handleFiles(files);
        },

        drop(event) {
            event.preventDefault();
            this.dragging = false;
            this._handleFiles(Array.from(event.dataTransfer?.files || []));
        },

        dragOver(event) {
            event.preventDefault();
            this.dragging = true;
        },

        dragLeave() {
            this.dragging = false;
        },

        _toast(message) {
            this.$wire.dispatch('toast', { message, type: 'error' });
        },

        _handleFiles(files) {
            if (files.length === 0) return;

            let images = files.filter((f) => f.type.startsWith('image/'));

            if (images.length === 0) {
                this._toast('Please select at least one image file.');
                return;
            }

            if (opts.maxSizeMB) {
                const limit = opts.maxSizeMB * 1024 * 1024;
                const ok    = images.filter((f) => f.size <= limit);

                if (ok.length < images.length) {
                    this._toast(`Images must be ${opts.maxSizeMB} MB or smaller.`);
                }
                images = ok;
                if (images.length === 0) return;
            }

            if (!opts.multiple) {
                this._request(images[0], 1, 1);
                return;
            }

            const capped = opts.maxFiles ? images.slice(0, opts.maxFiles) : images;

            this._queue          = capped.slice();
            this._croppedResults = [];
            this._totalCount     = capped.length;

            this._processNextInQueue();
        },

        _request(file, index, total) {
            const baseTitle = opts.title ?? 'Crop image';

            window.dispatchEvent(new CustomEvent('crop:request', {
                detail: {
                    token:       this._token,
                    file,
                    title:       total > 1 ? `${baseTitle} (${index} of ${total})` : baseTitle,
                    description: opts.description ?? null,
                    round:       !!opts.round,
                    minSize:     opts.minSize ?? null,
                    queue:       { index, total },
                },
            }));
        },

        _processNextInQueue() {
            if (this._queue.length === 0) {
                this._uploadCollected();
                return;
            }

            const file       = this._queue.shift();
            const currentNum = this._totalCount - this._queue.length;

            this._request(file, currentNum, this._totalCount);
        },

        async _onResult(event) {
            if (event.detail.token !== this._token) return;

            const { file, aborted } = event.detail;

            if (aborted) {
                this._queue          = [];
                this._croppedResults = [];
                this._totalCount     = 0;
                return;
            }

            if (opts.multiple) {
                if (file) {
                    this._croppedResults.push(file);

                    if (opts.previewEvent) {
                        const url = URL.createObjectURL(file);
                        window.dispatchEvent(new CustomEvent(opts.previewEvent, {
                            detail: {
                                url,
                                index: this._croppedResults.length - 1,
                                total: this._totalCount,
                            },
                        }));
                    }
                }

                setTimeout(() => this._processNextInQueue(), 180);
                return;
            }

            if (!file) return;

            const previewUrl = opts.previewEvent ? URL.createObjectURL(file) : null;

            try {
                await this.$wire.upload(
                    opts.wireProperty,
                    file,
                    () => {
                        if (opts.previewEvent && previewUrl) {
                            window.dispatchEvent(new CustomEvent(opts.previewEvent, {
                                detail: { url: previewUrl },
                            }));
                        }
                    },
                    () => {
                        if (previewUrl) URL.revokeObjectURL(previewUrl);
                        this._toast('Upload failed. Please try again.');
                    },
                    () => { /* progress */ },
                );
            } catch (e) {
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                console.error('[image-cropper] upload failed', e);
            }
        },

        async _uploadCollected() {
            if (this._croppedResults.length === 0) return;

            const batch = this._croppedResults.slice();
            this._croppedResults = [];
            this._totalCount     = 0;

            try {
                await this.$wire.uploadMultiple(
                    opts.wireProperty,
                    batch,
                    () => {
                        if (opts.completeMethod) {
                            this.$wire[opts.completeMethod]();
                        }
                    },
                    () => {
                        this._toast('Some images failed to upload. Please try again.');
                    },
                    () => { /* progress */ },
                );
            } catch (e) {
                console.error('[image-cropper] multiple upload failed', e);
            }
        },
    };
}

/* ═══════════════════════════════════════════════════════════
   Modal — singleton per page
   ═══════════════════════════════════════════════════════════ */

export function cropModal(opts = {}) {
    const selectionId = opts.selectionId || 'crop-selection';

    return {
        selectionId,

        open:        false,
        title:       'Crop image',
        description: null,
        token:       null,
        processing:  false,
        error:       '',

        round:       false,
        grid:        true,

        zoomPct:     100,
        angle:       0,
        dims:        '',
        lowRes:      false,
        minSize:     null,

        queueIndex:  1,
        queueTotal:  1,

        _imageEl:      null,
        _selectionEl:  null,
        _objectUrl:    null,
        _baseScale:    1,
        _sourceType:   '',
        _sourceName:   '',
        _initialRound: false,
        _prevFocus:    null,
        _raf:          0,
        _onRequest:    null,
        _onChange:     null,
        _boundEls:     [],

        get confirmLabel() {
            if (this.queueTotal <= 1) return 'Save & upload';
            return this.queueIndex < this.queueTotal ? 'Crop & next' : 'Crop & finish';
        },

        init() {
            this._onRequest = (e) => this._open(e.detail);
            window.addEventListener('crop:request', this._onRequest);

            this._onChange = () => this._scheduleSync();

            const image     = this.$el.querySelector('cropper-image');
            const selection = this.$el.querySelector('#' + this.selectionId);

            if (selection) selection.addEventListener('change', this._onChange);
            if (image)     image.addEventListener('transform', this._onChange);

            this._boundEls = [
                [selection, 'change'],
                [image, 'transform'],
            ];
        },

        destroy() {
            if (this._onRequest) window.removeEventListener('crop:request', this._onRequest);

            for (const [el, type] of this._boundEls) {
                if (el) el.removeEventListener(type, this._onChange);
            }

            if (this._raf) cancelAnimationFrame(this._raf);

            this._unlockScroll();
            this._release();
        },

        async _open(d) {
            const { token, file } = d;
            if (!file || !(file instanceof File)) return;

            this._release();

            this.token       = token;
            this.title       = d.title || 'Crop image';
            this.description = d.description || null;
            this.processing  = false;
            this.error       = '';

            this.round         = !!d.round;
            this._initialRound = this.round;
            this.minSize       = d.minSize || null;
            this.queueIndex    = d.queue?.index || 1;
            this.queueTotal    = d.queue?.total || 1;

            this.angle   = 0;
            this.zoomPct = 100;
            this.dims    = '';
            this.lowRes  = false;

            this._sourceType = file.type || '';
            this._sourceName = file.name || '';

            this._objectUrl = URL.createObjectURL(file);

            this._prevFocus = document.activeElement;
            this._lockScroll();
            this.open = true;

            await this.$nextTick();

            // The custom-element upgrade is a microtask chain triggered by
            // `import 'cropperjs'`. On first open it can still be pending.
            await this._whenCustomElementsReady();

            // Two rAFs (style recalc → paint) plus a bounded poll — the
            // subtree whose `hidden` class just flipped to visible has no
            // measured box until the browser has laid it out.
            await this._waitForLayout();

            this.$el.focus({ preventScroll: true });

            const canvasEl = this.$el.querySelector('cropper-canvas');
            if (!canvasEl) {
                console.warn('[image-cropper] cropper-canvas not found');
                this._finish(null);
                return;
            }

            const imageEl     = canvasEl.querySelector('cropper-image');
            const selectionEl = this.$el.querySelector('#' + this.selectionId);

            if (!imageEl || !selectionEl) {
                console.warn('[image-cropper] cropper-image / selection missing', {
                    selectionId: this.selectionId,
                });
                this._finish(null);
                return;
            }

            this._imageEl     = imageEl;
            this._selectionEl = selectionEl;

            // Force transform-origin to 0 0 across the whole shadow tree so
            // every matrix we apply composes the standard way.
            this._forceZeroOrigin();

            imageEl.setAttribute('src', this._objectUrl);

            await Promise.race([
                new Promise((resolve) => {
                    try {
                        imageEl.$ready(() => resolve());
                    } catch (e) {
                        if (imageEl.complete) resolve();
                        else imageEl.addEventListener('load', () => resolve(), { once: true });
                    }
                }),
                new Promise((r) => setTimeout(r, 5000)),
            ]);

            if (this._imageEl !== imageEl) return;

            // Give Cropper a frame to process the src and lay out the img.
            await new Promise((r) => requestAnimationFrame(r));

            const fitOk = await this._fitContain();

            if (!fitOk) {
                console.warn('[image-cropper] fit failed after retries', {
                    selectionId: this.selectionId,
                    canvasW:    canvasEl.clientWidth,
                    canvasH:    canvasEl.clientHeight,
                    naturalW:   this._naturalWidth(),
                    naturalH:   this._naturalHeight(),
                    transform:  this._safeGetTransform(),
                });
            }

            this._baseScale = this._scale();

            try {
                selectionEl.aspectRatio     = 1;
                selectionEl.initialCoverage = 0.85;
                selectionEl.$reset();
            } catch (e) { /* noop */ }

            this._sync();
        },

        async _whenCustomElementsReady() {
            if (typeof customElements === 'undefined') return;

            const tags = ['cropper-canvas', 'cropper-image', 'cropper-selection'];

            try {
                await Promise.race([
                    Promise.all(tags.map((t) => customElements.whenDefined(t))),
                    new Promise((r) => setTimeout(r, 2000)),
                ]);
            } catch (e) { /* noop */ }
        },

        async _waitForLayout() {
            await new Promise((r) => requestAnimationFrame(r));
            await new Promise((r) => requestAnimationFrame(r));

            for (let i = 0; i < 20; i++) {
                const c = this.$el.querySelector('cropper-canvas');
                if (c && c.clientWidth > 0 && c.clientHeight > 0) {
                    // One more frame so the size is stable.
                    await new Promise((r) => requestAnimationFrame(r));
                    return;
                }
                await new Promise((r) => setTimeout(r, 16));
            }
        },

        _forceZeroOrigin() {
            const el = this._imageEl;
            if (!el || !el.shadowRoot) return;
            if (el.shadowRoot.querySelector('style[data-crop-origin-zero]')) return;

            try {
                const style = document.createElement('style');
                style.setAttribute('data-crop-origin-zero', '');
                // Match every element in the shadow tree — Cropper sometimes
                // transforms a wrapper instead of the inner <img>.
                style.textContent = ':host * { transform-origin: 0 0 !important; }';
                el.shadowRoot.appendChild(style);
            } catch (e) {
                console.warn('[image-cropper] shadow origin override failed', e);
            }
        },

        /**
         * Uses Cropper.js v2's own primitives, which are known-good:
         *
         *   imageEl.$resetTransform()  — restore to identity
         *   imageEl.$center('contain') — fit + center inside the canvas
         *
         * Verification: after each attempt, the transform must be a pure
         * scale + translate with a non-identity scale. If verification
         * fails, wait a frame and retry (up to 30 frames).
         */
        async _fitContain() {
            const imageEl = this._imageEl;
            if (!imageEl) return false;

            for (let attempt = 0; attempt < 30; attempt++) {
                try { imageEl.$resetTransform?.(); } catch (e) { /* noop */ }
                try { imageEl.$center('contain');    } catch (e) { /* noop */ }

                // Let Cropper's internal render land.
                await new Promise((r) => requestAnimationFrame(r));

                const m = this._safeGetTransform(imageEl);
                if (m && this._isReasonableFit(m)) return true;

                await new Promise((r) => requestAnimationFrame(r));
            }

            return false;
        },

        /**
         * Read the current transform, normalized to [a, b, c, d, e, f].
         * Cropper.js v2 has returned both an array and a DOMMatrix across
         * versions; handle both shapes.
         */
        _safeGetTransform(el = null) {
            el = el || this._imageEl;
            if (!el) return null;

            try {
                const raw = el.$getTransform();
                if (Array.isArray(raw) && raw.length === 6) return raw.slice();
                if (raw && typeof raw.a === 'number') {
                    return [raw.a, raw.b, raw.c, raw.d, raw.e, raw.f];
                }
            } catch (e) { /* noop */ }

            return null;
        },

        /**
         * A transform is "reasonable" if:
         *   • the scale (|(a, b)|) is positive and not absurdly small or large
         *   • the translation is not astronomically off-screen
         *
         * This does not need to be a perfect fit test; it only needs to
         * distinguish "fit applied" from "identity / clobbered".
         */
        _isReasonableFit(m) {
            const scale = Math.hypot(m[0], m[1]);
            if (!isFinite(scale) || scale <= 0.01 || scale > 100) return false;

            if (!isFinite(m[4]) || !isFinite(m[5])) return false;
            if (Math.abs(m[4]) > 1e6 || Math.abs(m[5]) > 1e6) return false;

            // Identity check: if scale is effectively 1 AND translation is
            // effectively 0, the library's fit didn't run.
            const isIdentity =
                Math.abs(m[0] - 1) < 0.001 &&
                Math.abs(m[3] - 1) < 0.001 &&
                Math.abs(m[1])     < 0.001 &&
                Math.abs(m[2])     < 0.001 &&
                Math.abs(m[4])     < 0.5   &&
                Math.abs(m[5])     < 0.5;

            return !isIdentity;
        },

        _shadowImg() {
            const el = this._imageEl;
            if (!el) return null;

            if (el.$image && typeof el.$image.naturalWidth === 'number') {
                return el.$image;
            }

            const sr = el.shadowRoot;
            if (sr) {
                const img = sr.querySelector('img');
                if (img) return img;
            }

            return null;
        },

        _naturalWidth() {
            const img = this._shadowImg();
            return img ? (img.naturalWidth || 0) : 0;
        },

        _naturalHeight() {
            const img = this._shadowImg();
            return img ? (img.naturalHeight || 0) : 0;
        },

        _originPx() {
            const img = this._shadowImg();
            if (!img) return { ox: 0, oy: 0 };

            let os = '';
            try { os = window.getComputedStyle(img).transformOrigin || ''; } catch (e) { /* noop */ }

            // With the shadow override in place, this is '0 0'. If the
            // override failed, this reads the default '50% 50%' — return
            // the real pixel origin so the composition math stays correct.
            if (os === '') {
                const nw = img.naturalWidth  || 0;
                const nh = img.naturalHeight || 0;
                return { ox: nw / 2, oy: nh / 2 };
            }

            const parts = os.trim().split(/\s+/);
            return {
                ox: this._parseOriginPart(parts[0] ?? '0', img.naturalWidth  || 0),
                oy: this._parseOriginPart(parts[1] ?? parts[0] ?? '0', img.naturalHeight || 0),
            };
        },

        _parseOriginPart(v, dim) {
            if (typeof v !== 'string' || v === '') return 0;

            if (v.endsWith('%')) {
                const pct = parseFloat(v);
                return isFinite(pct) ? dim * pct / 100 : 0;
            }
            if (v.endsWith('px')) {
                const px = parseFloat(v);
                return isFinite(px) ? px : 0;
            }
            const n = parseFloat(v);
            return isFinite(n) ? n : 0;
        },

        /* ── Toolbar actions ──────────────────────────────── */

        rotate(deg) {
            this._rotateBy(deg);
        },

        flip(axis) {
            if (axis === 'h') this._about(-1, 0, 0, 1);
            else              this._about(1, 0, 0, -1);
        },

        setZoom(pct) {
            if (!this._imageEl || !this._baseScale) return;

            const target = Math.min(400, Math.max(25, Number(pct) || 100));
            const current = this._scale();
            const ratio   = (this._baseScale * target / 100) / current;

            if (!isFinite(ratio) || ratio <= 0) return;

            this._about(ratio, 0, 0, ratio);
            this.zoomPct = target;
        },

        zoomBy(step) {
            this.setZoom(this.zoomPct * (1 + step));
        },

        setStraighten(value) {
            const next = Math.min(45, Math.max(-45, Number(value) || 0));
            this._rotateBy(next - this.angle);
            this.angle = next;
        },

        async reset() {
            if (!this._imageEl) return;

            this.angle   = 0;
            this.zoomPct = 100;
            this.round   = this._initialRound;
            this.error   = '';

            // Re-fit via the library's own primitives — this clears pan,
            // zoom, rotate, and flip in one operation.
            await this._fitContain();

            this._baseScale = this._scale();

            if (this._selectionEl) {
                try {
                    this._selectionEl.aspectRatio     = 1;
                    this._selectionEl.initialCoverage = 0.85;
                    this._selectionEl.$reset();
                } catch (e) { /* noop */ }
            }

            this._scheduleSync();
        },

        /* ── Matrix helpers ───────────────────────────────── */

        _scale() {
            const m = this._safeGetTransform();
            if (!m) return 1;
            return Math.hypot(m[0], m[1]) || 1;
        },

        _pivot() {
            const s = this._selectionEl;
            if (s && s.width > 0 && s.height > 0) {
                return [s.x + s.width / 2, s.y + s.height / 2];
            }
            const c = this.$el.querySelector('cropper-canvas');
            return [(c?.clientWidth || 0) / 2, (c?.clientHeight || 0) / 2];
        },

        /**
         * Apply the linear map L = [[la, lc], [lb, ld]] to the image around
         * the pivot Q = (cx, cy), accounting for the transform-origin O:
         *
         *   A' = L·A
         *   t' = L·t + (I - L)·(Q - O)
         *
         * The (Q - O) term is what fixes the "zoom drifts up" bug — without
         * it the effective pivot sits at O, not Q, whenever O ≠ 0.
         * With the origin override in place, O = (0, 0) and the two forms
         * coincide.
         */
        _about(la, lb, lc, ld) {
            if (!this._imageEl) return;

            const m = this._safeGetTransform();
            if (!m) return;

            try {
                const [cx, cy]   = this._pivot();
                const { ox, oy } = this._originPx();
                const qx = cx - ox;
                const qy = cy - oy;

                const [a, b, c, d, e, f] = m;

                const na = la * a + lc * b;
                const nb = lb * a + ld * b;
                const nc = la * c + lc * d;
                const nd = lb * c + ld * d;
                const ne = la * e + lc * f + (1 - la) * qx - lc * qy;
                const nf = lb * e + ld * f - lb * qx + (1 - ld) * qy;

                this._imageEl.$setTransform(na, nb, nc, nd, ne, nf);
            } catch (e) {
                console.warn('[image-cropper] transform failed', e);
            }
        },

        _rotateBy(deg) {
            if (!deg) return;

            const t   = deg * Math.PI / 180;
            const cos = Math.abs(Math.cos(t)) < 1e-10 ? 0 : Math.cos(t);
            const sin = Math.abs(Math.sin(t)) < 1e-10 ? 0 : Math.sin(t);

            this._about(cos, sin, -sin, cos);
        },

        /* ── Readouts ─────────────────────────────────────── */

        _scheduleSync() {
            if (this._raf) return;
            this._raf = requestAnimationFrame(() => {
                this._raf = 0;
                this._sync();
            });
        },

        _sync() {
            if (!this._imageEl || !this._selectionEl) return;

            const scale = this._scale();

            if (this._baseScale) {
                this.zoomPct = Math.round(scale / this._baseScale * 100);
            }

            const { w, h } = this._outputDims(scale);
            this.dims      = `${w} × ${h} px`;
            this.lowRes    = !!this.minSize && Math.min(w, h) < this.minSize;
        },

        _outputDims(scale) {
            const sel = this._selectionEl;

            let w = (sel.width  || 1) / scale;
            let h = (sel.height || 1) / scale;

            const cap  = MAX_OUTPUT;
            const long = Math.max(w, h);

            if (long > cap) {
                const r = cap / long;
                w *= r;
                h *= r;
            }

            w = Math.max(1, Math.round(w));
            h = Math.max(1, Math.round(h));

            if (this.round) h = w;

            return { w, h };
        },

        /* ── Confirm / cancel ─────────────────────────────── */

        async confirm() {
            if (!this._selectionEl || !this._imageEl || this.processing) return;

            this.processing = true;
            this.error      = '';

            try {
                const { w, h } = this._outputDims(this._scale());

                let canvas = await this._selectionEl.$toCanvas({ width: w, height: h });
                if (!canvas) throw new Error('Crop produced no canvas.');

                const mime = this._resolveMime();

                if (mime === 'image/jpeg') {
                    canvas = this._flatten(canvas, true, this.round);
                } else if (this.round) {
                    canvas = this._flatten(canvas, false, true);
                }

                const blob = await new Promise((resolve) => {
                    canvas.toBlob((b) => resolve(b), mime, 0.92);
                });

                if (!blob) throw new Error('Canvas → Blob failed.');

                const ext  = EXT_BY_MIME[blob.type] || 'png';
                const base = (this._sourceName || 'image')
                    .replace(/\.[^.]+$/, '')
                    .replace(/[^\w-]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .slice(0, 40) || 'image';

                const file = new File([blob], `${base}-cropped-${Date.now()}.${ext}`, { type: blob.type });

                this._finish(file);
            } catch (e) {
                console.error('[image-cropper] crop failed', e);
                this.error      = 'Could not crop this image. Try again or pick a different file.';
                this.processing = false;
            }
        },

        _resolveMime() {
            let fmt = /image\/(png|webp|gif|svg)/.test(this._sourceType) ? 'png' : 'jpeg';

            if (this.round && fmt === 'jpeg') fmt = 'png';

            return 'image/' + fmt;
        },

        _flatten(src, whiteBackground, circle) {
            const w   = src.width;
            const h   = src.height;
            const out = document.createElement('canvas');
            out.width  = w;
            out.height = h;

            const ctx = out.getContext('2d');

            if (circle) {
                ctx.beginPath();
                ctx.ellipse(w / 2, h / 2, w / 2, h / 2, 0, 0, Math.PI * 2);
                ctx.closePath();
                ctx.clip();
            }

            if (whiteBackground) {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
            }

            ctx.drawImage(src, 0, 0);

            return out;
        },

        cancel() {
            if (this.processing) return;
            this._finish(null);
        },

        cancelAll() {
            if (this.processing) return;
            this._finish(null, true);
        },

        _finish(file, aborted = false) {
            const token = this.token;

            this.open  = false;
            this.token = null;

            this._unlockScroll();
            this._release();

            const prev = this._prevFocus;
            this._prevFocus = null;
            if (prev && typeof prev.focus === 'function') {
                try { prev.focus({ preventScroll: true }); } catch (e) { /* noop */ }
            }

            window.dispatchEvent(new CustomEvent('crop:result', {
                detail: { token, file, aborted },
            }));
        },

        _release() {
            if (this._imageEl) {
                try { this._imageEl.removeAttribute('src'); } catch (e) { /* noop */ }
            }

            this._imageEl     = null;
            this._selectionEl = null;
            this._baseScale   = 1;

            if (this._objectUrl) {
                const url = this._objectUrl;
                setTimeout(() => URL.revokeObjectURL(url), 200);
                this._objectUrl = null;
            }
        },

        _lockScroll() {
            document.documentElement.classList.add('overflow-hidden');
        },

        _unlockScroll() {
            document.documentElement.classList.remove('overflow-hidden');
        },
    };
}

/* ═══════════════════════════════════════════════════════════
   Preview consumer
   ═══════════════════════════════════════════════════════════ */

export function avatarPreview() {
    return {
        previewUrl: null,

        setUrl(url) {
            this.clear();
            this.previewUrl = url;
        },

        clear() {
            if (this.previewUrl) {
                try { URL.revokeObjectURL(this.previewUrl); } catch (e) { /* noop */ }
            }
            this.previewUrl = null;
        },
    };
}