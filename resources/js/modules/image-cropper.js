// Bare import — Cropper.js v2 registers <cropper-canvas>,
// <cropper-image>, <cropper-selection>, <cropper-shade>,
// <cropper-handle>, <cropper-grid>, <cropper-crosshair> as custom
// elements on module load. A bare (side-effect) import cannot be
// tree-shaken away by Vite/Rollup.
//
// NOTE: v2 is a web-components library. It does NOT ship a separate
// stylesheet — each custom element carries its styles inside an
// encapsulated shadow root. Do NOT add `import 'cropperjs/dist/cropper.css'`;
// that file does not exist in v2 and would 500 the whole module graph.
import 'cropperjs';

/**
 * Image cropper + preview — three Alpine factories.
 *
 * ── imageCropper(opts) ──
 *   Options:
 *     wireProperty   (required)  Public property on the SFC.
 *     aspect         (number)    Crop aspect. Omit / null for free-form.
 *     title          (string)    Modal title.
 *     description    (string)    Modal subtitle.
 *     previewEvent   (string)    Window event with an object URL of the
 *                                cropped blob (single mode) or of each
 *                                cropped blob (multiple mode).
 *     multiple       (boolean)   Multi-file crop flow. Default: false.
 *     maxFiles       (number)    Cap on picked files (multiple mode).
 *     completeMethod (string)    Livewire method called after a successful
 *                                uploadMultiple, so the SFC can merge the
 *                                incoming batch into its accumulated array.
 */

/* ═══════════════════════════════════════════════════════════
   Picker — one per upload input
   ═══════════════════════════════════════════════════════════ */

