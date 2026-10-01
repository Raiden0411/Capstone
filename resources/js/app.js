import './bootstrap';
import 'preline';

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Chart from 'chart.js/auto';
window.Chart = Chart;

import collapse from '@alpinejs/collapse';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(collapse);
});

(function installMapLibreInterceptor() {
    if (window.__maplibreInterceptorInstalled) return;
    window.__maplibreInterceptorInstalled = true;

    const tune = (map) => {
        if (!map) return;
        try { map.setMaxPitch(60); } catch (e) { /* noop */ }
        try { map.setMinPitch(0);  } catch (e) { /* noop */ }

        try {
            if (map.scrollZoom) {
                map.scrollZoom.setWheelZoomRate(1 / 450);
                map.scrollZoom.setZoomRate(1 / 80);
            }
        } catch (e) { /* noop */ }

        try {
            if (map.touchZoomRotate) {
                map.touchZoomRotate.setRotationThreshold(8);
            }
        } catch (e) { /* noop */ }

        try {
            if (map.touchZoomRotate) {
                map.touchZoomRotate.enableRotation();
            }
        } catch (e) { /* noop */ }

        try {
            if (map.dragRotate) map.dragRotate.enable();
        } catch (e) { /* noop */ }
    };

    const patch = (lib) => {
        if (!lib || typeof lib.Map !== 'function' || lib.Map.__captured) return;

        const Original = lib.Map;

        const Captured = function (...args) {
            const instance = new Original(...args);

            tune(instance);

            window.__lastMapInstance = instance;
            console.log('[explore-map] MapLibre instance intercepted + tuned');

            window.dispatchEvent(new CustomEvent('maplibre:captured', {
                detail: { map: instance },
            }));

            return instance;
        };

        Captured.prototype = Original.prototype;
        Object.setPrototypeOf(Captured, Original);

        try {
            Object.defineProperty(Captured, '__captured', { value: true });
            Object.defineProperty(Captured, 'name', { value: 'Map', configurable: true });
        } catch (e) { /* noop */ }

        lib.Map = Captured;
    };

    if (typeof window.maplibregl === 'undefined') {
        let _val;
        try {
            Object.defineProperty(window, 'maplibregl', {
                configurable: true,
                get() { return _val; },
                set(v) { _val = v; patch(v); },
            });
        } catch (e) {
            let tries = 0;
            const t = setInterval(() => {
                tries++;
                if (window.maplibregl) {
                    clearInterval(t);
                    patch(window.maplibregl);
                } else if (tries > 100) {
                    clearInterval(t);
                }
            }, 100);
        }
    } else {
        patch(window.maplibregl);
    }
})();

import { RealtimeNavigation } from './modules/realtime-navigation';
window.RealtimeNavigation = RealtimeNavigation;

import { locationPicker } from './modules/location-picker';
window.locationPicker = locationPicker;

import { imageCropper, cropModal, avatarPreview } from './modules/image-cropper';
window.imageCropper   = imageCropper;
window.cropModal      = cropModal;
window.avatarPreview  = avatarPreview;

(function installExploreMapHelpers() {
    if (window.__exploreMapModule) return;
    window.__exploreMapModule = true;

    function getData() {
        var root = document.querySelector('[data-map-root]');
        if (!root || !window.Alpine || typeof window.Alpine.$data !== 'function') return null;
        try { return window.Alpine.$data(root); } catch (e) { return null; }
    }

    window.__mapApi = {
        setLocating: function (v) { var d = getData(); if (d) d.locating = v; },
        setMapLoading: function (v) { var d = getData(); if (d) { d.mapLoading = v; d.mapStuck = false; } },
        wire: function () { var d = getData(); return d ? d.$wire : null; },
        toast: function (type, message) {
            var d = getData();
            if (d && typeof d.toast === 'function') d.toast(type, message);
        },
    };

    var pollTimer = null;
    var pollCount = 0;

    window.__mapCanvasPoll = {
        start: function () {
            if (pollTimer) clearTimeout(pollTimer);
            pollCount = 0;
            this.tick();
        },
        stop: function () {
            if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
            pollCount = 0;
        },
        tick: function () {
            var self = this;
            pollTimer = setTimeout(function () {
                var d = getData();
                if (!d) { self.stop(); return; }
                var c = document.querySelector('#tourist-map canvas');
                if (c && c.width > 0 && c.height > 0) {
                    d.mapLoading = false;
                    d.mapStuck   = false;
                    self.stop();
                    return;
                }
                pollCount++;
                if (pollCount === 60)   d.mapStuck = true;
                if (pollCount >= 1200) { self.stop(); return; }
                self.tick();
            }, 250);
        }
    };

    window.addEventListener('map:loaded', function () {
        var d = getData();
        if (d) { d.mapLoading = false; d.mapStuck = false; }
        window.__mapCanvasPoll.stop();
    });
})();

