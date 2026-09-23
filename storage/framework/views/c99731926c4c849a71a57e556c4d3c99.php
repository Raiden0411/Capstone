
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Booking;
use App\Models\User;
use App\Models\Property;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new
#[Layout('tenant.layouts.app')]
#[Title('Active Bookings')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public ?string $fromDate = null;
    #[Url]
    public ?string $toDate = null;
    #[Url]
    public ?int $userFilter = null;
    #[Url]
    public string $sortBy = 'newest';
    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->processMaintenanceTasks();
    }

    /**
     * Guard every subsequent Livewire request. mount() only runs once; every
     * action (delete, cancelBooking…) is a separate HTTP request.
     */
    public function hydrate(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
    }

    /**
     * Cancels overdue bookings and confirms fully-paid ones.
     *
     * Throttled: `Cache::add` is atomic across requests for our drivers, so
     * only one request per minute per tenant actually runs the work. The
     * payment deadline is 30 minutes and PayMongo enforces its own timeout,
     * so a ≤60s delay in the safety net is invisible.
     *
     * Never rethrows — the page must render regardless.
     */
    protected function processMaintenanceTasks(): void
    {
        try {
            $tenantId = Auth::user()->tenant_id;
            $cacheKey = "booking_maint:{$tenantId}";

            if (! Cache::add($cacheKey, true, now()->addMinute())) {
                return;
            }

            $hasPending = Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_RESERVED])
                ->exists();

            if (! $hasPending) {
                return;
            }

            DB::transaction(function () use ($tenantId): void {
                $deadline = now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES);

                $overdueBookings = Booking::withoutGlobalScope(TenantScope::class)
                    ->with(['items' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                    ->where('tenant_id', $tenantId)
                    ->where('status', Booking::STATUS_PENDING)
                    ->where('created_at', '<=', $deadline)
                    ->lockForUpdate()
                    ->get();

                if ($overdueBookings->isNotEmpty()) {
                    $propertyIds = $overdueBookings
                        ->flatMap->items
                        ->pluck('property_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if (! empty($propertyIds)) {
                        Property::withoutGlobalScope(TenantScope::class)
                            ->where('tenant_id', $tenantId)
                            ->whereIn('id', $propertyIds)
                            ->update(['status' => 'available']);
                    }

                    Booking::withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $overdueBookings->pluck('id'))
                        ->update(['status' => Booking::STATUS_CANCELLED]);
                }

                $pendingBookings = Booking::withoutGlobalScope(TenantScope::class)
                    ->with(['payments' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_RESERVED])
                    ->get();

                $confirmIds = $pendingBookings
                    ->filter(fn ($b) => $b->payments->where('payment_status', 'paid')->sum('amount') >= $b->total_amount)
                    ->pluck('id')
                    ->all();

                if (! empty($confirmIds)) {
                    Booking::withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $confirmIds)
                        ->update(['status' => Booking::STATUS_CONFIRMED]);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Booking maintenance task failed', [
                'tenant_id' => Auth::user()?->tenant_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingFromDate(): void     { $this->resetPage(); }
    public function updatingToDate(): void       { $this->resetPage(); }
    public function updatingUserFilter(): void   { $this->resetPage(); }
    public function updatingSortBy(): void       { $this->resetPage(); }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function delete(int $id): void
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        $this->authorize('delete', $booking);

        $ref = $booking->booking_reference;
        $booking->delete();
        session()->flash('message', "Booking #{$ref} deleted.");
    }

    public function cancelBooking(int $id): void
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        $this->authorize('update', $booking);

        if (in_array($booking->status, [
            Booking::STATUS_PENDING,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_RESERVED,
            Booking::STATUS_CHECKED_IN,
        ], true)) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
            session()->flash('message', "Booking #{$booking->booking_reference} has been cancelled.");
        } else {
            session()->flash('error', 'This booking cannot be cancelled.');
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'fromDate', 'toDate', 'userFilter', 'sortBy']);
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->whereHas('bookings', fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    private function query()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user:id,name,email,phone',
                'items.property:id,name',
                'services.service:id,name',
                'payments' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)
                    ->select('id', 'booking_id', 'payment_status', 'amount'),
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])
            ->when($this->search, fn ($q) => $q->where(fn ($q2) =>
                $q2->where('booking_reference', 'like', '%' . $this->search . '%')
                   ->orWhereHas('user', fn ($c) => $c->where('name', 'like', '%' . $this->search . '%'))
            ))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->fromDate && $this->toDate, function ($q) {
                $q->whereBetween('check_in', [
                    Carbon::parse($this->fromDate)->startOfDay(),
                    Carbon::parse($this->toDate)->endOfDay(),
                ]);
            })
            ->when($this->sortBy, function ($q) {
                match ($this->sortBy) {
                    'check_in_asc'  => $q->orderBy('check_in', 'asc'),
                    'check_in_desc' => $q->orderBy('check_in', 'desc'),
                    'amount_high'   => $q->orderByDesc('total_amount'),
                    'amount_low'    => $q->orderBy('total_amount'),
                    default         => $q->latest(),
                };
            });
    }

    #[Computed]
    public function bookings()
    {
        return $this->query()->paginate(12);
    }

    #[Computed]
    public function stats()
    {
        $tid      = Auth::user()->tenant_id;
        $deadline = now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES)->toDateTimeString();
        $today    = today()->toDateString();

        $agg = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->selectRaw("
                SUM(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' AND created_at <= ? THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN status IN ('confirmed', 'checked_in', 'completed') THEN total_amount ELSE 0 END) as revenue,
                SUM(CASE WHEN DATE(check_in) = ? AND status != 'cancelled' THEN 1 ELSE 0 END) as today_arrivals,
                SUM(CASE WHEN DATE(check_out) = ? AND status != 'cancelled' THEN 1 ELSE 0 END) as today_departures
            ", [$deadline, $today, $today])
            ->first();

        $availableCount = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->where('is_active', true)
            ->where('status', 'available')
            ->count();

        return [
            'total'            => $agg->total            ?? 0,
            'pending'          => $agg->pending          ?? 0,
            'reserved'         => $agg->reserved         ?? 0,
            'confirmed'        => $agg->confirmed        ?? 0,
            'checked_in'       => $agg->checked_in       ?? 0,
            'completed'        => $agg->completed        ?? 0,
            'overdue'          => $agg->overdue          ?? 0,
            'revenue'          => $agg->revenue          ?? 0,
            'today_arrivals'   => $agg->today_arrivals   ?? 0,
            'today_departures' => $agg->today_departures ?? 0,
            'available'        => $availableCount,
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || !empty($this->fromDate)
            || !empty($this->toDate)
            || !empty($this->userFilter);
    }
};
?>

<?php $s = $this->stats; ?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Bookings</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Active Bookings
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage current reservations, arrivals, and payments.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?php echo e(route('tenant.bookings.history')); ?>" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>History</span>
            </a>
            <button type="button" wire:click="$refresh"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/>
                </svg>
                <span>Refresh</span>
            </button>
            <a href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                      transition-all duration-200 active:scale-95
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>New Reservation</span>
            </a>
        </div>
    </div>

    
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

    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php
            $kpis = [
                ['label' => 'Arrivals Today',   'value' => $s['today_arrivals'],   'dot' => 'bg-emerald-500'],
                ['label' => 'Departures Today', 'value' => $s['today_departures'], 'dot' => 'bg-rose-500'],
                [
                    'label' => 'Pending',
                    'value' => $s['pending'],
                    'dot'   => 'bg-amber-500',
                    'sub'   => $s['overdue'] > 0 ? "{$s['overdue']} overdue" : null,
                ],
                ['label' => 'Revenue',          'value' => '₱' . number_format((float) $s['revenue'], 0), 'dot' => 'bg-emerald-500'],
            ];
        ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $kpis; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kpi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'kpi-'.e($loop->index).''; ?>wire:key="kpi-<?php echo e($loop->index); ?>"
                 class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo e($kpi['dot']); ?>"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400"><?php echo e($kpi['label']); ?></span>
                </div>
                <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums"><?php echo e($kpi['value']); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($kpi['sub'])): ?>
                    <p class="text-[10px] text-rose-600 dark:text-rose-400 font-bold uppercase tracking-wider mt-0.5"><?php echo e($kpi['sub']); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-4">

        
        <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   class="input w-full"
                   style="padding-left: 2.5rem;"
                   placeholder="Search reference or guest…">
        </div>

        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <select wire:model.live="userFilter" class="input w-full">
                <option value="">All Guests</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($user->id); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'guest-opt-'.e($user->id).''; ?>wire:key="guest-opt-<?php echo e($user->id); ?>"><?php echo e($user->name); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <select wire:model.live="sortBy" class="input w-full">
                <option value="newest">Newest Created</option>
                <option value="check_in_asc">Start (Earliest)</option>
                <option value="check_in_desc">Start (Latest)</option>
                <option value="amount_high">Amount (High → Low)</option>
                <option value="amount_low">Amount (Low → High)</option>
            </select>
        </div>

        
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Date Range</p>
            <div class="grid grid-cols-2 gap-2">
                <input type="date"
                       wire:model.live="fromDate"
                       class="input w-full"
                       aria-label="From date">
                <input type="date"
                       wire:model.live="toDate"
                       class="input w-full"
                       aria-label="To date">
            </div>
        </div>

        
        <?php
            $pills = [
                ['value' => '',           'label' => 'All',        'count' => $s['total']],
                ['value' => 'pending',    'label' => 'Pending',    'count' => $s['pending']],
                ['value' => 'reserved',   'label' => 'Reserved',   'count' => $s['reserved']],
                ['value' => 'confirmed',  'label' => 'Confirmed',  'count' => $s['confirmed']],
                ['value' => 'checked_in', 'label' => 'Checked In', 'count' => $s['checked_in']],
            ];
        ?>
        <div class="flex flex-wrap gap-2 items-center pt-1">
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

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s['overdue'] > 0): ?>
                <span class="inline-flex items-center gap-1.5 h-9 px-3 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <?php echo e($s['overdue']); ?> Overdue
                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center gap-1 h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                               border border-rose-300 dark:border-rose-500/40
                               bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                               transition-all duration-200 active:scale-95
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

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->isEmpty()): ?>
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="flex flex-col items-center max-w-md mx-auto">
                <svg class="w-14 h-14 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    No bookings found
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                        No bookings match your current filters. Try adjusting or clearing them.
                    <?php else: ?>
                        You don't have any active bookings yet. Create your first reservation to get started.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
                <div class="mt-5 flex flex-wrap gap-2 justify-center">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                        <button type="button" wire:click="clearFilters"
                                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            Clear Filters
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <a href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate
                       class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>New Reservation</span>
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,statusFilter,fromDate,toDate,userFilter,sortBy,clearFilters,gotoPage,nextPage,previousPage"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $paid    = (float) $booking->payments->where('payment_status', 'paid')->sum('amount');
                    $balance = (float) $booking->total_amount - $paid;

                    $isOverdue = $booking->status === Booking::STATUS_PENDING
                        && $balance > 0
                        && $booking->created_at
                        && $booking->created_at->copy()->addMinutes(Booking::PAYMENT_DEADLINE_MINUTES)->isPast();

                    $isToday = $booking->check_in?->isToday();

                    $days = ($booking->check_in && $booking->check_out)
                        ? max(1, $booking->check_in->diffInDays($booking->check_out))
                        : 0;

                    $minsLeft = max(0, Booking::PAYMENT_DEADLINE_MINUTES - $booking->created_at->diffInMinutes(now()));

                    $statusConfig = match ($booking->status) {
                        'pending'    => ['bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/40', 'Pending'],
                        'reserved'   => ['bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/40', 'Reserved'],
                        'confirmed'  => ['bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/40', 'Confirmed'],
                        'checked_in' => ['bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-500/40', 'Checked In'],
                        'completed'  => ['bg-slate-100 dark:bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-500/40', 'Completed'],
                        'cancelled'  => ['bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/40', 'Cancelled'],
                        default      => ['bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600', ucfirst($booking->status)],
                    };
                ?>

                <article <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'booking-'.e($booking->id).''; ?>wire:key="booking-<?php echo e($booking->id); ?>"
                         class="group relative bg-white dark:bg-gray-800/90 rounded-2xl border shadow-sm hover:shadow-md transition-all duration-200 flex flex-col overflow-hidden
                                <?php echo e($isOverdue ? 'border-rose-300 dark:border-rose-500/40' : 'border-gray-200/80 dark:border-gray-700/80'); ?>">

                    <div class="p-4 pb-3">
                        <div class="flex items-start justify-between gap-3 mb-1">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-11 h-11 rounded-full bg-blue-50 dark:bg-blue-500/15 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-sm shrink-0">
                                    <?php echo e(strtoupper(substr($booking->user->name ?? 'G', 0, 1))); ?>

                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                        <?php echo e($booking->user->name ?? 'Walk-in Guest'); ?>

                                    </p>
                                    <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                        <?php echo e($booking->booking_reference); ?>

                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border shrink-0
                                         <?php echo e($statusConfig[0]); ?>">
                                <span class="w-1 h-1 rounded-full bg-current"></span>
                                <?php echo e($statusConfig[1]); ?>

                            </span>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isToday): ?>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                             bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300
                                             text-[10px] font-bold uppercase tracking-wider">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Arriving Today
                                </span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1">
                        <div class="flex items-center gap-2 text-xs">
                            <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300 tabular-nums truncate">
                                <?php echo e($booking->check_in?->format('M d, H:i') ?? '—'); ?>

                                <span class="text-gray-400 dark:text-gray-500 mx-0.5">→</span>
                                <?php echo e($booking->check_out?->format('M d, H:i') ?? '—'); ?>

                            </span>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($days > 0): ?>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 pl-5.5"><?php echo e($days); ?> day<?php echo e($days != 1 ? 's' : ''); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1 text-xs">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items->take(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="flex justify-between gap-2" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'card-item-'.e($item->id).''; ?>wire:key="card-item-<?php echo e($item->id); ?>">
                                <span class="text-gray-600 dark:text-gray-400 truncate">
                                    <?php echo e($item->property->name ?? 'Unknown'); ?> <span class="text-gray-400 dark:text-gray-500">×<?php echo e($item->quantity); ?></span>
                                </span>
                                <span class="text-gray-900 dark:text-white tabular-nums shrink-0">₱<?php echo e(number_format((float) $item->subtotal, 0)); ?></span>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->items->count() > 2): ?>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 pt-0.5">
                                +<?php echo e($booking->items->count() - 2); ?> more item<?php echo e($booking->items->count() - 2 != 1 ? 's' : ''); ?>

                            </p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->services->isNotEmpty()): ?>
                            <div class="pt-1.5 mt-1.5 border-t border-dashed border-gray-200 dark:border-gray-700 flex justify-between text-[11px] text-gray-500 dark:text-gray-400">
                                <span><?php echo e($booking->services->count()); ?> add-on service<?php echo e($booking->services->count() != 1 ? 's' : ''); ?></span>
                                <span class="tabular-nums">₱<?php echo e(number_format((float) $booking->services->sum('subtotal'), 0)); ?></span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                                ₱<?php echo e(number_format((float) $booking->total_amount, 0)); ?>

                            </p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                <p class="text-[11px] text-rose-600 dark:text-rose-400 tabular-nums mt-1">
                                    ₱<?php echo e(number_format($balance, 0)); ?> due
                                </p>
                            <?php else: ?>
                                <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1 inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Paid in full
                                </p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->status === 'pending' && $balance > 0): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider tabular-nums
                                         <?php echo e($isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400'); ?>">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <?php echo e($isOverdue ? 'Overdue' : floor($minsLeft) . 'm left'); ?>

                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="mt-auto px-3 py-2.5 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end gap-1">
                        <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate
                           aria-label="View booking <?php echo e($booking->booking_reference); ?>"
                           title="View"
                           class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </a>

                        <a href="<?php echo e(route('tenant.bookings.edit', $booking->id)); ?>" wire:navigate
                           aria-label="Edit booking <?php echo e($booking->booking_reference); ?>"
                           title="Edit"
                           class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->status === 'pending' && $balance > 0): ?>
                            <button type="button"
                                    x-on:click="if (confirm('Cancel booking #<?php echo e($booking->booking_reference); ?>?')) $wire.cancelBooking(<?php echo e($booking->id); ?>)"
                                    aria-label="Cancel booking <?php echo e($booking->booking_reference); ?>"
                                    title="Cancel"
                                    class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <button type="button"
                                x-on:click="if (confirm('Delete booking #<?php echo e($booking->booking_reference); ?>?')) $wire.delete(<?php echo e($booking->id); ?>)"
                                aria-label="Delete booking <?php echo e($booking->booking_reference); ?>"
                                title="Delete"
                                class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->hasPages()): ?>
            <div class="pt-2">
                <?php echo e($this->bookings->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\booking\⚡view-booking.blade.php ENDPATH**/ ?>