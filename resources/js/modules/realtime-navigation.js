/**
 * Real-time GPS navigation module.
 *
 * ── Design contract ─────────────────────────────────────────────
 * Framework-agnostic. Extends the platform `EventTarget` class and
 * communicates outward ONLY through CustomEvents. Zero dependencies
 * on Alpine, Livewire, jQuery, or any other library.
 *
 * ── Events dispatched ───────────────────────────────────────────
 *   'state'    { active, heading, mapBearing, following, remainingMeters }
 *   'position' { lat, lng }
 *   'start'    { compassGranted }
 *   'stop'     { lat, lng } | null
 *   'error'    { reason: 'denied' | 'timeout' | 'unavailable' }
 *
 * ── Public API ──────────────────────────────────────────────────
 *   attach(map)                     bind to a MapLibre instance
 *   detach()                        release the map, stop navigation
 *   start({ routeCoords, ... })     begin navigation
 *   stop()                          end navigation
 *   recenter()                      re-lock camera on the user
 *   startPassiveHeading()           heading for the persistent marker
 *   stopPassiveHeading()
 *
 *   get isActive / isFollowing / remainingMeters / lastPosition
 *   static haversine(lat1, lng1, lat2, lng2)
 */

/**
 * Time constant (ms) for the heading exponential-moving-average.
 * Small enough to feel responsive at walking speed; large enough to
 * suppress the 1–5° per-sample jitter of a phone's magnetometer.
 */
const HEADING_SMOOTHING_TAU_MS = 150;

/**
 * iOS `webkitCompassAccuracy` values above this are dropped.
 * On a phone held flat, accuracy hovers around 15–25; anything
 * above 25 is the sensor fighting nearby magnetic fields, and the
 * reported heading can swing wildly for a single sample.
 */
const COMPASS_ACCURACY_CEILING = 25;

/**
 * Navigation marker HTML.
 *
 * Visual contract:
 *   • A solid blue dot (the puck) at the marker's geometric center.
 *   • A cone extending upward from the dot — facing indicator.
 *   • The pulse ring is disabled in CSS during navigation.
 */
const NAV_MARKER_HTML = `
    <div class="tourist-nav-pulse"></div>
    <div class="tourist-nav-cone-wrap">
        <div class="tourist-nav-cone"></div>
    </div>
    <div class="tourist-nav-puck"></div>
`;


export class RealtimeNavigation extends EventTarget {

    /**
     * @param {Object}  [options]
     * @param {number}  [options.cameraIntervalMs=700]      Min ms between camera easeTo
     * @param {number}  [options.headingZoomThreshold=15]   Below this zoom, force north-up
     * @param {number}  [options.targetZoom=16]             Preferred nav zoom
     * @param {number}  [options.targetPitch=50]            Nav pitch when heading-up
     * @param {number}  [options.headingEmitIntervalMs=50]  Throttle store updates for heading
     */
    constructor(options = {}) {
        super();

        this._cfg = {
            cameraIntervalMs:      options.cameraIntervalMs      ?? 700,
            headingZoomThreshold:  options.headingZoomThreshold  ?? 15,
            targetZoom:            options.targetZoom            ?? 16,
            targetPitch:           options.targetPitch           ?? 50,
            headingEmitIntervalMs: options.headingEmitIntervalMs ?? 50,
        };

        // ── Map ────────────────────────────────────────────────
        this._map          = null;
        this._currentZoom  = 12;

        // ── Navigation marker ──────────────────────────────────
        this._navMarker    = null;

        // ── GPS ────────────────────────────────────────────────
        this._navWatchId   = null;
        this._lastPos      = null;
        this._isActive     = false;
        this._following    = true;
        this._lastCameraAt = 0;

        // ── Heading ────────────────────────────────────────────
        this._headingHandler        = null;
        this._deviceHeading         = null;  // Smoothed, [0, 360)
        this._movementHeading       = null;
        this._passiveHeadingEnabled = false;
        this._lastHeadingEmitAt     = 0;

        // Heading smoothing state — see `_smoothHeading`.
        this._smoothedHeading       = null;
        this._lastHeadingSampleAt   = 0;

        // Unwrapped rotation angle applied to the cone. Persists
        // across rotations so CSS always transitions the shortest way.
        this._lastAppliedAngle      = null;

        // ── Route ──────────────────────────────────────────────
        this._routeCoords   = null;
        this._routePolyline = [];
        this._remainingMeters = null;

        // Cached segment index for `_remainingAlongRoute()`.
        this._lastSegIdx = null;

        // Throttle gate for _emitState() on the position hot path.
        this._lastStateEmitAt = 0;
    }