window.mapApp = function () {
    const toastTimers = new WeakMap();

    return {

        mobileOpen:      false,
        sidebarOpen:     false,
        followMode:      false,
        locating:        false,
        helpOpen:        false,
        mapLoading:      true,
        mapStuck:        false,
        toasts:          [],
        detailMinimized: false,

        _vpTimer:        null,
        _watchId:        null,
        _bootTimer:      null,
        _map:            null,
        _nav:            null,
        _attachTries:    0,
        _navRetryCount:  0,
        _tornDown:       false,
        _handlers:       [],
        _token:          null,
        _resizeObserver: null,
        _resizeTimers:   null,

        _routeHadData:   false,
        _routeListener:  null,
        _boundsListener: null,

        _userMarker:     null,
        _userMarkerEl:   null,
        _userWatchers:   [],

        get remainingText() {
            const store = (typeof Alpine !== 'undefined' && Alpine.store) ? Alpine.store('nav') : null;
            const m = store?.remainingMeters;

            if (m === null || m === undefined) return '—';
            if (m < 1000) return Math.round(m) + ' m';
            if (m < 10000) return (m / 1000).toFixed(1) + ' km';
            return Math.round(m / 1000) + ' km';
        },

        get isActive() {
            return this._token !== null
                && window.__activeMapAppToken === this._token
                && !this._tornDown;
        },

        _compactCoords(coords) {
            if (!Array.isArray(coords)) return [];
            return coords.map(c => {
                if (!Array.isArray(c) || c.length < 2) return null;
                const lng = Number(c[0]);
                const lat = Number(c[1]);
                if (!isFinite(lng) || !isFinite(lat)) return null;
                return [Math.round(lng * 1e5) / 1e5, Math.round(lat * 1e5) / 1e5];
            }).filter(Boolean);
        },

        _updateRouteLayer(coords) {
            const map = this._map;
            if (!map) return;

            const hasData = Array.isArray(coords) && coords.length >= 2;

            try {
                if (map.getLayer('tourist-route-line'))  map.removeLayer('tourist-route-line');
                if (map.getLayer('tourist-route-glow'))  map.removeLayer('tourist-route-glow');
                if (map.getSource('tourist-route'))      map.removeSource('tourist-route');
            } catch (e) { /* noop */ }

            if (!hasData) return;

            const compact = this._compactCoords(coords);
            if (compact.length < 2) return;

            try {
                map.addSource('tourist-route', {
                    type: 'geojson',
                    data: {
                        type: 'Feature',
                        properties: {},
                        geometry: { type: 'LineString', coordinates: compact },
                    },
                });

                map.addLayer({
                    id: 'tourist-route-glow',
                    type: 'line',
                    source: 'tourist-route',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: {
                        'line-color': '#3B82F6',
                        'line-width': 14,
                        'line-opacity': 0.18,
                        'line-blur': 4,
                    },
                });

                map.addLayer({
                    id: 'tourist-route-line',
                    type: 'line',
                    source: 'tourist-route',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: {
                        'line-color': '#2563EB',
                        'line-width': 5,
                        'line-opacity': 0.95,
                    },
                });
            } catch (e) {
                console.warn('[explore-map] route layer add failed', e);
            }
        },

        _safeMapResize() {
            if (!this._map || typeof this._map.resize !== 'function') return;
            try {
                this._map.resize();
                const container = document.getElementById('tourist-map');
                if (container) {
                    const canvas = container.querySelector('canvas');
                    if (canvas) {
                        const dpr = window.devicePixelRatio || 1;
                        const cw = Math.round(canvas.width / dpr);
                        const ch = Math.round(canvas.height / dpr);
                        const ew = Math.round(container.clientWidth);
                        const eh = Math.round(container.clientHeight);
                        if (cw !== ew || ch !== eh) {
                            console.debug(
                                '[explore-map] resize mismatch:',
                                'canvas', cw + '×' + ch,
                                'container', ew + '×' + eh,
                            );
                        }
                    }
                }
            } catch (e) { /* noop */ }
        },

        /**
         * Fire a burst of resize calls at increasing delays. MapLibre
         * re-measures the container on every call, so a burst covers the
         * staggered moments when the flex layout settles on desktop:
         * Alpine init → sidebar transition (300ms) → webfont swap →
         * panel mount. Any single timeout is fragile; the burst is not.
         */
        _burstResize() {
            if (this._resizeTimers) {
                this._resizeTimers.forEach(t => clearTimeout(t));
            }
            this._resizeTimers = [0, 80, 200, 400, 800, 1600, 3000].map(ms =>
                setTimeout(() => {
                    if (!this.isActive) return;
                    this._safeMapResize();
                }, ms)
            );
        },

        /**
         * Watch the map container for size changes and re-measure the
         * MapLibre canvas on every change.
         *
         * WHY THIS IS NECESSARY:
         *   MapLibre locks its canvas width/height at instantiation.
         *   It only re-measures when `map.resize()` is called. Any
         *   layout-driven change after `maplibre:captured` — the flex
         *   algorithm settling, a sibling panel mounting, a scrollbar
         *   appearing, DevTools opening, the sidebar toggling —
         *   leaves the canvas at its old size and the container's
         *   background bleeds through on the right/bottom.
         *
         *   The ResizeObserver fires for every size change (including
         *   the very first layout pass) which catches later changes;
         *   `_burstResize()` covers the initial settle.
         */
        _installResizeObserver() {
            if (this._resizeObserver) return;
            if (typeof ResizeObserver === 'undefined') return;

            const container = document.getElementById('tourist-map');
            if (!container) return;

            let lastW = 0;
            let lastH = 0;

            this._resizeObserver = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    const w = Math.round(entry.contentRect.width);
                    const h = Math.round(entry.contentRect.height);
                    if (w === lastW && h === lastH) continue;
                    if (w === 0 || h === 0) { lastW = w; lastH = h; continue; }
                    lastW = w;
                    lastH = h;
                    if (!this.isActive) continue;
                    this._safeMapResize();
                }
            });

            try {
                this._resizeObserver.observe(container);
                const parent = container.parentElement;
                if (parent) this._resizeObserver.observe(parent);
            } catch (e) {
                console.warn('[explore-map] ResizeObserver.observe failed', e);
            }
        },

        _syncUserMarker() {
            if (!this.isActive) return;
            if (!this._map) return;
            if (typeof maplibregl === 'undefined') return;

            const lat = Number(this.$wire.userLat);
            const lng = Number(this.$wire.userLng);
            const navigating = !!this.$wire.navigationActive;

            const hasCoords = Number.isFinite(lat)
                && Number.isFinite(lng)
                && !(lat === 0 && lng === 0)
                && Math.abs(lat) <= 90
                && Math.abs(lng) <= 180;

            if (!hasCoords || navigating) {
                if (this._userMarker) {
                    try { this._userMarker.remove(); } catch (e) { /* noop */ }
                    this._userMarker = null;
                    this._userMarkerEl = null;
                }
                return;
            }

            if (this._userMarker) {
                let el = null;
                try {
                    el = (typeof this._userMarker.getElement === 'function')
                        ? this._userMarker.getElement()
                        : null;
                } catch (e) { /* noop */ }

                if (!el || el.isConnected === false) {
                    this._clearUserMarker();
                } else {
                    try { this._userMarker.setLngLat([lng, lat]); } catch (e) { /* noop */ }
                    return;
                }
            }

            const el = document.createElement('div');
            el.className = 'tourist-user-marker';
            el.setAttribute('role', 'img');
            el.setAttribute('aria-label', 'Your location');
            el.innerHTML =
                '<div class="tourist-user-marker-ring"></div>' +
                '<div class="tourist-user-marker-halo"></div>' +
                '<div class="tourist-user-marker-dot"></div>';

            el.addEventListener('click', () => {
                try { this.$wire.shareLocation(); } catch (e) { /* noop */ }
            });

            try {
                this._userMarker = new maplibregl.Marker({
                    element: el,
                    anchor: 'center',
                    pitchAlignment: 'viewport',
                    rotationAlignment: 'viewport',
                })
                    .setLngLat([lng, lat])
                    .addTo(this._map);

                this._userMarkerEl = el;
            } catch (e) {
                console.warn('[explore-map] user marker add failed', e);
                this._userMarker = null;
                this._userMarkerEl = null;
            }
        },

        _clearUserMarker() {
            if (!this._userMarker) return;
            try { this._userMarker.remove(); } catch (e) { /* noop */ }
            this._userMarker = null;
            this._userMarkerEl = null;
        },

        _installUserMarkerWatchers() {
            if (this._userWatchers.length > 0) return;

            try {
                // Livewire's JS API exposes `$watch` on the `$wire` proxy.
                // The previous `this.wire.watch(...)` form threw a
                // TypeError on the first push, so the second and third
                // watchers never ran and the user marker never re-synced.
                this._userWatchers.push(
                    this.$wire.$watch('userLat', () => this._syncUserMarker())
                );
                this._userWatchers.push(
                    this.$wire.$watch('userLng', () => this._syncUserMarker())
                );
                this._userWatchers.push(
                    this.$wire.$watch('navigationActive', () => this._syncUserMarker())
                );
            } catch (e) {
                console.warn('[explore-map] user-marker watchers failed', e);
            }
        },

        _fitRouteBounds(coords) {
            const map = this._map;
            if (!map || !Array.isArray(coords) || coords.length < 2) return;

            let minLng =  Infinity, minLat =  Infinity;
            let maxLng = -Infinity, maxLat = -Infinity;

            for (const c of coords) {
                if (!Array.isArray(c) || c.length < 2) continue;
                const lng = Number(c[0]), lat = Number(c[1]);
                if (!isFinite(lng) || !isFinite(lat)) continue;
                if (lng < minLng) minLng = lng;
                if (lat < minLat) minLat = lat;
                if (lng > maxLng) maxLng = lng;
                if (lat > maxLat) maxLat = lat;
            }

            if (!isFinite(minLng) || !isFinite(minLat)) return;

            const spanLng = Math.max(maxLng - minLng, 0.0005);
            const spanLat = Math.max(maxLat - minLat, 0.0005);
            const padLng  = Math.max(spanLng * 0.12, 0.003);
            const padLat  = Math.max(spanLat * 0.12, 0.003);

            const sw = [minLng - padLng, minLat - padLat];
            const ne = [maxLng + padLng, maxLat + padLat];

            const isMobile = window.innerWidth < 1024;
            const padding = isMobile
                ? { top: 90,  bottom: 240, left: 40,  right: 40  }
                : { top: 100, bottom: 180, left: 100, right: 100 };

            try {
                map.fitBounds([sw, ne], {
                    padding,
                    duration: 900,
                    maxZoom: 15,
                    easing: (t) => 1 - Math.pow(1 - t, 3),
                });
            } catch (e) {
                console.warn('[explore-map] fitBounds failed', e);
            }
        },

        boot() {
            if (!this._token) {
                this._token = Math.random().toString(36).slice(2) + Date.now().toString(36);
            }

            window.__activeMapAppToken = this._token;
            this._tornDown = false;

            if (!Alpine.store('nav')) {
                Alpine.store('nav', {
                    active:          false,
                    heading:         null,
                    mapBearing:      0,
                    following:       true,
                    remainingMeters: null,
                });
            }

            try {
                this.sidebarOpen = !!this.$wire.sidebarOpen;
                this.followMode  = !!this.$wire.followMode;
            } catch (e) { /* noop */ }

            this._initNavigationModule();

            if (window.__mapCanvasPoll) window.__mapCanvasPoll.start();

            this._bootTimer = setTimeout(() => {
                if (!this.isActive) return;
                if (this.mapLoading) window.__mapApi.setMapLoading(false);
            }, 12000);

            const onCaptured = (e) => {
                if (!this.isActive) return;
                const map = e.detail?.map;
                if (!map) return;
                console.log('[explore-map] Received maplibre:captured — attaching nav');
                this._map = map;
                this._nav?.attach(map);

                this._clearUserMarker();

                if (window.__mapCanvasPoll) window.__mapCanvasPoll.stop();
                this.mapLoading = false;
                this.mapStuck   = false;

                try {
                    map.setRenderWorldCopies(false);
                    map.setMaxBounds([[115.0, 4.0], [128.0, 22.0]]);
                    map.setMinZoom(5);
                } catch (err) { /* noop */ }

                this._installResizeObserver();
                this._burstResize();

                try {
                    const existing = Array.isArray(this.$wire.routePolyline) ? this.$wire.routePolyline : [];
                    this._updateRouteLayer(existing);
                    this._routeHadData = existing.length >= 2;

                    if (existing.length >= 2) {
                        setTimeout(() => {
                            if (!this.isActive) return;
                            this._fitRouteBounds(existing);
                        }, 120);
                    }
                } catch (err) {
                    console.warn('[explore-map] route hydrate failed', err);
                }

                this._installUserMarkerWatchers();
                this._syncUserMarker();

                if (this.$wire.navigationActive) {
                    this.startNavigation();
                }
            };

            const onResize = () => {
                if (!this.isActive) return;
                if (window.innerWidth >= 1024 && this.mobileOpen) {
                    this.mobileOpen = false;
                }
                this._safeMapResize();
            };

            const onMapResizeEvent = () => {
                if (!this.isActive) return;
                this._safeMapResize();
            };

            const onWindowLoad = () => {
                if (!this.isActive) return;
                this._safeMapResize();
            };

            window.addEventListener('maplibre:captured', onCaptured);
            window.addEventListener('resize', onResize);
            window.addEventListener('map:resize', onMapResizeEvent);
            window.addEventListener('load', onWindowLoad);
            this._handlers.push(
                ['maplibre:captured', onCaptured],
                ['resize',            onResize],
                ['map:resize',        onMapResizeEvent],
                ['load',              onWindowLoad],
            );

            try {
                this._tryAttachMap();
            } catch (e) {
                console.error('[explore-map] _tryAttachMap threw — continuing boot', e);
            }

            try {
                this._routeListener = this.$wire.$watch('routePolyline', (coords) => {
                    if (!this.isActive) return;
                    const arr = Array.isArray(coords) ? coords : [];
                    this._updateRouteLayer(arr);

                    const hasData = arr.length >= 2;
                    if (hasData && !this._routeHadData) {
                        setTimeout(() => {
                            if (!this.isActive) return;
                            this._fitRouteBounds(arr);
                        }, 60);
                    }
                    this._routeHadData = hasData;
                });
            } catch (e) {
                console.warn('[explore-map] routePolyline watcher failed', e);
            }

            try {
                this._boundsListener = this.$wire.$watch('pendingRouteBounds', (bounds) => {
                    if (!this.isActive) return;
                    if (!Array.isArray(bounds) || bounds.length !== 2) return;
                    if (!this._map) return;

                    const [[lng1, lat1], [lng2, lat2]] = bounds;
                    const sw = [Math.min(lng1, lng2), Math.min(lat1, lat2)];
                    const ne = [Math.max(lng1, lng2), Math.max(lat1, lat2)];

                    const isMobile = window.innerWidth < 1024;
                    const padding = isMobile
                        ? { top: 90,  bottom: 240, left: 40,  right: 40  }
                        : { top: 100, bottom: 180, left: 100, right: 100 };

                    setTimeout(() => {
                        if (!this.isActive || !this._map) return;
                        try {
                            this._map.fitBounds([sw, ne], {
                                padding,
                                duration: 900,
                                maxZoom: 15,
                                easing: (t) => 1 - Math.pow(1 - t, 3),
                            });
                        } catch (e) { /* noop */ }
                    }, 60);
                });
            } catch (e) {
                console.warn('[explore-map] pendingRouteBounds watcher failed', e);
            }

            this.$watch('mobileOpen', v => {
                if (!this.isActive) return;
                document.body.classList.toggle('overflow-hidden', v && window.innerWidth < 1024);
            });

            this.$watch('sidebarOpen', v => {
                if (!this.isActive) return;
                document.cookie = 'map_sidebar_open=' + (v ? '1' : '0') + ';path=/;max-age=31536000;samesite=Lax';
                try { this.$wire.set('sidebarOpen', v, false); } catch (e) { /* noop */ }
                setTimeout(() => {
                    if (!this.isActive) return;
                    try { this.$wire.dispatch('map:resize'); } catch (e) { /* noop */ }
                    this._safeMapResize();
                }, 320);
            });

            this.$watch('followMode', v => {
                if (!this.isActive) return;
                try { this.$wire.set('followMode', v, false); } catch (e) { /* noop */ }
            });

            // `$wire.$watch` is Livewire's JS-side property observer.
            // The previous `this.watch('wire.X', cb)` form threw a
            // TypeError — `watch` is not an Alpine magic and `'wire.X'`
            // is not a valid path on the Alpine data object — which the
            // catch swallowed. Net effect was that neither watcher
            // installed: `enterNavigation()` never fired on
            // `navigationActive` → true and the user marker never
            // re-synced on toggle.
            try {
                this.$wire.$watch('navigationActive', (active) => {
                    if (!this.isActive) return;
                    if (active) {
                        if (this._nav && !this._nav.isActive) {
                            this.enterNavigation();
                        }
                    } else {
                        if (this._nav?.isActive) {
                            this._nav.stop();
                        }
                    }
                    this._syncUserMarker();
                });

                this.$wire.$watch('detailTenantId', () => {
                    if (!this.isActive) return;
                    this.detailMinimized = false;
                });
            } catch (e) {
                console.warn('[explore-map] wire-property watches unavailable', e);
            }
        },

        destroy() {
            this._teardown();

            if (window.__activeMapAppToken === this._token) {
                window.__activeMapAppToken = null;
            }
        },

        _teardown() {
            if (this._tornDown) return;
            this._tornDown = true;

            if (this._bootTimer) { clearTimeout(this._bootTimer); this._bootTimer = null; }
            if (this._vpTimer)   { clearTimeout(this._vpTimer);   this._vpTimer   = null; }

            if (this._resizeTimers) {
                this._resizeTimers.forEach(t => clearTimeout(t));
                this._resizeTimers = null;
            }

            if (this._resizeObserver) {
                try { this._resizeObserver.disconnect(); } catch (e) { /* noop */ }
                this._resizeObserver = null;
            }

            if (this._watchId) {
                try { navigator.geolocation.clearWatch(this._watchId); } catch (e) { /* noop */ }
                this._watchId = null;
            }

            for (const [evt, fn] of this._handlers) {
                try { window.removeEventListener(evt, fn); } catch (e) { /* noop */ }
            }
            this._handlers = [];

            if (this._userMarker) {
                try { this._userMarker.remove(); } catch (e) { /* noop */ }
                this._userMarker = null;
                this._userMarkerEl = null;
            }

            // Livewire's $wire.$watch() returns a cleanup function. Call
            // every one so a wire:navigate away from the map page does
            // not leak watchers that close over this scope. The guards
            // make this a no-op if the return shape ever changes.
            if (typeof this._routeListener === 'function') {
                try { this._routeListener(); } catch (e) { /* noop */ }
            }
            if (typeof this._boundsListener === 'function') {
                try { this._boundsListener(); } catch (e) { /* noop */ }
            }
            for (const off of this._userWatchers) {
                if (typeof off === 'function') {
                    try { off(); } catch (e) { /* noop */ }
                }
            }
            this._userWatchers = [];
            this._routeListener = null;
            this._boundsListener = null;

            if (this._nav) {
                try { this._nav.stop();   } catch (e) { /* noop */ }
                try { this._nav.detach(); } catch (e) { /* noop */ }
                this._nav = null;
            }

            if (window.__mapCanvasPoll) window.__mapCanvasPoll.stop();

            console.log('[explore-map] Scope torn down');
        },

        _initNavigationModule() {
            const NavClass = window.RealtimeNavigation;

            if (!NavClass) {
                console.warn('[explore-map] RealtimeNavigation module not loaded. Run `npm run build` and hard-refresh.');
                return;
            }

            this._nav = new NavClass({
                cameraIntervalMs:     700,
                headingZoomThreshold: 15,
                targetZoom:           16,
                targetPitch:          50,
            });

            this._nav.addEventListener('state', (e) => {
                if (!this.isActive) return;
                const store = Alpine.store('nav');
                if (!store) return;
                store.active          = e.detail.active;
                store.heading         = e.detail.heading;
                store.mapBearing      = e.detail.mapBearing;
                store.following       = e.detail.following;
                store.remainingMeters = e.detail.remainingMeters;
            });

            this._nav.addEventListener('stop', (e) => {
                if (!this.isActive) return;
                const pos = e.detail;
                this.$wire.stopNavigation(pos?.lat ?? null, pos?.lng ?? null);
            });

            this._nav.addEventListener('error', (e) => {
                if (!this.isActive) return;
                this.$wire.locationFailed(e.detail.reason);
            });
        },

        _tryAttachMap() {
            if (!this.isActive) return;

            this._attachTries++;

            const map = window.__lastMapInstance || this._discoverMap();

            if (map) {
                this._map = map;
                this._nav?.attach(map);

                this._clearUserMarker();

                console.log('[explore-map] Map attached via discovery (attempt ' + this._attachTries + ')');
                if (window.__mapCanvasPoll) window.__mapCanvasPoll.stop();
                this.mapLoading = false;
                this.mapStuck   = false;

                this._installResizeObserver();
                this._burstResize();

                try {
                    const existing = Array.isArray(this.$wire.routePolyline) ? this.$wire.routePolyline : [];
                    this._updateRouteLayer(existing);
                    this._routeHadData = existing.length >= 2;
                    if (existing.length >= 2) {
                        setTimeout(() => {
                            if (!this.isActive) return;
                            this._fitRouteBounds(existing);
                        }, 120);
                    }
                } catch (err) {
                    console.warn('[explore-map] route hydrate failed', err);
                }

                this._installUserMarkerWatchers();
                this._syncUserMarker();
                return;
            }

            if (this._attachTries < 40) {
                setTimeout(() => this._tryAttachMap(), 250);
                return;
            }

            console.warn('[explore-map] Map discovery failed after 40 attempts. Diagnostics:');

            const c = document.getElementById('tourist-map');
            if (!c) { console.warn('  #tourist-map not in DOM'); return; }

            console.group('[explore-map] Diagnostics');
            console.log('window.__lastMapInstance:', window.__lastMapInstance);
            console.log('Container element:', c);
            console.groupEnd();
        },

        _discoverMap() {
            const container = document.getElementById('tourist-map');
            if (!container) return null;

            const isMapLibre = (o) =>
                o && typeof o === 'object'
                    && typeof o.getCenter === 'function'
                    && typeof o.easeTo     === 'function'
                    && typeof o.getBearing === 'function'
                    && typeof o.setCenter  === 'function';

            const searchOneLevel = (obj) => {
                if (!obj || typeof obj !== 'object') return null;
                let names;
                try { names = Object.getOwnPropertyNames(obj); } catch (e) { return null; }
                for (const k of names) {
                    let v;
                    try { v = obj[k]; } catch (e) { continue; }
                    if (isMapLibre(v)) return v;
                }
                return null;
            };

            const searchDeep = (obj) => {
                const hit = searchOneLevel(obj);
                if (hit) return hit;
                let names;
                try { names = Object.getOwnPropertyNames(obj); } catch (e) { return null; }
                for (const k of names) {
                    let v;
                    try { v = obj[k]; } catch (e) { continue; }
                    if (v && typeof v === 'object' && !Array.isArray(v)) {
                        const inner = searchOneLevel(v);
                        if (inner) return inner;
                    }
                }
                return null;
            };

            const direct = searchDeep(container);
            if (direct) return direct;

            try {
                for (const sym of Object.getOwnPropertySymbols(container)) {
                    const v = container[sym];
                    if (isMapLibre(v)) return v;
                }
            } catch (e) { /* noop */ }

            if (window.Alpine && typeof window.Alpine.$data === 'function') {
                try {
                    const data = window.Alpine.$data(container);
                    if (data) {
                        const raw = (typeof window.Alpine.raw === 'function') ? window.Alpine.raw(data) : data;
                        const hit = searchDeep(raw) || searchDeep(data);
                        if (hit) return hit;
                    }
                } catch (e) { /* noop */ }
            }

            if (container._x_dataStack && Array.isArray(container._x_dataStack)) {
                for (const scope of container._x_dataStack) {
                    const hit = searchDeep(scope);
                    if (hit) return hit;
                }
            }

            if (container._x_refs && typeof container._x_refs === 'object') {
                for (const k of Object.getOwnPropertyNames(container._x_refs)) {
                    const ref = container._x_refs[k];
                    if (isMapLibre(ref)) return ref;
                    const hit = searchDeep(ref);
                    if (hit) return hit;
                }
            }

            let el = container.parentElement;
            for (let depth = 0; depth < 8 && el; depth++, el = el.parentElement) {
                const hit = searchDeep(el);
                if (hit) return hit;

                if (el._x_dataStack && Array.isArray(el._x_dataStack)) {
                    for (const scope of el._x_dataStack) {
                        const hit2 = searchDeep(scope);
                        if (hit2) return hit2;
                    }
                }
            }

            return null;
        },

        onMapLoaded(map) {
            if (!this.isActive) return;

            console.log('[explore-map] map:load fired with:', map);

            if (!map) {
                map = window.__lastMapInstance || this._discoverMap();
            }

            if (!map) return;

            this._map = map;

            this._clearUserMarker();

            try {
                map.setRenderWorldCopies(false);
                map.setMaxBounds([[115.0, 4.0], [128.0, 22.0]]);
                map.setMinZoom(5);
            } catch (e) { /* noop */ }

            this._nav?.attach(map);

            if (window.__mapCanvasPoll) window.__mapCanvasPoll.stop();
            this.mapLoading = false;
            this.mapStuck   = false;

            this._installResizeObserver();
            this._burstResize();

            this._installUserMarkerWatchers();
            this._syncUserMarker();

            if (this.$wire.navigationActive) {
                this.startNavigation();
            }
        },

        async startNavigation(isRetry = false) {
            if (!this.isActive) return;

            if (!isRetry) this._navRetryCount = 0;

            if (this._navRetryCount >= 3) {
                console.error('[explore-map] Gave up starting navigation after 3 attempts.');
                this.toast('error', 'Navigation unavailable. Please reload the page.');
                this._navRetryCount = 0;
                return;
            }

            if (!this._map) {
                this._map = window.__lastMapInstance || this._discoverMap();
            }
            if (this._map && this._nav && !this._nav.isActive) {
                this._nav.attach(this._map);
            }

            if (!window.RealtimeNavigation) {
                console.error('[explore-map] RealtimeNavigation module not loaded. Run `npm run build`.');
                this.toast('error', 'Navigation module not loaded. Rebuild assets.');
                return;
            }

            const w = this.$wire;
            if (!w.routeCoords || !w.routeCoords.start) {
                console.warn('[explore-map] No route — cannot navigate.');
                if (!isRetry) this.toast('info', 'Start a route first.');
                return;
            }
            if (!Array.isArray(w.routePolyline) || w.routePolyline.length < 2) {
                console.warn('[explore-map] routePolyline empty or missing.');
                if (!isRetry) this.toast('info', 'Route is not ready yet. Please wait a moment.');
                return;
            }
            if (!this._nav) {
                console.error('[explore-map] _nav not initialized.');
                this.toast('error', 'Navigation unavailable — module failed to init.');
                return;
            }

            this._syncUserMarker();
            this.stopFollow();

            const result = await this._nav.start({
                routeCoords:   w.routeCoords,
                routePolyline: w.routePolyline,
                userLat:       w.userLat,
                userLng:       w.userLng,
            });

            if (!this.isActive) return;

            if (result.error) {
                console.error('[explore-map] nav.start() failed:', result.error);

                if (result.error === 'map-not-ready') {
                    this._navRetryCount++;
                    const attempt = this._navRetryCount;
                    console.log('[explore-map] Retry ' + attempt + '/3 scheduled…');
                    setTimeout(() => this.startNavigation(true), 800);
                } else if (result.error === 'no-route') {
                    this.toast('info', 'Route is not ready yet.');
                } else {
                    this.toast('error', 'Could not start navigation.');
                }
                return;
            }

            this._navRetryCount = 0;

            if (!result.compassGranted
                && typeof DeviceOrientationEvent !== 'undefined'
                && typeof DeviceOrientationEvent.requestPermission === 'function') {
                this.toast('info', 'Compass unavailable — cone will follow your movement.');
            }

            this.$wire.startNavigation();
        },

        async enterNavigation() {
            if (!this.isActive) return;

            const w = this.$wire;
            if (!w.routeCoords || !Array.isArray(w.routePolyline)) return;
            if (!this._nav) return;

            const result = await this._nav.start({
                routeCoords:   w.routeCoords,
                routePolyline: w.routePolyline,
                userLat:       w.userLat,
                userLng:       w.userLng,
            });

            if (!this.isActive) return;

            if (!result.error) {
                this.$wire.startNavigation();
            }
        },

        stopNavigation() {
            if (!this.isActive) return;
            this._nav?.stop();
        },

        recenterNavigation() {
            if (!this.isActive) return;
            this._nav?.recenter();
        },

        handleFollowButton() {
            if (!this.isActive) return;

            const w = this.$wire;
            const hasRoute = !!(w.routeCoords && w.routeCoords.start);

            if (w.navigationActive) { this.stopNavigation(); return; }
            if (hasRoute)           { this.startNavigation();  return; }

            this.followMode ? this.stopFollow() : this.startFollow();
        },

        retryMap() {
            if (!this.isActive) return;

            window.__mapApi.setMapLoading(true);

            try {
                this.$wire.forceReloadMap();
            } catch (e) {
                try { this.$wire.refresh(); } catch (e2) { /* noop */ }
            }

            if (window.__mapCanvasPoll) window.__mapCanvasPoll.start();

            this._attachTries = 0;
            this._navRetryCount = 0;
            this._tryAttachMap();
        },

        locate() {
            if (!this.isActive) return;
            if (window.__mapLocateInFlight) return;
            window.__mapLocateInFlight = true;

            window.__mapApi.setLocating(true);

            if (!navigator.geolocation) {
                window.__mapLocateInFlight = false;
                window.__mapApi.setLocating(false);
                window.__mapApi.wire()?.locationFailed('unavailable');
                return;
            }

            if (window.isSecureContext === false) {
                window.__mapLocateInFlight = false;
                window.__mapApi.setLocating(false);
                window.__mapApi.wire()?.locationFailed('insecure');
                return;
            }

            this._nav?.startPassiveHeading();

            window.__mapApi.toast('info', 'Requesting your location… Allow the browser prompt if it appears.');

            let finished = false;

            const finish = (ok, payload) => {
                if (finished) return;
                finished = true;
                window.__mapLocateInFlight = false;
                window.__mapApi.setLocating(false);

                if (!this.isActive) return;

                const w = window.__mapApi.wire();
                if (!w) return;

                if (!ok) { w.locationFailed(payload); return; }

                Promise.resolve(w.setUserLocation(payload.lat, payload.lng))
                    .then(() => {
                        setTimeout(() => {
                            if (!this.isActive) return;
                            try {
                                Promise.resolve(w.loadDrivingDistances()).catch(() => { /* noop */ });
                            } catch (e) { /* noop */ }
                            this._syncUserMarker();
                        }, 50);
                    })
                    .catch(() => { /* noop */ });
            };

            const watchdog = setTimeout(() => finish(false, 'timeout'), 10000);

            navigator.geolocation.getCurrentPosition(
                pos => {
                    clearTimeout(watchdog);
                    finish(true, { lat: pos.coords.latitude, lng: pos.coords.longitude });
                },
                err => {
                    clearTimeout(watchdog);
                    const reason = err.code === 1 ? 'denied'
                                 : err.code === 3 ? 'timeout'
                                 : 'unavailable';
                    finish(false, reason);
                },
                { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
            );
        },

        locateForDirections() {
            if (!this.isActive) return;
            this.locate();
        },

        startFollow() {
            if (!this.isActive) return;
            if (this.$wire.navigationActive) return;
            if (this.followMode || !navigator.geolocation) return;
            if (window.isSecureContext === false) {
                this.$wire.locationFailed('insecure');
                return;
            }

            this.followMode = true;

            let lastSentAt  = 0;
            let lastSentLat = null;
            let lastSentLng = null;

            this._watchId = navigator.geolocation.watchPosition(
                pos => {
                    if (!this.isActive) return;

                    const now = Date.now();
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;

                    const moved = (lastSentLat === null || lastSentLng === null)
                        || (window.RealtimeNavigation
                            ? window.RealtimeNavigation.haversine(lastSentLat, lastSentLng, lat, lng) > 15
                            : true);
                    const elapsed = now - lastSentAt >= 5000;

                    if (!moved && !elapsed) return;

                    lastSentAt  = now;
                    lastSentLat = lat;
                    lastSentLng = lng;

                    this.$wire.setUserLocation(lat, lng);
                },
                () => { if (this.isActive) this.stopFollow(); },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 30000 }
            );
        },

        stopFollow() {
            this.followMode = false;
            if (this._watchId) {
                try { navigator.geolocation.clearWatch(this._watchId); } catch (e) { /* noop */ }
                this._watchId = null;
            }
        },

        debouncedViewport(lat, lng, zoom) {
            if (!this.isActive) return;
            clearTimeout(this._vpTimer);
            this._vpTimer = setTimeout(() => {
                if (!this.isActive) return;
                this.$wire.updateViewport(lat, lng, zoom);
            }, 500);
        },

        onZoomChanged(zoom) {
            if (!this.isActive) return;
            this.debouncedViewport(null, null, zoom);
        },

        async copyText(text) {
            if (!this.isActive) return;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const ta = Object.assign(document.createElement('textarea'), {
                        value: text, style: 'position:fixed;opacity:0;',
                    });
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                this.toast('success', 'Copied to clipboard.');
            } catch { this.toast('error', 'Could not copy link.'); }
        },

        handleKey(e) {
            if (!this.isActive) return;

            const typing = ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName);
            const mod    = e.ctrlKey || e.metaKey || e.altKey;

            if (e.key === 'Escape') {
                if (this.helpOpen) { this.helpOpen = false; return; }
                if (this.$wire.navigationActive) { this.stopNavigation(); return; }
                if (typing) { document.activeElement.blur(); return; }
                if (this.mobileOpen) { this.mobileOpen = false; }
                return;
            }

            if (e.key === '/' && !typing && !this.helpOpen) {
                e.preventDefault();
                document.querySelector('[x-ref="searchInput"]')?.focus();
                return;
            }

            if (typing || mod || this.helpOpen || this.mobileOpen) return;

            switch (e.key.toLowerCase()) {
                case 'l': e.preventDefault(); this.locate(); break;
                case 'f': e.preventDefault(); this.handleFollowButton(); break;
                case 's': e.preventDefault(); this.$wire.toggleSatellite(); break;
                case 'r':
                    e.preventDefault();
                    if (this.$wire.navigationActive) this.recenterNavigation();
                    else this.$wire.resetView();
                    break;
                case '?': e.preventDefault(); this.helpOpen = true; break;
            }
        },

        iconPath(type) {
            switch (type) {
                case 'success': return 'M5 13l4 4L19 7';
                case 'error':   return 'M6 18L18 6M6 6l12 12';
                case 'info':    return 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
                case 'warning': return 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
                default:        return '';
            }
        },

        toast(type, message) {
            if (!this.isActive) return;

            const dur = 4000;
            const id  = Date.now() + Math.random();

            this.toasts.push({ id, type, message, duration: dur, remaining: dur, paused: false });

            const t = this.toasts[this.toasts.length - 1];

            toastTimers.set(t, {
                timeout:  setTimeout(() => this.removeToast(id), dur),
                interval: setInterval(() => {
                    if (!t.paused) t.remaining = Math.max(0, t.remaining - 100);
                }, 100),
            });

            if (this.toasts.length > 4) {
                const oldest = this.toasts[0];
                const timers = toastTimers.get(oldest);
                if (timers) {
                    clearTimeout(timers.timeout);
                    clearInterval(timers.interval);
                    toastTimers.delete(oldest);
                }
                this.toasts.shift();
            }
        },

        pauseToast(t) {
            if (!this.isActive) return;
            t.paused = true;
            const timers = toastTimers.get(t);
            if (timers) clearTimeout(timers.timeout);
        },

        resumeToast(t) {
            if (!this.isActive) return;
            t.paused = false;
            const timers = toastTimers.get(t);
            if (timers) {
                clearTimeout(timers.timeout);
                timers.timeout = setTimeout(() => this.removeToast(t.id), Math.max(t.remaining, 300));
            }
        },

        removeToast(id) {
            const idx = this.toasts.findIndex(x => x.id === id);
            if (idx === -1) return;

            const t = this.toasts[idx];
            const timers = toastTimers.get(t);
            if (timers) {
                clearTimeout(timers.timeout);
                clearInterval(timers.interval);
                toastTimers.delete(t);
            }

            this.toasts.splice(idx, 1);
        },
    };
};

