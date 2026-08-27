{{-- resources/views/tenant/pages/property/⚡view-property.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Activity Inventory')]
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
            $this->selectedProperties = $this->properties
                ->pluck('id')
                ->map(fn($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedProperties = [];
        }
    }

    public function updateStatus($id, $newStatus)
    {
        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $property->update(['status' => $newStatus]);
        session()->flash('message', "{$property->name} status updated to " . ucfirst($newStatus) . '.');
    }

    public function toggleActive($id)
    {
        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $property->update(['is_active' => !$property->is_active]);
        session()->flash('message', "{$property->name} " . ($property->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function delete($id)
    {
        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $hasActiveBookings = BookingItem::where('property_id', $property->id)
            ->whereHas('booking', fn($q) => $q->whereNotIn('status', ['cancelled', 'completed']))
            ->exists();

        if ($hasActiveBookings) {
            session()->flash('error', "Cannot delete {$property->name} because it has active bookings.");
            return;
        }

        $propertyName = $property->name;
        $property->delete();
        session()->flash('message', "{$propertyName} deleted.");
    }

    public function bulkActivate()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No activities selected.');
            return;
        }
        $count = count($this->selectedProperties);
        Property::whereIn('id', $this->selectedProperties)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(['is_active' => true]);
        $this->resetSelection();
        session()->flash('message', "$count activities activated.");
    }

    public function bulkDeactivate()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No activities selected.');
            return;
        }
        $count = count($this->selectedProperties);
        Property::whereIn('id', $this->selectedProperties)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(['is_active' => false]);
        $this->resetSelection();
        session()->flash('message', "$count activities deactivated.");
    }

    public function bulkChangeStatus($newStatus)
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No activities selected.');
            return;
        }
        $count = count($this->selectedProperties);
        Property::whereIn('id', $this->selectedProperties)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(['status' => $newStatus]);
        $this->resetSelection();
        session()->flash('message', "$count activities marked as " . ucfirst($newStatus) . '.');
    }

    public function bulkDelete()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No activities selected.');
            return;
        }

        $count = count($this->selectedProperties);

        $hasActive = BookingItem::whereIn('property_id', $this->selectedProperties)
            ->whereHas('booking', fn($q) => $q->whereNotIn('status', ['cancelled', 'completed']))
            ->exists();

        if ($hasActive) {
            session()->flash('error', 'One or more selected activities have active bookings and cannot be deleted.');
            return;
        }

        Property::whereIn('id', $this->selectedProperties)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->delete();
        $this->resetSelection();
        session()->flash('message', "$count activities deleted.");
    }

    private function resetSelection()
    {
        $this->selectedProperties = [];
        $this->selectAll = false;
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
        return Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with([
                'propertyType' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'images'
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, fn($q) => $q->where('property_type_id', $this->typeFilter))
            ->when($this->statusFilter !== '', fn($q) => $q->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        $tid = Auth::user()->tenant_id;
        return [
            'total'       => Property::where('tenant_id', $tid)->count(),
            'available'   => Property::where('tenant_id', $tid)->where('status', 'available')->count(),
            'occupied'    => Property::where('tenant_id', $tid)->where('status', 'occupied')->count(),
            'maintenance' => Property::where('tenant_id', $tid)->where('status', 'maintenance')->count(),
        ];
    }

    #[Computed]
    public function activeBookings()
    {
        return Booking::where('tenant_id', Auth::user()->tenant_id)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('check_in', '<=', now())
            ->where('check_out', '>', now())
            ->with(['items', 'user'])
            ->get()
            ->flatMap(function ($booking) {
                return $booking->items->map(function ($item) use ($booking) {
                    return [
                        'property_id' => $item->property_id,
                        'guest_name'  => $booking->user->name ?? 'N/A',
                        'check_out'   => $booking->check_out->format('M d, Y'),
                    ];
                });
            })
            ->groupBy('property_id');
    }

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

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Activity Inventory</h1>
        </div>
        <a href="{{ route('tenant.properties.create') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold shadow-lg shadow-blue-500/20 transition hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Activity
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.293 10.293a1 1 0 011.414 0L12 10.586l.293-.293a1 1 0 111.414 1.414L13.414 12l.293.293a1 1 0 01-1.414 1.414L12 13.414l-.293.293a1 1 0 01-1.414-1.414L10.586 12l-.293-.293a1 1 0 010-1.414z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['total'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-sm text-green-600 dark:text-green-400">Available</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['available'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-sm text-amber-600 dark:text-amber-400">Occupied</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $this->stats['occupied'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-sm text-red-600 dark:text-red-400">Maintenance</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->stats['maintenance'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 shadow-sm">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
            <div class="relative flex-1 w-full">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or description..." 
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div class="flex flex-wrap gap-2 w-full md:w-auto">
                <select wire:model.live="typeFilter" class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition appearance-none">
                    <option value="">All Types</option>
                    @foreach($this->propertyTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition appearance-none">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                @if($search || $typeFilter || $statusFilter !== '')
                    <button wire:click="clearFilters" class="px-4 py-2 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold uppercase tracking-wider transition">✕ Clear</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Bulk Actions --}}
    @if(count($selectedProperties) > 0)
    <div class="bg-white dark:bg-gray-800 border border-[#376df1]/30 rounded-lg p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 shadow-sm">
        <span class="text-sm text-[#376df1] dark:text-blue-400 font-medium">{{ count($selectedProperties) }} selected</span>
        <div class="flex flex-wrap gap-2">
            <button wire:click="bulkActivate" class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded-full shadow-sm transition-colors">Activate</button>
            <button wire:click="bulkDeactivate" class="text-xs bg-yellow-600 hover:bg-yellow-500 text-white px-3 py-1.5 rounded-full shadow-sm transition-colors">Deactivate</button>
            <button wire:click="bulkChangeStatus('available')" class="text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-full shadow-sm transition-colors">Set Available</button>
            <button wire:click="bulkChangeStatus('occupied')" class="text-xs bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 rounded-full shadow-sm transition-colors">Set Occupied</button>
            <button wire:click="bulkChangeStatus('maintenance')" class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-full shadow-sm transition-colors">Set Maintenance</button>
            <button wire:click="bulkDelete" wire:confirm="Delete selected activities? This cannot be undone." class="text-xs bg-red-700 hover:bg-red-600 text-white px-3 py-1.5 rounded-full shadow-sm transition-colors">Delete</button>
            <button wire:click="$set('selectedProperties', [])" class="text-xs border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 px-3 py-1.5 rounded-full transition-colors">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Properties Table --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-4 w-10">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-[#376df1] focus:ring-[#376df1]">
                        </th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Activity</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden sm:table-cell">Type</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Capacity</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden lg:table-cell">Price</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status / Occupancy</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->properties as $property)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedProperties" value="{{ $property->id }}" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-[#376df1] focus:ring-[#376df1]">
                            </td>

                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0 h-10 w-10 rounded-lg bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                        @if($property->images->isNotEmpty())
                                            <img src="{{ asset('storage/'. $property->images->first()->image_path) }}" alt="{{ $property->name }}" class="h-full w-full object-cover">
                                        @else
                                            <div class="h-full w-full flex items-center justify-center text-gray-400 dark:text-gray-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $property->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px]">{{ $property->description ?: 'No description' }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 sm:px-6 py-4 text-sm hidden sm:table-cell">
                                {{ $property->propertyType->name ?? '—' }}
                            </td>

                            <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                {{ $property->capacity }} pers.
                            </td>

                            <td class="px-4 sm:px-6 py-4 text-sm hidden lg:table-cell">
                                ₱{{ number_format($property->price, 2) }}
                            </td>

                            <td class="px-4 sm:px-6 py-4">
                                <select wire:change="updateStatus({{ $property->id }}, $event.target.value)" 
                                        class="text-xs rounded-full px-3 py-1.5 border font-medium w-32 appearance-none cursor-pointer
                                            {{ $property->status === 'available' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border-green-200 dark:border-green-500/30' : '' }}
                                            {{ $property->status === 'occupied' ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30' : '' }}
                                            {{ $property->status === 'maintenance' ? 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border-red-200 dark:border-red-500/30' : '' }}">
                                    <option value="available" {{ $property->status === 'available' ? 'selected' : '' }}>Available</option>
                                    <option value="occupied" {{ $property->status === 'occupied' ? 'selected' : '' }}>Occupied</option>
                                    <option value="maintenance" {{ $property->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                </select>

                                @php
                                    $activeGuest = $this->activeBookings[$property->id][0] ?? null;
                                    $nextArrival = $this->upcomingBookings[$property->id][0] ?? null;
                                @endphp
                                @if($activeGuest)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $activeGuest['guest_name'] }} · until {{ $activeGuest['check_out'] }}
                                    </div>
                                @elseif($nextArrival && $property->status === 'available')
                                    <div class="text-xs text-[#376df1] dark:text-blue-400 mt-1">
                                        Next arrival: {{ $nextArrival['check_in'] }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="toggleActive({{ $property->id }})" class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" title="Toggle active">
                                        @if($property->is_active)
                                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        @else
                                            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        @endif
                                    </button>
                                    <a href="{{ route('tenant.properties.edit', $property->id) }}" wire:navigate
                                       class="p-1.5 text-[#376df1] dark:text-blue-400 hover:text-white hover:bg-blue-500/20 rounded-lg transition" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete({{ $property->id }})" wire:confirm="Delete this activity?"
                                            class="p-1.5 text-red-600 dark:text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                <span class="text-sm">No activities found{{ $search || $typeFilter || $statusFilter !== '' ? ' matching your filters' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->properties->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->properties->links() }}
            </div>
        @endif
    </div>
</div>