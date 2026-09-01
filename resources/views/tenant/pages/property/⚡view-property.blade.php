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
class extends Component 
{
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
        $this->selectedProperties = $value 
            ? $this->properties->pluck('id')->map(fn($id) => (string) $id)->toArray()
            : [];
    }

    private function tenantPropertiesQuery()
    {
        return Property::query();
    }

    private function tenantBookingsQuery()
    {
        return Booking::query()
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED]);
    }

    public function updateStatus($id, $newStatus)
    {
        $property = $this->tenantPropertiesQuery()->findOrFail($id);

        if ($newStatus === 'available' && $this->hasActiveBooking($id)) {
            session()->flash('error', "Cannot set {$property->name} to available because it has active bookings.");
            return;
        }

        $property->update(['status' => $newStatus]);
        session()->flash('message', "{$property->name} status updated to " . ucfirst($newStatus) . '.');
    }

    public function toggleActive($id)
    {
        $property = $this->tenantPropertiesQuery()->findOrFail($id);
        $property->update(['is_active' => !$property->is_active]);
        
        session()->flash('message', "{$property->name} " . ($property->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function delete($id)
    {
        $property = $this->tenantPropertiesQuery()->findOrFail($id);

        if ($this->hasActiveBooking($id)) {
            session()->flash('error', "Cannot delete {$property->name} because it has active bookings.");
            return;
        }

        $propertyName = $property->name;
        $property->delete();
        session()->flash('message', "{$propertyName} deleted.");
    }

    public function bulkActivate()
    {
        $this->executeBulkAction(fn($query) => $query->update(['is_active' => true]), 'activated');
    }

    public function bulkDeactivate()
    {
        $this->executeBulkAction(fn($query) => $query->update(['is_active' => false]), 'deactivated');
    }

    public function bulkChangeStatus($newStatus)
    {
        if (empty($this->selectedProperties)) return;

        if ($newStatus === 'available') {
            $hasActive = BookingItem::whereIn('property_id', $this->selectedProperties)
                ->whereHas('booking', fn($q) => $q->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED]))
                ->exists();

            if ($hasActive) {
                session()->flash('error', 'Cannot set to available: One or more selected activities have active bookings.');
                return;
            }
        }

        $this->executeBulkAction(fn($query) => $query->update(['status' => $newStatus]), "marked as " . ucfirst($newStatus));
    }

    public function bulkDelete()
    {
        if (empty($this->selectedProperties)) return;

        $hasActive = BookingItem::whereIn('property_id', $this->selectedProperties)
            ->whereHas('booking', fn($q) => $q->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED]))
            ->exists();

        if ($hasActive) {
            session()->flash('error', 'One or more selected activities have active bookings and cannot be deleted.');
            return;
        }

        $this->executeBulkAction(fn($query) => $query->delete(), 'deleted');
    }

    private function executeBulkAction(callable $action, string $successActionWord)
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'No activities selected.');
            return;
        }

        $count = count($this->selectedProperties);
        $query = $this->tenantPropertiesQuery()->whereIn('id', $this->selectedProperties);
        
        $action($query);
        
        $this->resetSelection();
        session()->flash('message', "{$count} activities {$successActionWord}.");
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
            ->select('id', 'name')
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function properties()
    {
        return $this->tenantPropertiesQuery()
            ->with([
                'propertyType' => fn($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
                'images' => fn($q) => $q->select('id', 'property_id', 'image_path')->orderBy('id'),
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
        $statuses = $this->tenantPropertiesQuery()
            ->toBase()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($statuses);

        return [
            'total'       => $total,
            'available'   => $statuses['available'] ?? 0,
            'reserved'    => $statuses['reserved'] ?? 0,
            'occupied'    => $statuses['occupied'] ?? 0,
            'maintenance' => $statuses['maintenance'] ?? 0,
        ];
    }

    #[Computed]
    public function activeBookings()
    {
        $propertyIds = $this->properties->pluck('id');
        if ($propertyIds->isEmpty()) return collect();

        return $this->tenantBookingsQuery()
            ->where('check_in', '<=', now())
            ->where('check_out', '>', now())
            ->whereHas('items', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->with([
                'items' => fn($q) => $q->whereIn('property_id', $propertyIds)->select('id', 'booking_id', 'property_id'),
                'user:id,name'
            ])
            ->select('id', 'user_id', 'check_out')
            ->get()
            ->flatMap(function ($booking) {
                return $booking->items->map(fn($item) => [
                    'property_id' => $item->property_id,
                    'guest_name'  => $booking->user->name ?? 'N/A',
                    'check_out'   => $booking->check_out->format('M d, Y'),
                ]);
            })
            ->groupBy('property_id');
    }

    #[Computed]
    public function upcomingBookings()
    {
        $propertyIds = $this->properties->pluck('id');
        if ($propertyIds->isEmpty()) return collect();

        return $this->tenantBookingsQuery()
            ->where('check_in', '>', now())
            ->whereHas('items', fn($q) => $q->whereIn('property_id', $propertyIds))
            ->with([
                'items' => fn($q) => $q->whereIn('property_id', $propertyIds)->select('id', 'booking_id', 'property_id')
            ])
            ->select('id', 'check_in')
            ->get()
            ->flatMap(function ($booking) {
                return $booking->items->map(fn($item) => [
                    'property_id' => $item->property_id,
                    'check_in'    => $booking->check_in->format('M d, Y'),
                ]);
            })
            ->groupBy('property_id');
    }

    public function hasActiveBooking($propertyId): bool
    {
        return isset($this->activeBookings[$propertyId]) || isset($this->upcomingBookings[$propertyId]);
    }

    public function derivedStatus($property): string
    {
        if (isset($this->activeBookings[$property->id])) return 'occupied';
        if (isset($this->upcomingBookings[$property->id])) return 'reserved';
        return $property->status;
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
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
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
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="card p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['total'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-sm text-green-600 dark:text-green-400">Available</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->stats['available'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-sm text-cyan-600 dark:text-cyan-400">Reserved</p>
            <p class="text-2xl font-bold text-cyan-600 dark:text-cyan-400">{{ $this->stats['reserved'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-sm text-amber-600 dark:text-amber-400">Occupied</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $this->stats['occupied'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-sm text-red-600 dark:text-red-400">Maintenance</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->stats['maintenance'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
            <div class="relative flex-1 w-full">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or description..." 
                       class="input pl-10">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div class="flex flex-wrap gap-2 w-full md:w-auto">
                <select wire:model.live="typeFilter" class="select w-full sm:w-auto">
                    <option value="">All Types</option>
                    @foreach($this->propertyTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="select w-full sm:w-auto">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="reserved">Reserved</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                @if($search || $typeFilter || $statusFilter !== '')
                    <button wire:click="clearFilters"
                            class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Bulk Actions --}}
    @if(count($selectedProperties) > 0)
    <div class="card p-3 border-primary-600/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
        <span class="text-sm text-primary-600 dark:text-primary-400 font-medium">{{ count($selectedProperties) }} selected</span>
        <div class="flex flex-wrap gap-2">
            <button wire:click="bulkActivate" wire:loading.attr="disabled"
                    class="text-xs bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white px-3 py-1.5 rounded-full shadow-sm transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-green-500/50">Activate</button>
            <button wire:click="bulkDeactivate" wire:loading.attr="disabled"
                    class="text-xs bg-yellow-600 hover:bg-yellow-500 disabled:opacity-50 text-white px-3 py-1.5 rounded-full shadow-sm transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-yellow-500/50">Deactivate</button>
            <button wire:click="bulkChangeStatus('available')" wire:loading.attr="disabled"
                    class="text-xs bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white px-3 py-1.5 rounded-full shadow-sm transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-emerald-500/50">Set Available</button>
            <button wire:click="bulkChangeStatus('occupied')" wire:loading.attr="disabled"
                    class="text-xs bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white px-3 py-1.5 rounded-full shadow-sm transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-amber-500/50">Set Occupied</button>
            <button wire:click="bulkChangeStatus('reserved')" wire:loading.attr="disabled"
                    class="text-xs bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 text-white px-3 py-1.5 rounded-full shadow-sm transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-cyan-500/50">Set Reserved</button>
            <button wire:click="bulkChangeStatus('maintenance')" wire:loading.attr="disabled"
                    class="text-xs bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white px-3 py-1.5 rounded-full shadow-sm transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50">Set Maintenance</button>
            <button wire:click="bulkDelete" wire:confirm="Delete selected activities? This cannot be undone." wire:loading.attr="disabled"
                    class="text-xs bg-red-700 hover:bg-red-600 disabled:opacity-50 text-white px-3 py-1.5 rounded-full shadow-sm transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50">Delete</button>
            <button wire:click="$set('selectedProperties', [])"
                    class="text-xs border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 px-3 py-1.5 rounded-full transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Properties Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-4 w-10">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                        </th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Activity</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden sm:table-cell">Type</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Capacity</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Units</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden lg:table-cell">Price</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status / Occupancy</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200 transition-opacity duration-200" wire:loading.class="opacity-50">
                    @forelse($this->properties as $property)
                        @php
                            $derived = $this->derivedStatus($property);
                            $hasActive = $this->hasActiveBooking($property->id);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedProperties" value="{{ $property->id }}" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
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

                            <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                {{ $property->quantity }}
                            </td>

                            <td class="px-4 sm:px-6 py-4 text-sm hidden lg:table-cell">
                                ₱{{ number_format($property->price, 2) }}
                            </td>

                            <td class="px-4 sm:px-6 py-4">
                                <select wire:change="updateStatus({{ $property->id }}, $event.target.value)" 
                                        {{ $hasActive ? 'disabled' : '' }}
                                        class="text-xs rounded-full px-3 py-1.5 border font-medium w-32 appearance-none cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition-all duration-200
                                            {{ $derived === 'available' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border-green-200 dark:border-green-500/30' : '' }}
                                            {{ $derived === 'occupied' ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30' : '' }}
                                            {{ $derived === 'reserved' ? 'bg-cyan-100 dark:bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-500/30' : '' }}
                                            {{ $derived === 'maintenance' ? 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border-red-200 dark:border-red-500/30' : '' }}">
                                    <option value="available" {{ $derived === 'available' ? 'selected' : '' }}>Available</option>
                                    <option value="occupied" {{ $derived === 'occupied' ? 'selected' : '' }}>Occupied</option>
                                    <option value="reserved" {{ $derived === 'reserved' ? 'selected' : '' }}>Reserved</option>
                                    <option value="maintenance" {{ $derived === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                </select>

                                @if($hasActive)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        @if(isset($this->activeBookings[$property->id]))
                                            {{ $this->activeBookings[$property->id][0]['guest_name'] }} · until {{ $this->activeBookings[$property->id][0]['check_out'] }}
                                        @elseif(isset($this->upcomingBookings[$property->id]))
                                            Next arrival: {{ $this->upcomingBookings[$property->id][0]['check_in'] }}
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="toggleActive({{ $property->id }})" wire:loading.attr="disabled"
                                            class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50" title="Toggle active">
                                        @if($property->is_active)
                                            <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        @else
                                            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        @endif
                                    </button>
                                    <a href="{{ route('tenant.properties.edit', $property->id) }}" wire:navigate
                                       class="p-1.5 text-primary-600 dark:text-primary-400 hover:text-white hover:bg-primary-500/20 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete({{ $property->id }})" wire:confirm="Delete this activity?" wire:loading.attr="disabled"
                                            class="p-1.5 text-red-600 dark:text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
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