window.dateSelector = function () {
    return {
        checkIn: '',
        checkOut: '',
        bookedDates: [],
        today: '',
        maxDate: '',
        firstAvailable: '',
        calendarStatus: {},
        durationLimits: { min: 1, max: null },
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear(),
        error: '',

        durationLabel: '',
        hasRange: false,

        get minStayDays() {
            const m = Number(this.durationLimits?.min);
            return Number.isFinite(m) && m >= 1 ? Math.floor(m) : 1;
        },

        get maxStayDays() {
            const m = this.durationLimits?.max;
            if (m === null || m === undefined) return null;
            const n = Number(m);
            if (!Number.isFinite(n) || n < 1) return null;
            return Math.max(this.minStayDays, Math.floor(n));
        },

        _addDays(dateStr, n) {
            if (!dateStr) return '';
            const d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d.getTime())) return '';
            d.setDate(d.getDate() + n);
            return this.toIso(d);
        },

        init() {
            const raw = this.$el.dataset.dateData;
            if (!raw) return;

            let data;
            try { data = JSON.parse(raw); } catch (e) {
                console.warn('[dateSelector] failed to parse dataset', e);
                return;
            }

            this.checkIn        = data.checkIn  || '';
            this.checkOut       = data.checkOut || '';
            this.bookedDates    = Array.isArray(data.bookedDates) ? data.bookedDates : [];
            this.today          = data.today    || '';
            this.maxDate        = data.maxDate  || '';
            this.firstAvailable = data.firstAvailable || this.today;

            this.calendarStatus = (data.calendarStatus && typeof data.calendarStatus === 'object')
                ? data.calendarStatus
                : {};

            if (data.durationLimits && typeof data.durationLimits === 'object') {
                this.durationLimits = {
                    min: Number(data.durationLimits.min) || 1,
                    max: data.durationLimits.max === null || data.durationLimits.max === undefined
                        ? null
                        : Number(data.durationLimits.max),
                };
            }

            const anchor = this.checkIn || this.firstAvailable || this.today;
            if (anchor) {
                const d = new Date(anchor + 'T00:00:00');
                if (! isNaN(d.getTime())) {
                    this.currentMonth = d.getMonth();
                    this.currentYear  = d.getFullYear();
                }
            }

            this._recomputeDerived();

            this.$watch('checkIn',  () => this._recomputeDerived());
            this.$watch('checkOut', () => this._recomputeDerived());

            try {
                this.$wire.$watch('check_in', (val) => {
                    if (typeof val === 'string' && val !== this.checkIn) this.checkIn = val;
                });
                this.$wire.$watch('check_out', (val) => {
                    if (typeof val === 'string' && val !== this.checkOut) this.checkOut = val;
                });
            } catch (e) { /* noop */ }
        },

        _recomputeDerived() {
            const hasIn  = this.checkIn !== '';
            const hasOut = this.checkOut !== '';

            this.hasRange = hasIn && hasOut;

            if (! this.hasRange) {
                this.durationLabel = '';
                return;
            }

            const start = new Date(this.checkIn  + 'T00:00:00');
            const end   = new Date(this.checkOut + 'T00:00:00');

            if (isNaN(start.getTime()) || isNaN(end.getTime())) {
                this.durationLabel = '';
                return;
            }

            const days = Math.max(1, Math.round((end - start) / 86400000) + 1);
            this.durationLabel = days === 1 ? '1 day' : days + ' days';
        },

        formatDate(dateStr, opts) {
            if (! dateStr) return '';
            const d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d.getTime())) return '';
            const o = opts || { month: 'short', day: 'numeric', year: 'numeric' };
            return d.toLocaleDateString('en-US', o);
        },

        formatDateShort(dateStr) {
            return this.formatDate(dateStr, { month: 'short', day: 'numeric' });
        },

        isBooked(dateStr) {
            const s = this.calendarStatus?.[dateStr];
            if (s && typeof s === 'object') return !!s.fully_blocked;
            return this.bookedDates.includes(dateStr);
        },

        isPartial(dateStr) {
            const s = this.calendarStatus?.[dateStr];
            return !!(s && !s.fully_blocked && s.has_booking);
        },

        isBeyondStayLimit(dateStr) {
            if (!this.checkIn) return false;
            if (dateStr < this.checkIn) return false;

            const max = this.maxStayDays;
            if (max === null) return false;

            const cap = this._addDays(this.checkIn, max - 1);
            return dateStr > cap;
        },

        isPast(dateStr)      { return dateStr < this.today; },
        isBeyondMax(dateStr) { return dateStr > this.maxDate; },
        isDisabled(dateStr)  { return this.isPast(dateStr) || this.isBeyondMax(dateStr); },

        isInRange(dateStr) {
            if (! this.checkIn || ! this.checkOut) return false;
            if (this.checkIn === this.checkOut)    return false;
            return dateStr > this.checkIn && dateStr < this.checkOut;
        },

        get daysInMonth() {
            const days = [];
            const total = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
            for (let day = 1; day <= total; day++) {
                const d = new Date(this.currentYear, this.currentMonth, day);
                const iso = this.toIso(d);
                days.push({
                    iso,
                    dayNumber: day,
                    isBooked: this.isBooked(iso),
                    isPartial: this.isPartial(iso),
                    isDisabled: this.isDisabled(iso),
                    isBeyondStayLimit: this.isBeyondStayLimit(iso),
                });
            }
            return days;
        },

        get firstDayOffset() {
            return new Date(this.currentYear, this.currentMonth, 1).getDay();
        },

        get currentMonthName() {
            return new Date(this.currentYear, this.currentMonth)
                .toLocaleDateString('en-US', { month: 'long' });
        },

        get canGoPrevMonth() {
            const curYM   = this.currentYear * 12 + this.currentMonth;
            const today   = new Date(this.today + 'T00:00:00');
            if (isNaN(today.getTime())) return true;
            const todayYM = today.getFullYear() * 12 + today.getMonth();
            return curYM > todayYM;
        },

        get canGoNextMonth() {
            const curYM = this.currentYear * 12 + this.currentMonth;
            const max   = new Date(this.maxDate + 'T00:00:00');
            if (isNaN(max.getTime())) return true;
            const maxYM = max.getFullYear() * 12 + max.getMonth();
            return curYM < maxYM;
        },

        prevMonth() {
            if (! this.canGoPrevMonth) return;
            this.currentMonth--;
            if (this.currentMonth < 0) { this.currentMonth = 11; this.currentYear--; }
        },

        nextMonth() {
            if (! this.canGoNextMonth) return;
            this.currentMonth++;
            if (this.currentMonth > 11) { this.currentMonth = 0; this.currentYear++; }
        },

        toIso(d) {
            return d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
        },

        isRangeFree(startIso, endIso) {
            const start = new Date(startIso + 'T00:00:00');
            const end   = new Date(endIso + 'T00:00:00');
            if (isNaN(start.getTime()) || isNaN(end.getTime())) return false;

            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const iso = this.toIso(d);
                if (this.isPast(iso) || this.isBeyondMax(iso)) return false;
                if (this.isBooked(iso)) return false;
            }
            return true;
        },

        quickSelect(kind) {
            const base = new Date(this.today + 'T00:00:00');
            if (isNaN(base.getTime())) return;

            const min = this.minStayDays;
            const max = this.maxStayDays;

            const clampSpan = (span) => {
                let s = Math.max(min, span);
                if (max !== null) s = Math.min(max, s);
                return s;
            };

            let start = new Date(base);
            let span;

            switch (kind) {
                case 'today':      span = clampSpan(1); break;
                case 'tomorrow':   start.setDate(base.getDate() + 1); span = clampSpan(1); break;
                case 'three-days': span = clampSpan(3); break;
                case 'weekend': {
                    const dow = base.getDay();
                    let daysToSat;
                    if (dow === 6)      daysToSat = 0;
                    else if (dow === 0) daysToSat = 6;
                    else                daysToSat = 6 - dow;
                    start.setDate(base.getDate() + daysToSat);
                    span = clampSpan(2);
                    break;
                }
                default: return;
            }

            let end = new Date(start);
            end.setDate(end.getDate() + span - 1);

            const s = this.toIso(start);
            const e = this.toIso(end);

            if (! this.isRangeFree(s, e)) {
                for (let attempt = 0; attempt < 14; attempt++) {
                    start.setDate(start.getDate() + 1);
                    end.setDate(end.getDate() + 1);
                    const s2 = this.toIso(start);
                    const e2 = this.toIso(end);
                    if (this.isRangeFree(s2, e2)) {
                        this.checkIn  = s2;
                        this.checkOut = e2;
                        this.error = '';
                        this.currentMonth = start.getMonth();
                        this.currentYear  = start.getFullYear();
                        try { this.$wire.setDates(s2, e2); } catch (err) { /* noop */ }
                        return;
                    }
                }
                this.error = 'That range includes unavailable dates. Please pick another.';
                return;
            }

            this.checkIn  = s;
            this.checkOut = e;
            this.error = '';
            this.currentMonth = start.getMonth();
            this.currentYear  = start.getFullYear();

            try { this.$wire.setDates(s, e); } catch (err) { /* noop */ }
        },

        selectDate(dateStr) {
            if (this.isBooked(dateStr) || this.isPast(dateStr) || this.isBeyondMax(dateStr)) {
                return;
            }

            const min = this.minStayDays;

            const freshRangeEnd = this.checkIn ? this._addDays(this.checkIn, min - 1) : '';
            const isFreshMinRange = this.checkIn !== '' && this.checkOut === freshRangeEnd;

            if (this.checkIn === '' || ! isFreshMinRange || dateStr <= this.checkIn) {
                this.checkIn  = dateStr;
                this.checkOut = this._addDays(dateStr, min - 1);
                this.error    = '';
                try { this.$wire.setDates(this.checkIn, this.checkOut); } catch (err) { /* noop */ }
                return;
            }

            if (this.isBeyondStayLimit(dateStr)) {
                const max = this.maxStayDays;
                this.error = `Maximum stay for this property is ${max} ${max === 1 ? 'day' : 'days'}.`;
                return;
            }

            if (! this.isRangeFree(this.checkIn, dateStr)) {
                this.checkIn  = dateStr;
                this.checkOut = this._addDays(dateStr, min - 1);
                this.error    = '';
            } else {
                this.checkOut = dateStr;
                this.error    = '';
            }

            try { this.$wire.setDates(this.checkIn, this.checkOut); } catch (err) { /* noop */ }
        },

        clearSelection() {
            const target = this.firstAvailable || this.today;
            const min    = this.minStayDays;

            this.checkIn  = target;
            this.checkOut = this._addDays(target, min - 1);
            this.error    = '';
            try { this.$wire.setDates(this.checkIn, this.checkOut); } catch (e) { /* noop */ }
        },
    };
};

