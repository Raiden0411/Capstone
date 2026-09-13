{{-- resources/views/tenant/pages/event/⚡view-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

new
#[Layout('tenant.layouts.app')]
#[Title('Events')]
class extends Component
{
    use WithPagination;

    #[Url] public string $search       = '';
    #[Url] public string $typeFilter   = '';
    #[Url] public string $statusFilter = '';
    #[Url] public int    $perPage      = 12;

    public function mount(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403, 'No business is linked to your account.');
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingTypeFilter(): void   { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingPerPage(): void      { $this->resetPage(); }

    // ─────────────────────────────────────────────────────────
    //  Filtering
    // ─────────────────────────────────────────────────────────

    private function filteredQuery()
    {
        $tenantId = Auth::user()->tenant_id;

        return Event::query()
            // TenantScope already filters by tenant_id; the explicit where
            // is belt-and-suspenders and gives the query planner a hint.
            ->where('tenant_id', $tenantId)
            ->when($this->search !== '', function ($q) {
                // ⚠️ The whole OR-group must be inside its own closure,
                // otherwise the OR leaks across the tenant-scope boundary.
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

    // ─────────────────────────────────────────────────────────
    //  Data
    // ─────────────────────────────────────────────────────────

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
                SUM(CASE WHEN (end_date IS NOT NULL AND end_date < ?) OR (end_date IS NULL AND start_date < ?) THEN 1 ELSE 0 END) as archived
            ', [$now, $now, $now, $now, $now, $now])
            ->first();

        return [
            'total'    => (int) ($row->total    ?? 0),
            'active'   => (int) ($row->active   ?? 0),
            'upcoming' => (int) ($row->upcoming ?? 0),
            'ongoing'  => (int) ($row->ongoing  ?? 0),
            'archived' => (int) ($row->archived ?? 0),
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->typeFilter !== ''
            || $this->statusFilter !== '';
    }

    // ─────────────────────────────────────────────────────────
    //  Effective status — derived from dates + is_active flag
    // ─────────────────────────────────────────────────────────

    /**
     * @return array{key: string, label: string, classes: string}
     */
    public function effectiveStatus(Event $event): array
    {
        $now = now();

        // Ended — takes priority over the is_active flag
        if ($event->end_date && $event->end_date < $now) {
            return [
                'key'     => 'archived',
                'label'   => 'Ended',
                'classes' => 'bg-slate-100 dark:bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-500/30',
            ];
        }

        // Upcoming
        if ($event->start_date && $event->start_date > $now) {
            return [
                'key'     => 'upcoming',
                'label'   => 'Upcoming',
                'classes' => 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/30',
            ];
        }

        // Manually turned off
        if (!$event->is_active) {
            return [
                'key'     => 'inactive',
                'label'   => 'Inactive',
                'classes' => 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border-red-200 dark:border-red-500/30',
            ];
        }

        // Ongoing
        return [
            'key'     => 'ongoing',
            'label'   => 'Ongoing',
            'classes' => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30',
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────

    public function deleteEvent(int $eventId): void
    {
        $event = Event::findOrFail($eventId); // TenantScope filters
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

        // Only after the DB row is gone.
        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        session()->flash('message', 'Event deleted.');
    }

    public function toggleActive(int $eventId): void
    {
        $event = Event::findOrFail($eventId);
        $this->authorize('update', $event);

        $event->update(['is_active' => !$event->is_active]);

        session()->flash(
            'message',
            $event->is_active ? 'Event activated.' : 'Event deactivated.'
        );
    }

    /**
     * Shift an ended event's dates forward and reactivate it.
     *
     * Shifts both dates by the same delta so the event's duration is
     * preserved. If only one date exists, shifts that one.
     */
    public function reactivate(int $eventId): void
    {
        $event = Event::findOrFail($eventId);
        $this->authorize('update', $event);

        $now = now();

        if ($event->end_date && $event->end_date < $now) {
            $delta      = $event->end_date->diffInSeconds($now) + 86400; // +1 day buffer
            $startShift = $event->start_date ? $event->start_date->copy()->addSeconds($delta) : null;
            $endShift   = $event->end_date->copy()->addSeconds($delta);

            $event->update([
                'start_date' => $startShift,
                'end_date'   => $endShift,
                'is_active'  => true,
            ]);

            session()->flash('message', 'Event reactivated with dates shifted forward.');
            return;
        }

        if (!$event->end_date && $event->start_date && $event->start_date < $now) {
            $delta = $event->start_date->diffInSeconds($now) + 86400;
            $event->update([
                'start_date' => $event->start_date->copy()->addSeconds($delta),
                'is_active'  => true,
            ]);

            session()->flash('message', 'Event reactivated with start date shifted forward.');
            return;
        }

        $event->update(['is_active' => true]);
        session()->flash('message', 'Event reactivated.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'statusFilter']);
        $this->resetPage();
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto space-y-6">

    {{-- Header — tenant eyebrow pattern --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                Events
            </p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Events
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage your business events and see their lifecycle at a glance.
            </p>
        </div>
        <a href="{{ route('tenant.events.create') }}" wire:navigate
           class="btn-primary active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Event
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session()->has('message'))
        <div class="flex items-start gap-3 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-md">
            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">{{ session('message') }}</p>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 p-4 rounded-md">
            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-rose-700 dark:text-rose-300 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Stats --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
        @php
            $cards = [
                ['label' => 'Total',    'value' => $s['total'],    'dot' => 'bg-gray-500',    'value_class' => 'text-gray-900 dark:text-white'],
                ['label' => 'Active',   'value' => $s['active'],   'dot' => 'bg-emerald-500', 'value_class' => 'text-emerald-600 dark:text-emerald-400'],
                ['label' => 'Upcoming', 'value' => $s['upcoming'], 'dot' => 'bg-blue-500',    'value_class' => 'text-blue-600 dark:text-blue-400'],
                ['label' => 'Ongoing',  'value' => $s['ongoing'],  'dot' => 'bg-primary-500', 'value_class' => 'text-primary-600 dark:text-primary-400'],
                ['label' => 'Archived', 'value' => $s['archived'], 'dot' => 'bg-slate-500',   'value_class' => 'text-slate-600 dark:text-slate-400'],
            ];
        @endphp
        @foreach($cards as $card)
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $card['label'] }}</span>
                    <span class="w-2 h-2 rounded-full {{ $card['dot'] }}"></span>
                </div>
                <p class="text-2xl font-bold {{ $card['value_class'] }} mt-2">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name, barangay, or type…"
                       class="input w-full pl-10">
            </div>

            <select wire:model.live="typeFilter" class="input w-full sm:w-auto">
                <option value="">All Types</option>
                @foreach($this->eventTypes as $type)
                    <option value="{{ $type }}" wire:key="type-{{ \Illuminate\Support\Str::slug($type) }}">{{ ucfirst($type) }}</option>
                @endforeach
            </select>

            <select wire:model.live="perPage" class="input w-full sm:w-auto">
                <option value="12">12 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
            </select>
        </div>

        {{-- Status pills --}}
        <div class="flex flex-wrap gap-2">
            @foreach([
                ''         => 'All',
                'active'   => 'Active',
                'upcoming' => 'Upcoming',
                'ongoing'  => 'Ongoing',
                'archived' => 'Archived',
                'inactive' => 'Inactive',
            ] as $val => $label)
                <button type="button"
                        wire:click="$set('statusFilter', '{{ $val }}')"
                        wire:key="pill-{{ $val !== '' ? $val : 'all' }}"
                        class="px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-wide border transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 shrink-0
                               {{ $statusFilter === $val
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-md shadow-primary-600/20'
                                  : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}">
                    {{ $label }}
                </button>
            @endforeach

            @if($this->hasActiveFilters)
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center gap-1 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-wide
                               border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400
                               hover:border-rose-400 hover:text-rose-600 dark:hover:text-rose-400
                               transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Events table --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto" wire:loading.class="opacity-50">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30">
                        <th class="px-4 sm:px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Event</th>
                        <th class="px-4 sm:px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden md:table-cell">Type</th>
                        <th class="px-4 sm:px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden lg:table-cell">Barangay</th>
                        <th class="px-4 sm:px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden lg:table-cell">Date</th>
                        <th class="px-4 sm:px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($this->events as $event)
                        @php
                            $status     = $this->effectiveStatus($event);
                            $isArchived = $status['key'] === 'archived';
                        @endphp
                        <tr wire:key="event-{{ $event->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors
                                   {{ $isArchived ? 'bg-slate-50/40 dark:bg-slate-900/10' : '' }}">
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if($event->image_path)
                                        <img src="{{ asset('storage/' . $event->image_path) }}"
                                             class="w-11 h-11 rounded-lg object-cover border border-gray-200 dark:border-gray-700 shrink-0
                                                    {{ $isArchived ? 'grayscale-[0.5]' : '' }}"
                                             alt="{{ $event->name }}">
                                    @else
                                        <div class="w-11 h-11 rounded-lg bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-sm shrink-0">
                                            {{ strtoupper(substr($event->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">{{ $event->name }}</p>
                                        @if($event->featured)
                                            <span class="inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                                Featured
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden md:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 capitalize">
                                    {{ $event->type }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell text-gray-600 dark:text-gray-300">
                                {{ $event->barangay ?: '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                                <p class="text-sm text-gray-900 dark:text-white">{{ $event->start_date?->format('M d, Y') ?? '—' }}</p>
                                @if($event->end_date)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">to {{ $event->end_date->format('M d, Y') }}</p>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border {{ $status['classes'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {{ $status['label'] }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    @if($isArchived)
                                        <button type="button"
                                                wire:click="reactivate({{ $event->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="reactivate({{ $event->id }})"
                                                wire:confirm="Shift this event's dates forward and reactivate it?"
                                                title="Reactivate"
                                                class="p-1.5 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 rounded-lg transition active:scale-95
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 disabled:opacity-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    @else
                                        <button type="button"
                                                wire:click="toggleActive({{ $event->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="toggleActive({{ $event->id }})"
                                                wire:confirm="{{ $event->is_active ? 'Deactivate this event?' : 'Activate this event?' }}"
                                                title="{{ $event->is_active ? 'Deactivate' : 'Activate' }}"
                                                class="p-1.5 {{ $event->is_active ? 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10' }} rounded-lg transition active:scale-95
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                @if($event->is_active)
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                @endif
                                            </svg>
                                        </button>
                                    @endif

                                    <a href="{{ route('tenant.events.edit', $event) }}" wire:navigate
                                       title="Edit"
                                       class="p-1.5 text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition active:scale-95
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    <button type="button"
                                            wire:click="deleteEvent({{ $event->id }})"
                                            wire:confirm="Delete this event? This cannot be undone."
                                            wire:loading.attr="disabled"
                                            wire:target="deleteEvent({{ $event->id }})"
                                            title="Delete"
                                            class="p-1.5 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition active:scale-95
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 disabled:opacity-50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center max-w-md mx-auto">
                                    <svg class="w-14 h-14 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                                        No events found
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        @if($this->hasActiveFilters)
                                            No events match your current filters. Try adjusting or clearing them.
                                        @else
                                            Get started by creating your first event.
                                        @endif
                                    </p>
                                    <div class="mt-5 flex flex-wrap gap-2 justify-center">
                                        @if($this->hasActiveFilters)
                                            <button type="button" wire:click="clearFilters"
                                                    class="btn-secondary active:scale-95 transition-transform
                                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                                Clear Filters
                                            </button>
                                        @endif
                                        <a href="{{ route('tenant.events.create') }}" wire:navigate
                                           class="btn-primary active:scale-95 transition-transform
                                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                                  inline-flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Add Event
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->events->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30">
                {{ $this->events->links() }}
            </div>
        @endif
    </div>
</div>