export function imageCropper(opts) {
    return {
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

            // Snapshot BEFORE clearing — some browsers empty the FileList
            // the instant the input's value is reset.
            const files = Array.from(input.files || []);
            input.value = '';

            if (files.length === 0) return;

            const images = files.filter((f) => f.type.startsWith('image/'));

            if (images.length === 0) {
                this.$wire.dispatch('toast', {
                    message: 'Please select at least one image file.',
                    type: 'error',
                });
                return;
            }

            // ── Single-file flow — one crop, one upload ──
            if (!opts.multiple) {
                window.dispatchEvent(new CustomEvent('crop:request', {
                    detail: {
                        token:       this._token,
                        file:        images[0],
                        aspect:      opts.aspect ?? null,
                        title:       opts.title ?? 'Crop image',
                        description: opts.description ?? null,
                    },
                }));
                return;
            }

            // ── Multi-file queue flow ──
            const capped = opts.maxFiles ? images.slice(0, opts.maxFiles) : images;

            this._queue          = capped.slice();
            this._croppedResults = [];
            this._totalCount     = capped.length;

            this._processNextInQueue();
        },

        _processNextInQueue() {
            if (this._queue.length === 0) {
                this._uploadCollected();
                return;
            }

            const file       = this._queue.shift();
            const currentNum = this._totalCount - this._queue.length;
            const baseTitle  = opts.title ?? 'Crop image';
            const title      = this._totalCount > 1
                ? `${baseTitle} (${currentNum} of ${this._totalCount})`
                : baseTitle;

            window.dispatchEvent(new CustomEvent('crop:request', {
                detail: {
                    token:       this._token,
                    file,
                    aspect:      opts.aspect ?? null,
                    title,
                    description: opts.description ?? null,
                },
            }));
        },

        async _onResult(event) {
            if (event.detail.token !== this._token) return;

            const file = event.detail.file;

            // ── Multi-file branch ──
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

            // ── Single-file branch — one upload, one preview event ──
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
                        this.$wire.dispatch('toast', {
                            message: 'Upload failed. Please try again.',
                            type: 'error',
                        });
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
                        this.$wire.dispatch('toast', {
                            message: 'Some images failed to upload. Please try again.',
                            type: 'error',
                        });
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

const MAX_OUTPUT = 4096;

export function cropModal() {
    return {
        open:        false,
        title:       'Crop image',
        description: null,
        token:       null,
        processing:  false,

        _imageEl:     null,
        _selectionEl: null,
        _objectUrl:   null,
        _handler:     null,

        init() {
            this._handler = (e) => this._open(e.detail);
            window.addEventListener('crop:request', this._handler);
        },

        destroy() {
            if (this._handler) window.removeEventListener('crop:request', this._handler);
            this._release();
        },

        async _open({ token, file, aspect, title, description }) {
            if (!file || !(file instanceof File)) return;

            this._release();

            this.token       = token;
            this.title       = title || 'Crop image';
            this.description = description;
            this.open        = true;
            this.processing  = false;

            this._objectUrl = URL.createObjectURL(file);

            await this.$nextTick();

            const canvasEl = this.$el.querySelector('cropper-canvas');
            if (!canvasEl) {
                console.warn('[image-cropper] cropper-canvas not found in modal');
                this.cancel();
                return;
            }

            const imageEl     = canvasEl.querySelector('cropper-image');
            const selectionEl = canvasEl.querySelector('cropper-selection');

            if (!imageEl || !selectionEl) {
                console.warn('[image-cropper] cropper-image / cropper-selection missing');
                this.cancel();
                return;
            }

            this._imageEl     = imageEl;
            this._selectionEl = selectionEl;

            imageEl.setAttribute('src', this._objectUrl);

            await new Promise((resolve) => {
                try {
                    imageEl.$ready(() => resolve());
                } catch (e) {
                    if (imageEl.complete) resolve();
                    else imageEl.addEventListener('load', () => resolve(), { once: true });
                }
            });

            try { imageEl.$center('contain'); } catch (e) { /* noop */ }

            try {
                selectionEl.aspectRatio     = (aspect && aspect > 0) ? aspect : NaN;
                selectionEl.initialCoverage = 0.85;
                selectionEl.$reset();
            } catch (e) { /* noop */ }
        },

        rotate(deg) {
            if (!this._imageEl) return;
            try { this._imageEl.$rotate(deg); } catch (e) { /* noop */ }
        },

        reset() {
            if (this._imageEl) {
                try { this._imageEl.$center('contain'); } catch (e) { /* noop */ }
            }
            if (this._selectionEl) {
                try { this._selectionEl.$reset(); } catch (e) { /* noop */ }
            }
        },

        async confirm() {
            if (!this._selectionEl || this.processing) return;

            this.processing = true;

            try {
                const selW = this._selectionEl.width  || 1;
                const selH = this._selectionEl.height || 1;

                let outW, outH;
                if (selW >= selH) {
                    outW = Math.min(MAX_OUTPUT, Math.round(selW));
                    outH = Math.max(1, Math.round(outW * selH / selW));
                } else {
                    outH = Math.min(MAX_OUTPUT, Math.round(selH));
                    outW = Math.max(1, Math.round(outH * selW / selH));
                }

                const canvas = await this._selectionEl.$toCanvas({
                    width:  outW,
                    height: outH,
                });

                if (!canvas) throw new Error('Crop produced no canvas.');

                const blob = await new Promise((resolve) => {
                    canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.95);
                });

                if (!blob) throw new Error('Canvas → Blob failed.');

                const filename = 'cropped-' + Date.now() + '.jpg';
                const file = new File([blob], filename, { type: 'image/jpeg' });

                this._finish(file);
            } catch (e) {
                console.error('[image-cropper] crop failed', e);
                this.processing = false;
            }
        },

        cancel() {
            if (this.processing) return;
            this._finish(null);
        },

        _finish(file) {
            const token = this.token;

            this.open  = false;
            this.token = null;

            this._release();

            window.dispatchEvent(new CustomEvent('crop:result', {
                detail: { token, file },
            }));
        },

        _release() {
            this._imageEl     = null;
            this._selectionEl = null;

            if (this._objectUrl) {
                const url = this._objectUrl;
                setTimeout(() => URL.revokeObjectURL(url), 200);
                this._objectUrl = null;
            }
        },
    };
}

/* ═══════════════════════════════════════════════════════════
   Preview consumer — manages the object-URL lifecycle
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