window.homepageEditor = function () {
    return {
        preview: {},
        filePreviews: {
            heroBackgroundImage: null,
            heroSideImage1:      null,
            heroSideImage2:      null,
            heroSideImage3:      null,
            heroSideImage4:      null,
            ctaBackgroundImage:  null,
        },
        draggingKey: null,

        _resetHandler: null,

        init() {
            try {
                this.preview = JSON.parse(this.$el.dataset.preview || '{}');
            } catch (e) {
                this.preview = {};
            }

            this._resetHandler = () => {
                this.filePreviews = {
                    heroBackgroundImage: null,
                    heroSideImage1:      null,
                    heroSideImage2:      null,
                    heroSideImage3:      null,
                    heroSideImage4:      null,
                    ctaBackgroundImage:  null,
                };
            };
            window.addEventListener('preview-reset', this._resetHandler);
        },

        destroy() {
            if (this._resetHandler) {
                window.removeEventListener('preview-reset', this._resetHandler);
                this._resetHandler = null;
            }
        },

        handleDrop(event, key) {
            this.draggingKey = null;
            const input = this.$refs['file-' + key];
            if (!input || !event.dataTransfer.files.length) return;
            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        previewFile(event, key) {
            const file = event.target.files?.[0];
            this.filePreviews[key] = file ? URL.createObjectURL(file) : null;
        },

        bindField(event, field) {
            this.preview[field] = event.target.value;
        },
    };
};

window.aboutEditor = function () {
    return {
        preview: {},
        filePreviews: {
            heroImage:          null,
            storyImage1:        null,
            storyImage2:        null,
            storyImage3:        null,
            highlight1Image:    null,
            highlight2Image:    null,
            highlight3Image:    null,
            ctaBackgroundImage: null,
        },
        draggingKey: null,

        _resetHandler: null,

        init() {
            try {
                this.preview = JSON.parse(this.$el.dataset.preview || '{}');
            } catch (e) {
                this.preview = {};
            }

            this._resetHandler = () => {
                this.filePreviews = {
                    heroImage:          null,
                    storyImage1:        null,
                    storyImage2:        null,
                    storyImage3:        null,
                    highlight1Image:    null,
                    highlight2Image:    null,
                    highlight3Image:    null,
                    ctaBackgroundImage: null,
                };
            };
            window.addEventListener('preview-reset', this._resetHandler);
        },

        destroy() {
            if (this._resetHandler) {
                window.removeEventListener('preview-reset', this._resetHandler);
                this._resetHandler = null;
            }
        },

        handleDrop(event, key) {
            this.draggingKey = null;
            const input = this.$refs['file-' + key];
            if (!input || !event.dataTransfer.files.length) return;
            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        previewFile(event, key) {
            const file = event.target.files?.[0];
            this.filePreviews[key] = file ? URL.createObjectURL(file) : null;
        },

        clearFile(key) {
            this.filePreviews[key] = null;
            try { this.$wire.set(key, null); } catch (e) { /* noop */ }
        },

        bindField(event, field) {
            this.preview[field] = event.target.value;
        },
    };
};

window.revealOnScroll = function () {
    return {
        _observer: null,
        _mutationObserver: null,
        _prepped: new WeakSet(),

        init() {
            const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (prefersReduced || !('IntersectionObserver' in window)) {
                return;
            }

            this._observer = new IntersectionObserver((entries, obs) => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    const el = entry.target;
                    el.classList.add('is-visible');
                    obs.unobserve(el);
                    setTimeout(() => el.classList.add('is-revealed'), 1200);
                }
            }, {
                rootMargin: '0px 0px -10% 0px',
                threshold: 0,
            });

            this._observeAll();

            if ('MutationObserver' in window) {
                this._mutationObserver = new MutationObserver((mutations) => {
                    for (const m of mutations) {
                        for (const node of m.addedNodes) {
                            if (node.nodeType !== 1) continue;
                            if (node.matches?.('[data-reveal]')) this._prep(node);
                            node.querySelectorAll?.('[data-reveal]').forEach(el => this._prep(el));
                        }
                    }
                });
                this._mutationObserver.observe(this.$el, { childList: true, subtree: true });
            }
        },

        _observeAll() {
            this.$el.querySelectorAll('[data-reveal]').forEach(el => this._prep(el));
        },

        _prep(el) {
            if (this._prepped.has(el)) return;
            this._prepped.add(el);

            const rect = el.getBoundingClientRect();
            const inViewport = rect.top < window.innerHeight && rect.bottom > 0;

            if (inViewport) {
                el.classList.add('is-visible', 'is-revealed');
                return;
            }

            el.classList.add('js-reveal-pending');
            this._observer.observe(el);
        },

        destroy() {
            if (this._observer) {
                this._observer.disconnect();
                this._observer = null;
            }
            if (this._mutationObserver) {
                this._mutationObserver.disconnect();
                this._mutationObserver = null;
            }
        },
    };
};