    // ═══════════════════════════════════════════════════════════
    //  Public API
    // ═══════════════════════════════════════════════════════════

    attach(mapInstance) {
        const wasActive = this._isActive;

        if (this._map && this._map !== mapInstance) {
            this._destroyMarker();
        }

        this._map = mapInstance;

        if (mapInstance) {
            this._currentZoom = mapInstance.getZoom();
            this._bindMapEvents();
        }

        if (wasActive && mapInstance) {
            this._createMarker();
            if (this._lastPos) {
                this._followCamera(this._lastPos.lat, this._lastPos.lng, false);
            }
        }

        this._emitState();
    }


    detach() {
        this.stop();
        this._map = null;
    }


    async start({ routeCoords: rc, routePolyline: rp, userLat, userLng }) {
        if (!this._map)                              return { error: 'map-not-ready' };
        if (!Array.isArray(rp) || rp.length < 2)     return { error: 'no-route' };

        this._routeCoords   = rc ?? null;
        this._routePolyline = rp;
        this._lastPos       = { lat: userLat, lng: userLng };

        const compassGranted = await this._requestCompassPermission();
        if (compassGranted) {
            this._passiveHeadingEnabled = true;
        }

        this._isActive        = true;
        this._following       = true;
        this._lastCameraAt    = 0;
        this._movementHeading = null;
        this._deviceHeading   = null;

        // Reset heading-smoothing state. A new session must not
        // inherit a stale baseline from the previous one.
        this._smoothedHeading     = null;
        this._lastHeadingSampleAt = 0;
        this._lastAppliedAngle    = null;

        this._remainingMeters = null;
        this._lastSegIdx      = null;
        this._lastStateEmitAt = 0;

        // Entering navigation zooms past the heading gate — set the
        // cached zoom now so `_followCamera()`'s gate reads the right
        // value on the very first follow tick.
        this._currentZoom = Math.max(this._currentZoom, this._cfg.targetZoom);

        this._createMarker();
        this._ensureHeadingWatchRunning();

        const watchResult = this._startPositionWatch();
        if (watchResult.error) {
            this.stop();
            return { error: watchResult.error };
        }

        this._emitState();
        this._followCamera(userLat, userLng, true);

        this.dispatchEvent(new CustomEvent('start', { detail: { compassGranted } }));

        return { ok: true, compassGranted };
    }


    stop() {
        const wasActive = this._isActive;
        this._isActive = false;

        this._stopPositionWatch();
        this._maybeStopHeadingWatch();
        this._destroyMarker();

        const finalPos = this._lastPos;

        this._lastPos             = null;
        this._routePolyline       = [];
        this._routeCoords         = null;
        this._remainingMeters     = null;
        this._lastSegIdx          = null;
        this._following           = true;

        // Reset heading-smoothing state so the next session starts
        // from a clean slate. Otherwise the cone snaps to a stale
        // angle on the first compass sample of the new session.
        this._smoothedHeading     = null;
        this._lastHeadingSampleAt = 0;
        this._lastAppliedAngle    = null;

        this._emitState();

        if (wasActive) {
            this.dispatchEvent(new CustomEvent('stop', { detail: finalPos }));
        }
    }


    recenter() {
        this._following    = true;
        this._lastCameraAt = 0;

        if (this._lastPos) {
            this._followCamera(this._lastPos.lat, this._lastPos.lng, true);
        }

        this._emitState();
    }


    async startPassiveHeading() {
        this._passiveHeadingEnabled = true;
        await this._requestCompassPermission();
        this._ensureHeadingWatchRunning();
    }


    stopPassiveHeading() {
        this._passiveHeadingEnabled = false;
        this._maybeStopHeadingWatch();
    }


