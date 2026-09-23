
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Payment;
use App\Models\Booking;
use App\Jobs\ProcessPayMongoPayment;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Payments')]
class extends Component {
    use WithPagination;
    use ChecksTenantPermissions;

    #[Url]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $methodFilter = '';
    #[Url]
    public ?string $fromDate = null;
    #[Url]
    public ?string $toDate = null;
    #[Url]
    public string $sortBy = 'newest';

    public function mount(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
        abort_unless($this->tenantCan('view payments'), 403);

        // Auto-reconcile pending PayMongo payments, throttled to at most
        // once every 5 minutes per tenant. Without the throttle, every
        // admin page load would dispatch up to 20 jobs.
        $this->syncPendingPayments(20);
    }

    /**
     * Guard every subsequent Livewire request. mount() runs once; every
     * action (refreshSync, clearFilters, pagination…) is a separate
     * HTTP request that bypasses the route middleware.
     */
    public function hydrate(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
        abort_unless($this->tenantCan('view payments'), 403);
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingMethodFilter(): void { $this->resetPage(); }
    public function updatingFromDate(): void     { $this->resetPage(); }
    public function updatingToDate(): void       { $this->resetPage(); }
    public function updatingSortBy(): void       { $this->resetPage(); }

    /**
     * Dispatch reconciliation jobs for pending PayMongo payments.
     *
     * Canonical `payment_status` values are 'pending' and 'paid' — NOT
     * 'unpaid'. The previous version of this method queried for 'unpaid'
     * (which never matches) and wrapped the dispatch in a transaction with
     * `lockForUpdate()` (which is released before the async jobs run — so
     * it locked nothing). Both were bugs.
     */
    protected function syncPendingPayments(int $limit = 20, bool $force = false): void
    {
        $tenantId = Auth::user()->tenant_id;

        // Throttle: at most one reconciliation sweep per tenant per 5
        // minutes. `Cache::add` is atomic across requests for our drivers.
        if (! $force && ! Cache::add("paymongo_sync:{$tenantId}", true, now()->addMinutes(5))) {
            return;
        }

        try {
            $sessionIds = Payment::query()
                ->where('tenant_id', $tenantId)
                ->where('payment_status', 'pending')
                ->whereNotNull('paymongo_session_id')
                ->latest('id')
                ->limit($limit)
                ->pluck('paymongo_session_id');

            foreach ($sessionIds as $sessionId) {
                try {
                    ProcessPayMongoPayment::dispatch($sessionId);
                } catch (\Throwable $e) {
                    Log::error('Failed to dispatch PayMongo sync', [
                        'tenant_id'  => $tenantId,
                        'session_id' => $sessionId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('syncPendingPayments failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function refreshSync(): void
    {
        $this->requirePermission('view payments');

        $this->syncPendingPayments(50, force: true);
        session()->flash('message', 'Payment statuses synced with PayMongo.');
        $this->resetPage();
    }

    #[Computed]
    public function payments()
    {
        return Payment::query()
            ->with([
                'booking' => fn ($q) => $q
                    ->withoutGlobalScope(TenantScope::class) // ensure all related bookings load
                    ->with([
                        'user:id,name,email,phone',
                        'payments' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)
                            ->select('id', 'booking_id', 'payment_status', 'amount'),
                    ])
                    ->select('id', 'tenant_id', 'user_id', 'total_amount', 'booking_reference', 'status'),
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('reference_number', 'like', '%' . $this->search . '%')
                       ->orWhere('paymongo_session_id', 'like', '%' . $this->search . '%')
                       ->orWhereHas('booking', function ($bq) {
                           $bq->where('booking_reference', 'like', '%' . $this->search . '%')
                              ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', '%' . $this->search . '%'));
                       });
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('payment_status', $this->statusFilter))
            ->when($this->methodFilter, fn ($q) => $q->where('payment_method', $this->methodFilter))
            ->when($this->fromDate && $this->toDate, function ($q) {
                $q->whereBetween('created_at', [
                    Carbon::parse($this->fromDate)->startOfDay(),
                    Carbon::parse($this->toDate)->endOfDay(),
                ]);
            })
            ->when($this->sortBy, function ($q) {
                match ($this->sortBy) {
                    'oldest'      => $q->oldest(),
                    'amount_high' => $q->orderByDesc('amount'),
                    'amount_low'  => $q->orderBy('amount'),
                    default       => $q->latest(),
                };
            })
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        $tid = Auth::user()->tenant_id;

        $agg = Payment::query()
            ->where('tenant_id', $tid)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN payment_status = 'paid'   THEN amount ELSE 0 END), 0) as total_received,
                COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END), 0) as total_pending,
                COALESCE(SUM(CASE WHEN payment_status = 'paid'   THEN 1 ELSE 0 END), 0)      as paid_count,
                COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END), 0)      as pending_count,
                COALESCE(SUM(CASE WHEN payment_type = 'reservation' AND payment_status = 'paid' THEN amount ELSE 0 END), 0) as reservation_fees
            ")
            ->first();

        return [
            'total_received'   => (float) ($agg->total_received   ?? 0),
            'total_pending'    => (float) ($agg->total_pending    ?? 0),
            'paid_count'       => (int)   ($agg->paid_count       ?? 0),
            'pending_count'    => (int)   ($agg->pending_count    ?? 0),
            'reservation_fees' => (float) ($agg->reservation_fees ?? 0),
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || $this->methodFilter !== ''
            || ! empty($this->fromDate)
            || ! empty($this->toDate);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'methodFilter', 'fromDate', 'toDate', 'sortBy']);
        $this->resetPage();
    }
};
?>

<?php
    $s = $this->stats;
    $totalCount = $s['paid_count'] + $s['pending_count'];
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Transactions</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Payments Overview
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Monitor all received and pending payments.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="refreshSync"
                    wire:loading.attr="disabled"
                    wire:target="refreshSync"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/>
                </svg>
                <span wire:loading.remove wire:target="refreshSync">Sync PayMongo</span>
                <span wire:loading wire:target="refreshSync" class="inline-flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Syncing…
                </span>
            </button>
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
                [
                    'label' => 'Total Received',
                    'value' => '₱' . number_format($s['total_received'], 0),
                    'dot'   => 'bg-emerald-500',
                    'sub'   => $s['paid_count'] . ' transaction' . ($s['paid_count'] === 1 ? '' : 's'),
                ],
                [
                    'label' => 'Pending Amount',
                    'value' => '₱' . number_format($s['total_pending'], 0),
                    'dot'   => 'bg-amber-500',
                    'sub'   => $s['pending_count'] > 0 ? $s['pending_count'] . ' awaiting' : null,
                ],
                [
                    'label' => 'Paid Transactions',
                    'value' => number_format($s['paid_count']),
                    'dot'   => 'bg-emerald-500',
                    'sub'   => null,
                ],
                [
                    'label' => 'Reservation Fees',
                    'value' => '₱' . number_format($s['reservation_fees'], 0),
                    'dot'   => 'bg-blue-500',
                    'sub'   => null,
                ],
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
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider mt-0.5"><?php echo e($kpi['sub']); ?></p>
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
                   placeholder="Search by reference, guest, or PayMongo ID…">
        </div>

        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <select wire:model.live="methodFilter" class="input w-full">
                <option value="">All Methods</option>
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
                <option value="paymaya">Maya</option>
                <option value="card">Card</option>
                <option value="qr">QR Code</option>
            </select>
            <select wire:model.live="sortBy" class="input w-full">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
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
                ['value' => '',        'label' => 'All',     'count' => $totalCount],
                ['value' => 'paid',    'label' => 'Paid',    'count' => $s['paid_count']],
                ['value' => 'pending', 'label' => 'Pending', 'count' => $s['pending_count']],
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

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters || $sortBy !== 'newest'): ?>
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

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->payments->isEmpty()): ?>
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="flex flex-col items-center max-w-md mx-auto">
                <svg class="w-14 h-14 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    No payments found
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                        No payments match your current filters. Try adjusting or clearing them.
                    <?php else: ?>
                        When your team records payments, they will appear here.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                    <button type="button" wire:click="clearFilters"
                            class="mt-5 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        Clear Filters
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,statusFilter,methodFilter,fromDate,toDate,sortBy,clearFilters,refreshSync,gotoPage,nextPage,previousPage"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $booking     = $payment->booking;
                    $totalPaid   = $booking?->payments?->where('payment_status', 'paid')->sum('amount') ?? 0;
                    $balance     = $booking ? (float) $booking->total_amount - (float) $totalPaid : 0;
                    $isPaid      = $payment->payment_status === 'paid';
                    $isReserve   = $payment->payment_type === 'reservation';

                    // Method config — full class strings so Tailwind can detect them
                    $methodMeta = match ($payment->payment_method) {
                        'cash'    => ['label' => 'Cash',  'bg' => 'bg-emerald-50 dark:bg-emerald-500/15', 'fg' => 'text-emerald-600 dark:text-emerald-400'],
                        'gcash'   => ['label' => 'GCash', 'bg' => 'bg-blue-50 dark:bg-blue-500/15',    'fg' => 'text-blue-600 dark:text-blue-400'],
                        'paymaya' => ['label' => 'Maya',  'bg' => 'bg-purple-50 dark:bg-purple-500/15','fg' => 'text-purple-600 dark:text-purple-400'],
                        'card'    => ['label' => 'Card',  'bg' => 'bg-gray-100 dark:bg-gray-700',      'fg' => 'text-gray-600 dark:text-gray-300'],
                        'qr'      => ['label' => 'QR',    'bg' => 'bg-indigo-50 dark:bg-indigo-500/15','fg' => 'text-indigo-600 dark:text-indigo-400'],
                        default   => ['label' => ucfirst(str_replace('_', ' ', (string) $payment->payment_method)),
                                      'bg' => 'bg-gray-100 dark:bg-gray-700',
                                      'fg' => 'text-gray-600 dark:text-gray-300'],
                    };
                ?>

                <article <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'payment-'.e($payment->id).''; ?>wire:key="payment-<?php echo e($payment->id); ?>"
                         class="group relative bg-white dark:bg-gray-800/90 rounded-2xl border shadow-sm hover:shadow-md transition-all duration-200 flex flex-col overflow-hidden
                                <?php echo e($isPaid ? 'border-gray-200/80 dark:border-gray-700/80' : 'border-amber-300 dark:border-amber-500/40'); ?>">

                    
                    <div class="p-4 pb-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                                ₱<?php echo e(number_format((float) $payment->amount, 2)); ?>

                            </p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full <?php echo e($methodMeta['bg']); ?> <?php echo e($methodMeta['fg']); ?> text-[10px] font-bold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->payment_method === 'cash'): ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        <?php elseif($payment->payment_method === 'gcash'): ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        <?php elseif($payment->payment_method === 'qr'): ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2m4 0v-1M5 7h6m6 0h2M5 11h6m6 0h2M5 15h6m6 0h2M5 19h6m6 0h2"/>
                                        <?php else: ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </svg>
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo e($methodMeta['label']); ?> · <?php echo e($isReserve ? 'Reservation Fee' : 'Full Payment'); ?>

                                </span>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border shrink-0
                                     <?php echo e($isPaid
                                        ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/40'
                                        : 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/40'); ?>">
                            <span class="w-1 h-1 rounded-full bg-current"></span>
                            <?php echo e(ucfirst($payment->payment_status)); ?>

                        </span>
                    </div>

                    
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Booking</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking): ?>
                            <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate
                               class="font-mono text-sm font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded inline-block">
                                <?php echo e($booking->booking_reference); ?>

                            </a>
                            <p class="text-sm text-gray-700 dark:text-gray-300 truncate mt-0.5">
                                <?php echo e($booking->user?->name ?? 'Walk-in Guest'); ?>

                            </p>
                        <?php else: ?>
                            <p class="text-sm text-gray-400 dark:text-gray-500 italic">Booking not found</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1 text-xs">
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-500 dark:text-gray-400">Date</span>
                            <span class="text-gray-900 dark:text-white tabular-nums">
                                <?php echo e($payment->paid_at?->format('M d, Y · h:i A')
                                    ?? $payment->created_at?->format('M d, Y · h:i A')
                                    ?? '—'); ?>

                            </span>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking): ?>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Booking Balance</span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                    <span class="text-amber-600 dark:text-amber-400 font-semibold tabular-nums">₱<?php echo e(number_format($balance, 2)); ?></span>
                                <?php else: ?>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold inline-flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Settled
                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->reference_number || $payment->paymongo_session_id): ?>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400">Ref</span>
                                <span class="text-gray-700 dark:text-gray-300 font-mono text-[10px] truncate max-w-[160px]">
                                    <?php echo e($payment->reference_number ?? $payment->paymongo_session_id); ?>

                                </span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking): ?>
                        <div class="mt-auto px-3 py-2.5 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end gap-1">
                            <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate
                               aria-label="View booking <?php echo e($booking->booking_reference); ?>"
                               title="View booking"
                               class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                      transition-all duration-200 active:scale-95
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0 && !in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)): ?>
                                <a href="<?php echo e(route('tenant.payments.create', ['booking' => $booking->id])); ?>" wire:navigate
                                   aria-label="Record another payment"
                                   title="Record payment"
                                   class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10
                                          transition-all duration-200 active:scale-95
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 9v2m0-3.5c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->payments->hasPages()): ?>
            <div class="pt-2">
                <?php echo e($this->payments->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\payment\⚡view-payment.blade.php ENDPATH**/ ?>