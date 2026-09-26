{{-- resources/views/superadmin/pages/map-marker/⚡manage-map-markers.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Tenant;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new
#[Layout('superadmin.layouts.app')]
#[Title('Map Markers')]
class extends Component
{
    public string $tenant_id = '';
    public array $coordinates = [];
    public string $tenantSearch = '';
    public bool $showRadius = true;

    public array $mapView = [];
    public int $mapVersion = 0;

    public array $markerCategories = [];

    private const DEFAULT_LAT = 10.900977766937142;
    private const DEFAULT_LNG = 123.07055771888716;
    private const MAX_MARKERS = 20;

    public function mount(): void
    {
        $this->authorizeSuperAdmin();

        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
        $this->updateMapViewport();
    }

    /**
     * Rule 16: every Livewire action is a fresh HTTP request that
     * bypasses route middleware. Every mutating method re-checks the
     * super-admin role here.
     */
    protected function authorizeSuperAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    public function updatedTenantId($value): void
    {
        if ($value && $tenant = Tenant::find($value)) {
            $this->coordinates = $tenant->coordinates ?? [];

            if (!empty($this->coordinates)) {
                $this->enforceParentRule();
                $this->updateMapViewport($this->coordinates[0]);
            } else {
                $this->updateMapViewport();
            }
        } else {
            $this->coordinates = [];
            $this->updateMapViewport();
        }

        $this->mapVersion++;
    }

    public function updated($property): void
    {
        if (preg_match('/^coordinates\.\d+\.type$/', $property)) {
            $this->enforceParentRule();
            $this->mapVersion++;
        }
    }

    #[Computed]
    public function availableTenants()
    {
        return Tenant::query()
            ->with('typeOfTenant:id,type')
            ->select('id', 'name', 'logo', 'coordinates', 'type_of_tenant_id')
            ->orderBy('name')
            ->when($this->tenantSearch, function ($q) {
                $term = trim($this->tenantSearch);
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhereHas('typeOfTenant', fn ($t) => $t->where('type', 'like', "%{$term}%"));
                });
            })
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function activeTenant(): ?Tenant
    {
        if (!$this->tenant_id) {
            return null;
        }

        return Tenant::query()
            ->with('typeOfTenant:id,type')
            ->select('id', 'name', 'logo', 'coordinates', 'type_of_tenant_id')
            ->find($this->tenant_id);
    }

    #[Computed]
    public function allMappedTenants()
    {
        return Tenant::query()
            ->select('id', 'name', 'slug', 'logo', 'coordinates')
            ->whereNotNull('coordinates')
            ->where('is_active', true)
            ->get()
            ->map(function ($tenant) {
                if (!$tenant->coordinates || !is_array($tenant->coordinates)) {
                    return null;
                }

                // Rule J: relative /storage path, never asset() — APP_URL
                // may not match the current host.
                return [
                    'id'      => $tenant->id,
                    'name'    => $tenant->name,
                    'slug'    => $tenant->slug,
                    'logo'    => $tenant->logo ? '/storage/' . ltrim($tenant->logo, '/') : null,
                    'markers' => $tenant->coordinates,
                ];
            })
            ->filter()
            ->values();
    }

    #[Computed]
    public function stats(): array
    {
        $total = Tenant::query()->count();

        $mapped = Tenant::query()
            ->whereNotNull('coordinates')
            ->whereRaw('JSON_LENGTH(coordinates) > 0')
            ->count();

        return [
            'total'    => $total,
            'mapped'   => $mapped,
            'unmapped' => max(0, $total - $mapped),
        ];
    }

    #[Computed]
    public function maxMarkers(): int
    {
        return self::MAX_MARKERS;
    }

    #[On('map:click')]
    public function onMapClick($lat, $lng): void
    {
        if (!$this->tenant_id) {
            $this->dispatch('toast', message: 'Select a business first.', type: 'warning');
            return;
        }

        if (count($this->coordinates) >= self::MAX_MARKERS) {
            $this->dispatch('toast', message: 'You can add up to ' . self::MAX_MARKERS . ' markers.', type: 'error');
            return;
        }

        $isFirst = count($this->coordinates) === 0;

        $this->coordinates[] = [
            'lat'  => round((float) $lat, 6),
            'lng'  => round((float) $lng, 6),
            'name' => '',
            'type' => $isFirst ? 'parent' : '',
        ];

        $this->mapVersion++;
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd($id, $lat, $lng): void
    {
        if (str_starts_with($id, 'active-')) {
            $index = (int) substr($id, 7);
            if (isset($this->coordinates[$index])) {
                $this->coordinates[$index]['lat'] = round((float) $lat, 6);
                $this->coordinates[$index]['lng'] = round((float) $lng, 6);
                $this->mapVersion++;
            }
        }
    }

    public function store(): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'tenant_id'          => 'required|exists:tenants,id',
            'coordinates'        => 'required|array|min:1|max:' . self::MAX_MARKERS,
            'coordinates.*.lat'  => 'required|numeric|min:-90|max:90',
            'coordinates.*.lng'  => 'required|numeric|min:-180|max:180',
            'coordinates.*.name' => 'nullable|string|max:100',
        ], [
            'coordinates.required' => 'Please place at least one marker on the map.',
            'coordinates.min'      => 'Please place at least one marker on the map.',
            'coordinates.max'      => 'You can place up to ' . self::MAX_MARKERS . ' markers.',
        ]);

        $this->enforceParentRule();

        foreach ($this->coordinates as $index => $coord) {
            if ($index === 0) continue;

            if (empty($coord['type'])) {
                $this->addError('coordinates', 'Please select a category for all sub-locations before saving.');
                return;
            }

            $isValid = collect($this->markerCategories)->contains('key', $coord['type']);
            if (!$isValid) {
                $this->addError('coordinates', 'Invalid category selected for a sub-location.');
                return;
            }
        }

        try {
            DB::transaction(function () {
                Tenant::findOrFail($this->tenant_id)->update([
                    'coordinates' => array_values($this->coordinates),
                ]);
            });

            $this->dispatch('toast', message: 'Locations updated successfully.', type: 'success');

            $this->resetCoordinates();
            $this->tenant_id = '';
            $this->updateMapViewport();
            $this->mapVersion++;

            unset($this->availableTenants, $this->allMappedTenants, $this->stats, $this->activeTenant);
        } catch (\Exception $e) {
            Log::error('Map marker store failed: ' . $e->getMessage(), [
                'tenant_id' => $this->tenant_id,
                'actor_id'  => Auth::id(),
            ]);
            $this->dispatch('toast', message: 'Failed to save locations. Please try again.', type: 'error');
        }
    }

    public function edit(int $id): void
    {
        $this->authorizeSuperAdmin();

        $tenant = Tenant::find($id);
        if (!$tenant) {
            $this->dispatch('toast', message: 'Business not found.', type: 'error');
            return;
        }

        $this->tenant_id = (string) $tenant->id;
        $this->coordinates = $tenant->coordinates ?? [];

        if (!empty($this->coordinates)) {
            $this->enforceParentRule();
            $this->updateMapViewport($this->coordinates[0]);
        } else {
            $this->updateMapViewport();
        }

        $this->mapVersion++;
    }

    public function removeLocation(int $id): void
    {
        $this->authorizeSuperAdmin();

        $tenant = Tenant::find($id);
        if (!$tenant) {
            $this->dispatch('toast', message: 'Business not found.', type: 'error');
            return;
        }

        try {
            DB::transaction(function () use ($tenant) {
                $tenant->update(['coordinates' => null]);
            });

            $this->dispatch('toast', message: "All locations removed for {$tenant->name}.", type: 'success');

            if ($this->tenant_id == $id) {
                $this->resetCoordinates();
                $this->tenant_id = '';
                $this->updateMapViewport();
                $this->mapVersion++;
            }

            unset($this->availableTenants, $this->allMappedTenants, $this->stats, $this->activeTenant);
        } catch (\Exception $e) {
            Log::error('Map marker removal failed: ' . $e->getMessage(), [
                'tenant_id' => $id,
                'actor_id'  => Auth::id(),
            ]);
            $this->dispatch('toast', message: 'Failed to remove locations. Please try again.', type: 'error');
        }
    }

    public function addCoordinate(): void
    {
        $this->authorizeSuperAdmin();

        if (!$this->tenant_id) {
            return;
        }

        if (count($this->coordinates) >= self::MAX_MARKERS) {
            $this->dispatch('toast', message: 'You can add up to ' . self::MAX_MARKERS . ' markers.', type: 'error');
            return;
        }

        $isFirst = count($this->coordinates) === 0;

        $this->coordinates[] = [
            'lat'  => round((float) ($this->mapView['lat'] ?? self::DEFAULT_LAT), 6),
            'lng'  => round((float) ($this->mapView['lng'] ?? self::DEFAULT_LNG), 6),
            'name' => '',
            'type' => $isFirst ? 'parent' : '',
        ];

        $this->mapVersion++;
    }

    public function removeCoordinate(int $index): void
    {
        $this->authorizeSuperAdmin();

        if (!isset($this->coordinates[$index])) {
            return;
        }

        unset($this->coordinates[$index]);
        $this->coordinates = array_values($this->coordinates);

        $this->enforceParentRule();
        $this->mapVersion++;
    }

    public function makeParent(int $index): void
    {
        $this->authorizeSuperAdmin();

        if ($index <= 0 || !isset($this->coordinates[$index])) {
            return;
        }

        $newParent = $this->coordinates[$index];
        unset($this->coordinates[$index]);
        array_unshift($this->coordinates, $newParent);

        $this->enforceParentRule();
        $this->mapVersion++;
    }

    public function resetFields(): void
    {
        $this->reset(['tenant_id']);
        $this->resetCoordinates();
        $this->updateMapViewport();
        $this->mapVersion++;
    }

    public function toggleRadius(): void
    {
        $this->showRadius = !$this->showRadius;
    }

    public function fitAllTenants(): void
    {
        $bounds = $this->getAllBounds();

        if (empty($bounds)) {
            $this->dispatch('toast', message: 'No mapped locations found.', type: 'info');
            return;
        }

        $this->dispatch('map:fit-bounds', bounds: $bounds, padding: 80);
    }

    protected function getAllBounds(): array
    {
        $bounds = [];
        foreach ($this->allMappedTenants as $tenant) {
            foreach ($tenant['markers'] as $coord) {
                if (isset($coord['lat'], $coord['lng'])) {
                    $bounds[] = [(float) $coord['lng'], (float) $coord['lat']];
                }
            }
        }
        return $bounds;
    }

    protected function enforceParentRule(): void
    {
        foreach ($this->coordinates as $i => &$coord) {
            if ($i === 0) {
                $coord['type'] = 'parent';
            } elseif (($coord['type'] ?? '') === 'parent') {
                $coord['type'] = '';
            }
        }
        unset($coord);
    }

    private function resetCoordinates(): void
    {
        $this->coordinates = [];
    }

    private function updateMapViewport(?array $centerCoord = null): void
    {
        if ($centerCoord && isset($centerCoord['lat'], $centerCoord['lng'])) {
            $this->mapView = [
                'lat'  => (float) $centerCoord['lat'],
                'lng'  => (float) $centerCoord['lng'],
                'zoom' => 15,
            ];
        } else {
            $this->mapView = [
                'lat'  => self::DEFAULT_LAT,
                'lng'  => self::DEFAULT_LNG,
                'zoom' => 12,
            ];
        }
    }

    public function getParentRadiusCircle(float $lat, float $lng, int $radiusMeters = 500, int $segments = 64): array
    {
        $earthRadius   = 6371000;
        $lat0          = deg2rad($lat);
        $lng0          = deg2rad($lng);
        $angularRadius = $radiusMeters / $earthRadius;

        $coords = [];
        for ($i = 0; $i <= $segments; $i++) {
            $bearing = 2 * M_PI * $i / $segments;
            $lat1 = asin(sin($lat0) * cos($angularRadius) + cos($lat0) * sin($angularRadius) * cos($bearing));
            $lng1 = $lng0 + atan2(sin($bearing) * sin($angularRadius) * cos($lat0), cos($angularRadius) - sin($lat0) * sin($lat1));
            $coords[] = [rad2deg($lng1), rad2deg($lat1)];
        }

        return $coords;
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto space-y-6"
     x-data="{ toasts: [] }"
     x-on:toast.window="
         const id = Date.now() + Math.random();
         toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
         setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
     ">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Map Markers</h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Select a business, place its main location and nearby establishments on the map.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('superadmin.marker-categories.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-semibold text-xs sm:text-sm shadow-sm transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                Categories
            </a>
            <button type="button" wire:click="fitAllTenants"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-semibold text-xs sm:text-sm shadow-sm transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M4 4l5 5M16 4h4v4M20 4l-5 5M4 16v4h4M4 20l5-5M16 20h4v-4M20 20l-5-5"/></svg>
                Fit All Pins
            </button>
        </div>
    </div>

    {{-- Flash Errors --}}
    @error('coordinates')
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm flex items-center gap-2.5">
            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            {{ $message }}
        </div>
    @enderror

    {{-- Main Grid: map (left) + sidebar (right) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- MAP (LEFT)                                              --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-8 order-1">
            <div class="bg-white dark:bg-gray-800/90
                        border border-gray-200/80 dark:border-gray-700/80
                        rounded-2xl shadow-sm overflow-hidden relative
                        h-[600px] lg:h-[700px]
                        {{ $tenant_id ? 'cursor-crosshair' : '' }}">

                {{-- Radius toggle --}}
                @if($tenant_id)
                    <div class="absolute top-3 left-3 z-[500]">
                        <button type="button" wire:click="toggleRadius"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full
                                       bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm
                                       border border-gray-300 dark:border-gray-700
                                       text-xs font-medium text-gray-700 dark:text-gray-300
                                       shadow-sm hover:bg-white dark:hover:bg-gray-900
                                       transition active:scale-95">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="9" stroke-dasharray="3 3"/>
                            </svg>
                            {{ $showRadius ? 'Hide radius' : 'Show radius' }}
                        </button>
                    </div>
                @endif

                {{-- Idle hint --}}
                @if(!$tenant_id)
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-[500] pointer-events-none">
                        <div class="rounded-full bg-gray-900/85 dark:bg-gray-950/90 backdrop-blur-sm
                                    px-4 py-2 text-xs font-medium text-white shadow-lg">
                            Select a business from the sidebar to edit its markers
                        </div>
                    </div>
                @endif

                <div wire:key="admin-map-{{ $tenant_id ?: 'idle' }}-{{ $mapVersion }}-{{ $showRadius ? 'r' : 'nr' }}"
                     class="absolute inset-0">
                    <x-map
                        id="admin-map"
                        :center="[(float)$mapView['lng'], (float)$mapView['lat']]"
                        :zoom="$mapView['zoom']"
                        height="100%"
                        provider="carto-voyager"
                        theme="auto"
                        class="h-full w-full"
                        :events="['click', 'marker-clicked', 'marker-drag-end']"
                    >
                        <x-map-controls
                            :zoom="true"
                            :compass="true"
                            :locate="false"
                            :fullscreen="true"
                            :scale="true"
                            position="top-right"
                        />

                        @if($tenant_id && !empty($coordinates) && $showRadius)
                            @php
                                $parentCoord = $coordinates[0] ?? null;
                                $circleCoords = $parentCoord
                                    ? $this->getParentRadiusCircle((float)$parentCoord['lat'], (float)$parentCoord['lng'], 500, 64)
                                    : [];
                            @endphp

                            @if(!empty($circleCoords))
                                <x-map-route
                                    wire:key="parent-radius-{{ $tenant_id }}-{{ $mapVersion }}"
                                    :coordinates="$circleCoords"
                                    color="#10b981"
                                    :width="2"
                                    :opacity="0.35"
                                    :dash-array="[4, 4]"
                                />
                            @endif
                        @endif

                        {{-- ═══════════ BROWSE MODE — COLORLESS PINS ═══════════ --}}
                        @if(!$tenant_id)
                            @foreach($this->allMappedTenants as $tenant)
                                @php
                                    $parent = $tenant['markers'][0] ?? null;
                                    if (!$parent) continue;
                                    $count  = count($tenant['markers']);
                                    $hasSub = $count > 1;
                                @endphp
                                <x-map-marker
                                    wire:key="browse-{{ $tenant['id'] }}"
                                    :lat="$parent['lat']"
                                    :lng="$parent['lng']"
                                    color="#94a3b8"
                                    id="browse-{{ $tenant['id'] }}"
                                    anchor="bottom"
                                >
                                    <x-marker-content>
                                        <div class="flex flex-col items-center cursor-pointer group">
                                            <div class="relative flex h-8 w-8 items-center justify-center
                                                        rounded-full
                                                        bg-white dark:bg-gray-800
                                                        border-2 border-slate-300 dark:border-slate-600
                                                        shadow-sm
                                                        transition-all duration-200
                                                        group-hover:scale-110
                                                        group-hover:border-slate-500 dark:group-hover:border-slate-400">
                                                <span class="block w-2.5 h-2.5 rounded-full
                                                             bg-slate-400 dark:bg-slate-500
                                                             transition-colors
                                                             group-hover:bg-slate-600 dark:group-hover:bg-slate-300"></span>

                                                @if($hasSub)
                                                    <span class="absolute -top-1.5 -right-1.5
                                                                 min-w-[16px] h-4 px-1
                                                                 flex items-center justify-center
                                                                 rounded-full
                                                                 bg-slate-500 dark:bg-slate-600 text-white
                                                                 text-[9px] font-bold tabular-nums
                                                                 ring-2 ring-white dark:ring-gray-900">
                                                        {{ $count }}
                                                    </span>
                                                @endif
                                            </div>
                                            <svg class="mt-[-1px] h-1.5 w-2 text-slate-300 dark:text-slate-600"
                                                 viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                                <path d="M0 0 L12 0 L6 8 Z"/>
                                            </svg>
                                        </div>
                                    </x-marker-content>

                                    <x-marker-popup>
                                        <div class="w-56 p-3 rounded-xl bg-white dark:bg-gray-900
                                                    shadow-xl border border-gray-200 dark:border-gray-800">
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                                Not selected
                                            </p>
                                            <strong class="block text-gray-900 dark:text-white text-sm truncate">
                                                {{ $tenant['name'] }}
                                            </strong>
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $count }} pin{{ $count === 1 ? '' : 's' }} placed
                                            </p>
                                            <button type="button"
                                                    wire:click="edit({{ $tenant['id'] }})"
                                                    class="mt-2 w-full rounded-lg
                                                           bg-slate-700 hover:bg-slate-800
                                                           dark:bg-slate-600 dark:hover:bg-slate-500
                                                           px-3 py-1.5 text-xs font-semibold text-white
                                                           transition active:scale-95
                                                           focus-visible:ring-2 focus-visible:ring-slate-500/50">
                                                Edit markers
                                            </button>
                                        </div>
                                    </x-marker-popup>
                                </x-map-marker>
                            @endforeach
                        @endif

                        {{-- ═══════════ FOCUSED MODE ═══════════ --}}
                        @if($tenant_id)
                            {{-- Other tenants: tiny, colorless context dots --}}
                            @foreach($this->allMappedTenants as $tenant)
                                @if((string) $tenant['id'] !== (string) $tenant_id)
                                    @php
                                        $parent = $tenant['markers'][0] ?? null;
                                        if (!$parent) continue;
                                    @endphp
                                    <x-map-marker
                                        wire:key="context-{{ $tenant['id'] }}"
                                        :lat="$parent['lat']"
                                        :lng="$parent['lng']"
                                        color="#cbd5e1"
                                        id="context-{{ $tenant['id'] }}"
                                        anchor="center"
                                    >
                                        <x-marker-content>
                                            <div class="w-2.5 h-2.5 rounded-full
                                                        bg-slate-300 dark:bg-slate-700
                                                        ring-2 ring-white/70 dark:ring-gray-900/70
                                                        opacity-60
                                                        transition-opacity hover:opacity-100"></div>
                                        </x-marker-content>
                                    </x-map-marker>
                                @endif
                            @endforeach

                            {{-- Focused tenant: parent (with LOGO) + sub-markers --}}
                            @foreach($coordinates as $idx => $coord)
                                @php
                                    $isParent = $idx === 0;
                                    $type = $isParent ? 'parent' : ($coord['type'] ?? '');
                                    $category = $isParent ? null : collect($this->markerCategories)->firstWhere('key', $type);
                                    $iconSvg = $isParent ? null : ($category['icon_svg'] ?? null);
                                    $letter = $category ? strtoupper(substr($category['label'], 0, 1)) : '?';
                                    $markerColor = $isParent ? '#10b981' : ($category['color'] ?? '#f97316');
                                @endphp

                                <x-map-marker
                                    wire:key="active-{{ $idx }}-{{ $type }}-{{ $mapVersion }}"
                                    :lat="$coord['lat']"
                                    :lng="$coord['lng']"
                                    :color="$markerColor"
                                    id="active-{{ $idx }}"
                                    draggable
                                >
                                    <x-marker-content>
                                        @if($isParent)
                                            <div class="relative flex flex-col items-center
                                                        transform-gpu will-change-transform
                                                        transition-transform duration-200
                                                        group-hover:scale-105 active:scale-95 cursor: grab">
                                                <div class="relative flex h-14 w-14 items-center justify-center
                                                            rounded-full
                                                            bg-white dark:bg-gray-900
                                                            border-[3px] border-emerald-500
                                                            ring-4 ring-emerald-500/15
                                                            shadow-xl
                                                            overflow-hidden">
                                                    @if($this->activeTenant?->logo)
                                                        <img src="/storage/{{ ltrim($this->activeTenant->logo, '/') }}"
                                                             alt="{{ $this->activeTenant->name }}"
                                                             class="w-full h-full object-cover"
                                                             loading="lazy">
                                                    @else
                                                        <span class="text-base font-bold text-gray-800 dark:text-white">
                                                            {{ strtoupper(substr($this->activeTenant?->name ?? '?', 0, 2)) }}
                                                        </span>
                                                    @endif
                                                </div>

                                                <div class="mt-1 whitespace-nowrap rounded-full
                                                            bg-emerald-600 px-2 py-0.5
                                                            text-[9px] font-bold uppercase tracking-wider text-white
                                                            shadow-md">
                                                    Main
                                                </div>

                                                <svg class="mt-0.5 h-1.5 w-2 text-emerald-600"
                                                     viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                                    <path d="M0 0 L12 0 L6 8 Z"/>
                                                </svg>
                                            </div>
                                        @else
                                            <div class="relative flex h-9 w-9 items-center justify-center
                                                        transform-gpu will-change-transform
                                                        transition-transform duration-200
                                                        group-hover:scale-110 active:scale-95"
                                                 style="cursor: grab;">
                                                <svg class="absolute inset-0 size-9 drop-shadow-md
                                                            fill-white dark:fill-gray-900
                                                            stroke-slate-400 dark:stroke-slate-600 stroke-1"
                                                     viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                </svg>
                                                @if($iconSvg)
                                                    <div class="absolute mb-1 size-[16px] text-gray-800 dark:text-white">
                                                        <x-safe-svg :svg="$iconSvg" class="size-full stroke-current fill-none" />
                                                    </div>
                                                @else
                                                    <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">
                                                        {{ $letter }}
                                                    </span>
                                                @endif
                                            </div>
                                            <svg class="mt-[-1px] h-1.5 w-2"
                                                 style="color: {{ $markerColor }};"
                                                 viewBox="0 0 12 8" fill="currentColor" aria-hidden="true">
                                                <path d="M0 0 L12 0 L6 8 Z"/>
                                            </svg>
                                        @endif
                                    </x-marker-content>

                                    <x-marker-popup>
                                        <div class="p-3 min-w-[200px]">
                                            @if($isParent)
                                                <div class="flex items-center gap-2 mb-2">
                                                    <div class="w-8 h-8 rounded-full overflow-hidden
                                                                bg-gray-100 dark:bg-gray-700
                                                                border border-emerald-300 dark:border-emerald-500/40">
                                                        @if($this->activeTenant?->logo)
                                                            <img src="/storage/{{ ltrim($this->activeTenant->logo, '/') }}"
                                                                 alt=""
                                                                 class="w-full h-full object-cover">
                                                        @else
                                                            <div class="w-full h-full flex items-center justify-center
                                                                        text-[10px] font-bold text-gray-500">
                                                                {{ strtoupper(substr($this->activeTenant?->name ?? '?', 0, 2)) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                                            Main location
                                                        </p>
                                                        <strong class="text-gray-900 dark:text-white text-sm truncate block">
                                                            {{ $coord['name'] ?: $this->activeTenant?->name }}
                                                        </strong>
                                                    </div>
                                                </div>
                                            @else
                                                <strong class="text-gray-900 dark:text-white text-sm">
                                                    {{ $coord['name'] ?: 'Marker ' . ($idx + 1) }}
                                                </strong>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {{ $category['label'] ?? 'Sub-location' }}
                                                </p>
                                            @endif
                                            <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500 font-mono">
                                                {{ number_format((float)$coord['lat'], 5) }}, {{ number_format((float)$coord['lng'], 5) }}
                                            </p>
                                            <p class="mt-2 text-[10px] text-gray-400 dark:text-gray-500 italic">
                                                Drag to move · Edit in sidebar
                                            </p>
                                        </div>
                                    </x-marker-popup>
                                </x-map-marker>
                            @endforeach
                        @endif
                    </x-map>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- SIDEBAR (RIGHT)                                         --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <aside class="lg:col-span-4 order-2">
            <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm
                        flex flex-col overflow-hidden
                        lg:sticky lg:top-24 lg:h-[700px]">

                {{-- ══════ BROWSE MODE ══════ --}}
                @if(!$tenant_id)
                    <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700/60
                                bg-gradient-to-b from-gray-50 to-white dark:from-gray-800 dark:to-gray-800/60">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Businesses</h2>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            Select one to edit its markers.
                        </p>

                        @php $s = $this->stats; @endphp
                        <div class="flex items-center gap-3 mt-2.5 text-[10px]">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                <span class="tabular-nums font-semibold text-gray-700 dark:text-gray-300">{{ $s['mapped'] }} mapped</span>
                            </span>
                            <span class="w-px h-3 bg-gray-300 dark:bg-gray-700"></span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                <span class="tabular-nums font-semibold text-gray-700 dark:text-gray-300">{{ $s['unmapped'] }} pending</span>
                            </span>
                        </div>
                    </div>

                    <div class="px-3 py-3 border-b border-gray-200 dark:border-gray-700/60">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="tenantSearch"
                                placeholder="Search businesses…"
                                autocomplete="off"
                                class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700
                                       rounded-xl py-2 pl-10 pr-3 text-sm text-gray-900 dark:text-white
                                       placeholder-gray-400
                                       focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50
                                       focus:border-primary-500 transition">
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-2 space-y-1">
                        @forelse($this->availableTenants as $t)
                            @php
                                $hasCoords   = !empty($t->coordinates);
                                $markerCount = $hasCoords ? count($t->coordinates) : 0;
                            @endphp
                            <button type="button"
                                    wire:click="edit({{ $t->id }})"
                                    wire:key="tenant-card-{{ $t->id }}"
                                    class="w-full text-left p-2.5 rounded-xl transition group
                                           hover:bg-gray-50 dark:hover:bg-gray-700/50
                                           focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                           active:scale-[0.99]">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0 w-10 h-10 rounded-lg overflow-hidden
                                                bg-gray-100 dark:bg-gray-700
                                                border border-gray-200 dark:border-gray-700">
                                        @if($t->logo)
                                            <img src="/storage/{{ ltrim($t->logo, '/') }}"
                                                 alt=""
                                                 class="w-full h-full object-cover"
                                                 loading="lazy">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center
                                                        text-[10px] font-bold text-gray-500 dark:text-gray-400">
                                                {{ strtoupper(substr($t->name, 0, 2)) }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0
                                                {{ $hasCoords ? 'bg-emerald-500' : 'bg-amber-400' }}"
                                                title="{{ $hasCoords ? 'Mapped' : 'Not yet mapped' }}"></span>
                                            <p class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                                                {{ $t->name }}
                                            </p>
                                        </div>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                            {{ $t->typeOfTenant?->type ?? 'Uncategorized' }}
                                        </p>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <p class="text-[11px] font-bold tabular-nums
                                            {{ $markerCount > 0 ? 'text-gray-700 dark:text-gray-200' : 'text-gray-300 dark:text-gray-600' }}">
                                            {{ $markerCount }}
                                        </p>
                                        <p class="text-[9px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            pins
                                        </p>
                                    </div>

                                    <svg class="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 shrink-0
                                                group-hover:text-primary-500 group-hover:translate-x-0.5 transition"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </button>
                        @empty
                            <div class="text-center py-12 px-4">
                                <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700
                                            flex items-center justify-center mb-3">
                                    <svg class="w-6 h-6 text-gray-400 dark:text-gray-500"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                    No businesses match
                                </p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                    Try a different search term.
                                </p>
                            </div>
                        @endforelse
                    </div>
                @endif

                {{-- ══════ EDIT MODE ══════ --}}
                @if($tenant_id && $this->activeTenant)
                    @php $active = $this->activeTenant; @endphp

                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700/60">
                        <button type="button"
                                wire:click="resetFields"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold
                                       text-gray-500 dark:text-gray-400
                                       hover:text-primary-600 dark:hover:text-primary-400
                                       transition active:scale-95
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                            </svg>
                            All businesses
                        </button>
                    </div>

                    <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700/60
                                bg-gradient-to-b from-primary-50/60 to-transparent
                                dark:from-primary-500/[0.04]">
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 w-12 h-12 rounded-xl overflow-hidden
                                        bg-gray-100 dark:bg-gray-700
                                        border-2 border-white dark:border-gray-800 shadow-sm">
                                @if($active->logo)
                                    <img src="/storage/{{ ltrim($active->logo, '/') }}"
                                         alt=""
                                         class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center
                                                text-sm font-bold text-gray-400 dark:text-gray-500">
                                        {{ strtoupper(substr($active->name, 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider
                                          text-primary-600 dark:text-primary-400">
                                    Editing
                                </p>
                                <p class="text-sm font-bold text-gray-900 dark:text-white truncate">
                                    {{ $active->name }}
                                </p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                    {{ $active->typeOfTenant?->type ?? 'Uncategorized' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <form wire:submit="store" class="flex-1 flex flex-col overflow-hidden">
                        <div class="flex-1 overflow-y-auto p-3 space-y-2">

                            <div class="flex items-center justify-between px-1 pb-1">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Markers
                                </label>
                                <span class="text-[10px] tabular-nums text-gray-400 dark:text-gray-500">
                                    {{ count($coordinates) }}/{{ $this->maxMarkers }}
                                </span>
                            </div>

                            @forelse($coordinates as $index => $coord)
                                @php
                                    $isParent = $index === 0;
                                    $type = $isParent ? 'parent' : ($coord['type'] ?? '');
                                    $category = $isParent ? null : collect($this->markerCategories)->firstWhere('key', $type);
                                @endphp

                                <div wire:key="marker-row-{{ $index }}-{{ $type }}"
                                     class="rounded-xl border p-3 space-y-2
                                        {{ $isParent
                                            ? 'border-primary-200 dark:border-primary-500/40 bg-primary-50/60 dark:bg-primary-500/5'
                                            : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60' }}">

                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            @if($isParent)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                             text-[10px] font-bold uppercase tracking-wider
                                                             bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300
                                                             border border-primary-200 dark:border-primary-500/30">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10"/>
                                                    </svg>
                                                    Main
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                             text-[10px] font-bold uppercase tracking-wider
                                                             bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300
                                                             border border-orange-200 dark:border-orange-500/30">
                                                    Sub
                                                </span>
                                                @if($category)
                                                    <span class="inline-flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                                                        <span class="w-2 h-2 rounded-full" style="background: {{ $category['color'] }}"></span>
                                                        {{ $category['label'] }}
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                        <button type="button"
                                                wire:click="removeCoordinate({{ $index }})"
                                                wire:confirm="{{ $isParent ? 'Remove the parent marker?' : 'Remove this sub-location?' }}"
                                                class="text-gray-400 hover:text-rose-500 transition-colors p-1
                                                       active:scale-95 rounded-md hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                                title="Remove">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <input
                                        type="text"
                                        wire:model.live.debounce.500ms="coordinates.{{ $index }}.name"
                                        placeholder="{{ $isParent ? 'Main location name' : 'Sub-location name' }}"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs
                                               text-gray-900 placeholder-gray-400
                                               focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600/50
                                               dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                    >

                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" readonly value="{{ number_format((float)$coord['lat'], 5) }}"
                                               class="w-full rounded-lg border border-gray-200 bg-gray-100 px-2 py-1.5
                                                      font-mono text-[10px] text-gray-600 cursor-not-allowed
                                                      dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                        <input type="text" readonly value="{{ number_format((float)$coord['lng'], 5) }}"
                                               class="w-full rounded-lg border border-gray-200 bg-gray-100 px-2 py-1.5
                                                      font-mono text-[10px] text-gray-600 cursor-not-allowed
                                                      dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                    </div>

                                    @if($isParent)
                                        <div class="flex items-start gap-2 rounded-lg
                                                    border border-primary-200 dark:border-primary-500/30
                                                    bg-primary-50/70 dark:bg-primary-500/5 px-3 py-2">
                                            <svg class="w-3.5 h-3.5 text-primary-500 dark:text-primary-400 shrink-0 mt-0.5"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <div class="min-w-0">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">
                                                    Logo marker
                                                </p>
                                                <p class="text-[10px] text-primary-600 dark:text-primary-300/80 mt-0.5 leading-snug">
                                                    The main marker displays {{ $active->name }}'s logo on the map.
                                                </p>
                                            </div>
                                        </div>
                                    @else
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                                                Category <span class="text-red-500">*</span>
                                            </label>
                                            <select wire:model.live="coordinates.{{ $index }}.type"
                                                    class="w-full rounded-lg border
                                                           {{ empty($type) ? 'border-rose-300 dark:border-rose-500' : 'border-gray-300 dark:border-gray-600' }}
                                                           bg-white px-2 py-1.5 text-xs text-gray-900
                                                           focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600/50
                                                           dark:bg-gray-900 dark:text-white">
                                                <option value="">Select category *</option>
                                                @foreach($this->markerCategories as $cat)
                                                    <option wire:key="cat-opt-{{ $cat['key'] }}" value="{{ $cat['key'] }}">{{ $cat['label'] }}</option>
                                                @endforeach
                                            </select>
                                            @if(empty($type))
                                                <p class="text-[10px] text-rose-500 dark:text-rose-400 mt-1">Please select a category.</p>
                                            @endif
                                        </div>

                                        <button type="button"
                                                wire:click="makeParent({{ $index }})"
                                                class="w-full text-[11px] font-semibold text-primary-600 dark:text-primary-400
                                                       hover:underline text-left active:scale-[0.99] transition-transform">
                                            ↑ Make this the main marker
                                        </button>
                                    @endif
                                </div>
                            @empty
                                <div class="rounded-xl border-2 border-dashed
                                            border-gray-300 dark:border-gray-600 p-5 text-center">
                                    <div class="mx-auto w-10 h-10 rounded-full
                                                bg-gray-100 dark:bg-gray-700
                                                flex items-center justify-center mb-2">
                                        <svg class="w-5 h-5 text-gray-400 dark:text-gray-500"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                        No markers yet
                                    </p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                        Click on the map to place the main location.
                                    </p>
                                </div>
                            @endforelse

                            <button type="button" wire:click="addCoordinate"
                                    class="w-full py-2 border-2 border-dashed
                                           border-gray-300 dark:border-gray-600
                                           rounded-xl text-xs font-semibold
                                           text-gray-600 dark:text-gray-400
                                           hover:border-primary-500 hover:text-primary-600
                                           dark:hover:border-primary-500 dark:hover:text-primary-400
                                           transition-colors active:scale-[0.99]">
                                + Add marker at map center
                            </button>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700/60 p-3 flex gap-2">
                            <button type="button" wire:click="resetFields"
                                    class="flex-1 px-4 py-2.5 rounded-xl
                                           border border-gray-300 dark:border-gray-700
                                           bg-white dark:bg-gray-900
                                           text-gray-700 dark:text-gray-300
                                           text-sm font-semibold
                                           hover:bg-gray-50 dark:hover:bg-gray-800
                                           transition active:scale-95">
                                Cancel
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    wire:target="store"
                                    class="flex-1 px-4 py-2.5 rounded-xl
                                           bg-primary-600 hover:bg-primary-700
                                           text-white text-sm font-semibold shadow-sm
                                           transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                           active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed
                                           inline-flex items-center justify-center gap-2">
                                <span wire:loading.remove wire:target="store">Save Locations</span>
                                <span wire:loading wire:target="store" class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Saving…
                                </span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </aside>
    </div>

    {{-- Toast Container --}}
    <div class="fixed bottom-4 right-4 z-[2000] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                    'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-300': toast.type === 'warning',
                }">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
</div>