{{-- resources/views/superadmin/pages/map-marker/⚡manage-map-markers.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\Tenant;

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

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedTenantId($value)
    {
        if ($value) {
            $tenant = Tenant::find($value);
            if ($tenant) {
                $this->coordinates = $tenant->coordinates ?? [];
                if (!empty($this->coordinates)) {
                    $this->dispatch('map:fly-to', center: [(float)$this->coordinates[0]['lng'], (float)$this->coordinates[0]['lat']], zoom: 15);
                }
            }
        } else {
            $this->resetCoordinates();
        }
    }

    #[Computed]
    public function availableTenants()
    {
        return Tenant::orderBy('name')
            ->when($this->tenantSearch, fn($q) => $q->where('name', 'like', '%' . $this->tenantSearch . '%'))
            ->select('id', 'name', 'logo')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::with('typeOfTenant')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function allMappedTenants()
    {
        return Tenant::whereNotNull('coordinates')
            ->get()
            ->map(function ($tenant) {
                $coords = $tenant->coordinates;
                if (!$coords) return null;

                return [
                    'id'      => $tenant->id,
                    'name'    => $tenant->name,
                    'slug'    => $tenant->slug,
                    'logo'    => $tenant->logo ? asset('storage/' . $tenant->logo) : null,
                    'markers' => $coords,
                ];
            })
            ->filter()
            ->values();
    }

    #[On('map:click')]
    public function onMapClick($lat, $lng)
    {
        if (!$this->tenant_id) return;

        $this->coordinates[] = [
            'lat'  => round((float) $lat, 6),
            'lng'  => round((float) $lng, 6),
            'name' => '',
            'type' => 'child',
        ];
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd($id, $lat, $lng)
    {
        if (str_starts_with($id, 'active-')) {
            $index = (int) substr($id, 7);
            if (isset($this->coordinates[$index])) {
                $this->coordinates[$index]['lat'] = round((float) $lat, 6);
                $this->coordinates[$index]['lng'] = round((float) $lng, 6);
            }
        }
    }

    public function store()
    {
        $this->validate([
            'tenant_id'         => 'required|exists:tenants,id',
            'coordinates'       => 'required|array|min:1',
            'coordinates.*.lat' => 'required|numeric',
            'coordinates.*.lng' => 'required|numeric',
            'coordinates.*.name' => 'nullable|string',
            'coordinates.*.type' => 'nullable|string|in:parent,child',
        ]);

        Tenant::findOrFail($this->tenant_id)->update([
            'coordinates' => $this->coordinates,
        ]);

        session()->flash('message', 'Locations updated successfully.');
        $this->resetCoordinates();
    }

    public function edit($id)
    {
        $tenant = Tenant::findOrFail($id);
        $this->tenant_id = (string) $tenant->id;
        $this->coordinates = $tenant->coordinates ?? [];

        if (!empty($this->coordinates)) {
            $this->dispatch('map:fly-to', center: [(float)$this->coordinates[0]['lng'], (float)$this->coordinates[0]['lat']], zoom: 15);
        }
    }

    public function removeLocation($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['coordinates' => null]);
        session()->flash('message', "All locations removed for {$tenant->name}.");

        if ($this->tenant_id == $id) {
            $this->resetCoordinates();
        }
    }

    public function addCoordinate()
    {
        $this->coordinates[] = [
            'lat'  => 10.6765,
            'lng'  => 122.9509,
            'name' => '',
            'type' => 'child',
        ];
    }

    public function removeCoordinate($index)
    {
        unset($this->coordinates[$index]);
        $this->coordinates = array_values($this->coordinates);
    }

    public function resetFields()
    {
        $this->reset(['tenant_id']);
        $this->resetCoordinates();
    }

    private function resetCoordinates()
    {
        $this->coordinates = [];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Map Markers</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage business locations, logo markers, and sub‑locations.
            </p>
        </div>

        @if($tenant_id)
            <div class="flex items-center gap-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                <span class="inline-block h-2 w-2 rounded-full bg-primary-600 dark:bg-primary-400"></span>
                Editing: {{ \App\Models\Tenant::find($tenant_id)?->name ?? 'Unknown' }}
            </div>
        @endif
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-lg text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Quick Stats --}}
    @php
        $totalTenants = Tenant::count();
        $mappedTenants = Tenant::whereNotNull('coordinates')->count();
        $unmappedTenants = $totalTenants - $mappedTenants;
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Businesses</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $totalTenants }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mapped</p>
            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ $mappedTenants }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Unmapped</p>
            <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $unmappedTenants }}</p>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Map Column --}}
        <div class="lg:col-span-8 order-2 lg:order-1">
            <div class="card overflow-hidden relative min-h-[650px]">

                {{-- Legend --}}
                <div class="absolute top-4 left-4 z-[1000] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-xs text-gray-700 dark:text-gray-300 shadow-sm space-y-1.5">
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-red-500"></span> Active (draggable)
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-3 w-3 rounded-full bg-gray-400"></span> Other spots
                    </div>
                    @if($tenant_id)
                        <p class="pt-1 text-[10px] text-gray-400 dark:text-gray-500">
                            Click map to add marker
                        </p>
                    @endif
                </div>

                {{-- Map Wrapper --}}
                <div wire:key="admin-map-{{ $tenant_id }}-{{ md5(serialize($coordinates)) }}"
                     class="absolute inset-0">
                    <x-map
                        id="admin-map"
                        :center="[122.9509, 10.6765]"
                        :zoom="12"
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

                        {{-- Static markers for other tenants --}}
                        @foreach($this->allMappedTenants as $tenant)
                            @if($tenant['id'] != $tenant_id)
                                @foreach($tenant['markers'] as $idx => $coord)
                                    @php
                                        $isParent = $coord['type'] === 'parent' || $idx === 0;
                                        $color = '#9ca3af';
                                    @endphp

                                    <x-map-marker
                                        :lat="$coord['lat']"
                                        :lng="$coord['lng']"
                                        color="{{ $color }}"
                                        id="static-{{ $tenant['id'] }}-{{ $idx }}"
                                    >
                                        <x-marker-content>
                                            @if($isParent && $tenant['logo'])
                                                <div class="flex h-11 w-11 items-center justify-center rounded-full border-2 bg-white shadow-lg"
                                                     style="border-color: {{ $color }};">
                                                    <img src="{{ $tenant['logo'] }}" alt="{{ $tenant['name'] }}"
                                                         class="h-full w-full rounded-full object-cover">
                                                </div>
                                            @elseif($isParent)
                                                <div class="flex h-11 w-11 items-center justify-center rounded-full border-2 bg-white shadow-lg"
                                                     style="border-color: {{ $color }};">
                                                    <span class="text-sm font-black text-gray-600">
                                                        {{ strtoupper(substr($tenant['name'], 0, 2)) }}
                                                    </span>
                                                </div>
                                            @else
                                                <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 bg-white shadow"
                                                     style="border-color: {{ $color }};">
                                                    <span class="block h-2.5 w-2.5 rounded-full" style="background: {{ $color }};"></span>
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
                                                <button
                                                    wire:click="edit({{ $tenant['id'] }})"
                                                    class="mt-2 w-full rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700 transition focus-visible:ring-2 focus-visible:ring-primary-500/50"
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
                            @php $activeTenant = \App\Models\Tenant::find($tenant_id); @endphp
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
                                        @if($isParent && $activeTenant?->logo)
                                            <div class="flex h-11 w-11 items-center justify-center rounded-full border-2 bg-white shadow-lg"
                                                 style="border-color: {{ $color }};">
                                                <img src="{{ asset('storage/'.$activeTenant->logo) }}" alt="{{ $activeTenant->name }}"
                                                     class="h-full w-full rounded-full object-cover">
                                            </div>
                                        @elseif($isParent)
                                            <div class="flex h-11 w-11 items-center justify-center rounded-full border-2 bg-white shadow-lg"
                                                 style="border-color: {{ $color }};">
                                                <span class="text-sm font-black text-gray-700">
                                                    {{ strtoupper(substr($activeTenant?->name ?? 'T', 0, 2)) }}
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 bg-white shadow"
                                                 style="border-color: {{ $color }};">
                                                <span class="block h-2.5 w-2.5 rounded-full" style="background: {{ $color }};"></span>
                                            </div>
                                        @endif
                                    </x-marker-content>

                                    <x-marker-popup>
                                        <div class="p-3 min-w-[180px]">
                                            <strong class="text-gray-900 dark:text-white">{{ $coord['name'] ?? 'Marker ' . ($idx + 1) }}</strong>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isParent ? 'Main location' : 'Sub-location' }}</p>
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Business / Tenant
                        </label>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="tenantSearch"
                            placeholder="Search businesses…"
                            class="input mb-2 focus:ring-primary-500/50 focus:border-primary-500"
                        >
                        <select wire:model.live="tenant_id" class="select focus:ring-primary-500/50 focus:border-primary-500">
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
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Markers
                            </label>

                            @foreach($coordinates as $index => $coord)
                                <div class="flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-700/50">
                                    <div class="grid flex-1 grid-cols-2 gap-1">
                                        <div>
                                            <input
                                                type="text"
                                                wire:model.live.debounce.500ms="coordinates.{{ $index }}.lat"
                                                placeholder="Lat"
                                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 placeholder-gray-400 focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600/50 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                            >
                                        </div>
                                        <div>
                                            <input
                                                type="text"
                                                wire:model.live.debounce.500ms="coordinates.{{ $index }}.lng"
                                                placeholder="Lng"
                                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 placeholder-gray-400 focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600/50 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                            >
                                        </div>
                                        <div class="col-span-2">
                                            <input
                                                type="text"
                                                wire:model.live.debounce.500ms="coordinates.{{ $index }}.name"
                                                placeholder="Name (optional)"
                                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 placeholder-gray-400 focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600/50 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:placeholder-gray-500"
                                            >
                                        </div>
                                        <div class="col-span-2">
                                            <select
                                                wire:model.live="coordinates.{{ $index }}.type"
                                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600/50 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                            >
                                                <option value="parent">Parent (main spot)</option>
                                                <option value="child">Child (sub-location)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="removeCoordinate({{ $index }})"
                                        class="mt-1 text-xs text-red-600 hover:text-red-700 dark:text-red-400"
                                    >
                                        ✕
                                    </button>
                                </div>
                            @endforeach

                            <button
                                type="button"
                                wire:click="addCoordinate"
                                class="text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded"
                            >
                                + Add sub-location
                            </button>
                        </div>
                    @endif

                    <div class="flex gap-2 pt-4">
                        <button
                            type="submit"
                            class="btn-primary flex-1 disabled:opacity-50 disabled:cursor-not-allowed"
                            {{ !$tenant_id ? 'disabled' : '' }}
                        >
                            Save Locations
                        </button>
                        <button
                            type="button"
                            wire:click="resetFields"
                            class="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
                            {{ !$tenant_id ? 'disabled' : '' }}
                        >
                            Clear
                        </button>
                    </div>
                </form>
            </div>

            {{-- Tenant List --}}
            <div class="card flex h-[400px] flex-col">
                <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Filter list…"
                        class="input focus:ring-primary-500/50 focus:border-primary-500"
                    >
                </div>

                <div class="flex-1 space-y-3 overflow-y-auto p-4">
                    @forelse ($this->tenants as $tenant)
                        <div
                            wire:click="edit({{ $tenant->id }})"
                            class="cursor-pointer rounded-xl border border-gray-200 p-3 transition-all duration-150 dark:border-gray-700
                                   {{ $tenant_id == $tenant->id
                                       ? 'border-primary-600/60 bg-primary-50 ring-2 ring-primary-600/20 dark:border-primary-500/50 dark:bg-primary-500/10 dark:ring-primary-500/20'
                                       : 'hover:border-primary-600/40 hover:bg-gray-50 dark:hover:border-primary-500/40 dark:hover:bg-gray-800/50' }}"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        @if($tenant->logo)
                                            <img src="{{ asset('storage/'.$tenant->logo) }}"
                                                 alt="{{ $tenant->name }}"
                                                 class="h-8 w-8 shrink-0 rounded-full object-cover border border-gray-200 dark:border-gray-700">
                                        @else
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white">
                                                {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                            </span>
                                        @endif

                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $tenant->name }}
                                            </p>
                                            @if($tenant->typeOfTenant)
                                                <p class="truncate text-[10px] text-gray-500 dark:text-gray-400">
                                                    {{ $tenant->typeOfTenant->type }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    @if($tenant->coordinates)
                                        <span class="mt-1.5 flex items-center text-[10px] font-medium text-green-600 dark:text-green-400">
                                            <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            {{ count($tenant->coordinates) }} marker(s)
                                        </span>
                                    @else
                                        <span class="mt-1.5 flex items-center text-[10px] font-medium text-gray-500 dark:text-gray-400">
                                            <span class="mr-1 h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                            Unmapped
                                        </span>
                                    @endif
                                </div>

                                <div class="flex flex-col items-end gap-1">
                                    <button
                                        wire:click.stop="edit({{ $tenant->id }})"
                                        class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-primary-600 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        title="Edit this business"
                                    >
                                        Edit
                                    </button>

                                    @if($tenant->coordinates)
                                        <button
                                            wire:click.stop="removeLocation({{ $tenant->id }})"
                                            wire:confirm="Remove all pins for this tenant?"
                                            class="text-[10px] text-red-600 hover:text-red-700 hover:underline dark:text-red-400"
                                            title="Remove all markers"
                                        >
                                            Remove
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No businesses found.
                        </div>
                    @endforelse
                </div>

                <div class="rounded-b-2xl border-t border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                    {{ $this->tenants->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        </div>
    </div>
</div>