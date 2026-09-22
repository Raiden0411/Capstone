// Bare import — Cropper.js v2 registers <cropper-*> custom elements on
// module load. A bare (side-effect) import cannot be tree-shaken.
import 'cropperjs';

/**
 * Location picker — Alpine factory (framework-agnostic module).
 *
 * Owns a plain MapLibre marker inside a `wire:ignore`'d map wrapper.
 * Communicates outward ONLY via debounced `$wire` calls.
 *
 * ── Configurable options (all optional) ──
 *   containerId      string  DOM id of the map container.
 *                            Default: 'business-location-map'
 *   wireMethod       string  Livewire method called with (lat, lng)
 *                            after the pin settles. Default: 'setBusinessLocation'
 *   geocodeMethod    string  Livewire method called with (lat, lng)
 *                            ~900 ms after the pin settles. Default: 'resolveAddress'
 *   geocodeEnabled   bool    Set false to skip the geocode call entirely.
 *                            Default: true
 *   initialLat       ?float  Starting latitude (null → no pin)
 *   initialLng       ?float  Starting longitude
 *
 * ── Outward events (window) ──
 *   'crop:result'      from imageCropper (unrelated)
 *   'map:pin-cleared'  dispatched by SFC to remove the marker
 *
 * ── Inward events (window) ──
 *   'request-geolocation'  dispatched by the SFC's Use-my-location button
 */

export function locationPicker(opts) {
    return {
        map:            null,
        marker:         null,
        lat:            opts.initialLat ?? null,
        lng:            opts.initialLng ?? null,
        _attachTries:   0,
        _syncTimer:     null,
        _geocodeTimer:  null,
        _attached:      false,
        _geoHandler:    null,

        // Options — captured at construction.
        _containerId:    opts.containerId    ?? 'business-location-map',
        _wireMethod:     opts.wireMethod     ?? 'setBusinessLocation',
        _geocodeMethod:  opts.geocodeMethod  ?? 'resolveAddress',
        _geocodeEnabled: opts.geocodeEnabled !== false,

        get hasPin() {
            return this.lat !== null && this.lng !== null;
        },

        init() {
            this.$nextTick(() => this._tryAttach());
        },

        _tryAttach() {
            if (this._attached) return;

            const map = window.__lastMapInstance || this._discoverMap();
            if (map) {
                this._attachToMap(map);
                return;
            }

            if (++this._attachTries < 40) {
                setTimeout(() => this._tryAttach(), 250);
            } else {
                console.warn('[location-picker] Could not find MapLibre instance after 40 attempts.');
            }
        },

        _discoverMap() {
            const container = document.getElementById(this._containerId);
            if (!container) return null;

            const isMap = (o) => o && typeof o === 'object'
                && typeof o.getCenter === 'function'
                && typeof o.easeTo     === 'function'
                && typeof o.on         === 'function'
                && typeof o.getZoom    === 'function';

            for (const k of ['_maplibregl_map', '_map', '__map', 'map', 'mapInstance']) {
                if (isMap(container[k])) return container[k];
            }

            if (container._x_dataStack && Array.isArray(container._x_dataStack)) {
                for (const scope of container._x_dataStack) {
                    if (!scope || typeof scope !== 'object') continue;
                    let names;
                    try { names = Object.getOwnPropertyNames(scope); } catch (e) { continue; }
                    for (const k of names) {
                        let v;
                        try { v = scope[k]; } catch (e) { continue; }
                        if (isMap(v)) return v;
                    }
                }
            }

            let el = container.parentElement;
            for (let d = 0; d < 6 && el; d++, el = el.parentElement) {
                for (const k of ['_maplibregl_map', '_map']) {
                    if (isMap(el[k])) return el[k];
                }
                if (el._x_dataStack && Array.isArray(el._x_dataStack)) {
                    for (const scope of el._x_dataStack) {
                        if (!scope || typeof scope !== 'object') continue;
                        let names;
                        try { names = Object.getOwnPropertyNames(scope); } catch (e) { continue; }
                        for (const k of names) {
                            let v;
                            try { v = scope[k]; } catch (e) { continue; }
                            if (isMap(v)) return v;
                        }
                    }
                }
            }

            return null;
        },

        _attachToMap(map) {
            if (!map) return;
            this._attached = true;
            this.map = map;

            if (this.hasPin) {
                this._placeMarker(this.lat, this.lng, false);
            }

            map.on('click', (e) => {
                const { lat, lng } = e.lngLat;
                this.lat = lat;
                this.lng = lng;
                this._placeMarker(lat, lng, true);
                this._scheduleSync(lat, lng);
            });

            if (!this._geoHandler) {
                this._geoHandler = () => this._requestGeolocation();
                window.addEventListener('request-geolocation', this._geoHandler);
            }

            console.log('[location-picker] attached', this._containerId);
        },

        _requestGeolocation() {
            if (!navigator.geolocation) {
                this._notify('Geolocation is not supported by your browser.', 'error');
                return;
            }

            this._notify('Requesting your location…', 'info');

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;

                    this.lat = lat;
                    this.lng = lng;
                    this._placeMarker(lat, lng, true);
                    this._scheduleSync(lat, lng);

                    this._notify('Location pinned.', 'success');
                },
                (err) => {
                    const reason = err && err.code === 1
                        ? 'Location permission denied.'
                        : 'Unable to retrieve your location.';
                    this._notify(reason, 'error');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        },

        _placeMarker(lat, lng, recenter) {
            if (!this.map || typeof maplibregl === 'undefined') return;

            if (this.marker) {
                this.marker.setLngLat([lng, lat]);
            } else {
                const SVG_NS = 'http://www.w3.org/2000/svg';

                const el = document.createElement('div');
                el.setAttribute('aria-hidden', 'true');
                el.style.cssText = [
                    'width:40px',
                    'height:40px',
                    'cursor:grab',
                    'user-select:none',
                    'will-change:transform',
                    'display:block',
                ].join(';');

                const svg = document.createElementNS(SVG_NS, 'svg');
                svg.setAttribute('width', '40');
                svg.setAttribute('height', '40');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', '#ef4444');
                svg.setAttribute('stroke', 'white');
                svg.setAttribute('stroke-width', '1.5');
                svg.style.cssText = 'display:block;filter:drop-shadow(0 4px 6px rgba(0,0,0,0.35));';

                const shape = document.createElementNS(SVG_NS, 'path');
                shape.setAttribute('d', 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z');
                svg.appendChild(shape);

                const dot = document.createElementNS(SVG_NS, 'circle');
                dot.setAttribute('cx', '12');
                dot.setAttribute('cy', '9');
                dot.setAttribute('r', '2.5');
                dot.setAttribute('fill', 'white');
                svg.appendChild(dot);

                el.appendChild(svg);

                this.marker = new maplibregl.Marker({
                    element: el,
                    anchor: 'bottom',
                    draggable: true,
                })
                    .setLngLat([lng, lat])
                    .addTo(this.map);

                this.marker.on('dragstart', () => { el.style.cursor = 'grabbing'; });
                this.marker.on('dragend', () => {
                    el.style.cursor = 'grab';
                    const pos = this.marker.getLngLat();
                    this.lat = pos.lat;
                    this.lng = pos.lng;
                    this._scheduleSync(pos.lat, pos.lng);
                });
            }

            if (recenter) {
                try {
                    this.map.easeTo({ center: [lng, lat], duration: 350, essential: true });
                } catch (e) { /* noop */ }
            }
        },

        _scheduleSync(lat, lng) {
            clearTimeout(this._syncTimer);
            this._syncTimer = setTimeout(() => {
                this.$wire[this._wireMethod](lat, lng);
            }, 120);

            if (this._geocodeEnabled) {
                clearTimeout(this._geocodeTimer);
                this._geocodeTimer = setTimeout(() => {
                    this.$wire[this._geocodeMethod](lat, lng);
                }, 900);
            }
        },

        _notify(message, type = 'info') {
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message, type },
            }));
        },

        clearMarker() {
            if (this.marker) {
                try { this.marker.remove(); } catch (e) { /* noop */ }
                this.marker = null;
            }
            this.lat = null;
            this.lng = null;
        },
    };
}

