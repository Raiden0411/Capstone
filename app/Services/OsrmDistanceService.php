<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches driving distances and full route geometries from OSRM.
 *
 * All HTTP happens server-side. The browser never contacts OSRM
 * directly — that's what makes the mobile app light.
 *
 * Two responsibilities:
 *   • drivingDistance(s)  — one-shot distance/duration lookups,
 *                           used to populate the sidebar badges.
 *   • fetchRoute()        — full route polyline, used by the map's
 *                           navigation view. Cached; downsamples
 *                           long routes to keep payloads bounded.
 *
 * ── Profile handling (important) ────────────────────────────────
 *
 * The public OSRM demo server (router.project-osrm.org) runs ONLY the
 * car profile. Requests for `walking` or `cycling` return HTTP 400
 * with a body like `{"code":"InvalidQuery","message":"Profile not
 * found"}`. When that happens, the caller sees null and silently
 * falls back to a straight line — which is exactly the "routing is
 * broken, it draws a straight line" symptom.
 *
 * Since the road geometry is identical for every mode, we ALWAYS
 * request `driving` from OSRM and scale the reported duration for
 * the user's selected profile on read. See adjustForProfile().
 */
class OsrmDistanceService
{
    private const CACHE_TTL_HOURS = 24;
    private const COORD_PRECISION = 4;

    /** One-shot distance lookup timeout (seconds). */
    private const HTTP_TIMEOUT = 3;

    private const MAX_HAVERSINE_KM = 50;

    private const INTER_REQUEST_DELAY_MS = 150;

    private const ATTEMPTS = 2;

    private const RETRY_BACKOFF_MS = [0, 400];

    public const DEFAULT_MAX_TARGETS = 15;

    public const DEFAULT_BUDGET_SECONDS = 3.5;

    /**
     * Route geometries longer than this get downsampled to this many
     * points before being returned. Keeps Livewire snapshots and
     * MapLibre rendering cheap on mobile.
     *
     * Tier 2 (pending confirmation) proposes lowering this to 150 —
     * six- to seven-fold less client-side interpolation work during
     * heading-up navigation, visually indistinguishable at zoom 12–16.
     */
    private const MAX_ROUTE_POINTS = 1000;

    /**
     * Route fetch timeout (seconds). Deliberately generous — the
     * demo OSRM is rate-limited and can take 6–10s under load.
     * The client shows a "Calculating route…" chip during the wait,
     * so the delay is honest and visible, not a mystery hang.
     */
    private const ROUTE_TIMEOUT = 12;

    /** TCP connect timeout for route fetches. */
    private const ROUTE_CONNECT_TIMEOUT = 5;

    /** Identifying UA — the OSRM demo server rejects anonymous scrapers. */
    private const USER_AGENT = 'Capstone-Booking-Platform/1.0 '
        . '(+https://capstone.envkit.net; contact: tourism.management.ph@gmail.com)';

    // ═══════════════════════════════════════════════════════════
    //  Route geometry (server-side OSRM fetch)
    // ═══════════════════════════════════════════════════════════

