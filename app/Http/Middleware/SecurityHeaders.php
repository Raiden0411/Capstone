<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip on binary/streamed downloads — no benefit, can corrupt some clients.
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $enforced = [
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(self), microphone=(), geolocation=(self), payment=(self), usb=()',
            // Disable the legacy XSS auditor — it introduced its own XSS class.
            'X-XSS-Protection'       => '0',
        ];

        // HSTS only over real TLS. Never on http://127.0.0.1 or a plain-HTTP tunnel.
        // No includeSubDomains — envkit.net may be shared.
        if ($request->secure()) {
            $enforced['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($enforced as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        // CSP report-only. Enforcing CSP breaks Livewire 4 (inline bootstrap),
        // Alpine (inline x-data evaluation), and MapLibre (blob workers).
        // Report-only gives the auditor the header + browser console violations
        // without white-screening the UI during the defense.
        //
        // Post-defense: rename the header to 'Content-Security-Policy' and
        // migrate to Vite::useCspNonce() + a Livewire CSP-safe build.
        if (! $response->headers->has('Content-Security-Policy-Report-Only')) {
            $response->headers->set(
                'Content-Security-Policy-Report-Only',
                $this->reportOnlyCsp()
            );
        }

        return $response;
    }

    private function reportOnlyCsp(): string
    {
        $directives = [
            "default-src 'self'",
            // jsdelivr + unpkg: MapLibre CDN fallback. js.paymongo.com: PayMongo.js.
            // 'unsafe-inline' + 'unsafe-eval': Livewire + Alpine require both.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://js.paymongo.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            // OSRM: routes. CARTO + OSM: tiles. PayMongo: API. wss: Livewire (if broadcasting).
            "connect-src 'self' https://api.paymongo.com https://router.project-osrm.org https://*.basemaps.cartocdn.com https://*.tile.openstreetmap.org https://nominatim.openstreetmap.org https://server.arcgisonline.com wss:",
            "worker-src 'self' blob:",
            "frame-src 'self' https://js.paymongo.com https://checkout.paymongo.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }
}