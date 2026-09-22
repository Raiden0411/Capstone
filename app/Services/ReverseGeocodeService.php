<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reverse-geocodes a coordinate pair into a structured address using
 * Nominatim (OpenStreetMap). Server-side only — Nominatim's usage
 * policy forbids browser-direct calls and requires an identifying
 * User-Agent, both of which we satisfy here.
 *
 * Usage policy: https://operations.osmfoundation.org/policies/nominatim/
 *   • max 1 request per second
 *   • identifying User-Agent required
 *   • no bulk scraping
 * The debounce in the Alpine picker (900ms) plus the coordinate cache
 * keep us comfortably inside the rate limit for interactive use.
 */
class ReverseGeocodeService
{
    private const CACHE_TTL_HOURS  = 24;
    private const COORD_PRECISION  = 4;      // ~11 m at the equator
    private const HTTP_TIMEOUT     = 5;
    private const RETRY_DELAY_MS   = 400;

    private const USER_AGENT = 'Capstone-Booking-Platform/1.0 '
        . '(+https://capstone.envkit.net; contact: tourism.management.ph@gmail.com)';

    /**
     * Reverse-geocode a coordinate pair.
     *
     * @return array{
     *     address: string,
     *     barangay: string,
     *     city: string,
     *     province: string,
     *     display_name: string
     * }|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        $lat = round($lat, self::COORD_PRECISION);
        $lng = round($lng, self::COORD_PRECISION);

        $key = sprintf('geocode:reverse:%s,%s', $lat, $lng);

        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['address'])) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                    'User-Agent'      => self::USER_AGENT,
                    'Accept-Language' => 'en',
                    'Accept'          => 'application/json',
                ])
                ->timeout(self::HTTP_TIMEOUT)
                ->retry(1, self::RETRY_DELAY_MS)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat'            => $lat,
                    'lon'            => $lng,
                    'format'         => 'jsonv2',
                    'addressdetails' => 1,
                    'zoom'           => 18,
                ]);
        } catch (ConnectionException $e) {
            Log::info('Nominatim connection error', [
                'lat'   => $lat,
                'lng'   => $lng,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::info('Nominatim threw', [
                'lat'   => $lat,
                'lng'   => $lng,
                'error' => $e->getMessage(),
                'type'  => get_class($e),
            ]);
            return null;
        }

        if (! $response->successful()) {
            Log::info('Nominatim HTTP error', [
                'status' => $response->status(),
                'lat'    => $lat,
                'lng'    => $lng,
            ]);
            return null;
        }

        $data = $response->json();

        if (! is_array($data) || empty($data['address']) || ! is_array($data['address'])) {
            return null;
        }

        $parsed = $this->parse($data);

        Cache::put($key, $parsed, now()->addHours(self::CACHE_TTL_HOURS));

        return $parsed;
    }

    /**
     * Map Nominatim's response shape to the four fields the wizard
     * exposes. The Philippines' admin hierarchy maps loosely:
     *
     *   address   ← house_number + road        (or the display_name's
     *                                            first segment)
     *   barangay  ← suburb / neighbourhood / quarter / hamlet / village
     *   city      ← city / town / municipality / county
     *   province  ← state / province / region
     *
     * Any field that can't be determined is returned as an empty string
     * so the caller can decide whether to overwrite.
     *
     * @param  array<string, mixed>  $data
     * @return array{address: string, barangay: string, city: string, province: string, display_name: string}
     */
    private function parse(array $data): array
    {
        /** @var array<string, mixed> $addr */
        $addr = $data['address'] ?? [];

        // ── Street ──
        $street = '';
        if (! empty($addr['house_number'])) {
            $street .= (string) $addr['house_number'] . ' ';
        }
        if (! empty($addr['road'])) {
            $street .= (string) $addr['road'];
        }
        $street = trim($street);

        if ($street === '' && ! empty($data['display_name'])) {
            $first  = explode(',', (string) $data['display_name'])[0] ?? '';
            $street = trim($first);
        }

        // ── Barangay (Philippine equivalent) ──
        $barangay = $this->firstNonEmpty([
            $addr['suburb']        ?? null,
            $addr['neighbourhood'] ?? null,
            $addr['quarter']       ?? null,
            $addr['hamlet']        ?? null,
            $addr['village']       ?? null,
        ]);

        // ── City / Municipality ──
        $city = $this->firstNonEmpty([
            $addr['city']         ?? null,
            $addr['town']         ?? null,
            $addr['municipality'] ?? null,
            $addr['county']       ?? null,
        ]);

        // ── Province / State ──
        $province = $this->firstNonEmpty([
            $addr['state']    ?? null,
            $addr['province'] ?? null,
            $addr['region']   ?? null,
        ]);

        return [
            'address'      => $street,
            'barangay'     => $barangay,
            'city'         => $city,
            'province'     => $province,
            'display_name' => (string) ($data['display_name'] ?? ''),
        ];
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    private function firstNonEmpty(array $candidates): string
    {
        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}