    // ═══════════════════════════════════════════════════════════
    //  Getters
    // ═══════════════════════════════════════════════════════════

    get isActive()        { return this._isActive; }
    get isFollowing()     { return this._following; }
    get remainingMeters() { return this._remainingMeters; }
    get lastPosition()    { return this._lastPos ? { ...this._lastPos } : null; }


    // ═══════════════════════════════════════════════════════════
    //  Static helpers
    // ═══════════════════════════════════════════════════════════

    static haversine(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
                + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
                * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }


    // ═══════════════════════════════════════════════════════════
    //  State emission
    // ═══════════════════════════════════════════════════════════

    _emitState() {
        const heading = (this._deviceHeading !== null)
            ? this._deviceHeading
            : this._movementHeading;

        this.dispatchEvent(new CustomEvent('state', {
            detail: {
                active:          this._isActive,
                heading:         heading,
                mapBearing:      this._map ? this._map.getBearing() : 0,
                following:       this._following,
                remainingMeters: this._remainingMeters,
            },
        }));
    }


    _emitHeadingThrottled() {
        const now = performance.now();
        if (now - this._lastHeadingEmitAt < this._cfg.headingEmitIntervalMs) return;
        this._lastHeadingEmitAt = now;
        this._emitState();
    }


    // ═══════════════════════════════════════════════════════════
    //  Navigation marker
    // ═══════════════════════════════════════════════════════════

    _createMarker() {
        if (this._navMarker || !this._map || typeof maplibregl === 'undefined') return;

        const el = document.createElement('div');
        el.className = 'tourist-nav-marker';
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = NAV_MARKER_HTML;

        const startLng = this._lastPos?.lng ?? this._routeCoords?.start?.[0] ?? 123.07391289720677;
        const startLat = this._lastPos?.lat ?? this._routeCoords?.start?.[1] ?? 10.900736693923502;

        this._navMarker = new maplibregl.Marker({
            element:             el,
            anchor:              'center',
            pitchAlignment:      'viewport',
            rotationAlignment:   'viewport',
        })
            .setLngLat([startLng, startLat])
            .addTo(this._map);
    }


    _destroyMarker() {
        if (!this._navMarker) return;
        try { this._navMarker.remove(); } catch (e) { /* noop */ }
        this._navMarker = null;
    }


    /**
     * Rotate the cone so it points where the device is facing,
     * relative to the current map bearing.
     *
     * ── Why unwrap the angle ──
     *
     * If the raw angle jumps from 358° to 2°, a naive assignment
     * would set `transform: rotate(2deg)`, and the browser would
     * transition the cone from 358° → 2° the LONG way around
     * (−356° of rotation). We instead accumulate an unwrapped
     * angle (`_lastAppliedAngle`) that continues past 360, so
     * 358° → 362° is a +4° transition, which is what the eye
     * expects.
     *
     * ── Why the dot is rotation-invariant ──
     *
     * The puck is a circle. Only the cone wrapper rotates.
     */
    _applyRotation() {
        const heading = (this._deviceHeading !== null)
            ? this._deviceHeading
            : this._movementHeading;

        if (!this._navMarker) {
            this._emitHeadingThrottled();
            return;
        }

        const mapBearing = this._map ? this._map.getBearing() : 0;
        const h          = heading ?? 0;

        // Target: heading relative to map bearing, wrapped to [0, 360).
        let target = (h - mapBearing) % 360;
        if (target < 0) target += 360;

        // Unwrap: pick the signed delta closest to 0 between the
        // last applied angle (mod 360) and the target.
        let applied;
        if (this._lastAppliedAngle === null) {
            applied = target;
        } else {
            const lastMod = ((this._lastAppliedAngle % 360) + 360) % 360;
            let delta = target - lastMod;
            if (delta > 180)  delta -= 360;
            if (delta < -180) delta += 360;
            applied = this._lastAppliedAngle + delta;
        }
        this._lastAppliedAngle = applied;

        const el   = this._navMarker.getElement();
        const wrap = el.querySelector('.tourist-nav-cone-wrap');

        if (wrap) wrap.style.transform = `rotate(${applied}deg)`;

        this._emitHeadingThrottled();
    }


