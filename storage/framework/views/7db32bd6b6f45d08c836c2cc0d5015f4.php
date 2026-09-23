
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use App\Models\Event;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

new
#[Layout('superadmin.layouts.app')]
#[Title('Events')]
class extends Component
{
    use WithPagination;

    #[Url(keep: true)] public string $search       = '';
    #[Url(keep: true)] public string $typeFilter   = '';
    #[Url(keep: true)] public string $statusFilter = '';
    #[Url(keep: true)] public int    $perPage      = 12;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    /**
     * Four-layer pattern, Layer 3 — re-verify on every Livewire update
     * request. Route middleware only runs on the initial GET.
     */
    public function hydrate(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingTypeFilter(): void   { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingPerPage(): void      { $this->resetPage(); }

    // ─────────────────────────────────────────────────────────
    //  Filters
    // ─────────────────────────────────────────────────────────

    private function filteredQuery()
    {
        return Event::withoutGlobalScope(TenantScope::class)
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

                    'featured' => $q->where('featured', true),

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
            ->with(['tenant:id,name'])
            ->orderByDesc('start_date')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function eventTypes()
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->select('type')
            ->distinct()
            ->whereNotNull('type')
            ->orderBy('type')
            ->pluck('type');
    }

    /**
     * Aggregate counts via the query builder — returns a plain stdClass
     * rather than a phantom Eloquent Event with only the selectRaw
     * attributes. Safe to cache later if the numbers ever warrant it
     * (Rule 79).
     *
     * @return array{total: int, active: int, upcoming: int, ongoing: int, archived: int, inactive: int, featured: int}
     */
    #[Computed]
    public function stats(): array
    {
        $now = now();

        $row = DB::table('events')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 AND (end_date IS NULL OR end_date >= ?) THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN start_date > ? THEN 1 ELSE 0 END) as upcoming,
                SUM(CASE WHEN start_date <= ? AND (end_date IS NULL OR end_date >= ?) THEN 1 ELSE 0 END) as ongoing,
                SUM(CASE WHEN (end_date IS NOT NULL AND end_date < ?) OR (end_date IS NULL AND start_date < ?) THEN 1 ELSE 0 END) as archived,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured
            ', [$now, $now, $now, $now, $now, $now])
            ->first();

        return [
            'total'    => (int) ($row->total    ?? 0),
            'active'   => (int) ($row->active   ?? 0),
            'upcoming' => (int) ($row->upcoming ?? 0),
            'ongoing'  => (int) ($row->ongoing  ?? 0),
            'archived' => (int) ($row->archived ?? 0),
            'inactive' => (int) ($row->inactive ?? 0),
            'featured' => (int) ($row->featured ?? 0),
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
    //  Per-row helpers
    // ─────────────────────────────────────────────────────────

    public function effectiveStatus(Event $event): array
    {
        $now = now();

        if ($event->start_date && $event->start_date > $now) {
            return [
                'key'     => 'upcoming',
                'label'   => 'Upcoming',
                'classes' => 'bg-blue-600 text-white',
            ];
        }

        if ($event->end_date && $event->end_date < $now) {
            return [
                'key'     => 'archived',
                'label'   => 'Ended',
                'classes' => 'bg-slate-600 text-white',
            ];
        }

        if (!$event->is_active) {
            return [
                'key'     => 'inactive',
                'label'   => 'Inactive',
                'classes' => 'bg-rose-600 text-white',
            ];
        }

        return [
            'key'     => 'ongoing',
            'label'   => 'Ongoing',
            'classes' => 'bg-emerald-600 text-white',
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────

    public function deleteEvent(int $eventId): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $event = Event::withoutGlobalScope(TenantScope::class)->findOrFail($eventId);

        $imagePath = $event->image_path;

        try {
            $event->delete();
        } catch (\Throwable $e) {
            Log::error('Event delete failed', [
                'event_id' => $eventId,
                'actor_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to delete the event. Please try again.');
            return;
        }

        if ($imagePath && Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }

        session()->flash('message', 'Event deleted successfully.');
    }

    public function toggleActive(int $eventId): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $event = Event::withoutGlobalScope(TenantScope::class)->findOrFail($eventId);

        $event->update(['is_active' => !$event->is_active]);

        session()->flash(
            'message',
            $event->is_active ? 'Event activated.' : 'Event deactivated.'
        );
    }

    public function reactivate(int $eventId): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $event = Event::withoutGlobalScope(TenantScope::class)->findOrFail($eventId);

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

            session()->flash('message', 'Event reactivated and dates shifted forward.');
            return;
        }

        if (!$event->end_date && $event->start_date && $event->start_date < $now) {
            $delta = $event->start_date->diffInSeconds($now) + 86400;
            $event->update([
                'start_date' => $event->start_date->copy()->addSeconds($delta),
                'is_active'  => true,
            ]);

            session()->flash('message', 'Event reactivated and start date shifted forward.');
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

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?php echo e(session('message')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?php echo e(session('error')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Events
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage festivals, activities, and community events across the platform.
            </p>
        </div>
        <a href="<?php echo e(route('superadmin.events.create')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                  transition-all duration-200 active:scale-95
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Add Event</span>
        </a>
    </div>

    
    <?php $s = $this->stats; ?>
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-3">

        
        <div class="flex flex-wrap gap-2 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search events by name, barangay, or type…"
                       enterkeyhint="search"
                       aria-label="Search events"
                       class="input w-full"
                       style="padding-left: 2.5rem;">
            </div>

            <select wire:model.live="typeFilter"
                    aria-label="Filter by type"
                    class="input w-full sm:w-auto sm:min-w-[140px]">
                <option value="">All Types</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->eventTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($type); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'type-opt-'.e($loop->index).''; ?>wire:key="type-opt-<?php echo e($loop->index); ?>"><?php echo e(ucfirst($type)); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>

            <select wire:model.live="perPage"
                    aria-label="Events per page"
                    class="input w-full sm:w-auto sm:min-w-[140px]">
                <option value="12">12 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
            </select>
        </div>

        
        <div class="flex flex-wrap gap-2 items-center">
            <?php
                $pills = [
                    ['value' => '',         'label' => 'All',      'count' => $s['total']],
                    ['value' => 'active',   'label' => 'Active',   'count' => $s['active']],
                    ['value' => 'upcoming', 'label' => 'Upcoming', 'count' => $s['upcoming']],
                    ['value' => 'ongoing',  'label' => 'Ongoing',  'count' => $s['ongoing']],
                    ['value' => 'archived', 'label' => 'Archived', 'count' => $s['archived']],
                    ['value' => 'inactive', 'label' => 'Inactive', 'count' => $s['inactive']],
                    ['value' => 'featured', 'label' => 'Featured', 'count' => $s['featured']],
                ];
            ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $pills; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pill): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php $isActive = $statusFilter === $pill['value']; ?>
                <button type="button"
                        wire:click="$set('statusFilter', '<?php echo e($pill['value']); ?>')"
                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'pill-'.e($pill['value'] !== '' ? $pill['value'] : 'all').''; ?>wire:key="pill-<?php echo e($pill['value'] !== '' ? $pill['value'] : 'all'); ?>"
                        aria-pressed="<?php echo e($isActive ? 'true' : 'false'); ?>"
                        class="inline-flex items-center gap-2 h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               <?php echo e($isActive
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                  : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400'); ?>">
                    <span><?php echo e($pill['label']); ?></span>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                 <?php echo e($isActive
                                    ? 'bg-white/20 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'); ?>">
                        <?php echo e($pill['count']); ?>

                    </span>
                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                <button type="button"
                        wire:click="clearFilters"
                        class="inline-flex items-center gap-1 h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                               border border-rose-300 dark:border-rose-500/40
                               bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                               transition-all duration-200 active:scale-95 shrink-0
                               hover:bg-rose-50 dark:hover:bg-rose-500/10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->events->isEmpty()): ?>
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12">
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
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                        No events match your current filters. Try adjusting or clearing them.
                    <?php else: ?>
                        Get started by creating the first event on the platform.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
                <div class="mt-6 flex flex-wrap gap-2 justify-center">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                        <button type="button"
                                wire:click="clearFilters"
                                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <span>Clear Filters</span>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <a href="<?php echo e(route('superadmin.events.create')); ?>" wire:navigate
                       class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Add Event</span>
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,typeFilter,statusFilter,perPage,gotoPage,nextPage,previousPage"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $status     = $this->effectiveStatus($event);
                    $isArchived = $status['key'] === 'archived';
                ?>
                <article <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'event-'.e($event->id).''; ?>wire:key="event-<?php echo e($event->id); ?>"
                         class="group bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm
                                hover:shadow-md hover:border-primary-300 dark:hover:border-primary-500/40
                                overflow-hidden flex flex-col transition-all duration-200">

                    
                    <div class="relative aspect-video bg-gray-100 dark:bg-gray-900 overflow-hidden">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->image_path): ?>
                            <img src="<?php echo e(asset('storage/' . $event->image_path)); ?>"
                                 alt="<?php echo e($event->name); ?>"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105
                                        <?php echo e($isArchived ? 'grayscale-[0.5]' : ''); ?>"
                                 loading="lazy"
                                 decoding="async">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600">
                                <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <div class="absolute top-3 right-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-sm backdrop-blur-sm <?php echo e($status['classes']); ?>">
                                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-90" aria-hidden="true"></span>
                                <?php echo e($status['label']); ?>

                            </span>
                        </div>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->featured): ?>
                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                                             bg-amber-500 text-white shadow-sm backdrop-blur-sm">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    Featured
                                </span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div class="p-4 flex-1 flex flex-col gap-3">

                        <h3 class="font-semibold text-gray-900 dark:text-white text-base leading-snug line-clamp-2 min-h-[2.5rem]">
                            <?php echo e($event->name); ?>

                        </h3>

                        <div class="space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="truncate"><?php echo e($event->barangay ?: 'No barangay'); ?></span>
                                <span class="text-gray-300 dark:text-gray-700 shrink-0" aria-hidden="true">·</span>
                                <span class="capitalize truncate"><?php echo e($event->type); ?></span>
                            </div>

                            <div class="flex items-center gap-1.5 min-w-0">
                                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="truncate tabular-nums">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->start_date): ?>
                                        <?php echo e($event->start_date->format('M d, Y')); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->end_date): ?>
                                            – <?php echo e($event->end_date->format('M d, Y')); ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php else: ?>
                                        No dates set
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 min-w-0">
                                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span class="truncate"><?php echo e($event->tenant->name ?? 'Platform-wide'); ?></span>
                            </div>
                        </div>

                        
                        <div class="mt-auto pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isArchived): ?>
                                
