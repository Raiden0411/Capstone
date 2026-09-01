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
use App\Models\Event;
use App\Models\SiteSetting;
use App\Scopes\TenantScope;

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
    public bool $showEvents = false;

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

    public int $userLocationVersion = 0;
    public int $mapRefreshVersion   = 0;

    public string $filtersHash = '';
    public int $routeVersion = 0;
    public ?array $pendingFitBounds = null;

    public ?int $pendingDirectionsTenantId = null;
    public ?int $pendingDirectionsCoordIndex = 0;

    public int $themeVersion = 0;

    private const CITY_CENTER = [123.07391289720677, 10.900736693923502];

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
            $this->showEvents,
        ]));
    }

    protected function hydrateFromQueryString(): void
    {
        if (request()->filled('lat') && request()->filled('lng')) {
            $this->userLat = (float) request('lat');
            $this->userLng = (float) request('lng');
            $this->currentLat = $this->userLat;
            $this->currentLng = $this->userLng;
            $this->userLocationVersion++;
            $this->mapRefreshVersion++;
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
                'typeOfTenant:id,type',
                'settings' => fn ($q) => $q->where('key', 'business_info')->select('tenant_id', 'value'),
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
            ->get([
                'id', 'name', 'slug', 'logo', 'address', 'contact_number',
                'email', 'coordinates', 'is_recommended', 'type_of_tenant_id',
                'created_at',
            ]);

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

        return Event::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('coordinates')
            ->where('is_active', true)
            ->where('start_date', '>=', now()->subDay())
            ->with('tenant:id,name,slug')
            ->get(['id', 'name', 'barangay', 'type', 'start_date', 'end_date', 'coordinates', 'featured', 'tenant_id'])
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
            $status = $this->statusLabel($this->tenantOpenStatus($tenant));

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

    protected function statusLabel(?bool $isOpen): string
    {
        return match ($isOpen) {
            true    => 'Open now',
            false   => 'Closed now',
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

    public function favoritesStorageKey(): string
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
    public function updatedShowEvents(): void { $this->filtersHash = $this->computeFiltersHash(); }

    public function updatedSortBy(string $value): void
    {
        if ($value === 'distance' && !$this->userLat) {
            $this->dispatch('request-location-for-distance');
        }
    }

    public function setUserLocation($lat, $lng): void
    {
        $oldLat = $this->userLat;
        $oldLng = $this->userLng;

        $this->userLat = round((float) $lat, 6);
        $this->userLng = round((float) $lng, 6);
        $this->currentLat = $this->userLat;
        $this->currentLng = $this->userLng;
        $this->currentZoom = 15;

        $this->userLocationVersion++;

        $firstLocation = ($oldLat === null || $oldLng === null);
        $distanceMoved = ($oldLat !== null && $oldLng !== null)
            ? $this->calculateDistance($oldLat, $oldLng)
            : 0;

        if ($firstLocation || $distanceMoved > 0.1) {
            $this->mapRefreshVersion++;
        }

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
            $eventId = (int) substr($id, 6);
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
        $this->dispatch('map:fly-to', center: self::CITY_CENTER, zoom: 12);
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
                    'typeOfTenant:id,type',
                    'settings' => fn ($q) => $q->where('key', 'business_info')->select('tenant_id', 'value'),
                ])
                ->select([
                    'id', 'name', 'slug', 'logo', 'address', 'contact_number',
                    'email', 'coordinates', 'is_recommended', 'type_of_tenant_id',
                    'created_at',
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

    public function refreshRoute(): void
    {
        $this->routeVersion++;
    }

    public function handleThemeChange(): void
    {
        $this->themeVersion++;
        $this->mapRefreshVersion++;
        $this->routeVersion++;
        $this->dispatch('map:resize');
    }

    #[Computed]
    public function markerCategories(): array
    {
        return SiteSetting::getValue('marker_categories', []);
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
        viewportTimer: null,
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

        addRecent(detail) { console.log('Tenant viewed:', detail); },

        locate() {
            if (!navigator.geolocation) { $wire.locationFailed('unavailable'); return; }
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
            if (!navigator.geolocation) { $wire.locationFailed('unavailable'); return; }
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
                    $wire.setUserLocation(pos.coords.latitude, pos.coords.longitude);
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
     x-on:theme-changed.window="$wire.handleThemeChange()"
     x-on:map:center-changed.window="
        viewport.lat = $event.detail.lat;
        viewport.lng = $event.detail.lng;
        clearTimeout(viewportTimer);
        viewportTimer = setTimeout(() => $wire.updateViewport(viewport.lat, viewport.lng, viewport.zoom), 500);
     "
     x-on:map:zoom-changed.window="
        viewport.zoom = $event.detail.zoom;
        clearTimeout(viewportTimer);
        viewportTimer = setTimeout(() => $wire.updateViewport(viewport.lat, viewport.lng, viewport.zoom), 500);
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
        @click="mobileOpen = false; sidebarOpen = false"
        aria-hidden="true"
    ></div>

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-[1100] w-[85%] max-w-[360px] -translate-x-full border-r border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out motion-reduce:transition-none dark:border-gray-700 dark:bg-gray-900 lg:static lg:z-auto lg:max-w-none lg:translate-x-0 lg:overflow-hidden lg:transition-[width] lg:duration-300 print:hidden"
        :class="[
            sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            sidebarOpen ? 'lg:w-[360px]' : 'lg:w-0',
        ]"
        role="dialog"
        aria-modal="true"
        aria-label="Destinations sidebar"
    >
        <div class="relative h-full w-[85vw] max-w-[360px] lg:w-[360px]">
            <button
                type="button"
                @click="sidebarOpen = false; mobileOpen = false"
                class="absolute right-3 top-3 z-10 inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg lg:hidden"
                aria-label="Close destinations list"
            >
                <svg class="size-2.5 shrink-0 stroke-current stroke-[2.5] fill-none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            @include('livewire.partials.explore-sidebar')
        </div>
    </aside>

    {{-- Map Area --}}
    <div class="relative h-full min-w-0 flex-1 bg-gray-100 dark:bg-gray-800 print:bg-white">

        <div class="absolute inset-0 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 print:hidden" aria-hidden="true"></div>

        <div
            x-show="!online"
            x-transition
            class="absolute inset-x-0 top-0 z-[1200] flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-center text-[12px] font-semibold text-white print:hidden"
            role="status"
        >
            <svg class="size-4 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            You're offline — map tiles and directions may not load until your connection returns.
        </div>

        <div class="pointer-events-none absolute left-4 top-4 z-[50] hidden rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-900 print:block">
            Explore Map — {{ now()->format('F j, Y') }} · {{ $this->tenants->count() }} {{ Str::plural('destination', $this->tenants->count()) }} shown
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
            wire:key="tourist-map-{{ $satellite ? 'satellite' : 'normal' }}-{{ $filtersHash }}-{{ $routeVersion }}-{{ $itineraryEnabled ? 'itinerary' : 'no-itinerary' }}-{{ $showEvents ? 'events' : 'no-events' }}-{{ $mapRefreshVersion }}-{{ $themeVersion }}"
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
                @map:load="$event.detail.map?.setRenderWorldCopies(false); $event.detail.map?.setMaxBounds([[122.0, 9.5], [124.0, 11.8]]); $event.detail.map?.setMinZoom(10);"
            >
                <x-map-controls
                    :zoom="true"
                    :compass="true"
                    :locate="false"
                    :fullscreen="true"
                    :scale="true"
                    position="top-right"
                />

                {{-- USER LOCATION MARKER --}}
                @if($userLat && $userLng)
                    <x-map-marker
                        :key="'user-location-'.$userLocationVersion"
                        wire:key="marker-user-location-{{ $userLocationVersion }}"
                        :lat="$userLat"
                        :lng="$userLng"
                        color="#3b82f6"
                        id="user-location"
                        anchor="center"
                    >
                        <x-marker-content>
                            <div class="relative flex h-16 w-16 items-center justify-center pointer-events-none group transform-gpu will-change-transform">
                                <div
                                    class="absolute inset-0 transition-transform duration-300 ease-out origin-center"
                                    :style="'transform: rotate(' + (userHeading || 0) + 'deg)'"
                                >
                                    <svg viewBox="0 0 64 64" class="h-full w-full drop-shadow-sm">
                                        <defs>
                                            <linearGradient id="heading-gradient-{{ $userLocationVersion }}" x1="0%" y1="100%" x2="0%" y2="0%">
                                                <stop offset="0%" stop-color="#3b82f6" stop-opacity="0" />
                                                <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.5" />
                                            </linearGradient>
                                        </defs>
                                        <path d="M 32 32 L 10 0 A 32 32 0 0 1 54 0 Z" fill="url(#heading-gradient-{{ $userLocationVersion }})" />
                                    </svg>
                                </div>

                                @if($followMode)
                                    <div class="absolute h-10 w-10 rounded-full bg-blue-500/25 animate-ping"></div>
                                @endif
                                <div class="absolute h-12 w-12 rounded-full bg-blue-500/15 border border-blue-400/30"></div>

                                <div class="relative flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 border-2 border-white dark:border-gray-900 shadow-lg ring-4 ring-blue-500/30">
                                    <div class="h-2 w-2 rounded-full bg-white"></div>
                                </div>
                            </div>
                        </x-marker-content>
                        <x-marker-popup>
                            <div class="p-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md rounded-2xl border border-gray-100 dark:border-gray-800 shadow-xl min-w-[180px]">
                                <strong class="text-xs font-black tracking-wide uppercase text-gray-900 dark:text-white">
                                    {{ !empty($routeCoords) ? 'Route Origin' : 'Current Location' }}
                                </strong>
                                <button type="button" @click="$wire.shareLocation()"
                                        class="mt-2 inline-flex w-full items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary-50 dark:bg-blue-950/50 text-[11px] font-bold text-primary-600 dark:text-blue-400 hover:bg-primary-100 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:opacity-50 disabled:pointer-events-none">
                                    <svg class="size-3 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                    </svg>
                                    Share Location
                                </button>
                            </div>
                        </x-marker-popup>
                    </x-map-marker>
                @endif

                {{-- TENANT MARKERS --}}
                @if(!$showEvents)
                    @foreach($this->tenants as $tenant)
                        @php
                            $tenantColors = ['#f97316','#a855f7','#3b82f6','#14b8a6','#eab308','#64748b','#10b981','#8b5cf6'];
                            $tenantColor = $tenantColors[$loop->index % count($tenantColors)];
                            $isRouteDestination = $routeTenantId === $tenant->id && !empty($routeCoords);
                            $isHighlighted = $highlightedId === $tenant->id;
                            $minPrice = $tenant->properties_min_price;
                        @endphp

                        @foreach($tenant->coordinates as $coordIndex => $coord)
                            @php
                                $isParent = $coordIndex === 0 || ($coord['type'] ?? '') === 'parent';
                                $logoUrl = $tenant->logo ? asset('storage/' . $tenant->logo) : null;
                                $coordType = $coord['type'] ?? null;
                                $isCategoryType = !$isParent && $coordType && collect($this->markerCategories)->contains('key', $coordType);
                                $activeColor = $isParent ? $tenantColor : ($isCategoryType ? collect($this->markerCategories)->firstWhere('key', $coordType)['color'] ?? $tenantColor : $tenantColor);
                            @endphp

                            <x-map-marker
                                :key="'tenant-'.$tenant->id.'-'.$coordIndex"
                                wire:key="marker-{{ $tenant->id }}-{{ $coordIndex }}"
                                :lat="$coord['lat']"
                                :lng="$coord['lng']"
                                :color="$activeColor"
                                id="tenant-{{ $tenant->id }}-{{ $coordIndex }}"
                                anchor="bottom"
                            >
                                <x-marker-content>
                                    <div class="flex flex-col items-center group cursor-pointer transform-gpu will-change-transform">
                                        @if($isParent)
                                            <div class="flex h-12 w-12 items-center justify-center rounded-full border-2 bg-white dark:bg-gray-900 shadow-lg transition-all duration-300 group-hover:scale-110 active:scale-95
                                                        {{ $isRouteDestination || $isHighlighted ? 'ring-2 ring-primary-500 scale-110' : '' }}"
                                                 style="border-color: {{ $tenantColor }};">
                                                @if($logoUrl)
                                                    <img src="{{ $logoUrl }}" alt="{{ $tenant->name }}" class="h-full w-full rounded-full object-cover" loading="lazy">
                                                @else
                                                    <span class="text-xs font-black text-gray-800 dark:text-white tracking-tighter">{{ strtoupper(substr($tenant->name, 0, 2)) }}</span>
                                                @endif
                                            </div>
                                        @elseif($isCategoryType)
                                            @php
                                                $category = collect($this->markerCategories)->firstWhere('key', $coordType);
                                                $iconSvg = $category['icon_svg'] ?? null;
                                                $color = $category['color'] ?? '#94a3b8';
                                            @endphp
                                            <div class="relative flex h-10 w-10 items-center justify-center
                                                        transform-gpu will-change-transform transition-transform duration-200
                                                        group-hover:scale-110 active:scale-95
                                                        {{ $isRouteDestination || $isHighlighted ? 'ring-2 ring-primary-500 rounded-full scale-110' : '' }}">
                                                <svg class="absolute inset-0 size-10 drop-shadow-md
                                                            fill-white dark:fill-gray-900
                                                            stroke-slate-400 dark:stroke-slate-600 stroke-1"
                                                     viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                </svg>
                                                @if($iconSvg)
                                                    <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                        {!! str_replace('<svg ', '<svg class="size-full stroke-current fill-none" ', $iconSvg) !!}
                                                    </div>
                                                @else
                                                    <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">
                                                        {{ strtoupper(substr($coordType, 0, 1)) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 bg-white dark:bg-gray-900 shadow-sm transition-all duration-300 group-hover:scale-110 active:scale-95
                                                        {{ $isRouteDestination || $isHighlighted ? 'ring-2 ring-primary-500 scale-110' : '' }}"
                                                 style="--marker-color: {{ $tenantColor }}; border-color: var(--marker-color);">
                                                <span class="block h-2 w-2 rounded-full" style="background: var(--marker-color);"></span>
                                            </div>
                                        @endif

                                        <svg class="w-2 h-1.5 text-current -mt-0.5" style="color: {{ $activeColor }};" viewBox="0 0 12 8" fill="currentColor"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                    </div>
                                </x-marker-content>

                                <x-marker-popup>
                                    <div class="min-w-[260px] max-w-[280px] p-4 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-800">
                                        @if($logoUrl && $isParent)
                                            <div class="relative h-28 w-full mb-3 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                                <img src="{{ $logoUrl }}" alt="{{ $tenant->name }}" class="w-full h-full object-cover">
                                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                                                <span class="absolute bottom-2 left-2 text-[10px] font-bold uppercase tracking-wider text-white bg-black/40 px-2 py-0.5 rounded-md backdrop-blur-sm">
                                                    {{ $tenant->typeOfTenant?->type ?? 'Business' }}
                                                </span>
                                            </div>
                                        @endif

                                        <h3 class="font-extrabold text-gray-900 dark:text-white text-base leading-tight">{{ $coord['name'] ?? $tenant->name }}</h3>

                                        <p class="text-xs font-semibold text-primary-600 dark:text-primary-400 mt-0.5">
                                            {{ $isParent ? ($tenant->typeOfTenant?->type ?? 'Business') : ($isCategoryType ? ($category['label'] ?? 'Sub-location') : 'Sub-location') }}
                                        </p>

                                        <div class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-300">
                                            @if($isParent && $minPrice !== null)
                                                <p class="font-bold text-gray-900 dark:text-white text-sm">Starting at ₱{{ number_format($minPrice, 2) }}</p>
                                            @endif
                                            @if($userLat && $userLng)
                                                <p class="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                                                    <svg class="size-2.5 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                        <circle cx="12" cy="10" r="2" stroke="currentColor" fill="none"/>
                                                    </svg>
                                                    {{ $this->formatDistance($this->calculateDistance($coord['lat'], $coord['lng'])) }} away
                                                </p>
                                            @endif
                                        </div>

                                        <div class="mt-4 flex gap-2">
                                            <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate
                                               wire:loading.attr="disabled"
                                               class="flex-1 inline-flex items-center justify-center rounded-xl bg-primary-600 px-3 py-2 text-center text-xs font-bold text-white shadow-sm hover:bg-primary-700 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:opacity-50 disabled:pointer-events-none">
                                                <span wire:loading.remove>View</span>
                                                <span wire:loading>
                                                    <svg class="animate-spin size-3 inline-block" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                </span>
                                            </a>
                                            <button type="button"
                                                    wire:click="{{ $isParent ? 'getDirectionsTo('.$tenant->id.')' : 'getDirectionsToCoord('.$tenant->id.','.$coordIndex.')' }}"
                                                    wire:loading.attr="disabled"
                                                    class="flex-1 inline-flex items-center justify-center rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 hover:border-primary-500 hover:text-primary-600 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:opacity-50 disabled:pointer-events-none">
                                                <span wire:loading.remove>Directions</span>
                                                <span wire:loading>
                                                    <svg class="animate-spin size-3 inline-block" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </x-marker-popup>
                            </x-map-marker>
                        @endforeach
                    @endforeach
                @endif

                {{-- Event Markers --}}
                @if($showEvents)
                    @foreach($this->eventMarkers as $event)
                        <x-map-marker
                            :key="'event-'.$event['id']"
                            wire:key="event-marker-{{ $event['id'] }}"
                            :lat="$event['lat']"
                            :lng="$event['lng']"
                            color="#8b5cf6"
                            id="event-{{ $event['id'] }}"
                            anchor="bottom"
                        >
                            <x-marker-content>
                                <div class="flex flex-col items-center group cursor-pointer transform-gpu will-change-transform">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-tr from-purple-600 to-pink-500 border-2 border-white dark:border-gray-900 shadow-lg transition-all duration-300 group-hover:scale-110 active:scale-95">
                                        <svg class="size-4 text-white stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                    </div>
                                    <svg class="w-2 h-1.5 text-current -mt-0.5" style="color: #8b5cf6;" viewBox="0 0 12 8" fill="currentColor"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                </div>
                            </x-marker-content>
                            <x-marker-popup>
                                <div class="min-w-[220px] p-4 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md rounded-2xl border border-gray-100 dark:border-gray-800 shadow-xl">
                                    <span class="inline-block px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/50 rounded-md mb-2">Event</span>
                                    <h3 class="font-extrabold text-gray-900 dark:text-white text-sm leading-snug">{{ $event['name'] }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium flex items-center gap-1">
                                        <svg class="size-3 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        {{ $event['start_date']->format('M d, Y') }}
                                    </p>
                                    <a href="{{ route('events', ['event' => $event['id']]) }}" wire:navigate
                                       wire:loading.attr="disabled"
                                       class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-primary-600 hover:bg-primary-700 py-2 text-xs font-bold text-white transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 disabled:opacity-50 disabled:pointer-events-none">
                                        <span wire:loading.remove>View Event</span>
                                        <span wire:loading>
                                            <svg class="animate-spin size-3 inline-block" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                        </span>
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
                        color="#6366f1"
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
                            color="#6366f1"
                            id="itinerary-stop-{{ $idx }}"
                            anchor="bottom"
                        >
                            <x-marker-content>
                                <div class="flex flex-col items-center group cursor-pointer transform-gpu will-change-transform">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 border-2 border-white dark:border-gray-900 shadow-lg font-black text-xs text-white transition-all duration-300 group-hover:scale-110 active:scale-95">
                                        {{ $idx + 1 }}
                                    </div>
                                    <svg class="w-2 h-1.5 text-current -mt-0.5" style="color: #6366f1;" viewBox="0 0 12 8" fill="currentColor"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                </div>
                            </x-marker-content>
                            <x-marker-popup>
                                <div class="p-3 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md rounded-2xl border border-gray-100 dark:border-gray-800 shadow-xl min-w-[160px]">
                                    <span class="text-[10px] font-black uppercase text-indigo-600 dark:text-indigo-400">Stop {{ $idx + 1 }}</span>
                                    <strong class="text-xs font-extrabold text-gray-900 dark:text-white block mt-0.5">{{ $stop['name'] }}</strong>
                                </div>
                            </x-marker-popup>
                        </x-map-marker>
                    @endforeach
                @endif

                {{-- Active Route --}}
                @if(!empty($routeCoords))
                    <x-map-route
                        wire:key="route-primary-{{ md5(serialize($routeCoords)) }}-{{ $directionsProfile }}-{{ $routeVersion }}"
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
                        wire:key="route-reference-{{ md5(serialize($routeCoords)) }}-{{ $routeVersion }}"
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

        {{-- Map tools toolbar --}}
        <div class="absolute left-3 top-3 z-[1000] flex flex-col gap-1.5 sm:left-4 sm:top-4 sm:gap-2 print:hidden" role="toolbar" aria-label="Map tools">
            <button type="button" @click="sidebarOpen = !sidebarOpen"
                    class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg"
                    :aria-expanded="sidebarOpen.toString()"
                    aria-label="Toggle destinations sidebar"
                    title="Toggle destinations"
            >
                <svg class="size-5 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <button type="button" @click="locate()" :disabled="locating"
                    class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg"
                    aria-label="Use my location" title="Use my location (L)">
                <svg x-show="!locating" class="size-5 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21c-4.5-4.5-7.5-8.24-7.5-11.5A7.5 7.5 0 0112 2a7.5 7.5 0 017.5 7.5c0 3.26-3 7-7.5 11.5z"/>
                    <circle cx="12" cy="9.5" r="2.5" stroke-width="2" fill="none"/>
                </svg>
                <svg x-show="locating" class="size-5 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </button>

            <button type="button" @click="followMode ? stopFollowMode() : startFollowMode()" aria-pressed="{{ $followMode ? 'true' : 'false' }}"
                    class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg
                           {{ $followMode ? 'bg-primary-600 text-white hover:bg-primary-700' : '' }}"
                    aria-label="Toggle follow mode" title="Follow my location (F)">
                <svg class="size-5 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2 L21 21 L12 17 L3 21 Z"/>
                </svg>
            </button>

            <button type="button" wire:click="fitAllLocations" wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg"
                    aria-label="Go to city center" title="Fit all destinations (city center)">
                <svg class="size-5 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="8" stroke-width="2" />
                    <line x1="12" y1="2" x2="12" y2="4" stroke-width="2" />
                    <line x1="12" y1="20" x2="12" y2="22" stroke-width="2" />
                    <line x1="2" y1="12" x2="4" y2="12" stroke-width="2" />
                    <line x1="20" y1="12" x2="22" y2="12" stroke-width="2" />
                </svg>
            </button>

            <button type="button" wire:click="toggleSatellite" wire:loading.attr="disabled" aria-pressed="{{ $satellite ? 'true' : 'false' }}"
                    class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg
                           {{ $satellite ? 'bg-primary-600 text-white hover:bg-primary-700' : '' }}"
                    aria-label="Toggle satellite view" title="Satellite view (S)">
                <svg class="size-5 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 11a8 8 0 00-14 0M4 13a8 8 0 0014 0M12 12v3m0 0a9 9 0 01-9 9m9-9a9 9 0 019 9"/>
                </svg>
            </button>

            @if(!empty($routeCoords))
                <button type="button" wire:click="clearRoute" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-500/10 dark:text-red-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg"
                        aria-label="Cancel route" title="Cancel route">
                    <svg class="size-2.5 stroke-current stroke-[2.5] fill-none" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            @endif

            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
                        class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-800 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg"
                        aria-label="More map options" title="More options">
                    <svg class="size-5 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="5" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                        <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                        <circle cx="19" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                </button>
                <div x-show="open" x-transition:enter="transition ease-out duration-120" x-transition:enter-start="opacity-0 -translate-x-1" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 -translate-x-1" style="display: none;" class="absolute left-full top-0 ml-2 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white py-1.5 shadow-xl dark:border-gray-700 dark:bg-gray-800" role="menu">
                    <button type="button" role="menuitem" @click="open = false; $wire.printMap()"
                            class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 dark:text-gray-200 dark:hover:bg-gray-700/60">
                        <svg class="size-2.5 stroke-current stroke-[2.5] fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Print map
                    </button>
                    <button type="button" role="menuitem" @click="open = false; $wire.shareLocation()"
                            class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 dark:text-gray-200 dark:hover:bg-gray-700/60">
                        <svg class="size-4 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                        </svg>
                        Share my location
                    </button>
                    <button type="button" role="menuitem" @click="open = false; $wire.resetView()"
                            class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 dark:text-gray-200 dark:hover:bg-gray-700/60">
                        <svg class="size-4 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 11a8 8 0 00-14 0M4 13a8 8 0 0014 0"/>
                        </svg>
                        Recenter map
                    </button>
                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                    <button type="button" role="menuitem" @click="open = false; helpTrigger = $el; helpOpen = true"
                            class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 dark:text-gray-200 dark:hover:bg-gray-700/60">
                        <svg class="size-4 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9.75A5 5 0 0012 15a5 5 0 004.772-3.25M9.75 9.75h4.5m-4.5 4.5h4.5M12 21a9 9 0 110-18 9 9 0 010 18z"/>
                        </svg>
                        Help &amp; shortcuts
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast stack --}}
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
                    <span x-show="toast.type === 'success'">
                        <svg class="size-4 text-emerald-500 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <span x-show="toast.type === 'error'">
                        <svg class="size-4 text-red-500 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </span>
                    <span x-show="toast.type === 'info'">
                        <svg class="size-4 text-blue-500 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <span x-show="toast.type === 'warning'">
                        <svg class="size-4 text-amber-500 stroke-current stroke-2 fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </span>
                    <span class="flex-1" x-text="toast.message"></span>
                    <button type="button" @click="removeToast(toast.id)"
                            class="inline-flex items-center justify-center p-1 text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg"
                            aria-label="Dismiss notification">
                        <svg class="size-2.5 stroke-current stroke-[2.5] fill-none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
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

    {{-- Help modal --}}
    <div x-show="helpOpen" x-transition.opacity style="display: none;" class="fixed inset-0 z-[1500] flex items-center justify-center bg-black/50 p-4 print:hidden" role="dialog" aria-modal="true" aria-labelledby="help-modal-title" @click.self="helpOpen = false; helpTrigger?.focus(); helpTrigger = null" x-init="$watch('helpOpen', (open) => { if (open) $nextTick(() => $refs.helpCloseBtn && $refs.helpCloseBtn.focus()) })">
        <div x-show="helpOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h2 id="help-modal-title" class="text-[15px] font-extrabold text-gray-900 dark:text-white">Legend &amp; shortcuts</h2>
                <button type="button" x-ref="helpCloseBtn" @click="helpOpen = false; helpTrigger?.focus(); helpTrigger = null"
                        class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 active:scale-95 disabled:opacity-50 disabled:pointer-events-none rounded-lg"
                        aria-label="Close help">
                    <svg class="size-2.5 stroke-current stroke-[2.5] fill-none" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
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