    // ═══════════════════════════════════════════════════════════
    //  Camera follow
    // ═══════════════════════════════════════════════════════════

    _followCamera(lat, lng, animate) {
        if (!this._map) return;

        const heading      = (this._deviceHeading !== null) ? this._deviceHeading : (this._movementHeading ?? 0);
        const applyHeading = this._currentZoom >= this._cfg.headingZoomThreshold;

        // Match the GPS follow interval (cameraIntervalMs = 700). At
        // 500 ms the camera arrived early and sat still for 200 ms
        // before the next tick, which read as stutter. 700 ms is seamless.
        const opts = {
            center:    [lng, lat],
            essential: true,
            duration:  animate ? 700 : 0,
            easing:    (t) => 1 - Math.pow(1 - t, 3), // easeOutCubic
        };

        if (applyHeading) {
            opts.bearing = heading;
            opts.pitch   = this._cfg.targetPitch;
        } else {
            opts.bearing = 0;
            opts.pitch   = 0;
        }

        if (this._map.getZoom() < this._cfg.targetZoom) {
            opts.zoom = this._cfg.targetZoom;
        }

        try { this._map.easeTo(opts); } catch (e) { /* noop */ }
    }


    // ═══════════════════════════════════════════════════════════
    //  GPS position watch
    // ═══════════════════════════════════════════════════════════

    _startPositionWatch() {
        if (!navigator.geolocation) return { error: 'unavailable' };
        if (this._navWatchId !== null) return { ok: true };

        // `maximumAge: 1000` lets the GPS chip return a fix up to one
        // second old. `0` forced a fresh acquisition on every tick,
        // which burns battery and produces no visible accuracy gain.
        this._navWatchId = navigator.geolocation.watchPosition(
            (pos) => this._onPosition(pos),
            (err) => this._onPositionError(err),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 1000 }
        );

