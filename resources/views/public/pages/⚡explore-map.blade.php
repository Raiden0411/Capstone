{{-- resources/views/public/pages/explore-map.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\Event; // NEW: import Event model

new
#[Layout('layouts.app')]
#[Title('Explore Map · Victorias City')]
class extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'category', history: true)]
    public string $categoryFilter = '';

    #[Url(as: 'sort', history: true)]
    public string $sortBy = 'name';

    #[Url(as: 'open', history: true)]
    public bool $openNow = false;

    #[Url(as: 'offers', history: true)]
    public bool $hasOfferings = false;

    #[Url(as: 'saved', history: true)]
    public bool $favoritesOnly = false;

    #[Url(as: 'recommended', history: true)]
    public bool $recommendedOnly = false;

    #[Url(as: 'events', history: true)]
    public bool $showEvents = false; // NEW: toggle for events

    public bool $itineraryEnabled = false;

    public ?float $userLat = null;
    public ?float $userLng = null;
    public bool $followMode = false;

    public ?int    $highlightedId        = null;
    public array   $routeCoords          = [];
    public ?string $routeDestinationName = null;
    public ?int    $routeTenantId        = null;
    public string  $routeId              = 'tourist-route';
    public string  $directionsProfile    = 'driving';
    public bool    $satellite            = false;
    public bool    $sidebarOpen          = true;

    public ?int $pendingMarkerId = null;
    public bool $autoDirections = false;

    public array $favorites = [];

    public ?float $currentLat = null;
    public ?float $currentLng = null;
    public ?int   $currentZoom = null;

    public int $locationVersion = 0;
    public string $filtersHash = '';
    public int $routeVersion = 0;
    public ?array $pendingFitBounds = null;

    public ?int $pendingDirectionsTenantId = null;
    public ?int $pendingDirectionsCoordIndex = 0;

    public array $markerTypes = [
        'restaurant' => 'Restaurant',
        'cafe'       => 'Café',
        'inn'        => 'Inn / Hotel',
        'shop'       => 'Shop',
        'viewpoint'  => 'Viewpoint',
        'parking'    => 'Parking',
        'entrance'   => 'Entrance',
        'other'      => 'Other',
    ];

    public array $markerColors = [
        'restaurant' => '#f97316',
        'cafe'       => '#a855f7',
        'inn'        => '#3b82f6',
        'shop'       => '#14b8a6',
        'viewpoint'  => '#eab308',
        'parking'    => '#6b7280',
        'entrance'   => '#22c55e',
        'other'      => '#94a3b8',
    ];

    public array $markerEmojis = [
        'restaurant' => '🍽️',
        'cafe'       => '☕',
        'inn'        => '🏨',
        'shop'       => '🛍️',
        'viewpoint'  => '🌄',
        'parking'    => '🅿️',
        'entrance'   => '🚪',
        'other'      => '📍',
    ];

    private const CITY_CENTER = [123.07055771888716, 10.900977766937142];

    public function mount(): void
    {
        $this->favorites   = session($this->favoritesStorageKey(), []);
        $this->sidebarOpen = request()->cookie('hs_sidebar_open') !== '0';
        $this->filtersHash = $this->computeFiltersHash();

        $this->hydrateFromQueryString();
    }

    protected function computeFiltersHash(): string
    {
        return md5(json_encode([
            $this->search,
            $this->categoryFilter,
            $this->openNow,
            $this->hasOfferings,
            $this->favoritesOnly,
            $this->recommendedOnly,
            $this->showEvents, // NEW
        ]));
    }

    protected function hydrateFromQueryString(): void
    {
        if (request()->filled('lat') && request()->filled('lng')) {
            $this->userLat = (float) request('lat');
            $this->userLng = (float) request('lng');
            $this->currentLat = $this->userLat;
            $this->currentLng = $this->userLng;
            $this->locationVersion++;
        }

        if (request()->filled('marker')) {
            $this->pendingMarkerId = (int) request('marker');
        }

        if ($this->pendingMarkerId && request()->boolean('directions')) {
            $this->autoDirections = true;
        }

        if (request()->filled('profile') && in_array(request('profile'), ['driving', 'walking', 'cycling'], true)) {
            $this->directionsProfile = request('profile');
        }
    }

    #[Computed]
    public function tenants()
    {
        $term = trim($this->search);

        $tenants = Tenant::query()
            ->where('is_active', true)
            ->whereNotNull('coordinates')
            ->with([
                'typeOfTenant',
                'settings' => fn ($q) => $q->where('key', 'business_info'),
            ])
            ->withCount(['properties', 'services'])
            ->withMin('properties', 'price')
            ->when($term !== '', function ($q) use ($term) {
                $like = '%' . $term . '%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('name', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhereHas('typeOfTenant', fn ($t) => $t->where('type', 'like', $like));
                });
            })
            ->when($this->categoryFilter, fn ($q) => $q->whereHas(
                'typeOfTenant',
                fn ($sub) => $sub->where('type', $this->categoryFilter)
            ))
            ->when($this->favoritesOnly, fn ($q) => $q->whereIn('id', $this->favorites ?: [0]))
            ->when($this->recommendedOnly, fn ($q) => $q->where('is_recommended', true))
            ->get();

        if ($this->hasOfferings) {
            $tenants = $tenants->filter(fn ($t) => $t->properties_count > 0 || $t->services_count > 0);
        }

        if ($this->openNow) {
            $tenants = $tenants->filter(fn ($t) => $this->isOpenNow($t));
        }

        return match ($this->sortBy) {
            'distance' => $this->userLat && $this->userLng
                ? $tenants->sortBy(fn ($t) => $this->calculateDistance(
                    (float) $t->coordinates[0]['lat'],
                    (float) $t->coordinates[0]['lng']
                ))->values()
                : $tenants->sortBy('name')->values(),
            'newest'  => $tenants->sortByDesc('created_at')->values(),
            'popular' => $tenants->sortByDesc(fn ($t) => $t->properties_count + $t->services_count)->values(),
            default   => $tenants->sortBy('name')->values(),
        };
    }

    #[Computed]
    public function eventMarkers()
    {
        if (!$this->showEvents) {
            return collect();
        }

        return Event::query()
            ->whereNotNull('coordinates')
            ->where('is_active', true)
            ->where('start_date', '>=', now()->subDay()) // show events from yesterday onwards
            ->get()
            ->map(function ($event) {
                $coords = is_array($event->coordinates)
                    ? $event->coordinates
                    : json_decode($event->coordinates, true);

                return [
                    'id'          => $event->id,
                    'name'        => $event->name,
                    'barangay'    => $event->barangay,
                    'type'        => $event->type,
                    'start_date'  => $event->start_date,
                    'end_date'    => $event->end_date,
                    'lat'         => (float) $coords['lat'],
                    'lng'         => (float) $coords['lng'],
                    'featured'    => (bool) $event->featured,
                    'tenant_id'   => $event->tenant_id,
                    'tenant_slug' => optional($event->tenant)->slug,
                ];
            });
    }

    #[Computed]
    public function geoJsonData()
    {
        $tenantData = $this->tenants->flatMap(function ($tenant) {
            $status = $this->statusEmoji($this->tenantOpenStatus($tenant));

            return collect($tenant->coordinates)->map(function ($coord) use ($tenant, $status) {
                return [
                    'type'       => 'Feature',
                    'properties' => [
                        'name'      => $coord['name'] ?? $tenant->name,
                        'type'      => $tenant->typeOfTenant?->type ?? 'Business',
                        'tenant_id' => $tenant->id,
                        'slug'      => $tenant->slug,
                        'logo'      => $tenant->logo,
                        'address'   => $tenant->address,
                        'phone'     => $tenant->contact_number,
                        'email'     => $tenant->email,
                        'offerings' => $tenant->properties_count + $tenant->services_count,
                        'favorite'  => in_array($tenant->id, $this->favorites, true),
                        'status'    => $status,
                    ],
                    'geometry' => [
                        'type'        => 'Point',
                        'coordinates' => [(float) $coord['lng'], (float) $coord['lat']],
                    ],
                ];
            });
        })->values()->toArray();

        // Add event markers to GeoJSON (if enabled)
        if ($this->showEvents) {
            $eventFeatures = $this->eventMarkers->map(function ($event) {
                return [
                    'type'       => 'Feature',
                    'properties' => [
                        'name'      => $event['name'],
                        'type'      => 'Event',
                        'event_id'  => $event['id'],
                        'barangay'  => $event['barangay'],
                        'start'     => $event['start_date']->format('M d, Y'),
                    ],
                    'geometry' => [
                        'type'        => 'Point',
                        'coordinates' => [$event['lng'], $event['lat']],
                    ],
                ];
            })->values()->toArray();

            return array_merge($tenantData, $eventFeatures);
        }

        return $tenantData;
    }

    #[Computed]
    public function categories()
    {
        return TypeOfTenant::query()
            ->withCount(['tenants' => fn ($q) => $q->where('is_active', true)])
            ->whereHas('tenants', fn ($q) => $q->where('is_active', true))
            ->orderBy('type')
            ->get(['id', 'type']);
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->categoryFilter !== ''
            || $this->openNow
            || $this->hasOfferings
            || $this->favoritesOnly
            || $this->recommendedOnly
            || $this->showEvents;
    }

    #[Computed]
    public function initialCenter(): array
    {
        if ($this->currentLat && $this->currentLng) {
            return [(float) $this->currentLng, (float) $this->currentLat];
        }

        if ($this->userLat && $this->userLng) {
            return [(float) $this->userLng, (float) $this->userLat];
        }

        return self::CITY_CENTER;
    }

    #[Computed]
    public function initialZoom(): int
    {
        return $this->currentZoom ?? ($this->userLat && $this->userLng ? 14 : 12);
    }

    #[Computed]
    public function itineraryStops(): array
    {
        if (!$this->itineraryEnabled) {
            return [];
        }

        $stops = $this->tenants
            ->filter(fn ($t) => $t->is_recommended && !empty($t->coordinates))
            ->take(4)
            ->values();

        if ($stops->count() < 2) {
            return [];
        }

        $coords = $stops->map(function ($tenant) {
            $main = $tenant->coordinates[0];
            return [
                'tenant_id' => $tenant->id,
                'name'      => $tenant->name,
                'lat'       => (float) $main['lat'],
                'lng'       => (float) $main['lng'],
                'logo'      => $tenant->logo ? asset('storage/' . $tenant->logo) : null,
            ];
        })->values()->toArray();

        $line = collect($coords)->map(fn ($c) => [$c['lng'], $c['lat']])->values()->all();

        return [
            'coords' => $coords,
            'line'   => $line,
        ];
    }

    protected function isOpenNow(Tenant $tenant): bool
    {
        $hours = $tenant->settings->first()?->value['opening_hours'] ?? null;

        if (!$hours) {
            return false;
        }

        $days = $hours['days'] ?? null;

        if (is_array($days) && !in_array((int) now()->dayOfWeek, array_map('intval', $days), true)) {
            return false;
        }

        $now     = now()->format('H:i');
        $opening = $hours['opening'] ?? '00:00';
        $closing = $hours['closing'] ?? '23:59';

        return $opening <= $closing
            ? $now >= $opening && $now <= $closing
            : $now >= $opening || $now <= $closing;
    }

    public function tenantOpenStatus(Tenant $tenant): ?bool
    {
        $hours = $tenant->settings->first()?->value['opening_hours'] ?? null;

        return $hours ? $this->isOpenNow($tenant) : null;
    }

    protected function statusEmoji(?bool $isOpen): string
    {
        return match ($isOpen) {
            true    => '🟢 Open now',
            false   => '⚪ Closed now',
            default => '',
        };
    }

    public function calculateDistance($lat2, $lng2)
    {
        if (!$this->userLat || !$this->userLng) {
            return PHP_FLOAT_MAX;
        }

        $R    = 6371;
        $dLat = deg2rad($lat2 - $this->userLat);
        $dLng = deg2rad($lng2 - $this->userLng);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($this->userLat)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function formatDistance(float $km): string
    {
        return $km < 1
            ? round($km * 1000) . ' m'
            : number_format($km, 1) . ' km';
    }

    public function highlightMatch(string $text): \Illuminate\Support\HtmlString
    {
        $escaped = e($text);
        $term    = trim($this->search);

        if ($term === '') {
            return new \Illuminate\Support\HtmlString($escaped);
        }

        $pattern     = '/(' . preg_quote(e($term), '/') . ')/i';
        $highlighted = preg_replace(
            $pattern,
            '<mark class="rounded-sm bg-amber-200 px-0.5 text-inherit dark:bg-amber-400/40">$1</mark>',
            $escaped
        );

        return new \Illuminate\Support\HtmlString($highlighted ?? $escaped);
    }

    protected function favoritesStorageKey(): string
    {
        return auth()->check()
            ? 'explore_map_favorites_user_' . auth()->id()
            : 'explore_map_favorites_guest';
    }

    public function toggleFavorite(int $id): void
    {
        if (in_array($id, $this->favorites, true)) {
            $this->favorites = array_values(array_diff($this->favorites, [$id]));
            $this->notify('Removed from favorites.', 'info');
        } else {
            $this->favorites[] = $id;
            $this->notify('Saved to favorites.', 'success');
        }

        session([$this->favoritesStorageKey() => $this->favorites]);
    }

    protected function notify(string $message, string $type = 'info'): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }

    public function updatedSearch(): void { $this->filtersHash = $this->computeFiltersHash(); }
    public function updatedCategoryFilter(): void { $this->filtersHash = $this->computeFiltersHash(); }
    public function updatedOpenNow(): void { $this->filtersHash = $this->computeFiltersHash(); }
    public function updatedHasOfferings(): void { $this->filtersHash = $this->computeFiltersHash(); }
    public function updatedFavoritesOnly(): void { $this->filtersHash = $this->computeFiltersHash(); }
    public function updatedRecommendedOnly(): void { $this->filtersHash = $this->computeFiltersHash(); }
    public function updatedShowEvents(): void { $this->filtersHash = $this->computeFiltersHash(); } // NEW

    public function updatedSortBy(string $value): void
    {
        if ($value === 'distance' && !$this->userLat) {
            $this->dispatch('request-location-for-distance');
        }
    }

    public function setUserLocation($lat, $lng): void
    {
        $this->userLat = round((float) $lat, 6);
        $this->userLng = round((float) $lng, 6);
        $this->currentLat = $this->userLat;
        $this->currentLng = $this->userLng;
        $this->currentZoom = 15;
        $this->locationVersion++;

        if ($this->pendingDirectionsTenantId) {
            $tenantId = $this->pendingDirectionsTenantId;
            $coordIdx = $this->pendingDirectionsCoordIndex;
            $this->pendingDirectionsTenantId = null;
            $this->pendingDirectionsCoordIndex = 0;

            $tenant = $this->resolveTenant($tenantId);

            if (!$tenant || empty($tenant->coordinates[$coordIdx])) {
                $this->notify('That destination is no longer available.', 'error');
                return;
            }

            $label = $tenant->coordinates[$coordIdx]['name'] ?? $tenant->name;
            $this->routeToCoordinate($tenant, $coordIdx, $label);

            return;
        }

        if ($this->autoDirections && $this->highlightedId) {
            $this->autoDirections = false;
            $this->getDirectionsTo($this->highlightedId);
            $this->notify('Location found — charting your route.', 'success');
            return;
        }

        if ($this->sortBy !== 'distance') {
            $this->sortBy = 'distance';
        }

        $this->dispatch('map:fly-to', center: [$this->userLng, $this->userLat], zoom: 15);
        $this->notify('Location found — sorted by distance.', 'success');
    }

    public function locationFailed(string $reason = 'unavailable'): void
    {
        $wasForDirections = $this->autoDirections;
        $wasForPendingDirections = $this->pendingDirectionsTenantId !== null;

        $this->autoDirections = false;
        $this->pendingDirectionsTenantId = null;
        $this->pendingDirectionsCoordIndex = 0;

        if ($wasForPendingDirections) {
            $this->notify('We couldn\'t get your location, so directions didn\'t start automatically. Try "Use My Location" instead.', 'warning');
            return;
        }

        if ($wasForDirections) {
            $this->notify('We couldn\'t get your location, so directions didn\'t start automatically. Try "Use My Location" instead.', 'warning');
            return;
        }

        $this->notify(match ($reason) {
            'denied'  => 'Location access was denied — you can still search and browse manually.',
            'timeout' => 'Finding your location took too long. Please try again.',
            default   => 'Your location could not be determined right now.',
        }, $reason === 'unavailable' ? 'error' : 'warning');
    }

    public function toggleFollowMode(bool $enabled): void
    {
        $this->followMode = $enabled;
        $this->notify($enabled ? 'Follow mode on — the map will track your location.' : 'Follow mode off.', 'info');
    }

    #[On('map:loaded')]
    public function onMapReady(): void
    {
        if ($this->pendingMarkerId) {
            $tenant = $this->resolveTenant($this->pendingMarkerId);
            $this->pendingMarkerId = null;

            if (!$tenant || empty($tenant->coordinates)) {
                $this->notify('That destination link is no longer available.', 'error');
                $this->autoDirections = false;
                return;
            }

            $this->highlightedId = $tenant->id;
            $coord = $tenant->coordinates[0];

            $this->dispatch('map:fly-to', center: [(float) $coord['lng'], (float) $coord['lat']], zoom: 16);
            $this->dispatch('tenant-viewed', id: $tenant->id, name: $tenant->name, type: $tenant->typeOfTenant?->type ?? 'Business');

            if ($this->autoDirections) {
                $this->dispatch('locate-me-for-directions');
            }
        }

        if ($this->pendingFitBounds) {
            $this->dispatch('map:fit-bounds', ...$this->pendingFitBounds);
            $this->pendingFitBounds = null;
        }
    }

    #[On('map:marker-clicked')]
    public function onMarkerClicked($id, $lat, $lng): void
    {
        if (preg_match('/tenant-(\d+)-(\d+)/', $id, $matches)) {
            $tenantId = (int) $matches[1];
            $coordIndex = (int) $matches[2];
            $this->highlightedId = $tenantId;

            $tenant = $this->resolveTenant($tenantId);
            if ($tenant && isset($tenant->coordinates[$coordIndex])) {
                $coord = $tenant->coordinates[$coordIndex];
                $this->dispatch('map:fly-to', center: [(float) $coord['lng'], (float) $coord['lat']], zoom: 16);
            }
        } elseif (str_starts_with($id, 'event-')) {
            // Event marker clicked – optional handling
            $eventId = (int) substr($id, 6);
            // Could fly to event, but popup already opened
        }
    }

    public function flyToTenant(int $id): void
    {
        $tenant = $this->resolveTenant($id);

        if (!$tenant || empty($tenant->coordinates)) {
            $this->notify('This business has no map location yet.', 'error');
            return;
        }

        $this->highlightedId = $id;
        $coord = $tenant->coordinates[0];

        $this->dispatch('map:fly-to', center: [(float) $coord['lng'], (float) $coord['lat']], zoom: 16);
        $this->dispatch('tenant-viewed', id: $tenant->id, name: $tenant->name, type: $tenant->typeOfTenant?->type ?? 'Business');
    }

    public function flyToTenantCoord(int $id, int $index): void
    {
        $tenant = $this->resolveTenant($id);

        if (!$tenant || empty($tenant->coordinates[$index])) {
            $this->notify('That location could not be found.', 'error');
            return;
        }

        $this->highlightedId = $id;
        $coord = $tenant->coordinates[$index];

        $this->dispatch('map:fly-to', center: [(float) $coord['lng'], (float) $coord['lat']], zoom: 17);
    }

    public function getDirectionsTo(int $id): void
    {
        $tenant = $this->resolveTenant($id);

        if (!$tenant || empty($tenant->coordinates)) {
            $this->notify('This business has no map location yet.', 'error');
            return;
        }

        if (!$this->userLat || !$this->userLng) {
            $this->pendingDirectionsTenantId = $id;
            $this->pendingDirectionsCoordIndex = 0;
            $this->notify('Finding your location…', 'info');
            $this->dispatch('locate-me-for-directions');
            return;
        }

        $this->routeToCoordinate($tenant, 0, $tenant->name);
    }

    public function getDirectionsToCoord(int $id, int $index): void
    {
        $tenant = $this->resolveTenant($id);

        if (!$tenant || empty($tenant->coordinates[$index])) {
            $this->notify('That location could not be found.', 'error');
            return;
        }

        if (!$this->userLat || !$this->userLng) {
            $this->pendingDirectionsTenantId = $id;
            $this->pendingDirectionsCoordIndex = $index;
            $this->notify('Finding your location…', 'info');
            $this->dispatch('locate-me-for-directions');
            return;
        }

        $label = $tenant->coordinates[$index]['name'] ?? $tenant->name;
        $this->routeToCoordinate($tenant, $index, $label);
    }

    protected function routeToCoordinate(Tenant $tenant, int $index, string $label): void
    {
        $coord = $tenant->coordinates[$index];

        $start = [(float) $this->userLng, (float) $this->userLat];
        $end   = [(float) $coord['lng'], (float) $coord['lat']];

        $this->routeCoords          = ['start' => $start, 'end' => $end];
        $this->routeDestinationName = $label;
        $this->routeTenantId        = $tenant->id;
        $this->highlightedId        = $tenant->id;

        $this->routeVersion++;
        $this->pendingFitBounds = [
            'bounds'  => [$start, $end],
            'padding' => 90,
        ];
    }

    public function setDirectionsProfile(string $profile): void
    {
        if (!in_array($profile, ['driving', 'walking', 'cycling'], true)) {
            return;
        }

        $this->directionsProfile = $profile;
        $this->routeVersion++;

        if (!empty($this->routeCoords)) {
            $this->pendingFitBounds = [
                'bounds'  => [$this->routeCoords['start'], $this->routeCoords['end']],
                'padding' => 90,
            ];
        }
    }

    public function clearRoute(): void
    {
        $this->routeCoords          = [];
        $this->routeDestinationName = null;
        $this->routeTenantId        = null;
        $this->pendingFitBounds     = null;
        $this->routeVersion++;
        $this->notify('Route cleared.', 'info');
    }

    public function fitAllLocations(): void
    {
        $bounds = collect($this->geoJsonData)
            ->map(fn ($feature) => $feature['geometry']['coordinates'])
            ->values()
            ->toArray();

        if (count($bounds) < 2) {
            $this->notify('Add more destinations to your filters to fit them all.', 'info');
            return;
        }

        $this->dispatch('map:fit-bounds', bounds: $bounds, padding: 60);
    }

    public function resetView(): void
    {
        $this->dispatch('map:fly-to', center: self::CITY_CENTER, zoom: 12);
    }

    public function toggleSatellite(): void
    {
        $this->satellite = !$this->satellite;
        $this->notify($this->satellite ? 'Satellite view on.' : 'Standard map view on.', 'info');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'openNow', 'hasOfferings', 'favoritesOnly', 'recommendedOnly', 'showEvents']);
        $this->filtersHash = $this->computeFiltersHash();
        $this->notify('Filters cleared.', 'info');
    }

    public function shareLocation(): void
    {
        if (!$this->userLat || !$this->userLng) {
            $this->notify('Find your location first, then share it.', 'info');
            return;
        }

        $url = $this->buildShareUrl(['lat' => $this->userLat, 'lng' => $this->userLng]);

        $this->dispatch('copy-to-clipboard', text: $url);
        $this->notify('Map link copied to clipboard.', 'success');
    }

    public function shareMarker(int $id): void
    {
        $tenant = $this->resolveTenant($id);

        if (!$tenant) {
            $this->notify('That destination could not be found.', 'error');
            return;
        }

        $url = $this->buildShareUrl(['marker' => $id]);

        $this->dispatch('copy-to-clipboard', text: $url);
        $this->notify('Marker link copied to clipboard.', 'success');
    }

    public function shareRoute(): void
    {
        if (!$this->routeTenantId) {
            $this->notify('Start a route first, then share it.', 'info');
            return;
        }

        $url = $this->buildShareUrl([
            'marker'     => $this->routeTenantId,
            'directions' => 1,
            'profile'    => $this->directionsProfile,
        ]);

        $this->dispatch('copy-to-clipboard', text: $url);
        $this->notify('Directions link copied — it will ask them for their own location.', 'success');
    }

    protected function buildShareUrl(array $params): string
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        return request()->url() . '?' . http_build_query($params);
    }

    public function printMap(): void
    {
        $this->dispatch('print-map');
        $this->notify('Preparing print view…', 'info');
    }

    protected function resolveTenant(int $id): ?Tenant
    {
        return $this->tenants->firstWhere('id', $id)
            ?? Tenant::query()
                ->where('is_active', true)
                ->with([
                    'typeOfTenant',
                    'settings' => fn ($q) => $q->where('key', 'business_info'),
                ])
                ->find($id);
    }

    public function updateViewport(?float $lat = null, ?float $lng = null, ?int $zoom = null): void
    {
        if ($lat !== null) {
            $this->currentLat = round($lat, 6);
        }

        if ($lng !== null) {
            $this->currentLng = round($lng, 6);
        }

        if ($zoom !== null) {
            $this->currentZoom = $zoom;
        }
    }
};
?>