/* ═══════════════════════════════════════════════════════════
   Image cropper — picker + modal + preview consumer
   (unchanged from the last delivery; included here for a
    self-contained file)
   ═══════════════════════════════════════════════════════════ */

export function imageCropper(opts) {
    return {
        _token:   null,
        _handler: null,

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
            const file = event.target.files?.[0];
            if (!file) return;

            event.target.value = '';

            if (!file.type.startsWith('image/')) {
                this.$wire.dispatch('toast', { message: 'Please select an image file.', type: 'error' });
                return;
            }

            window.dispatchEvent(new CustomEvent('crop:request', {
                detail: {
                    token:       this._token,
                    file,
                    aspect:      opts.aspect ?? null,
                    title:       opts.title ?? 'Crop image',
                    description: opts.description ?? null,
                },
            }));
        },

        async _onResult(event) {
            if (event.detail.token !== this._token) return;

            const file = event.detail.file;
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
                        this.$wire.dispatch('toast', { message: 'Upload failed. Please try again.', type: 'error' });
                    },
                    () => {},
                );
            } catch (e) {
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                console.error('[image-cropper] upload failed', e);
            }
        },
    };
}

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
            if (!canvasEl) { this.cancel(); return; }

            const imageEl     = canvasEl.querySelector('cropper-image');
            const selectionEl = canvasEl.querySelector('cropper-selection');
            if (!imageEl || !selectionEl) { this.cancel(); return; }

            this._imageEl     = imageEl;
            this._selectionEl = selectionEl;

            imageEl.setAttribute('src', this._objectUrl);

            await new Promise((resolve) => {
                try { imageEl.$ready(() => resolve()); }
                catch (e) {
                    if (imageEl.complete) resolve();
                    else imageEl.addEventListener('load', () => resolve(), { once: true });
                }
            });

            try { imageEl.$center('contain'); } catch (e) {}

            try {
                selectionEl.aspectRatio = (aspect && aspect > 0) ? aspect : NaN;
                selectionEl.initialCoverage = 0.85;
                selectionEl.$reset();
            } catch (e) {}
        },

        rotate(deg) {
            if (!this._imageEl) return;
            try { this._imageEl.$rotate(deg); } catch (e) {}
        },

        reset() {
            if (this._imageEl) { try { this._imageEl.$center('contain'); } catch (e) {} }
            if (this._selectionEl) { try { this._selectionEl.$reset(); } catch (e) {} }
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

                const canvas = await this._selectionEl.$toCanvas({ width: outW, height: outH });
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

export function avatarPreview() {
    return {
        previewUrl: null,

        setUrl(url) {
            this.clear();
            this.previewUrl = url;
        },

        clear() {
            if (this.previewUrl) {
                try { URL.revokeObjectURL(this.previewUrl); } catch (e) {}
            }
            this.previewUrl = null;
        },
    };
}