{{-- resources/views/public/pages/⚡explore-map.blade.php --}}
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
use App\Services\OsrmDistanceService;

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

    #[Url(as: 'establishments', history: true)]
    public bool $showAmenities = false;

    public ?float $userLat = null;
    public ?float $userLng = null;
    public bool   $followMode  = false;
    public bool   $satellite   = false;

    /**
     * Map theme — decoupled from the global site theme.
     *
     * 'light' | 'dark'. Persisted in session so it survives navigation.
     * Toggled only via the dedicated map control (desktop toolbar /
     * mobile actions sheet) — see toggleMapTheme().
     */
    public string $mapTheme = 'light';

    public bool   $sidebarOpen = true;
    public bool   $navigationActive = false;

    public ?int    $highlightedId        = null;
    public array   $routeCoords          = [];
    public ?string $routeDestinationName = null;
    public ?int    $routeTenantId        = null;
    public string  $routeId              = 'tourist-route';
    public string  $directionsProfile    = 'driving';

    /** @var array<int, array{0: float, 1: float}> */
    public array $routePolyline = [];

    public float $routeDistanceKm = 0.0;
    public int   $routeDurationMin = 0;

    public ?int   $pendingMarkerId = null;
    public ?int   $pendingEventId  = null;
    public ?int   $highlightedEventId = null;
    public bool   $autoDirections = false;
    public array  $favorites = [];

    public ?float $currentLat  = null;
    public ?float $currentLng  = null;
    public ?int   $currentZoom = null;

    public int $mapForceReload = 0;
    public int $themeVersion = 0;
    public int $mapEpoch = 0;

    private string $filtersHash         = '';
    private int    $routeVersion        = 0;
    private int    $userLocationVersion = 0;
    private int    $mapRefreshVersion   = 0;

    public ?int $pendingDirectionsTenantId  = null;
    public int  $pendingDirectionsCoordIndex = 0;

    public ?int $detailTenantId   = null;
    public int  $detailCoordIndex = 0;

    public string $osrmUrl = '';

    public array $pendingRouteBounds = [];
    public array $drivingDistances = [];

    private const CITY_CENTER = [123.07391289720677, 10.900736693923502];

    public function mount(): void
    {
        $this->osrmUrl     = (string) (config('livewire-mapcn.osrm_url') ?? 'https://router.project-osrm.org');
        $this->favorites   = session($this->favoritesKey(), []);
        $this->sidebarOpen = request()->cookie('map_sidebar_open') !== '0';

        $theme = (string) session('map_theme', 'light');
        $this->mapTheme = in_array($theme, ['light', 'dark'], true) ? $theme : 'light';

        $this->filtersHash = $this->hashFilters();
        $this->hydrateQs();
    }

    protected function hashFilters(): string
    {
        return md5(json_encode([
            $this->search, $this->categoryFilter, $this->openNow,
            $this->hasOfferings, $this->favoritesOnly,
            $this->recommendedOnly, $this->showEvents,
        ]));
    }

    protected function hydrateQs(): void
    {
        if (request()->filled('lat') && request()->filled('lng')) {
            $this->userLat = $this->currentLat = (float) request('lat');
            $this->userLng = $this->currentLng = (float) request('lng');
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

        if (request()->filled('event')) {
            $this->pendingEventId     = (int) request('event');
            $this->highlightedEventId = $this->pendingEventId;
            $this->showEvents         = true;
        }
    }

    #[Computed]
    public function tenants()
    {
        $term = trim($this->search);

        $tenants = Tenant::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->whereNotNull('coordinates')
            ->with([
                'typeOfTenant:id,type',
                'settings' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('key', 'business_info')
                    ->select('tenant_id', 'value'),
            ])
            ->selectRaw('tenants.*')
            ->selectRaw('(SELECT COUNT(*) FROM properties WHERE properties.tenant_id = tenants.id) as properties_count')
            ->selectRaw('(SELECT COUNT(*) FROM services   WHERE services.tenant_id  = tenants.id) as services_count')
            ->selectRaw('(SELECT MIN(price) FROM properties WHERE properties.tenant_id = tenants.id) as min_price')
            ->when($term, fn ($q) => $q->where(fn ($s) =>
                $s->where('name', 'like', "%{$term}%")
                  ->orWhere('address', 'like', "%{$term}%")
                  ->orWhereHas('typeOfTenant', fn ($t) => $t->where('type', 'like', "%{$term}%"))
            ))
            ->when($this->categoryFilter, fn ($q) =>
                $q->whereHas('typeOfTenant', fn ($s) => $s->where('type', $this->categoryFilter))
            )
            ->when($this->favoritesOnly, fn ($q) => $q->whereIn('id', $this->favorites ?: [0]))
            ->when($this->recommendedOnly, fn ($q) => $q->where('is_recommended', true))
            ->get([
                'id', 'name', 'slug', 'logo', 'address', 'contact_number', 'email',
                'coordinates', 'is_recommended', 'type_of_tenant_id', 'created_at',
            ]);

        if ($this->hasOfferings) {
            $tenants = $tenants->filter(fn ($t) => $t->properties_count > 0 || $t->services_count > 0);
        }
        if ($this->openNow) {
            $tenants = $tenants->filter(fn ($t) => $this->isOpenNow($t));
        }

        return match ($this->sortBy) {
            'distance' => ($this->userLat && $this->userLng)
                ? $tenants->sortBy(fn ($t) => $this->distance($t->coordinates[0]['lat'], $t->coordinates[0]['lng']))->values()
                : $tenants->sortBy('name')->values(),
            'newest'   => $tenants->sortByDesc('created_at')->values(),
            'popular'  => $tenants->sortByDesc(fn ($t) => $t->properties_count + $t->services_count)->values(),
            default    => $tenants->sortBy('name')->values(),
        };
    }

    #[Computed]
    public function detailTenant(): ?Tenant
    {
        if (!$this->detailTenantId) return null;

        return Tenant::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->whereKey($this->detailTenantId)
            ->with([
                'typeOfTenant:id,type',
                'settings' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('key', 'business_info'),
                'properties' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('is_active', true)
                    ->orderBy('price')
                    ->limit(6)
                    ->select('id', 'tenant_id', 'name', 'price', 'capacity', 'quantity', 'property_type_id'),
                'properties.propertyType' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->select('id', 'name', 'tenant_id'),
                'properties.images' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->select('id', 'property_id', 'image_path')
                    ->orderBy('id'),
                'services' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('is_active', true)
                    ->orderBy('price')
                    ->limit(6)
                    ->select('id', 'tenant_id', 'name', 'price'),
            ])
            ->first([
                'id', 'name', 'slug', 'logo', 'address', 'barangay', 'contact_number',
                'email', 'coordinates', 'is_recommended', 'type_of_tenant_id', 'created_at',
            ]);
    }

    #[Computed]
    public function detailCoord(): ?array
    {
        $t = $this->detailTenant;
        if (!$t || empty($t->coordinates)) return null;

        return $t->coordinates[$this->detailCoordIndex]
            ?? $t->coordinates[0]
            ?? null;
    }

    #[Computed]
    public function detailIsEstablishment(): bool
    {
        if ($this->detailCoordIndex === 0) return false;

        $c = $this->detailCoord;
        if (!$c) return false;

        return ($c['type'] ?? '') !== 'parent';
    }

    #[Computed]
    public function detailCategory(): ?array
    {
        if (! $this->detailIsEstablishment) return null;

        $c = $this->detailCoord;
        if (!$c) return null;

        $type = $c['type'] ?? null;
        if (!$type) return null;

        return collect($this->markerCategories)->firstWhere('key', $type) ?: null;
    }

    #[Computed]
    public function detailUpcomingEvents()
    {
        if (!$this->detailTenantId) return collect();

        return Event::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->detailTenantId)
            ->where('is_active', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->limit(3)
            ->get(['id', 'name', 'type', 'start_date']);
    }

    #[Computed]
    public function eventMarkers()
    {
        if (!$this->showEvents) return collect();

        return Event::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('coordinates')
            ->where('is_active', true)
            ->where('start_date', '>=', now()->subDay())
            ->with('tenant:id,name,slug')
            ->get(['id', 'name', 'barangay', 'type', 'start_date', 'end_date', 'coordinates', 'featured', 'tenant_id'])
            ->map(function ($e) {
                $c = is_array($e->coordinates) ? $e->coordinates : json_decode($e->coordinates, true);
                if (!is_array($c) || !isset($c['lat'], $c['lng'])) return null;

                return [
                    'id'          => $e->id,
                    'name'        => $e->name,
                    'barangay'    => $e->barangay,
                    'type'        => $e->type,
                    'start_date'  => $e->start_date,
                    'end_date'    => $e->end_date,
                    'lat'         => (float) $c['lat'],
                    'lng'         => (float) $c['lng'],
                    'featured'    => (bool) $e->featured,
                    'tenant_slug' => optional($e->tenant)->slug,
                ];
            })
            ->filter()
            ->values();
    }

    #[Computed]
    public function categories()
    {
        return TypeOfTenant::withoutGlobalScope(TenantScope::class)
            ->withCount(['tenants' => fn ($q) => $q->where('is_active', true)->withoutGlobalScope(TenantScope::class)])
            ->whereHas('tenants', fn ($q) => $q->where('is_active', true)->withoutGlobalScope(TenantScope::class))
            ->orderBy('type')
            ->get(['id', 'type']);
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->categoryFilter !== '' || $this->openNow
            || $this->hasOfferings || $this->favoritesOnly || $this->recommendedOnly
            || $this->showEvents || $this->showAmenities;
    }

    #[Computed]
    public function initialCenter(): array
    {
        return ($this->currentLat && $this->currentLng)
            ? [(float) $this->currentLng, (float) $this->currentLat]
            : (($this->userLat && $this->userLng)
                ? [(float) $this->userLng, (float) $this->userLat]
                : self::CITY_CENTER);
    }

    #[Computed]
    public function initialZoom(): int
    {
        return $this->currentZoom ?? ($this->userLat ? 14 : 12);
    }

    #[Computed]
    public function markerCategories(): array
    {
        return SiteSetting::getValue('marker_categories', []) ?? [];
    }

    protected function isOpenNow(Tenant $tenant): bool
    {
        $hours = $tenant->settings->first()?->value['opening_hours'] ?? null;
        if (!$hours) return false;

        $days = $hours['days'] ?? null;
        if (is_array($days) && !in_array((int) now()->dayOfWeek, array_map('intval', $days), true)) {
            return false;
        }

        $now = now()->format('H:i');
        $o   = $hours['opening'] ?? '00:00';
        $c   = $hours['closing'] ?? '23:59';

        return $o <= $c ? ($now >= $o && $now <= $c) : ($now >= $o || $now <= $c);
    }

    protected function openStatusLabel(Tenant $tenant): string
    {
        $hours = $tenant->settings->first()?->value['opening_hours'] ?? null;
        if (!$hours) return '';

        return $this->isOpenNow($tenant) ? 'Open now' : 'Closed now';
    }

    public function distance($lat2, $lng2): float
    {
        if (!$this->userLat || !$this->userLng) return PHP_FLOAT_MAX;

        $R    = 6371;
        $dLat = deg2rad($lat2 - $this->userLat);
        $dLng = deg2rad($lng2 - $this->userLng);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($this->userLat)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function formatDistance(float $km): string
    {
        return $km < 1 ? round($km * 1000) . ' m' : number_format($km, 1) . ' km';
    }

    public function formatDuration(int $minutes): string
    {
        if ($minutes < 60) return $minutes . ' min';

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? "{$h}h" : "{$h}h {$m}min";
    }

    public function highlightMatch(string $text): \Illuminate\Support\HtmlString
    {
        $escaped = e($text);
        $term    = trim($this->search);

        if (!$term) {
            return new \Illuminate\Support\HtmlString($escaped);
        }

        $highlighted = preg_replace(
            '/(' . preg_quote(e($term), '/') . ')/i',
            '<mark class="bg-amber-200 dark:bg-amber-400/30 rounded-sm px-0.5">$1</mark>',
            $escaped
        );

        return new \Illuminate\Support\HtmlString($highlighted ?? $escaped);
    }

    public function favoritesKey(): string
    {
        return auth()->check()
            ? 'map_favorites_user_' . auth()->id()
            : 'map_favorites_guest';
    }

    protected function notify(string $message, string $type = 'info'): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }

    protected function resolveT(int $id): ?Tenant
    {
        return $this->tenants->firstWhere('id', $id)
            ?? Tenant::withoutGlobalScope(TenantScope::class)
                ->where('is_active', true)
                ->with([
                    'typeOfTenant:id,type',
                    'settings' => fn ($q) => $q
                        ->withoutGlobalScope(TenantScope::class)
                        ->where('key', 'business_info')
                        ->select('tenant_id', 'value'),
                ])
                ->select([
                    'id', 'name', 'slug', 'logo', 'address', 'contact_number', 'email',
                    'coordinates', 'is_recommended', 'type_of_tenant_id', 'created_at',
                ])
                ->find($id);
    }

    public function openDetail(int $tenantId, int $coordIndex = 0): void
    {
        $t = $this->resolveT($tenantId);
        if (!$t || empty($t->coordinates)) {
            $this->notify('No location found for this spot.', 'error');
            return;
        }

        $this->detailTenantId   = $tenantId;
        $this->detailCoordIndex = $coordIndex;
        $this->highlightedId    = $tenantId;

        $c = $t->coordinates[$coordIndex] ?? $t->coordinates[0];
        $this->dispatch('map:fly-to', center: [(float) $c['lng'], (float) $c['lat']], zoom: 17);
        $this->dispatch('tenant-viewed', id: $t->id, name: $t->name, type: $t->typeOfTenant?->type ?? 'Business');
    }

    public function closeDetail(): void
    {
        $this->detailTenantId   = null;
        $this->detailCoordIndex = 0;
        $this->highlightedId    = null;
    }

    public function getDirectionsToDetail(): void
    {
        if ($this->detailTenantId) {
            $this->getDirectionsTo($this->detailTenantId, $this->detailCoordIndex);
        }
    }

    public function shareDetail(): void
    {
        if (!$this->detailTenantId) return;
        $this->shareMarker($this->detailTenantId);
    }

    public function toggleFavorite(int $id): void
    {
        if (in_array($id, $this->favorites, true)) {
            $this->favorites = array_values(array_diff($this->favorites, [$id]));
            $this->notify('Removed from saved places.', 'info');
        } else {
            $this->favorites[] = $id;
            $this->notify('Saved to your places.', 'success');
        }

        session([$this->favoritesKey() => $this->favorites]);
    }

    public function flyToTenant(int $id): void
    {
        $this->openDetail($id);
    }

    public function flyToTenantCoord(int $id, int $index): void
    {
        $t = $this->resolveT($id);
        if (!$t || empty($t->coordinates[$index])) {
            $this->notify('Location not found.', 'error');
            return;
        }

        $this->highlightedId = $id;
        $c = $t->coordinates[$index];
        $this->dispatch('map:fly-to', center: [(float) $c['lng'], (float) $c['lat']], zoom: 17);
    }

    public function getDirectionsTo(int $id, int $index = 0): void
    {
        $t = $this->resolveT($id);
        if (!$t || empty($t->coordinates)) {
            $this->notify('No location found.', 'error');
            return;
        }

        if (!$this->userLat || !$this->userLng) {
            $this->pendingDirectionsTenantId   = $id;
            $this->pendingDirectionsCoordIndex = $index;
            $this->notify('Finding your location…', 'info');
            $this->dispatch('locate-for-directions');
            return;
        }

        $label = $t->coordinates[$index]['name'] ?? $t->name;
        $this->buildRoute($t, $index, $label);
    }

    protected function buildRoute(Tenant $t, int $idx, string $label): void
    {
        $c = $t->coordinates[$idx] ?? $t->coordinates[0] ?? null;
        if (!$c || !isset($c['lat'], $c['lng'])) {
            $this->notify('Destination has no valid coordinates.', 'error');
            return;
        }

        $destLat = (float) $c['lat'];
        $destLng = (float) $c['lng'];
        $userLat = (float) $this->userLat;
        $userLng = (float) $this->userLng;

        $coordsValid = is_finite($destLat) && is_finite($destLng)
                    && is_finite($userLat) && is_finite($userLng)
                    && !($destLat === 0.0 && $destLng === 0.0)
                    && !($userLat === 0.0 && $userLng === 0.0)
                    && abs($destLat) <= 90 && abs($destLng) <= 180
                    && abs($userLat) <= 90 && abs($userLng) <= 180;

        if (!$coordsValid) {
            $this->notify('Coordinates are invalid. Try setting your location again.', 'error');
            return;
        }

        $route = app(OsrmDistanceService::class)->fetchRoute(
            $userLat, $userLng,
            $destLat, $destLng,
            $this->directionsProfile,
        );

        if ($route === null || empty($route['coordinates']) || count($route['coordinates']) < 2) {
            $this->notify(
                'Could not calculate a route right now. Please try again in a moment.',
                'error'
            );
            \Illuminate\Support\Facades\Log::warning('Route fetch failed — no fallback applied', [
                'tenant_id' => $t->id,
                'profile'   => $this->directionsProfile,
                'from'      => [$userLat, $userLng],
                'to'        => [$destLat, $destLng],
            ]);
            return;
        }

        $this->routeCoords = [
            'start' => [$userLng, $userLat],
            'end'   => [$destLng, $destLat],
        ];
        $this->routeDestinationName = $label;
        $this->routeTenantId        = $t->id;
        $this->highlightedId        = $t->id;

        $this->routePolyline   = $route['coordinates'];
        $this->routeDistanceKm = (float) $route['distance_km'];
        $this->routeDurationMin = (int) $route['duration_min'];

        $this->detailTenantId   = null;
        $this->detailCoordIndex = 0;

        $this->dispatch('map:close-sidebar');

        $this->pendingRouteBounds = [
            [$userLng, $userLat],
            [$destLng, $destLat],
        ];

        $this->routeVersion++;
    }

    public function setDirectionsProfile(string $profile): void
    {
        if (!in_array($profile, ['driving', 'walking', 'cycling'], true)) return;

        if (empty($this->routeCoords['start']) || empty($this->routeCoords['end'])) {
            $this->directionsProfile = $profile;
            return;
        }

        $previous = $this->directionsProfile;
        $start    = $this->routeCoords['start'];
        $end      = $this->routeCoords['end'];

        $route = app(OsrmDistanceService::class)->fetchRoute(
            (float) $start[1], (float) $start[0],
            (float) $end[1], (float) $end[0],
            $profile,
        );

        if ($route === null || empty($route['coordinates']) || count($route['coordinates']) < 2) {
            $this->notify(
                "Could not switch to the {$profile} route. Keeping {$previous}.",
                'error'
            );
            return;
        }

        $this->directionsProfile = $profile;
        $this->routePolyline     = $route['coordinates'];
        $this->routeDistanceKm   = (float) $route['distance_km'];
        $this->routeDurationMin  = (int) $route['duration_min'];

        if ($this->navigationActive) {
            $this->navigationActive = false;
        }

        $this->routeVersion++;
    }

    public function clearRoute(): void
    {
        $this->routeCoords          = [];
        $this->routePolyline        = [];
        $this->routeDistanceKm      = 0.0;
        $this->routeDurationMin     = 0;
        $this->routeDestinationName = null;
        $this->routeTenantId        = null;
        $this->pendingRouteBounds   = [];
        $this->navigationActive     = false;
        $this->routeVersion++;
        $this->notify('Route cleared.', 'info');
    }

    public function startNavigation(): void
    {
        if (empty($this->routePolyline) || empty($this->routeCoords)) {
            $this->notify('Start a route first.', 'info');
            return;
        }

        $this->navigationActive = true;
        $this->followMode       = true;
    }

    public function stopNavigation(?float $lat = null, ?float $lng = null): void
    {
        $wasActive = $this->navigationActive;
        $this->navigationActive = false;

        if ($lat !== null && $lng !== null && is_finite($lat) && is_finite($lng)) {
            $this->userLat = $this->currentLat = round($lat, 6);
            $this->userLng = $this->currentLng = round($lng, 6);
            $this->userLocationVersion++;
            $this->mapRefreshVersion++;
        }

        if ($wasActive) {
            $this->notify('Navigation ended.', 'info');
        }
    }

    public function useCityCenterLocation(): void
    {
        $this->userLat = $this->currentLat = self::CITY_CENTER[1];
        $this->userLng = $this->currentLng = self::CITY_CENTER[0];
        $this->currentZoom = 14;
        $this->userLocationVersion++;
        $this->mapRefreshVersion++;

        $this->loadDrivingDistances();
        $this->notify('Location set to Victorias City centre.', 'success');

        if (!empty($this->routeCoords) && $this->routeTenantId) {
            $t = $this->resolveT($this->routeTenantId);
            if ($t && !empty($t->coordinates[$this->detailCoordIndex])) {
                $this->buildRoute(
                    $t,
                    $this->detailCoordIndex,
                    $t->coordinates[$this->detailCoordIndex]['name'] ?? $t->name
                );
            }
            return;
        }

        if ($this->pendingDirectionsTenantId) {
            $id  = $this->pendingDirectionsTenantId;
            $idx = $this->pendingDirectionsCoordIndex;
            $this->pendingDirectionsTenantId   = null;
            $this->pendingDirectionsCoordIndex = 0;

            $t = $this->resolveT($id);
            if ($t && !empty($t->coordinates[$idx])) {
                $this->buildRoute($t, $idx, $t->coordinates[$idx]['name'] ?? $t->name);
            }
            return;
        }

        $this->dispatch('map:fly-to', center: [self::CITY_CENTER[0], self::CITY_CENTER[1]], zoom: 14);
    }

    public function setUserLocation($lat, $lng): void
    {
        if ($this->navigationActive) return;

        $oldLat = $this->userLat;
        $oldLng = $this->userLng;

        $this->userLat     = $this->currentLat = round((float) $lat, 6);
        $this->userLng     = $this->currentLng = round((float) $lng, 6);
        $this->currentZoom = 15;
        $this->userLocationVersion++;

        $moved = ($oldLat === null || $oldLng === null)
            || $this->haversine($oldLat, $oldLng, $this->userLat, $this->userLng) > 0.1;

        if ($moved) {
            $this->mapRefreshVersion++;
        }

        if ($this->pendingDirectionsTenantId) {
            $id  = $this->pendingDirectionsTenantId;
            $idx = $this->pendingDirectionsCoordIndex;
            $this->pendingDirectionsTenantId   = null;
            $this->pendingDirectionsCoordIndex = 0;

            $t = $this->resolveT($id);
            if (!$t || empty($t->coordinates[$idx])) {
                $this->notify('Destination no longer available.', 'error');
                return;
            }

            $this->buildRoute($t, $idx, $t->coordinates[$idx]['name'] ?? $t->name);
            return;
        }

        if ($this->autoDirections && $this->highlightedId) {
            $this->autoDirections = false;
            $this->getDirectionsTo($this->highlightedId);
            $this->notify('Route started.', 'success');
            return;
        }

        if ($this->sortBy !== 'distance') $this->sortBy = 'distance';
        $this->dispatch('map:fly-to', center: [$this->userLng, $this->userLat], zoom: 15);
        $this->notify('Location found — sorted by distance.', 'success');
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function locationFailed(string $reason = 'unavailable'): void
    {
        $this->autoDirections              = false;
        $this->pendingDirectionsTenantId   = null;
        $this->pendingDirectionsCoordIndex = 0;
        $this->navigationActive            = false;

        $this->notify(match ($reason) {
            'denied'   => 'Location access was denied. Click the lock icon in your browser\'s address bar and allow location, then try again.',
            'timeout'  => 'Location timed out. Please try again.',
            'insecure' => 'Location needs a secure connection. Open the site via http://127.0.0.1:8000 or https.',
            default    => 'Your location could not be determined.',
        }, $reason === 'timeout' ? 'warning' : 'error');
    }

    public function toggleFollowMode(bool $on): void
    {
        $this->followMode = $on;
        $this->notify($on ? 'Following your location.' : 'Follow mode off.', 'info');
    }

    public function toggleSatellite(): void
    {
        $this->satellite = !$this->satellite;
        $this->notify($this->satellite ? 'Satellite view on.' : 'Standard view on.', 'info');
    }

    public function toggleEstablishments(): void
    {
        $this->showAmenities = ! $this->showAmenities;
        $this->mapEpoch++;
    }

    /**
     * Recenter the map on the user's location.
     *
     * Works regardless of navigation state — unlike the Alpine-side
     * recenterNavigation(), which early-returns when nav is inactive.
     * If we don't yet know the user's location, kick off a locate
     * request; when it resolves, setUserLocation() flies the camera.
     */
    public function recenterOnMe(): void
    {
        if ($this->userLat && $this->userLng) {
            $this->dispatch('map:fly-to', center: [(float) $this->userLng, (float) $this->userLat], zoom: 15);
            $this->notify('Centered on your location.', 'success');
            return;
        }

        $this->dispatch('request-location-for-distance');
        $this->notify('Finding your location…', 'info');
    }

    /**
     * Toggle the MAP theme — decoupled from the global site theme.
     *
     * Bumps $mapEpoch so the <x-map> wrapper re-keys and MapLibre
     * reinitialises with the new theme. Users see the loading overlay
     * briefly (via the wrapper's x-init="mapLoading = true").
     */
    public function toggleMapTheme(): void
    {
        $this->mapTheme = $this->mapTheme === 'dark' ? 'light' : 'dark';
        session(['map_theme' => $this->mapTheme]);

        $this->mapEpoch++;

        $this->notify(
            $this->mapTheme === 'dark' ? 'Dark map enabled.' : 'Light map enabled.',
            'info'
        );
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'openNow', 'hasOfferings', 'favoritesOnly', 'recommendedOnly', 'showEvents', 'showAmenities']);

        $this->mapEpoch++;

        $this->refreshFilterState();
        $this->notify('Filters cleared.', 'info');
    }

    public function resetView(): void
    {
        $this->dispatch('map:fly-to', center: self::CITY_CENTER, zoom: 12);
    }

    public function shareLocation(): void
    {
        if (!$this->userLat) {
            $this->notify('Find your location first.', 'info');
            return;
        }
        $this->dispatch('copy-to-clipboard', text: request()->url() . '?lat=' . $this->userLat . '&lng=' . $this->userLng);
        $this->notify('Location link copied.', 'success');
    }

    public function shareMarker(int $id): void
    {
        $t = $this->resolveT($id);
        if (!$t) {
            $this->notify('Destination not found.', 'error');
            return;
        }
        $this->dispatch('copy-to-clipboard', text: request()->url() . '?marker=' . $id);
        $this->notify('Destination link copied.', 'success');
    }

    public function shareRoute(): void
    {
        if (!$this->routeTenantId) {
            $this->notify('Start a route first.', 'info');
            return;
        }
        $url = request()->url() . '?marker=' . $this->routeTenantId . '&directions=1&profile=' . $this->directionsProfile;
        $this->dispatch('copy-to-clipboard', text: $url);
        $this->notify('Route link copied.', 'success');
    }

    public function loadDrivingDistances(): void
    {
        if (!$this->userLat || !$this->userLng) return;

        $tenants = $this->tenants;
        if ($tenants->isEmpty()) return;

        $pending = [];
        foreach ($tenants as $tenant) {
            foreach ($tenant->coordinates as $ci => $coord) {
                if (!isset($coord['lat'], $coord['lng'])) continue;

                $key = "{$tenant->id}:{$ci}";
                if (isset($this->drivingDistances[$key])) continue;

                $pending[$key] = [(float) $coord['lat'], (float) $coord['lng']];
            }
        }

        if (empty($pending)) return;

        $results = app(OsrmDistanceService::class)->drivingDistancesBatch(
            (float) $this->userLat,
            (float) $this->userLng,
            $pending,
            maxTargets: 15,
            budgetSeconds: 3.5,
        );

        if (!empty($results)) {
            $this->drivingDistances = array_merge($this->drivingDistances, $results);
        }
    }

    public function updateViewport(?float $lat, ?float $lng, ?int $zoom): void
    {
        if ($lat)  $this->currentLat  = round($lat, 6);
        if ($lng)  $this->currentLng  = round($lng, 6);
        if ($zoom) $this->currentZoom = max(5, $zoom);
    }

    /**
     * Global site theme changed.
     *
     * Map theme is now DECOUPLED from the global theme (see
     * toggleMapTheme + :theme="$mapTheme" on <x-map>). We no longer
     * bump $themeVersion, so toggling site dark mode does NOT remount
     * the map. Just nudge MapLibre to recalc in case the surrounding
     * chrome changed dimensions.
     */
    public function handleThemeChange(): void
    {
        $this->dispatch('map:resize');
    }

    public function forceReloadMap(): void
    {
        $this->mapForceReload++;
    }

    protected function refreshFilterState(): void
    {
        $this->filtersHash = $this->hashFilters();
        $this->loadDrivingDistances();
    }

    public function updatedSearch():          void { $this->refreshFilterState(); }
    public function updatedCategoryFilter():  void { $this->refreshFilterState(); }
    public function updatedOpenNow():         void { $this->refreshFilterState(); }
    public function updatedHasOfferings():    void { $this->refreshFilterState(); }
    public function updatedFavoritesOnly():   void { $this->refreshFilterState(); }
    public function updatedRecommendedOnly(): void { $this->refreshFilterState(); }
    public function updatedShowEvents():      void { $this->refreshFilterState(); }

    public function updatedSortBy(string $v): void
    {
        if ($v === 'distance' && !$this->userLat) {
            $this->dispatch('request-location-for-distance');
        }
    }

    protected function isUsablePoint(mixed $point): bool
    {
        if (!is_array($point) || count($point) !== 2) return false;

        $lng = $point[0] ?? null;
        $lat = $point[1] ?? null;

        if (!is_numeric($lng) || !is_numeric($lat)) return false;

        $lng = (float) $lng;
        $lat = (float) $lat;

        if (!is_finite($lng) || !is_finite($lat)) return false;
        if (abs($lng) > 180 || abs($lat) > 90)     return false;
        if ($lng === 0.0 && $lat === 0.0)          return false;

        return true;
    }

    public function getZoom(mixed $value = null): int
    {
        if ($value !== null && is_numeric($value)) {
            $this->currentZoom = max(5, min(22, (int) $value));
        }

        return (int) ($this->currentZoom ?? 12);
    }

    /** @return array{lng: float, lat: float} */
    public function getCenter(mixed $value = null): array
    {
        return [
            'lng' => (float) ($this->currentLng ?? self::CITY_CENTER[0]),
            'lat' => (float) ($this->currentLat ?? self::CITY_CENTER[1]),
        ];
    }

    public function getBearing(mixed $value = null): float
    {
        return 0.0;
    }

    public function getPitch(mixed $value = null): float
    {
        return 0.0;
    }

    #[On('map:loaded')]
    public function onMapReady(): void
    {
        if ($this->pendingEventId) {
            $ev = Event::withoutGlobalScope(TenantScope::class)
                ->where('is_active', true)
                ->find($this->pendingEventId);

            $this->pendingEventId = null;

            if ($ev && !empty($ev->coordinates)) {
                $c = $ev->coordinates;
                $this->dispatch('map:fly-to', center: [(float) $c['lng'], (float) $c['lat']], zoom: 17);
            }
            return;
        }

        if (!empty($this->pendingRouteBounds)) {
            $bounds = $this->pendingRouteBounds;
            $this->pendingRouteBounds = [];

            if (count($bounds) === 2
                && $this->isUsablePoint($bounds[0])
                && $this->isUsablePoint($bounds[1])) {
                $this->dispatch('map:fit-bounds', [
                    'bounds'  => $bounds,
                    'padding' => ['top' => 80, 'bottom' => 140, 'left' => 80, 'right' => 80],
                ]);
            }
            return;
        }

        if (!$this->pendingMarkerId) return;

        $t = $this->resolveT($this->pendingMarkerId);
        $this->pendingMarkerId = null;

        if (!$t || empty($t->coordinates)) {
            $this->autoDirections = false;
            return;
        }

        $this->highlightedId  = $t->id;
        $this->detailTenantId = $t->id;

        $c = $t->coordinates[0];
        $this->dispatch('map:fly-to', center: [(float) $c['lng'], (float) $c['lat']], zoom: 16);
        $this->dispatch('tenant-viewed', id: $t->id, name: $t->name, type: $t->typeOfTenant?->type ?? 'Business');

        if ($this->autoDirections) {
            $this->dispatch('locate-for-directions');
        }
    }

    #[On('map:marker-clicked')]
    public function onMarkerClicked($id, $lat, $lng): void
    {
        if (preg_match('/tenant-(\d+)-(\d+)/', (string) $id, $m)) {
            $this->openDetail((int) $m[1], (int) $m[2]);
            return;
        }

        if (preg_match('/^event-(\d+)$/', (string) $id, $m)) {
            $this->dispatch('map:fly-to', center: [(float) $lng, (float) $lat], zoom: 17);
        }
    }
};
?>