        return { ok: true };
    }


    _stopPositionWatch() {
        if (this._navWatchId === null) return;
        navigator.geolocation.clearWatch(this._navWatchId);
        this._navWatchId = null;
    }


    _onPosition(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;

        let newMovementHeading = null;

        if (typeof pos.coords.heading === 'number'
            && !isNaN(pos.coords.heading)
            && pos.coords.heading >= 0) {
            newMovementHeading = pos.coords.heading;
        } else if (this._lastPos) {
            const dLat = lat - this._lastPos.lat;
            const dLng = lng - this._lastPos.lng;
            const meters = Math.hypot(dLat, dLng) * 111000;
            if (meters > 3) {
                newMovementHeading = ((Math.atan2(dLng, dLat) * 180) / Math.PI + 360) % 360;
            }
        }

        this._lastPos = { lat, lng };
        if (newMovementHeading !== null) {
            this._movementHeading = newMovementHeading;
        }

        if (this._navMarker) this._navMarker.setLngLat([lng, lat]);

        this._applyRotation();

        this._remainingMeters = this._remainingAlongRoute(lat, lng);

        this.dispatchEvent(new CustomEvent('position', { detail: { lat, lng } }));

        // Throttle the store-state emission to ~10 Hz. The nav top and
        // bottom bars re-render from the Alpine store on every emission;
        // firing it on every GPS tick caused visible hitching.
        const now = performance.now();
        if (now - this._lastStateEmitAt >= 100) {
            this._lastStateEmitAt = now;
            this._emitState();
        }

        if (this._following) {
            const now = Date.now();
            if (now - this._lastCameraAt >= this._cfg.cameraIntervalMs) {
                this._lastCameraAt = now;
                this._followCamera(lat, lng, true);
            }
        }
    }


    _onPositionError(err) {
        const reason = err.code === 1 ? 'denied'
                     : err.code === 3 ? 'timeout'
                     : 'unavailable';
        this.dispatchEvent(new CustomEvent('error', { detail: { reason } }));
    }


    // ═══════════════════════════════════════════════════════════
    //  Device heading (compass)
    // ═══════════════════════════════════════════════════════════

    async _requestCompassPermission() {
        if (typeof DeviceOrientationEvent === 'undefined') return false;

        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            try {
                const result = await DeviceOrientationEvent.requestPermission();
                return result === 'granted';
            } catch (e) {
                return false;
            }
        }

        return true;
    }


    _ensureHeadingWatchRunning() {
        if (this._headingHandler) return;
        if (!this._isActive && !this._passiveHeadingEnabled) return;
        if (typeof DeviceOrientationEvent === 'undefined') return;

        this._headingHandler = (event) => {
            // ── Extract a heading in degrees, or bail ──
            let raw = null;

            if (typeof event.webkitCompassHeading === 'number'
                && !isNaN(event.webkitCompassHeading)
                && event.webkitCompassHeading >= 0) {
                // iOS Safari path. Also exposes webkitCompassAccuracy —
                // reject samples the sensor itself flags as unreliable.
                const accuracy = event.webkitCompassAccuracy;
                if (typeof accuracy === 'number'
                    && !isNaN(accuracy)
                    && accuracy > COMPASS_ACCURACY_CEILING) {
                    return;
                }
                raw = event.webkitCompassHeading;
            } else if (event.absolute
                && typeof event.alpha === 'number'
                && !isNaN(event.alpha)) {
                // Android / Chrome path. `alpha` is counter-clockwise
                // from north, so invert it.
                raw = (360 - event.alpha) % 360;
            }

            if (raw === null) return;

            const now = performance.now();
            const smoothed = this._smoothHeading(raw, now);

            this._deviceHeading = smoothed;
            this._applyRotation();
        };

        window.addEventListener('deviceorientationabsolute', this._headingHandler, true);
        window.addEventListener('deviceorientation', this._headingHandler, true);
    }


    /**
     * Exponential-moving-average on the raw compass heading.
     *
     * ── Why this exists ──
     * A phone's magnetometer reports a heading with 1–5° of noise
     * per sample, at 10–20 Hz. Rotating the cone to the raw value
     * makes it twitch visibly, and reads as "the compass is broken".
     * An EMA with τ ≈ 150 ms smooths the noise while staying
     * responsive at walking / driving speeds.
     *
     * ── Why the angle must be unwrapped before averaging ──
     * Heading lives on a circle. If we averaged 358 and 2 naively
     * we'd get 180 (south) — the arithmetic mean of two nearly-
     * identical directions. The fix: take the shortest signed delta
     * between the previous smoothed value and the new raw sample,
     * scale that delta by the EMA factor, and add it to the previous
     * smoothed value. The result may exceed [0, 360) but that's fine
     * — the caller wraps it.
     *
     * @param  {number} raw  Raw heading, [0, 360).
     * @param  {number} now  performance.now() at the sample.
     * @return {number}      Smoothed heading, wrapped to [0, 360).
     */
    _smoothHeading(raw, now) {
        if (this._smoothedHeading === null) {
            this._smoothedHeading     = raw;
            this._lastHeadingSampleAt = now;
            return raw;
        }

        // Time-based EMA factor: dt / (tau + dt). Time-based rather
        // than sample-count-based so a burst of samples doesn't
        // out-vote a steady stream.
        const dt = Math.max(1, now - this._lastHeadingSampleAt);
        const k  = dt / (HEADING_SMOOTHING_TAU_MS + dt);

        const prev = this._smoothedHeading;

        // Shortest signed delta from prev → raw.
        let delta = raw - prev;
        while (delta > 180)  delta -= 360;
        while (delta < -180) delta += 360;

        this._smoothedHeading     = prev + delta * k;
        this._lastHeadingSampleAt = now;

        // Return a wrapped value in [0, 360).
        let out = this._smoothedHeading % 360;
        if (out < 0) out += 360;
        return out;
    }


    _maybeStopHeadingWatch() {
        if (this._isActive || this._passiveHeadingEnabled) return;
        if (!this._headingHandler) return;

        window.removeEventListener('deviceorientationabsolute', this._headingHandler, true);
        window.removeEventListener('deviceorientation', this._headingHandler, true);
        this._headingHandler = null;
        this._deviceHeading  = null;
    }


    // ═══════════════════════════════════════════════════════════
    //  Map event bindings
    // ═══════════════════════════════════════════════════════════

    _bindMapEvents() {
        if (!this._map) return;

        this._map.on('dragstart', () => {
            if (!this._isActive) return;
            this._following = false;
            this._emitState();
        });

        this._map.on('zoom', () => {
            this._currentZoom = this._map.getZoom();
        });

        this._map.on('rotate', () => {
            if (this._isActive) this._applyRotation();
            else this._emitState();
        });
    }


    // ═══════════════════════════════════════════════════════════
    //  Remaining distance along the route polyline
    // ═══════════════════════════════════════════════════════════

    /**
     * Remaining distance along the route, measured from the user's
     * projected position on the nearest segment to the route's end.
     *
     * The user always moves forward along the polyline, so we scan a
     * ±WINDOW-segment slice around the last-known segment index instead
     * of the whole array. Falls back to a full scan only when the
     * windowed minimum is >FULL_SCAN_THRESHOLD meters away — which
     * means the user jumped (route change, teleport, fresh start).
     *
     * O(1) per tick on the hot path, O(N) only on cold starts.
     */
    _remainingAlongRoute(lat, lng) {
        const coords = this._routePolyline;
        if (!Array.isArray(coords) || coords.length < 2) return null;

        const total = coords.length - 1;
        const WINDOW = 10;
        const FULL_SCAN_THRESHOLD_M = 500;

        let startIdx = 0;
        let endIdx   = total - 1;

        if (this._lastSegIdx !== null) {
            startIdx = Math.max(0, this._lastSegIdx - WINDOW);
            endIdx   = Math.min(total - 1, this._lastSegIdx + WINDOW);
        }

        let bestSegIdx = startIdx;
        let bestT      = 0;
        let bestDist   = Infinity;

        // ── Windowed scan ──
        for (let i = startIdx; i <= endIdx; i++) {
            const a = coords[i];
            const b = coords[i + 1];
            if (!Array.isArray(a) || !Array.isArray(b)) continue;

            const t = this._projectParam(lat, lng, a[1], a[0], b[1], b[0]);
            const projLat = a[1] + t * (b[1] - a[1]);
            const projLng = a[0] + t * (b[0] - a[0]);
            const dist = RealtimeNavigation.haversine(lat, lng, projLat, projLng);

            if (dist < bestDist) {
                bestDist   = dist;
                bestSegIdx = i;
                bestT      = t;
            }
        }

        // ── Full scan fallback — only when the windowed result is
        //    suspiciously far away (route change, teleport, or the very
        //    first call after start()). ──
        if (bestDist > FULL_SCAN_THRESHOLD_M) {
            bestSegIdx = 0;
            bestT      = 0;
            bestDist   = Infinity;

            for (let i = 0; i < total; i++) {
                const a = coords[i];
                const b = coords[i + 1];
                if (!Array.isArray(a) || !Array.isArray(b)) continue;

                const t = this._projectParam(lat, lng, a[1], a[0], b[1], b[0]);
                const projLat = a[1] + t * (b[1] - a[1]);
                const projLng = a[0] + t * (b[0] - a[0]);
                const dist = RealtimeNavigation.haversine(lat, lng, projLat, projLng);

                if (dist < bestDist) {
                    bestDist   = dist;
                    bestSegIdx = i;
                    bestT      = t;
                }
            }
        }

        this._lastSegIdx = bestSegIdx;

        // ── Remaining = projected-point→end-of-segment + all later segments ──
        const a = coords[bestSegIdx];
        const b = coords[bestSegIdx + 1];
        const projLat = a[1] + bestT * (b[1] - a[1]);
        const projLng = a[0] + bestT * (b[0] - a[0]);

        let remaining = RealtimeNavigation.haversine(projLat, projLng, b[1], b[0]);

        for (let i = bestSegIdx + 1; i < total; i++) {
            const p1 = coords[i];
            const p2 = coords[i + 1];
            remaining += RealtimeNavigation.haversine(p1[1], p1[0], p2[1], p2[0]);
        }

        return remaining;
    }


    _projectParam(pLat, pLng, aLat, aLng, bLat, bLng) {
        const dx = bLng - aLng;
        const dy = bLat - aLat;
        const lenSq = dx * dx + dy * dy;
        if (lenSq === 0) return 0;
        const t = ((pLng - aLng) * dx + (pLat - aLat) * dy) / lenSq;
        return Math.max(0, Math.min(1, t));
    }
}