<div class="relative z-10 flex h-[calc(100vh-64px)] overflow-hidden"
     x-data="{
        mobileOpen: false,
        sidebarOpen: @entangle('sidebarOpen'),
        locating: false,
        followMode: @entangle('followMode'),
        online: navigator.onLine,
        helpOpen: false,
        helpTrigger: null,
        viewport: { lat: null, lng: null, zoom: null },
        toasts: [],
        pendingDirectionRequest: false,
        userHeading: null,

        addToast(type, message) {
            const id = Date.now() + Math.random();
            const duration = 4200;
            const toast = { id, type, message, duration, remaining: duration, paused: false, _timer: null, _tick: null };
            toast._timer = setTimeout(() => this.removeToast(id), duration);
            toast._tick = setInterval(() => {
                if (!toast.paused) toast.remaining = Math.max(0, toast.remaining - 100);
            }, 100);
            this.toasts.push(toast);
            if (this.toasts.length > 4) {
                const oldest = this.toasts.shift();
                clearTimeout(oldest._timer);
                clearInterval(oldest._tick);
            }
        },
        pauseToast(toast) {
            toast.paused = true;
            clearTimeout(toast._timer);
        },
        resumeToast(toast) {
            toast.paused = false;
            toast._timer = setTimeout(() => this.removeToast(toast.id), Math.max(toast.remaining, 300));
        },
        removeToast(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) { clearTimeout(toast._timer); clearInterval(toast._tick); }
            this.toasts = this.toasts.filter(t => t.id !== id);
        },

        locate() {
            if (!navigator.geolocation) {
                $wire.locationFailed('unavailable');
                return;
            }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.locating = false;
                    this.userHeading = pos.coords.heading || 0;
                    $wire.setUserLocation(pos.coords.latitude, pos.coords.longitude);
                    if (this.pendingDirectionRequest) {
                        this.pendingDirectionRequest = false;
                        setTimeout(() => this.startFollowMode(), 500);
                    }
                },
                (err) => {
                    this.locating = false;
                    this.pendingDirectionRequest = false;
                    const reason = err.code === err.PERMISSION_DENIED ? 'denied'
                        : err.code === err.TIMEOUT ? 'timeout' : 'unavailable';
                    $wire.locationFailed(reason);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        },

        startFollowMode() {
            if (this.followMode) return;
            if (!navigator.geolocation) {
                $wire.locationFailed('unavailable');
                return;
            }
            this.followMode = true;
            $wire.toggleFollowMode(true);
            let lastUpdate = 0;
            this.watchId = navigator.geolocation.watchPosition(
                (pos) => {
                    const now = Date.now();
                    if (now - lastUpdate < 2000) return;
                    lastUpdate = now;
                    this.userHeading = pos.coords.heading || 0;
                    $wire.dispatch('map:fly-to', {
                        center: [pos.coords.longitude, pos.coords.latitude],
                        zoom: 16,
                        essential: true
                    });
                },
                (err) => {
                    this.followMode = false;
                    $wire.toggleFollowMode(false);
                    $wire.locationFailed(err.code === err.PERMISSION_DENIED ? 'denied' : 'unavailable');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        },

        stopFollowMode() {
            this.followMode = false;
            $wire.toggleFollowMode(false);
            if (this.watchId) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
        },

        async copyText(text) {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                this.addToast('success', 'Link copied to clipboard.');
            } catch (e) {
                this.addToast('error', 'Could not copy the link automatically.');
            }
        },
     }"
     x-init="
        $watch('mobileOpen', (open) => document.body.classList.toggle('overflow-hidden', open));
        $watch('sidebarOpen', (open) => {
            document.cookie = 'hs_sidebar_open=' + (open ? '1' : '0') + ';path=/;max-age=31536000;samesite=Lax';
            setTimeout(() => $wire.dispatch('map:resize'), 320);
        });
        window.addEventListener('online', () => online = true);
        window.addEventListener('offline', () => online = false);
     "
     x-on:notify.window="addToast($event.detail.type, $event.detail.message)"
     x-on:copy-to-clipboard.window="copyText($event.detail.text)"
     x-on:request-location-for-distance.window="locate()"
     x-on:locate-me-for-directions.window="pendingDirectionRequest = true; locate()"
     x-on:tenant-viewed.window="addRecent($event.detail)"
     x-on:print-map.window="window.print()"
     x-on:map:center-changed.window="
        viewport.lat = $event.detail.lat;
        viewport.lng = $event.detail.lng;
        $wire.updateViewport(viewport.lat, viewport.lng, viewport.zoom);
     "
     x-on:map:zoom-changed.window="
        viewport.zoom = $event.detail.zoom;
        $wire.updateViewport(viewport.lat, viewport.lng, viewport.zoom);
     "
     x-on:keydown.window="
        const typing = ['INPUT','TEXTAREA'].includes(document.activeElement?.tagName);
        const hasModifier = $event.ctrlKey || $event.metaKey || $event.altKey;

        if ($event.key === '/' && !typing) {
            $event.preventDefault();
            $refs.searchInput && $refs.searchInput.focus();
            return;
        }

        if ($event.key === 'Escape') {
            if (helpOpen) { helpOpen = false; helpTrigger?.focus(); helpTrigger = null; }
            else if (typing && document.activeElement === $refs.searchInput) { $refs.searchInput.blur(); }
            else if (mobileOpen) { mobileOpen = false; }
            return;
        }

        if (typing || hasModifier) return;

        switch ($event.key.toLowerCase()) {
            case 'l': locate(); break;
            case 'f': followMode ? stopFollowMode() : startFollowMode(); break;
            case 's': $wire.toggleSatellite(); break;
            case 'p': $wire.printMap(); break;
            case 'r': $wire.resetFilters(); break;
            case '?': helpOpen = true; break;
        }
     ">

    {{-- Mobile overlay --}}
    <div
        class="fixed inset-0 z-[1090] bg-black/50 transition-opacity duration-300 motion-reduce:transition-none lg:hidden print:hidden"
        :class="mobileOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
        @click="mobileOpen = false"
        aria-hidden="true"
    ></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-[1100] w-[85%] max-w-[360px] -translate-x-full border-r border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out motion-reduce:transition-none dark:border-gray-700 dark:bg-gray-900 lg:static lg:z-auto lg:max-w-none lg:translate-x-0 lg:overflow-hidden lg:transition-[width] lg:duration-300 print:hidden"
        :class="[
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            sidebarOpen ? 'lg:w-[360px]' : 'lg:w-0',
        ]"
        role="dialog"
        aria-modal="true"
        aria-label="Destinations sidebar"
    >
        <div class="relative h-full w-[85vw] max-w-[360px] lg:w-[360px]">
            <button
                type="button"
                @click="mobileOpen = false"
                class="absolute right-3 top-3 z-10 flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-200 lg:hidden"
                aria-label="Close destinations list"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            @include('livewire.partials.explore-sidebar')
        </div>
    </aside>

    {{-- Desktop sidebar collapse toggle --}}
    <button
        type="button"
        @click="sidebarOpen = !sidebarOpen"
        class="absolute left-0 top-1/2 z-[1000] hidden h-16 w-6 -translate-y-1/2 items-center justify-center rounded-r-xl border border-l-0 border-gray-200 bg-white text-gray-500 shadow-lg transition-[left] duration-300 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-blue-400 lg:flex print:hidden"
        :style="`left: ${sidebarOpen ? 360 : 0}px`"
        :aria-expanded="sidebarOpen.toString()"
        aria-label="Toggle sidebar"
    >
        <svg x-show="sidebarOpen" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <svg x-show="!sidebarOpen" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>

    {{-- Map Area --}}
    <div class="relative h-full min-w-0 flex-1 bg-gray-100 dark:bg-gray-800 print:bg-white">

        <div class="absolute inset-0 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 print:hidden" aria-hidden="true"></div>

        <div
            x-show="!online"
            x-transition
            class="absolute inset-x-0 top-0 z-[1200] flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-center text-[12px] font-semibold text-white print:hidden"
            role="status"
        >
            ⚠️ You're offline — map tiles and directions may not load until your connection returns.
        </div>

        <div class="pointer-events-none absolute left-4 top-4 z-[50] hidden rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-900 print:block">
            Victorias City · Explore Map — {{ now()->format('F j, Y') }} · {{ $this->tenants->count() }} {{ Str::plural('destination', $this->tenants->count()) }} shown
        </div>

        {{-- Floating Filter Bar --}}
        <div class="absolute left-1/2 top-4 z-[1000] -translate-x-1/2 flex flex-wrap items-center justify-center gap-1.5 rounded-full border border-gray-200 bg-white/95 p-1 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-800/95 print:hidden"
             role="group" aria-label="Quick filters">
            <button type="button"
                    wire:click="resetFilters"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide transition
                           {{ !$this->hasActiveFilters ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                All
            </button>
            <button type="button"
                    wire:click="$set('recommendedOnly', {{ $recommendedOnly ? 'false' : 'true' }})"
                    aria-pressed="{{ $recommendedOnly ? 'true' : 'false' }}"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide transition
                           {{ $recommendedOnly ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                ⭐ Recommended
            </button>
            <button type="button"
                    wire:click="$set('openNow', {{ $openNow ? 'false' : 'true' }})"
                    aria-pressed="{{ $openNow ? 'true' : 'false' }}"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide transition
                           {{ $openNow ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                Open Now
            </button>
            <button type="button"
                    wire:click="$set('hasOfferings', {{ $hasOfferings ? 'false' : 'true' }})"
                    aria-pressed="{{ $hasOfferings ? 'true' : 'false' }}"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide transition
                           {{ $hasOfferings ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                Has Offerings
            </button>
            <button type="button"
                    wire:click="$set('showEvents', {{ $showEvents ? 'false' : 'true' }})"
                    aria-pressed="{{ $showEvents ? 'true' : 'false' }}"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide transition
                           {{ $showEvents ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                🎉 Events
            </button>
            <button type="button"
                    wire:click="$set('itineraryEnabled', {{ $itineraryEnabled ? 'false' : 'true' }})"
                    aria-pressed="{{ $itineraryEnabled ? 'true' : 'false' }}"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide transition
                           {{ $itineraryEnabled ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                🗺️ Itinerary
            </button>
        </div>

        <div
            x-data="{ show: true }"
            x-init="
                const hide = () => { show = false };
                window.addEventListener('map:loaded', hide, { once: true });
                setTimeout(hide, 6000);
            "
            x-show="show"
            x-transition:leave="transition-opacity duration-500 ease-in motion-reduce:transition-none"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-[900] flex items-center justify-center bg-gray-100 dark:bg-gray-900 print:hidden"
        >
            <div class="text-center">
                <div class="mx-auto mb-3 h-12 w-12 animate-spin motion-reduce:animate-none rounded-full border-4 border-primary-600 border-t-transparent"></div>
                <p class="text-sm text-gray-600 dark:text-gray-300">Loading map…</p>
            </div>
        </div>

        <div
            wire:key="tourist-map-{{ $satellite ? 'satellite' : 'normal' }}-{{ $locationVersion }}-{{ $filtersHash }}-{{ $routeVersion }}-{{ $itineraryEnabled ? 'itinerary' : 'no-itinerary' }}-{{ $showEvents ? 'events' : 'no-events' }}"
            class="absolute inset-0"
        >
            <x-map
                id="tourist-map"
                :center="$this->initialCenter"
                :zoom="$this->initialZoom"
                height="100%"
                :provider="$satellite ? 'custom' : 'carto-voyager'"
                :style="$satellite ? route('map.satellite.style') : null"
                :light-style="$satellite ? route('map.satellite.style') : null"
                :dark-style="$satellite ? route('map.satellite.style') : null"
                theme="auto"
                :max-zoom="$satellite ? 19 : 22"
                class="h-full w-full"
                :events="['click', 'marker-clicked']"
            >
                <x-map-controls
                    :zoom="true"
                    :compass="true"
                    :locate="false"
                    :fullscreen="true"
                    :scale="true"
                    position="top-right"
                />

                @if($userLat && $userLng)
                    <x-map-marker
                        :key="'user-location'"
                        wire:key="marker-user-location"
                        :lat="$userLat"
                        :lng="$userLng"
                        color="#22c55e"
                        id="user-location"
                    >
                        <x-marker-content>
                            <div class="relative flex h-10 w-10 items-center justify-center">
                                @if(!empty($routeCoords))
                                    <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span>
                                @endif
                                <div class="relative flex h-8 w-8 items-center justify-center rounded-full border-2 shadow-lg transition-colors"
                                     :class="{ 'bg-blue-500 border-white': @js(!empty($routeCoords)), 'bg-green-500 border-white': @js(empty($routeCoords)) }">
                                    <svg x-show="userHeading !== null && userHeading !== undefined"
                                         :style="'transform: rotate(' + (userHeading || 0) + 'deg)'"
                                         class="h-4 w-4 text-white"
                                         fill="currentColor"
                                         viewBox="0 0 24 24">
                                        <path d="M12 2 L19 21 L12 17 L5 21 Z" />
                                    </svg>
                                    <svg x-show="!userHeading"
                                         class="h-3 w-3 text-white"
                                         fill="currentColor"
                                         viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="4" />
                                    </svg>
                                </div>
                            </div>
                        </x-marker-content>
                        <x-marker-popup>
                            <div class="p-2">
                                <strong class="text-gray-900 dark:text-white">
                                    {{ !empty($routeCoords) ? 'Route Start' : 'You are here' }}
                                </strong>
                                <button type="button" @click="$wire.shareLocation()" class="mt-1 block text-[11px] font-semibold text-primary-600 hover:text-blue-700 dark:text-blue-400">🔗 Share this location</button>
                            </div>
                        </x-marker-popup>
                    </x-map-marker>
                @endif

                @foreach($this->tenants as $tenant)
                    @php
                        $hue = ($loop->index * 137) % 360;
                        $tenantColor = 'hsl(' . $hue . ', 65%, 55%)';
                        $isRouteDestination = $routeTenantId === $tenant->id && !empty($routeCoords);
                        $isHighlighted = $highlightedId === $tenant->id;
                        $minPrice = $tenant->properties_min_price;
                    @endphp

                    @foreach($tenant->coordinates as $coordIndex => $coord)
                        @php
                            $isParent = $coordIndex === 0 || ($coord['type'] ?? '') === 'parent';
                            $logoUrl = $tenant->logo ? asset('storage/' . $tenant->logo) : null;

                            $coordType = $coord['type'] ?? null;
                            $isCategoryType = !$isParent && $coordType && isset($this->markerTypes[$coordType]);
                        @endphp

                        <x-map-marker
                            :key="'tenant-'.$tenant->id.'-'.$coordIndex"
                            wire:key="marker-{{ $tenant->id }}-{{ $coordIndex }}"
                            :lat="$coord['lat']"
                            :lng="$coord['lng']"
                            :color="$isParent ? $tenantColor : ($isCategoryType ? $this->markerColors[$coordType] : $tenantColor)"
                            id="tenant-{{ $tenant->id }}-{{ $coordIndex }}"
                        >
                            <x-marker-content>
                                @if($isParent)
                                    <div class="flex h-11 w-11 items-center justify-center rounded-full border-2 bg-white shadow-lg transition-all duration-200
                                                {{ $isRouteDestination || $isHighlighted ? 'ring-4 ring-blue-300/50 scale-110' : '' }}"
                                         style="border-color: {{ $tenantColor }};">
                                        @if($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $tenant->name }}"
                                                 class="h-full w-full rounded-full object-cover"
                                                 loading="lazy">
                                        @else
                                            <span class="text-sm font-black text-gray-800">
                                                {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                            </span>
                                        @endif
                                    </div>
                                @elseif($isCategoryType)
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 bg-white shadow-lg text-xl transition-all duration-200
                                                {{ $isRouteDestination || $isHighlighted ? 'ring-4 ring-blue-300/50 scale-110' : '' }}"
                                         style="border-color: {{ $this->markerColors[$coordType] }};">
                                        <span class="leading-none">{{ $this->markerEmojis[$coordType] }}</span>
                                    </div>
                                @else
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 bg-white shadow transition-all duration-200
                                                {{ $isRouteDestination || $isHighlighted ? 'ring-4 ring-blue-300/50 scale-110' : '' }}"
                                         style="border-color: {{ $tenantColor }};">
                                        <span class="block h-2.5 w-2.5 rounded-full" style="background: {{ $tenantColor }};"></span>
                                    </div>
                                @endif
                            </x-marker-content>

                            <x-marker-popup>
                                <div class="min-w-[260px] p-3">
                                    @if($logoUrl)
                                        <img src="{{ $logoUrl }}" alt="{{ $tenant->name }}" class="w-full h-24 object-cover rounded-lg mb-2">
                                    @endif
                                    <h3 class="font-bold text-gray-900 dark:text-white">{{ $coord['name'] ?? $tenant->name }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $isParent ? ($tenant->typeOfTenant?->type ?? 'Business') : ($isCategoryType ? $this->markerTypes[$coordType] : 'Sub-location') }}
                                    </p>
                                    @if($isParent && $minPrice !== null)
                                        <p class="mt-1 text-xs font-semibold text-gray-900 dark:text-white">
                                            From ₱{{ number_format($minPrice, 2) }}
                                        </p>
                                    @endif
                                    @if($userLat && $userLng)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            📍 {{ $this->formatDistance($this->calculateDistance($coord['lat'], $coord['lng'])) }} away
                                        </p>
                                    @endif
                                    @if($tenant->address)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $tenant->address }}</p>
                                    @endif
                                    <div class="mt-3 flex gap-2">
                                        <a href="{{ route('business.offerings', $tenant->slug) }}" class="flex-1 rounded-lg bg-primary-600 px-3 py-2 text-center text-xs font-semibold text-white transition hover:bg-blue-700">View</a>
                                        <button type="button" wire:click="{{ $isParent ? 'getDirectionsTo('.$tenant->id.')' : 'getDirectionsToCoord('.$tenant->id.','.$coordIndex.')' }}" class="flex-1 rounded-lg border border-primary-600 px-3 py-2 text-xs font-semibold text-primary-600 transition hover:bg-blue-50 dark:hover:bg-blue-500/10">Directions</button>
                                    </div>
                                </div>
                            </x-marker-popup>
                        </x-map-marker>
                    @endforeach
                @endforeach

                {{-- Event Markers --}}
                @if($showEvents)
                    @foreach($this->eventMarkers as $event)
                        <x-map-marker
                            :key="'event-'.$event['id']"
                            wire:key="event-marker-{{ $event['id'] }}"
                            :lat="$event['lat']"
                            :lng="$event['lng']"
                            color="#8b5cf6"  {{-- Indigo/purple for events --}}
                            id="event-{{ $event['id'] }}"
                        >
                            <x-marker-content>
                                <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 bg-white shadow-lg text-xl transition-all duration-200"
                                     style="border-color: #8b5cf6;">
                                    <span class="leading-none">🎉</span>
                                </div>
                            </x-marker-content>
                            <x-marker-popup>
                                <div class="min-w-[240px] p-3">
                                    <h3 class="font-bold text-gray-900 dark:text-white">{{ $event['name'] }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        📅 {{ $event['start_date']->format('M d, Y') }}
                                        @if($event['end_date'] && $event['end_date'] != $event['start_date'])
                                            - {{ $event['end_date']->format('M d, Y') }}
                                        @endif
                                    </p>
                                    <p class="mt-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                        📍 {{ $event['barangay'] }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 capitalize">{{ $event['type'] }}</p>
                                    <a href="{{ route('events', ['event' => $event['id']]) }}"
                                       wire:navigate
                                       class="mt-2 inline-block text-xs font-semibold text-primary-600 hover:underline dark:text-blue-400">
                                        View Event Details →
                                    </a>
                                </div>
                            </x-marker-popup>
                        </x-map-marker>
                    @endforeach
                @endif

                {{-- Itinerary Path Layer --}}
                @if($itineraryEnabled && count($this->itineraryStops) > 0)
                    @php
                        $itinLine = $this->itineraryStops['line'];
                        $itinCoords = $this->itineraryStops['coords'];
                    @endphp
                    <x-map-route
                        wire:key="itinerary-route-{{ md5(json_encode($itinLine)) }}"
                        :coordinates="$itinLine"
                        color="#8b5cf6"
                        :width="4"
                        :opacity="0.9"
                        :dash-array="[12, 8]"
                    />

                    @foreach($itinCoords as $idx => $stop)
                        <x-map-marker
                            :key="'itin-stop-'.$idx"
                            wire:key="itinerary-marker-{{ $idx }}"
                            :lat="$stop['lat']"
                            :lng="$stop['lng']"
                            color="#8b5cf6"
                            id="itinerary-stop-{{ $idx }}"
                        >
                            <x-marker-content>
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-white border-2 shadow-lg font-bold text-sm text-purple-700"
                                     style="border-color: #8b5cf6;">
                                    {{ $idx + 1 }}
                                </div>
                            </x-marker-content>
                            <x-marker-popup>
                                <div class="p-2">
                                    <strong class="text-gray-900 dark:text-white">{{ $stop['name'] }}</strong>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Itinerary stop {{ $idx + 1 }}</p>
                                </div>
                            </x-marker-popup>
                        </x-map-marker>
                    @endforeach
                @endif

                @if(!empty($routeCoords))
                    <x-map-route
                        wire:key="route-primary-{{ md5(serialize($routeCoords)) }}-{{ $directionsProfile }}"
                        id="{{ $routeId }}"
                        :coordinates="[$routeCoords['start'], $routeCoords['end']]"
                        :fetch-directions="true"
                        :alternatives="true"
                        :directions-profile="$directionsProfile"
                        color="#22c55e"
                        :width="5"
                        :with-stops="true"
                        alternative-color="#06b6d4"
                    />

                    <x-map-route
                        wire:key="route-reference-{{ md5(serialize($routeCoords)) }}"
                        :coordinates="[$routeCoords['start'], $routeCoords['end']]"
                        color="#f59e0b"
                        :width="3"
                        :opacity="0.7"
                        :dash-array="[8, 6]"
                    />

                    <x-map-route-list
                        route-id="{{ $routeId }}"
                        map-id="tourist-map"
                        title="Available Routes"
                        width="w-60"
                        position="bottom-left"
                        container-class="z-[850] print:hidden"
                    />
                @endif
            </x-map>
        </div>

        {{-- Map tools toolbar (unchanged) --}}
        <div class="absolute left-3 top-3 z-[1000] flex flex-col gap-1.5 sm:left-4 sm:top-4 sm:gap-2 print:hidden" role="toolbar" aria-label="Map tools">
            <button type="button" @click="mobileOpen = true" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-lg transition hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400 lg:hidden" aria-label="Open destinations list" title="Destinations">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <button type="button" @click="locate()" :disabled="locating" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-lg transition hover:text-primary-600 disabled:cursor-wait disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400" aria-label="Use my location" title="Use my location (L)">
                <svg x-show="!locating" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21c-4.5-4.5-7.5-8.24-7.5-11.5A7.5 7.5 0 0112 2a7.5 7.5 0 017.5 7.5c0 3.26-3 7-7.5 11.5z"/><circle cx="12" cy="9.5" r="2.5" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                <svg x-show="locating" class="h-4 w-4 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            </button>

            <button type="button" @click="followMode ? stopFollowMode() : startFollowMode()" aria-pressed="{{ $followMode ? 'true' : 'false' }}" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border shadow-lg transition {{ $followMode ? 'border-primary-600 bg-primary-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400' }}" aria-label="Toggle follow mode" title="Follow my location (F)">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M12 2 L21 21 L12 17 L3 21 Z" fill="currentColor" stroke="none"/>
                </svg>
            </button>

            <button type="button" wire:click="fitAllLocations" @disabled(count($this->geoJsonData) < 2) class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-lg transition hover:text-primary-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:disabled:hover:text-gray-300" aria-label="Show all destinations" title="Fit all destinations">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M4 4l5 5M16 4h4v4M20 4l-5 5M4 16v4h4M4 20l5-5M16 20h4v-4M20 20l-5-5"/></svg>
            </button>

            <button type="button" wire:click="toggleSatellite" aria-pressed="{{ $satellite ? 'true' : 'false' }}" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border text-lg shadow-lg transition {{ $satellite ? 'border-primary-600 bg-primary-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400' }}" aria-label="Toggle satellite view" title="Satellite view (S)">🛰️</button>

            @if(!empty($routeCoords))
                <button type="button" wire:click="clearRoute" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-red-200 bg-white text-red-500 shadow-lg transition hover:bg-red-50 dark:border-red-500/30 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-500/10" aria-label="Cancel route" title="Cancel route">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif

            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-lg transition hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400" aria-label="More map options" title="More options">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.5" fill="currentColor" stroke="none"/></svg>
                </button>
                <div x-show="open" x-transition:enter="transition ease-out duration-120" x-transition:enter-start="opacity-0 -translate-x-1" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 -translate-x-1" style="display: none;" class="absolute left-full top-0 ml-2 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white py-1.5 shadow-xl dark:border-gray-700 dark:bg-gray-800" role="menu">
                    <button type="button" role="menuitem" @click="open = false; $wire.printMap()" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">🖨️ Print map</button>
                    <button type="button" role="menuitem" @click="open = false; $wire.shareLocation()" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">🔗 Share my location</button>
                    <button type="button" role="menuitem" @click="open = false; $wire.resetView()" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">🎯 Recenter map</button>
                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                    <button type="button" role="menuitem" @click="open = false; helpTrigger = $el; helpOpen = true" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">❓ Help &amp; shortcuts</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast stack (unchanged) --}}
    <div
        class="pointer-events-none fixed z-[1300] flex flex-col gap-2 print:hidden
               inset-x-4 top-20 items-center
               sm:inset-x-auto sm:right-4 sm:top-4 sm:items-end"
        aria-live="polite"
        aria-atomic="false"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div @mouseenter="pauseToast(toast)" @mouseleave="resumeToast(toast)"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl border bg-white/95 shadow-xl backdrop-blur dark:bg-gray-800/95 sm:w-auto"
                 :class="{
                    'border-emerald-200 dark:border-emerald-500/30': toast.type === 'success',
                    'border-red-200 dark:border-red-500/30': toast.type === 'error',
                    'border-blue-200 dark:border-blue-500/30': toast.type === 'info',
                    'border-amber-200 dark:border-amber-500/30': toast.type === 'warning'
                 }"
            >
                <div class="flex items-center gap-2.5 px-4 py-3 text-[13px] font-medium"
                     :class="{
                        'text-emerald-800 dark:text-emerald-300': toast.type === 'success',
                        'text-red-800 dark:text-red-300': toast.type === 'error',
                        'text-blue-800 dark:text-blue-300': toast.type === 'info',
                        'text-amber-800 dark:text-amber-300': toast.type === 'warning'
                     }"
                >
                    <span x-show="toast.type === 'success'">✅</span>
                    <span x-show="toast.type === 'error'">⚠️</span>
                    <span x-show="toast.type === 'info'">ℹ️</span>
                    <span x-show="toast.type === 'warning'">🔶</span>
                    <span class="flex-1" x-text="toast.message"></span>
                    <button type="button" @click="removeToast(toast.id)" class="text-gray-400 transition hover:text-gray-700 dark:hover:text-gray-200" aria-label="Dismiss notification">✕</button>
                </div>
                <div class="h-0.5 w-full bg-black/5 dark:bg-white/5">
                    <div class="h-full transition-[width] duration-100 ease-linear motion-reduce:transition-none"
                         :class="{
                            'bg-emerald-400': toast.type === 'success',
                            'bg-red-400': toast.type === 'error',
                            'bg-blue-400': toast.type === 'info',
                            'bg-amber-400': toast.type === 'warning'
                         }"
                         :style="'width: ' + ((toast.remaining / toast.duration) * 100) + '%'"
                    ></div>
                </div>
            </div>
        </template>
    </div>

    {{-- Help modal (unchanged) --}}
    <div x-show="helpOpen" x-transition.opacity style="display: none;" class="fixed inset-0 z-[1500] flex items-center justify-center bg-black/50 p-4 print:hidden" role="dialog" aria-modal="true" aria-labelledby="help-modal-title" @click.self="helpOpen = false; helpTrigger?.focus(); helpTrigger = null" x-init="$watch('helpOpen', (open) => { if (open) $nextTick(() => $refs.helpCloseBtn && $refs.helpCloseBtn.focus()) })">
        <div x-show="helpOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h2 id="help-modal-title" class="text-[15px] font-extrabold text-gray-900 dark:text-white">Legend &amp; shortcuts</h2>
                <button type="button" x-ref="helpCloseBtn" @click="helpOpen = false; helpTrigger?.focus(); helpTrigger = null" class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200" aria-label="Close help">✕</button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                <section>
                    <h3 class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Map legend</h3>
                    <ul class="space-y-2 text-[12.5px] text-gray-600 dark:text-gray-300">
                        <li class="flex items-center gap-2.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"></span> Your current location</li>
                        <li class="flex items-center gap-2.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-primary-600"></span> Destinations &amp; clusters</li>
                        <li class="flex items-center gap-2.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-purple-500"></span> Events</li>
                        <li class="flex items-center gap-2.5"><span class="h-1 w-4 shrink-0 rounded-full bg-emerald-500"></span> Active route</li>
                        <li class="flex items-center gap-2.5"><span class="h-1 w-4 shrink-0 rounded-full bg-cyan-500"></span> Alternative route</li>
                        <li class="flex items-center gap-2.5"><span class="h-1 w-4 shrink-0 rounded-full border-t-2 border-dashed border-amber-500"></span> Straight-line reference</li>
                    </ul>
                </section>
                <section class="mt-5">
                    <h3 class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Keyboard shortcuts</h3>
                    <dl class="space-y-1.5 text-[12.5px]">
                        @foreach(['/' => 'Focus search','L' => 'Use my location','F' => 'Toggle follow mode','S' => 'Toggle satellite view','P' => 'Print map','R' => 'Clear filters','Esc' => 'Close panels','?' => 'Toggle this help'] as $key => $label)
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600 dark:text-gray-300">{{ $label }}</dt>
                                <dd><kbd class="rounded-md border border-gray-300 bg-gray-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-gray-600 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300">{{ $key }}</kbd></dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            </div>
        </div>
    </div>
</div>