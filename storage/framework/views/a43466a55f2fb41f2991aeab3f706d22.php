
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Payment;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Business Dashboard')]
class extends Component
{
    use ChecksTenantPermissions;

    public string $dateRange   = 'this-month';
    public string $customStart = '';
    public string $customEnd   = '';

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorizeDashboard();

        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd   = now()->endOfMonth()->format('Y-m-d');
    }

    /**
     * Livewire re-hydrates this component on every subsequent request.
     * Route middleware (`IsTenantAdmin`) only runs on the original GET.
     *
     * NOTE: this route was previously ungated — any employee with ≥1
     * permission could see tenant-wide revenue/bookings. The dashboard
     * is analytics-grade content, so it's gated by `view analytics`.
     */
    public function hydrate(): void
    {
        $this->authorizeDashboard();
    }

    protected function authorizeDashboard(): void
    {
        abort_unless(
            Auth::user()?->tenant_id,
            403,
            'No business is linked to your account.'
        );

        $this->requirePermission('view analytics');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    #[Computed]
    public function dateBounds(): array
    {
        $now = now();

        return match ($this->dateRange) {
            'today'      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last-7'     => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last-30'    => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this-month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last-month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'custom'     => [
                $this->customStart !== ''
                    ? Carbon::parse($this->customStart)->startOfDay()
                    : $now->copy()->startOfMonth(),
                $this->customEnd !== ''
                    ? Carbon::parse($this->customEnd)->endOfDay()
                    : $now->copy()->endOfMonth(),
            ],
            default      => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    // ─────────────────────────────────────────────────────────
    //  Stats
    // ─────────────────────────────────────────────────────────

    /**
     * @return array<string, float|int>
     */
    #[Computed]
    public function stats(): array
    {
        [$start, $end] = $this->dateBounds;
        $tenantId      = Auth::user()->tenant_id;

        $revenue = (float) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $bookingAgg = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as period_bookings,
                COALESCE(COUNT(DISTINCT CASE WHEN created_at BETWEEN ? AND ? THEN user_id END), 0) as period_guests,
                COALESCE(SUM(CASE WHEN status NOT IN ("cancelled","completed") AND check_in <= ? AND check_out > ? THEN 1 ELSE 0 END), 0) as active_count,
                COALESCE(SUM(CASE WHEN DATE(check_in)  = CURDATE() THEN 1 ELSE 0 END), 0) as arrivals,
                COALESCE(SUM(CASE WHEN DATE(check_out) = CURDATE() THEN 1 ELSE 0 END), 0) as departures
            ', [$start, $end, $start, $end, $end, $start])
            ->first();

        $totalBookings  = (int) ($bookingAgg?->period_bookings ?? 0);
        $totalGuests    = (int) ($bookingAgg?->period_guests   ?? 0);
        $activeBookings = (int) ($bookingAgg?->active_count    ?? 0);
        $arrivalsToday  = (int) ($bookingAgg?->arrivals        ?? 0);
        $departuresToday= (int) ($bookingAgg?->departures      ?? 0);

        $totalProperties = (int) Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->count();

        $occupiedProperties = (int) Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('status', 'occupied')
            ->count();

        $outstandingBalance = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
            ->withSum(
                ['payments as paid_amount' => fn ($q) => $q->where('payment_status', 'paid')],
                'amount',
            )
            ->get(['id', 'total_amount'])
            ->sum(fn ($b) => max(0, (float) $b->total_amount - (float) ($b->paid_amount ?? 0)));

        $occupancy       = $totalProperties > 0 ? round(($activeBookings / $totalProperties) * 100, 1) : 0.0;
        $avgBookingValue = $totalBookings   > 0 ? round($revenue / $totalBookings, 2) : 0.0;

        return [
            'revenue'             => $revenue,
            'total_bookings'      => $totalBookings,
            'total_guests'        => $totalGuests,
            'occupancy_rate'      => $occupancy,
            'avg_booking_value'   => $avgBookingValue,
            'outstanding_balance' => (float) $outstandingBalance,
            'repeat_guest_rate'   => $this->repeatGuestRate,
            'arrivals_today'      => $arrivalsToday,
            'departures_today'    => $departuresToday,
            'occupied_properties' => $occupiedProperties,
            'total_properties'    => $totalProperties,
        ];
    }

    #[Computed]
    public function repeatGuestRate(): float
    {
        $tenantId = Auth::user()->tenant_id;

        $userCounts = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select('user_id', DB::raw('COUNT(*) as bookings'))
            ->groupBy('user_id')
            ->pluck('bookings', 'user_id');

        $total  = $userCounts->count();
        $repeat = $userCounts->filter(fn ($count) => $count > 1)->count();

        return $total > 0 ? round(($repeat / $total) * 100, 1) : 0.0;
    }

    // ─────────────────────────────────────────────────────────
    //  Recent activity
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function recentBookings()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with(['user:id,name'])
            ->select('id', 'user_id', 'booking_reference', 'check_in', 'total_amount', 'status')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->orderByDesc('created_at')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function upcomingArrivals()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with(['user:id,name'])
            ->select('id', 'user_id', 'booking_reference', 'check_in')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereDate('check_in', '>=', now())
            ->orderBy('check_in')
            ->take(3)
            ->get();
    }

    #[Computed]
    public function recentPayments()
    {
        return Payment::query()
            ->with(['booking:id,booking_reference'])
            ->select('id', 'booking_id', 'amount', 'paid_at', 'reference_number')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->take(3)
            ->get();
    }
};
?>

<?php
    $s = $this->stats;

    $primaryKpis = [
        [
            'label'    => 'Revenue',
            'value'    => '₱' . number_format($s['revenue'], 2),
            'subtitle' => 'Paid this period',
            'bg'       => 'bg-emerald-50 dark:bg-emerald-500/15',
            'fg'       => 'text-emerald-600 dark:text-emerald-400',
            'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>',
        ],
        [
            'label'    => 'Bookings',
            'value'    => number_format($s['total_bookings']),
            'subtitle' => $s['total_guests'] > 0
                            ? 'from ' . number_format($s['total_guests']) . ' unique ' . \Illuminate\Support\Str::plural('guest', (int) $s['total_guests'])
                            : 'Created this period',
            'bg'       => 'bg-primary-50 dark:bg-primary-500/15',
            'fg'       => 'text-primary-600 dark:text-primary-400',
            'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        ],
        [
            'label'    => 'Occupancy',
            'value'    => $s['occupancy_rate'] . '%',
            'subtitle' => $s['total_properties'] > 0
                            ? $s['occupied_properties'] . ' of ' . $s['total_properties'] . ' occupied'
                            : 'No properties yet',
            'bg'       => 'bg-blue-50 dark:bg-blue-500/15',
            'fg'       => 'text-blue-600 dark:text-blue-400',
            'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
        ],
        [
            'label'    => 'Outstanding',
            'value'    => '₱' . number_format($s['outstanding_balance'], 2),
            'subtitle' => 'Unpaid balance across active bookings',
            'bg'       => 'bg-amber-50 dark:bg-amber-500/15',
            'fg'       => 'text-amber-600 dark:text-amber-400',
            'icon'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ],
    ];

    $secondaryKpis = [
        [
            'label' => 'Arrivals Today',
            'value' => number_format($s['arrivals_today']),
            'dot'   => 'bg-emerald-500',
        ],
        [
            'label' => 'Departures Today',
            'value' => number_format($s['departures_today']),
            'dot'   => 'bg-rose-500',
        ],
        [
            'label' => 'Avg Booking Value',
            'value' => '₱' . number_format($s['avg_booking_value'], 2),
            'dot'   => 'bg-slate-500',
        ],
        [
            'label' => 'Repeat Guest Rate',
            'value' => $s['repeat_guest_rate'] . '%',
            'dot'   => 'bg-purple-500',
        ],
    ];
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6" wire:poll.60s>

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div class="min-w-0">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Dashboard</span>
            </div>
            <h1 class="font-display text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white tracking-tight truncate">
                <?php echo e(Auth::user()?->tenant?->name ?? 'Business Dashboard'); ?>

            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Overview of your property performance and activity.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->tenantCan('create bookings')): ?>
                <a href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                          transition-all duration-200 active:scale-95
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>New Booking</span>
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->tenantCan('view analytics')): ?>
                <a href="<?php echo e(route('tenant.analytics.index')); ?>" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                          transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l4-4 4 4 5-5"/>
                    </svg>
                    <span>Analytics</span>
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1.5 shrink-0">
                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden sm:inline">Period</span>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 flex-1 min-w-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    'today'      => 'Today',
                    'yesterday'  => 'Yesterday',
                    'last-7'     => '7D',
                    'last-30'    => '30D',
                    'this-month' => 'This Month',
                    'last-month' => 'Last Month',
                    'custom'     => 'Custom',
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php $isActive = $dateRange === $val; ?>
                    <button type="button"
                            <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'range-'.e($val).''; ?>wire:key="range-<?php echo e($val); ?>"
                            wire:click="$set('dateRange', '<?php echo e($val); ?>')"
                            aria-pressed="<?php echo e($isActive ? 'true' : 'false'); ?>"
                            class="inline-flex items-center justify-center h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide shrink-0
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   <?php echo e($isActive
                                      ? 'bg-primary-600 text-white shadow-sm shadow-primary-600/20'
                                      : 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400'); ?>">
                        <?php echo e($label); ?>

                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <div wire:loading.delay wire:target="dateRange,customStart,customEnd"
                 class="ml-auto inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400 shrink-0">
                <svg class="animate-spin h-3.5 w-3.5 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Updating
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dateRange === 'custom'): ?>
            <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                <input type="date"
                       wire:model.live="customStart"
                       aria-label="Start date"
                       class="input h-11 w-full sm:w-auto">
                <span class="text-gray-400 dark:text-gray-500 text-xs font-medium">to</span>
                <input type="date"
                       wire:model.live="customEnd"
                       aria-label="End date"
                       class="input h-11 w-full sm:w-auto">
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div wire:loading.class="opacity-50 pointer-events-none"
         wire:target="dateRange,customStart,customEnd"
         class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 transition-opacity duration-200">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $primaryKpis; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kpi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'kpi-primary-'.e($loop->index).''; ?>wire:key="kpi-primary-<?php echo e($loop->index); ?>"
                 class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:shadow-md transition-shadow duration-200 p-5 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <?php echo e($kpi['label']); ?>

                    </p>
                    <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white tabular-nums mt-2 leading-none truncate">
                        <?php echo e($kpi['value']); ?>

                    </p>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2 truncate">
                        <?php echo e($kpi['subtitle']); ?>

                    </p>
                </div>
                <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 <?php echo e($kpi['bg']); ?> <?php echo e($kpi['fg']); ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <?php echo $kpi['icon']; ?>

                    </svg>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div wire:loading.class="opacity-50 pointer-events-none"
         wire:target="dateRange,customStart,customEnd"
         class="grid grid-cols-2 lg:grid-cols-4 gap-3 transition-opacity duration-200">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $secondaryKpis; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kpi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'kpi-secondary-'.e($loop->index).''; ?>wire:key="kpi-secondary-<?php echo e($loop->index); ?>"
                 class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo e($kpi['dot']); ?>"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">
                        <?php echo e($kpi['label']); ?>

                    </span>
                </div>
                <p class="text-xl font-bold text-gray-900 dark:text-white tabular-nums mt-1.5">
                    <?php echo e($kpi['value']); ?>

                </p>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Recent Bookings</h2>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">The five latest bookings created for your business.</p>
                </div>
            </div>
            <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate
               class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300
                      transition-all duration-200 active:scale-95 shrink-0
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <span>View all</span>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="dateRange,customStart,customEnd"
             class="overflow-x-auto transition-opacity duration-200">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700/60 bg-gray-50/60 dark:bg-gray-900/40">
                        <th class="px-5 py-3 text-left">Reference</th>
                        <th class="px-5 py-3 text-left">Guest</th>
                        <th class="px-5 py-3 text-left hidden sm:table-cell">Start date</th>
                        <th class="px-5 py-3 text-left">Amount</th>
                        <th class="px-5 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentBookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $statusClasses = match ($b->status) {
                                'pending'    => 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/40',
                                'reserved'   => 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/40',
                                'confirmed'  => 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border-primary-200 dark:border-primary-500/40',
                                'completed'  => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/40',
                                'cancelled'  => 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/40',
                                'checked_in' => 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-500/40',
                                default      => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600',
                            };
                        ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'booking-'.e($b->id).''; ?>wire:key="booking-<?php echo e($b->id); ?>" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                            <td class="px-5 py-3">
                                <a href="<?php echo e(route('tenant.bookings.show', $b->id)); ?>" wire:navigate
                                   class="font-mono text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                    <?php echo e($b->booking_reference); ?>

                                </a>
                            </td>
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-white truncate max-w-[180px]">
                                <?php echo e($b->user->name ?? 'Walk-in Guest'); ?>

                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-400 tabular-nums hidden sm:table-cell">
                                <?php echo e($b->check_in?->format('M d, Y') ?? '—'); ?>

                            </td>
                            <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white tabular-nums">
                                ₱<?php echo e(number_format((float) $b->total_amount, 2)); ?>

                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border <?php echo e($statusClasses); ?>">
                                    <span class="w-1 h-1 rounded-full bg-current"></span>
                                    <?php echo e(ucfirst(str_replace('_', ' ', $b->status))); ?>

                                </span>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr>
                            <td colspan="5" class="px-5 py-14 text-center">
                                <div class="flex flex-col items-center max-w-sm mx-auto">
                                    <div class="p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mb-3">
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">No bookings yet</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Booking activity will appear here once guests start reserving.
                                    </p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->tenantCan('create bookings')): ?>
                                        <a href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate
                                           class="mt-4 inline-flex items-center justify-center gap-2 h-9 px-3.5 rounded-lg
                                                  bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm
                                                  transition-all duration-200 active:scale-95
                                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Create first booking
                                        </a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-emerald-500"></span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Upcoming Arrivals</h2>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Confirmed guests arriving soon.</p>
                    </div>
                </div>
            </div>

            <div wire:loading.class="opacity-40 pointer-events-none" class="divide-y divide-gray-100 dark:divide-gray-700/60 transition-opacity duration-200">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingArrivals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <a href="<?php echo e(route('tenant.bookings.show', $b->id)); ?>" wire:navigate
                       <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'arrival-'.e($b->id).''; ?>wire:key="arrival-<?php echo e($b->id); ?>"
                       class="group flex items-center justify-between gap-3 p-4
                              hover:bg-gray-50 dark:hover:bg-gray-700/40
                              transition-colors
                              focus-visible:outline-none focus-visible:bg-gray-50 dark:focus-visible:bg-gray-700/40">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 flex items-center justify-center font-bold text-sm shrink-0">
                                <?php echo e(strtoupper(substr($b->user->name ?? 'G', 0, 1))); ?>

                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    <?php echo e($b->user->name ?? 'Guest'); ?>

                                </p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate tabular-nums">
                                    <?php echo e($b->check_in?->format('M d, Y') ?? '—'); ?>

                                    · <span class="font-mono"><?php echo e($b->booking_reference); ?></span>
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider shrink-0
                                     bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/40">
                            <span class="w-1 h-1 rounded-full bg-current"></span>
                            Confirmed
                        </span>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="px-5 py-12 text-center">
                        <div class="flex flex-col items-center max-w-sm mx-auto">
                            <div class="p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mb-3">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No upcoming arrivals</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Confirmed bookings will appear here as their start date approaches.
                            </p>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-emerald-500"></span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Recent Payments</h2>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Latest settled transactions.</p>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->tenantCan('view payments')): ?>
                    <a href="<?php echo e(route('tenant.payments.index')); ?>" wire:navigate
                       class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300
                              transition-all duration-200 active:scale-95 shrink-0
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        <span>View all</span>
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div wire:loading.class="opacity-40 pointer-events-none" class="divide-y divide-gray-100 dark:divide-gray-700/60 transition-opacity duration-200">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentPayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'payment-'.e($p->id).''; ?>wire:key="payment-<?php echo e($p->id); ?>" class="flex items-center justify-between gap-3 p-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                                    ₱<?php echo e(number_format((float) $p->amount, 2)); ?>

                                </p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate tabular-nums">
                                    <?php echo e($p->paid_at?->format('M d, Y') ?? '—'); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->reference_number): ?>
                                        · <span class="font-mono"><?php echo e($p->reference_number); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider shrink-0
                                     bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/40">
                            <span class="w-1 h-1 rounded-full bg-current"></span>
                            Paid
                        </span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="px-5 py-12 text-center">
                        <div class="flex flex-col items-center max-w-sm mx-auto">
                            <div class="p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mb-3">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No recent payments</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Settled payments will appear here once recorded.
                            </p>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    
    <?php
        $quickActions = array_values(array_filter([
            $this->tenantCan('manage services')   ? ['tenant.services.create',   'Add Service',   'M12 6v6m0 0v6m0-6h6m-6 0H6'] : null,
            $this->tenantCan('manage employees')  ? ['tenant.employees.create',  'Add Employee',  'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'] : null,
            $this->tenantCan('view payments')     ? ['tenant.payments.index',    'Payments',      'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'] : null,
            $this->tenantCan('manage properties') ? ['tenant.properties.create',  'Add Property',  'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'] : null,
        ]));
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($quickActions)): ?>
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5">
            <div class="flex items-center gap-3 mb-4">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Quick Actions
                </h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $quickActions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$routeName, $label, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <a href="<?php echo e(route($routeName)); ?>" wire:navigate
                       <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'qa-'.e($routeName).''; ?>wire:key="qa-<?php echo e($routeName); ?>"
                       aria-label="<?php echo e($label); ?>"
                       class="group inline-flex items-center gap-2.5 h-12 px-3.5 rounded-xl
                              bg-primary-50 dark:bg-primary-500/10
                              border border-primary-200 dark:border-primary-500/25
                              hover:bg-primary-100 dark:hover:bg-primary-500/20
                              text-primary-700 dark:text-primary-300
                              text-sm font-semibold
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo e($icon); ?>"/>
                        </svg>
                        <span class="truncate"><?php echo e($label); ?></span>
                        <svg class="w-3 h-3 ml-auto shrink-0 opacity-0 group-hover:opacity-100 transition-opacity duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\dashboard\⚡dashboard-page.blade.php ENDPATH**/ ?>