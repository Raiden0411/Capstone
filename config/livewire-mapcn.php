<?php

/*
|--------------------------------------------------------------------------
| livewire-mapcn — Configuration
|--------------------------------------------------------------------------
| Package: kwasii/livewire-mapcn
|
| These keys are read by the <x-map> family of Blade components at boot.
| Any change requires `php artisan config:clear` to take effect.
|
| ── VERSION CONTRACT ── read before editing maplibre_version / cdn_url ──
|
|   'maplibre_version'  Fallback version. Used ONLY when cdn_url is empty.
|                       The package builds the script URL from this string.
|
|   'cdn_url'           Actual <script src> the browser loads. When set,
|                       this wins over maplibre_version.
|
|   'cdn_css_url'       Actual <link href> the browser loads.
|
| All three must agree on the same major version. Currently pinned to the
| 4.x line. If the CDN URLs were ever removed, maplibre_version would take
| over — keeping them in sync prevents an accidental silent upgrade.
|
| To upgrade to 5.x: change ALL THREE at once and test on a real iPhone.
| MapLibre 5 has breaking changes from 4 (style spec version, some event
| payloads, dropped deprecated methods).
*/

return [

    'default_provider' => 'carto-positron',

    // See VERSION CONTRACT above. Matches the @4.x alias in the CDN URLs.
    'maplibre_version' => '4',

    'load_from_cdn' => true,
    'cdn_url' => 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.x/dist/maplibre-gl.js',
    'cdn_css_url' => 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.x/dist/maplibre-gl.css',
    'carto_license' => 'non-commercial',
    'default_height' => 'full',
    'default_zoom' => 7,
    'default_center' => [0, 0],
    'dark_provider' => 'carto-dark-matter',
    'osrm_url' => 'https://router.project-osrm.org',
    'cluster_popup_view' => null,

    /*
    |--------------------------------------------------------------------------
    | Custom MapLibre Events
    |--------------------------------------------------------------------------
    |
    | An array of additional MapLibre event names to forward to Livewire on
    | all maps. Each event will be dispatched as "map:{event-name}".
    | Example: ['idle', 'sourcedata', 'error', 'terrain']
    |
    | Per-map overrides can be set via the `events` prop on <x-map>.
    |
    */
    'custom_events' => [],

    /*
    |--------------------------------------------------------------------------
    | Asset Injection Method
    |--------------------------------------------------------------------------
    |
    | How should the package JS/CSS be loaded?
    | - 'route': Serve from package via Laravel route (no publishing needed)
    | - 'published': Use published assets from public/vendor/livewire-mapcn
    |
    */
    'inject_assets' => 'route',
];