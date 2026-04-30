<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Booking;
use App\Models\Customer;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new 
#[Layout('tenant.layouts.app')]
#[Title('Manage Properties')]
class extends Component {
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';
    public string $statusFilter = '';
    public array $selectedProperties = [];
    public bool $selectAll = false;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingTypeFilter() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedProperties = $this->properties->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedProperties = [];
        }
    }

    public function updateStatus($id, $newStatus)
    {
        $property = Property::findOrFail($id);
        $property->update(['status' => $newStatus]);
        session()->flash('message', "Property '{$property->name}' status updated to " . ucfirst($newStatus) . '.');
    }

    public function toggleActive($id)
    {
        $property = Property::findOrFail($id);
        $property->update(['is_active' => !$property->is_active]);
        session()->flash('message', "Property '{$property->name}' " . ($property->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function delete($id)
    {
        $property = Property::findOrFail($id);
        $propertyName = $property->name;
        $property->delete();
        session()->flash('message', "Property '{$propertyName}' deleted.");
    }

    public function bulkActivate()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No properties selected.');
            return;
        }
        Property::whereIn('id', $this->selectedProperties)->update(['is_active' => true]);
        $this->selectedProperties = [];
        $this->selectAll = false;
        session()->flash('message', count($this->selectedProperties) . ' properties activated.');
    }

    public function bulkDeactivate()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No properties selected.');
            return;
        }
        Property::whereIn('id', $this->selectedProperties)->update(['is_active' => false]);
        $this->selectedProperties = [];
        $this->selectAll = false;
        session()->flash('message', count($this->selectedProperties) . ' properties deactivated.');
    }

    public function bulkChangeStatus($newStatus)
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No properties selected.');
            return;
        }
        Property::whereIn('id', $this->selectedProperties)->update(['status' => $newStatus]);
        $this->selectedProperties = [];
        $this->selectAll = false;
        session()->flash('message', count($this->selectedProperties) . " properties marked as " . ucfirst($newStatus) . '.');
    }

    public function bulkDelete()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No properties selected.');
            return;
        }
        Property::whereIn('id', $this->selectedProperties)->delete();
        $this->selectedProperties = [];
        $this->selectAll = false;
        session()->flash('message', count($this->selectedProperties) . ' properties deleted.');
    }

    public function clearFilters()
    {
        $this->reset(['search', 'typeFilter', 'statusFilter']);
        $this->resetPage();
    }

    #[Computed]
    public function propertyTypes()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function properties()
    {
        return Property::query()
            ->with([
                'propertyType' => function ($query) {
                    $query->withoutGlobalScope(TenantScope::class);
                },
                'images'
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, function ($query) {
                $query->where('property_type_id', $this->typeFilter);
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('name')
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        return [
            'total' => Property::count(),
            'available' => Property::where('status', 'available')->count(),
            'occupied' => Property::where('status', 'occupied')->count(),
            'maintenance' => Property::where('status', 'maintenance')->count(),
        ];
    }

    #[Computed]
    public function activeBookings()
    {
        return Booking::where('tenant_id', Auth::user()->tenant_id)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('check_in', '<=', now())
            ->where('check_out', '>', now())
            ->with(['items', 'customer'])
            ->get()
            ->flatMap(function ($booking) {
                return $booking->items->map(function ($item) use ($booking) {
                    return [
                        'property_id' => $item->property_id,
                        'guest_name'  => $booking->customer->name ?? 'N/A',
                        'check_out'   => $booking->check_out->format('M d, Y'),
                    ];
                });
            })
            ->groupBy('property_id');
    }
};
?>

<div class="p-6 sm:p-10 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Properties</h1>
            <p class="text-slate-500">Manage your rooms, cottages, and other rentable items.</p>
        </div>
        <a href="{{ route('tenant.properties.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Property
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-sm font-medium text-slate-500">Total</p>
            <p class="text-2xl font-bold text-slate-800">{{ $this->stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-sm font-medium text-green-600">Available</p>
            <p class="text-2xl font-bold text-green-600">{{ $this->stats['available'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-sm font-medium text-amber-600">Occupied</p>
            <p class="text-2xl font-bold text-amber-600">{{ $this->stats['occupied'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-sm font-medium text-red-600">Maintenance</p>
            <p class="text-2xl font-bold text-red-600">{{ $this->stats['maintenance'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
            <div class="relative flex-1">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or description..." 
                       class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <svg class="absolute left-3 top-2.5 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <select wire:model.live="typeFilter" class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">All Types</option>
                    @foreach($this->propertyTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                @if($search || $typeFilter || $statusFilter !== '')
                    <button wire:click="clearFilters" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-800 border border-slate-300 rounded-lg hover:bg-slate-50 transition">
                        Clear
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Bulk Actions --}}
    @if(count($selectedProperties) > 0)
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm text-blue-800 font-medium">{{ count($selectedProperties) }} selected</span>
        <div class="flex flex-wrap gap-2">
            <button wire:click="bulkActivate" class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded shadow-sm transition">Activate</button>
            <button wire:click="bulkDeactivate" class="text-xs bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1.5 rounded shadow-sm transition">Deactivate</button>
            <button wire:click="bulkChangeStatus('available')" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded shadow-sm transition">Set Available</button>
            <button wire:click="bulkChangeStatus('occupied')" class="text-xs bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded shadow-sm transition">Set Occupied</button>
            <button wire:click="bulkChangeStatus('maintenance')" class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded shadow-sm transition">Set Maintenance</button>
            <button wire:click="bulkDelete" wire:confirm="Delete selected properties? This cannot be undone." class="text-xs bg-red-700 hover:bg-red-800 text-white px-3 py-1.5 rounded shadow-sm transition">Delete</button>
            <button wire:click="$set('selectedProperties', [])" class="text-xs text-slate-600 hover:text-slate-800 px-3 py-1.5 border border-slate-300 rounded hover:bg-slate-50 transition">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Properties Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-4 w-10">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Property</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Capacity</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status / Guest</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($this->properties as $property)
                        <tr class="hover:bg-slate-50 transition-colors">
                            {{-- Checkbox --}}
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedProperties" value="{{ $property->id }}" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            </td>

                            {{-- Thumbnail + Name --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0 h-10 w-10 rounded-lg bg-slate-100 overflow-hidden">
                                        @if($property->images->isNotEmpty())
                                            <img src="{{ Storage::url($property->images->first()->image_path) }}" alt="{{ $property->name }}" class="h-full w-full object-cover">
                                        @else
                                            <div class="h-full w-full flex items-center justify-center text-slate-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-medium text-slate-800">{{ $property->name }}</div>
                                        <div class="text-xs text-slate-500 truncate max-w-[200px]">{{ $property->description ?: 'No description' }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $property->propertyType->name ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $property->capacity }} persons
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                ₱{{ number_format($property->price, 2) }}
                            </td>

                            {{-- Status + Guest Info --}}
                            <td class="px-6 py-4">
                                <select wire:change="updateStatus({{ $property->id }}, $event.target.value)" 
                                        class="text-xs rounded-full px-2 py-1 border-0 font-medium w-28
                                            {{ $property->status === 'available' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $property->status === 'occupied' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $property->status === 'maintenance' ? 'bg-red-100 text-red-800' : '' }}">
                                    <option value="available" {{ $property->status === 'available' ? 'selected' : '' }}>Available</option>
                                    <option value="occupied" {{ $property->status === 'occupied' ? 'selected' : '' }}>Occupied</option>
                                    <option value="maintenance" {{ $property->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                </select>
                                @php
                                    $activeBooking = $this->activeBookings[$property->id][0] ?? null;
                                @endphp
                                @if($activeBooking)
                                    <div class="text-xs text-slate-500 mt-1">
                                        {{ $activeBooking['guest_name'] }} · out {{ $activeBooking['check_out'] }}
                                    </div>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Edit --}}
                                    <a href="{{ route('tenant.properties.edit', $property->id) }}" wire:navigate
                                       class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    {{-- Delete --}}
                                    <button wire:click="delete({{ $property->id }})" wire:confirm="Delete this property?"
                                            class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                    <span class="text-slate-500">No properties found{{ $search || $typeFilter || $statusFilter !== '' ? ' matching your filters' : '' }}.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->properties->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{ $this->properties->links() }}
            </div>
        @endif
    </div>
</div>