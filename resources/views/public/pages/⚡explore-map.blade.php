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

    public ?float $userLat = null;
    public ?float $userLng = null;
    public bool   $followMode  = false;
    public bool   $satellite   = false;
    public bool   $sidebarOpen = true;

    public ?int    $highlightedId        = null;
    public array   $routeCoords          = [];
    public ?string $routeDestinationName = null;
    public ?int    $routeTenantId        = null;
    public string  $routeId              = 'tourist-route';
    public string  $directionsProfile    = 'driving';

    public ?int   $pendingMarkerId = null;
    public bool   $autoDirections = false;
    public array  $favorites = [];

    public ?float $currentLat  = null;
    public ?float $currentLng  = null;
    public ?int   $currentZoom = null;

    public string  $filtersHash  = '';
    public int     $routeVersion = 0;

    public int $userLocationVersion = 0;
    public int $mapRefreshVersion   = 0;
    public int $themeVersion        = 0;

    public ?int $pendingDirectionsTenantId  = null;
    public int  $pendingDirectionsCoordIndex = 0;

    public ?int $detailTenantId   = null;
    public int  $detailCoordIndex = 0;

    private const CITY_CENTER = [123.07391289720677, 10.900736693923502];

    public function mount(): void
    {
        $this->favorites   = session($this->favoritesKey(), []);
        $this->sidebarOpen = request()->cookie('map_sidebar_open') !== '0';
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
        if (!$this->detailTenantId) {
            return null;
        }

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
    public function detailUpcomingEvents()
    {
        if (!$this->detailTenantId) {
            return collect();
        }

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
        if (!$this->showEvents) {
            return collect();
        }

        return Event::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('coordinates')
            ->where('is_active', true)
            ->where('start_date', '>=', now()->subDay())
            ->with('tenant:id,name,slug')
            ->get(['id', 'name', 'barangay', 'type', 'start_date', 'end_date', 'coordinates', 'featured', 'tenant_id'])
            ->map(function ($e) {
                $c = is_array($e->coordinates) ? $e->coordinates : json_decode($e->coordinates, true);

                if (!is_array($c) || !isset($c['lat'], $c['lng'])) {
                    return null;
                }

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
            || $this->hasOfferings || $this->favoritesOnly || $this->recommendedOnly || $this->showEvents;
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
        $this->dispatch('map:fly-to', center: [(float) $c['lng'], (float) $c['lat']], zoom: 16);
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
        $c = $t->coordinates[$idx];

        $this->routeCoords = [
            'start' => [(float) $this->userLng, (float) $this->userLat],
            'end'   => [(float) $c['lng'], (float) $c['lat']],
        ];
        $this->routeDestinationName = $label;
        $this->routeTenantId        = $t->id;
        $this->highlightedId        = $t->id;
        $this->routeVersion++;

        $this->dispatch('map:fit-bounds', [
            'bounds' => [
                [(float) $this->userLng, (float) $this->userLat],
                [(float) $c['lng'], (float) $c['lat']],
            ],
            'padding' => 60,
        ]);
    }

    public function setDirectionsProfile(string $profile): void
    {
        if (!in_array($profile, ['driving', 'walking', 'cycling'], true)) return;

        $this->directionsProfile = $profile;
        $this->routeVersion++;
    }

    public function clearRoute(): void
    {
        $this->routeCoords          = [];
        $this->routeDestinationName = null;
        $this->routeTenantId        = null;
        $this->routeVersion++;
        $this->notify('Route cleared.', 'info');
    }

    public function setUserLocation($lat, $lng): void
    {
        $oldLat = $this->userLat;

        $this->userLat     = $this->currentLat = round((float) $lat, 6);
        $this->userLng     = $this->currentLng = round((float) $lng, 6);
        $this->currentZoom = 15;
        $this->userLocationVersion++;

        if ($oldLat === null || $this->distance($oldLat, $this->userLng) > 0.1) {
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

    public function locationFailed(string $reason = 'unavailable'): void
    {
        $this->autoDirections              = false;
        $this->pendingDirectionsTenantId   = null;
        $this->pendingDirectionsCoordIndex = 0;

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

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'openNow', 'hasOfferings', 'favoritesOnly', 'recommendedOnly', 'showEvents']);
        $this->filtersHash = $this->hashFilters();
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

    public function updateViewport(?float $lat, ?float $lng, ?int $zoom): void
    {
        if ($lat)  $this->currentLat  = round($lat, 6);
        if ($lng)  $this->currentLng  = round($lng, 6);
        if ($zoom) $this->currentZoom = $zoom;
    }

    public function handleThemeChange(): void
    {
        $this->themeVersion++;
        $this->mapRefreshVersion++;
        $this->routeVersion++;
        $this->dispatch('map:resize');
    }

    public function updatedSearch():          void { $this->filtersHash = $this->hashFilters(); }
    public function updatedCategoryFilter():  void { $this->filtersHash = $this->hashFilters(); }
    public function updatedOpenNow():         void { $this->filtersHash = $this->hashFilters(); }
    public function updatedHasOfferings():    void { $this->filtersHash = $this->hashFilters(); }
    public function updatedFavoritesOnly():   void { $this->filtersHash = $this->hashFilters(); }
    public function updatedRecommendedOnly(): void { $this->filtersHash = $this->hashFilters(); }
    public function updatedShowEvents():      void { $this->filtersHash = $this->hashFilters(); }

    public function updatedSortBy(string $v): void
    {
        if ($v === 'distance' && !$this->userLat) {
            $this->dispatch('request-location-for-distance');
        }
    }

    #[On('map:loaded')]
    public function onMapReady(): void
    {
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

{{-- ✅ Single root — dvh keeps mobile browsers happy with their hide-on-scroll address bar --}}
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
     x-on:map:zoom-changed.window="debouncedViewport(null, null, $event.detail.zoom)"
     x-on:keydown.window="handleKey($event)">

    {{-- Mobile sidebar backdrop --}}
    <div x-show="mobileOpen"
         x-cloak
         x-transition:enter="transition-opacity duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[1090] bg-black/60 backdrop-blur-sm lg:hidden"
         @click="mobileOpen = false"
         aria-hidden="true"></div>

    {{-- Sidebar — mobile: off-canvas overlay; desktop: static, persisted width --}}
    <aside data-map-aside
           class="fixed bottom-0 top-16 md:top-20 lg:static lg:top-0 lg:bottom-auto
                  left-0 z-[1100]
                  w-[85vw] max-w-[340px] lg:w-[340px] lg:max-w-none lg:z-auto flex-shrink-0
                  transition-[transform,width] duration-300 ease-out
                  bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800
                  will-change-transform"
           :class="{
               'translate-x-0': mobileOpen,
               '-translate-x-full': !mobileOpen,
               'lg:translate-x-0': sidebarOpen,
               'lg:-translate-x-full': !sidebarOpen,
               'lg:w-0 lg:overflow-hidden lg:border-r-0': !sidebarOpen,
           }">
        @include('livewire.partials.explore-sidebar')
    </aside>

    {{-- Map area --}}
    <div class="relative flex-1 min-w-0 overflow-hidden bg-gray-100 dark:bg-gray-900">

        {{-- Map loading overlay --}}
        <div x-show="mapLoading" x-cloak
             class="absolute inset-0 z-[900] flex items-center justify-center bg-gray-100 dark:bg-gray-900">
            <div class="text-center px-6">
                <div class="mx-auto mb-3 h-10 w-10 animate-spin rounded-full border-2 border-primary-600 border-t-transparent motion-reduce:animate-none"></div>
                <p class="text-sm text-gray-500 dark:text-gray-400 italic">Loading map…</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">This can take a few seconds on slow connections.</p>
            </div>
        </div>

        {{-- Stuck state — with diagnostics --}}
        <div x-show="mapStuck && !mapLoading" x-cloak
             class="absolute inset-0 z-[900] flex items-center justify-center bg-gray-100 dark:bg-gray-900 overflow-y-auto">
            <div class="text-center max-w-md px-4 sm:px-6 py-8">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/10">
                    <svg class="h-7 w-7 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Map couldn't load</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Something prevented MapLibre from initialising. Details below.
                </p>

                <div class="mt-5 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 p-4 text-left">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">
                        Diagnostic
                    </p>
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
                                   text-white px-4 py-2.5 text-sm font-bold transition active:scale-95
                                   focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Retry
                    </button>
                    <button type="button" onclick="window.location.reload()"
                            class="inline-flex items-center justify-center rounded-lg
                                   border border-gray-300 dark:border-gray-700
                                   bg-white dark:bg-gray-900
                                   px-4 py-2.5 text-sm font-bold text-gray-700 dark:text-gray-200
                                   hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400
                                   transition active:scale-95
                                   focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                        Reload Page
                    </button>
                </div>
            </div>
        </div>

        {{-- Map canvas --}}
        <div wire:key="map-{{ $satellite ? 'sat' : 'std' }}-{{ $filtersHash }}-{{ $routeVersion }}-{{ $showEvents ? 'ev' : '' }}-{{ $mapRefreshVersion }}-{{ $themeVersion }}"
             class="absolute inset-0">
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
                @map:load="
                    $event.detail.map?.setRenderWorldCopies(false);
                    $event.detail.map?.setMaxBounds([[122.0, 9.5], [124.0, 11.8]]);
                    $event.detail.map?.setMinZoom(10);
                "
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
                        :key="'user-' . $userLocationVersion"
                        wire:key="user-marker-{{ $userLocationVersion }}"
                        :lat="$userLat"
                        :lng="$userLng"
                        color="#C8A96E"
                        id="user-location"
                        anchor="center">
                        <x-marker-content>
                            <div class="relative flex h-13 w-13 items-center justify-center">
                                @if($followMode)
                                    <div class="absolute inset-0 rounded-full bg-primary-500/20 animate-ping motion-reduce:animate-none"></div>
                                @endif
                                <div class="absolute h-11 w-11 rounded-full bg-primary-500/10 border border-primary-500/30"></div>
                                <div class="h-5 w-5 rounded-full bg-primary-500 border-2 border-white shadow-lg relative z-10"></div>
                            </div>
                        </x-marker-content>
                        <x-marker-popup>
                            <div class="rounded-xl bg-white p-4 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 min-w-[180px]">
                                <p class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-2">
                                    {{ !empty($routeCoords) ? 'Route Origin' : 'Your Location' }}
                                </p>
                                <button type="button" wire:click="shareLocation"
                                        class="w-full rounded-lg bg-primary-50 dark:bg-primary-500/10 px-3 py-2
                                               text-xs font-semibold text-primary-600 dark:text-primary-400
                                               hover:bg-primary-100 dark:hover:bg-primary-500/20 transition">
                                    Share Location
                                </button>
                            </div>
                        </x-marker-popup>
                    </x-map-marker>
                @endif

                @foreach($this->tenants as $tenant)
                    @php
                        $colors = ['#f97316','#a855f7','#3b82f6','#14b8a6','#eab308','#10b981','#8b5cf6','#f43f5e'];
                        $tc     = $colors[$loop->index % count($colors)];
                        $isRoute= $routeTenantId === $tenant->id && !empty($routeCoords);
                        $isHL   = $highlightedId === $tenant->id;
                        $logo   = $tenant->logo ? asset('storage/' . $tenant->logo) : null;
                    @endphp
                    @foreach($tenant->coordinates as $ci => $coord)
                        @php
                            $isParent  = $ci === 0 || ($coord['type'] ?? '') === 'parent';
                            $coordType = $coord['type'] ?? null;
                            $mcMatch   = !$isParent && $coordType
                                ? collect($this->markerCategories)->firstWhere('key', $coordType)
                                : null;
                            $activeColor = $mcMatch ? ($mcMatch['color'] ?? $tc) : $tc;
                        @endphp
                        <x-map-marker
                            :key="'t-' . $tenant->id . '-' . $ci"
                            wire:key="m-{{ $tenant->id }}-{{ $ci }}"
                            :lat="$coord['lat']"
                            :lng="$coord['lng']"
                            :color="$activeColor"
                            id="tenant-{{ $tenant->id }}-{{ $ci }}"
                            anchor="bottom">
                            <x-marker-content>
                                <div class="flex flex-col items-center cursor-pointer group">
                                    @if($isParent)
                                        <div class="flex h-11 w-11 items-center justify-center rounded-full border-2
                                                    bg-white shadow-lg transition-transform group-hover:scale-110 dark:bg-gray-900
                                                    {{ ($isRoute || $isHL) ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : '' }}"
                                             style="border-color: {{ $tc }};">
                                            @if($logo)
                                                <img src="{{ $logo }}" alt="{{ $tenant->name }}"
                                                     class="h-full w-full rounded-full object-cover" loading="lazy">
                                            @else
                                                <span class="text-xs font-bold text-gray-900 dark:text-white">
                                                    {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                                </span>
                                            @endif
                                        </div>
                                    @elseif($mcMatch)
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full border-2
                                                    bg-white shadow-md transition-transform group-hover:scale-110 dark:bg-gray-900"
                                             style="border-color: {{ $activeColor }};">
                                            @if($mcMatch['icon_svg'] ?? null)
                                                <div class="h-4 w-4 text-gray-800 dark:text-white">
                                                    {!! str_replace('<svg ', '<svg class="h-full w-full fill-none stroke-current stroke-2" ', $mcMatch['icon_svg']) !!}
                                                </div>
                                            @else
                                                <span class="text-xs font-bold text-gray-800 dark:text-white">
                                                    {{ strtoupper(substr($coordType, 0, 1)) }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="h-3.5 w-3.5 rounded-full border-2 border-white shadow
                                                    transition-transform group-hover:scale-125 dark:border-gray-900"
                                             style="background: {{ $tc }};"></div>
                                    @endif
                                    <svg class="mt-[-1px] h-1.5 w-2" style="color: {{ $activeColor }};"
                                         viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                        <path d="M0 0 L12 0 L6 8 Z"/>
                                    </svg>
                                </div>
                            </x-marker-content>
                        </x-map-marker>
                    @endforeach
                @endforeach

                @if($showEvents)
                    @foreach($this->eventMarkers as $ev)
                        <x-map-marker
                            :key="'ev-' . $ev['id']"
                            wire:key="ev-m-{{ $ev['id'] }}"
                            :lat="$ev['lat']"
                            :lng="$ev['lng']"
                            color="#A78BFA"
                            id="event-{{ $ev['id'] }}"
                            anchor="bottom">
                            <x-marker-content>
                                <div class="flex flex-col items-center cursor-pointer group">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full
                                                bg-gradient-to-tr from-purple-600 to-pink-500
                                                border-2 border-white shadow-lg
                                                transition-transform group-hover:scale-110 dark:border-gray-900">
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                    </div>
                                    <svg class="mt-[-1px] h-1.5 w-2" style="color: #A78BFA;"
                                         viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                        <path d="M0 0 L12 0 L6 8 Z"/>
                                    </svg>
                                </div>
                            </x-marker-content>
                            <x-marker-popup>
                                <div class="w-[220px] rounded-xl bg-white p-4 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                    <p class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Event</p>
                                    <h3 class="mt-1 text-lg font-display font-semibold text-gray-900 dark:text-white">{{ $ev['name'] }}</h3>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $ev['start_date']->format('M d, Y') }}
                                    </p>
                                    <a href="{{ route('events') }}"
                                       wire:navigate
                                       class="mt-3 block rounded-lg bg-purple-600 px-3 py-2 text-center text-xs font-bold text-white transition hover:bg-purple-700">
                                        View Event
                                    </a>
                                </div>
                            </x-marker-popup>
                        </x-map-marker>
                    @endforeach
                @endif

                @if(!empty($routeCoords))
                    <x-map-route
                        wire:key="route-{{ md5(serialize($routeCoords)) }}-{{ $directionsProfile }}-{{ $routeVersion }}"
                        id="{{ $routeId }}"
                        :coordinates="[$routeCoords['start'], $routeCoords['end']]"
                        :fetch-directions="true"
                        :alternatives="true"
                        :directions-profile="$directionsProfile"
                        color="#C8A96E"
                        :width="4"
                        alternative-color="#60A5FA"
                    />
                    <x-map-route-list
                        route-id="{{ $routeId }}"
                        map-id="tourist-map"
                        title="Routes"
                        width="w-56"
                        position="bottom-left"
                        container-class="z-[850]"
                    />
                @endif
            </x-map>
        </div>

        {{-- Toolbar — desktop: vertical stack on left; mobile: single compact row on left --}}
        <div class="absolute left-2 top-2 sm:left-3 sm:top-3 z-[1000] flex flex-col gap-1.5 sm:gap-2">

            {{-- Primary actions --}}
            <div class="flex flex-col divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200
                        bg-white shadow-lg dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">

                {{-- Sidebar toggle — mobile only --}}
                <button type="button"
                        data-tip="Open filters"
                        @click="mobileOpen = true"
                        aria-label="Open filters"
                        class="flex lg:hidden h-9 w-9 sm:h-10 sm:w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h7"/>
                    </svg>
                </button>

                {{-- Sidebar toggle — desktop only --}}
                <button type="button"
                        data-tip="Toggle sidebar"
                        @click="sidebarOpen = !sidebarOpen"
                        :aria-pressed="sidebarOpen.toString()"
                        aria-label="Toggle sidebar"
                        class="hidden lg:flex h-10 w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        :class="sidebarOpen ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                    </svg>
                </button>

                {{-- Locate --}}
                <button type="button" data-tip="My location (L)" @click="locate()"
                        aria-label="My location"
                        class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        :class="locating ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg x-show="!locating" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21c-4.5-4.5-7.5-8.24-7.5-11.5A7.5 7.5 0 0112 2a7.5 7.5 0 017.5 7.5c0 3.26-3 7-7.5 11.5z"/>
                        <circle cx="12" cy="9.5" r="2.5" stroke-width="2" fill="none"/>
                    </svg>
                    <svg x-show="locating" x-cloak class="h-5 w-5 animate-spin motion-reduce:animate-none"
                         fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>

                {{-- Follow --}}
                <button type="button" data-tip="Follow mode (F)"
                        @click="followMode ? stopFollow() : startFollow()"
                        aria-label="Follow mode"
                        class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        :class="followMode ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L21 21 12 17 3 21Z"/>
                    </svg>
                </button>
            </div>

            {{-- View actions --}}
            <div class="flex flex-col divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200
                        bg-white shadow-lg dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
                <button type="button" data-tip="Recenter (R)" wire:click="resetView"
                        aria-label="Recenter"
                        class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="8"/>
                        <line x1="12" y1="2" x2="12" y2="4"/>
                        <line x1="12" y1="20" x2="12" y2="22"/>
                        <line x1="2" y1="12" x2="4" y2="12"/>
                        <line x1="20" y1="12" x2="22" y2="12"/>
                    </svg>
                </button>
                <button type="button" data-tip="Satellite (S)" wire:click="toggleSatellite"
                        aria-label="Toggle satellite"
                        class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white
                               {{ $satellite ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : '' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 11a8 8 0 00-14 0M4 13a8 8 0 0014 0M12 12v3m0 0a9 9 0 01-9 9m9-9a9 9 0 019 9"/>
                    </svg>
                </button>
            </div>

            {{-- Utility actions — hidden on very small screens to save space --}}
            <div class="hidden sm:flex flex-col divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200
                        bg-white shadow-lg dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
                <button type="button" data-tip="Share location" wire:click="shareLocation"
                        aria-label="Share location"
                        class="flex h-10 w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                    </svg>
                </button>
                <button type="button" data-tip="Help & shortcuts (?)" @click="helpOpen = true"
                        aria-label="Help"
                        class="flex h-10 w-10 items-center justify-center text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900
                               focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95
                               dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/>
                    </svg>
                </button>
            </div>

            @if(!empty($routeCoords))
                <div class="flex flex-col overflow-hidden rounded-lg border border-red-200 bg-white shadow-lg dark:border-red-500/30 dark:bg-gray-900">
                    <button type="button" data-tip="Clear route" wire:click="clearRoute"
                            aria-label="Clear route"
                            class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center text-red-500
                                   transition hover:bg-red-50 hover:text-red-700
                                   focus:outline-none focus:ring-2 focus:ring-red-500 active:scale-95
                                   dark:hover:bg-red-500/10">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif
        </div>

        {{-- Route info panel — stacks on mobile, single row on desktop --}}
        @if(!empty($routeCoords))
            <div class="absolute left-1/2 bottom-3 sm:bottom-6 z-[900] w-[calc(100%-1rem)] sm:w-auto
                        max-w-md sm:max-w-none -translate-x-1/2
                        flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-6
                        rounded-xl bg-white px-4 sm:px-5 py-3 shadow-xl
                        dark:bg-gray-900 border border-gray-200 dark:border-gray-800">

                <div class="flex items-center justify-between gap-3 sm:block">
                    <div class="min-w-0">
                        <p class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Navigating to</p>
                        <p class="font-display text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate">
                            {{ $routeDestinationName }}
                        </p>
                    </div>
                    {{-- Mobile-only close button lives in the row with the destination --}}
                    <button type="button" wire:click="clearRoute"
                            aria-label="Clear route"
                            class="sm:hidden flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-500
                                   transition hover:border-red-500 hover:text-red-500
                                   dark:border-gray-700 dark:text-gray-400 dark:hover:border-red-500 dark:hover:text-red-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div>
                    <p class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Mode</p>
                    <div class="flex gap-1">
                        @foreach(['driving' => 'Drive', 'walking' => 'Walk', 'cycling' => 'Cycle'] as $p => $l)
                            <button type="button" wire:click="setDirectionsProfile('{{ $p }}')"
                                    class="flex-1 sm:flex-none rounded-md px-2.5 py-1 text-[11px] sm:text-xs font-semibold uppercase transition active:scale-95
                                           focus:outline-none focus:ring-2 focus:ring-primary-500/50
                                           {{ $directionsProfile === $p
                                              ? 'bg-primary-600 text-white'
                                              : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                                {{ $l }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Desktop-only close button --}}
                <button type="button" wire:click="clearRoute"
                        aria-label="Clear route"
                        class="hidden sm:flex ml-2 h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-500
                               transition hover:border-red-500 hover:text-red-500
                               dark:border-gray-700 dark:text-gray-400 dark:hover:border-red-500 dark:hover:text-red-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif
    </div>

    {{-- ═══════════════ DETAIL PANEL ═══════════════ --}}
    <div x-show="$wire.detailTenantId !== null"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 lg:translate-x-8 lg:translate-y-0"
         x-transition:enter-end="opacity-100 translate-x-0 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-x-0 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-8 lg:translate-x-8 lg:translate-y-0"
         class="fixed inset-x-0 bottom-0 z-[1200] max-h-[85dvh] flex flex-col
                lg:absolute lg:inset-y-0 lg:left-auto lg:right-0 lg:bottom-auto lg:top-0
                lg:w-[420px] lg:max-h-full
                bg-white dark:bg-gray-900
                border-t lg:border-t-0 lg:border-l border-gray-200 dark:border-gray-800
                rounded-t-2xl lg:rounded-none shadow-2xl overflow-hidden"
         role="dialog"
         aria-modal="false"
         aria-labelledby="detail-panel-title">

        {{-- Drag handle — mobile affordance to hint the sheet is dismissable --}}
        <div class="lg:hidden absolute top-2 left-1/2 -translate-x-1/2 z-10 h-1 w-10 rounded-full bg-white/70 backdrop-blur-sm pointer-events-none"
             aria-hidden="true"></div>

        @if($this->detailTenant)
            @php
                $dt    = $this->detailTenant;
                $hours = $dt->settings->first()?->value['opening_hours'] ?? null;
                $bio   = $dt->settings->first()?->value ?? [];
                $desc  = $bio['description'] ?? null;
                $site  = $bio['website'] ?? null;
                $fb    = $bio['social_links']['facebook'] ?? null;
                $ig    = $bio['social_links']['instagram'] ?? null;
                $coord = $dt->coordinates[$detailCoordIndex] ?? $dt->coordinates[0] ?? null;
                $dist  = $coord ? $this->distance($coord['lat'], $coord['lng']) : null;
                $statusLabel = $this->openStatusLabel($dt);
                $hasHours = (bool) $hours;
            @endphp

            <div class="relative h-40 sm:h-52 shrink-0">
                @if($dt->logo)
                    <img src="{{ asset('storage/' . $dt->logo) }}"
                         alt="{{ $dt->name }}"
                         class="h-full w-full object-cover">
                @else
                    <div class="h-full w-full bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 flex items-center justify-center">
                        <span class="font-display text-6xl font-black text-white/90 tracking-tighter">
                            {{ strtoupper(substr($dt->name, 0, 2)) }}
                        </span>
                    </div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"></div>

                <button type="button" wire:click="closeDetail"
                        aria-label="Close details"
                        class="absolute right-3 top-3 flex h-9 w-9 items-center justify-center
                               rounded-full bg-black/40 backdrop-blur-md text-white
                               hover:bg-black/60 transition active:scale-95
                               focus:outline-none focus:ring-2 focus:ring-white/60">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                @php $isFav = in_array($dt->id, $favorites, true); @endphp
                <button type="button" wire:click="toggleFavorite({{ $dt->id }})"
                        aria-label="{{ $isFav ? 'Remove from saved' : 'Save this spot' }}"
                        class="absolute right-14 top-3 flex h-9 w-9 items-center justify-center
                               rounded-full bg-black/40 backdrop-blur-md text-white
                               hover:bg-black/60 transition active:scale-95
                               focus:outline-none focus:ring-2 focus:ring-white/60">
                    <svg class="h-4 w-4" fill="{{ $isFav ? 'currentColor' : 'none' }}"
                         stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z"/>
                    </svg>
                </button>

                <div class="absolute bottom-4 left-4 right-4">
                    <div class="flex flex-wrap items-center gap-1.5 mb-2">
                        <span class="rounded-full bg-white/20 backdrop-blur-md px-2.5 py-0.5
                                     text-[10px] font-bold uppercase tracking-wider text-white">
                            {{ $dt->typeOfTenant?->type ?? 'Business' }}
                        </span>

                        @if($dt->is_recommended)
                            <span class="rounded-full bg-amber-400 px-2.5 py-0.5
                                         text-[10px] font-bold uppercase tracking-wider text-amber-900
                                         flex items-center gap-1">
                                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
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
                    </div>
                    <h2 id="detail-panel-title" class="font-display text-xl sm:text-2xl font-bold text-white leading-tight line-clamp-2">
                        {{ $dt->name }}
                    </h2>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-px bg-gray-200 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-800 shrink-0">
                <div class="bg-white dark:bg-gray-900 py-2.5 sm:py-3 text-center">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Distance</p>
                    <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                        {{ $dist !== null && $dist < PHP_FLOAT_MAX ? $this->formatDistance($dist) : '—' }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-900 py-2.5 sm:py-3 text-center">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Places</p>
                    <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                        {{ $dt->properties->count() }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-900 py-2.5 sm:py-3 text-center">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Services</p>
                    <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                        {{ $dt->services->count() }}
                    </p>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar overscroll-contain">

                @if($desc)
                    <section class="px-4 sm:px-5 py-4 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">About</h3>
                        <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $desc }}</p>
                    </section>
                @endif

                @if($dt->contact_number || $dt->email || $dt->address || $hours)
                    <section class="px-4 sm:px-5 py-4 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Contact &amp; Hours</h3>
                        <dl class="space-y-3 text-sm">

                            @if($dt->address || $dt->barangay)
                                <div class="flex gap-3">
                                    <div class="mt-0.5 shrink-0 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="sr-only">Address</dt>
                                        <dd class="text-gray-800 dark:text-gray-200 leading-snug">
                                            {{ $dt->address }}
                                            @if($dt->barangay && !str_contains($dt->address ?? '', $dt->barangay))
                                                <span class="block text-gray-500 dark:text-gray-400 text-xs mt-0.5">{{ $dt->barangay }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                </div>
                            @endif

                            @if($dt->contact_number)
                                <div class="flex gap-3">
                                    <div class="mt-0.5 shrink-0 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="sr-only">Phone</dt>
                                        <dd>
                                            <a href="tel:{{ $dt->contact_number }}"
                                               class="text-primary-600 dark:text-primary-400 font-medium hover:underline rounded
                                                      focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                                                {{ $dt->contact_number }}
                                            </a>
                                        </dd>
                                    </div>
                                </div>
                            @endif

                            @if($dt->email)
                                <div class="flex gap-3">
                                    <div class="mt-0.5 shrink-0 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="sr-only">Email</dt>
                                        <dd>
                                            <a href="mailto:{{ $dt->email }}"
                                               class="text-primary-600 dark:text-primary-400 font-medium hover:underline break-all rounded
                                                      focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                                                {{ $dt->email }}
                                            </a>
                                        </dd>
                                    </div>
                                </div>
                            @endif

                            @if($hours && !empty($hours['opening']) && !empty($hours['closing']))
                                <div class="flex gap-3">
                                    <div class="mt-0.5 shrink-0 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
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
                                <div class="flex gap-3">
                                    <div class="mt-0.5 shrink-0 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex flex-wrap gap-2">
                                        <dt class="sr-only">Links</dt>
                                        @if($site)
                                            <a href="{{ $site }}" target="_blank" rel="noopener noreferrer"
                                               class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline rounded
                                                      focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                                                Website
                                            </a>
                                        @endif
                                        @if($fb)
                                            <a href="{{ $fb }}" target="_blank" rel="noopener noreferrer"
                                               class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline rounded
                                                      focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                                                Facebook
                                            </a>
                                        @endif
                                        @if($ig)
                                            <a href="{{ $ig }}" target="_blank" rel="noopener noreferrer"
                                               class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline rounded
                                                      focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                                                Instagram
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </dl>
                    </section>
                @endif

                @if($dt->properties->isNotEmpty())
                    <section class="px-4 sm:px-5 py-4 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                Places Available
                            </h3>
                            <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 tabular-nums">
                                {{ $dt->properties->count() }}
                            </span>
                        </div>
                        <div class="space-y-2">
                            @foreach($dt->properties as $prop)
                                @php $img = $prop->images->first(); @endphp
                                <div wire:key="detail-prop-{{ $prop->id }}"
                                     class="flex gap-3 p-2 rounded-lg
                                            hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <div class="h-14 w-14 shrink-0 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800">
                                        @if($img)
                                            <img src="{{ asset('storage/' . $img->image_path) }}"
                                                 alt="{{ $prop->name }}"
                                                 class="h-full w-full object-cover"
                                                 loading="lazy">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-gray-400">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $prop->name }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ $prop->propertyType?->name ?? 'Place' }}
                                            @if($prop->capacity)
                                                · Fits {{ $prop->capacity }}
                                            @endif
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
                    <section class="px-4 sm:px-5 py-4 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                                Services
                            </h3>
                            <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 tabular-nums">
                                {{ $dt->services->count() }}
                            </span>
                        </div>
                        <div class="space-y-1.5">
                            @foreach($dt->services as $svc)
                                <div wire:key="detail-svc-{{ $svc->id }}"
                                     class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg
                                            bg-gray-50 dark:bg-gray-800/50">
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
                    <section class="px-4 sm:px-5 py-4 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">
                            Upcoming Events
                        </h3>
                        <div class="space-y-2">
                            @foreach($events as $ev)
                                <div wire:key="detail-event-{{ $ev->id }}"
                                     class="flex items-start gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <div class="shrink-0 rounded-lg bg-gradient-to-br from-purple-100 to-purple-50 dark:from-purple-900/30 dark:to-purple-800/20
                                                border border-purple-200/60 dark:border-purple-500/20 px-2 py-1.5 text-center min-w-[52px]">
                                        <p class="text-[9px] font-bold uppercase tracking-wider text-purple-700 dark:text-purple-300 leading-none">
                                            {{ $ev->start_date->format('M') }}
                                        </p>
                                        <p class="text-base font-extrabold text-purple-700 dark:text-purple-200 leading-tight tabular-nums">
                                            {{ $ev->start_date->format('d') }}
                                        </p>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $ev->name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ $ev->type }} · {{ $ev->start_date->format('h:i A') }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="h-4"></div>
            </div>

            <div class="shrink-0 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3 flex gap-2">
                <a href="{{ route('business.offerings', $dt->slug) }}"
                   wire:navigate
                   class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl
                          bg-primary-600 hover:bg-primary-700 text-white
                          px-3 sm:px-4 py-3 text-sm font-bold transition active:scale-95
                          focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span class="hidden xs:inline">View Business</span>
                    <span class="xs:hidden">View</span>
                </a>
                <button type="button" wire:click="getDirectionsToDetail"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl
                               border border-gray-300 dark:border-gray-700
                               bg-white dark:bg-gray-900
                               px-3 py-3 text-sm font-bold text-gray-700 dark:text-gray-200
                               hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400
                               transition active:scale-95
                               focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    <span class="hidden sm:inline">Directions</span>
                </button>
                <button type="button" wire:click="shareDetail"
                        aria-label="Share this spot"
                        class="inline-flex items-center justify-center rounded-xl
                               border border-gray-300 dark:border-gray-700
                               bg-white dark:bg-gray-900
                               px-3 py-3 text-gray-500 dark:text-gray-400
                               hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400
                               transition active:scale-95
                               focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                    </svg>
                </button>
            </div>
        @endif
    </div>

    {{-- ═══════════════ TOASTS ═══════════════ --}}
    <div class="pointer-events-none fixed bottom-3 right-3 sm:bottom-6 sm:right-5 z-[1300] flex flex-col gap-2"
         aria-live="polite"
         wire:ignore>
        <template x-for="t in toasts" :key="t.id">
            <div class="relative pointer-events-auto flex min-w-[220px] sm:min-w-[240px] max-w-[calc(100vw-1.5rem)] sm:max-w-sm items-center gap-3 rounded-lg border
                        bg-white p-3 shadow-xl dark:bg-gray-900 cursor-pointer"
                 :class="{
                     'border-l-4 border-l-emerald-500': t.type === 'success',
                     'border-l-4 border-l-red-500':     t.type === 'error',
                     'border-l-4 border-l-blue-500':    t.type === 'info',
                     'border-l-4 border-l-amber-500':   t.type === 'warning',
                 }"
                 x-transition:enter="transition duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0"
                 @mouseenter="pauseToast(t)"
                 @mouseleave="resumeToast(t)"
                 @click="removeToast(t.id)">
                <svg class="h-4 w-4 flex-shrink-0" :class="{
                        'text-emerald-500': t.type === 'success',
                        'text-red-500':     t.type === 'error',
                        'text-blue-500':    t.type === 'info',
                        'text-amber-500':   t.type === 'warning',
                     }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <template x-if="t.type === 'success'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></template>
                    <template x-if="t.type === 'error'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></template>
                    <template x-if="t.type === 'info'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></template>
                    <template x-if="t.type === 'warning'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></template>
                </svg>
                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200" x-text="t.message"></span>
                <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        @click.stop="removeToast(t.id)" aria-label="Dismiss">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <div class="absolute bottom-0 left-0 h-0.5 bg-current transition-all" :class="{
                        'text-emerald-500': t.type === 'success',
                        'text-red-500':     t.type === 'error',
                        'text-blue-500':    t.type === 'info',
                        'text-amber-500':   t.type === 'warning',
                     }" :style="'width:' + ((t.remaining / t.duration) * 100) + '%'"></div>
            </div>
        </template>
    </div>

    {{-- ═══════════════ HELP MODAL ═══════════════ --}}
    <div x-show="helpOpen" x-cloak
         wire:ignore
         data-help-modal
         class="fixed inset-0 z-[1500] flex items-end sm:items-center justify-center bg-black/60 p-0 sm:p-4 backdrop-blur-sm"
         @click.self="helpOpen = false"
         x-transition:enter="transition duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="w-full max-w-sm max-h-[90dvh] sm:max-h-[85dvh] overflow-hidden rounded-t-2xl sm:rounded-xl bg-white shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800"
             x-transition:enter="transition duration-200"
             x-transition:enter-start="opacity-0 translate-y-8 sm:scale-95 sm:translate-y-0"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white">Legend &amp; Shortcuts</h2>
                <button type="button" @click="helpOpen = false"
                        aria-label="Close help"
                        class="rounded-lg bg-gray-100 p-2 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto p-5">
                <p class="mb-3 text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">Map Legend</p>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-2.5 w-2.5 rounded-full bg-primary-500"></span> Your location</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span> Destinations</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-2.5 w-2.5 rounded-full bg-purple-500"></span> Events</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-1 w-5 rounded bg-primary-500"></span> Active route</div>
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><span class="h-1 w-5 rounded bg-blue-500"></span> Alternate route</div>
                </div>
                <p class="mb-3 mt-6 text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">Keyboard Shortcuts</p>
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

    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 20px;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #475569;
    }

    /* ──────────────────────────────────────────────────────────
       Sidebar pre-boot positioning.
       Without this, the sidebar briefly shows on mobile before
       Alpine boots and applies the translate classes. The rule
       below hides it during that window; Alpine's translate-x-0
       class then overrides it (higher specificity selector) once
       mobileOpen becomes true.
       ────────────────────────────────────────────────────────── */
    @media (max-width: 1023.98px) {
        [data-map-aside] {
            transform: translateX(-100%);
        }
        [data-map-aside].translate-x-0 {
            transform: translateX(0);
        }
    }

    /* Small phones (<= 380px) — Tailwind has no `xs` breakpoint by default */
    @media (min-width: 420px) {
        .xs\:inline { display: inline; }
        .xs\:hidden { display: none; }
    }
</style>
@endpush

{{-- ✅ @script — inlined into the response so it runs before Alpine boots --}}
@script
<script>
    (function () {
        'use strict';

        if (window.__exploreMapModule) return;
        window.__exploreMapModule = true;

        function getData() {
            var root = document.querySelector('[data-map-root]');
            if (!root || !window.Alpine || typeof window.Alpine.$data !== 'function') return null;
            try { return window.Alpine.$data(root); } catch (e) { return null; }
        }

        window.__mapApi = {
            setLocating: function (v) {
                var d = getData();
                if (d) d.locating = v;
            },
            setMapLoading: function (v) {
                var d = getData();
                if (d) { d.mapLoading = v; d.mapStuck = false; }
            },
            wire: function () {
                var d = getData();
                return d ? d.$wire : null;
            },
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
            if (d) {
                d.mapLoading = false;
                d.mapStuck   = false;
            }
            window.__mapCanvasPoll.stop();
        });
    })();

    window.mapApp = function () {
        var toastTimers = new WeakMap();

        return {
            mobileOpen:   false,
            sidebarOpen:  false,
            followMode:   false,
            locating:     false,
            helpOpen:     false,
            mapLoading:   true,
            mapStuck:     false,
            toasts:       [],
            _vpTimer:     null,
            _watchId:     null,

            boot() {
                try {
                    this.sidebarOpen = !!$wire.sidebarOpen;
                    this.followMode  = !!$wire.followMode;
                } catch (e) { /* noop */ }

                if (window.__mapCanvasPoll) {
                    window.__mapCanvasPoll.start();
                }

                setTimeout(() => {
                    window.__mapApi.setMapLoading(false);
                }, 6000);

                this.$watch('mobileOpen', v => {
                    document.body.classList.toggle('overflow-hidden', v && window.innerWidth < 1024);
                });

                this.$watch('sidebarOpen', v => {
                    document.cookie = 'map_sidebar_open=' + (v ? '1' : '0') + ';path=/;max-age=31536000;samesite=Lax';
                    try { $wire.set('sidebarOpen', v, false); } catch (e) { /* noop */ }
                    setTimeout(() => {
                        try { $wire.dispatch('map:resize'); } catch (e) { /* noop */ }
                    }, 320);
                });

                this.$watch('followMode', v => {
                    try { $wire.set('followMode', v, false); } catch (e) { /* noop */ }
                });

                // Close mobile sidebar on resize to desktop
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024 && this.mobileOpen) {
                        this.mobileOpen = false;
                    }
                });
            },

            retryMap() {
                window.__mapApi.setMapLoading(true);

                try {
                    const current = Number($wire.mapRefreshVersion) || 0;
                    $wire.set('mapRefreshVersion', current + 1, true);
                } catch (e) {
                    try { $wire.$refresh(); } catch (e2) { /* noop */ }
                }

                if (window.__mapCanvasPoll) {
                    window.__mapCanvasPoll.start();
                }
            },

            locate() {
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

                window.__mapApi.toast('info', 'Requesting your location… Allow the browser prompt if it appears.');

                let finished = false;

                const finish = (ok, payload) => {
                    if (finished) return;
                    finished = true;
                    window.__mapLocateInFlight = false;
                    window.__mapApi.setLocating(false);

                    const w = window.__mapApi.wire();
                    if (!w) return;

                    if (ok) w.setUserLocation(payload.lat, payload.lng);
                    else    w.locationFailed(payload);
                };

                const watchdog = setTimeout(() => finish(false, 'timeout'), 15000);

                navigator.geolocation.getCurrentPosition(
                    pos => {
                        clearTimeout(watchdog);
                        finish(true, {
                            lat: pos.coords.latitude,
                            lng: pos.coords.longitude,
                        });
                    },
                    err => {
                        clearTimeout(watchdog);
                        const reason = err.code === 1 ? 'denied'
                                     : err.code === 3 ? 'timeout'
                                     : 'unavailable';
                        finish(false, reason);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout:            14000,
                        maximumAge:         0,
                    }
                );
            },

            locateForDirections() { this.locate(); },

            startFollow() {
                if (this.followMode || !navigator.geolocation) return;

                if (window.isSecureContext === false) {
                    this.$wire.locationFailed('insecure');
                    return;
                }

                this.followMode = true;

                let last = 0;
                this._watchId = navigator.geolocation.watchPosition(
                    pos => {
                        const now = Date.now();
                        if (now - last < 2000) return;
                        last = now;
                        this.$wire.setUserLocation(pos.coords.latitude, pos.coords.longitude);
                    },
                    () => { this.stopFollow(); },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            },

            stopFollow() {
                this.followMode = false;
                if (this._watchId) {
                    navigator.geolocation.clearWatch(this._watchId);
                    this._watchId = null;
                }
            },

            debouncedViewport(lat, lng, zoom) {
                clearTimeout(this._vpTimer);
                this._vpTimer = setTimeout(() => this.$wire.updateViewport(lat, lng, zoom), 500);
            },

            async copyText(text) {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const ta = Object.assign(document.createElement('textarea'), {
                            value: text,
                            style: 'position:fixed;opacity:0;',
                        });
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                    this.toast('success', 'Copied to clipboard.');
                } catch {
                    this.toast('error', 'Could not copy link.');
                }
            },

            handleKey(e) {
                const typing = ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName);
                const mod    = e.ctrlKey || e.metaKey || e.altKey;

                if (e.key === 'Escape') {
                    if (this.helpOpen) { this.helpOpen = false; return; }
                    if (typing) { document.activeElement.blur(); return; }
                    if (this.mobileOpen) { this.mobileOpen = false; }
                    return;
                }

                if (e.key === '/' && !typing) {
                    e.preventDefault();
                    document.querySelector('[x-ref="searchInput"]')?.focus();
                    return;
                }

                if (typing || mod) return;

                switch (e.key.toLowerCase()) {
                    case 'l': this.locate(); break;
                    case 'f': this.followMode ? this.stopFollow() : this.startFollow(); break;
                    case 's': this.$wire.toggleSatellite(); break;
                    case 'r': this.$wire.resetView(); break;
                    case '?': this.helpOpen = true; break;
                }
            },

            toast(type, message) {
                const dur = 4000;
                const id  = Date.now() + Math.random();

                this.toasts.push({
                    id, type, message,
                    duration:  dur,
                    remaining: dur,
                    paused:    false,
                });

                const t = this.toasts[this.toasts.length - 1];

                toastTimers.set(t, {
                    timeout:  setTimeout(() => this.removeToast(id), dur),
                    interval: setInterval(() => {
                        if (!t.paused) {
                            t.remaining = Math.max(0, t.remaining - 100);
                        }
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
                t.paused = true;
                const timers = toastTimers.get(t);
                if (timers) clearTimeout(timers.timeout);
            },

            resumeToast(t) {
                t.paused = false;
                const timers = toastTimers.get(t);
                if (timers) {
                    timers.timeout = setTimeout(() => this.removeToast(t.id), Math.max(t.remaining, 300));
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
</script>
@endscript