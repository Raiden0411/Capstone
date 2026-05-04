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
#[Title('Property Inventory')]
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
        session()->flash('message', "{$property->name} status updated to " . ucfirst($newStatus) . '.');
    }

    public function toggleActive($id)
    {
        $property = Property::findOrFail($id);
        $property->update(['is_active' => !$property->is_active]);
        session()->flash('message', "{$property->name} " . ($property->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function delete($id)
    {
        $property = Property::findOrFail($id);
        $propertyName = $property->name;
        $property->delete();
        session()->flash('message', "{$propertyName} deleted.");
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

    /** Returns current active bookings grouped by property_id. */
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

    /** Returns the next future booking check‑in date for each property. */
    #[Computed]
    public function upcomingBookings()
    {
        return Booking::where('tenant_id', Auth::user()->tenant_id)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('check_in', '>', now())
            ->with('items')
            ->get()
            ->flatMap(function ($booking) {
                return $booking->items->map(function ($item) use ($booking) {
                    return [
                        'property_id' => $item->property_id,
                        'check_in'    => $booking->check_in->format('M d, Y'),
                    ];
                });
            })
            ->groupBy('property_id');
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold">Property Inventory</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Manage your tourist‑spot properties, attractions, and bookable items.</p>
        </div>
        <a href="{{ route('tenant.properties.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Property
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-md shadow-sm">
            <p class="text-sm text-red-700 dark:text-red-400 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Total</p>
            <p class="text-2xl font-bold">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-emerald-600 dark:text-emerald-400">Available</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $this->stats['available'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-amber-600 dark:text-amber-400">Occupied</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $this->stats['occupied'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-red-600 dark:text-red-400">Maintenance</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->stats['maintenance'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-4">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
            <div class="relative flex-1 w-full">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or description..." 
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div class="flex flex-wrap gap-2 w-full md:w-auto">
                <select wire:model.live="typeFilter" class="px-3 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Types</option>
                    @foreach($this->propertyTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="px-3 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                @if($search || $typeFilter || $statusFilter !== '')
                    <button wire:click="clearFilters" class="px-3 py-2 text-sm text-gray-600 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">Clear</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Bulk Actions --}}
    @if(count($selectedProperties) > 0)
    <div class="bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 rounded-lg p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
        <span class="text-sm text-blue-800 dark:text-blue-400 font-medium">{{ count($selectedProperties) }} selected</span>
        <div class="flex flex-wrap gap-2">
            <button wire:click="bulkActivate" class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Activate</button>
            <button wire:click="bulkDeactivate" class="text-xs bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Deactivate</button>
            <button wire:click="bulkChangeStatus('available')" class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Set Available</button>
            <button wire:click="bulkChangeStatus('occupied')" class="text-xs bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Set Occupied</button>
            <button wire:click="bulkChangeStatus('maintenance')" class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Set Maintenance</button>
            <button wire:click="bulkDelete" wire:confirm="Delete selected properties? This cannot be undone." class="text-xs bg-red-700 hover:bg-red-800 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Delete</button>
            <button wire:click="$set('selectedProperties', [])" class="text-xs text-gray-600 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white px-3 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Properties Table --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700/50">
                    <tr>
                        <th class="px-4 py-4 w-10">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Property</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase hidden sm:table-cell">Type</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase hidden md:table-cell">Capacity</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase hidden lg:table-cell">Price</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Status / Occupancy</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700/30 text-gray-700 dark:text-slate-300">
                    @forelse($this->properties as $property)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            {{-- Checkbox --}}
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedProperties" value="{{ $property->id }}" class="rounded border-gray-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500">
                            </td>

                            {{-- Thumbnail + Name --}}
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0 h-10 w-10 rounded-lg bg-gray-100 dark:bg-slate-800 overflow-hidden">
                                        @if($property->images->isNotEmpty())
                                            <img src="{{ Storage::url($property->images->first()->image_path) }}" alt="{{ $property->name }}" class="h-full w-full object-cover">
                                        @else
                                            <div class="h-full w-full flex items-center justify-center text-gray-400 dark:text-slate-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $property->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-slate-400 truncate max-w-[150px]">{{ $property->description ?: 'No description' }}</div>
                                    </div>
                                </div>
                            </td>

                            {{-- Type --}}
                            <td class="px-4 sm:px-6 py-4 text-sm hidden sm:table-cell">
                                {{ $property->propertyType->name ?? '—' }}
                            </td>

                            {{-- Capacity --}}
                            <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                {{ $property->capacity }} pers.
                            </td>

                            {{-- Price --}}
                            <td class="px-4 sm:px-6 py-4 text-sm hidden lg:table-cell">
                                ₱{{ number_format($property->price, 2) }}
                            </td>

                            {{-- Status + Occupation / Next Arrival --}}
                            <td class="px-4 sm:px-6 py-4">
                                <select wire:change="updateStatus({{ $property->id }}, $event.target.value)" 
                                        class="text-xs rounded-full px-2 py-1 border-0 font-medium w-28
                                            {{ $property->status === 'available' ? 'bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400' : '' }}
                                            {{ $property->status === 'occupied' ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400' : '' }}
                                            {{ $property->status === 'maintenance' ? 'bg-red-100 dark:bg-red-500/20 text-red-800 dark:text-red-400' : '' }}">
                                    <option value="available" {{ $property->status === 'available' ? 'selected' : '' }}>Available</option>
                                    <option value="occupied" {{ $property->status === 'occupied' ? 'selected' : '' }}>Occupied</option>
                                    <option value="maintenance" {{ $property->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                </select>

                                @php
                                    $activeGuest = $this->activeBookings[$property->id][0] ?? null;
                                    $nextArrival = $this->upcomingBookings[$property->id][0] ?? null;
                                @endphp
                                @if($activeGuest)
                                    <div class="text-xs text-gray-500 dark:text-slate-400 mt-1">
                                        {{ $activeGuest['guest_name'] }} · until {{ $activeGuest['check_out'] }}
                                    </div>
                                @elseif($nextArrival && $property->status === 'available')
                                    <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                        Next arrival: {{ $nextArrival['check_in'] }}
                                    </div>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('tenant.properties.edit', $property->id) }}" wire:navigate
                                       class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete({{ $property->id }})" wire:confirm="Delete this property?"
                                            class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-slate-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                <span class="text-sm">No properties found{{ $search || $typeFilter || $statusFilter !== '' ? ' matching your filters' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->properties->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50">
                {{ $this->properties->links() }}
            </div>
        @endif
    </div>
</div>