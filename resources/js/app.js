import './bootstrap';
import 'preline';

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/*
|--------------------------------------------------------------------------
| Chart.js — bundled via Vite
|--------------------------------------------------------------------------
| Used by the tenant analytics page.
|
| Loading it from the Vite bundle instead of a CDN <script> tag means:
|
|   • No dependency on the tenant layout having a @stack('scripts') slot.
|     Any layout, any theme, works.
|   • No CSP issues (no external script source).
|   • No network round-trip on cold load; works offline and on local dev.
|
| `chart.js/auto` registers every controller, scale, and plugin, so any
| <canvas> element works with any chart type without further imports.
| We expose it as `window.Chart` because the analytics SFC's @script
| block reaches for the global.
*/
import Chart from 'chart.js/auto';
window.Chart = Chart;

/*
|--------------------------------------------------------------------------
| Alpine plugins
|--------------------------------------------------------------------------
| Livewire v4 bundles Alpine core but does NOT bundle Collapse. We import
| it here as an ES module value — not via a CDN <script> — and register
| it on `alpine:init`, which fires exactly once when Livewire boots.
*/
import collapse from '@alpinejs/collapse';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(collapse);
});

/*
|--------------------------------------------------------------------------
| MapLibre instance interceptor + Apple-Maps-style camera tuning
|--------------------------------------------------------------------------
| The kwasii/livewire-mapcn package creates its MapLibre instance inside
| an Alpine factory closure and never exposes it. A closure variable
| cannot be reached from outside JavaScript. The only reliable capture
| point is the constructor itself.
|
| This block does two jobs:
|
|   1. CAPTURE — stash the instance on window.__lastMapInstance and
|      dispatch a `maplibre:captured` event.
|
|   2. TUNE — apply the platform's smoothness defaults immediately on
|      construction. These settings are the ones that produce the
|      "Apple Maps feel".
*/
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

/*
|--------------------------------------------------------------------------
| Modular JS — real-time navigation
|--------------------------------------------------------------------------
*/
import { RealtimeNavigation } from './modules/realtime-navigation';
window.RealtimeNavigation = RealtimeNavigation;

/*
|--------------------------------------------------------------------------
| Modular JS — location picker (Alpine factory)
|--------------------------------------------------------------------------
*/
import { locationPicker } from './modules/location-picker';
window.locationPicker = locationPicker;

/*
|--------------------------------------------------------------------------
| Modular JS — image cropper (two coordinated Alpine factories)
|--------------------------------------------------------------------------
*/
import { imageCropper, cropModal, avatarPreview } from './modules/image-cropper';
window.imageCropper   = imageCropper;
window.cropModal      = cropModal;
window.avatarPreview  = avatarPreview;

