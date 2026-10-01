<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
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
    #[Url(as: 'q')] public string $search = '';
    #[Url(as: 'category', history: true)] public string $categoryFilter = '';
    #[Url(as: 'sort', history: true)] public string $sortBy = 'name';
    #[Url(as: 'open', history: true)] public bool $openNow = false;
    #[Url(as: 'offers', history: true)] public bool $hasOfferings = false;
    #[Url(as: 'saved', history: true)] public bool $favoritesOnly = false;
    #[Url(as: 'recommended', history: true)] public bool $recommendedOnly = false;
    #[Url(as: 'events', history: true)] public bool $showEvents = false;
    #[Url(as: 'establishments', history: true)] public bool $showAmenities = false;

    public ?float $userLat = null;
    public ?float $userLng = null;
    public bool $followMode = false;
    public bool $satellite = false;
    public string $mapTheme = 'light';
    public bool $sidebarOpen = true;
    public bool $navigationActive = false;

    public ?int $highlightedId = null;
    public array $routeCoords = [];
    public ?string $routeDestinationName = null;
    public ?int $routeTenantId = null;
    public string $directionsProfile = 'driving';
    public array $routePolyline = [];
    public float $routeDistanceKm = 0.0;
    public int $routeDurationMin = 0;

    public ?int $pendingMarkerId = null;
    public ?int $pendingEventId = null;
    public ?int $highlightedEventId = null;
    public bool $autoDirections = false;
    public array $favorites = [];

    public ?float $currentLat = null;
    public ?float $currentLng = null;
    public ?int $currentZoom = null;

    public int $mapForceReload = 0;
    public int $mapEpoch = 0;

    public ?int $pendingDirectionsTenantId = null;
    public int $pendingDirectionsCoordIndex = 0;
    public ?int $detailTenantId = null;
    public int $detailCoordIndex = 0;

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

        $this->hydrateQs();
    }

    protected function hydrateQs(): void
    {
        if (request()->filled('lat') && request()->filled('lng')) {
            $this->userLat = $this->currentLat = (float) request('lat');
            $this->userLng = $this->currentLng = (float) request('lng');
        }
        if (request()->filled('marker')) {
            $this->pendingMarkerId = (int) request('marker');
        }
        if ($this->pendingMarkerId && request()->boolean('directions')) {
            $this->autoDirections = true;
        }
        if (in_array(request('profile'), ['driving', 'walking', 'cycling'], true)) {
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
                'settings' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('key', 'business_info')->select('tenant_id', 'value'),
            ])
            ->selectRaw('tenants.*')
            ->selectRaw('(SELECT COUNT(*) FROM properties WHERE properties.tenant_id = tenants.id) as properties_count')
            ->selectRaw('(SELECT COUNT(*) FROM services   WHERE services.tenant_id  = tenants.id) as services_count')
            ->selectRaw('(SELECT MIN(price) FROM properties WHERE properties.tenant_id = tenants.id) as min_price')
            ->when($term, fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%")
                ->orWhereHas('typeOfTenant', fn ($t) => $t->where('type', 'like', "%{$term}%"))))
            ->when($this->categoryFilter, fn ($q) => $q->whereHas('typeOfTenant', fn ($s) => $s->where('type', $this->categoryFilter)))
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
        if (! $this->detailTenantId) return null;

        return Tenant::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->whereKey($this->detailTenantId)
            ->with([
                'typeOfTenant:id,type',
                'settings' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('key', 'business_info'),
                'properties' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('is_active', true)->orderBy('price')->limit(6)
                    ->select('id', 'tenant_id', 'name', 'price', 'capacity', 'quantity', 'property_type_id'),
                'properties.propertyType' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name', 'tenant_id'),
                'properties.images' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'property_id', 'image_path')->orderBy('id'),
                'services' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('is_active', true)->orderBy('price')->limit(6)
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
        if (! $t || empty($t->coordinates) || ! is_array($t->coordinates)) return null;

        $idx = max(0, min($this->detailCoordIndex, count($t->coordinates) - 1));

        return $t->coordinates[$idx] ?? null;
    }

    #[Computed]
    public function detailIsEstablishment(): bool
    {
        if ($this->detailCoordIndex === 0) return false;

        $c = $this->detailCoord;

        return is_array($c) && ($c['type'] ?? '') !== 'parent';
    }

    #[Computed]
    public function detailCategory(): ?array
    {
        if (! $this->detailIsEstablishment) return null;

        $type = $this->detailCoord['type'] ?? null;

        return $type ? (collect($this->markerCategories)->firstWhere('key', $type) ?: null) : null;
    }

    #[Computed]
    public function detailUpcomingEvents()
    {
        if (! $this->detailTenantId) return collect();

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
        if (! $this->showEvents) return collect();

        return Event::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('coordinates')
            ->where('is_active', true)
            ->where('start_date', '>=', now()->subDay())
            ->with('tenant:id,name,slug')
            ->get(['id', 'name', 'barangay', 'type', 'start_date', 'end_date', 'coordinates', 'featured', 'tenant_id'])
            ->map(function ($e) {
                $c = is_array($e->coordinates) ? $e->coordinates : json_decode($e->coordinates, true);
                if (! is_array($c) || ! isset($c['lat'], $c['lng'])) return null;

                return [
                    'id'         => $e->id,
                    'name'       => $e->name,
                    'barangay'   => $e->barangay,
                    'type'       => $e->type,
                    'start_date' => $e->start_date,
                    'end_date'   => $e->end_date,
                    'lat'        => (float) $c['lat'],
                    'lng'        => (float) $c['lng'],
                    'featured'   => (bool) $e->featured,
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
        if ($this->currentLat && $this->currentLng) return [(float) $this->currentLng, (float) $this->currentLat];
        if ($this->userLat && $this->userLng) return [(float) $this->userLng, (float) $this->userLat];

        return self::CITY_CENTER;
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
        if (! $hours) return false;

        $days = $hours['days'] ?? null;
        if (is_array($days) && ! in_array((int) now()->dayOfWeek, array_map('intval', $days), true)) return false;

        $now = now()->format('H:i');
        $o   = $hours['opening'] ?? '00:00';
        $c   = $hours['closing'] ?? '23:59';

        return $o <= $c ? ($now >= $o && $now <= $c) : ($now >= $o || $now <= $c);
    }

    protected function openStatusLabel(Tenant $tenant): string
    {
        if (! ($tenant->settings->first()?->value['opening_hours'] ?? null)) return '';

        return $this->isOpenNow($tenant) ? 'Open now' : 'Closed now';
    }

    public function distance($lat2, $lng2): float
    {
        if (! $this->userLat || ! $this->userLng) return PHP_FLOAT_MAX;

        return $this->haversine($this->userLat, $this->userLng, (float) $lat2, (float) $lng2);
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
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

    public function highlightMatch(string $text): HtmlString
    {
        $escaped = e($text);
        $term    = trim($this->search);

        if (! $term) return new HtmlString($escaped);

        $out = preg_replace(
            '/(' . preg_quote(e($term), '/') . ')/i',
            '<mark class="rounded-sm bg-primary-100 px-0.5 dark:bg-primary-500/30">$1</mark>',
            $escaped
        );

        return new HtmlString($out ?? $escaped);
    }

    public function favoritesKey(): string
    {
        return auth()->check() ? 'map_favorites_user_' . auth()->id() : 'map_favorites_guest';
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
                    'settings' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('key', 'business_info')->select('tenant_id', 'value'),
                ])
                ->select(['id', 'name', 'slug', 'logo', 'address', 'contact_number', 'email', 'coordinates', 'is_recommended', 'type_of_tenant_id', 'created_at'])
                ->find($id);
    }

    protected function coordOf(Tenant $t, int $index): ?array
    {
        if (empty($t->coordinates) || ! is_array($t->coordinates)) return null;

        $c = $t->coordinates[max(0, min($index, count($t->coordinates) - 1))] ?? null;

        return (is_array($c) && isset($c['lat'], $c['lng'])) ? $c : null;
    }

    protected function validPoint(float $lat, float $lng): bool
    {
        return is_finite($lat) && is_finite($lng) && abs($lat) <= 90 && abs($lng) <= 180 && ! ($lat === 0.0 && $lng === 0.0);
    }

    public function openDetail(int $tenantId, int $coordIndex = 0): void
    {
        $t = $this->resolveT($tenantId);
        if (! $t || empty($t->coordinates) || ! is_array($t->coordinates)) {
            $this->notify('No location found for this spot.', 'error');
            return;
        }

        $safe = max(0, min($coordIndex, count($t->coordinates) - 1));

        $this->detailTenantId   = $tenantId;
        $this->detailCoordIndex = $safe;
        $this->highlightedId    = $tenantId;

        $c = $t->coordinates[$safe] ?? $t->coordinates[0];
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
        if (! $this->detailTenantId) {
            $this->notify('Select a destination first.', 'error');
            return;
        }

        try {
            $this->getDirectionsTo($this->detailTenantId, $this->detailCoordIndex);
        } catch (\Throwable $e) {
            Log::error('[explore-map] getDirectionsToDetail failed', [
                'tenant_id' => $this->detailTenantId,
                'error'     => $e->getMessage(),
            ]);
            $this->notify('Could not start navigation. Please try again.', 'error');
        }
    }

    public function shareDetail(): void
    {
        if ($this->detailTenantId) $this->shareMarker($this->detailTenantId);
    }

    public function toggleFavorite(int $id): void
    {
        if (in_array($id, $this->favorites, true)) {
            $this->favorites = array_values(array_diff($this->favorites, [$id]));
            $this->notify('Removed from saved places.');
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
        $c = $t ? $this->coordOf($t, $index) : null;
        if (! $c) {
            $this->notify('Location not found.', 'error');
            return;
        }

        $this->highlightedId = $id;
        $this->dispatch('map:fly-to', center: [(float) $c['lng'], (float) $c['lat']], zoom: 17);
    }

    public function getDirectionsTo(int $id, int $index = 0): void
    {
        $t = $this->resolveT($id);
        if (! $t || empty($t->coordinates) || ! is_array($t->coordinates)) {
            $this->notify('No location found.', 'error');
            return;
        }

        $safe  = max(0, min($index, count($t->coordinates) - 1));
        $coord = $this->coordOf($t, $safe);

        if (! $coord) {
            $this->notify('This destination has no valid coordinates.', 'error');
            return;
        }
        if (! $this->validPoint((float) $coord['lat'], (float) $coord['lng'])) {
            $this->notify('This destination has invalid coordinates.', 'error');
            return;
        }

        if (! $this->userLat || ! $this->userLng) {
            $this->pendingDirectionsTenantId   = $id;
            $this->pendingDirectionsCoordIndex = $safe;
            $this->notify('Finding your location…');
            $this->dispatch('locate-for-directions');
            return;
        }

        $this->buildRoute($t, $safe, $coord['name'] ?? $t->name);
    }

    protected function buildRoute(Tenant $t, int $idx, string $label): void
    {
        $c = $this->coordOf($t, $idx);
        if (! $c) {
            $this->notify('Destination has no valid coordinates.', 'error');
            return;
        }

        $destLat = (float) $c['lat'];
        $destLng = (float) $c['lng'];
        $userLat = (float) $this->userLat;
        $userLng = (float) $this->userLng;

        if (! $this->validPoint($destLat, $destLng) || ! $this->validPoint($userLat, $userLng)) {
            $this->notify('Coordinates are invalid. Try setting your location again.', 'error');
            return;
        }

        try {
            $route = app(OsrmDistanceService::class)->fetchRoute($userLat, $userLng, $destLat, $destLng, $this->directionsProfile);
        } catch (\Throwable $e) {
            Log::error('[explore-map] OSRM fetchRoute threw', ['tenant_id' => $t->id, 'error' => $e->getMessage()]);
            $this->notify('Routing service unavailable. Please try again.', 'error');
            return;
        }

        if ($route === null || empty($route['coordinates']) || count($route['coordinates']) < 2) {
            $this->notify('Could not calculate a route right now. Please try again in a moment.', 'error');
            Log::warning('[explore-map] route fetch failed', ['tenant_id' => $t->id, 'profile' => $this->directionsProfile]);
            return;
        }

        $this->routeCoords          = ['start' => [$userLng, $userLat], 'end' => [$destLng, $destLat]];
        $this->routeDestinationName = $label;
        $this->routeTenantId        = $t->id;
        $this->highlightedId        = $t->id;
        $this->routePolyline        = $route['coordinates'];
        $this->routeDistanceKm      = (float) $route['distance_km'];
        $this->routeDurationMin     = (int) $route['duration_min'];
        $this->detailTenantId       = null;
        $this->detailCoordIndex     = 0;
        $this->pendingRouteBounds   = [[$userLng, $userLat], [$destLng, $destLat]];

        $this->dispatch('map:close-sidebar');
    }

    public function setDirectionsProfile(string $profile): void
    {
        if (! in_array($profile, ['driving', 'walking', 'cycling'], true)) return;

        if (empty($this->routeCoords['start']) || empty($this->routeCoords['end'])) {
            $this->directionsProfile = $profile;
            return;
        }

        $previous = $this->directionsProfile;
        [$start, $end] = [$this->routeCoords['start'], $this->routeCoords['end']];

        try {
            $route = app(OsrmDistanceService::class)->fetchRoute((float) $start[1], (float) $start[0], (float) $end[1], (float) $end[0], $profile);
        } catch (\Throwable $e) {
            Log::error('[explore-map] setDirectionsProfile threw', ['profile' => $profile, 'error' => $e->getMessage()]);
            $route = null;
        }

        if ($route === null || empty($route['coordinates']) || count($route['coordinates']) < 2) {
            $this->notify("Could not switch to the {$profile} route. Keeping {$previous}.", 'error');
            return;
        }

        $this->directionsProfile = $profile;
        $this->routePolyline     = $route['coordinates'];
        $this->routeDistanceKm   = (float) $route['distance_km'];
        $this->routeDurationMin  = (int) $route['duration_min'];
        $this->navigationActive  = false;
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
        $this->notify('Route cleared.');
    }

    public function startNavigation(): void
    {
        if (empty($this->routePolyline) || empty($this->routeCoords)) {
            $this->notify('Start a route first.');
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
        }

        if ($wasActive) $this->notify('Navigation ended.');
    }

    protected function resumePendingDirections(): bool
    {
        if (! $this->pendingDirectionsTenantId) return false;

        [$id, $idx] = [$this->pendingDirectionsTenantId, $this->pendingDirectionsCoordIndex];
        $this->pendingDirectionsTenantId   = null;
        $this->pendingDirectionsCoordIndex = 0;

        $t = $this->resolveT($id);
        $c = $t ? $this->coordOf($t, $idx) : null;

        if (! $c) {
            $this->notify('Destination no longer available.', 'error');
            return true;
        }

        $this->buildRoute($t, $idx, $c['name'] ?? $t->name);

        return true;
    }

    public function useCityCenterLocation(): void
    {
        $this->userLat = $this->currentLat = self::CITY_CENTER[1];
        $this->userLng = $this->currentLng = self::CITY_CENTER[0];
        $this->currentZoom = 14;

        $this->loadDrivingDistances();
        $this->notify('Location set to Victorias City centre.', 'success');

        if (! empty($this->routeCoords) && $this->routeTenantId) {
            $t = $this->resolveT($this->routeTenantId);
            if ($t && ! empty($t->coordinates[$this->detailCoordIndex])) {
                $this->buildRoute($t, $this->detailCoordIndex, $t->coordinates[$this->detailCoordIndex]['name'] ?? $t->name);
            }
            return;
        }

        if ($this->resumePendingDirections()) return;

        $this->dispatch('map:fly-to', center: self::CITY_CENTER, zoom: 14);
    }

    public function setUserLocation($lat, $lng): void
    {
        if ($this->navigationActive) return;

        $this->userLat     = $this->currentLat = round((float) $lat, 6);
        $this->userLng     = $this->currentLng = round((float) $lng, 6);
        $this->currentZoom = 15;

        if ($this->resumePendingDirections()) return;

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

    public function locationFailed(string $reason = 'unavailable'): void
    {
        $this->autoDirections              = false;
        $this->pendingDirectionsTenantId   = null;
        $this->pendingDirectionsCoordIndex = 0;
        $this->navigationActive            = false;

        $this->notify(match ($reason) {
            'denied'   => "Location access was denied. Allow location in your browser's address bar, then try again.",
            'timeout'  => 'Location timed out. Please try again.',
            'insecure' => 'Location needs a secure connection. Open the site via http://127.0.0.1:8000 or https.',
            default    => 'Your location could not be determined.',
        }, $reason === 'timeout' ? 'warning' : 'error');
    }

    public function toggleFollowMode(bool $on): void
    {
        $this->followMode = $on;
        $this->notify($on ? 'Following your location.' : 'Follow mode off.');
    }

    public function toggleSatellite(): void
    {
        $this->satellite = ! $this->satellite;
        $this->notify($this->satellite ? 'Satellite view on.' : 'Standard view on.');
    }

    public function toggleEstablishments(): void
    {
        $this->showAmenities = ! $this->showAmenities;
        $this->mapEpoch++;
    }

    public function recenterOnMe(): void
    {
        if ($this->userLat && $this->userLng) {
            $this->dispatch('map:fly-to', center: [(float) $this->userLng, (float) $this->userLat], zoom: 15);
            $this->notify('Centered on your location.', 'success');
            return;
        }

        $this->dispatch('request-location-for-distance');
        $this->notify('Finding your location…');
    }

    public function toggleMapTheme(): void
    {
        $this->mapTheme = $this->mapTheme === 'dark' ? 'light' : 'dark';
        session(['map_theme' => $this->mapTheme]);
        $this->mapEpoch++;
        $this->notify($this->mapTheme === 'dark' ? 'Dark map enabled.' : 'Light map enabled.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'openNow', 'hasOfferings', 'favoritesOnly', 'recommendedOnly', 'showEvents', 'showAmenities']);
        $this->mapEpoch++;
        $this->loadDrivingDistances();
        $this->notify('Filters cleared.');
    }

    public function resetView(): void
    {
        $this->dispatch('map:fly-to', center: self::CITY_CENTER, zoom: 12);
    }

    public function shareLocation(): void
    {
        if (! $this->userLat) {
            $this->notify('Find your location first.');
            return;
        }
        $this->dispatch('copy-to-clipboard', text: request()->url() . '?lat=' . $this->userLat . '&lng=' . $this->userLng);
        $this->notify('Location link copied.', 'success');
    }

    public function shareMarker(int $id): void
    {
        if (! $this->resolveT($id)) {
            $this->notify('Destination not found.', 'error');
            return;
        }
        $this->dispatch('copy-to-clipboard', text: request()->url() . '?marker=' . $id);
        $this->notify('Destination link copied.', 'success');
    }

    public function shareRoute(): void
    {
        if (! $this->routeTenantId) {
            $this->notify('Start a route first.');
            return;
        }
        $this->dispatch('copy-to-clipboard', text: request()->url() . '?marker=' . $this->routeTenantId . '&directions=1&profile=' . $this->directionsProfile);
        $this->notify('Route link copied.', 'success');
    }

    public function loadDrivingDistances(): void
    {
        if (! $this->userLat || ! $this->userLng || $this->tenants->isEmpty()) return;

        $pending = [];
        foreach ($this->tenants as $tenant) {
            foreach ($tenant->coordinates as $ci => $coord) {
                $key = "{$tenant->id}:{$ci}";
                if (! isset($coord['lat'], $coord['lng']) || isset($this->drivingDistances[$key])) continue;

                $pending[$key] = [(float) $coord['lat'], (float) $coord['lng']];
            }
        }

        if (empty($pending)) return;

        $results = app(OsrmDistanceService::class)->drivingDistancesBatch(
            (float) $this->userLat, (float) $this->userLng, $pending, maxTargets: 15, budgetSeconds: 3.5,
        );

        if (! empty($results)) $this->drivingDistances = array_merge($this->drivingDistances, $results);
    }

    public function updateViewport(?float $lat, ?float $lng, ?int $zoom): void
    {
        if ($lat)  $this->currentLat  = round($lat, 6);
        if ($lng)  $this->currentLng  = round($lng, 6);
        if ($zoom) $this->currentZoom = max(5, $zoom);
    }

    public function handleThemeChange(): void { $this->dispatch('map:resize'); }
    public function forceReloadMap(): void { $this->mapForceReload++; }

    public function updatedSearch(): void { $this->loadDrivingDistances(); }
    public function updatedCategoryFilter(): void { $this->loadDrivingDistances(); }
    public function updatedOpenNow(): void { $this->loadDrivingDistances(); }
    public function updatedHasOfferings(): void { $this->loadDrivingDistances(); }
    public function updatedFavoritesOnly(): void { $this->loadDrivingDistances(); }
    public function updatedRecommendedOnly(): void { $this->loadDrivingDistances(); }
    public function updatedShowEvents(): void { $this->loadDrivingDistances(); }

    public function updatedSortBy(string $v): void
    {
        if ($v === 'distance' && ! $this->userLat) $this->dispatch('request-location-for-distance');
    }

    protected function isUsablePoint(mixed $p): bool
    {
        return is_array($p) && count($p) === 2 && is_numeric($p[0] ?? null) && is_numeric($p[1] ?? null)
            && $this->validPoint((float) $p[1], (float) $p[0]);
    }

    public function getZoom(mixed $value = null): int
    {
        if (is_numeric($value)) $this->currentZoom = max(5, min(22, (int) $value));

        return (int) ($this->currentZoom ?? 12);
    }

    public function getCenter(mixed $value = null): array
    {
        return [
            'lng' => (float) ($this->currentLng ?? self::CITY_CENTER[0]),
            'lat' => (float) ($this->currentLat ?? self::CITY_CENTER[1]),
        ];
    }

    public function getBearing(mixed $value = null): float { return 0.0; }
    public function getPitch(mixed $value = null): float { return 0.0; }
    public function getLayer(string $id): bool { return false; }
    public function removeLayer(string $id): void {}
    public function getSource(string $id): bool { return false; }
    public function removeSource(string $id): void {}

    #[On('map:loaded')]
    public function onMapReady(): void
    {
        if ($this->pendingEventId) {
            $ev = Event::withoutGlobalScope(TenantScope::class)->where('is_active', true)->find($this->pendingEventId);
            $this->pendingEventId = null;

            if ($ev && ! empty($ev->coordinates)) {
                $this->dispatch('map:fly-to', center: [(float) $ev->coordinates['lng'], (float) $ev->coordinates['lat']], zoom: 17);
            }
            return;
        }

        if (! empty($this->pendingRouteBounds)) {
            $bounds = $this->pendingRouteBounds;
            $this->pendingRouteBounds = [];

            if (count($bounds) === 2 && $this->isUsablePoint($bounds[0]) && $this->isUsablePoint($bounds[1])) {
                $this->dispatch('map:fit-bounds', [
                    'bounds'  => $bounds,
                    'padding' => ['top' => 80, 'bottom' => 140, 'left' => 80, 'right' => 80],
                ]);
            }
            return;
        }

        if (! $this->pendingMarkerId) return;

        $t = $this->resolveT($this->pendingMarkerId);
        $this->pendingMarkerId = null;

        if (! $t || empty($t->coordinates)) {
            $this->autoDirections = false;
            return;
        }

        $this->highlightedId  = $t->id;
        $this->detailTenantId = $t->id;

        $c = $t->coordinates[0];
        $this->dispatch('map:fly-to', center: [(float) $c['lng'], (float) $c['lat']], zoom: 16);
        $this->dispatch('tenant-viewed', id: $t->id, name: $t->name, type: $t->typeOfTenant?->type ?? 'Business');

        if ($this->autoDirections) $this->dispatch('locate-for-directions');
    }

    #[On('map:marker-clicked')]
    public function onMarkerClicked($id, $lat, $lng): void
    {
        if (preg_match('/tenant-(\d+)-(\d+)/', (string) $id, $m)) {
            $this->openDetail((int) $m[1], (int) $m[2]);
            return;
        }

        if (preg_match('/^event-(\d+)$/', (string) $id)) {
            $this->dispatch('map:fly-to', center: [(float) $lng, (float) $lat], zoom: 17);
        }
    }
};
?>

@push('styles')
    <link rel="preconnect" href="https://basemaps.cartocdn.com" crossorigin>
    <link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
    <link rel="dns-prefetch" href="https://router.project-osrm.org">
    <link rel="dns-prefetch" href="https://nominatim.openstreetmap.org">
@endpush

@php
    $dt = $this->detailTenant;

    if ($dt) {
        $isEst    = $this->detailIsEstablishment;
        $coord    = $this->detailCoord;
        $cat      = $this->detailCategory;
        $info     = $dt->settings->first()?->value ?? [];
        $hours    = $info['opening_hours'] ?? null;
        $desc     = $info['description'] ?? null;
        $site     = $info['website'] ?? null;
        $fb       = $info['social_links']['facebook'] ?? null;
        $ig       = $info['social_links']['instagram'] ?? null;
        $isOpen   = $hours ? $this->isOpenNow($dt) : null;
        $statusLabel = $this->openStatusLabel($dt);
        $events   = $this->detailUpcomingEvents;

        $driving  = $drivingDistances["{$dt->id}:{$detailCoordIndex}"] ?? null;
        $dist     = $driving ? (float) $driving['distance_km'] : ($coord ? $this->distance((float) $coord['lat'], (float) $coord['lng']) : null);
        $distText = ($dist !== null && $dist < PHP_FLOAT_MAX) ? ($driving ? '' : '~') . $this->formatDistance($dist) : '—';
        $durText  = ($driving && ! empty($driving['duration_min'])) ? $this->formatDuration((int) $driving['duration_min']) : null;

        $displayName = $isEst ? ($coord['name'] ?? 'Establishment') : $dt->name;
        $displayType = $isEst ? ($cat['label'] ?? 'Establishment') : ($dt->typeOfTenant?->type ?? 'Business');
        $catSvg      = $isEst ? ($cat['icon_svg'] ?? null) : null;
        $catColor    = $isEst ? ($cat['color'] ?? '#94a3b8') : null;
        $isFav       = in_array($dt->id, $favorites, true);
        $logoUrl     = $dt->logo ? '/storage/' . ltrim($dt->logo, '/') : null;
    }

    $mapIcon = 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7';
    $eyeIcon = 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z';
    $xIcon   = 'M6 18L18 6M6 6l12 12';
    $activeCls = 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400';
    $eyebrow   = 'text-xs font-semibold uppercase tracking-[0.18em]';

    $tools = [
        ['Toggle sidebar', 'Sidebar', 'sidebarOpen = !sidebarOpen', 'M4 6h16M4 12h16M4 18h7', 'sidebarOpen'],
        ['My location (L)', 'Location', 'locate()', 'M12 21c-4.5-4.5-7.5-8.24-7.5-11.5A7.5 7.5 0 0112 2a7.5 7.5 0 017.5 7.5c0 3.26-3 7-7.5 11.5zM14.5 9.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z', 'locating'],
        ['Follow mode (F)', 'Follow', 'handleFollowButton()', 'M12 2L21 21 12 17 3 21z', 'followMode'],
        ['Recenter (R)', 'Recenter', '$wire.resetView()', 'M12 2v3m0 14v3M2 12h3m14 0h3M17 12a5 5 0 11-10 0 5 5 0 0110 0z', 'false'],
        ['Satellite (S)', 'Satellite', 'mapLoading = true; window.__mapCanvasPoll?.start(); $wire.toggleSatellite()', 'M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5', '$wire.satellite'],
        ['Map theme', 'Theme', '$wire.toggleMapTheme()', 'M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z', '$wire.mapTheme === "dark"'],
        ['Establishments', 'Establishments', "\$dispatch('map:prepare-rebuild'); \$wire.toggleEstablishments()", 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2zM9 22V12h6v10', '$wire.showAmenities'],
        ['Help (?)', 'Help', 'helpOpen = true', 'M22 12a10 10 0 11-20 0 10 10 0 0120 0zM9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01', 'false'],
    ];
    $toolGroups = [[0, 1, 2], [3, 4, 5, 6], [7]];
    $sheetTools = [2, 3, 4, 5, 6, 7];

    $toolBtn = 'flex flex-col items-center justify-center gap-1.5 rounded-2xl border min-h-[64px] px-3 py-3 text-xs font-semibold transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';
    $fab     = 'grid size-11 place-items-center rounded-2xl glass shadow-lg text-gray-700 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:text-gray-200';
    $close   = 'grid size-11 shrink-0 place-items-center rounded-full text-gray-400 transition hover:bg-rose-50 hover:text-rose-500 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 dark:hover:bg-rose-500/10 dark:hover:text-rose-400';
    $palette = ['#f97316', '#a855f7', '#3b82f6', '#14b8a6', '#eab308', '#10b981', '#8b5cf6', '#f43f5e'];
    $markerCats = $this->markerCategories;
@endphp

<div class="relative flex h-[calc(100dvh-4rem)] overflow-hidden font-sans dark:bg-gray-950 md:h-[calc(100dvh-5rem)]"
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

    <div x-cloak :class="mobileOpen ? 'opacity-100' : 'pointer-events-none opacity-0'"
         class="fixed inset-0 z-[1090] bg-black/60 backdrop-blur-sm transition-opacity duration-200 lg:hidden"
         @click="mobileOpen = false" aria-hidden="true"></div>

    <aside data-map-aside wire:init="loadDrivingDistances"
           class="fixed bottom-0 left-0 top-16 z-[1100] w-[85vw] max-w-[340px] shrink-0 border-r border-gray-200 bg-white will-change-transform
                  transition-[transform,width] duration-300 ease-out dark:border-gray-800 dark:bg-gray-900
                  md:top-20 lg:static lg:bottom-auto lg:top-0 lg:z-auto lg:max-w-none"
           :class="{
               'translate-x-0': mobileOpen,
               '-translate-x-full': !mobileOpen,
               'lg:translate-x-0 lg:w-[340px]': sidebarOpen,
               'lg:-translate-x-full lg:w-0 lg:overflow-hidden lg:border-r-0': !sidebarOpen,
           }">
        @include('livewire.partials.explore-sidebar')
    </aside>

    <div class="relative min-w-0 flex-1 overflow-hidden bg-gray-100 dark:bg-gray-900">

        <div x-cloak :class="mapLoading ? '' : 'hidden'"
             class="absolute inset-0 z-[900] flex items-center justify-center bg-gray-100 dark:bg-gray-900">
            <div class="px-6 text-center">
                <div class="mx-auto mb-3 size-10 animate-spin rounded-full border-2 border-primary-600 border-t-transparent motion-reduce:animate-none"></div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Loading map…</p>
            </div>
        </div>

        <div x-cloak :class="(mapStuck && !mapLoading) ? '' : 'hidden'"
             class="absolute inset-0 z-[900] flex items-center justify-center overflow-y-auto bg-gray-100 dark:bg-gray-900">
            <div class="max-w-sm px-6 py-8 text-center">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Map couldn't load</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Check your connection and try again.</p>
                <div class="mt-6 flex flex-col justify-center gap-2 sm:flex-row">
                    <button type="button" @click.stop="retryMap()" class="btn-primary min-h-[44px]">Retry</button>
                    <button type="button" onclick="window.location.reload()" class="btn-secondary min-h-[44px]">Reload page</button>
                </div>
            </div>
        </div>

        <div wire:key="map-{{ $satellite ? 'sat' : 'std' }}-{{ $mapForceReload }}-{{ $showAmenities ? 'full' : 'main' }}-{{ $mapEpoch }}-{{ $mapTheme }}"
             x-init="mapLoading = true" class="absolute inset-0 will-change-transform">
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
                class="size-full"
                :events="['click', 'marker-clicked']">
                <x-map-controls :zoom="true" :compass="true" :locate="false" :fullscreen="true" :scale="true" position="bottom-right" />

                @if(! $navigationActive)
                    @foreach($this->tenants as $tenant)
                        @php
                            $tc      = $palette[$loop->index % count($palette)];
                            $isRoute = $routeTenantId === $tenant->id && ! empty($routeCoords);
                            $isHL    = $highlightedId === $tenant->id;
                            $logo    = $tenant->logo ? '/storage/' . ltrim($tenant->logo, '/') : null;
                            $nearby  = max(0, count($tenant->coordinates) - 1);
                        @endphp
                        @foreach($tenant->coordinates as $ci => $coord)
                            @php $isParent = $ci === 0 || ($coord['type'] ?? '') === 'parent'; @endphp
                            @continue(! $isParent && ! $showAmenities)

                            @php
                                $coordType = $coord['type'] ?? null;
                                $mc = (! $isParent && $coordType) ? collect($markerCats)->firstWhere('key', $coordType) : null;
                                $color = $mc['color'] ?? $tc;
                            @endphp

                            <x-map-marker wire:key="m-{{ $tenant->id }}-{{ $ci }}-{{ $mapEpoch }}"
                                          :lat="$coord['lat']" :lng="$coord['lng']" :color="$color"
                                          id="tenant-{{ $tenant->id }}-{{ $ci }}" anchor="bottom"
                                          :class="$isParent ? 'z-30' : 'z-10'">
                                <x-marker-content>
                                    <div class="group flex cursor-pointer flex-col items-center">
                                        @if($isParent)
                                            <div class="relative grid size-12 place-items-center rounded-full border-[2.5px] bg-white shadow-xl shadow-gray-900/20 ring-2 ring-white transition-transform duration-150 motion-safe:group-hover:scale-110 dark:bg-gray-900 dark:ring-gray-900
                                                        {{ ($isRoute || $isHL) ? 'outline outline-2 outline-offset-2 outline-primary-500' : '' }}"
                                                 style="border-color: {{ $tc }};">
                                                @if($logo)
                                                    <img src="{{ $logo }}" alt="{{ $tenant->name }}" width="48" height="48" loading="lazy" decoding="async" class="size-full rounded-full object-cover">
                                                @else
                                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ strtoupper(substr($tenant->name, 0, 2)) }}</span>
                                                @endif
                                                @if($nearby > 0 && ! $showAmenities)
                                                    <span class="pointer-events-none absolute -right-2 -top-2 grid h-5 min-w-[20px] select-none place-items-center rounded-full bg-gray-700 px-1 text-xs font-bold tabular-nums text-white ring-2 ring-white dark:ring-gray-900"
                                                          aria-label="{{ $nearby }} establishments nearby">+{{ $nearby }}</span>
                                                @endif
                                            </div>
                                            <svg class="-mt-0.5 h-2 w-3" style="color: {{ $tc }};" viewBox="0 0 12 8" fill="currentColor" aria-hidden="true"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                        @elseif($mc)
                                            <div class="tap-area grid size-8 place-items-center rounded-full border bg-white/90 opacity-80 shadow-sm transition duration-150 motion-safe:group-hover:scale-110 motion-safe:group-hover:opacity-100 dark:bg-gray-900/90"
                                                 style="border-color: {{ $color }}80;">
                                                @if($mc['icon_svg'] ?? null)
                                                    <div class="size-3.5 text-gray-700 dark:text-gray-200">
                                                        <x-safe-svg :svg="$mc['icon_svg']" class="size-full fill-none stroke-current stroke-2" />
                                                    </div>
                                                @else
                                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ strtoupper(substr($coordType, 0, 1)) }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="tap-area size-2.5 rounded-full opacity-70 shadow-sm ring-2 ring-white transition duration-150 motion-safe:group-hover:scale-150 motion-safe:group-hover:opacity-100 dark:ring-gray-900"
                                                 style="background: {{ $tc }};"></div>
                                        @endif
                                    </div>
                                </x-marker-content>
                            </x-map-marker>
                        @endforeach
                    @endforeach

                    @if($showEvents)
                        @foreach($this->eventMarkers as $ev)
                            <x-map-marker wire:key="ev-m-{{ $ev['id'] }}-{{ $mapEpoch }}"
                                          :lat="$ev['lat']" :lng="$ev['lng']" color="#A78BFA"
                                          id="event-{{ $ev['id'] }}" anchor="bottom" class="z-20">
                                <x-marker-content>
                                    <div class="group flex cursor-pointer flex-col items-center {{ $highlightedEventId === $ev['id'] ? 'rounded-full ring-2 ring-purple-500 ring-offset-4 ring-offset-transparent' : '' }}">
                                        <div class="grid size-11 place-items-center rounded-full border-2 border-white bg-gradient-to-tr from-purple-600 to-pink-500 shadow-lg ring-2 ring-white transition-transform motion-safe:group-hover:scale-110 dark:border-gray-900 dark:ring-gray-900">
                                            <svg class="size-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                        </div>
                                        <svg class="-mt-0.5 h-2 w-3" style="color:#A78BFA;" viewBox="0 0 12 8" fill="currentColor" aria-hidden="true"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                    </div>
                                </x-marker-content>
                                <x-marker-popup>
                                    <div class="w-56 p-4">
                                        <p class="{{ $eyebrow }} text-purple-600 dark:text-purple-400">Event</p>
                                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $ev['name'] }}</h3>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $ev['start_date']->format('M d, Y') }}</p>
                                        <a href="{{ route('events') }}" wire:navigate @click.stop
                                           class="btn-primary mt-3 min-h-[44px] w-full bg-purple-600 px-3 py-2 text-xs shadow-purple-600/20 hover:bg-purple-700">View event</a>
                                    </div>
                                </x-marker-popup>
                            </x-map-marker>
                        @endforeach
                    @endif
                @endif
            </x-map>
        </div>

        <div wire:loading.delay.shortest wire:target="getDirectionsToDetail,getDirectionsTo,setDirectionsProfile" x-cloak
             class="absolute inset-0 z-[2000] flex items-center justify-center bg-black/40" aria-live="polite" aria-busy="true">
            <div class="glass flex flex-col items-center gap-3 rounded-3xl px-6 py-5 shadow-2xl">
                <div class="size-9 animate-spin rounded-full border-2 border-primary-600 border-t-transparent motion-reduce:animate-none" role="status" aria-label="Loading"></div>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Calculating route…</p>
            </div>
        </div>

        <div x-data="{
                show: false,
                dismissed: false,
                _compute: null,
                _timer: null,
                init() {
                    this._compute = () => {
                        const zoom = Number(this.$wire.currentZoom) || 12;
                        this.show = ! this.dismissed
                            && zoom < 14
                            && ! this.$wire.navigationActive
                            && ! this.$wire.showAmenities
                            && ! this.$wire.routeTenantId;
                    };
                    this._compute();
                    this.$watch('$wire.currentZoom',    () => this._compute());
                    this.$watch('$wire.navigationActive', () => this._compute());
                    this.$watch('$wire.showAmenities',  () => this._compute());
                    this.$watch('$wire.routeTenantId',  () => this._compute());
                    this._timer = setTimeout(() => { this.dismissed = true; this._compute(); }, 8000);
                },
                dismiss() { this.dismissed = true; this.show = false; },
                destroy() { if (this._timer) clearTimeout(this._timer); }
             }"
             x-on:map:center-changed.window="dismiss()"
             x-on:map:zoom-changed.window="dismiss()"
             x-on:map:marker-clicked.window="dismiss()"
             x-cloak
             :class="show ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'"
             class="pointer-events-none absolute bottom-4 left-4 z-[900] hidden select-none transition-all duration-300 lg:flex">
            <span class="rounded-full border border-gray-200/60 bg-white/70 px-3 py-1.5 text-[11px] font-medium text-gray-600 backdrop-blur-sm dark:border-white/10 dark:bg-gray-900/70 dark:text-gray-400">
                Zoom in or tap a pin to explore
            </span>
        </div>

        <div :class="$store.nav.active ? 'pointer-events-auto translate-y-0 opacity-100' : 'pointer-events-none -translate-y-3 opacity-0'"
             class="absolute left-2 right-2 top-[max(0.5rem,var(--safe-top))] z-[1200] flex items-center gap-3 rounded-full bg-gray-900 py-1.5 pl-4 pr-4 shadow-2xl shadow-black/30 ring-1 ring-white/10 transition-all duration-300 ease-out dark:bg-gray-950 sm:left-1/2 sm:right-auto sm:top-4 sm:w-[400px] sm:-translate-x-1/2">
            <div class="grid size-9 shrink-0 place-items-center rounded-full bg-primary-500/20 ring-1 ring-primary-400/40">
                <svg class="size-4 text-primary-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2 L21 22 L12 17.5 L3 22 Z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="{{ $eyebrow }} leading-none text-primary-300">Navigating</p>
                <p class="mt-1 truncate text-sm font-semibold leading-tight text-white">{{ $routeDestinationName ?? 'Destination' }}</p>
            </div>
        </div>

        <div :class="$store.nav.active ? 'pointer-events-auto translate-y-0 opacity-100' : 'pointer-events-none translate-y-3 opacity-0'"
             class="absolute bottom-[max(0.5rem,var(--safe-bottom))] left-2 right-2 z-[1200] flex items-center gap-1 rounded-full bg-gray-900 py-1.5 pl-4 pr-1.5 shadow-2xl shadow-black/30 ring-1 ring-white/10 transition-all duration-300 ease-out dark:bg-gray-950 sm:bottom-6 sm:left-1/2 sm:right-auto sm:w-[400px] sm:-translate-x-1/2">
            <div class="min-w-0 flex-1">
                <p class="{{ $eyebrow }} leading-none text-primary-300">Remaining</p>
                <p class="mt-1 text-base font-bold tabular-nums leading-tight text-white" x-text="remainingText"></p>
            </div>
            <button type="button" @click.stop="recenterNavigation()" aria-label="Recenter on my location"
                    class="grid size-11 shrink-0 place-items-center rounded-full text-white/85 transition hover:bg-white/10 hover:text-white active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v3m0 14v3M2 12h3m14 0h3M17 12a5 5 0 11-10 0 5 5 0 0110 0z"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg>
            </button>
            <button type="button" @click.stop="stopNavigation()"
                    class="btn-danger min-h-[44px] shrink-0 px-5 py-2 text-xs">End</button>
        </div>

        <div :class="$wire.navigationActive ? 'pointer-events-none opacity-0' : 'opacity-100'"
             class="absolute right-3 top-3 z-[1000] hidden flex-col gap-2 transition-all duration-200 ease-out lg:flex {{ $detailTenantId ? 'lg:right-[432px]' : '' }}">
            @foreach($toolGroups as $group)
                <div class="glass flex flex-col divide-y divide-gray-200 overflow-hidden rounded-2xl shadow-lg dark:divide-gray-800">
                    @foreach($group as $i)
                        @php $t = $tools[$i]; @endphp
                        <button type="button" data-tip="{{ $t[0] }}" aria-label="{{ $t[0] }}" @click.stop="{{ $t[2] }}"
                                :class="({{ $t[4] }}) ? '{{ $activeCls }}' : 'text-gray-500 dark:text-gray-400'"
                                class="grid size-11 place-items-center transition hover:bg-gray-100 hover:text-gray-900 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/50 dark:hover:bg-gray-800 dark:hover:text-white">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $t[3] }}"/></svg>
                        </button>
                    @endforeach
                </div>
            @endforeach

            @if(! empty($routeCoords))
                <div class="glass overflow-hidden rounded-2xl shadow-lg">
                    <button type="button" data-tip="Clear route" aria-label="Clear route" @click.stop="$wire.clearRoute()"
                            class="grid size-11 place-items-center text-rose-500 transition hover:bg-rose-50 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rose-500/50 dark:hover:bg-rose-500/10">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $xIcon }}"/></svg>
                    </button>
                </div>
            @endif
        </div>

        <div x-data="{ actionsOpen: false }"
             :class="$wire.navigationActive ? 'pointer-events-none opacity-0' : 'opacity-100'"
             class="absolute right-[max(0.5rem,var(--safe-right))] top-[max(0.5rem,var(--safe-top))] z-[1000] transition-all duration-200 ease-out lg:hidden">
            <div class="flex items-center gap-1.5">
                <button type="button" @click.stop="mobileOpen = true" aria-label="Open filters" class="{{ $fab }} w-auto gap-1.5 px-4">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
                    <span class="text-sm font-semibold">Filters</span>
                </button>
                @foreach([1, 6] as $i)
                    @php $t = $tools[$i]; @endphp
                    <button type="button" @click.stop="{{ $t[2] }}" aria-label="{{ $t[0] }}"
                            :class="({{ $t[4] }}) ? '{{ $activeCls }}' : ''" class="{{ $fab }}">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $t[3] }}"/></svg>
                    </button>
                @endforeach
                <button type="button" @click.stop="actionsOpen = true" aria-label="More actions" class="{{ $fab }}">
                    <svg class="size-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
                </button>
            </div>

            <div x-cloak x-show="actionsOpen" @click.self="actionsOpen = false"
                 class="fixed inset-0 z-[1400] flex items-end bg-black/50 backdrop-blur-sm"
                 role="dialog" aria-modal="true" aria-label="More map actions">
                <div class="mobile-action-sheet w-full rounded-t-3xl bg-white px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3 dark:bg-gray-900">
                    <div class="sheet-handle"></div>
                    <p class="mb-3 mt-4 text-center text-sm font-semibold text-gray-500 dark:text-gray-400">Map actions</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($sheetTools as $i)
                            @php $t = $tools[$i]; @endphp
                            <button type="button" @click.stop="actionsOpen = false; {{ $t[2] }}"
                                    :class="({{ $t[4] }}) ? 'border-primary-500 {{ $activeCls }}' : 'border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200'"
                                    class="{{ $toolBtn }}">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $t[3] }}"/></svg>
                                {{ $t[1] }}
                            </button>
                        @endforeach
                        @if(! empty($routeCoords))
                            <button type="button" @click.stop="actionsOpen = false; $wire.clearRoute()"
                                    class="{{ $toolBtn }} border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $xIcon }}"/></svg>
                                Clear route
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if(! empty($routeCoords))
            <div :class="$wire.navigationActive ? 'hidden' : 'map-route-drawer'"
                 class="glass absolute inset-x-0 bottom-0 z-[900] w-full overflow-hidden rounded-t-3xl pb-[env(safe-area-inset-bottom)] shadow-2xl shadow-gray-900/15
                        sm:inset-x-auto sm:bottom-6 sm:left-1/2 sm:w-auto sm:max-w-md sm:-translate-x-1/2 sm:rounded-3xl
                        {{ $detailTenantId && ! $navigationActive ? 'lg:left-[calc(50%-210px)]' : '' }}">
                <div class="pb-1 pt-3 sm:hidden"><div class="sheet-handle"></div></div>
                <div class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="{{ $eyebrow }} text-gray-500 dark:text-gray-400">Route to</p>
                            <p class="mt-1 truncate text-lg font-semibold leading-tight text-gray-900 dark:text-white sm:text-xl">{{ $routeDestinationName }}</p>
                            @if($routeDistanceKm > 0)
                                <p class="mt-1 text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $this->formatDistance($routeDistanceKm) }} · {{ $this->formatDuration($routeDurationMin) }}</p>
                            @endif
                        </div>
                        <button type="button" wire:click.stop="clearRoute" @click.stop aria-label="Cancel route" class="{{ $close }}">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $xIcon }}"/></svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex min-w-0 flex-1 gap-0.5 rounded-2xl bg-gray-100 p-1 dark:bg-gray-800/60">
                            @foreach(['driving' => 'Drive', 'walking' => 'Walk', 'cycling' => 'Cycle'] as $p => $l)
                                <button type="button" wire:click.stop="setDirectionsProfile('{{ $p }}')" @click.stop
                                        aria-pressed="{{ $directionsProfile === $p ? 'true' : 'false' }}"
                                        class="min-h-[44px] flex-1 rounded-xl px-2 text-sm font-semibold transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                               {{ $directionsProfile === $p ? 'bg-white text-primary-700 shadow-sm dark:bg-gray-700 dark:text-primary-300' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' }}">
                                    {{ $l }}
                                </button>
                            @endforeach
                        </div>
                        <button type="button" @click.stop="startNavigation()" class="btn-primary min-h-[44px] shrink-0 px-5">
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2 L21 22 L12 17.5 L3 22 Z"/></svg>
                            Navigate
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div x-cloak
         :class="($wire.detailTenantId !== null && !$wire.navigationActive) ? 'lg:flex map-detail-panel' : 'hidden'"
         class="map-detail-panel-desktop fixed bottom-0 right-0 top-20 z-[1200] hidden w-[420px] flex-col overflow-hidden border-l border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900"
         role="dialog" aria-modal="false" aria-labelledby="detail-panel-title">
        @if($dt)
            <div class="relative h-52 shrink-0">
                @if($isEst)
                    <div class="flex size-full items-center justify-center" style="background: linear-gradient(135deg, {{ $catColor }} 0%, {{ $catColor }}cc 100%);">
                        @if($catSvg)
                            <div class="size-16 text-white/95 drop-shadow-lg"><x-safe-svg :svg="$catSvg" class="size-full fill-none stroke-current stroke-2" /></div>
                        @else
                            <span class="font-display text-5xl font-black tracking-tighter text-white/95">{{ strtoupper(substr($displayName, 0, 2)) }}</span>
                        @endif
                    </div>
                @elseif($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $dt->name }}" class="size-full object-cover" loading="lazy" decoding="async">
                @else
                    <div class="flex size-full items-center justify-center bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700">
                        <span class="font-display text-6xl font-black tracking-tighter text-white/90">{{ strtoupper(substr($dt->name, 0, 2)) }}</span>
                    </div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"></div>

                <button type="button" wire:click.stop="closeDetail" @click.stop aria-label="Close details"
                        class="absolute right-3 top-3 grid size-11 place-items-center rounded-full bg-black/40 text-white backdrop-blur-md transition hover:bg-black/60 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $xIcon }}"/></svg>
                </button>

                @unless($isEst)
                    <button type="button" wire:click.stop="toggleFavorite({{ $dt->id }})" @click.stop
                            aria-label="{{ $isFav ? 'Remove from saved' : 'Save this spot' }}"
                            class="absolute right-16 top-3 grid size-11 place-items-center rounded-full bg-black/40 text-white backdrop-blur-md transition hover:bg-black/60 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                        <svg class="size-4" fill="{{ $isFav ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                    </button>
                @endunless

                <div class="absolute inset-x-4 bottom-4">
                    <div class="mb-2 flex flex-wrap items-center gap-1.5">
                        <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white backdrop-blur-md">{{ $displayType }}</span>
                        @if($isEst)
                            <span class="flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white backdrop-blur-md">
                                @if($logoUrl)<img src="{{ $logoUrl }}" alt="" class="size-4 rounded-full object-cover ring-1 ring-white/60">@endif
                                {{ $dt->name }}
                            </span>
                        @else
                            @if($dt->is_recommended)
                                <span class="rounded-full bg-primary-600 px-3 py-1 text-xs font-semibold text-white">Recommended</span>
                            @endif
                            @if($hours)
                                <span class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold text-white backdrop-blur-md {{ $isOpen ? 'bg-emerald-500/40' : 'bg-rose-500/40' }}">
                                    <span class="size-1.5 rounded-full bg-current"></span>{{ $statusLabel }}
                                </span>
                            @endif
                        @endif
                    </div>
                    <h2 id="detail-panel-title" class="line-clamp-2 font-display text-2xl font-bold leading-tight text-white">{{ $displayName }}</h2>
                </div>
            </div>

            <div class="custom-scrollbar flex-1 overflow-y-auto overscroll-contain">
                @include('livewire.partials.explore-detail-body', ['prefix' => 'desk'])
                <div class="h-4"></div>
            </div>

            <div class="flex shrink-0 gap-2 border-t border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                @unless($isEst)
                    <a href="{{ route('business.offerings', $dt->slug) }}" wire:navigate @click.stop class="btn-primary min-h-[44px] flex-1">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $eyeIcon }}"/></svg>
                        View business
                    </a>
                @endunless
                <button type="button" wire:click.stop="getDirectionsToDetail" @click.stop
                        class="{{ $isEst ? 'btn-primary flex-1' : 'btn-secondary' }} min-h-[44px]">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $mapIcon }}"/></svg>
                    Directions
                </button>
            </div>
        @endif
    </div>

    <div x-cloak
         x-data="{
             expanded: false, dragging: false, hasDragged: false,
             dragStartY: 0, dragStartTranslate: 0, dragCurrent: 0,
             headerHeight: 140, _lastTenantId: null, _measure: null,
             noDrag: {
                 ['@click.stop']() {},
                 ['@touchstart.stop']() {},
                 ['@touchmove.stop']() {},
                 ['@touchend.stop']() {},
             },
             get vh() { return window.innerHeight || 800; },
             get sheetHeight() { return this.vh * 0.88; },
             get peekTranslate() { return Math.max(80, this.sheetHeight - this.headerHeight); },
             get translate() {
                 if (this.$wire.detailTenantId === null) return this.vh + 80;
                 if (this.dragging) return this.dragCurrent;
                 return this.expanded ? 0 : this.peekTranslate;
             },
             init() {
                 this._measure = () => {
                     const el = document.querySelector('.mobile-detail-sheet-header');
                     if (el && el.offsetHeight > 0) this.headerHeight = el.offsetHeight;
                 };
                 window.addEventListener('resize', this._measure);
             },
             destroy() { window.removeEventListener('resize', this._measure); },
             _y(e) { return (e.touches && e.touches[0]) ? e.touches[0].clientY : e.clientY; },
             onDragStart(e) {
                 this.dragStartY = this._y(e);
                 this.dragStartTranslate = this.expanded ? 0 : this.peekTranslate;
                 this.dragCurrent = this.dragStartTranslate;
                 this.dragging = true;
                 this.hasDragged = false;
             },
             onDragMove(e) {
                 if (!this.dragging) return;
                 const dy = this._y(e) - this.dragStartY;
                 if (Math.abs(dy) > 10) this.hasDragged = true;
                 if (!this.hasDragged) return;
                 this.dragCurrent = Math.max(-30, Math.min(this.peekTranslate + 140, this.dragStartTranslate + dy));
             },
             onDragEnd() {
                 if (!this.dragging) return;
                 const moved = this.hasDragged;
                 this.dragging = false;
                 this.hasDragged = false;
                 if (!moved) { this.expanded = !this.expanded; return; }
                 if (this.dragCurrent > this.peekTranslate + 60) { this.$wire.closeDetail(); this.dragCurrent = 0; return; }
                 this.expanded = this.dragCurrent < this.peekTranslate / 2;
                 this.dragCurrent = 0;
             },
         }"
         x-effect="
             if (_lastTenantId !== $wire.detailTenantId) {
                 _lastTenantId = $wire.detailTenantId;
                 expanded = false; dragging = false; hasDragged = false; dragCurrent = 0;
                 $nextTick(() => _measure && _measure());
                 setTimeout(() => _measure && _measure(), 150);
             }
         "
         :class="{ 'sheet-dragging': dragging }"
         :style="`--sheet-y: ${translate}px`"
         class="mobile-detail-sheet fixed inset-x-0 bottom-0 z-[1210] flex h-[88dvh] flex-col overflow-hidden rounded-t-3xl border-t border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900 lg:hidden"
         role="dialog" aria-modal="false" aria-labelledby="mobile-detail-title">
        @if($dt)
            <header x-ref="sheetHeader"
                    class="mobile-detail-sheet-header shrink-0 select-none bg-white dark:bg-gray-900"
                    @touchstart="onDragStart($event)" @touchmove="onDragMove($event)"
                    @touchend="onDragEnd()" @touchcancel="onDragEnd()">
                <div class="flex justify-center pb-1 pt-2"><div class="h-1.5 w-10 rounded-full bg-gray-300 dark:bg-gray-600"></div></div>

                <div class="flex items-center gap-3 px-4 pb-3 pt-1">
                    <div class="size-11 shrink-0 overflow-hidden rounded-full bg-gray-100 shadow-sm ring-2 ring-white dark:bg-gray-800 dark:ring-gray-900">
                        @if($catSvg)
                            <div class="grid size-full place-items-center" style="background: {{ $catColor }}20; color: {{ $catColor }};">
                                <div class="size-5"><x-safe-svg :svg="$catSvg" class="size-full fill-none stroke-current stroke-2" /></div>
                            </div>
                        @elseif($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="size-full object-cover" decoding="async">
                        @else
                            <div class="grid size-full place-items-center text-xs font-bold text-gray-500 dark:text-gray-400">{{ strtoupper(substr($displayName, 0, 2)) }}</div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p id="mobile-detail-title" class="truncate text-sm font-semibold leading-tight text-gray-900 dark:text-white">{{ $displayName }}</p>
                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                            @if($isEst){{ $displayType }} · Part of {{ $dt->name }}
                            @else{{ $displayType }}@if($hours) · <span class="{{ $isOpen ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ $statusLabel }}</span>@endif
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click.stop="closeDetail" x-bind="noDrag" aria-label="Close details" class="{{ $close }}">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $xIcon }}"/></svg>
                    </button>
                </div>

                <div class="flex items-center gap-2 px-4 pb-3">
                    @if($isEst)
                        <button type="button" wire:click.stop="getDirectionsToDetail" x-bind="noDrag" class="btn-primary min-h-[44px] flex-1">
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $mapIcon }}"/></svg>
                            Directions
                        </button>
                    @else
                        <a href="{{ route('business.offerings', $dt->slug) }}" wire:navigate x-bind="noDrag" class="btn-primary min-h-[44px] flex-1">
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $eyeIcon }}"/></svg>
                            View
                        </a>
                        <button type="button" wire:click.stop="getDirectionsToDetail" x-bind="noDrag" aria-label="Directions" class="btn-secondary size-11 shrink-0 !p-0">
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $mapIcon }}"/></svg>
                        </button>
                    @endif
                </div>
            </header>

            <div :class="(expanded || dragging) ? 'opacity-100' : 'pointer-events-none opacity-0'"
                 class="custom-scrollbar min-h-0 flex-1 overflow-y-auto overscroll-contain border-t border-gray-200 bg-white transition-opacity duration-200 dark:border-gray-800 dark:bg-gray-900">
                <div class="relative h-44">
                    @if($isEst)
                        <div class="flex size-full items-center justify-center" style="background: linear-gradient(135deg, {{ $catColor }} 0%, {{ $catColor }}cc 100%);">
                            @if($catSvg)
                                <div class="size-14 text-white/95 drop-shadow-lg"><x-safe-svg :svg="$catSvg" class="size-full fill-none stroke-current stroke-2" /></div>
                            @else
                                <span class="font-display text-5xl font-black tracking-tighter text-white/95">{{ strtoupper(substr($displayName, 0, 2)) }}</span>
                            @endif
                        </div>
                    @elseif($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $dt->name }}" class="size-full object-cover" loading="lazy" decoding="async">
                    @else
                        <div class="flex size-full items-center justify-center bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700">
                            <span class="font-display text-6xl font-black tracking-tighter text-white/90">{{ strtoupper(substr($dt->name, 0, 2)) }}</span>
                        </div>
                    @endif
                    @unless($isEst)
                        <button type="button" wire:click.stop="toggleFavorite({{ $dt->id }})"
                                aria-label="{{ $isFav ? 'Remove from saved' : 'Save this spot' }}"
                                class="absolute right-3 top-3 grid size-11 place-items-center rounded-full bg-black/40 text-white backdrop-blur-md transition hover:bg-black/60 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                            <svg class="size-4" fill="{{ $isFav ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/></svg>
                        </button>
                    @endunless
                </div>

                @include('livewire.partials.explore-detail-body', ['prefix' => 'mob'])
                <div class="h-[max(1rem,env(safe-area-inset-bottom))]"></div>
            </div>
        @endif
    </div>

    <div class="pointer-events-none fixed right-[max(0.75rem,var(--safe-right))] z-[1300] flex flex-col gap-2 transition-all duration-200 sm:right-5"
         :class="$store.nav.active ? 'top-[max(3.5rem,calc(var(--safe-top)+0.5rem))] sm:top-20' : 'bottom-[max(0.75rem,var(--safe-bottom))] sm:bottom-6'"
         aria-live="polite" wire:ignore>
        <template x-for="t in toasts" :key="t.id">
            <div class="map-toast glass pointer-events-auto relative flex min-w-[220px] max-w-[calc(100vw-1.5rem)] cursor-pointer items-center gap-3 overflow-hidden rounded-2xl p-3 shadow-lg sm:min-w-[240px] sm:max-w-sm"
                 :class="{
                     'border-l-4 border-l-emerald-500': t.type === 'success',
                     'border-l-4 border-l-rose-500': t.type === 'error',
                     'border-l-4 border-l-primary-500': t.type === 'info',
                     'border-l-4 border-l-amber-500': t.type === 'warning',
                 }"
                 @mouseenter="pauseToast(t)" @mouseleave="resumeToast(t)" @click="removeToast(t.id)">
                <svg class="size-4 shrink-0" :class="{
                        'text-emerald-500': t.type === 'success', 'text-rose-500': t.type === 'error',
                        'text-primary-500': t.type === 'info', 'text-amber-500': t.type === 'warning' }"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(t.type)"/></svg>
                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200" x-text="t.message"></span>
                <button type="button" @click.stop="removeToast(t.id)" aria-label="Dismiss"
                        class="grid size-11 shrink-0 place-items-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $xIcon }}"/></svg>
                </button>
                <div class="absolute bottom-0 left-0 h-0.5 bg-current transition-all" :class="{
                        'text-emerald-500': t.type === 'success', 'text-rose-500': t.type === 'error',
                        'text-primary-500': t.type === 'info', 'text-amber-500': t.type === 'warning' }"
                     :style="'width:' + ((t.remaining / t.duration) * 100) + '%'"></div>
            </div>
        </template>
    </div>

    <div x-cloak wire:ignore data-help-modal :class="helpOpen ? 'flex' : 'hidden'"
         class="fixed inset-0 z-[1500] items-end justify-center bg-black/60 backdrop-blur-sm sm:items-center sm:p-4"
         @click.self="helpOpen = false" role="dialog" aria-modal="true" aria-labelledby="help-modal-title">
        <div class="map-help-panel glass max-h-[90dvh] w-full max-w-sm overflow-hidden rounded-t-3xl shadow-2xl sm:rounded-3xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 dark:border-gray-800">
                <h2 id="help-modal-title" class="font-display text-xl font-semibold tracking-tight text-gray-900 dark:text-white">Legend &amp; shortcuts</h2>
                <button type="button" @click.stop="helpOpen = false" aria-label="Close help" class="{{ $close }} text-gray-500">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $xIcon }}"/></svg>
                </button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                <p class="{{ $eyebrow }} mb-3 text-primary-600 dark:text-primary-400">Legend</p>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li class="flex items-center gap-2"><span class="size-3 rounded-full bg-blue-600 ring-2 ring-white dark:ring-gray-900"></span>Your location</li>
                    <li class="flex items-center gap-2"><span class="size-3 rounded-full bg-orange-500 ring-2 ring-white dark:ring-gray-900"></span>Tourist spot</li>
                    <li class="flex items-center gap-2"><span class="size-2 rounded-full bg-orange-500 opacity-70"></span>Establishment</li>
                    <li class="flex items-center gap-2"><span class="size-3 rounded-full bg-purple-500 ring-2 ring-white dark:ring-gray-900"></span>Event</li>
                    <li class="flex items-center gap-2"><span class="h-1 w-5 rounded bg-blue-600"></span>Active route</li>
                </ul>

                <p class="{{ $eyebrow }} mb-3 mt-6 text-primary-600 dark:text-primary-400">Tips</p>
                <ul class="space-y-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                    <li>The <strong>+N</strong> badge counts establishments inside a spot. Turn on Establishments to show them all.</li>
                    <li>Tap a pin to open details. Swipe the sheet up for more, down to close.</li>
                    <li>Tap <strong>Navigate</strong> for live guidance. Remaining distance follows the road.</li>
                    <li>Wrong location on desktop? Add <code class="rounded bg-gray-100 px-1 py-0.5 font-mono dark:bg-gray-800">?lat=10.90&amp;lng=123.07</code> to the URL.</li>
                </ul>

                <p class="{{ $eyebrow }} mb-3 mt-6 text-primary-600 dark:text-primary-400">Shortcuts</p>
                <div class="space-y-2">
                    @foreach(['/' => 'Focus search', 'L' => 'My location', 'F' => 'Follow mode', 'S' => 'Satellite', 'R' => 'Reset view', '?' => 'This help', 'Esc' => 'Close panels'] as $k => $l)
                        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                            <span>{{ $l }}</span>
                            <kbd class="rounded-lg border border-gray-300 bg-gray-50 px-2 py-0.5 font-mono text-xs font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $k }}</kbd>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>