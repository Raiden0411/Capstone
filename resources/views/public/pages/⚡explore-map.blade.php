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
    public ?array  $pendingFlyTo = null;

    public int $userLocationVersion = 0;
    public int $mapRefreshVersion   = 0;
    public int $themeVersion        = 0;

    public ?int $pendingDirectionsTenantId  = null;
    public int  $pendingDirectionsCoordIndex = 0;

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
        if (request()->filled('marker'))  $this->pendingMarkerId  = (int)    request('marker');
        if ($this->pendingMarkerId && request()->boolean('directions')) $this->autoDirections = true;
        if (request()->filled('profile') && in_array(request('profile'), ['driving','walking','cycling'], true))
            $this->directionsProfile = request('profile');
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
                'settings' => fn($q) => $q->where('key','business_info')->select('tenant_id','value'),
            ])
            ->withCount(['properties','services'])
            ->withMin('properties','price')
            ->when($term, fn($q) => $q->where(fn($s) =>
                $s->where('name','like',"%$term%")
                  ->orWhere('address','like',"%$term%")
                  ->orWhereHas('typeOfTenant', fn($t) => $t->where('type','like',"%$term%"))
            ))
            ->when($this->categoryFilter, fn($q) =>
                $q->whereHas('typeOfTenant', fn($s) => $s->where('type', $this->categoryFilter))
            )
            ->when($this->favoritesOnly, fn($q) => $q->whereIn('id', $this->favorites ?: [0]))
            ->when($this->recommendedOnly, fn($q) => $q->where('is_recommended', true))
            ->get(['id','name','slug','logo','address','contact_number','email',
                   'coordinates','is_recommended','type_of_tenant_id','created_at']);

        if ($this->hasOfferings) {
            $tenants = $tenants->filter(fn($t) => $t->properties_count > 0 || $t->services_count > 0);
        }
        if ($this->openNow) {
            $tenants = $tenants->filter(fn($t) => $this->isOpenNow($t));
        }

        return match ($this->sortBy) {
            'distance' => ($this->userLat && $this->userLng)
                ? $tenants->sortBy(fn($t) => $this->distance($t->coordinates[0]['lat'], $t->coordinates[0]['lng']))->values()
                : $tenants->sortBy('name')->values(),
            'newest'   => $tenants->sortByDesc('created_at')->values(),
            'popular'  => $tenants->sortByDesc(fn($t) => $t->properties_count + $t->services_count)->values(),
            default    => $tenants->sortBy('name')->values(),
        };
    }

    #[Computed]
    public function eventMarkers()
    {
        if (!$this->showEvents) return collect();

        return Event::withoutGlobalScope(TenantScope::class)
            ->whereNotNull('coordinates')
            ->where('is_active', true)
            ->where('start_date','>=', now()->subDay())
            ->with('tenant:id,name,slug')
            ->get(['id','name','barangay','type','start_date','end_date','coordinates','featured','tenant_id'])
            ->map(function ($e) {
                $c = is_array($e->coordinates) ? $e->coordinates : json_decode($e->coordinates, true);
                return [
                    'id' => $e->id, 'name' => $e->name, 'barangay' => $e->barangay,
                    'type' => $e->type, 'start_date' => $e->start_date, 'end_date' => $e->end_date,
                    'lat' => (float)$c['lat'], 'lng' => (float)$c['lng'],
                    'featured' => (bool)$e->featured,
                    'tenant_slug' => optional($e->tenant)->slug,
                ];
            });
    }

    #[Computed]
    public function geoJsonData()
    {
        $features = $this->tenants->flatMap(fn($tenant) =>
            collect($tenant->coordinates)->map(fn($coord) => [
                'type' => 'Feature',
                'properties' => [
                    'name'      => $coord['name'] ?? $tenant->name,
                    'type'      => $tenant->typeOfTenant?->type ?? 'Business',
                    'tenant_id' => $tenant->id,
                    'slug'      => $tenant->slug,
                    'logo'      => $tenant->logo,
                    'address'   => $tenant->address,
                    'offerings' => $tenant->properties_count + $tenant->services_count,
                    'favorite'  => in_array($tenant->id, $this->favorites, true),
                    'status'    => $this->openStatusLabel($tenant),
                ],
                'geometry' => ['type' => 'Point', 'coordinates' => [(float)$coord['lng'], (float)$coord['lat']]],
            ])
        )->values()->toArray();

        if ($this->showEvents) {
            $eventFeatures = $this->eventMarkers->map(fn($e) => [
                'type' => 'Feature',
                'properties' => [
                    'name' => $e['name'], 'type' => 'Event',
                    'event_id' => $e['id'], 'barangay' => $e['barangay'],
                    'start' => $e['start_date']->format('M d, Y'),
                ],
                'geometry' => ['type' => 'Point', 'coordinates' => [$e['lng'], $e['lat']]],
            ])->values()->toArray();
            return array_merge($features, $eventFeatures);
        }

        return $features;
    }

    #[Computed]
    public function categories()
    {
        return TypeOfTenant::withoutGlobalScope(TenantScope::class)
            ->withCount(['tenants' => fn($q) => $q->where('is_active', true)->withoutGlobalScope(TenantScope::class)])
            ->whereHas('tenants', fn($q) => $q->where('is_active', true)->withoutGlobalScope(TenantScope::class))
            ->orderBy('type')->get(['id','type']);
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
            ? [(float)$this->currentLng, (float)$this->currentLat]
            : (($this->userLat && $this->userLng)
                ? [(float)$this->userLng, (float)$this->userLat]
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
        return SiteSetting::getValue('marker_categories', []);
    }

    protected function isOpenNow(Tenant $tenant): bool
    {
        $hours = $tenant->settings->first()?->value['opening_hours'] ?? null;
        if (!$hours) return false;
        $days = $hours['days'] ?? null;
        if (is_array($days) && !in_array((int)now()->dayOfWeek, array_map('intval', $days), true)) return false;
        $now = now()->format('H:i');
        $o = $hours['opening'] ?? '00:00';
        $c = $hours['closing'] ?? '23:59';
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
        $R = 6371;
        $dLat = deg2rad($lat2 - $this->userLat);
        $dLng = deg2rad($lng2 - $this->userLng);
        $a = sin($dLat/2)**2 + cos(deg2rad($this->userLat)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }

    public function formatDistance(float $km): string
    {
        return $km < 1 ? round($km * 1000).' m' : number_format($km, 1).' km';
    }

    public function highlightMatch(string $text): \Illuminate\Support\HtmlString
    {
        $escaped = e($text);
        $term    = trim($this->search);
        if (!$term) return new \Illuminate\Support\HtmlString($escaped);
        $highlighted = preg_replace(
            '/('.preg_quote(e($term),'/').')/i',
            '<mark class="bg-amber-200 dark:bg-amber-400/30 rounded-sm px-0.5">$1</mark>',
            $escaped
        );
        return new \Illuminate\Support\HtmlString($highlighted ?? $escaped);
    }

    public function favoritesKey(): string
    {
        return auth()->check()
            ? 'map_favorites_user_'.auth()->id()
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
                ->with(['typeOfTenant:id,type','settings' => fn($q) => $q->where('key','business_info')->select('tenant_id','value')])
                ->select(['id','name','slug','logo','address','contact_number','email','coordinates','is_recommended','type_of_tenant_id','created_at'])
                ->find($id);
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
        $t = $this->resolveT($id);
        if (!$t || empty($t->coordinates)) { $this->notify('No location found.', 'error'); return; }
        $this->highlightedId = $id;
        $c = $t->coordinates[0];
        $this->dispatch('map:fly-to', center: [(float)$c['lng'], (float)$c['lat']], zoom: 16);
        $this->dispatch('tenant-viewed', id: $t->id, name: $t->name, type: $t->typeOfTenant?->type ?? 'Business');
    }

    public function flyToTenantCoord(int $id, int $index): void
    {
        $t = $this->resolveT($id);
        if (!$t || empty($t->coordinates[$index])) { $this->notify('Location not found.', 'error'); return; }
        $this->highlightedId = $id;
        $c = $t->coordinates[$index];
        $this->dispatch('map:fly-to', center: [(float)$c['lng'], (float)$c['lat']], zoom: 17);
    }

    public function getDirectionsTo(int $id, int $index = 0): void
    {
        $t = $this->resolveT($id);
        if (!$t || empty($t->coordinates)) { $this->notify('No location found.', 'error'); return; }
        if (!$this->userLat || !$this->userLng) {
            $this->pendingDirectionsTenantId  = $id;
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
        $this->routeCoords          = ['start' => [(float)$this->userLng, (float)$this->userLat], 'end' => [(float)$c['lng'], (float)$c['lat']]];
        $this->routeDestinationName = $label;
        $this->routeTenantId        = $t->id;
        $this->highlightedId        = $t->id;
        $this->routeVersion++;

        $dLat = abs($this->userLat - (float)$c['lat']);
        $dLng = abs($this->userLng - (float)$c['lng']);
        $max  = max($dLat, $dLng);
        $zoom = $max < 0.01 ? 16 : ($max < 0.05 ? 15 : ($max < 0.1 ? 14 : 13));
        $this->pendingFlyTo = ['center' => [($this->userLng + (float)$c['lng'])/2, ($this->userLat + (float)$c['lat'])/2], 'zoom' => $zoom];
    }

    public function setDirectionsProfile(string $profile): void
    {
        if (!in_array($profile, ['driving','walking','cycling'], true)) return;
        $this->directionsProfile = $profile;
        $this->routeVersion++;
    }

    public function clearRoute(): void
    {
        $this->routeCoords = []; $this->routeDestinationName = null;
        $this->routeTenantId = null; $this->pendingFlyTo = null;
        $this->routeVersion++;
        $this->notify('Route cleared.', 'info');
    }

    public function setUserLocation($lat, $lng): void
    {
        $oldLat = $this->userLat;
        $this->userLat = $this->currentLat = round((float)$lat, 6);
        $this->userLng = $this->currentLng = round((float)$lng, 6);
        $this->currentZoom = 15;
        $this->userLocationVersion++;
        if ($oldLat === null || $this->distance($oldLat, $this->userLng) > 0.1) $this->mapRefreshVersion++;

        if ($this->pendingDirectionsTenantId) {
            $id  = $this->pendingDirectionsTenantId;
            $idx = $this->pendingDirectionsCoordIndex;
            $this->pendingDirectionsTenantId = null;
            $this->pendingDirectionsCoordIndex = 0;
            $t = $this->resolveT($id);
            if (!$t || empty($t->coordinates[$idx])) { $this->notify('Destination no longer available.', 'error'); return; }
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
        $this->autoDirections = false;
        $this->pendingDirectionsTenantId = null;
        $this->pendingDirectionsCoordIndex = 0;
        $this->notify(match($reason) {
            'denied'  => 'Location access was denied. You can still browse manually.',
            'timeout' => 'Location timed out. Please try again.',
            default   => 'Your location could not be determined.',
        }, $reason === 'unavailable' ? 'error' : 'warning');
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
        $this->reset(['search','categoryFilter','openNow','hasOfferings','favoritesOnly','recommendedOnly','showEvents']);
        $this->filtersHash = $this->hashFilters();
        $this->notify('Filters cleared.', 'info');
    }

    public function resetView(): void
    {
        $this->dispatch('map:fly-to', center: self::CITY_CENTER, zoom: 12);
    }

    public function shareLocation(): void
    {
        if (!$this->userLat) { $this->notify('Find your location first.', 'info'); return; }
        $this->dispatch('copy-to-clipboard', text: request()->url().'?lat='.$this->userLat.'&lng='.$this->userLng);
        $this->notify('Location link copied.', 'success');
    }

    public function shareMarker(int $id): void
    {
        $t = $this->resolveT($id);
        if (!$t) { $this->notify('Destination not found.', 'error'); return; }
        $this->dispatch('copy-to-clipboard', text: request()->url().'?marker='.$id);
        $this->notify('Destination link copied.', 'success');
    }

    public function shareRoute(): void
    {
        if (!$this->routeTenantId) { $this->notify('Start a route first.', 'info'); return; }
        $url = request()->url().'?marker='.$this->routeTenantId.'&directions=1&profile='.$this->directionsProfile;
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

    public function updatedSearch():         void { $this->filtersHash = $this->hashFilters(); }
    public function updatedCategoryFilter(): void { $this->filtersHash = $this->hashFilters(); }
    public function updatedOpenNow():        void { $this->filtersHash = $this->hashFilters(); }
    public function updatedHasOfferings():   void { $this->filtersHash = $this->hashFilters(); }
    public function updatedFavoritesOnly():  void { $this->filtersHash = $this->hashFilters(); }
    public function updatedRecommendedOnly():void { $this->filtersHash = $this->hashFilters(); }
    public function updatedShowEvents():     void { $this->filtersHash = $this->hashFilters(); }

    public function updatedSortBy(string $v): void
    {
        if ($v === 'distance' && !$this->userLat) $this->dispatch('request-location-for-distance');
    }

    #[On('map:loaded')]
    public function onMapReady(): void
    {
        if ($this->pendingMarkerId) {
            $t = $this->resolveT($this->pendingMarkerId);
            $this->pendingMarkerId = null;
            if (!$t || empty($t->coordinates)) { $this->autoDirections = false; return; }
            $this->highlightedId = $t->id;
            $c = $t->coordinates[0];
            $this->dispatch('map:fly-to', center: [(float)$c['lng'], (float)$c['lat']], zoom: 16);
            $this->dispatch('tenant-viewed', id: $t->id, name: $t->name, type: $t->typeOfTenant?->type ?? 'Business');
            if ($this->autoDirections) $this->dispatch('locate-for-directions');
        }
        if ($this->pendingFlyTo) {
            $this->dispatch('map:fly-to', center: $this->pendingFlyTo['center'], zoom: $this->pendingFlyTo['zoom']);
            $this->pendingFlyTo = null;
        }
    }

    #[On('map:marker-clicked')]
    public function onMarkerClicked($id, $lat, $lng): void
    {
        if (preg_match('/tenant-(\d+)-(\d+)/', $id, $m)) {
            $this->highlightedId = (int)$m[1];
            $t = $this->resolveT((int)$m[1]);
            if ($t && isset($t->coordinates[(int)$m[2]])) {
                $c = $t->coordinates[(int)$m[2]];
                $this->dispatch('map:fly-to', center: [(float)$c['lng'], (float)$c['lat']], zoom: 16);
            }
        }
    }
};
?>

<div class="relative flex overflow-hidden font-sans dark:bg-gray-950"
     style="height: calc(100vh - 64px);"
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

    {{-- Mobile sidebar overlay --}}
    <div x-show="mobileOpen"
         x-transition:enter="transition-opacity duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[1090] bg-black/60 backdrop-blur-sm lg:hidden"
         @click="mobileOpen = false"
         x-cloak></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-[1100] w-[340px] lg:static lg:z-auto flex-shrink-0 transition-all duration-300 ease-out bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800"
           :class="{
               'translate-x-0': sidebarOpen || mobileOpen,
               '-translate-x-full': !sidebarOpen && !mobileOpen,
               'lg:translate-x-0 lg:w-[340px]': sidebarOpen,
               'lg:w-0 lg:overflow-hidden lg:border-r-0': !sidebarOpen,
           }">
        @include('livewire.partials.explore-sidebar')
    </aside>

    {{-- Map area --}}
    <div class="relative flex-1 min-w-0 overflow-hidden bg-gray-100 dark:bg-gray-900">

        {{-- Offline banner --}}
        <div x-show="!online" x-cloak
             class="absolute inset-x-0 top-0 z-[1200] flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-center text-xs font-semibold text-white dark:text-gray-900">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            You're offline — map tiles and routing unavailable
        </div>

        {{-- Map loading --}}
        <div x-show="mapLoading" x-cloak
             class="absolute inset-0 z-[900] flex items-center justify-center bg-gray-100 dark:bg-gray-900">
            <div class="text-center">
                <div class="mx-auto mb-3 h-10 w-10 animate-spin rounded-full border-2 border-primary-600 border-t-transparent"></div>
                <p class="text-sm text-gray-500 dark:text-gray-400 italic">Loading map…</p>
            </div>
        </div>

        {{-- Actual map --}}
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
                :events="['click','marker-clicked']"
                @map:load="
                    $event.detail.map?.setRenderWorldCopies(false);
                    $event.detail.map?.setMaxBounds([[122.0,9.5],[124.0,11.8]]);
                    $event.detail.map?.setMinZoom(10);
                "
            >
                <x-map-controls :zoom="true" :compass="true" :locate="false" :fullscreen="true" :scale="true" position="top-right"/>

                {{-- User location marker --}}
                @if($userLat && $userLng)
                    <x-map-marker
                        :key="'user-'.$userLocationVersion"
                        wire:key="user-marker-{{ $userLocationVersion }}"
                        :lat="$userLat" :lng="$userLng"
                        color="#C8A96E" id="user-location" anchor="center">
                        <x-marker-content>
                            <div class="relative flex h-13 w-13 items-center justify-center">
                                @if($followMode)
                                    <div class="absolute inset-0 rounded-full bg-primary-500/20 animate-ping"></div>
                                @endif
                                <div class="absolute h-11 w-11 rounded-full bg-primary-500/10 border border-primary-500/30"></div>
                                <div class="h-5 w-5 rounded-full bg-primary-500 border-2 border-white shadow-lg relative z-10"></div>
                            </div>
                        </x-marker-content>
                        <x-marker-popup>
                            <div class="rounded-xl bg-white p-4 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 min-w-[180px]">
                                <p class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-2">{{ !empty($routeCoords) ? 'Route Origin' : 'Your Location' }}</p>
                                <button type="button" wire:click="shareLocation" class="w-full rounded-lg bg-primary-50 dark:bg-primary-500/10 px-3 py-2 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-500/20 transition">Share Location</button>
                            </div>
                        </x-marker-popup>
                    </x-map-marker>
                @endif

                {{-- Tenant markers --}}
                @foreach($this->tenants as $tenant)
                    @php
                        $colors = ['#f97316','#a855f7','#3b82f6','#14b8a6','#eab308','#10b981','#8b5cf6','#f43f5e'];
                        $tc     = $colors[$loop->index % count($colors)];
                        $isRoute= $routeTenantId === $tenant->id && !empty($routeCoords);
                        $isHL   = $highlightedId === $tenant->id;
                        $logo   = $tenant->logo ? asset('storage/'.$tenant->logo) : null;
                        $minP   = $tenant->properties_min_price;
                    @endphp
                    @foreach($tenant->coordinates as $ci => $coord)
                        @php
                            $isParent   = $ci === 0 || ($coord['type'] ?? '') === 'parent';
                            $coordType  = $coord['type'] ?? null;
                            $mcMatch    = !$isParent && $coordType
                                ? collect($this->markerCategories)->firstWhere('key', $coordType)
                                : null;
                            $activeColor = $mcMatch ? ($mcMatch['color'] ?? $tc) : $tc;
                        @endphp
                        <x-map-marker
                            :key="'t-'.$tenant->id.'-'.$ci"
                            wire:key="m-{{ $tenant->id }}-{{ $ci }}"
                            :lat="$coord['lat']" :lng="$coord['lng']"
                            :color="$activeColor"
                            id="tenant-{{ $tenant->id }}-{{ $ci }}"
                            anchor="bottom">
                            <x-marker-content>
                                <div class="flex flex-col items-center cursor-pointer group">
                                    @if($isParent)
                                        <div class="flex h-11 w-11 items-center justify-center rounded-full border-2 bg-white shadow-lg transition-transform group-hover:scale-110 dark:bg-gray-900 {{ ($isRoute||$isHL) ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-gray-900' : '' }}"
                                             style="border-color: {{ $tc }};">
                                            @if($logo)
                                                <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="h-full w-full rounded-full object-cover" loading="lazy">
                                            @else
                                                <span class="text-xs font-bold text-gray-900 dark:text-white">{{ strtoupper(substr($tenant->name,0,2)) }}</span>
                                            @endif
                                        </div>
                                    @elseif($mcMatch)
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full border-2 bg-white shadow-md transition-transform group-hover:scale-110 dark:bg-gray-900" style="border-color: {{ $activeColor }};">
                                            @if($mcMatch['icon_svg'] ?? null)
                                                <div class="h-4 w-4 text-gray-800 dark:text-white">{!! str_replace('<svg ',  '<svg class="h-full w-full fill-none stroke-current stroke-2" ', $mcMatch['icon_svg']) !!}</div>
                                            @else
                                                <span class="text-xs font-bold text-gray-800 dark:text-white">{{ strtoupper(substr($coordType,0,1)) }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="h-3.5 w-3.5 rounded-full border-2 border-white shadow transition-transform group-hover:scale-125 dark:border-gray-900" style="background: {{ $tc }};"></div>
                                    @endif
                                    <svg class="mt-[-1px] h-1.5 w-2" style="color: {{ $activeColor }};" viewBox="0 0 12 8" fill="currentColor"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                </div>
                            </x-marker-content>
                            <x-marker-popup>
                                <div class="w-[260px] max-w-[280px] overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                    @if($logo && $isParent)
                                        <div class="relative h-28 w-full overflow-hidden">
                                            <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="h-full w-full object-cover brightness-90">
                                            <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 to-transparent"></div>
                                            <span class="absolute bottom-2 left-3 rounded bg-gray-900/60 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary-300">
                                                {{ $tenant->typeOfTenant?->type ?? 'Business' }}
                                            </span>
                                        </div>
                                    @endif
                                    <div class="p-4">
                                        <h3 class="text-lg font-display font-semibold text-gray-900 dark:text-white leading-tight">{{ $coord['name'] ?? $tenant->name }}</h3>
                                        @if(!($logo && $isParent))
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-wider" style="color: {{ $tc }};">{{ $tenant->typeOfTenant?->type ?? 'Business' }}</p>
                                        @endif
                                        @if($minP !== null)
                                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">From <span class="font-semibold text-gray-900 dark:text-white">₱{{ number_format($minP,2) }}</span></p>
                                        @endif
                                        @if($userLat && $userLng)
                                            <p class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                                {{ $this->formatDistance($this->distance($coord['lat'],$coord['lng'])) }} away
                                            </p>
                                        @endif
                                        <div class="mt-4 flex gap-2">
                                            <a href="{{ route('business.offerings',$tenant->slug) }}" wire:navigate class="flex-1 rounded-lg bg-primary-600 px-3 py-2 text-center text-xs font-bold text-white transition hover:bg-primary-700 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500">View</a>
                                            <button type="button" wire:click="getDirectionsTo({{ $tenant->id }},{{ $ci }})" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-primary-500 hover:text-primary-600 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-300 dark:hover:border-primary-500 dark:hover:text-primary-400">Directions</button>
                                        </div>
                                    </div>
                                </div>
                            </x-marker-popup>
                        </x-map-marker>
                    @endforeach
                @endforeach

                {{-- Event markers --}}
                @if($showEvents)
                    @foreach($this->eventMarkers as $ev)
                        <x-map-marker
                            :key="'ev-'.$ev['id']"
                            wire:key="ev-m-{{ $ev['id'] }}"
                            :lat="$ev['lat']" :lng="$ev['lng']"
                            color="#A78BFA" id="event-{{ $ev['id'] }}" anchor="bottom">
                            <x-marker-content>
                                <div class="flex flex-col items-center cursor-pointer group">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-tr from-purple-600 to-pink-500 border-2 border-white shadow-lg transition-transform group-hover:scale-110 dark:border-gray-900">
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    </div>
                                    <svg class="mt-[-1px] h-1.5 w-2" style="color: #A78BFA;" viewBox="0 0 12 8" fill="currentColor"><path d="M0 0 L12 0 L6 8 Z"/></svg>
                                </div>
                            </x-marker-content>
                            <x-marker-popup>
                                <div class="w-[220px] rounded-xl bg-white p-4 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                    <p class="text-xs font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400">Event</p>
                                    <h3 class="mt-1 text-lg font-display font-semibold text-gray-900 dark:text-white">{{ $ev['name'] }}</h3>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $ev['start_date']->format('M d, Y') }}</p>
                                    <a href="{{ route('events',['event'=>$ev['id']]) }}" wire:navigate class="mt-3 block rounded-lg bg-purple-600 px-3 py-2 text-center text-xs font-bold text-white transition hover:bg-purple-700">View Event</a>
                                </div>
                            </x-marker-popup>
                        </x-map-marker>
                    @endforeach
                @endif

                {{-- Active route --}}
                @if(!empty($routeCoords))
                    <x-map-route
                        wire:key="route-{{ md5(serialize($routeCoords)) }}-{{ $directionsProfile }}-{{ $routeVersion }}"
                        id="{{ $routeId }}"
                        :coordinates="[$routeCoords['start'],$routeCoords['end']]"
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

        {{-- Toolbar --}}
        <div class="absolute left-3 top-3 z-[1000] flex flex-col gap-1.5">
            <div class="flex flex-col divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
                <button type="button" data-tip="Toggle sidebar" @click="sidebarOpen = !sidebarOpen" class="flex h-10 w-10 items-center justify-center text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" :class="sidebarOpen ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                </button>
                <button type="button" data-tip="My location (L)" @click="locate()" :disabled="locating" class="flex h-10 w-10 items-center justify-center text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" :class="locating ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg x-show="!locating" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21c-4.5-4.5-7.5-8.24-7.5-11.5A7.5 7.5 0 0112 2a7.5 7.5 0 017.5 7.5c0 3.26-3 7-7.5 11.5z"/><circle cx="12" cy="9.5" r="2.5" stroke-width="2" fill="none"/></svg>
                    <svg x-show="locating" x-cloak class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </button>
                <button type="button" data-tip="Follow mode (F)" @click="followMode ? stopFollow() : startFollow()" class="flex h-10 w-10 items-center justify-center text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" :class="followMode ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : ''">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L21 21 12 17 3 21Z"/></svg>
                </button>
            </div>

            <div class="flex flex-col divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
                <button type="button" data-tip="Recenter (R)" wire:click="resetView" class="flex h-10 w-10 items-center justify-center text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/></svg>
                </button>
                <button type="button" data-tip="Satellite (S)" wire:click="toggleSatellite" class="flex h-10 w-10 items-center justify-center text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" :class="{{ $satellite ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : '' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 11a8 8 0 00-14 0M4 13a8 8 0 0014 0M12 12v3m0 0a9 9 0 01-9 9m9-9a9 9 0 019 9"/></svg>
                </button>
            </div>

            <div class="flex flex-col divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-900">
                <button type="button" data-tip="Share location" wire:click="shareLocation" class="flex h-10 w-10 items-center justify-center text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                </button>
                <button type="button" data-tip="Help & shortcuts (?)" @click="helpOpen = true" class="flex h-10 w-10 items-center justify-center text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500 active:scale-95 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
                </button>
            </div>

            @if(!empty($routeCoords))
                <div class="flex flex-col overflow-hidden rounded-lg border border-red-200 bg-white shadow-lg dark:border-red-500/30 dark:bg-gray-900">
                    <button type="button" data-tip="Clear route" wire:click="clearRoute" class="flex h-10 w-10 items-center justify-center text-red-500 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 active:scale-95 dark:hover:bg-red-500/10">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif
        </div>

        {{-- Route panel --}}
        @if(!empty($routeCoords))
            <div class="absolute bottom-6 left-1/2 z-[900] flex -translate-x-1/2 items-center gap-6 rounded-xl bg-white px-5 py-3 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Navigating to</p>
                    <p class="font-display text-lg font-semibold text-gray-900 dark:text-white">{{ $routeDestinationName }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Mode</p>
                    <div class="flex gap-1">
                        @foreach(['driving'=>'Drive','walking'=>'Walk','cycling'=>'Cycle'] as $p=>$l)
                            <button type="button" wire:click="setDirectionsProfile('{{ $p }}')" class="rounded-md px-2.5 py-1 text-xs font-semibold uppercase transition {{ $directionsProfile === $p ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">{{ $l }}</button>
                        @endforeach
                    </div>
                </div>
                <button type="button" wire:click="clearRoute" class="ml-2 flex h-8 w-8 items-center justify-center rounded-lg border border-gray-300 text-gray-500 transition hover:border-red-500 hover:text-red-500 dark:border-gray-700 dark:text-gray-400 dark:hover:border-red-500 dark:hover:text-red-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif
    </div>

    {{-- Toast stack --}}
    <div class="pointer-events-none fixed bottom-6 right-5 z-[1300] flex flex-col gap-2" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex min-w-[240px] max-w-sm items-center gap-3 rounded-lg border bg-white p-3 shadow-xl dark:bg-gray-900 cursor-pointer"
                 :class="{
                     'border-l-4 border-l-emerald-500': t.type === 'success',
                     'border-l-4 border-l-red-500': t.type === 'error',
                     'border-l-4 border-l-blue-500': t.type === 'info',
                     'border-l-4 border-l-amber-500': t.type === 'warning',
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
                    'text-red-500': t.type === 'error',
                    'text-blue-500': t.type === 'info',
                    'text-amber-500': t.type === 'warning',
                }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <template x-if="t.type === 'success'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></template>
                    <template x-if="t.type === 'error'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></template>
                    <template x-if="t.type === 'info'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></template>
                    <template x-if="t.type === 'warning'"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></template>
                </svg>
                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200" x-text="t.message"></span>
                <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click.stop="removeToast(t.id)" aria-label="Dismiss">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <div class="absolute bottom-0 left-0 h-0.5 bg-current transition-all" :class="{
                    'text-emerald-500': t.type === 'success',
                    'text-red-500': t.type === 'error',
                    'text-blue-500': t.type === 'info',
                    'text-amber-500': t.type === 'warning',
                }" :style="'width:' + ((t.remaining / t.duration) * 100) + '%'"></div>
            </div>
        </template>
    </div>

    {{-- Help modal --}}
    <div x-show="helpOpen" x-cloak class="fixed inset-0 z-[1500] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="helpOpen = false"
         x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800" x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white">Legend & Shortcuts</h2>
                <button @click="helpOpen = false" class="rounded-lg bg-gray-100 p-2 text-gray-500 transition hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
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

@script
<script>
function mapApp() {
    return {
        mobileOpen:  false,
        sidebarOpen: @entangle('sidebarOpen'),
        followMode:  @entangle('followMode'),
        locating:    false,
        online:      navigator.onLine,
        helpOpen:    false,
        mapLoading:  true,
        toasts:      [],
        _vpTimer:    null,
        _watchId:    null,

        boot() {
            window.addEventListener('map:loaded', () => { this.mapLoading = false; }, { once: true });
            setTimeout(() => { this.mapLoading = false; }, 6000);
            window.addEventListener('online',  () => { this.online = true; });
            window.addEventListener('offline', () => { this.online = false; });
            this.$watch('mobileOpen', v => document.body.classList.toggle('overflow-hidden', v));
            this.$watch('sidebarOpen', v => {
                document.cookie = 'map_sidebar_open=' + (v ? '1' : '0') + ';path=/;max-age=31536000;samesite=Lax';
                setTimeout(() => this.$wire.dispatch('map:resize'), 320);
            });
        },

        locate() {
            if (!navigator.geolocation) { this.$wire.locationFailed('unavailable'); return; }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                pos => { this.locating = false; this.$wire.setUserLocation(pos.coords.latitude, pos.coords.longitude); },
                err => { this.locating = false; this.$wire.locationFailed(err.code === 1 ? 'denied' : err.code === 3 ? 'timeout' : 'unavailable'); },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        },
        locateForDirections() { this.locate(); },
        startFollow() {
            if (this.followMode || !navigator.geolocation) return;
            this.followMode = true;
            this.$wire.toggleFollowMode(true);
            let last = 0;
            this._watchId = navigator.geolocation.watchPosition(
                pos => {
                    const now = Date.now();
                    if (now - last < 2000) return;
                    last = now;
                    this.$wire.setUserLocation(pos.coords.latitude, pos.coords.longitude);
                },
                () => { this.stopFollow(); },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        },
        stopFollow() {
            this.followMode = false;
            this.$wire.toggleFollowMode(false);
            if (this._watchId) { navigator.geolocation.clearWatch(this._watchId); this._watchId = null; }
        },

        debouncedViewport(lat, lng, zoom) {
            clearTimeout(this._vpTimer);
            this._vpTimer = setTimeout(() => this.$wire.updateViewport(lat, lng, zoom), 500);
        },

        async copyText(text) {
            try {
                if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(text);
                else { const ta = Object.assign(document.createElement('textarea'), { value: text, style: 'position:fixed;opacity:0;' }); document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); }
                this.toast('success', 'Copied to clipboard.');
            } catch { this.toast('error', 'Could not copy link.'); }
        },

        handleKey(e) {
            const typing = ['INPUT','TEXTAREA'].includes(document.activeElement?.tagName);
            const mod = e.ctrlKey || e.metaKey || e.altKey;
            if (e.key === 'Escape') {
                if (this.helpOpen) { this.helpOpen = false; return; }
                if (typing) { document.activeElement.blur(); return; }
                if (this.mobileOpen) { this.mobileOpen = false; }
                return;
            }
            if (e.key === '/' && !typing) { e.preventDefault(); document.querySelector('[x-ref="searchInput"]')?.focus(); return; }
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
            const t   = { id: Date.now()+Math.random(), type, message, duration: dur, remaining: dur, paused: false };
            t._timer  = setTimeout(() => this.removeToast(t.id), dur);
            t._tick   = setInterval(() => { if (!t.paused) t.remaining = Math.max(0, t.remaining - 100); }, 100);
            this.toasts.push(t);
            if (this.toasts.length > 4) { const o = this.toasts.shift(); clearTimeout(o._timer); clearInterval(o._tick); }
        },
        pauseToast(t)  { t.paused = true; clearTimeout(t._timer); },
        resumeToast(t) { t.paused = false; t._timer = setTimeout(() => this.removeToast(t.id), Math.max(t.remaining, 300)); },
        removeToast(id) {
            const t = this.toasts.find(x => x.id === id);
            if (t) { clearTimeout(t._timer); clearInterval(t._tick); }
            this.toasts = this.toasts.filter(x => x.id !== id);
        },
    };
}
window.mapApp = mapApp;
</script>
@endscript