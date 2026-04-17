<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\PropertyType;
use App\Models\Tenant;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Global Property Types')]
class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterScope = 'all';
    public ?int $filterTenant = null;
    public array $expandedTypes = [];

    public function updatingSearch() { $this->resetPage(); }
    public function updatedFilterScope() { $this->resetPage(); }
    public function updatedFilterTenant() { $this->resetPage(); }

    public function toggleExpand(int $typeId)
    {
        if (in_array($typeId, $this->expandedTypes)) {
            $this->expandedTypes = array_diff($this->expandedTypes, [$typeId]);
        } else {
            $this->expandedTypes[] = $typeId;
        }
    }

    public function delete(int $id)
    {
        $type = PropertyType::withCount('properties')->findOrFail($id);
        $typeName = $type->name;
        $isGlobal = is_null($type->tenant_id);
        
        $type->delete();
        
        session()->flash('success', $isGlobal 
            ? "Global type '{$typeName}' deleted." 
            : "Custom type '{$typeName}' from '{$type->tenant->name}' deleted."
        );
    }

    public function clearFilters()
    {
        $this->reset(['search', 'filterScope', 'filterTenant']);
        $this->resetPage();
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::orderBy('name')->get();
    }

    #[Computed]
    public function types()
    {
        return PropertyType::with(['tenant', 'properties.tenant'])
            ->withCount('properties')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->filterScope === 'global', fn($q) => $q->whereNull('tenant_id'))
            ->when($this->filterScope === 'tenant', fn($q) => $q->whereNotNull('tenant_id'))
            ->when($this->filterTenant, fn($q) => $q->where('tenant_id', $this->filterTenant))
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function stats()
    {
        return [
            'total' => PropertyType::count(),
            'global' => PropertyType::whereNull('tenant_id')->count(),
            'custom' => PropertyType::whereNotNull('tenant_id')->count(),
        ];
    }
};
?>

<div class="p-6 sm:p-10 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Property Type Standards</h1>
            <p class="text-slate-500 mt-1">Define global categories available to all businesses. View and manage tenant-specific custom types.</p>
        </div>
        <a href="{{ route('superadmin.property-types.create') }}" wire:navigate class="bg-amber-500 hover:bg-amber-600 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Global Type
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Total Types</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $this->stats['total'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"></path></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Global Standards</p>
                    <p class="text-2xl font-bold text-amber-600">{{ $this->stats['global'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"></path></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Tenant Custom</p>
                    <p class="text-2xl font-bold text-indigo-600">{{ $this->stats['custom'] }}</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
        <div class="flex flex-col md:flex-row gap-3">
            <div class="relative flex-1">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search types..." 
                       class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-amber-500 focus:border-amber-500">
                <svg class="absolute left-3 top-2.5 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterScope" class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-amber-500 focus:border-amber-500 text-sm">
                    <option value="all">All Scopes</option>
                    <option value="global">Global Only</option>
                    <option value="tenant">Tenant Custom Only</option>
                </select>
                <select wire:model.live="filterTenant" class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-amber-500 focus:border-amber-500 text-sm">
                    <option value="">All Tenants</option>
                    @foreach($this->tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                    @endforeach
                </select>
                @if($search || $filterScope !== 'all' || $filterTenant)
                    <button wire:click="clearFilters" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-800 border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                        Clear
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Type Name</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Scope / Owner</th>
                        <th class="px-6 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Properties</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($this->types as $type)
                        <tr class="hover:bg-slate-50 transition-colors {{ in_array($type->id, $expandedTypes) ? 'bg-slate-50' : '' }}">
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-800">{{ $type->name }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if(is_null($type->tenant_id))
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"></path></svg>
                                        Global Standard
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        {{ $type->tenant->name ?? 'Unknown' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button wire:click="toggleExpand({{ $type->id }})" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                                    {{ $type->properties_count }} {{ Str::plural('property', $type->properties_count) }}
                                    <svg class="w-3 h-3 transition-transform {{ in_array($type->id, $expandedTypes) ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(is_null($type->tenant_id))
                                        <a href="{{ route('superadmin.property-types.edit', $type->id) }}" wire:navigate class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit global standard">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                    @endif
                                    <button wire:click="delete({{ $type->id }})" 
                                            wire:confirm="{{ is_null($type->tenant_id) ? 'Delete this global standard? It will be removed for all tenants.' : 'Delete this tenant custom type?' }}" 
                                            class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        {{-- Expanded Properties Row --}}
                        @if(in_array($type->id, $expandedTypes))
                            <tr>
                                <td colspan="4" class="px-6 py-4 bg-slate-50 border-t border-slate-200">
                                    <div class="pl-4">
                                        <h4 class="text-sm font-semibold text-slate-700 mb-3">Properties using "{{ $type->name }}"</h4>
                                        @if($type->properties->count() > 0)
                                            <div class="grid grid-cols-1 gap-2">
                                                @foreach($type->properties as $property)
                                                    <div class="flex items-center justify-between p-2 bg-white rounded-lg border border-slate-200">
                                                        <div>
                                                            <span class="font-medium text-slate-800">{{ $property->name }}</span>
                                                            <span class="text-xs text-slate-500 ml-2">({{ $property->tenant->name ?? 'Unknown' }})</span>
                                                        </div>
                                                        <span class="text-xs px-2 py-1 rounded-full {{ $property->status === 'available' ? 'bg-green-100 text-green-700' : ($property->status === 'occupied' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                                            {{ ucfirst($property->status) }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-sm text-slate-500">No properties assigned to this type yet.</p>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span class="text-slate-500">No property types found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->types->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{ $this->types->links() }}
            </div>
        @endif
    </div>
</div>