    /**
     * Fetch the full route polyline + distance + duration.
     *
     * Returns null if OSRM is unavailable. Cached for 24h keyed by
     * rounded start/end coordinates.
     *
     * @return array{
     *     coordinates: array<int, array{0: float, 1: float}>,
     *     distance_km: float,
     *     duration_min: int
     * }|null
     */
    public function fetchRoute(
        float $fromLat, float $fromLng,
        float $toLat,   float $toLng,
        string $profile = 'driving',
    ): ?array {
        if (!in_array($profile, ['driving', 'walking', 'cycling'], true)) {
            $profile = 'driving';
        }

        // Always request the driving profile — the demo OSRM server
        // does not serve walking/cycling. Road geometry is mode-
        // agnostic; only the ETA differs, and that's adjusted below.
        $requestProfile = 'driving';

        $key = sprintf(
            'osrm:route:%s:%s,%s:%s,%s',
            $requestProfile,
            round($fromLat, self::COORD_PRECISION),
            round($fromLng, self::COORD_PRECISION),
            round($toLat,   self::COORD_PRECISION),
            round($toLng,   self::COORD_PRECISION),
        );

        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['coordinates'])) {
            return $this->adjustForProfile($cached, $profile);
        }

        $url = $this->baseUrl()
             . "/route/v1/{$requestProfile}/{$fromLng},{$fromLat};{$toLng},{$toLat}";

        try {
            $response = Http::timeout(self::ROUTE_TIMEOUT)
                ->connectTimeout(self::ROUTE_CONNECT_TIMEOUT)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept'     => 'application/json',
                ])
                // One retry, 800ms backoff. Handles transient network
                // hiccups. The client is showing a spinner — the extra
                // ~12.8s worst case is visible, not silent.
                ->retry(2, 800, throw: false)
                ->get($url, [
                    'overview'     => 'full',
                    'geometries'   => 'geojson',
                    'alternatives' => 'false',
                    'steps'        => 'false',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('OSRM route fetch connection error', [
                'from'  => [$fromLat, $fromLng],
                'to'    => [$toLat, $toLng],
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('OSRM route fetch threw', [
                'from'  => [$fromLat, $fromLng],
                'to'    => [$toLat, $toLng],
                'url'   => $url,
                'error' => $e->getMessage(),
                'type'  => get_class($e),
            ]);
            return null;
        }

        if (! $response->successful()) {
            Log::warning('OSRM route fetch HTTP error', [
                'status' => $response->status(),
                'url'    => $url,
                // First 500 bytes are enough to identify the cause
                // (e.g. InvalidQuery, rate-limit notice, HTML error).
                'body'   => substr($response->body(), 0, 500),
            ]);
            return null;
        }

        $route = $response->json('routes.0');

        if (! is_array($route)
            || ! isset($route['geometry']['coordinates'])
            || ! is_array($route['geometry']['coordinates'])
        ) {
            Log::warning('OSRM route fetch: malformed response', [
                'url'      => $url,
                'top_keys' => is_array($response->json()) ? array_keys($response->json()) : null,
            ]);
            return null;
        }

        $coords = $route['geometry']['coordinates'];

        // ── Downsample if the polyline is very long.
        if (count($coords) > self::MAX_ROUTE_POINTS) {
            $coords = $this->downsample($coords, self::MAX_ROUTE_POINTS);
        }

        // Round coordinates to 5 decimal places (~1.1m precision) —
        // shaves bytes off the JSON without visible loss.
        $coords = array_map(
            fn ($pair) => [
                round((float) $pair[0], 5),
                round((float) $pair[1], 5),
            ],
            $coords
        );

        $result = [
            'coordinates'  => $coords,
            'distance_km'  => round((float) $route['distance'] / 1000, 2),
            'duration_min' => (int) round((float) ($route['duration'] ?? 0) / 60),
        ];

        Cache::put($key, $result, now()->addHours(self::CACHE_TTL_HOURS));

        return $this->adjustForProfile($result, $profile);
    }

    /**
     * Scale a driving route's duration for walking or cycling.
     *
     * Road geometry is mode-agnostic — the polyline is used as-is.
     * Only the reported ETA changes.
     *
     * ── Factors ──
     *
     * These are crude estimates based on average effective speeds
     * in a small city (not the OSRM-reported speed, which already
     * accounts for traffic and lights):
     *
     *   Driving   ≈ 50 km/h effective
     *   Cycling   ≈ 14 km/h
     *   Walking   ≈  4.5 km/h
     *
     *   → walking factor  ≈ 50 / 4.5 ≈ 11   (rounded to 12)
     *   → cycling factor  ≈ 50 / 14  ≈ 3.6  (rounded to 4)
     *
     * Round numbers, deliberately on the conservative side — a
     * tourist is better served by a slightly pessimistic ETA than
     * by one that underestimates a walk.
     */
    private function adjustForProfile(array $route, string $profile): array
    {
        if ($profile === 'driving') {
            return $route;
        }

        $factor = $profile === 'walking' ? 12.0 : 4.0;

        $route['duration_min'] = max(
            1,
            (int) round(($route['duration_min'] ?? 0) * $factor)
        );

        return $route;
    }

    /**
     * Keep every Nth coordinate so the polyline has at most $target
     * points. First and last are always preserved.
     *
     * @param  array<int, array{0: float, 1: float}>  $coords
     * @return array<int, array{0: float, 1: float}>
     */
    private function downsample(array $coords, int $target): array
    {
        $count = count($coords);
        if ($count <= $target) {
            return $coords;
        }

        $step  = (int) ceil($count / $target);
        $out   = [];
        $last  = $count - 1;

        for ($i = 0; $i < $count; $i += $step) {
            $out[] = $coords[$i];
        }

        // Always include the final coordinate
        if ($out[count($out) - 1] !== $coords[$last]) {
            $out[] = $coords[$last];
        }

        return $out;
    }

    // ═══════════════════════════════════════════════════════════
    //  One-shot distance lookups (sidebar badges)
    // ═══════════════════════════════════════════════════════════

    public function drivingDistance(
        float $fromLat, float $fromLng,
        float $toLat,   float $toLng,
    ): ?array {
        if ($this->haversine($fromLat, $fromLng, $toLat, $toLng) > self::MAX_HAVERSINE_KM) {
            return null;
        }

        $key    = $this->cacheKey($fromLat, $fromLng, $toLat, $toLng);
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->fetchOneWithRetry($fromLat, $fromLng, $toLat, $toLng);

        if ($result !== null) {
            Cache::put($key, $result, now()->addHours(self::CACHE_TTL_HOURS));
        }

        return $result;
    }

    /**
     * Bounded sequential lookup for many destinations from one origin.
     *
     * @param  array<string|int, array{0: float, 1: float}>  $targets
     * @return array<string|int, array{distance_km: float, duration_min: int}>
     */
    public function drivingDistancesBatch(
        float $fromLat,
        float $fromLng,
        array $targets,
        int $maxTargets = self::DEFAULT_MAX_TARGETS,
        float $budgetSeconds = self::DEFAULT_BUDGET_SECONDS,
    ): array {
        if (empty($targets)) {
            return [];
        }

        $startedAt = microtime(true);
        $out       = [];
        $pending   = [];

        foreach ($targets as $key => [$lat, $lng]) {
            if ($this->haversine($fromLat, $fromLng, $lat, $lng) > self::MAX_HAVERSINE_KM) {
                continue;
            }

            $cacheKey = $this->cacheKey($fromLat, $fromLng, $lat, $lng);
            $cached   = Cache::get($cacheKey);

            if ($cached !== null) {
                $out[$key] = $cached;
                continue;
            }

            $pending[$key] = ['lat' => $lat, 'lng' => $lng, 'cache_key' => $cacheKey];
        }

        if (empty($pending)) {
            return $out;
        }

        $fetchedCount = 0;
        $first        = true;

        foreach ($pending as $key => $info) {
            if ($fetchedCount >= $maxTargets) {
                Log::info('OSRM batch: max target count reached', [
                    'fetched' => $fetchedCount,
                    'skipped' => count($pending) - $fetchedCount,
                    'elapsed' => round(microtime(true) - $startedAt, 2),
                ]);
                break;
            }

            $elapsed = microtime(true) - $startedAt;
            if ($elapsed >= $budgetSeconds) {
                Log::info('OSRM batch: time budget exhausted', [
                    'fetched' => $fetchedCount,
                    'skipped' => count($pending) - $fetchedCount,
                    'elapsed' => round($elapsed, 2),
                ]);
                break;
            }

            if (! $first) {
                usleep(self::INTER_REQUEST_DELAY_MS * 1000);
            }
            $first = false;

            $result = $this->fetchOneWithRetry(
                $fromLat, $fromLng,
                (float) $info['lat'], (float) $info['lng'],
            );

            $fetchedCount++;

            if ($result === null) {
                continue;
            }

            $out[$key] = $result;
            Cache::put(
                $info['cache_key'],
                $result,
                now()->addHours(self::CACHE_TTL_HOURS)
            );
        }

        return $out;
    }

    private function fetchOneWithRetry(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $backoff = self::RETRY_BACKOFF_MS[$attempt];
            if ($backoff > 0) {
                usleep($backoff * 1000);
            }

            $wasTimeout = false;
            $result     = $this->fetchOne($fromLat, $fromLng, $toLat, $toLng, $wasTimeout);

            if ($result !== null) {
                return $result;
            }

            if ($wasTimeout) {
                return null;
            }
        }

        return null;
    }

    private function fetchOne(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        bool &$wasTimeout = false,
    ): ?array {
        $wasTimeout = false;

        $url = $this->baseUrl() . "/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}";

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept'     => 'application/json',
                ])
                ->retry(0, 0)
                ->get($url, [
                    'overview'     => 'false',
                    'alternatives' => 'false',
                    'steps'        => 'false',
                ]);
        } catch (ConnectionException $e) {
            $msg = strtolower($e->getMessage());
            $wasTimeout = str_contains($msg, 'timed out')
                       || str_contains($msg, 'timeout')
                       || str_contains($msg, 'operation timed out');

            return null;
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $route = $response->json('routes.0');

        if (! is_array($route) || ! isset($route['distance'])) {
            return null;
        }

        return [
            'distance_km'  => round((float) $route['distance'] / 1000, 2),
            'duration_min' => (int) round((float) ($route['duration'] ?? 0) / 60),
        ];
    }

    private function cacheKey(float $fromLat, float $fromLng, float $toLat, float $toLng): string
    {
        return sprintf(
            'osrm:dist:%s,%s:%s,%s',
            round($fromLat, self::COORD_PRECISION),
            round($fromLng, self::COORD_PRECISION),
            round($toLat,   self::COORD_PRECISION),
            round($toLng,   self::COORD_PRECISION),
        );
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config('livewire-mapcn.osrm_url', 'https://router.project-osrm.org'),
            '/'
        );
    }
}