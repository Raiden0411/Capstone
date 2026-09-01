{{-- resources/views/superadmin/pages/map-marker/⚡manage-map-markers.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\Tenant;
use App\Models\SiteSetting;

new
#[Layout('superadmin.layouts.app')]
#[Title('Map Markers')]
class extends Component
{
    use WithPagination;

    public string $tenant_id = '';
    public array $coordinates = [];
    public string $search = '';
    public string $tenantSearch = '';
    public bool $showRadius = true;

    public array $mapView = [];
    public int $mapVersion = 0;

    // Dynamic marker categories loaded from site settings
    public array $markerCategories = [];

    public function mount()
    {
        $this->markerCategories = SiteSetting::getValue('marker_categories', []);
        $this->updateMapViewport();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedTenantId($value)
    {
        if ($value && $tenant = Tenant::find($value)) {
            $this->coordinates = $tenant->coordinates ?? [];
            if (!empty($this->coordinates)) {
                $this->coordinates[0]['type'] = 'parent';
                $this->updateMapViewport($this->coordinates[0]);
            } else {
                $this->updateMapViewport();
            }
        } else {
            $this->resetCoordinates();
            $this->updateMapViewport();
        }
        $this->mapVersion++;
    }

    public function updated($property)
    {
        // When a marker type changes, force map re-render to update the icon
        if (preg_match('/^coordinates\.\d+\.type$/', $property)) {
            $this->mapVersion++;
        }
    }

    #[Computed]
    public function availableTenants()
    {
        return Tenant::query()
            ->select('id', 'name', 'logo')
            ->orderBy('name')
            ->when($this->tenantSearch, fn($q) => $q->where('name', 'like', '%' . $this->tenantSearch . '%'))
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::query()
            ->select('id', 'name', 'coordinates')
            ->with('typeOfTenant:id,type')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function allMappedTenants()
    {
        return Tenant::query()
            ->select('id', 'name', 'slug', 'logo', 'coordinates')
            ->whereNotNull('coordinates')
            ->get()
            ->map(function ($tenant) {
                if (!$tenant->coordinates) return null;
                return [
                    'id'      => $tenant->id,
                    'name'    => $tenant->name,
                    'slug'    => $tenant->slug,
                    'logo'    => $tenant->logo ? asset('storage/' . $tenant->logo) : null,
                    'markers' => $tenant->coordinates,
                ];
            })
            ->filter()
            ->values();
    }

    #[Computed]
    public function stats()
    {
        $totalTenants = Tenant::count();
        $mappedTenants = Tenant::whereNotNull('coordinates')->count();
        return [
            'total'   => $totalTenants,
            'mapped'  => $mappedTenants,
            'unmapped'=> $totalTenants - $mappedTenants,
        ];
    }

    #[Computed]
    public function activeTenantName()
    {
        if (!$this->tenant_id) return null;
        return Tenant::query()->where('id', $this->tenant_id)->value('name');
    }

    #[On('map:click')]
    public function onMapClick($lat, $lng)
    {
        if (!$this->tenant_id) return;

        $this->coordinates[] = [
            'lat'  => round((float) $lat, 6),
            'lng'  => round((float) $lng, 6),
            'name' => '',
            'type' => '',
        ];
        $this->mapVersion++;
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd($id, $lat, $lng)
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

    public function store()
    {
        $this->validate([
            'tenant_id'          => 'required|exists:tenants,id',
            'coordinates'        => 'required|array|min:1',
            'coordinates.*.lat'  => 'required|numeric',
            'coordinates.*.lng'  => 'required|numeric',
            'coordinates.*.name' => 'nullable|string',
        ]);

        foreach ($this->coordinates as $index => $coord) {
            if ($index === 0) {
                $this->coordinates[$index]['type'] = 'parent';
            } else {
                if (empty($coord['type'])) {
                    session()->flash('error', 'Please select a category for all sub‑locations before saving.');
                    return;
                }
                $isValidCategory = collect($this->markerCategories)->contains('key', $coord['type']);
                if (!$isValidCategory) {
                    session()->flash('error', 'Invalid category selected for a sub‑location.');
                    return;
                }
            }
        }

        Tenant::findOrFail($this->tenant_id)->update([
            'coordinates' => array_values($this->coordinates),
        ]);

        session()->flash('message', 'Locations updated successfully.');
        $this->resetCoordinates();
        $this->tenant_id = '';
        $this->updateMapViewport();
        $this->mapVersion++;
    }

    public function edit($id)
    {
        $tenant = Tenant::findOrFail($id);
        $this->tenant_id = (string) $tenant->id;
        $this->coordinates = $tenant->coordinates ?? [];

        if (!empty($this->coordinates)) {
            $this->coordinates[0]['type'] = 'parent';
            $this->updateMapViewport($this->coordinates[0]);
        } else {
            $this->updateMapViewport();
        }
        $this->mapVersion++;
    }

    public function removeLocation($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['coordinates' => null]);
        session()->flash('message', "All locations removed for {$tenant->name}.");

        if ($this->tenant_id == $id) {
            $this->resetCoordinates();
            $this->tenant_id = '';
            $this->updateMapViewport();
            $this->mapVersion++;
        }
    }

    public function addCoordinate()
    {
        $this->coordinates[] = [
            'lat'  => round($this->mapView['lat'], 6),
            'lng'  => round($this->mapView['lng'], 6),
            'name' => '',
            'type' => '',
        ];
        $this->mapVersion++;
    }

    public function removeCoordinate($index)
    {
        unset($this->coordinates[$index]);
        $this->coordinates = array_values($this->coordinates);
        
        if (!empty($this->coordinates)) {
            $this->coordinates[0]['type'] = 'parent';
        }
        $this->mapVersion++;
    }

    public function makeParent($index)
    {
        if ($index > 0 && isset($this->coordinates[$index])) {
            $newParent = $this->coordinates[$index];
            unset($this->coordinates[$index]);
            array_unshift($this->coordinates, $newParent);

            foreach ($this->coordinates as $key => &$coord) {
                $coord['type'] = $key === 0 ? 'parent' : $coord['type'];
            }
            $this->mapVersion++;
        }
    }

    public function resetFields()
    {
        $this->reset(['tenant_id']);
        $this->resetCoordinates();
        $this->updateMapViewport();
        $this->mapVersion++;
    }

    public function toggleRadius()
    {
        $this->showRadius = !$this->showRadius;
    }

    public function fitAllTenants()
    {
        $bounds = $this->getAllBounds();
        if (!empty($bounds)) {
            $this->dispatch('map:fit-bounds', bounds: $bounds, padding: 80);
        }
    }

    protected function getAllBounds(): array
    {
        $bounds = [];
        foreach ($this->allMappedTenants as $tenant) {
            foreach ($tenant['markers'] as $coord) {
                $bounds[] = [(float) $coord['lng'], (float) $coord['lat']];
            }
        }
        return $bounds;
    }

    private function resetCoordinates()
    {
        $this->coordinates = [];
    }

    private function updateMapViewport(?array $centerCoord = null)
    {
        if ($centerCoord) {
            $this->mapView = [
                'lat' => (float) $centerCoord['lat'],
                'lng' => (float) $centerCoord['lng'],
                'zoom' => 15,
            ];
        } else {
            $this->mapView = [
                'lat' => 10.900977766937142,
                'lng' => 123.07055771888716,
                'zoom' => 12,
            ];
        }
    }

    public function getParentRadiusCircle($lat, $lng, $radiusMeters = 500, $segments = 64)
    {
        $earthRadius = 6371000;
        $lat0 = deg2rad($lat);
        $lng0 = deg2rad($lng);
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

<div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Map Markers</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage business locations, parent spots, and child sub‑locations.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('superadmin.marker-categories.index') }}" wire:navigate
               class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Manage Categories
            </a>
            <button type="button" wire:click="fitAllTenants"
                    class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M4 4l5 5M16 4h4v4M20 4l-5 5M4 16v4h4M4 20l5-5M16 20h4v-4M20 20l-5-5"/></svg>
                Fit All Pins
            </button>
            @if($tenant_id)
                <span class="text-sm font-medium text-primary-600 dark:text-primary-400">
                    Editing: {{ $this->activeTenantName ?? 'Unknown' }}
                </span>
            @endif
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-lg text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-lg text-sm text-red-700 dark:text-red-300 font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Quick Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Businesses</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['total'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mapped</p>
            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['mapped'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unmapped</p>
            <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $this->stats['unmapped'] }}</p>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Map Column --}}
        <div class="lg:col-span-8 order-2 lg:order-1">
            <div class="card overflow-hidden relative min-h-[650px] {{ $tenant_id ? 'cursor-crosshair' : '' }}">

                {{-- Toggle Radius --}}
                <div class="absolute bottom-4 left-4 z-[1000]">
                    <button type="button" wire:click="toggleRadius"
                            class="px-3 py-1.5 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-xs font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95">
                        {{ $showRadius ? 'Hide Radius' : 'Show Radius' }}
                    </button>
                </div>

                {{-- Map Wrapper --}}
                <div wire:key="admin-map-{{ $tenant_id }}-{{ $mapVersion }}-{{ $mapView['zoom'] }}" class="absolute inset-0">
                    <x-map
                        id="admin-map"
                        :center="[(float)$mapView['lng'], (float)$mapView['lat']]"
                        :zoom="$mapView['zoom']"
                        height="100%"
                        provider="carto-voyager"
                        theme="auto"
                        class="h-full w-full"
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
                                if ($parentCoord) {
                                    $circleCoords = $this->getParentRadiusCircle($parentCoord['lat'], $parentCoord['lng'], 500, 64);
                                }
                            @endphp
                            @if(isset($circleCoords) && count($circleCoords) > 0)
                                <x-map-route
                                    wire:key="parent-radius-{{ $tenant_id }}"
                                    :coordinates="$circleCoords"
                                    color="#ef4444"
                                    :width="2"
                                    :opacity="0.25"
                                    :dash-array="[4, 4]"
                                />
                            @endif
                        @endif

                        {{-- Static markers for other tenants --}}
                        @foreach($this->allMappedTenants as $tenant)
                            @if($tenant['id'] != $tenant_id)
                                @foreach($tenant['markers'] as $idx => $coord)
                                    @php
                                        $isParent = ($coord['type'] ?? '') === 'parent' || $idx === 0;
                                        $color = '#9ca3af';
                                    @endphp

                                    <x-map-marker
                                        :lat="$coord['lat']"
                                        :lng="$coord['lng']"
                                        color="{{ $color }}"
                                        id="static-{{ $tenant['id'] }}-{{ $idx }}"
                                    >
                                        <x-marker-content>
                                            @if($isParent)
                                                <div class="relative flex items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95">
                                                    <svg class="h-10 w-10 drop-shadow-lg" viewBox="0 0 24 24" fill="#9ca3af" stroke="white" stroke-width="1.5">
                                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                                        <circle cx="12" cy="9" r="2.5" fill="white"/>
                                                    </svg>
                                                </div>
                                            @else
                                                @php
                                                    $type = $coord['type'] ?? '';
                                                    $category = collect($this->markerCategories)->firstWhere('key', $type);
                                                    $iconSvg = $category['icon_svg'] ?? null;
                                                    $fallbackLetter = $category ? strtoupper(substr($category['label'], 0, 1)) : '?';
                                                @endphp
                                                <div class="relative flex h-10 w-10 items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95"
                                                     style="cursor: pointer;">
                                                    <svg class="absolute inset-0 size-10 drop-shadow-md fill-white dark:fill-gray-900 stroke-slate-400 dark:stroke-slate-600 stroke-1" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                    </svg>
                                                    @if($iconSvg)
                                                        <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                            {!! str_replace('<svg ', '<svg class="size-full stroke-current fill-none" ', $iconSvg) !!}
                                                        </div>
                                                    @else
                                                        <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">{{ $fallbackLetter }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </x-marker-content>

                                        <x-marker-popup>
                                            <div class="p-3 min-w-[220px]">
                                                <div class="flex items-center gap-2 mb-2">
                                                    @if($tenant['logo'])
                                                        <img src="{{ $tenant['logo'] }}" alt="{{ $tenant['name'] }}"
                                                             class="h-8 w-8 rounded-full object-cover">
                                                    @endif
                                                    <div class="min-w-0">
                                                        <strong class="block truncate text-gray-900 dark:text-white">{{ $tenant['name'] }}</strong>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $coord['name'] ?? '' }}</p>
                                                    </div>
                                                </div>
                                                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $isParent ? 'Main location' : 'Sub-location' }}</p>
                                                <button type="button"
                                                        wire:click="edit({{ $tenant['id'] }})"
                                                        class="mt-2 w-full rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                                >
                                                    Edit this business
                                                </button>
                                            </div>
                                        </x-marker-popup>
                                    </x-map-marker>
                                @endforeach
                            @endif
                        @endforeach

                        {{-- Active tenant markers --}}
                        @if($tenant_id)
                            @foreach($coordinates as $idx => $coord)
                                @php
                                    $isParent = ($coord['type'] ?? 'child') === 'parent' || $idx === 0;
                                    $color = $isParent ? '#ef4444' : '#f97316';
                                @endphp

                                <x-map-marker
                                    :lat="$coord['lat']"
                                    :lng="$coord['lng']"
                                    color="{{ $color }}"
                                    id="active-{{ $idx }}"
                                    draggable
                                >
                                    <x-marker-content>
                                        @if($isParent)
                                            <div class="relative flex items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95">
                                                <svg class="h-10 w-10 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5">
                                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                                    <circle cx="12" cy="9" r="2.5" fill="white"/>
                                                </svg>
                                            </div>
                                        @else
                                            @php
                                                $type = $coord['type'] ?? '';
                                                $category = collect($this->markerCategories)->firstWhere('key', $type);
                                                $iconSvg = $category['icon_svg'] ?? null;
                                                $markerColor = $category['color'] ?? '#f97316';
                                                $fallbackLetter = $category ? strtoupper(substr($category['label'], 0, 1)) : '?';
                                            @endphp
                                            <div class="relative flex h-10 w-10 items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95"
                                                 style="cursor: pointer;">
                                                <svg class="absolute inset-0 size-10 drop-shadow-md fill-white dark:fill-gray-900 stroke-slate-400 dark:stroke-slate-600 stroke-1" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                </svg>
                                                @if($iconSvg)
                                                    <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                        {!! str_replace('<svg ', '<svg class="size-full stroke-current fill-none" ', $iconSvg) !!}
                                                    </div>
                                                @else
                                                    <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">{{ $fallbackLetter }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </x-marker-content>

                                    <x-marker-popup>
                                        <div class="p-3 min-w-[180px]">
                                            <strong class="text-gray-900 dark:text-white">{{ $coord['name'] ?? 'Marker ' . ($idx + 1) }}</strong>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isParent ? 'Main location' : ($category['label'] ?? 'Sub-location') }}</p>
                                            <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">
                                                {{ $coord['lat'] }}, {{ $coord['lng'] }}
                                            </p>
                                            <p class="mt-2 text-[10px] text-gray-400 dark:text-gray-500">
                                                Drag to move · Edit in form to remove
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

        {{-- Sidebar Column --}}
        <div class="lg:col-span-4 order-1 lg:order-2 space-y-6">

            {{-- Location Setter --}}
            <div class="card p-6">
                <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Set Locations
                </h2>

                <form wire:submit="store" class="space-y-4">
                    <div>
                        <label for="tenantSearch" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Business / Tenant
                        </label>
                        <input
                            id="tenantSearch"
                            type="text"
                            wire:model.live.debounce.300ms="tenantSearch"
                            placeholder="Search businesses…"
                            class="input mb-2"
                        >
                        <select wire:model.live="tenant_id" class="select">
                            <option value="">-- Select a business --</option>
                            @foreach($this->availableTenants as $tenantOption)
                                <option value="{{ $tenantOption->id }}">{{ $tenantOption->name }}</option>
                            @endforeach
                        </select>
                        @error('tenant_id')
                            <span class="mt-1 block text-xs text-red-500 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    @if($tenant_id)
                        <div class="space-y-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Markers <span class="text-xs text-gray-400 font-normal">(first is always parent)</span>
                            </label>

                            @foreach($coordinates as $index => $coord)
                                <div class="flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-700/50">
                                    <div class="grid flex-1 grid-cols-2 gap-2">
                                        <div>
                                            <input
                                                type="text"
                                                wire:model="coordinates.{{ $index }}.lat"
                                                readonly
                                                placeholder="Lat"
                                                class="w-full rounded-md border border-gray-300 bg-gray-100 px-2 py-1.5 text-xs text-gray-900 placeholder-gray-400 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500 cursor-not-allowed"
                                            >
                                        </div>
                                        <div>
                                            <input
                                                type="text"
                                                wire:model="coordinates.{{ $index }}.lng"
                                                readonly
                                                placeholder="Lng"
                                                class="w-full rounded-md border border-gray-300 bg-gray-100 px-2 py-1.5 text-xs text-gray-900 placeholder-gray-400 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500 cursor-not-allowed"
                                            >
                                        </div>
                                        <div class="col-span-2">
                                            <input
                                                type="text"
                                                wire:model.live.debounce.500ms="coordinates.{{ $index }}.name"
                                                placeholder="Location Name (optional)"
                                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-900 placeholder-gray-400 focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600/50 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                            >
                                        </div>

                                        @if($index > 0)
                                            <div class="col-span-2">
                                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Icon / Category</label>
                                                <select wire:model.live="coordinates.{{ $index }}.type" class="select w-full !py-2 text-xs">
                                                    <option value="">-- Select category --</option>
                                                    @foreach($this->markerCategories as $cat)
                                                        <option value="{{ $cat['key'] }}">{{ $cat['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif

                                        <div class="col-span-2 flex items-center justify-between mt-1">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset
                                                    {{ $index === 0
                                                        ? 'bg-red-50 text-red-700 ring-red-600/10 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/20'
                                                        : 'bg-orange-50 text-orange-700 ring-orange-600/10 dark:bg-orange-400/10 dark:text-orange-400 dark:ring-orange-400/20' }}">
                                                    {{ $index === 0 ? 'Parent Spot' : 'Sub-location' }}
                                                </span>
                                                @if($index > 0)
                                                    <button
                                                        type="button"
                                                        wire:click="makeParent({{ $index }})"
                                                        class="shrink-0 text-[11px] font-medium text-primary-600 hover:underline dark:text-primary-400 active:scale-95 transition-transform"
                                                    >
                                                        Make parent
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <button 
                                        type="button" 
                                        wire:click="removeCoordinate({{ $index }})" 
                                        class="text-gray-400 hover:text-red-500 transition-colors p-1 active:scale-95"
                                        title="Remove Marker"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            @endforeach

                            <button 
                                type="button" 
                                wire:click="addCoordinate" 
                                class="w-full py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-500 dark:text-gray-400 hover:border-primary-500 hover:text-primary-600 dark:hover:border-primary-500 dark:hover:text-primary-400 transition-colors active:scale-95"
                            >
                                + Add Marker Center Screen
                            </button>
                        </div>
                    @endif

                    @if($tenant_id)
                        <div class="pt-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 border-t border-gray-200 dark:border-gray-700">
                            <button 
                                type="button" 
                                wire:click="resetFields" 
                                class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit" 
                                class="btn-primary w-full sm:w-auto active:scale-95 transition-transform"
                            >
                                Save Locations
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>