                                <button type="button"
                                        x-on:click="if (confirm('Shift this event\'s dates forward and reactivate it?')) $wire.reactivate(<?php echo e($event->id); ?>)"
                                        wire:loading.attr="disabled"
                                        wire:target="reactivate"
                                        aria-label="Reactivate <?php echo e($event->name); ?>"
                                        title="Reactivate"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-lg
                                               text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10
                                               transition-all duration-200 active:scale-95
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50
                                               disabled:opacity-60 disabled:cursor-not-allowed">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                </button>
                            <?php else: ?>
                                
                                <button type="button"
                                        x-on:click="if (confirm('<?php echo e($event->is_active ? 'Deactivate' : 'Activate'); ?> this event?')) $wire.toggleActive(<?php echo e($event->id); ?>)"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleActive"
                                        aria-label="<?php echo e($event->is_active ? 'Deactivate' : 'Activate'); ?> <?php echo e($event->name); ?>"
                                        title="<?php echo e($event->is_active ? 'Deactivate' : 'Activate'); ?>"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-lg
                                               transition-all duration-200 active:scale-95
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                               disabled:opacity-60 disabled:cursor-not-allowed
                                               <?php echo e($event->is_active
                                                  ? 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10'
                                                  : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10'); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->is_active): ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        <?php else: ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </svg>
                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <a href="<?php echo e(route('superadmin.events.edit', $event)); ?>" wire:navigate
                               aria-label="Edit <?php echo e($event->name); ?>"
                               title="Edit"
                               class="inline-flex items-center justify-center h-9 w-9 rounded-lg
                                      text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10
                                      transition-all duration-200 active:scale-95
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>

                            
                            <button type="button"
                                    x-on:click="if (confirm('Are you sure you want to delete this event? This cannot be undone.')) $wire.deleteEvent(<?php echo e($event->id); ?>)"
                                    wire:loading.attr="disabled"
                                    wire:target="deleteEvent"
                                    aria-label="Delete <?php echo e($event->name); ?>"
                                    title="Delete"
                                    class="inline-flex items-center justify-center h-9 w-9 rounded-lg
                                           text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->events->hasPages()): ?>
            <div class="pt-2">
                <?php echo e($this->events->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\event\⚡view-event.blade.php ENDPATH**/ ?>