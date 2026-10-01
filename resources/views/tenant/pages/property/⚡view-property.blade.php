{{-- resources/views/tenant/pages/property/⚡view-property.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;

new
#[Layout('tenant.layouts.app')]
#[Title('Activity Inventory')]
class extends Component
{
    use WithPagination;
    use ChecksTenantPermissions;

    #[Url(keep: true)] public string $search       = '';
    #[Url(keep: true)] public string $typeFilter   = '';
    #[Url(keep: true)] public string $statusFilter = '';

    /** @var array<int, int|string> */
    public array $selectedProperties = [];

    public bool $selectAll = false;

    public function mount(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
        abort_unless($this->tenantCan('view properties'), 403);
    }

    public function hydrate(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
        abort_unless($this->tenantCan('view properties'), 403);
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingTypeFilter(): void   { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function updatedSelectAll($value): void
    {
        $this->selectedProperties = $value
            ? $this->properties->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    public function updatedSelectedProperties(): void
    {
        $this->selectAll = count($this->selectedProperties) === $this->properties->count()
            && $this->properties->count() > 0;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'statusFilter']);
        $this->resetPage();
    }

    private function resetSelection(): void
    {
        $this->selectedProperties = [];
        $this->selectAll = false;
    }

    public function clearSelection(): void
    {
        $this->resetSelection();
    }

    private function tenantPropertiesQuery()
    {
        return Property::query()
            ->where('tenant_id', Auth::user()->tenant_id);
    }

    private function tenantBookingsQuery()
    {
        return Booking::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED]);
    }

    public function updateStatus($id, $newStatus): void
    {
        $this->requirePermission('manage properties');

        $property = $this->tenantPropertiesQuery()->findOrFail($id);

        if ($newStatus === 'available' && $this->hasActiveBooking($id)) {
            session()->flash('error', "Cannot set {$property->name} to available because it has active bookings.");
            return;
        }

        $property->update(['status' => $newStatus]);
        session()->flash('message', "{$property->name} status updated to " . ucfirst($newStatus) . '.');
    }

    public function toggleActive($id): void
    {
        $this->requirePermission('manage properties');

        $property = $this->tenantPropertiesQuery()->findOrFail($id);
        $property->update(['is_active' => ! $property->is_active]);

        session()->flash('message', "{$property->name} " . ($property->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function delete($id): void
    {
        $this->requirePermission('manage properties');

        $property = $this->tenantPropertiesQuery()->findOrFail($id);

        if ($this->hasActiveBooking($id)) {
            session()->flash('error', "Cannot delete {$property->name} because it has active bookings.");
            return;
        }

        $propertyName = $property->name;
        $property->delete();
        session()->flash('message', "{$propertyName} deleted.");
    }

    public function bulkActivate(): void
    {
        $this->requirePermission('manage properties');
        $this->executeBulkAction(fn ($query) => $query->update(['is_active' => true]), 'activated');
    }

    public function bulkDeactivate(): void
    {
        $this->requirePermission('manage properties');
        $this->executeBulkAction(fn ($query) => $query->update(['is_active' => false]), 'deactivated');
    }

    public function bulkChangeStatus($newStatus): void
    {
        $this->requirePermission('manage properties');

        if (empty($this->selectedProperties)) {
            return;
        }

        if ($newStatus === 'available') {
            $hasActive = BookingItem::query()
                ->whereHas('booking', fn ($q) => $q
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                )
                ->whereIn('property_id', $this->selectedProperties)
                ->exists();

            if ($hasActive) {
                session()->flash('error', 'Cannot set to available: one or more selected activities have active bookings.');
                return;
            }
        }

        $this->executeBulkAction(fn ($query) => $query->update(['status' => $newStatus]), 'marked as ' . ucfirst($newStatus));
    }

    public function bulkDelete(): void
    {
        $this->requirePermission('manage properties');

        if (empty($this->selectedProperties)) {
            return;
        }

        $hasActive = BookingItem::query()
            ->whereHas('booking', fn ($q) => $q
                ->where('tenant_id', Auth::user()->tenant_id)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
            )
            ->whereIn('property_id', $this->selectedProperties)
            ->exists();

        if ($hasActive) {
            session()->flash('error', 'One or more selected activities have active bookings and cannot be deleted.');
            return;
        }

        $this->executeBulkAction(fn ($query) => $query->delete(), 'deleted');
    }

    private function executeBulkAction(callable $action, string $successActionWord): void
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
                'propertyType' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
                'images' => fn ($q) => $q->select('id', 'property_id', 'image_path')->orderBy('id'),
            ])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, fn ($q) => $q->where('property_type_id', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(12);
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
            'available'   => $statuses['available']   ?? 0,
            'reserved'    => $statuses['reserved']    ?? 0,
            'occupied'    => $statuses['occupied']    ?? 0,
            'maintenance' => $statuses['maintenance'] ?? 0,
        ];
    }

    #[Computed]
    public function activeBookings()
    {
        $propertyIds = $this->properties->pluck('id');
        if ($propertyIds->isEmpty()) {
            return collect();
        }

        return $this->tenantBookingsQuery()
            ->where('check_in', '<=', now())
            ->where('check_out', '>', now())
            ->whereHas('items', fn ($q) => $q->whereIn('property_id', $propertyIds))
            ->with([
                'items' => fn ($q) => $q->whereIn('property_id', $propertyIds)->select('id', 'booking_id', 'property_id'),
                'user:id,name',
            ])
            ->select('id', 'user_id', 'check_out')
            ->get()
            ->flatMap(function ($booking) {
                return $booking->items->map(fn ($item) => [
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
        if ($propertyIds->isEmpty()) {
            return collect();
        }

        return $this->tenantBookingsQuery()
            ->where('check_in', '>', now())
            ->whereHas('items', fn ($q) => $q->whereIn('property_id', $propertyIds))
            ->with([
                'items' => fn ($q) => $q->whereIn('property_id', $propertyIds)->select('id', 'booking_id', 'property_id'),
            ])
            ->select('id', 'check_in')
            ->get()
            ->flatMap(function ($booking) {
                return $booking->items->map(fn ($item) => [
                    'property_id' => $item->property_id,
                    'check_in'    => $booking->check_in->format('M d, Y'),
                ]);
            })
            ->groupBy('property_id');
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->typeFilter !== ''
            || $this->statusFilter !== '';
    }

    public function hasActiveBooking($propertyId): bool
    {
        return isset($this->activeBookings[$propertyId])
            || isset($this->upcomingBookings[$propertyId]);
    }

    public function derivedStatus($property): string
    {
        if (isset($this->activeBookings[$property->id])) {
            return 'occupied';
        }
        if (isset($this->upcomingBookings[$property->id])) {
            return 'reserved';
        }
        return $property->status;
    }

    /**
     * @return array{label: string, classes: string, dot: string}
     */
    public function statusConfig(string $status): array
    {
        return match ($status) {
            'available'   => ['label' => 'Available',   'classes' => 'bg-emerald-600 text-white', 'dot' => 'bg-white/90'],
            'occupied'    => ['label' => 'Occupied',    'classes' => 'bg-amber-600 text-white',   'dot' => 'bg-white/90'],
            'reserved'    => ['label' => 'Reserved',    'classes' => 'bg-blue-600 text-white',    'dot' => 'bg-white/90'],
            'maintenance' => ['label' => 'Maintenance', 'classes' => 'bg-rose-600 text-white',    'dot' => 'bg-white/90'],
            default       => ['label' => ucfirst($status), 'classes' => 'bg-gray-600 text-white', 'dot' => 'bg-white/90'],
        };
    }

    /**
     * Human-readable stay-limits summary for a property card.
     * Returns null when the property has no limits to display.
     */
    public function stayLimitsLabel(Property $property): ?string
    {
        if (! $property->hasDurationLimits()) {
            return null;
        }

        $min = $property->effectiveMinStayDays();
        $max = $property->effectiveMaxStayDays();

        if ($max === null) {
            return $min === 1
                ? 'Any stay length'
                : "Min {$min} " . ($min === 1 ? 'day' : 'days');
        }

        if ($min === $max) {
            return $max === 1
                ? '1-day stays only'
                : "{$max}-day stays only";
        }

        return "{$min}–{$max} day stays";
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-properties-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-properties-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

<div class="relative">
    <div class="tenant-properties-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        @if (session()->has('message'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 4000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('message') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        @if (session()->has('error'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 5000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200/70 dark:border-gray-800/70">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Inventory</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Activity Inventory
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Manage your bookable activities and their availability.
                </p>
            </div>
            @if($this->tenantCan('manage properties'))
                <a href="{{ route('tenant.properties.create') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                          transition-all duration-200 active:scale-95
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Add Activity</span>
                </a>
            @endif
        </div>

        @php $s = $this->stats; @endphp
        <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                    shadow-sm p-4 space-y-3">

            <div class="flex flex-wrap gap-2 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search by name or description…"
                           enterkeyhint="search"
                           aria-label="Search activities"
                           class="w-full h-11 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl pl-10 pr-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400
                                  focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                                  [touch-action:manipulation]">
                </div>

                <select wire:model.live="typeFilter"
                        aria-label="Filter by type"
                        class="h-11 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 text-base sm:text-sm text-gray-900 dark:text-white
                               focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                               [touch-action:manipulation]">
                    <option value="">All Types</option>
                    @foreach($this->propertyTypes as $type)
                        <option value="{{ $type->id }}" wire:key="type-opt-{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
                @php
                    $pills = [
                        ['value' => '',            'label' => 'All',         'count' => $s['total'],       'active' => 'bg-primary-600 border-primary-600 text-white shadow-sm shadow-primary-600/20',         'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600'],
                        ['value' => 'available',   'label' => 'Available',   'count' => $s['available'],   'active' => 'bg-emerald-600 border-emerald-600 text-white shadow-sm shadow-emerald-600/20',       'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-emerald-400 dark:hover:border-emerald-500/60'],
                        ['value' => 'reserved',    'label' => 'Reserved',    'count' => $s['reserved'],    'active' => 'bg-blue-600 border-blue-600 text-white shadow-sm shadow-blue-600/20',                'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-blue-400 dark:hover:border-blue-500/60'],
                        ['value' => 'occupied',    'label' => 'Occupied',    'count' => $s['occupied'],    'active' => 'bg-amber-600 border-amber-600 text-white shadow-sm shadow-amber-600/20',             'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-amber-400 dark:hover:border-amber-500/60'],
                        ['value' => 'maintenance', 'label' => 'Maintenance', 'count' => $s['maintenance'], 'active' => 'bg-rose-600 border-rose-600 text-white shadow-sm shadow-rose-600/20',                'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-rose-400 dark:hover:border-rose-500/60'],
                    ];
                @endphp

                @foreach($pills as $pill)
                    @php $isActive = $statusFilter === $pill['value']; @endphp
                    <button type="button"
                            wire:click="$set('statusFilter', '{{ $pill['value'] }}')"
                            wire:key="pill-{{ $pill['value'] !== '' ? $pill['value'] : 'all' }}"
                            aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                            class="inline-flex items-center gap-2 h-11 sm:h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                                   transition-all duration-200 active:scale-95 shrink-0
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   {{ $isActive ? $pill['active'] : $pill['rest'] }}">
                        <span>{{ $pill['label'] }}</span>
                        <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                     {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                            {{ $pill['count'] }}
                        </span>
                    </button>
                @endforeach

                @if($this->hasActiveFilters)
                    <button type="button"
                            wire:click="clearFilters"
                            class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                                   border border-rose-300 dark:border-rose-500/40
                                   bg-white dark:bg-gray-800
                                   text-rose-700 dark:text-rose-300
                                   transition-all duration-200 active:scale-95 shrink-0
                                   hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Clear</span>
                    </button>
                @endif
            </div>
        </div>

        @if(count($selectedProperties) > 0 && $this->tenantCan('manage properties'))
            <div class="flex flex-wrap items-center justify-between gap-3 bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/30 p-4 rounded-2xl">
                <div class="flex items-center gap-2 text-sm text-primary-800 dark:text-primary-200">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span><strong>{{ count($selectedProperties) }}</strong> selected</span>
                </div>

                <div class="flex flex-wrap gap-2 items-center">
                    <button type="button"
                            wire:click="bulkActivate"
                            wire:loading.attr="disabled"
                            wire:target="bulkActivate"
                            class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-xl
                                   border border-emerald-300 dark:border-emerald-500/40
                                   bg-white dark:bg-gray-800
                                   text-emerald-700 dark:text-emerald-300
                                   text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-emerald-50 dark:hover:bg-emerald-500/10
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Activate</span>
                    </button>

                    <button type="button"
                            wire:click="bulkDeactivate"
                            wire:loading.attr="disabled"
                            wire:target="bulkDeactivate"
                            class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-xl
                                   border border-amber-300 dark:border-amber-500/40
                                   bg-white dark:bg-gray-800
                                   text-amber-700 dark:text-amber-300
                                   text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-amber-50 dark:hover:bg-amber-500/10
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        <span>Deactivate</span>
                    </button>

                    <span class="w-px h-6 bg-primary-300/50 dark:bg-primary-500/30 mx-1" aria-hidden="true"></span>

                    <button type="button"
                            wire:click="bulkChangeStatus('available')"
                            wire:loading.attr="disabled"
                            wire:target="bulkChangeStatus"
                            class="inline-flex items-center justify-center h-11 sm:h-9 px-3.5 rounded-xl
                                   border border-emerald-300 dark:border-emerald-500/40
                                   bg-white dark:bg-gray-800
                                   text-emerald-700 dark:text-emerald-300
                                   text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-emerald-50 dark:hover:bg-emerald-500/10
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span>Set Available</span>
                    </button>

                    <button type="button"
                            wire:click="bulkChangeStatus('occupied')"
                            wire:loading.attr="disabled"
                            wire:target="bulkChangeStatus"
                            class="inline-flex items-center justify-center h-11 sm:h-9 px-3.5 rounded-xl
                                   border border-amber-300 dark:border-amber-500/40
                                   bg-white dark:bg-gray-800
                                   text-amber-700 dark:text-amber-300
                                   text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-amber-50 dark:hover:bg-amber-500/10
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span>Set Occupied</span>
                    </button>

                    <button type="button"
                            wire:click="bulkChangeStatus('reserved')"
                            wire:loading.attr="disabled"
                            wire:target="bulkChangeStatus"
                            class="inline-flex items-center justify-center h-11 sm:h-9 px-3.5 rounded-xl
                                   border border-blue-300 dark:border-blue-500/40
                                   bg-white dark:bg-gray-800
                                   text-blue-700 dark:text-blue-300
                                   text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-blue-50 dark:hover:bg-blue-500/10
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span>Set Reserved</span>
                    </button>

                    <button type="button"
                            wire:click="bulkChangeStatus('maintenance')"
                            wire:loading.attr="disabled"
                            wire:target="bulkChangeStatus"
                            class="inline-flex items-center justify-center h-11 sm:h-9 px-3.5 rounded-xl
                                   border border-rose-300 dark:border-rose-500/40
                                   bg-white dark:bg-gray-800
                                   text-rose-700 dark:text-rose-300
                                   text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span>Set Maintenance</span>
                    </button>

                    <span class="w-px h-6 bg-primary-300/50 dark:bg-primary-500/30 mx-1" aria-hidden="true"></span>

                    <button type="button"
                            x-data="{
                                armed: false,
                                _t: null,
                                arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                destroy() { clearTimeout(this._t); }
                            }"
                            @click="armed ? (unarm(), $wire.bulkDelete()) : arm()"
                            wire:loading.attr="disabled"
                            wire:target="bulkDelete"
                            :class="armed
                                ? 'bg-amber-100 dark:bg-amber-500/20 border-amber-400 dark:border-amber-500/60 text-amber-800 dark:text-amber-200 ring-2 ring-amber-400/60'
                                : 'border-rose-300 dark:border-rose-500/40 bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-500/10'"
                            :aria-label="armed ? 'Click again to confirm delete' : 'Delete selected activities'"
                            :title="armed ? 'Click again to confirm' : 'Delete selected'"
                            class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-xl border
                                   text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg x-show="!armed" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <svg x-show="armed" x-cloak class="w-3.5 h-3.5 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-show="!armed">Delete</span>
                        <span x-show="armed" x-cloak>Confirm</span>
                    </button>

                    <button type="button"
                            wire:click="clearSelection"
                            class="inline-flex items-center justify-center h-11 sm:h-9 px-3.5 rounded-xl
                                   border border-gray-300 dark:border-gray-600
                                   bg-white dark:bg-gray-800
                                   text-gray-700 dark:text-gray-200
                                   text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-gray-50 dark:hover:bg-gray-700
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <span>Cancel</span>
                    </button>
                </div>
            </div>
        @endif

        @if($this->properties->isEmpty())
            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        p-12">
                <div class="flex flex-col items-center max-w-md mx-auto text-center">
                    <div class="p-4 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mb-4">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        No activities found
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        @if($this->hasActiveFilters)
                            No activities match your current filters. Try adjusting or clearing them.
                        @else
                            You haven't created any bookable activities yet.
                        @endif
                    </p>
                    <div class="mt-6 flex flex-wrap gap-2 justify-center">
                        @if($this->hasActiveFilters)
                            <button type="button"
                                    wire:click="clearFilters"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <span>Clear Filters</span>
                            </button>
                        @endif
                        @if($this->tenantCan('manage properties'))
                            <a href="{{ route('tenant.properties.create') }}" wire:navigate
                               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                      transition-all duration-200 active:scale-95
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add Activity</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="search,typeFilter,statusFilter,perPage,gotoPage,nextPage,previousPage"
                 class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
                @foreach($this->properties as $property)
                    @php
                        $derived        = $this->derivedStatus($property);
                        $statusCfg      = $this->statusConfig($derived);
                        $hasActive      = $this->hasActiveBooking($property->id);
                        $cover          = $property->images->first();
                        $canManage      = $this->tenantCan('manage properties');
                        $stayLabel      = $this->stayLimitsLabel($property);
                    @endphp
                    <article wire:key="property-{{ $property->id }}"
                             class="group bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                    shadow-sm
                                    hover:shadow-md hover:border-primary-300/80 dark:hover:border-primary-500/40
                                    overflow-hidden flex flex-col transition-all duration-200">

                        <div class="relative aspect-video bg-gray-100 dark:bg-gray-900 overflow-hidden">
                            @if($cover)
                                <img src="{{ '/storage/' . ltrim($cover->image_path, '/') }}"
                                     alt="{{ $property->name }}"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105
                                            {{ !$property->is_active ? 'grayscale-[0.6] opacity-80' : '' }}"
                                     loading="lazy"
                                     decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif

                            @if($canManage)
                                <label class="absolute top-3 left-3 z-10 flex items-center justify-center h-11 w-11 sm:h-8 sm:w-8 rounded-lg
                                              bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm
                                              border border-white/80 dark:border-gray-700/80
                                              shadow-sm cursor-pointer
                                              hover:bg-white dark:hover:bg-gray-900
                                              transition-all duration-200
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                    <input type="checkbox"
                                           wire:model.live="selectedProperties"
                                           value="{{ $property->id }}"
                                           aria-label="Select {{ $property->name }}"
                                           class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                </label>
                            @endif

                            <div class="absolute top-3 right-3 z-10">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-sm backdrop-blur-sm {{ $statusCfg['classes'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusCfg['dot'] }} opacity-90" aria-hidden="true"></span>
                                    {{ $statusCfg['label'] }}
                                </span>
                            </div>

                            @if(!$property->is_active)
                                <div class="absolute bottom-3 left-3 z-10">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                                                 bg-gray-900/85 backdrop-blur-sm text-white shadow-sm">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Inactive
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="p-4 flex-1 flex flex-col gap-3">

                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-base leading-snug line-clamp-2 min-h-[2.5rem]">
                                    {{ $property->name }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                                    {{ $property->propertyType->name ?? 'No type' }}
                                </p>
                            </div>

                            <div class="grid grid-cols-3 gap-2 pt-1">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Capacity</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5 tabular-nums">{{ $property->capacity }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Units</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5 tabular-nums">{{ $property->quantity }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Price</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5 tabular-nums">₱{{ number_format($property->price, 2) }}</p>
                                </div>
                            </div>

                            @if($stayLabel)
                                <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="truncate">{{ $stayLabel }}</span>
                                </div>
                            @endif

                            @if($hasActive)
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    @if(isset($this->activeBookings[$property->id]))
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <span class="truncate">{{ $this->activeBookings[$property->id][0]['guest_name'] }} · until {{ $this->activeBookings[$property->id][0]['check_out'] }}</span>
                                        </span>
                                    @elseif(isset($this->upcomingBookings[$property->id]))
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="truncate">Next: {{ $this->upcomingBookings[$property->id][0]['check_in'] }}</span>
                                        </span>
                                    @endif
                                </div>
                            @endif

                            @if($canManage)
                                <div class="pt-2">
                                    <select wire:change="updateStatus({{ $property->id }}, $event.target.value)"
                                            {{ $hasActive ? 'disabled' : '' }}
                                            aria-label="Change status for {{ $property->name }}"
                                            class="w-full h-11 sm:h-9 px-3 rounded-lg text-xs font-semibold border appearance-none cursor-pointer
                                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition-all duration-200
                                                   [touch-action:manipulation]
                                                   disabled:opacity-60 disabled:cursor-not-allowed
                                                   {{ $derived === 'available'   ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30' : '' }}
                                                   {{ $derived === 'occupied'    ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30' : '' }}
                                                   {{ $derived === 'reserved'    ? 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/30' : '' }}
                                                   {{ $derived === 'maintenance' ? 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30' : '' }}">
                                        <option value="available"   {{ $derived === 'available'   ? 'selected' : '' }}>Available</option>
                                        <option value="occupied"    {{ $derived === 'occupied'    ? 'selected' : '' }}>Occupied</option>
                                        <option value="reserved"    {{ $derived === 'reserved'    ? 'selected' : '' }}>Reserved</option>
                                        <option value="maintenance" {{ $derived === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                    </select>
                                    @if($hasActive)
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                                            Status is derived from an active booking.
                                        </p>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-auto pt-3 border-t border-gray-100/70 dark:border-white/[0.04] flex items-center justify-end gap-1">
                                @if($canManage)
                                    <button type="button"
                                            wire:click="toggleActive({{ $property->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleActive"
                                            aria-label="{{ $property->is_active ? 'Deactivate' : 'Activate' }} {{ $property->name }}"
                                            title="{{ $property->is_active ? 'Deactivate' : 'Activate' }}"
                                            class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                                   transition-all duration-200 active:scale-95
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                                   disabled:opacity-60 disabled:cursor-not-allowed
                                                   {{ $property->is_active
                                                      ? 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10'
                                                      : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                        @if($property->is_active)
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                            </svg>
                                        @endif
                                    </button>

                                    <a href="{{ route('tenant.properties.edit', $property->id) }}" wire:navigate
                                       aria-label="Edit {{ $property->name }}"
                                       title="Edit"
                                       class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                              text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/50
                                              transition-all duration-200 active:scale-95
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    <button type="button"
                                            x-data="{
                                                armed: false,
                                                _t: null,
                                                arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                                unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                                destroy() { clearTimeout(this._t); }
                                            }"
                                            @click="armed ? (unarm(), $wire.delete({{ $property->id }})) : arm()"
                                            wire:loading.attr="disabled"
                                            wire:target="delete"
                                            :class="armed
                                                ? 'text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-500/20 ring-2 ring-amber-400/60'
                                                : 'text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50'"
                                            :aria-label="armed ? 'Click again to confirm delete' : 'Delete {{ $property->name }}'"
                                            :title="armed ? 'Click again to confirm' : 'Delete'"
                                            class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                                   transition-all duration-200 active:scale-95
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                                   disabled:opacity-60 disabled:cursor-not-allowed">
                                        <svg x-show="!armed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        <svg x-show="armed" x-cloak class="w-4 h-4 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500 italic">View only</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($this->properties->hasPages())
                <div class="pt-2">
                    {{ $this->properties->links() }}
                </div>
            @endif
        @endif
    </div>
</div>