{{-- resources/views/tenant/pages/event/⚡view-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use App\Models\Event;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

new
#[Layout('tenant.layouts.app')]
#[Title('Events')]
class extends Component
{
    use WithPagination;
    use ChecksTenantPermissions;

    #[Url(keep: true)] public string $search       = '';
    #[Url(keep: true)] public string $typeFilter   = '';
    #[Url(keep: true)] public string $statusFilter = '';
    #[Url(keep: true)] public int    $perPage      = 12;

    public function mount(): void
    {
        $this->authorizeViewEvents();
    }

    public function hydrate(): void
    {
        $this->authorizeViewEvents();
    }

    protected function authorizeViewEvents(): void
    {
        abort_unless(
            Auth::user()?->tenant_id,
            403,
            'No business is linked to your account.'
        );

        $this->requirePermission('view events');
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingTypeFilter(): void   { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingPerPage(): void      { $this->resetPage(); }

    private function filteredQuery()
    {
        $tenantId = Auth::user()->tenant_id;

        return Event::query()
            ->where('tenant_id', $tenantId)
            ->when($this->search !== '', function ($q) {
                $search = '%' . $this->search . '%';
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', $search)
                        ->orWhere('barangay', 'like', $search)
                        ->orWhere('type', 'like', $search);
                });
            })
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter !== '', function ($q) {
                $now = now();

                match ($this->statusFilter) {
                    'active' => $q->where('is_active', true)
                        ->where(fn ($sub) => $sub->whereNull('end_date')
                            ->orWhere('end_date', '>=', $now)),

                    'upcoming' => $q->where('start_date', '>', $now),

                    'ongoing' => $q->where('start_date', '<=', $now)
                        ->where(fn ($sub) => $sub->whereNull('end_date')
                            ->orWhere('end_date', '>=', $now)),

                    'archived' => $q->where(fn ($sub) => $sub->where('end_date', '<', $now)
                        ->orWhere(fn ($s2) => $s2->whereNull('end_date')
                            ->where('start_date', '<', $now))),

                    'inactive' => $q->where('is_active', false),

                    default => $q,
                };
            });
    }

    #[Computed]
    public function events()
    {
        return $this->filteredQuery()
            ->select('id', 'name', 'barangay', 'type', 'start_date', 'end_date',
                     'tenant_id', 'is_active', 'featured', 'image_path')
            ->orderByDesc('start_date')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function eventTypes()
    {
        return Event::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotNull('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');
    }

    /**
     * @return array{total: int, active: int, upcoming: int, ongoing: int, archived: int, inactive: int}
     */
    #[Computed]
    public function stats(): array
    {
        $now      = now();
        $tenantId = Auth::user()->tenant_id;

        $row = Event::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 AND (end_date IS NULL OR end_date >= ?) THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN start_date > ? THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN start_date <= ? AND (end_date IS NULL OR end_date >= ?) THEN 1 ELSE 0 END) as ongoing,
                SUM(CASE WHEN (end_date IS NOT NULL AND end_date < ?) OR (end_date IS NULL AND start_date < ?) THEN 1 ELSE 0 END) as archived,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
            ', [$now, $now, $now, $now, $now, $now])
            ->first();

        return [
            'total'    => (int) ($row->total    ?? 0),
            'active'   => (int) ($row->active   ?? 0),
            'upcoming' => (int) ($row->upcoming ?? 0),
            'ongoing'  => (int) ($row->ongoing  ?? 0),
            'archived' => (int) ($row->archived ?? 0),
            'inactive' => (int) ($row->inactive ?? 0),
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->typeFilter !== ''
            || $this->statusFilter !== '';
    }

    /**
     * @return array{key: string, label: string, classes: string, dot: string}
     */
    public function effectiveStatus(Event $event): array
    {
        $now = now();

        if ($event->end_date && $event->end_date < $now) {
            return [
                'key'     => 'archived',
                'label'   => 'Ended',
                'classes' => 'bg-slate-600 text-white',
                'dot'     => 'bg-white/90',
            ];
        }

        if ($event->start_date && $event->start_date > $now) {
            return [
                'key'     => 'upcoming',
                'label'   => 'Upcoming',
                'classes' => 'bg-blue-600 text-white',
                'dot'     => 'bg-white/90',
            ];
        }

        if (!$event->is_active) {
            return [
                'key'     => 'inactive',
                'label'   => 'Inactive',
                'classes' => 'bg-rose-600 text-white',
                'dot'     => 'bg-white/90',
            ];
        }

        return [
            'key'     => 'ongoing',
            'label'   => 'Ongoing',
            'classes' => 'bg-emerald-600 text-white',
            'dot'     => 'bg-white/90',
        ];
    }

    public function deleteEvent(int $eventId): void
    {
        $this->requirePermission('manage events');

        $event = Event::findOrFail($eventId);
        $this->authorize('delete', $event);

        $imagePath = $event->image_path;

        try {
            $event->delete();
        } catch (\Throwable $e) {
            Log::error('Tenant event delete failed', [
                'event_id'  => $eventId,
                'tenant_id' => Auth::user()->tenant_id,
                'actor_id'  => Auth::id(),
                'error'     => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to delete the event. Please try again.');
            return;
        }

        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        unset($this->events);
        unset($this->stats);
        unset($this->eventTypes);

        session()->flash('message', 'Event deleted.');
    }

    public function toggleActive(int $eventId): void
    {
        $this->requirePermission('manage events');

        $event = Event::findOrFail($eventId);
        $this->authorize('update', $event);

        $event->update(['is_active' => !$event->is_active]);

        unset($this->events);
        unset($this->stats);

        session()->flash(
            'message',
            $event->is_active ? 'Event activated.' : 'Event deactivated.'
        );
    }

    public function reactivate(int $eventId): void
    {
        $this->requirePermission('manage events');

        $event = Event::findOrFail($eventId);
        $this->authorize('update', $event);

        $now = now();

        if ($event->end_date && $event->end_date < $now) {
            $delta      = $event->end_date->diffInSeconds($now) + 86400;
            $startShift = $event->start_date ? $event->start_date->copy()->addSeconds($delta) : null;
            $endShift   = $event->end_date->copy()->addSeconds($delta);

            $event->update([
                'start_date' => $startShift,
                'end_date'   => $endShift,
                'is_active'  => true,
            ]);

            unset($this->events);
            unset($this->stats);

            session()->flash('message', 'Event reactivated with dates shifted forward.');
            return;
        }

        if (!$event->end_date && $event->start_date && $event->start_date < $now) {
            $delta = $event->start_date->diffInSeconds($now) + 86400;
            $event->update([
                'start_date' => $event->start_date->copy()->addSeconds($delta),
                'is_active'  => true,
            ]);

            unset($this->events);
            unset($this->stats);

            session()->flash('message', 'Event reactivated with start date shifted forward.');
            return;
        }

        $event->update(['is_active' => true]);

        unset($this->events);
        unset($this->stats);

        session()->flash('message', 'Event reactivated.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'statusFilter']);
        $this->resetPage();
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-events-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-events-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

<div class="relative">
    <div class="tenant-events-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        @if(session()->has('message'))
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

        @if(session()->has('error'))
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
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Events</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Events
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Manage your business events and see their lifecycle at a glance.
                </p>
            </div>
            @if($this->tenantCan('manage events'))
                <a href="{{ route('tenant.events.create') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                          transition-all duration-200 active:scale-95
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Add Event</span>
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
                           placeholder="Search by name, barangay, or type…"
                           enterkeyhint="search"
                           aria-label="Search events"
                           class="w-full h-11 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl pl-10 pr-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400
                                  focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                                  [touch-action:manipulation]">
                </div>

                <select wire:model.live="typeFilter"
                        aria-label="Filter by event type"
                        class="h-11 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 text-base sm:text-sm text-gray-900 dark:text-white
                               focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                               [touch-action:manipulation]">
                    <option value="">All Types</option>
                    @foreach($this->eventTypes as $type)
                        <option value="{{ $type }}" wire:key="type-{{ \Illuminate\Support\Str::slug($type) }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>

                <select wire:model.live="perPage"
                        aria-label="Events per page"
                        class="h-11 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 text-base sm:text-sm text-gray-900 dark:text-white
                               focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                               [touch-action:manipulation]">
                    <option value="12">12 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                </select>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
                @php
                    $pills = [
                        ['value' => '',         'label' => 'All',      'count' => $s['total'],    'active' => 'bg-primary-600 border-primary-600 text-white shadow-sm shadow-primary-600/20', 'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600'],
                        ['value' => 'active',   'label' => 'Active',   'count' => $s['active'],   'active' => 'bg-emerald-600 border-emerald-600 text-white shadow-sm shadow-emerald-600/20', 'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-emerald-400 dark:hover:border-emerald-500/60'],
                        ['value' => 'upcoming', 'label' => 'Upcoming', 'count' => $s['upcoming'], 'active' => 'bg-blue-600 border-blue-600 text-white shadow-sm shadow-blue-600/20',       'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-blue-400 dark:hover:border-blue-500/60'],
                        ['value' => 'ongoing',  'label' => 'Ongoing',  'count' => $s['ongoing'],  'active' => 'bg-emerald-600 border-emerald-600 text-white shadow-sm shadow-emerald-600/20', 'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-emerald-400 dark:hover:border-emerald-500/60'],
                        ['value' => 'archived', 'label' => 'Ended',    'count' => $s['archived'], 'active' => 'bg-slate-600 border-slate-600 text-white shadow-sm shadow-slate-600/20',      'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-slate-400 dark:hover:border-slate-500/60'],
                        ['value' => 'inactive', 'label' => 'Inactive', 'count' => $s['inactive'], 'active' => 'bg-rose-600 border-rose-600 text-white shadow-sm shadow-rose-600/20',          'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-rose-400 dark:hover:border-rose-500/60'],
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
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Clear</span>
                    </button>
                @endif
            </div>
        </div>

        @if($this->events->isEmpty())
            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        p-12">
                <div class="flex flex-col items-center max-w-md mx-auto text-center">
                    <div class="p-4 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mb-4">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        No events found
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        @if($this->hasActiveFilters)
                            No events match your current filters. Try adjusting or clearing them.
                        @else
                            Get started by creating your first event.
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
                        @if($this->tenantCan('manage events'))
                            <a href="{{ route('tenant.events.create') }}" wire:navigate
                               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                      transition-all duration-200 active:scale-95
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add Event</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="search,typeFilter,statusFilter,perPage,gotoPage,nextPage,previousPage"
                 class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
                @foreach($this->events as $event)
                    @php
                        $status     = $this->effectiveStatus($event);
                        $isArchived = $status['key'] === 'archived';
                        $canManage  = $this->tenantCan('manage events');
                    @endphp
                    <article wire:key="event-{{ $event->id }}"
                             class="group bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                    shadow-sm
                                    hover:shadow-md hover:border-primary-300/80 dark:hover:border-primary-500/40
                                    overflow-hidden flex flex-col transition-all duration-200">

                        <div class="relative aspect-video bg-gray-100 dark:bg-gray-900 overflow-hidden">
                            @if($event->image_path)
                                <img src="{{ '/storage/' . ltrim($event->image_path, '/') }}"
                                     alt="{{ $event->name }}"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105
                                            {{ $isArchived ? 'grayscale-[0.5]' : '' }}"
                                     loading="lazy"
                                     decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600">
                                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif

                            <div class="absolute top-3 right-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-sm backdrop-blur-sm {{ $status['classes'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }} opacity-90" aria-hidden="true"></span>
                                    {{ $status['label'] }}
                                </span>
                            </div>

                            @if($event->featured)
                                <div class="absolute top-3 left-3">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                                                 bg-amber-500 text-white shadow-sm backdrop-blur-sm">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        Featured
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="p-4 flex-1 flex flex-col gap-3">

                            <h3 class="font-semibold text-gray-900 dark:text-white text-base leading-snug line-clamp-2 min-h-[2.5rem]">
                                {{ $event->name }}
                            </h3>

                            <div class="space-y-1.5 text-xs text-gray-600 dark:text-gray-400">

                                <div class="flex items-center gap-1.5 min-w-0">
                                    <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span class="truncate">{{ $event->barangay ?: 'No barangay' }}</span>
                                    <span class="text-gray-300 dark:text-gray-700 shrink-0" aria-hidden="true">·</span>
                                    <span class="capitalize truncate">{{ $event->type }}</span>
                                </div>

                                <div class="flex items-center gap-1.5 min-w-0">
                                    <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="truncate">
                                        @if($event->start_date)
                                            {{ $event->start_date->format('M d, Y') }}
                                            @if($event->end_date)
                                                – {{ $event->end_date->format('M d, Y') }}
                                            @endif
                                        @else
                                            No dates set
                                        @endif
                                    </span>
                                </div>
                            </div>

                            <div class="mt-auto pt-3 border-t border-gray-100/70 dark:border-white/[0.04] flex items-center justify-end gap-1">
                                @if($canManage)
                                    @if($isArchived)
                                        <button type="button"
                                                x-data="{
                                                    armed: false,
                                                    _t: null,
                                                    arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                                    unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                                    destroy() { clearTimeout(this._t); }
                                                }"
                                                @click="armed ? (unarm(), $wire.reactivate({{ $event->id }})) : arm()"
                                                wire:loading.attr="disabled"
                                                wire:target="reactivate"
                                                :aria-label="armed ? 'Click again to confirm reactivate' : 'Reactivate {{ $event->name }}'"
                                                :title="armed ? 'Click again to confirm' : 'Reactivate'"
                                                :class="armed
                                                    ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-500/20 ring-2 ring-emerald-400/60'
                                                    : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10'"
                                                class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                                       transition-all duration-200 active:scale-95
                                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50
                                                       disabled:opacity-60 disabled:cursor-not-allowed">
                                            <svg x-show="!armed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                            <svg x-show="armed" x-cloak class="w-4 h-4 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                    @else
                                        <button type="button"
                                                x-data="{
                                                    armed: false,
                                                    _t: null,
                                                    arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                                    unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                                    destroy() { clearTimeout(this._t); }
                                                }"
                                                @click="armed ? (unarm(), $wire.toggleActive({{ $event->id }})) : arm()"
                                                wire:loading.attr="disabled"
                                                wire:target="toggleActive"
                                                :aria-label="armed ? 'Click again to confirm' : '{{ $event->is_active ? 'Deactivate' : 'Activate' }} {{ $event->name }}'"
                                                :title="armed ? 'Click again to confirm' : '{{ $event->is_active ? 'Deactivate' : 'Activate' }}'"
                                                :class="armed
                                                    ? 'ring-2 ring-amber-400/60 bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300'
                                                    : '{{ $event->is_active ? 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10' }}'"
                                                class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                                       transition-all duration-200 active:scale-95
                                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                                       disabled:opacity-60 disabled:cursor-not-allowed">
                                            <svg x-show="!armed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                @if($event->is_active)
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                @endif
                                            </svg>
                                            <svg x-show="armed" x-cloak class="w-4 h-4 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                    @endif

                                    <a href="{{ route('tenant.events.edit', $event) }}" wire:navigate
                                       aria-label="Edit {{ $event->name }}"
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
                                            @click="armed ? (unarm(), $wire.deleteEvent({{ $event->id }})) : arm()"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteEvent"
                                            :aria-label="armed ? 'Click again to confirm delete' : 'Delete {{ $event->name }}'"
                                            :title="armed ? 'Click again to confirm' : 'Delete'"
                                            :class="armed
                                                ? 'text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-500/20 ring-2 ring-rose-400/60'
                                                : 'text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50'"
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

            @if($this->events->hasPages())
                <div class="pt-2">
                    {{ $this->events->links() }}
                </div>
            @endif
        @endif
    </div>
</div>