{{-- ✅ Single root --}}
<div class="relative flex overflow-hidden font-sans dark:bg-gray-950
            h-[calc(100dvh-4rem)] md:h-[calc(100dvh-5rem)]"
     data-map-root
     x-data="mapApp()"
     x-init="boot()"
     x-on:notify.window="toast($event.detail.type, $event.detail.message)"
     x-on:copy-to-clipboard.window="copyText($event.detail.text)"
     x-on:request-location-for-distance.window="locate()"
     x-on:locate-for-directions.window="locateForDirections()"
     x-on:theme-changed.window="$wire.handleThemeChange()"
     x-on:map:center-changed.window="debouncedViewport($event.detail.lat, $event.detail.lng, null)"
     x-on:map:zoom-changed.window="onZoomChanged($event.detail.zoom)"
     x-on:map:close-sidebar.window="mobileOpen = false"
     x-on:map:prepare-rebuild.window="mapLoading = true"
     x-on:keydown.window="handleKey($event)">

    {{-- Mobile sidebar backdrop --}}
    <div x-cloak
         :class="mobileOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'"
         class="fixed inset-0 z-[1090] bg-black/60 backdrop-blur-sm lg:hidden transition-opacity duration-200
                [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
         @click="mobileOpen = false"
         aria-hidden="true"></div>

    {{-- Sidebar --}}
    <aside data-map-aside
           wire:init="loadDrivingDistances"
           class="fixed bottom-0 top-16 md:top-20 lg:static lg:top-0 lg:bottom-auto
                  left-0 z-[1100]
                  w-[85vw] max-w-[340px] lg:max-w-none lg:z-auto flex-shrink-0
                  transition-[transform,width] duration-300 ease-out
                  bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800
                  will-change-transform"
           :class="{
               'translate-x-0': mobileOpen,
               '-translate-x-full': !mobileOpen,
               'lg:translate-x-0': sidebarOpen,
               'lg:-translate-x-full': !sidebarOpen,
               'lg:w-[340px]': sidebarOpen,
               'lg:w-0 lg:overflow-hidden lg:border-r-0': !sidebarOpen,
           }">
        @include('livewire.partials.explore-sidebar')
    </aside>

    {{-- Map area --}}
    <div class="relative flex-1 min-w-0 overflow-hidden bg-gray-100 dark:bg-gray-900">

        {{-- Map loading overlay --}}
        <div x-cloak
             :class="mapLoading ? '' : 'hidden'"
             class="absolute inset-0 z-[900] flex items-center justify-center bg-gray-100 dark:bg-gray-900">
            <div class="text-center px-6">
                <div class="mx-auto mb-3 h-10 w-10 animate-spin rounded-full border-2 border-primary-600 border-t-transparent motion-reduce:animate-none"></div>
                <p class="text-sm text-gray-500 dark:text-gray-400 italic">Loading map…</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">This can take a few seconds on slow connections.</p>
            </div>
        </div>

        {{-- Stuck state --}}
        <div x-cloak
             :class="(mapStuck && !mapLoading) ? '' : 'hidden'"
             class="absolute inset-0 z-[900] flex items-center justify-center bg-gray-100 dark:bg-gray-900 overflow-y-auto">
            <div class="text-center max-w-md px-4 sm:px-6 py-8">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Map couldn't load</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Something prevented MapLibre from initialising. Details below.
                </p>

                <div class="mt-5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-4 text-left">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Diagnostic</p>
                    <dl class="space-y-1 text-xs font-mono">
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Alpine</dt>
                            <dd :class="typeof Alpine !== 'undefined' ? 'text-emerald-600' : 'text-rose-600'"
                                x-text="typeof Alpine !== 'undefined' ? 'loaded' : 'missing'"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Livewire</dt>
                            <dd :class="typeof Livewire !== 'undefined' ? 'text-emerald-600' : 'text-rose-600'"
                                x-text="typeof Livewire !== 'undefined' ? 'loaded' : 'missing'"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">MapLibre</dt>
                            <dd :class="typeof maplibregl !== 'undefined' ? 'text-emerald-600' : 'text-rose-600'"
                                x-text="typeof maplibregl !== 'undefined' ? 'loaded' : 'missing'"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Nav module</dt>
                            <dd :class="typeof window.RealtimeNavigation !== 'undefined' ? 'text-emerald-600' : 'text-rose-600'"
                                x-text="typeof window.RealtimeNavigation !== 'undefined' ? 'loaded' : 'missing'"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Map container</dt>
                            <dd :class="document.getElementById('tourist-map') ? 'text-emerald-600' : 'text-rose-600'"
                                x-text="document.getElementById('tourist-map') ? 'present' : 'missing'"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Map canvas</dt>
                            <dd :class="document.querySelector('#tourist-map canvas') ? 'text-emerald-600' : 'text-rose-600'"
                                x-text="document.querySelector('#tourist-map canvas') ? 'present' : 'missing'"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Canvas size</dt>
                            <dd class="text-gray-900 dark:text-gray-100"
                                x-text="(() => { const c = document.querySelector('#tourist-map canvas'); return c ? c.width + '×' + c.height : '—'; })()"></dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-5 flex flex-col sm:flex-row gap-2 justify-center">
                    <button type="button" @click="retryMap()"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 hover:bg-primary-700
                                   text-white px-4 h-11 text-sm font-bold transition active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Retry
                    </button>
                    <button type="button" onclick="window.location.reload()"
                            class="inline-flex items-center justify-center rounded-lg h-11
                                   border border-gray-300 dark:border-gray-700
                                   bg-white dark:bg-gray-900
                                   px-4 text-sm font-bold text-gray-700 dark:text-gray-200
                                   hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400
                                   transition active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Reload Page
                    </button>
                </div>
            </div>
        </div>

        {{-- MAP CANVAS WRAPPER --}}
        <div wire:key="map-{{ $satellite ? 'sat' : 'std' }}-{{ $mapForceReload }}-{{ $showAmenities ? 'full' : 'main' }}-{{ $mapEpoch }}-{{ $mapTheme }}"
             x-init="mapLoading = true"
             class="absolute inset-0 will-change-transform">
            <x-map
                wire:key="mapcn-{{ $mapEpoch }}-{{ $satellite ? 'sat' : 'std' }}-{{ $mapTheme }}"
                id="tourist-map"
                :center="$this->initialCenter"
                :zoom="$this->initialZoom"
                :min-zoom="5"
                :max-zoom="$satellite ? 19 : 22"
                height="100%"
                :provider="$satellite ? 'custom' : 'carto-voyager'"
                :style="$satellite ? route('map.satellite.style') : null"
                :light-style="$satellite ? route('map.satellite.style') : null"
                :dark-style="$satellite ? route('map.satellite.style') : null"
                :theme="$mapTheme"
                class="h-full w-full"
                :events="['click', 'marker-clicked']"
            >
                <x-map-controls
                    :zoom="true"
                    :compass="true"
                    :locate="false"
                    :fullscreen="true"
                    :scale="true"
                    position="bottom-right"
                />

                @php
                    // Resolved once per render — avoids N new Collection
                    // instances inside the coordinate loops.
                    $markerCats = $this->markerCategories;
                    $markerColors = ['#f97316','#a855f7','#3b82f6','#14b8a6','#eab308','#10b981','#8b5cf6','#f43f5e'];
                @endphp

                @if(!$navigationActive)
                    @foreach($this->tenants as $tenant)
                        @php
                            $tc      = $markerColors[$loop->index % count($markerColors)];
                            $isRoute = $routeTenantId === $tenant->id && !empty($routeCoords);
                            $isHL    = $highlightedId === $tenant->id;
                            // Rule J: relative /storage path, never asset() —
                            // APP_URL may not match the current host.
                            $logo    = $tenant->logo ? '/storage/' . ltrim($tenant->logo, '/') : null;

                            $coordCount   = count($tenant->coordinates);
                            $nearbyCount  = max(0, $coordCount - 1);
                            $hasNearby    = $nearbyCount > 0;
                        @endphp
                        @foreach($tenant->coordinates as $ci => $coord)
                            @php
                                $isParent  = $ci === 0 || ($coord['type'] ?? '') === 'parent';
                            @endphp

                            @if(!$isParent && !$showAmenities)
                                @continue
                            @endif

                            @php
                                $coordType = $coord['type'] ?? null;
                                $mcMatch   = null;
                                if (! $isParent && $coordType) {
                                    foreach ($markerCats as $cat) {
                                        if (($cat['key'] ?? '') === $coordType) { $mcMatch = $cat; break; }
                                    }
                                }
                                $activeColor = $mcMatch ? ($mcMatch['color'] ?? $tc) : $tc;
                            @endphp

                            <x-map-marker
                                wire:key="m-{{ $tenant->id }}-{{ $ci }}-{{ $mapEpoch }}"
                                :lat="$coord['lat']"
                                :lng="$coord['lng']"
                                :color="$activeColor"
                                id="tenant-{{ $tenant->id }}-{{ $ci }}"
                                anchor="bottom"
                                :class="$isParent ? 'z-30' : 'z-10'">
                                <x-marker-content>
                                    <div class="flex flex-col items-center cursor-pointer group">
                                        @if($isParent)
                                            <div class="relative flex h-12 w-12 items-center justify-center rounded-full
                                                        border-[2.5px] bg-white dark:bg-gray-900
                                                        ring-2 ring-white dark:ring-gray-900
                                                        shadow-xl shadow-gray-900/20
                                                        transition-transform duration-150
                                                        motion-safe:group-hover:scale-110
                                                        {{ ($isRoute || $isHL) ? 'outline outline-2 outline-offset-2 outline-primary-500' : '' }}"
                                                 style="border-color: {{ $tc }};">
                                                @if($logo)
                                                    <img src="{{ $logo }}" alt="{{ $tenant->name }}"
                                                         class="h-full w-full rounded-full object-cover"
                                                         loading="lazy" decoding="async" width="48" height="48">
                                                @else
                                                    <span class="text-[13px] font-bold text-gray-900 dark:text-white">
                                                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                                    </span>
                                                @endif

                                                @if($hasNearby && !$showAmenities)
                                                    <span class="absolute -top-2 -right-2 min-w-[20px] h-5
                                                                 flex items-center justify-center rounded-full
                                                                 bg-amber-500 text-white text-[10px] font-bold font-mono
                                                                 px-1 shadow-md ring-2 ring-white dark:ring-gray-900
                                                                 tabular-nums pointer-events-none select-none"
                                                          aria-label="{{ $nearbyCount }} establishments nearby">
                                                        +{{ $nearbyCount }}
                                                    </span>
                                                @endif
                                            </div>

                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                 class="mt-[-2px] h-2 w-3"
                                                 style="color: {{ $tc }};"
                                                 viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                                <path d="M0 0 L12 0 L6 8 Z"/>
                                            </svg>
                                        @elseif($mcMatch)
                                            <div class="flex h-8 w-8 sm:h-7 sm:w-7 items-center justify-center rounded-full
                                                        border bg-white/90 dark:bg-gray-900/90
                                                        shadow-sm
                                                        opacity-80
                                                        transition-all duration-150
                                                        motion-safe:group-hover:opacity-100
                                                        motion-safe:group-hover:scale-110
                                                        motion-safe:group-hover:shadow-md"
                                                 style="border-color: {{ $activeColor }}80;">
                                                @if($mcMatch['icon_svg'] ?? null)
                                                    <div class="h-3.5 w-3.5 sm:h-3 sm:w-3 text-gray-700 dark:text-gray-200">
                                                        <x-safe-svg :svg="$mcMatch['icon_svg']" class="h-full w-full fill-none stroke-current stroke-2" />
                                                    </div>
                                                @else
                                                    <span class="text-[10px] font-bold text-gray-700 dark:text-gray-200">
                                                        {{ strtoupper(substr($coordType, 0, 1)) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="h-2 w-2 rounded-full
                                                        ring-2 ring-white dark:ring-gray-900
                                                        shadow-sm
                                                        opacity-70
                                                        transition-all duration-150
                                                        motion-safe:group-hover:opacity-100
                                                        motion-safe:group-hover:scale-150"
                                                 style="background: {{ $tc }};"></div>
                                        @endif
                                    </div>
                                </x-marker-content>
                            </x-map-marker>
                        @endforeach
                    @endforeach

                    @if($showEvents)
                        @foreach($this->eventMarkers as $ev)
                            <x-map-marker
                                wire:key="ev-m-{{ $ev['id'] }}-{{ $mapEpoch }}"
                                :lat="$ev['lat']"
                                :lng="$ev['lng']"
                                color="#A78BFA"
                                id="event-{{ $ev['id'] }}"
                                anchor="bottom"
                                class="z-20">
                                <x-marker-content>
                                    <div class="flex flex-col items-center cursor-pointer group"
                                         :class="{ 'ring-2 ring-purple-500 ring-offset-4 ring-offset-transparent rounded-full': {{ $ev['id'] }} === {{ (int) ($highlightedEventId ?? 0) }} }">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full
                                                    bg-gradient-to-tr from-purple-600 to-pink-500
                                                    border-2 border-white shadow-lg
                                                    ring-2 ring-white dark:ring-gray-900
                                                    transition-transform motion-safe:group-hover:scale-110 dark:border-gray-900">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <rect x="3" y="4" width="18" height="18" rx="2"/>
                                                <line x1="16" y1="2" x2="16" y2="6"/>
                                                <line x1="8" y1="2" x2="8" y2="6"/>
                                                <line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-[-2px] h-2 w-3" style="color: #A78BFA;"
                                             viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                            <path d="M0 0 L12 0 L6 8 Z"/>
                                        </svg>
                                    </div>
                                </x-marker-content>
                                <x-marker-popup>
                                    <div class="w-[220px] rounded-2xl bg-white p-4 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                        <p class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Event</p>
                                        <h3 class="mt-1 text-lg font-display font-semibold text-gray-900 dark:text-white">{{ $ev['name'] }}</h3>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $ev['start_date']->format('M d, Y') }}
                                        </p>
                                        <a href="{{ route('events') }}" wire:navigate
                                           class="mt-3 block rounded-lg bg-purple-600 px-3 py-2 text-center text-xs font-bold text-white transition hover:bg-purple-700
                                                  focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500/50">
                                            View Event
                                        </a>
                                    </div>
                                </x-marker-popup>
                            </x-map-marker>
                        @endforeach
                    @endif
                @endif
            </x-map>
        </div>

        {{-- Route calculation loading overlay --}}
        <div wire:loading.delay.shortest
             wire:target="getDirectionsToDetail,getDirectionsTo,setDirectionsProfile"
             x-cloak
             class="absolute inset-0 z-[2000] flex items-center justify-center bg-black/40"
             aria-live="polite"
             aria-busy="true">
            <div class="flex flex-col items-center gap-3 rounded-2xl bg-white px-6 py-5 shadow-2xl dark:bg-gray-900">
                <div class="h-9 w-9 animate-spin rounded-full border-2 border-primary-600 border-t-transparent motion-reduce:animate-none" role="status" aria-label="Loading"></div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Calculating route…</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">This can take a few seconds.</p>
            </div>
        </div>

        {{-- Nearby markers hint chip --}}
        <div x-data="{ show: false }"
             x-init="
                const compute = () => {
                    const z = Number($wire.currentZoom) || 12;
                    $data.show = z < 14 && !$wire.navigationActive && !$wire.showAmenities;
                };
                compute();
                $watch('$wire.currentZoom', compute);
                $watch('$wire.navigationActive', compute);
                $watch('$wire.showAmenities', compute);
             "
             x-cloak
             :class="show ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'"
             class="absolute bottom-3 left-3 sm:bottom-6 sm:left-6 z-[900]
                    hidden lg:flex items-center gap-2 rounded-full
                    bg-white/95 dark:bg-gray-900/95 backdrop-blur-md
                    border border-gray-200/80 dark:border-gray-800/80
                    shadow-md px-3 py-1.5 pointer-events-none select-none
                    transition-all duration-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
            </svg>
            <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 whitespace-nowrap">
                Zoom in, or click a pin, to see more
            </span>
        </div>

        {{-- Navigation top bar --}}
        <div :class="$store.nav.active ? 'opacity-100 translate-y-0 pointer-events-auto' : 'opacity-0 -translate-y-3 pointer-events-none'"
             class="absolute top-[max(0.5rem,var(--safe-top))] left-2 right-2 z-[1200]
                    sm:top-4 sm:left-1/2 sm:-translate-x-1/2 sm:right-auto sm:w-[400px]
                    flex items-center gap-2
                    rounded-full bg-gray-900 dark:bg-gray-950
                    ring-1 ring-white/10 shadow-2xl shadow-black/30
                    pl-4 pr-3 py-1.5
                    transition-all duration-300 ease-out">
            <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-full
                        bg-primary-500/20 ring-1 ring-primary-400/40">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2 L21 22 L12 17.5 L3 22 Z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-primary-400 leading-none mb-0.5">
                    Navigating
                </p>
                <p class="text-sm font-semibold text-white truncate leading-tight">
                    {{ $routeDestinationName ?? 'Destination' }}
                </p>
            </div>
        </div>

        {{-- Navigation bottom bar --}}
        <div :class="$store.nav.active ? 'opacity-100 translate-y-0 pointer-events-auto' : 'opacity-0 translate-y-3 pointer-events-none'"
             class="absolute bottom-[max(0.5rem,var(--safe-bottom))] left-2 right-2 z-[1200]
                    sm:bottom-6 sm:left-1/2 sm:-translate-x-1/2 sm:right-auto sm:w-[400px]
                    flex items-center gap-1
                    rounded-full bg-gray-900 dark:bg-gray-950
                    ring-1 ring-white/10 shadow-2xl shadow-black/30
                    pl-4 pr-1 py-1.5
                    transition-all duration-300 ease-out">
            <div class="flex-1 min-w-0">
                <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-primary-400 leading-none mb-0.5">
                    Remaining
                </p>
                <p class="text-base font-bold text-white tabular-nums leading-tight"
                   x-text="remainingText"></p>
            </div>

            <button type="button"
                    @click="recenterNavigation()"
                    aria-label="Recenter on my location"
                    class="shrink-0 flex h-10 w-10 items-center justify-center rounded-full
                           text-white/85 hover:text-white hover:bg-white/10
                           transition active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="7"/>
                    <line x1="12" y1="2" x2="12" y2="5"/>
                    <line x1="12" y1="19" x2="12" y2="22"/>
                    <line x1="2" y1="12" x2="5" y2="12"/>
                    <line x1="19" y1="12" x2="22" y2="12"/>
                    <circle cx="12" cy="12" r="2" fill="currentColor"/>
                </svg>
            </button>

            <button type="button"
                    @click="stopNavigation()"
                    class="shrink-0 inline-flex items-center gap-1.5 h-10 px-4 rounded-full
                           bg-rose-600 hover:bg-rose-500 text-white
                           text-[11px] font-bold uppercase tracking-wider
                           transition active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400/60 focus-visible:ring-offset-2 focus-visible:ring-offset-gray-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="6" y="6" width="12" height="12" rx="1.5"/>
                </svg>
                End
            </button>
        </div>

        {{-- Desktop toolbar --}}
        <div :class="$wire.navigationActive ? 'opacity-0 pointer-events-none' : 'opacity-100'"
             class="hidden lg:flex absolute top-3 right-3 z-[1000]
                    flex-col gap-2
                    transition-all duration-200 ease-out
                    {{ $detailTenantId ? 'lg:right-[432px]' : '' }}">

            <div class="flex flex-col divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200/80
                        bg-white dark:bg-gray-900 shadow-lg shadow-gray-900/5
                        dark:divide-gray-800 dark:border-gray-800/80">
                <button type="button" data-tip="Toggle sidebar" @click="sidebarOpen = !sidebarOpen"
                        :aria-pressed="sidebarOpen.toString()" aria-label="Toggle sidebar"
                        class="flex h-10 w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        :class="sidebarOpen ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                    </svg>
                </button>

                <button type="button" data-tip="My location (L)" @click="locate()" aria-label="My location"
                        class="flex h-10 w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        :class="locating ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" x-show="!locating" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21c-4.5-4.5-7.5-8.24-7.5-11.5A7.5 7.5 0 0112 2a7.5 7.5 0 017.5 7.5c0 3.26-3 7-7.5 11.5z"/>
                        <circle cx="12" cy="9.5" r="2.5" stroke-width="2" fill="none"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" x-show="locating" x-cloak class="h-5 w-5 animate-spin motion-reduce:animate-none"
                         fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>

                <button type="button" data-tip="Follow mode (F)" @click="handleFollowButton()" aria-label="Follow mode"
                        class="flex h-10 w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        :class="followMode ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L21 21 12 17 3 21Z"/>
                    </svg>
                </button>
            </div>

            <div class="flex flex-col divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200/80
                        bg-white dark:bg-gray-900 shadow-lg shadow-gray-900/5
                        dark:divide-gray-800 dark:border-gray-800/80">
                <button type="button" data-tip="Recenter (R)" wire:click="resetView" aria-label="Recenter"
                        class="flex h-10 w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="8"/>
                        <line x1="12" y1="2" x2="12" y2="4"/>
                        <line x1="12" y1="20" x2="12" y2="22"/>
                        <line x1="2" y1="12" x2="4" y2="12"/>
                        <line x1="20" y1="12" x2="22" y2="12"/>
                    </svg>
                </button>

                <button type="button" data-tip="Toggle satellite (S)"
                        @click="mapLoading = true; if (window.__mapCanvasPoll) window.__mapCanvasPoll.start();"
                        wire:click="toggleSatellite" aria-label="Toggle satellite view"
                        @class([
                            'flex h-10 w-10 items-center justify-center transition',
                            'hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-800 dark:hover:text-white',
                            'focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 active:scale-95',
                            '[touch-action:manipulation] [-webkit-tap-highlight-color:transparent]',
                            'text-gray-500 dark:text-gray-400',
                            'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $satellite,
                        ])>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 17l10 5 10-5"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 12l10 5 10-5"/>
                    </svg>
                </button>

                {{-- Map theme toggle — decoupled from global site theme. --}}
                <button type="button" data-tip="Toggle map theme"
                        wire:click="toggleMapTheme"
                        wire:loading.attr="disabled"
                        wire:target="toggleMapTheme"
                        aria-label="Toggle map theme"
                        aria-pressed="{{ $mapTheme === 'dark' ? 'true' : 'false' }}"
                        @class([
                            'flex h-10 w-10 items-center justify-center transition',
                            'hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-800 dark:hover:text-white',
                            'focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 active:scale-95',
                            '[touch-action:manipulation] [-webkit-tap-highlight-color:transparent]',
                            'text-gray-500 dark:text-gray-400',
                            'disabled:opacity-60 disabled:cursor-wait',
                        ])>
                    {{-- Sun when map theme is dark (tap → light); moon when light (tap → dark). --}}
                    @if($mapTheme === 'dark')
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="4"/>
                            <path d="M12 2v2"/>
                            <path d="M12 20v2"/>
                            <path d="m4.93 4.93 1.41 1.41"/>
                            <path d="m17.66 17.66 1.41 1.41"/>
                            <path d="M2 12h2"/>
                            <path d="M20 12h2"/>
                            <path d="m6.34 17.66-1.41 1.41"/>
                            <path d="m19.07 4.93-1.41 1.41"/>
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                        </svg>
                    @endif
                </button>

                <button type="button" data-tip="Toggle establishments"
                        @click="$dispatch('map:prepare-rebuild'); $wire.toggleEstablishments()"
                        wire:loading.attr="disabled"
                        wire:target="toggleEstablishments"
                        aria-label="Toggle establishments"
                        aria-pressed="{{ $showAmenities ? 'true' : 'false' }}"
                        @class([
                            'flex h-10 w-10 items-center justify-center transition',
                            'hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-800 dark:hover:text-white',
                            'focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 active:scale-95',
                            '[touch-action:manipulation] [-webkit-tap-highlight-color:transparent]',
                            'text-gray-500 dark:text-gray-400',
                            'disabled:opacity-60 disabled:cursor-wait',
                            'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' => $showAmenities,
                        ])>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 22V12h6v10"/>
                    </svg>
                </button>
            </div>

            <div class="flex flex-col overflow-hidden rounded-xl border border-gray-200/80
                        bg-white dark:bg-gray-900 shadow-lg shadow-gray-900/5
                        dark:border-gray-800/80">
                <button type="button" data-tip="Help & shortcuts (?)" @click="helpOpen = true" aria-label="Help"
                        class="flex h-10 w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/>
                    </svg>
                </button>
            </div>

            @if(!empty($routeCoords))
                <div class="flex flex-col overflow-hidden rounded-xl border border-rose-200/80 bg-white dark:bg-gray-900 shadow-lg shadow-gray-900/5 dark:border-rose-500/30">
                    <button type="button" data-tip="Clear route" wire:click="clearRoute" aria-label="Clear route"
                            class="flex h-10 w-10 items-center justify-center text-rose-500
                                   transition hover:bg-rose-50 hover:text-rose-700
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rose-500/50 active:scale-95
                                   dark:hover:bg-rose-500/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif
        </div>

        {{-- Mobile toolbar --}}
        <div x-data="{ actionsOpen: false }"
             :class="$wire.navigationActive ? 'opacity-0 pointer-events-none' : 'opacity-100'"
             class="lg:hidden absolute z-[1000] transition-all duration-200 ease-out
                    top-[max(0.5rem,var(--safe-top))] right-[max(0.5rem,var(--safe-right))]">

            <div class="flex items-center gap-1.5">
                <button type="button" @click="mobileOpen = true"
                        aria-label="Open filters"
                        class="flex h-11 min-w-[44px] items-center justify-center gap-1.5 rounded-xl
                               border border-gray-200/80 bg-white px-3.5
                               shadow-lg shadow-gray-900/5
                               text-gray-700 dark:text-gray-200
                               dark:border-gray-800/80 dark:bg-gray-900
                               transition active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h7"/>
                    </svg>
                    <span class="text-xs font-bold uppercase tracking-wider">Filters</span>
                </button>

                <button type="button" @click="locate()"
                        aria-label="My location"
                        class="flex h-11 w-11 items-center justify-center rounded-xl
                               border border-gray-200/80 bg-white
                               shadow-lg shadow-gray-900/5
                               text-gray-700 dark:text-gray-200
                               dark:border-gray-800/80 dark:bg-gray-900
                               transition active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                        :class="locating ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" x-show="!locating" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21c-4.5-4.5-7.5-8.24-7.5-11.5A7.5 7.5 0 0112 2a7.5 7.5 0 017.5 7.5c0 3.26-3 7-7.5 11.5z"/>
                        <circle cx="12" cy="9.5" r="2.5" stroke-width="2" fill="none"/>
                    </svg>
                    <svg xmlns="http://www.w3.org/2000/svg" x-show="locating" x-cloak class="h-5 w-5 animate-spin motion-reduce:animate-none"
                         fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>

                {{-- Recenter on user location. --}}
                <button type="button"
                        wire:click="recenterOnMe"
                        wire:loading.attr="disabled"
                        wire:target="recenterOnMe"
                        aria-label="Recenter on my location"
                        class="flex h-11 w-11 items-center justify-center rounded-xl
                               border border-gray-200/80 bg-white
                               shadow-lg shadow-gray-900/5
                               text-gray-700 dark:text-gray-200
                               dark:border-gray-800/80 dark:bg-gray-900
                               transition active:scale-[0.98]
                               disabled:opacity-60 disabled:cursor-wait
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="7"/>
                        <line x1="12" y1="2" x2="12" y2="5"/>
                        <line x1="12" y1="19" x2="12" y2="22"/>
                        <line x1="2" y1="12" x2="5" y2="12"/>
                        <line x1="19" y1="12" x2="22" y2="12"/>
                        <circle cx="12" cy="12" r="2" fill="currentColor"/>
                    </svg>
                </button>

                <button type="button"
                        @click="$dispatch('map:prepare-rebuild'); $wire.toggleEstablishments()"
                        wire:loading.attr="disabled"
                        wire:target="toggleEstablishments"
                        aria-label="Toggle establishments"
                        aria-pressed="{{ $showAmenities ? 'true' : 'false' }}"
                        @class([
                            'flex h-11 w-11 items-center justify-center rounded-xl',
                            'border shadow-lg shadow-gray-900/5',
                            'transition active:scale-95',
                            '[touch-action:manipulation] [-webkit-tap-highlight-color:transparent]',
                            'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50',
                            'disabled:opacity-60 disabled:cursor-wait',
                            'border-primary-500 bg-primary-50 text-primary-700 dark:border-primary-500/40 dark:bg-primary-500/15 dark:text-primary-300' => $showAmenities,
                            'border-gray-200/80 bg-white text-gray-700 dark:border-gray-800/80 dark:bg-gray-900 dark:text-gray-200' => !$showAmenities,
                        ])>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 22V12h6v10"/>
                    </svg>
                </button>

                <button type="button" @click="actionsOpen = true"
                        aria-label="More actions"
                        class="flex h-11 w-11 items-center justify-center rounded-xl
                               border border-gray-200/80 bg-white
                               shadow-lg shadow-gray-900/5
                               text-gray-700 dark:text-gray-200
                               dark:border-gray-800/80 dark:bg-gray-900
                               transition active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="5" cy="12" r="2"/>
                        <circle cx="12" cy="12" r="2"/>
                        <circle cx="19" cy="12" r="2"/>
                    </svg>
                </button>
            </div>

            <div x-cloak
                 x-show="actionsOpen"
                 class="fixed inset-0 z-[1400] bg-black/50 backdrop-blur-sm flex items-end
                        [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
                 @click.self="actionsOpen = false"
                 role="dialog"
                 aria-modal="true"
                 aria-label="More map actions">

                <div class="mobile-action-sheet w-full rounded-t-2xl bg-white dark:bg-gray-900
                            pt-3 px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <div class="sheet-handle"></div>

                    <p class="mt-4 mb-3 text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-500 text-center">
                        Map actions
                    </p>

                    <div class="grid grid-cols-3 gap-2">
                        <button type="button"
                                @click="actionsOpen = false; handleFollowButton()"
                                class="flex flex-col items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 min-h-[64px] py-3
                                       text-gray-700 dark:text-gray-200 dark:border-gray-700 dark:bg-gray-800
                                       transition active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L21 21 12 17 3 21Z"/>
                            </svg>
                            <span class="text-[10px] font-bold uppercase tracking-wider">Follow</span>
                        </button>

                        {{-- Recenter — now calls a server action that works
                             regardless of navigation state. --}}
                        <button type="button"
                                wire:click="recenterOnMe"
                                wire:loading.attr="disabled"
                                wire:target="recenterOnMe"
                                @click="actionsOpen = false"
                                class="flex flex-col items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 min-h-[64px] py-3
                                       text-gray-700 dark:text-gray-200 dark:border-gray-700 dark:bg-gray-800
                                       transition active:scale-95
                                       disabled:opacity-60 disabled:cursor-wait
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="8"/>
                                <line x1="12" y1="2" x2="12" y2="4"/>
                                <line x1="12" y1="20" x2="12" y2="22"/>
                                <line x1="2" y1="12" x2="4" y2="12"/>
                                <line x1="20" y1="12" x2="22" y2="12"/>
                            </svg>
                            <span class="text-[10px] font-bold uppercase tracking-wider">Recenter</span>
                        </button>

                        <button type="button"
                                @click="actionsOpen = false; mapLoading = true; if (window.__mapCanvasPoll) window.__mapCanvasPoll.start(); $wire.toggleSatellite()"
                                class="flex flex-col items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 min-h-[64px] py-3
                                       text-gray-700 dark:text-gray-200 dark:border-gray-700 dark:bg-gray-800
                                       transition active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2 17l10 5 10-5"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2 12l10 5 10-5"/>
                            </svg>
                            <span class="text-[10px] font-bold uppercase tracking-wider">Satellite</span>
                        </button>

                        {{-- Map theme toggle --}}
                        <button type="button"
                                wire:click="toggleMapTheme"
                                wire:loading.attr="disabled"
                                wire:target="toggleMapTheme"
                                @click="actionsOpen = false"
                                aria-label="Toggle map theme"
                                aria-pressed="{{ $mapTheme === 'dark' ? 'true' : 'false' }}"
                                class="flex flex-col items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 min-h-[64px] py-3
                                       text-gray-700 dark:text-gray-200 dark:border-gray-700 dark:bg-gray-800
                                       transition active:scale-95
                                       disabled:opacity-60 disabled:cursor-wait
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            @if($mapTheme === 'dark')
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="12" r="4"/>
                                    <path d="M12 2v2"/>
                                    <path d="M12 20v2"/>
                                    <path d="m4.93 4.93 1.41 1.41"/>
                                    <path d="m17.66 17.66 1.41 1.41"/>
                                    <path d="M2 12h2"/>
                                    <path d="M20 12h2"/>
                                    <path d="m6.34 17.66-1.41 1.41"/>
                                    <path d="m19.07 4.93-1.41 1.41"/>
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                                </svg>
                            @endif
                            <span class="text-[10px] font-bold uppercase tracking-wider">Theme</span>
                        </button>

                        <button type="button"
                                @click="actionsOpen = false; $dispatch('map:prepare-rebuild'); $wire.toggleEstablishments()"
                                wire:loading.attr="disabled"
                                wire:target="toggleEstablishments"
                                class="flex flex-col items-center gap-1.5 rounded-xl border px-3 min-h-[64px] py-3
                                       transition active:scale-95 disabled:opacity-60 disabled:cursor-wait
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                :class="$wire.showAmenities
                                    ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300 dark:border-primary-500/40'
                                    : 'border-gray-200 bg-white text-gray-700 dark:text-gray-200 dark:border-gray-700 dark:bg-gray-800'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 22V12h6v10"/>
                            </svg>
                            <span class="text-[10px] font-bold uppercase tracking-wider">Establishments</span>
                        </button>

                        <button type="button"
                                @click="actionsOpen = false; helpOpen = true"
                                class="flex flex-col items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 min-h-[64px] py-3
                                       text-gray-700 dark:text-gray-200 dark:border-gray-700 dark:bg-gray-800
                                       transition active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/>
                            </svg>
                            <span class="text-[10px] font-bold uppercase tracking-wider">Help</span>
                        </button>

                        @if(!empty($routeCoords))
                            <button type="button"
                                    @click="actionsOpen = false; $wire.clearRoute()"
                                    class="flex flex-col items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 min-h-[64px] py-3
                                           text-rose-600 dark:text-rose-300 dark:border-rose-500/30 dark:bg-rose-500/10
                                           transition active:scale-95
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span class="text-[10px] font-bold uppercase tracking-wider">Clear</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Route drawer --}}
        @if(!empty($routeCoords))
            <div :class="$wire.navigationActive ? 'hidden' : 'map-route-drawer'"
                 class="absolute z-[900]
                        inset-x-0 bottom-0
                        sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:bottom-6
                        {{ $detailTenantId && !$navigationActive ? 'lg:left-[calc(50%-210px)]' : '' }}
                        w-full sm:w-auto sm:max-w-md
                        rounded-t-2xl sm:rounded-2xl
                        bg-white dark:bg-gray-900
                        ring-1 ring-gray-200/80 dark:ring-gray-800/80
                        shadow-2xl shadow-gray-900/15
                        overflow-hidden
                        pb-[env(safe-area-inset-bottom)]">

                <div class="sm:hidden pt-3 pb-1">
                    <div class="sheet-handle"></div>
                </div>

                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400 leading-none mb-1">
                                Route to
                            </p>
                            <p class="font-display text-lg sm:text-xl font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                {{ $routeDestinationName }}
                            </p>
                        </div>

                        <button type="button" wire:click="clearRoute"
                                aria-label="Cancel route"
                                class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg
                                       text-gray-400 hover:text-rose-500 hover:bg-rose-50
                                       dark:text-gray-500 dark:hover:text-rose-400 dark:hover:bg-rose-500/10
                                       transition active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex-1 flex gap-0.5 rounded-xl bg-gray-100 dark:bg-gray-800/60 p-1 min-w-0">
                            @foreach(['driving' => 'Drive', 'walking' => 'Walk', 'cycling' => 'Cycle'] as $p => $l)
                                <button type="button"
                                        wire:click="setDirectionsProfile('{{ $p }}')"
                                        aria-pressed="{{ $directionsProfile === $p ? 'true' : 'false' }}"
                                        class="flex-1 rounded-lg px-2 py-2.5 text-xs font-bold uppercase tracking-wider
                                               transition active:scale-95
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                               {{ $directionsProfile === $p
                                                  ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-700 dark:text-primary-300'
                                                  : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                                    {{ $l }}
                                </button>
                            @endforeach
                        </div>

                        <button type="button"
                                x-on:click="startNavigation()"
                                class="shrink-0 inline-flex items-center gap-2 rounded-xl px-5 py-2.5 min-h-[44px]
                                       bg-primary-600 hover:bg-primary-700 text-white
                                       text-sm font-bold uppercase tracking-wider
                                       shadow-lg shadow-primary-600/30
                                       transition active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2 L21 22 L12 17.5 L3 22 Z"/>
                            </svg>
                            Navigate
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ═══════════════ DETAIL PANEL ═══════════════ --}}
    <div x-cloak
         :class="($wire.detailTenantId !== null && !$wire.navigationActive) ? 'map-detail-panel' : 'hidden'"
         class="fixed inset-x-0 bottom-0 z-[1200] max-h-[85dvh] flex flex-col
                lg:absolute lg:inset-y-0 lg:left-auto lg:right-0 lg:bottom-auto lg:top-0
                lg:w-[420px] lg:max-h-full
                bg-white dark:bg-gray-900
                border-t lg:border-t-0 lg:border-l border-gray-200 dark:border-gray-800
                rounded-t-2xl lg:rounded-none shadow-2xl overflow-hidden"
         role="dialog"
         aria-modal="false"
         aria-labelledby="detail-panel-title">

        <button type="button"
                x-cloak
                :class="!detailMinimized ? 'flex' : 'hidden'"
                @click="detailMinimized = true"
                aria-label="Minimize details"
                class="lg:hidden absolute top-0 inset-x-0 z-20 justify-center pt-2 pb-3
                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                       focus:outline-none">
            <span class="h-1 w-10 rounded-full bg-white/70 backdrop-blur-sm"></span>
        </button>

        @if($this->detailTenant)
            @php
                $dt      = $this->detailTenant;
                $isEst   = $this->detailIsEstablishment;
                $coord   = $this->detailCoord;
                $cat     = $this->detailCategory;

                $hours = $dt->settings->first()?->value['opening_hours'] ?? null;
                $bio   = $dt->settings->first()?->value ?? [];
                $desc  = $bio['description'] ?? null;
                $site  = $bio['website'] ?? null;
                $fb    = $bio['social_links']['facebook'] ?? null;
                $ig    = $bio['social_links']['instagram'] ?? null;

                $driving = $drivingDistances["{$dt->id}:{$detailCoordIndex}"] ?? null;
                $dist = $driving
                    ? (float) $driving['distance_km']
                    : ($coord ? $this->distance($coord['lat'], $coord['lng']) : null);
                $durMin = $driving['duration_min'] ?? null;

                $statusLabel = $this->openStatusLabel($dt);
                $hasHours = (bool) $hours;

                $displayName = $isEst
                    ? ($coord['name'] ?? 'Establishment')
                    : $dt->name;

                $displayType = $isEst
                    ? ($cat['label'] ?? 'Establishment')
                    : ($dt->typeOfTenant?->type ?? 'Business');

                $catIconSvg = $isEst ? ($cat['icon_svg'] ?? null) : null;
                $catColor   = $isEst ? ($cat['color'] ?? '#94a3b8') : null;
            @endphp

            <div x-cloak
                 :class="detailMinimized ? 'flex' : 'hidden'"
                 class="lg:hidden items-center gap-3 px-4 py-3">
                <button type="button" @click="detailMinimized = false"
                        class="flex flex-1 min-w-0 items-center gap-3 text-left rounded-lg min-h-[44px] py-1
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <div class="h-9 w-9 shrink-0 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-800 ring-2 ring-white dark:ring-gray-900 shadow">
                        @if($isEst && $catIconSvg)
                            <div class="flex h-full w-full items-center justify-center"
                                 style="background: {{ $catColor }}20; color: {{ $catColor }};">
                                <div class="h-4 w-4">
                                    <x-safe-svg :svg="$catIconSvg" class="h-full w-full fill-none stroke-current stroke-2" />
                                </div>
                            </div>
                        @elseif($dt->logo)
                            <img src="/storage/{{ ltrim($dt->logo, '/') }}" alt="" class="h-full w-full object-cover" decoding="async">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-[10px] font-bold text-gray-500 dark:text-gray-400">
                                {{ strtoupper(substr($displayName, 0, 2)) }}
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $displayName }}</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                            @if($isEst)
                                {{ $displayType }} · Part of {{ $dt->name }}
                            @else
                                Tap to expand
                            @endif
                        </p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
                    </svg>
                </button>
                <button type="button" wire:click="closeDetail" aria-label="Close details"
                        class="shrink-0 flex h-10 w-10 items-center justify-center rounded-full
                               text-gray-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10
                               transition active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div :class="detailMinimized ? 'hidden lg:contents' : 'contents'">

                {{-- HERO --}}
                <div class="relative h-40 sm:h-52 shrink-0">
                    @if($isEst)
                        <div class="h-full w-full flex items-center justify-center"
                             style="background: linear-gradient(135deg, {{ $catColor }} 0%, {{ $catColor }}cc 100%);">
                            @if($catIconSvg)
                                <div class="h-16 w-16 text-white/95 drop-shadow-lg">
                                    <x-safe-svg :svg="$catIconSvg" class="h-full w-full fill-none stroke-current stroke-2" />
                                </div>
                            @else
                                <span class="font-display text-5xl font-black text-white/95 tracking-tighter">
                                    {{ strtoupper(substr($displayName, 0, 2)) }}
                                </span>
                            @endif
                        </div>
                    @elseif($dt->logo)
                        <img src="/storage/{{ ltrim($dt->logo, '/') }}" alt="{{ $dt->name }}"
                             class="h-full w-full object-cover" loading="lazy" decoding="async">
                    @else
                        <div class="h-full w-full bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 flex items-center justify-center">
                            <span class="font-display text-6xl font-black text-white/90 tracking-tighter">
                                {{ strtoupper(substr($dt->name, 0, 2)) }}
                            </span>
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"></div>

                    <button type="button" wire:click="closeDetail" aria-label="Close details"
                            class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center
                                   rounded-full bg-black/40 backdrop-blur-md text-white
                                   hover:bg-black/60 transition active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    @if(!$isEst)
                        @php $isFav = in_array($dt->id, $favorites, true); @endphp
                        <button type="button" wire:click="toggleFavorite({{ $dt->id }})"
                                aria-label="{{ $isFav ? 'Remove from saved' : 'Save this spot' }}"
                                class="absolute right-14 top-3 flex h-10 w-10 items-center justify-center
                                       rounded-full bg-black/40 backdrop-blur-md text-white
                                       hover:bg-black/60 transition active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="{{ $isFav ? 'currentColor' : 'none' }}"
                                 stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
                            </svg>
                        </button>
                    @endif

                    <div class="absolute bottom-4 left-4 right-4">
                        <div class="flex flex-wrap items-center gap-1.5 mb-2">
                            <span class="rounded-full bg-white/20 backdrop-blur-md px-2.5 py-0.5
                                         text-[10px] font-bold uppercase tracking-wider text-white">
                                {{ $displayType }}
                            </span>

                            @if($isEst)
                                <span class="rounded-full bg-white/20 backdrop-blur-md px-2 py-0.5
                                             text-[10px] font-semibold text-white
                                             flex items-center gap-1.5">
                                    @if($dt->logo)
                                        <img src="/storage/{{ ltrim($dt->logo, '/') }}" alt=""
                                             class="h-3.5 w-3.5 rounded-full object-cover ring-1 ring-white/60">
                                    @endif
                                    {{ $dt->name }}
                                </span>
                            @else
                                @if($dt->is_recommended)
                                    <span class="rounded-full bg-amber-400 px-2.5 py-0.5
                                                 text-[10px] font-bold uppercase tracking-wider text-amber-900
                                                 flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        Recommended
                                    </span>
                                @endif

                                @if($hasHours)
                                    <span class="rounded-full backdrop-blur-md px-2.5 py-0.5
                                                 text-[10px] font-bold uppercase tracking-wider text-white
                                                 flex items-center gap-1
                                                 {{ $this->isOpenNow($dt) ? 'bg-emerald-500/40' : 'bg-rose-500/40' }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                        {{ $statusLabel }}
                                    </span>
                                @endif
                            @endif
                        </div>
                        <h2 id="detail-panel-title" class="font-display text-xl sm:text-2xl font-bold text-white leading-tight line-clamp-2">
                            {{ $displayName }}
                        </h2>
                    </div>
                </div>

                {{-- STATS --}}
                @if($isEst)
                    <div class="bg-white dark:bg-gray-900 py-3 text-center border-b border-gray-200 dark:border-gray-800 shrink-0">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Distance from you</p>
                        <p class="mt-1 text-base font-semibold text-gray-900 dark:text-white tabular-nums">
                            @if($dist !== null && $dist < PHP_FLOAT_MAX)
                                {{ $driving ? '' : '~' }}{{ $this->formatDistance($dist) }}
                                @if($driving && $durMin)
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400"> · {{ $this->formatDuration($durMin) }} drive</span>
                                @endif
                            @else
                                —
                            @endif
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-3 gap-px bg-gray-200 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-800 shrink-0">
                        <div class="bg-white dark:bg-gray-900 py-3 text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Distance</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                                @if($dist !== null && $dist < PHP_FLOAT_MAX)
                                    {{ $driving ? '' : '~' }}{{ $this->formatDistance($dist) }}
                                @else
                                    —
                                @endif
                            </p>
                            @if($driving && $durMin)
                                <p class="text-[9px] font-semibold tracking-wide text-gray-400 dark:text-gray-500">
                                    {{ $this->formatDuration($durMin) }} drive
                                </p>
                            @endif
                        </div>
                        <div class="bg-white dark:bg-gray-900 py-3 text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Places</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white tabular-nums">{{ $dt->properties->count() }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-900 py-3 text-center">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Services</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white tabular-nums">{{ $dt->services->count() }}</p>
                        </div>
                    </div>
                @endif

                {{-- BODY --}}
                <div class="flex-1 overflow-y-auto custom-scrollbar overscroll-contain">
                    @if($isEst)
                        <section class="px-5 py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
                                {{ $displayType }}
                            </h3>
                            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                This establishment is part of <strong class="font-semibold text-gray-900 dark:text-white">{{ $dt->name }}</strong>.
                            </p>
                            @if($dt->address)
                                <div class="mt-4 flex items-start gap-3">
                                    <div class="shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ $dt->address }}@if($dt->barangay && !str_contains($dt->address ?? '', $dt->barangay)), {{ $dt->barangay }}@endif
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </section>

                        <section class="px-5 py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
                                Location
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono tabular-nums">
                                {{ number_format((float) ($coord['lat'] ?? 0), 6) }}, {{ number_format((float) ($coord['lng'] ?? 0), 6) }}
                            </p>
                        </section>

                        <div class="h-4"></div>
                    @else
                        @if($desc)
                            <section class="px-5 py-5 border-b border-gray-100 dark:border-gray-800">
                                <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">About</h3>
                                <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $desc }}</p>
                            </section>
                        @endif

                        @if($dt->contact_number || $dt->email || $dt->address || $hours)
                            <section class="px-5 py-5 border-b border-gray-100 dark:border-gray-800">
                                <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-4">Contact &amp; Hours</h3>
                                <dl class="space-y-4 text-sm">

                                    @if($dt->address || $dt->barangay)
                                        <div class="flex items-start gap-3">
                                            <div class="shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <dt class="sr-only">Address</dt>
                                                <dd class="text-gray-800 dark:text-gray-200 leading-snug">
                                                    {{ $dt->address }}
                                                    @if($dt->barangay && !str_contains($dt->address ?? '', $dt->barangay))
                                                        <span class="block text-gray-500 dark:text-gray-400 text-xs mt-1">{{ $dt->barangay }}</span>
                                                    @endif
                                                </dd>
                                            </div>
                                        </div>
                                    @endif

                                    @if($dt->contact_number)
                                        <div class="flex items-start gap-3">
                                            <div class="shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <dt class="sr-only">Phone</dt>
                                                <dd>
                                                    <a href="tel:{{ $dt->contact_number }}"
                                                       class="text-primary-600 dark:text-primary-400 font-medium hover:underline rounded
                                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                                        {{ $dt->contact_number }}
                                                    </a>
                                                </dd>
                                            </div>
                                        </div>
                                    @endif

                                    @if($dt->email)
                                        <div class="flex items-start gap-3">
                                            <div class="shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <dt class="sr-only">Email</dt>
                                                <dd>
                                                    <a href="mailto:{{ $dt->email }}"
                                                       class="text-primary-600 dark:text-primary-400 font-medium hover:underline break-all rounded
                                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                                        {{ $dt->email }}
                                                    </a>
                                                </dd>
                                            </div>
                                        </div>
                                    @endif

                                    @if($hours && !empty($hours['opening']) && !empty($hours['closing']))
                                        <div class="flex items-start gap-3">
                                            <div class="shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <dt class="sr-only">Hours</dt>
                                                <dd class="text-gray-800 dark:text-gray-200 tabular-nums">
                                                    {{ $hours['opening'] }} – {{ $hours['closing'] }}
                                                    <span class="ml-2 text-xs font-semibold {{ $this->isOpenNow($dt) ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                        ({{ $statusLabel }})
                                                    </span>
                                                </dd>
                                            </div>
                                        </div>
                                    @endif

                                    @if($site || $fb || $ig)
                                        <div class="flex items-start gap-3">
                                            <div class="shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1 flex flex-wrap gap-3">
                                                <dt class="sr-only">Links</dt>
                                                @if($site)
                                                    <a href="{{ $site }}" target="_blank" rel="noopener noreferrer"
                                                       class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline rounded
                                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">Website</a>
                                                @endif
                                                @if($fb)
                                                    <a href="{{ $fb }}" target="_blank" rel="noopener noreferrer"
                                                       class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline rounded
                                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">Facebook</a>
                                                @endif
                                                @if($ig)
                                                    <a href="{{ $ig }}" target="_blank" rel="noopener noreferrer"
                                                       class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline rounded
                                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">Instagram</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </dl>
                            </section>
                        @endif

                        @if($dt->properties->isNotEmpty())
                            <section class="px-5 py-5 border-b border-gray-100 dark:border-gray-800">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Places Available</h3>
                                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 tabular-nums">{{ $dt->properties->count() }}</span>
                                </div>
                                <div class="space-y-2">
                                    @foreach($dt->properties as $prop)
                                        @php $img = $prop->images->first(); @endphp
                                        <div wire:key="detail-prop-{{ $prop->id }}"
                                             class="flex gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <div class="h-14 w-14 shrink-0 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800">
                                                @if($img)
                                                    <img src="/storage/{{ ltrim($img->image_path, '/') }}"
                                                         alt="{{ $prop->name }}"
                                                         class="h-full w-full object-cover" loading="lazy" decoding="async">
                                                @else
                                                    <div class="flex h-full w-full items-center justify-center text-gray-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                                        </svg>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $prop->name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {{ $prop->propertyType?->name ?? 'Place' }}
                                                    @if($prop->capacity) · Fits {{ $prop->capacity }} @endif
                                                </p>
                                                <p class="mt-1 text-sm font-bold text-primary-600 dark:text-primary-400 tabular-nums">
                                                    ₱{{ number_format((float) $prop->price, 2) }}
                                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">/ night</span>
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if($dt->services->isNotEmpty())
                            <section class="px-5 py-5 border-b border-gray-100 dark:border-gray-800">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Services</h3>
                                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 tabular-nums">{{ $dt->services->count() }}</span>
                                </div>
                                <div class="space-y-1.5">
                                    @foreach($dt->services as $svc)
                                        <div wire:key="detail-svc-{{ $svc->id }}"
                                             class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                            <span class="text-sm text-gray-800 dark:text-gray-200 truncate">{{ $svc->name }}</span>
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums shrink-0">
                                                ₱{{ number_format((float) $svc->price, 2) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @php $events = $this->detailUpcomingEvents; @endphp
                        @if($events->isNotEmpty())
                            <section class="px-5 py-5 border-b border-gray-100 dark:border-gray-800">
                                <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-4">Upcoming Events</h3>
                                <div class="space-y-2">
                                    @foreach($events as $ev)
                                        <div wire:key="detail-event-{{ $ev->id }}"
                                             class="flex items-start gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <div class="shrink-0 rounded-lg bg-gradient-to-br from-purple-100 to-purple-50 dark:from-purple-900/30 dark:to-purple-800/20
                                                        border border-purple-200/60 dark:border-purple-500/20 px-2 py-1.5 text-center min-w-[52px]">
                                                <p class="text-[9px] font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300 leading-none">{{ $ev->start_date->format('M') }}</p>
                                                <p class="text-base font-extrabold text-purple-700 dark:text-purple-200 leading-tight tabular-nums">{{ $ev->start_date->format('d') }}</p>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $ev->name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $ev->type }} · {{ $ev->start_date->format('h:i A') }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <div class="h-4"></div>
                    @endif
                </div>

                {{-- BOTTOM ACTIONS --}}
                <div class="shrink-0 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3
                            pb-[max(0.75rem,env(safe-area-inset-bottom))]
                            flex gap-2">
                    @if($isEst)
                        <button type="button" wire:click="getDirectionsToDetail"
                                class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl min-h-[44px]
                                       bg-primary-600 hover:bg-primary-700 text-white
                                       px-4 text-sm font-bold transition active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            Directions
                        </button>
                    @else
                        <a href="{{ route('business.offerings', $dt->slug) }}" wire:navigate
                           class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl min-h-[44px]
                                  bg-primary-600 hover:bg-primary-700 text-white
                                  px-4 text-sm font-bold transition active:scale-95
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span class="hidden xs:inline">View Business</span>
                            <span class="xs:hidden">View</span>
                        </a>
                        <button type="button" wire:click="getDirectionsToDetail"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl min-h-[44px]
                                       border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900
                                       px-4 text-sm font-bold text-gray-700 dark:text-gray-200
                                       hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400
                                       transition active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <span class="hidden sm:inline">Directions</span>
                        </button>
                    @endif
                </div>

            </div>
        @endif
    </div>

    {{-- ═══════════════ TOASTS ═══════════════ --}}
    <div class="pointer-events-none fixed z-[1300] flex flex-col gap-2
                right-[max(0.75rem,var(--safe-right))] sm:right-5 transition-all duration-200"
         :class="$store.nav.active
            ? 'top-[max(3.5rem,calc(var(--safe-top)+0.5rem))] sm:top-20'
            : 'bottom-[max(0.75rem,var(--safe-bottom))] sm:bottom-6'"
         aria-live="polite" wire:ignore>
        <template x-for="t in toasts" :key="t.id">
            <div class="map-toast relative pointer-events-auto flex min-w-[220px] sm:min-w-[240px] max-w-[calc(100vw-1.5rem)] sm:max-w-sm items-center gap-3 rounded-xl border
                        bg-white p-3 shadow-lg dark:bg-gray-900 cursor-pointer
                        [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
                 :class="{
                     'border-l-4 border-l-emerald-500': t.type === 'success',
                     'border-l-4 border-l-rose-500':    t.type === 'error',
                     'border-l-4 border-l-primary-500': t.type === 'info',
                     'border-l-4 border-l-amber-500':   t.type === 'warning',
                 }"
                 @mouseenter="pauseToast(t)" @mouseleave="resumeToast(t)" @click="removeToast(t.id)">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" :class="{
                        'text-emerald-500': t.type === 'success',
                        'text-rose-500':    t.type === 'error',
                        'text-primary-500': t.type === 'info',
                        'text-amber-500':   t.type === 'warning',
                     }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="iconPath(t.type)"/>
                </svg>
                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200" x-text="t.message"></span>
                <button type="button" class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-md
                                             text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800
                                             transition active:scale-95
                                             [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                             focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                        @click.stop="removeToast(t.id)" aria-label="Dismiss">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <div class="absolute bottom-0 left-0 h-0.5 bg-current transition-all" :class="{
                        'text-emerald-500': t.type === 'success',
                        'text-rose-500':    t.type === 'error',
                        'text-primary-500': t.type === 'info',
                        'text-amber-500':   t.type === 'warning',
                     }" :style="'width:' + ((t.remaining / t.duration) * 100) + '%'"></div>
            </div>
        </template>
    </div>

    {{-- ═══════════════ HELP MODAL ═══════════════ --}}
    <div x-cloak wire:ignore data-help-modal
         :class="helpOpen ? 'flex' : 'hidden'"
         class="fixed inset-0 z-[1500] items-end sm:items-center justify-center bg-black/60 p-0 sm:p-4 backdrop-blur-sm
                [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
         @click.self="helpOpen = false"
         role="dialog"
         aria-modal="true"
         aria-labelledby="help-modal-title">
        <div class="map-help-panel w-full max-w-sm max-h-[90dvh] sm:max-h-[85dvh] overflow-hidden rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 id="help-modal-title" class="font-display text-xl font-semibold tracking-tight text-gray-900 dark:text-white">Legend &amp; Shortcuts</h2>
                <button type="button" @click="helpOpen = false" aria-label="Close help"
                        class="inline-flex items-center justify-center w-10 h-10 rounded-lg
                               text-gray-500 transition hover:bg-gray-200 hover:text-gray-700
                               dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                <p class="mb-3 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Map Legend</p>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-3 w-3 rounded-full bg-blue-600 ring-2 ring-white dark:ring-gray-900"></span> Your location</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-3 w-3 rounded-full bg-orange-500 ring-2 ring-white dark:ring-gray-900"></span> Tourist spot (anchor)</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-2 w-2 rounded-full bg-orange-500 opacity-70"></span> Establishment (small context)</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Establishments nearby (badge count)</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-3 w-3 rounded-full bg-purple-500 ring-2 ring-white dark:ring-gray-900"></span> Event</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-1 w-5 rounded bg-blue-600"></span> Active route</div>
                </div>

                <p class="mb-3 mt-6 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Establishments layer</p>
                <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    <p>• By default the map shows only the primary Tourist Spots — the large white-ringed anchors. Each anchor may carry a small <strong>+N</strong> badge indicating how many establishments sit inside it.</p>
                    <p>• Turn on <strong>Establishments</strong> from the sidebar filters, the toolbar, or the mobile actions sheet to reveal every sub-establishment marker. They render as small, muted pins underneath the anchors.</p>
                    <p>• Tap an establishment to see its name, category, and its own directions — not the parent's.</p>
                </div>

                <p class="mb-3 mt-6 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Navigation</p>
                <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    <p>• Tap <strong>Navigate</strong> on a route to enter live navigation. Other pins hide so you can focus on the road.</p>
                    <p>• The blue dot shows your position. The <strong>blue cone</strong> points where your phone is facing.</p>
                    <p>• The map stays <strong>heading-up</strong> — it rotates with you. The camera follows you until you pan — tap <strong>Recenter</strong> to resume.</p>
                    <p>• The <strong>Remaining</strong> value is measured along the road, not as the crow flies.</p>
                    <p>• Zoomed out past city level? The map flattens to north-up to save battery. Zoom back in and heading-up returns.</p>
                </div>

                <p class="mb-3 mt-6 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Location Tips</p>
                <div class="space-y-2 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    <p>• If the map shows you in the wrong place (common on desktops without GPS), append <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">?lat=10.90&lng=123.07</code> to the URL to jump to Victorias City centre.</p>
                    <p>• Tap your blue dot to copy a share link.</p>
                </div>

                <p class="mb-3 mt-6 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Keyboard Shortcuts</p>
                <div class="space-y-2">
                    @foreach(['/' => 'Focus search', 'L' => 'My location', 'F' => 'Follow mode', 'S' => 'Satellite', 'R' => 'Reset view', '?' => 'This help', 'Esc' => 'Close panels'] as $k => $l)
                        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                            <span>{{ $l }}</span>
                            <kbd class="rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 font-mono text-xs font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $k }}</kbd>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
{{-- ✅ End root --}}

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    [data-help-modal][x-cloak] { display: none !important; }

    :root {
        --safe-top:    env(safe-area-inset-top, 0px);
        --safe-bottom: env(safe-area-inset-bottom, 0px);
        --safe-left:   env(safe-area-inset-left, 0px);
        --safe-right:  env(safe-area-inset-right, 0px);
    }

    .mobile-action-sheet {
        animation: mobileActionSheetIn .22s cubic-bezier(.16, 1, .3, 1);
    }
    @keyframes mobileActionSheetIn {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .mobile-action-sheet { animation: none; }
    }

    .sheet-handle {
        width: 36px;
        height: 4px;
        border-radius: 9999px;
        background: rgba(156, 163, 175, 0.5);
        margin: 0 auto;
    }
    .dark .sheet-handle {
        background: rgba(75, 85, 99, 0.6);
    }

    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #475569;
    }

    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .scrollbar-hide::-webkit-scrollbar { display: none; }

    .tourist-user-marker {
        position: relative;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: auto;
        cursor: pointer;
        z-index: 2;
        will-change: transform;
    }

    .tourist-user-marker-ring {
        position: absolute;
        inset: 8px;
        border-radius: 50%;
        background: rgba(37, 99, 235, 0.22);
        animation: tourist-user-pulse 2s ease-out infinite;
        pointer-events: none;
    }

    @keyframes tourist-user-pulse {
        0%   { transform: scale(0.85); opacity: 0.75; }
        100% { transform: scale(1.8);  opacity: 0; }
    }

    .tourist-user-marker-halo {
        position: absolute;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(37, 99, 235, 0.08);
        border: 1px solid rgba(59, 130, 246, 0.32);
        pointer-events: none;
    }

    .dark .tourist-user-marker-halo {
        background: rgba(96, 165, 250, 0.1);
        border-color: rgba(96, 165, 250, 0.35);
    }

    .tourist-user-marker-dot {
        position: relative;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #2563EB;
        border: 2.5px solid #ffffff;
        /* Strengthened glow so the dot reads clearly against complex
           satellite imagery (dark forest, bright sand, busy urban). */
        box-shadow:
            0 2px 12px rgba(37, 99, 235, 0.75),
            0 0 0 1px rgba(255, 255, 255, 0.35);
        pointer-events: none;
        z-index: 1;
    }

    .dark .tourist-user-marker-dot {
        border-color: #0f172a;
        box-shadow:
            0 2px 14px rgba(96, 165, 250, 0.85),
            0 0 0 1px rgba(255, 255, 255, 0.25);
    }

    @media (prefers-reduced-motion: reduce) {
        .tourist-user-marker-ring { animation: none; opacity: 0.22; }
    }

    .tourist-nav-marker {
        width: 64px;
        height: 64px;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        position: relative;
        will-change: transform;
    }

    .tourist-nav-cone-wrap {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transform-origin: 50% 50%;
        pointer-events: none;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        will-change: transform;
    }

    .tourist-nav-cone {
        position: absolute;
        left: 50%;
        bottom: 50%;
        width: 104px;
        height: 104px;
        margin-left: -52px;
        background: radial-gradient(
            ellipse 45% 100% at 50% 100%,
            rgba(37, 99, 235, 0.85) 0%,
            rgba(59, 130, 246, 0.6) 25%,
            rgba(59, 130, 246, 0.3) 55%,
            rgba(59, 130, 246, 0.08) 80%,
            rgba(59, 130, 246, 0) 100%
        );
        clip-path: polygon(50% 100%, 0% 0%, 100% 0%);
        pointer-events: none;
    }
    .dark .tourist-nav-cone {
        background: radial-gradient(
            ellipse 45% 100% at 50% 100%,
            rgba(96, 165, 250, 0.9) 0%,
            rgba(96, 165, 250, 0.6) 25%,
            rgba(96, 165, 250, 0.3) 55%,
            rgba(96, 165, 250, 0.08) 80%,
            rgba(96, 165, 250, 0) 100%
        );
    }

    .tourist-nav-pulse {
        display: none;
    }

    @media (prefers-reduced-motion: reduce) {
        .tourist-nav-cone-wrap { transition: none; }
    }

    .tourist-nav-puck {
        position: relative;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: radial-gradient(circle at 50% 35%, #3b82f6 0%, #2563eb 65%, #1d4ed8 100%);
        box-shadow:
            0 3px 12px rgba(37, 99, 235, 0.55),
            0 0 0 2.5px #ffffff,
            0 0 0 4.5px rgba(37, 99, 235, 0.28);
        z-index: 1;
    }

    .dark .tourist-nav-puck {
        box-shadow:
            0 3px 12px rgba(37, 99, 235, 0.65),
            0 0 0 2.5px #0f172a,
            0 0 0 4.5px rgba(37, 99, 235, 0.4);
    }

    /* ─── User-location marker z-order fix ───
       Bug: user-location icon disappeared under satellite raster.
       Cause: MapLibre's DOM markers share a stacking context; the
       user marker was rendering behind newer markers or the raster
       imagery after a style swap.

       Fix: bump the MapLibre marker WRAPPER (.maplibregl-marker)
       that contains the user-location or nav-marker inner element.
       :has() support — Chrome 105+, Firefox 121+, Safari 15.4+.

       If the marker is NOT a MapLibre DOM marker (i.e. it's a
       MapLibre *Layer* added via addLayer), this CSS does nothing
       and the fix must be applied in mapApp() by re-adding the
       layer after every setStyle() call. */
    .maplibregl-marker:has(.tourist-user-marker),
    .maplibregl-marker:has(.tourist-nav-marker) {
        z-index: 9999 !important;
    }

    @media (hover: hover) and (min-width: 640px) {
        [data-tip] { position: relative; }
        [data-tip]:hover::after,
        [data-tip]:focus-visible::after {
            content: attr(data-tip);
            position: absolute;
            right: calc(100% + 8px);
            top: 50%;
            transform: translateY(-50%);
            white-space: nowrap;
            background: #111827;
            color: #f9fafb;
            font-size: 11px;
            font-weight: 600;
            padding: 5px 9px;
            border-radius: 6px;
            pointer-events: none;
            z-index: 1200;
            opacity: 0;
            animation: tooltip-in 120ms ease-out 250ms forwards;
        }
        [data-tip]:hover::before,
        [data-tip]:focus-visible::before {
            content: '';
            position: absolute;
            right: calc(100% + 3px);
            top: 50%;
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-left-color: #111827;
            pointer-events: none;
            z-index: 1200;
            opacity: 0;
            animation: tooltip-in 120ms ease-out 250ms forwards;
        }
        @keyframes tooltip-in { to { opacity: 1 } }
    }

    @media (max-width: 1023.98px) {
        [data-map-aside] { transform: translateX(-100%); }
        [data-map-aside].translate-x-0 { transform: translateX(0); }
    }

    @media (min-width: 420px) {
        .xs\:inline { display: inline; }
        .xs\:hidden { display: none; }
    }

    .map-toast {
        animation: mapToastIn .28s cubic-bezier(.16, 1, .3, 1);
    }
    @keyframes mapToastIn {
        from { opacity: 0; transform: translateY(10px) scale(.96); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .map-detail-panel { animation: mapDetailPanelInMobile .36s cubic-bezier(.16, 1, .3, 1); }
    @keyframes mapDetailPanelInMobile {
        from { opacity: 0; transform: translateY(32px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @media (min-width: 1024px) {
        .map-detail-panel { animation: mapDetailPanelInDesktop .36s cubic-bezier(.16, 1, .3, 1); }
    }
    @keyframes mapDetailPanelInDesktop {
        from { opacity: 0; transform: translateX(32px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .map-route-drawer { animation: mapRouteDrawerIn .28s cubic-bezier(.16, 1, .3, 1); }
    @keyframes mapRouteDrawerIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .map-help-panel { animation: mapHelpPanelIn .28s cubic-bezier(.16, 1, .3, 1); }
    @keyframes mapHelpPanelIn {
        from { opacity: 0; transform: translateY(20px) scale(.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    @media (prefers-reduced-motion: reduce) {
        .map-toast,
        .map-detail-panel,
        .map-route-drawer,
        .map-help-panel { animation: none; }
    }
</style>
@endpush