/*
|==========================================================================
| EXPLORE MAP — page helper + Alpine factory
|==========================================================================
|
| WHY THIS LIVES IN app.js AND NOT IN THE SFC <script> TAG:
|
| Livewire v4 extracts <script> tags from view-based components and
| serves them as separate, async-loaded, cached files. By the time those
| files execute, Alpine has already walked the DOM and evaluated
| `x-data="mapApp()"` — producing "mapApp is not defined".
|
| Vite loads app.js as a module script. Module scripts run after HTML
| parsing completes but BEFORE DOMContentLoaded — which is when Livewire
| boots Alpine. So `window.mapApp` and the helper IIFE below are
| guaranteed to exist before Alpine touches the DOM.
|
| LIVEWIRE MAGIC SCOPE NOTE:
|
| Inside an SFC <script> block, Livewire injects `$wire` as a bare
| identifier. Inside app.js, it does NOT. We use `this.$wire` on the
| Alpine reactive proxy instead.
|
|==========================================================================
*/

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

        _vpTimer:       null,
        _watchId:       null,
        _bootTimer:     null,
        _map:           null,
        _nav:           null,
        _attachTries:   0,
        _navRetryCount: 0,
        _tornDown:      false,
        _handlers:      [],
        _token:         null,

        // ── Imperative route layer state ────────────────────
        _routeHadData:  false,
        _routeListener: null,
        _boundsListener: null,

        // ── Imperative user marker state ────────────────────
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

        /**
         * Rounds a coordinate array for compact GeoJSON.
         */
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

        /**
         * Draw (or update) the active route on the map imperatively.
         */
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

        /**
         * Imperative user-location marker.
         *
         * WHY NOT <x-map-marker>:
         *   The mapcn <x-map-marker> component is created once during
         *   Alpine's initial walk. Livewire's morph engine swaps the
         *   underlying DOM node out on every @if toggle or wire:key
         *   change — but MapLibre's marker instance (which lives inside
         *   MapLibre's own overlay container, outside Livewire's tree)
         *   is not reconciled. Result: the marker exists in the map's
         *   layer container but points at a detached (invisible) DOM
         *   node. The user only sees it again after a full map rebuild
         *   (theme toggle, satellite toggle, Retry).
         *
         * This method draws the marker imperatively instead. It's the
         * same pattern as `_updateRouteLayer` above — nothing Livewire
         * touches, so nothing Livewire can break.
         */
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

            // During navigation, the nav puck replaces this marker.
            if (!hasCoords || navigating) {
                if (this._userMarker) {
                    try { this._userMarker.remove(); } catch (e) { /* noop */ }
                    this._userMarker = null;
                    this._userMarkerEl = null;
                }
                return;
            }

            // Existing marker → just move it.
            if (this._userMarker) {
                try { this._userMarker.setLngLat([lng, lat]); } catch (e) { /* noop */ }
                return;
            }

            // Create a fresh marker element.
            const el = document.createElement('div');
            el.className = 'tourist-user-marker';
            el.setAttribute('role', 'img');
            el.setAttribute('aria-label', 'Your location');
            el.innerHTML =
                '<div class="tourist-user-marker-ring"></div>' +
                '<div class="tourist-user-marker-halo"></div>' +
                '<div class="tourist-user-marker-dot"></div>';

            // Click → share-location link (preserves the popup's old
            // "Share Location" affordance without needing a Popup).
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

        /**
         * Install the watchers that keep the marker in sync with the
         * server-side userLat / userLng / navigationActive state.
         */
        _installUserMarkerWatchers() {
            if (this._userWatchers.length > 0) return;

            try {
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

        /**
         * Compute a bounding box from a route polyline and fly the
         * camera to it with a single, smooth easeOutCubic animation.
         */
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
                if (window.__mapCanvasPoll) window.__mapCanvasPoll.stop();
                this.mapLoading = false;
                this.mapStuck   = false;

                try {
                    map.setRenderWorldCopies(false);
                    map.setMaxBounds([[115.0, 4.0], [128.0, 22.0]]);
                    map.setMinZoom(5);
                } catch (err) { /* noop */ }

                // Route + user marker — both imperative.
                const existing = Array.isArray(this.$wire.routePolyline) ? this.$wire.routePolyline : [];
                this._updateRouteLayer(existing);
                this._routeHadData = existing.length >= 2;

                if (existing.length >= 2) {
                    setTimeout(() => {
                        if (!this.isActive) return;
                        this._fitRouteBounds(existing);
                    }, 120);
                }

                // Install watchers + draw the user marker NOW.
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
            };

            window.addEventListener('maplibre:captured', onCaptured);
            window.addEventListener('resize', onResize);
            this._handlers.push(
                ['maplibre:captured', onCaptured],
                ['resize',            onResize],
            );

            this._tryAttachMap();

            /*
            |──────────────────────────────────────────────────────
            | Imperative route updates — mobile camera fix.
            |──────────────────────────────────────────────────────
            */
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

            /*
            |──────────────────────────────────────────────────────
            | Fallback: server-driven fit-bounds (URL deep-link case)
            |──────────────────────────────────────────────────────
            */
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
                }, 320);
            });

            this.$watch('followMode', v => {
                if (!this.isActive) return;
                try { this.$wire.set('followMode', v, false); } catch (e) { /* noop */ }
            });

            this.$watch('$wire.navigationActive', (active) => {
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

            this.$watch('$wire.detailTenantId', () => {
                if (!this.isActive) return;
                this.detailMinimized = false;
            });
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
            this._userWatchers = [];

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
                console.log('[explore-map] Map attached via discovery (attempt ' + this._attachTries + ')');
                if (window.__mapCanvasPoll) window.__mapCanvasPoll.stop();
                this.mapLoading = false;
                this.mapStuck   = false;

                const existing = Array.isArray(this.$wire.routePolyline) ? this.$wire.routePolyline : [];
                this._updateRouteLayer(existing);
                this._routeHadData = existing.length >= 2;
                if (existing.length >= 2) {
                    setTimeout(() => {
                        if (!this.isActive) return;
                        this._fitRouteBounds(existing);
                    }, 120);
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

            try {
                map.setRenderWorldCopies(false);
                map.setMaxBounds([[115.0, 4.0], [128.0, 22.0]]);
                map.setMinZoom(5);
            } catch (e) { /* noop */ }

            this._nav?.attach(map);

            if (window.__mapCanvasPoll) window.__mapCanvasPoll.stop();
            this.mapLoading = false;
            this.mapStuck   = false;

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
                try { this.$wire.$refresh(); } catch (e2) { /* noop */ }
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
            if (timers) clearTimeout(timers.timeout);
            const timers2 = toastTimers.get(t);
            if (timers2) {
                timers2.timeout = setTimeout(() => this.removeToast(t.id), Math.max(t.remaining, 300));
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

/*
|==========================================================================
| BOOKING — Date selector (Alpine factory)
|==========================================================================
|
| Reads initial state from `data-date-data` on the wrapper element.
|
| Server supplies (via dateSelectorDataJson):
|   checkIn, checkOut, bookedDates, today, maxDate, firstAvailable
|
| `firstAvailable` is the first day within the booking window that
| is not already booked. It's what mount() defaults to so the user
| never lands on a booked day.
|
| `selectDate` state machine:
|   1. If no selection, OR the current start is itself invalid (booked,
|      past, beyond max), OR a completed range is present → the click
|      becomes a fresh single-day selection.
|   2. If a valid single-day selection exists and the click is AFTER it
|      → extend IF free. If the range spans a booked day, restart the
|      selection at the clicked day (no dead-ends — this was the bug
|      that trapped users).
|   3. If the click is BEFORE the current start → fresh selection.
|
| `durationLabel` and `hasRange` are PLAIN properties updated by
| `_recomputeDerived()`, which fires from $watch on the source values.
| (Alpine doesn't reliably track getters inside a Livewire-morphed
| scope — this is a workaround for that.)
|==========================================================================
*/
window.dateSelector = function () {
    return {
        checkIn: '',
        checkOut: '',
        bookedDates: [],
        today: '',
        maxDate: '',
        firstAvailable: '',
        currentMonth: new Date().getMonth(),
        currentYear: new Date().getFullYear(),
        error: '',

        // Derived (plain properties — see header comment).
        durationLabel: '',
        hasRange: false,

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

            // Land on the month containing the start date (or first available)
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
                this.$watch('$wire.check_in', (val) => {
                    if (typeof val === 'string' && val !== this.checkIn) this.checkIn = val;
                });
                this.$watch('$wire.check_out', (val) => {
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

            const days = Math.max(1, Math.round((end - start) / 86400000));
            this.durationLabel = days === 1 ? '1 day' : days + ' days';
        },

        /* ── Formatting ──────────────────────────────── */

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

        /* ── Predicates ──────────────────────────────── */

        isBooked(dateStr)    { return this.bookedDates.includes(dateStr); },
        isPast(dateStr)      { return dateStr < this.today; },
        isBeyondMax(dateStr) { return dateStr > this.maxDate; },
        isDisabled(dateStr)  { return this.isPast(dateStr) || this.isBeyondMax(dateStr); },

        isInRange(dateStr) {
            if (! this.checkIn || ! this.checkOut) return false;
            if (this.checkIn === this.checkOut)    return false;
            return dateStr > this.checkIn && dateStr < this.checkOut;
        },

        /* ── Calendar grid ───────────────────────────── */

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
                    isDisabled: this.isDisabled(iso),
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

        /* ── Navigation bounds ───────────────────────── */

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

        /* ── Helpers ─────────────────────────────────── */

        toIso(d) {
            return d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
        },

        isRangeFree(startIso, endIso) {
            const start = new Date(startIso + 'T00:00:00');
            const end   = new Date(endIso   + 'T00:00:00');
            if (isNaN(start.getTime()) || isNaN(end.getTime())) return false;
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const iso = this.toIso(d);
                if (this.isBooked(iso) || this.isPast(iso) || this.isBeyondMax(iso)) {
                    return false;
                }
            }
            return true;
        },

        /* ── Quick ranges ────────────────────────────── */

        quickSelect(kind) {
            const base = new Date(this.today + 'T00:00:00');
            if (isNaN(base.getTime())) return;

            let start = new Date(base);
            let end   = new Date(base);

            switch (kind) {
                case 'today':
                    break;
                case 'tomorrow':
                    start.setDate(base.getDate() + 1);
                    end.setDate(base.getDate() + 1);
                    break;
                case 'three-days':
                    end.setDate(base.getDate() + 2);
                    break;
                case 'weekend': {
                    const dow = base.getDay();
                    let daysToSat;
                    if (dow === 6)      daysToSat = 7;
                    else if (dow === 0) daysToSat = 6;
                    else                daysToSat = 6 - dow;
                    start.setDate(base.getDate() + daysToSat);
                    end.setDate(base.getDate() + daysToSat + 1);
                    break;
                }
                default: return;
            }

            const s = this.toIso(start);
            const e = this.toIso(end);

            if (! this.isRangeFree(s, e)) {
                // Shift forward one day at a time until a valid range
                // is found. Gives the user a working selection instead
                // of an error they can't act on.
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

        /* ── Selection (2-click range; 3rd click resets) ── */

        selectDate(dateStr) {
            if (this.isBooked(dateStr) || this.isPast(dateStr) || this.isBeyondMax(dateStr)) {
                return;
            }

            // If the current start is itself invalid (booked, past,
            // beyond max), treat this click as a fresh selection.
            // This is what unblocks the user when the server defaulted
            // them onto a day that is already booked.
            const startInvalid = this.checkIn === ''
                || this.isBooked(this.checkIn)
                || this.isPast(this.checkIn)
                || this.isBeyondMax(this.checkIn);

            const hasCompleteRange = ! startInvalid
                && this.checkOut !== ''
                && this.checkOut !== this.checkIn;

            if (startInvalid || hasCompleteRange) {
                // Fresh selection
                this.checkIn  = dateStr;
                this.checkOut = dateStr;
                this.error    = '';
            } else {
                // We have a valid single-day selection; try to extend.
                if (dateStr < this.checkIn) {
                    // Clicked before start → new start
                    this.checkIn  = dateStr;
                    this.checkOut = dateStr;
                    this.error    = '';
                } else {
                    if (! this.isRangeFree(this.checkIn, dateStr)) {
                        // The range spans a booked day. Rather than
                        // dead-ending the user, restart the selection
                        // at the clicked day. They can extend from there.
                        this.checkIn  = dateStr;
                        this.checkOut = dateStr;
                        this.error    = '';
                    } else {
                        this.checkOut = dateStr;
                        this.error    = '';
                    }
                }
            }

            try { this.$wire.setDates(this.checkIn, this.checkOut); } catch (err) { /* noop */ }
        },

        /* ── Reset ───────────────────────────────────── */

        clearSelection() {
            const target = this.firstAvailable || this.today;
            this.checkIn  = target;
            this.checkOut = target;
            this.error    = '';
            try { this.$wire.setDates(target, target); } catch (e) { /* noop */ }
        },
    };
};

/*
|==========================================================================
| HOMEPAGE EDITOR — Alpine factory
|==========================================================================
|
| Used by the superadmin homepage editor SFC at:
|   resources/views/superadmin/pages/homepage/⚡homepage-editor.blade.php
|
| WHY THIS LIVES IN app.js AND NOT IN THE SFC <script> TAG:
|
| Livewire v4 extracts <script> tags from view-based components and
| serves them as separate, async-loaded, cached files. By the time those
| files execute, Alpine has already walked the DOM and evaluated
| `x-data="homepageEditor()"` — producing "homepageEditor is not defined"
| on a cold load.
|
| Vite loads app.js as a module script. Module scripts run after HTML
| parsing completes but BEFORE DOMContentLoaded — which is when Livewire
| boots Alpine. So the factory below is guaranteed to exist in time.
|
| STATE SHAPE:
|   preview      — object mirrored from `data-preview` on the root element.
|                  Keys: heroTitle, heroSubtitle, heroDescription,
|                        discoverTitle, discoverDescription,
|                        ctaEyebrow, ctaTitle, ctaDescription,
|                        ctaButtonText
|   filePreviews — object holding ObjectURL strings for images the user
|                  picked but hasn't saved yet. Null when no pick.
|                  Keys match the Livewire image properties:
|                        heroBackgroundImage, heroSideImage1..4,
|                        ctaBackgroundImage
|   draggingKey  — tracking the currently-hovered drop target's key.
|                  Only used for the visual drag state on the uploader.
|
| NOTE: after a successful save() the SFC redirects (Rule 133), so
| this factory is disposed and re-created on the fresh page load. The
| 'preview-reset' event listener below is defensive only — no current
| code path dispatches it.
|==========================================================================
*/
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

        init() {
            try {
                this.preview = JSON.parse(this.$el.dataset.preview || '{}');
            } catch (e) {
                this.preview = {};
            }

            window.addEventListener('preview-reset', () => {
                this.filePreviews = {
                    heroBackgroundImage: null,
                    heroSideImage1:      null,
                    heroSideImage2:      null,
                    heroSideImage3:      null,
                    heroSideImage4:      null,
                    ctaBackgroundImage:  null,
                };
            });
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

/*
|==========================================================================
| ABOUT EDITOR — Alpine factory
|==========================================================================
|
| Used by the superadmin About page editor SFC at:
|   resources/views/superadmin/pages/homepage/⚡about-editor.blade.php
|
| WHY THIS LIVES IN app.js AND NOT IN THE SFC <script> TAG:
|
| Livewire v4 extracts <script> tags from view-based components and
| serves them as separate, async-loaded, cached files. By the time those
| files execute, Alpine has already walked the DOM and evaluated
| `x-data="aboutEditor()"` — producing "aboutEditor is not defined" on a
| cold load.
|
| Vite loads app.js as a module script. Module scripts run after HTML
| parsing completes but BEFORE DOMContentLoaded — which is when Livewire
| boots Alpine. So the factory below is guaranteed to exist in time.
|
| STATE SHAPE:
|   preview      — object mirrored from `data-preview` on the root element.
|                  Keys: heroSubheading, heroHeading, heroDescription,
|                        storyHeading, storyText1, storyText2,
|                        highlight1Title/Text, highlight2Title/Text,
|                        highlight3Title/Text,
|                        ctaHeading, ctaText
|   filePreviews — object holding ObjectURL strings for images the user
|                  picked but hasn't saved yet. Null when no pick.
|                  Keys match the Livewire image properties:
|                        heroImage, storyImage1..3,
|                        highlight1..3Image, ctaBackgroundImage
|   draggingKey  — tracking the currently-hovered drop target's key.
|                  Only used for the visual drag state on the uploader.
|
| NOTE: after a successful save() the SFC redirects (Rule 133), so this
| factory is disposed and re-created on the fresh page load. The
| 'preview-reset' event listener is defensive only — no current code path
| dispatches it. `clearFile()` IS used by the shared image-uploader
| partial to discard a pending upload from the "Remove" button in the
| preview tile.
|==========================================================================
*/
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

        init() {
            try {
                this.preview = JSON.parse(this.$el.dataset.preview || '{}');
            } catch (e) {
                this.preview = {};
            }

            // Defensive only — save() now redirects (Rule 133), so
            // filePreviews is refreshed by a full re-mount.
            window.addEventListener('preview-reset', () => {
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
            });
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
            // Reset the underlying Livewire property so the pending
            // upload is discarded on save.
            try { this.$wire.set(key, null); } catch (e) { /* noop */ }
        },

        bindField(event, field) {
            this.preview[field] = event.target.value;
        },
    };
};