
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TypeOfTenant;
use App\Scopes\TenantScope;
use App\Services\SuperadminNotificationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

new
#[Layout('superadmin.layouts.app')]
#[Title('Platform Analytics')]
class extends Component
{
    public ?string $startDate = null;
    public ?string $endDate   = null;
    public string  $preset    = 'this_year';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
        $this->applyPreset('this_year');
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;
        $now          = now();

        switch ($preset) {
            case 'today':
                $this->startDate = $now->toDateString();
                $this->endDate   = $now->toDateString();
                break;

            case '7d':
                $this->startDate = $now->copy()->subDays(6)->toDateString();
                $this->endDate   = $now->toDateString();
                break;

            case '30d':
                $this->startDate = $now->copy()->subDays(29)->toDateString();
                $this->endDate   = $now->toDateString();
                break;

            case 'this_month':
                $this->startDate = $now->copy()->startOfMonth()->toDateString();
                $this->endDate   = $now->copy()->endOfMonth()->toDateString();
                break;

            case 'this_year':
            default:
                $this->startDate = $now->copy()->startOfYear()->toDateString();
                $this->endDate   = $now->copy()->endOfYear()->toDateString();
                break;

            case 'custom':
                break;
        }
    }

    public function updatedStartDate(): void
    {
        $this->preset = 'custom';
    }

    public function updatedEndDate(): void
    {
        $this->preset = 'custom';
    }

    // ─────────────────────────────────────────────────────────
    //  Computed — core stats
    // ─────────────────────────────────────────────────────────

    /** @return array<string, int> */
    #[Computed]
    public function stats(): array
    {
        $now          = now();
        $weekAgo      = $now->copy()->subDays(7);
        $startOfMonth = $now->copy()->startOfMonth();

        $tenantStats = Tenant::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
                COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_week,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_month
            ', [$weekAgo, $startOfMonth])
            ->first();

        $userStats = User::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_month
            ', [$startOfMonth])
            ->first();

        $eventStats = Event::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN start_date >= ? AND is_active = 1 THEN 1 ELSE 0 END), 0) as upcoming
            ', [$now])
            ->first();

        return [
            'total_tenants'        => (int) ($tenantStats?->total ?? 0),
            'active_tenants'       => (int) ($tenantStats?->active ?? 0),
            'pending_tenants'      => (int) ($tenantStats?->pending ?? 0),
            'new_this_month'       => (int) ($tenantStats?->new_this_month ?? 0),
            'new_this_week'        => (int) ($tenantStats?->new_this_week ?? 0),
            'total_users'          => (int) ($userStats?->total ?? 0),
            'active_users'         => (int) ($userStats?->active ?? 0),
            'new_users_this_month' => (int) ($userStats?->new_this_month ?? 0),
            'total_events'         => (int) ($eventStats?->total ?? 0),
            'upcoming_events'      => (int) ($eventStats?->upcoming ?? 0),
        ];
    }

    /**
     * Hero KPI metrics — revenue, bookings, and their deltas vs the
     * equivalent preceding period. Deltas are null when there is no
     * baseline (avoids displaying a misleading "+∞%").
     *
     * @return array{
     *     revenue: float, revenue_delta: ?float,
     *     bookings: int, bookings_delta: ?float,
     *     period_start: Carbon, period_end: Carbon, period_days: int
     * }
     */
    #[Computed]
    public function kpiMetrics(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfYear();
        $end   = $this->endDate   ? Carbon::parse($this->endDate)->endOfDay()     : now()->endOfYear();

        $span = max(1, $start->diffInDays($end) + 1);

        $prevStart = $start->copy()->subDays($span);
        $prevEnd   = $start->copy()->subSecond();

        // Current period
        $revenue = (float) Booking::withoutGlobalScope(TenantScope::class)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
            ->sum('total_amount');

        $bookings = (int) Booking::withoutGlobalScope(TenantScope::class)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // Previous period (same length, immediately before)
        $prevRevenue = (float) Booking::withoutGlobalScope(TenantScope::class)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
            ->sum('total_amount');

        $prevBookings = (int) Booking::withoutGlobalScope(TenantScope::class)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        return [
            'revenue'        => $revenue,
            'revenue_delta'  => $prevRevenue > 0 ? round((($revenue - $prevRevenue) / $prevRevenue) * 100, 1) : null,
            'bookings'       => $bookings,
            'bookings_delta' => $prevBookings > 0 ? round((($bookings - $prevBookings) / $prevBookings) * 100, 1) : null,
            'period_start'   => $start,
            'period_end'     => $end,
            'period_days'    => $span,
        ];
    }

    /**
     * Pending review-queue counts.
     * Reads cached scalars from SuperadminNotificationService — no new DB cost.
     *
     * @return array{applications: int, deletions: int, tenants: int, total: int}
     */
    #[Computed]
    public function reviewQueue(): array
    {
        $notifications = app(SuperadminNotificationService::class);

        $apps      = $notifications->pendingCount();
        $deletions = $notifications->deletionRequestCount();
        $tenants   = (int) Tenant::query()->where('is_active', false)->count();

        return [
            'applications' => $apps,
            'deletions'    => $deletions,
            'tenants'      => $tenants,
            'total'        => $apps + $deletions + $tenants,
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Computed — chart data
    // ─────────────────────────────────────────────────────────

    /**
     * @return array{labels: array<int, string>, values: array<int, int|null>}
     */
    #[Computed]
    public function tenantAcquisition(): array
    {
        $start = now()->subMonths(5)->startOfMonth();
        $end   = now()->endOfMonth();

        $labels = [];
        $values = [];

        $period = CarbonPeriod::create($start, '1 month', $end);

        foreach ($period as $dt) {
            $mStart = $dt->copy()->startOfMonth();
            $mEnd   = $dt->copy()->endOfMonth();

            $labels[] = $dt->format('M');

            $count = (int) Tenant::query()
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->count();

            $values[] = $count === 0 ? null : $count;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int|null>}
     */
    #[Computed]
    public function userGrowth(): array
    {
        $start = now()->subMonths(5)->startOfMonth();
        $end   = now()->endOfMonth();

        $labels = [];
        $values = [];

        $period = CarbonPeriod::create($start, '1 month', $end);

        foreach ($period as $dt) {
            $mStart = $dt->copy()->startOfMonth();
            $mEnd   = $dt->copy()->endOfMonth();

            $labels[] = $dt->format('M');

            $count = (int) User::query()
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super-admin'))
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->count();

            $values[] = $count === 0 ? null : $count;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels: array<int, string>, values: array<int, int>, colors: array<int, string>} */
    #[Computed]
    public function tenantStatusData(): array
    {
        $stats = $this->stats;

        return [
            'labels' => ['Active', 'Pending'],
            'values' => [$stats['active_tenants'], $stats['pending_tenants']],
            'colors' => ['#10b981', '#f59e0b'],
        ];
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     values: array<int, int>,
     *     colors: array<int, string>
     * }
     */
    #[Computed]
    public function topSpotsData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfYear();
        $end   = $this->endDate   ? Carbon::parse($this->endDate)->endOfDay()     : now()->endOfYear();

        $topSpots = Booking::query()
            ->withoutGlobalScope(TenantScope::class)
            ->join('tenants', 'bookings.tenant_id', '=', 'tenants.id')
            ->whereBetween('bookings.created_at', [$start, $end])
            ->whereNotIn('bookings.status', [Booking::STATUS_CANCELLED])
            ->select(
                'tenants.id',
                'tenants.name',
                DB::raw('COUNT(DISTINCT bookings.id) as total_bookings'),
            )
            ->groupBy('tenants.id', 'tenants.name')
            ->orderByDesc('total_bookings')
            ->limit(5)
            ->get();

        $ramp = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'];

        return [
            'labels' => $topSpots->pluck('name')->all(),
            'values' => $topSpots->pluck('total_bookings')->map(fn ($v) => (int) $v)->all(),
            'colors' => array_slice($ramp, 0, $topSpots->count()),
        ];
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     values: array<int, int>,
     *     colors: array<int, string>
     * }
     */
    #[Computed]
    public function categoryDistribution(): array
    {
        $types = TypeOfTenant::query()
            ->withCount('tenants')
            ->having('tenants_count', '>', 0)
            ->orderByDesc('tenants_count')
            ->limit(6)
            ->get();

        $palette = [
            'rgba(16, 185, 129, 0.75)',
            'rgba(59, 130, 246, 0.75)',
            'rgba(139, 92, 246, 0.75)',
            'rgba(245, 158, 11, 0.75)',
            'rgba(244, 63, 94, 0.75)',
            'rgba(100, 116, 139, 0.75)',
        ];

        return [
            'labels' => $types->pluck('type')->all(),
            'values' => $types->pluck('tenants_count')->map(fn ($v) => (int) $v)->all(),
            'colors' => array_slice($palette, 0, $types->count()),
        ];
    }

    #[Computed]
    public function activeRate(): int
    {
        $stats = $this->stats;

        if ($stats['total_tenants'] <= 0) {
            return 0;
        }

        return (int) round(($stats['active_tenants'] / $stats['total_tenants']) * 100);
    }

    public function exportCsv(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $url = route('superadmin.analytics.export', array_filter([
            'start' => $this->startDate,
            'end'   => $this->endDate,
        ]));

        $this->dispatch('open-url', url: $url);
    }
};
?>

<?php
    $s           = $this->stats;
    $kpi         = $this->kpiMetrics;
    $rq          = $this->reviewQueue;
    $tenantAcq   = $this->tenantAcquisition;
    $userGrowth  = $this->userGrowth;
    $topSpots    = $this->topSpotsData;
    $categories  = $this->categoryDistribution;
    $statusData  = $this->tenantStatusData;

    $revenueDelta  = $kpi['revenue_delta'];
    $bookingsDelta = $kpi['bookings_delta'];

    // Empty-state detection — if the source has no data, skip the chart
    // canvas and render a placeholder instead.
    $tenantAcqHasData   = !empty(array_filter($tenantAcq['values'],  fn ($v) => $v !== null));
    $userGrowthHasData  = !empty(array_filter($userGrowth['values'], fn ($v) => $v !== null));
    $statusHasData      = array_sum($statusData['values']) > 0;
    $categoriesHasData  = !empty($categories['labels']);
    $topSpotsHasData    = !empty($topSpots['labels']);
?>

<?php $__env->startPush('scripts'); ?>
    <?php if (! $__env->hasRenderedOnce('d84979ba-0e33-4c6a-98eb-c264a5da3c87')): $__env->markAsRenderedOnce('d84979ba-0e33-4c6a-98eb-c264a5da3c87'); ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<div class="analytics-page p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-4 sm:space-y-6" wire:poll.60s>

    
    <div id="analytics-data"
         data-tenant-acq="<?php echo e(json_encode($tenantAcq, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"
         data-user-growth="<?php echo e(json_encode($userGrowth, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"
         data-status="<?php echo e(json_encode($statusData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"
         data-topspots="<?php echo e(json_encode($topSpots, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"
         data-categories="<?php echo e(json_encode($categories, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"
         data-review="<?php echo e(json_encode($rq, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"
         hidden
         aria-hidden="true"></div>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">

            
            <div class="flex items-center gap-4 min-w-0">
                <div class="hidden sm:flex w-12 h-12 rounded-xl bg-primary-600 text-white items-center justify-center shrink-0 shadow-md shadow-primary-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21h18M5 21V8l7-5 7 5v13M9 21v-5h6v5M9 11h.01M15 11h.01"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="font-display text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white tracking-tight leading-tight">
                            Analytics <em class="italic text-primary-600 dark:text-primary-400">Dashboard</em>
                        </h1>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                     bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300
                                     border border-emerald-200 dark:border-emerald-500/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                            Live · 60s
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Platform-wide tourism metrics, onboarding growth, and usage overview.
                    </p>
                </div>
            </div>

            
            <div class="flex flex-wrap items-center gap-2 shrink-0 no-print">

                
                <div class="inline-flex items-center gap-0.5 p-1 bg-gray-100 dark:bg-gray-900/60 rounded-xl border border-gray-200/70 dark:border-gray-700/70 max-w-full overflow-x-auto">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                        'today'      => 'Today',
                        '7d'         => '7D',
                        '30d'        => '30D',
                        'this_month' => 'Month',
                        'this_year'  => 'Year',
                        'custom'     => 'Custom',
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php $isActive = $preset === $val; ?>
                        <button type="button"
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'preset-'.e($val).''; ?>wire:key="preset-<?php echo e($val); ?>"
                                wire:click="applyPreset('<?php echo e($val); ?>')"
                                aria-pressed="<?php echo e($isActive ? 'true' : 'false'); ?>"
                                class="inline-flex items-center justify-center h-9 px-3 rounded-lg text-xs font-semibold whitespace-nowrap
                                       transition-all duration-150 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       <?php echo e($isActive
                                          ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-300 shadow-sm border border-gray-200/60 dark:border-gray-600'
                                          : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-transparent'); ?>">
                            <?php echo e($label); ?>

                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                
                <button type="button"
                        wire:click="$refresh"
                        wire:loading.attr="disabled"
                        wire:target="$refresh"
                        aria-label="Refresh analytics data"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/>
                    </svg>
                    <span class="hidden sm:inline">Refresh</span>
                </button>

                
                <button type="button"
                        wire:click="exportCsv"
                        wire:loading.attr="disabled"
                        wire:target="exportCsv"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="exportCsv" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="hidden sm:inline">Export</span>
                    </span>
                    <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="hidden sm:inline">Preparing…</span>
                    </span>
                </button>

                
                <button type="button"
                        onclick="window.print()"
                        aria-label="Print analytics report"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                    </svg>
                    <span class="hidden sm:inline">Print</span>
                </button>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preset === 'custom'): ?>
            <div class="flex flex-wrap items-center gap-3 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/60 no-print">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Custom range</span>
                </div>
                <input type="date"
                       wire:model.live="startDate"
                       aria-label="Start date"
                       class="h-11 px-3 text-sm font-medium bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-700 rounded-xl
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:border-primary-500">
                <span class="text-gray-400 dark:text-gray-500 text-xs font-medium">to</span>
                <input type="date"
                       wire:model.live="endDate"
                       aria-label="End date"
                       class="h-11 px-3 text-sm font-medium bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-700 rounded-xl
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:border-primary-500">
                <span class="ml-auto text-[11px] text-gray-500 dark:text-gray-400 tabular-nums">
                    <?php echo e($kpi['period_start']->format('M j, Y')); ?> – <?php echo e($kpi['period_end']->format('M j, Y')); ?>

                    · <?php echo e($kpi['period_days']); ?> day<?php echo e($kpi['period_days'] === 1 ? '' : 's'); ?>

                </span>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        
        <div class="bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:shadow-md transition-shadow duration-200 group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revenueDelta !== null): ?>
                    <?php $positive = $revenueDelta >= 0; ?>
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full inline-flex items-center gap-1 tabular-nums
                                 <?php echo e($positive
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                    : 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'); ?>">
                        <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($positive): ?>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            <?php else: ?>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"/>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </svg>
                        <?php echo e(abs($revenueDelta)); ?>%
                    </span>
                <?php else: ?>
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full
                                 bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        No baseline
                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                Platform Revenue
            </h3>
            <div class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                ₱<?php echo e(number_format($kpi['revenue'], 0)); ?>

            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                Confirmed + completed bookings
            </p>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:shadow-md transition-shadow duration-200 group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 bg-blue-50 dark:bg-blue-500/15 text-blue-600 dark:text-blue-400 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bookingsDelta !== null): ?>
                    <?php $positive = $bookingsDelta >= 0; ?>
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full inline-flex items-center gap-1 tabular-nums
                                 <?php echo e($positive
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                    : 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'); ?>">
                        <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($positive): ?>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            <?php else: ?>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"/>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </svg>
                        <?php echo e(abs($bookingsDelta)); ?>%
                    </span>
                <?php else: ?>
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full
                                 bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        No baseline
                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                Total Bookings
            </h3>
            <div class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                <?php echo e(number_format($kpi['bookings'])); ?>

            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                Created this period
            </p>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:shadow-md transition-shadow duration-200 group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 bg-purple-50 dark:bg-purple-500/15 text-purple-600 dark:text-purple-400 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <?php
                    $tenantStatus = $s['pending_tenants'] > 0 ? 'Needs review' : 'Stable';
                    $tenantBadge = $s['pending_tenants'] > 0
                        ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
                        : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
                ?>
                <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full <?php echo e($tenantBadge); ?>">
                    <?php echo e($tenantStatus); ?>

                </span>
            </div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                Active Tourist Spots
            </h3>
            <div class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                <?php echo e(number_format($s['active_tenants'])); ?>

                <span class="text-sm text-gray-400 dark:text-gray-500 font-medium ml-1">
                    / <?php echo e(number_format($s['total_tenants'])); ?>

                </span>
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                <?php echo e(number_format($s['total_users'])); ?> users · <?php echo e(number_format($s['total_events'])); ?> events
            </p>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rq['total'] > 0): ?>
            <a href="<?php echo e(route('superadmin.business-applications.index')); ?>" wire:navigate
               aria-label="Review pending approvals"
               class="bg-amber-50/70 dark:bg-amber-500/[0.08] p-5 sm:p-6 rounded-2xl border border-amber-300 dark:border-amber-500/40 shadow-sm hover:shadow-md transition-all duration-200 group
                      active:scale-[0.99]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full
                                 bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300
                                 inline-flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse motion-reduce:animate-none"></span>
                        Action Required
                    </span>
                </div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300 mb-1">
                    Pending Approvals
                </h3>
                <div class="text-3xl font-bold text-amber-900 dark:text-amber-200 tabular-nums leading-none">
                    <?php echo e($rq['total']); ?>

                </div>
                <p class="text-[11px] text-amber-700 dark:text-amber-300/80 mt-2">
                    <?php echo e($rq['applications']); ?> KYB · <?php echo e($rq['deletions']); ?> deletion · <?php echo e($rq['tenants']); ?> tenant
                </p>
            </a>
        <?php else: ?>
            <div class="bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
                <div class="flex justify-between items-start mb-4">
                    <div class="w-12 h-12 bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full
                                 bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                        All Clear
                    </span>
                </div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">
                    Pending Approvals
                </h3>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                    0
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                    Nothing in the review queue
                </p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rq['total'] > 0): ?>
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-amber-500"></span>
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Review Queue</h2>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Workload distribution across pending reviews.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-amber-100 dark:bg-amber-500/15 text-amber-800 dark:text-amber-300
                             border border-amber-200 dark:border-amber-500/30 tabular-nums shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse motion-reduce:animate-none" aria-hidden="true"></span>
                    <?php echo e($rq['total']); ?> pending
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-0 divide-y lg:divide-y-0 lg:divide-x divide-gray-100 dark:divide-gray-700/60">

                <div class="p-5">
                    <div class="w-full h-40 sm:h-44 relative" wire:ignore>
                        <canvas id="reviewChart"
                                role="img"
                                aria-label="Bar chart: pending reviews by category"></canvas>
                    </div>
                </div>

                <div class="p-5 space-y-2">
                    <a href="<?php echo e(route('superadmin.business-applications.index')); ?>" wire:navigate
                       class="group flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 active:scale-[0.98]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50
                              <?php echo e($rq['applications'] > 0
                                 ? 'border-amber-200 dark:border-amber-500/40 bg-amber-50/70 dark:bg-amber-500/[0.06] hover:bg-amber-100 dark:hover:bg-amber-500/[0.12]'
                                 : 'border-gray-200/80 dark:border-gray-700/60 bg-gray-50 dark:bg-gray-900/40 hover:border-amber-300 dark:hover:border-amber-500/40'); ?>">
                        <span class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center
                                     <?php echo e($rq['applications'] > 0 ? 'bg-amber-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400'); ?>">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">KYB Applications</p>
                            <p class="text-base font-bold text-gray-900 dark:text-white tabular-nums leading-none mt-0.5"><?php echo e($rq['applications']); ?></p>
                        </div>
                        <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 group-hover:text-amber-500 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>

                    <a href="<?php echo e(route('superadmin.deletion-requests.index')); ?>" wire:navigate
                       class="group flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 active:scale-[0.98]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                              <?php echo e($rq['deletions'] > 0
                                 ? 'border-rose-200 dark:border-rose-500/40 bg-rose-50/70 dark:bg-rose-500/[0.06] hover:bg-rose-100 dark:hover:bg-rose-500/[0.12]'
                                 : 'border-gray-200/80 dark:border-gray-700/60 bg-gray-50 dark:bg-gray-900/40 hover:border-rose-300 dark:hover:border-rose-500/40'); ?>">
                        <span class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center
                                     <?php echo e($rq['deletions'] > 0 ? 'bg-rose-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400'); ?>">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Deletion Requests</p>
                            <p class="text-base font-bold text-gray-900 dark:text-white tabular-nums leading-none mt-0.5"><?php echo e($rq['deletions']); ?></p>
                        </div>
                        <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 group-hover:text-rose-500 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>

                    <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
                       class="group flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 active:scale-[0.98]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                              <?php echo e($rq['tenants'] > 0
                                 ? 'border-primary-200 dark:border-primary-500/40 bg-primary-50/70 dark:bg-primary-500/[0.06] hover:bg-primary-100 dark:hover:bg-primary-500/[0.12]'
                                 : 'border-gray-200/80 dark:border-gray-700/60 bg-gray-50 dark:bg-gray-900/40 hover:border-primary-300 dark:hover:border-primary-500/40'); ?>">
                        <span class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center
                                     <?php echo e($rq['tenants'] > 0 ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400'); ?>">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pending Tenants</p>
                            <p class="text-base font-bold text-gray-900 dark:text-white tabular-nums leading-none mt-0.5"><?php echo e($rq['tenants']); ?></p>
                        </div>
                        <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 group-hover:text-primary-500 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        
        <div class="lg:col-span-2 bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-px bg-primary-600"></span>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Tenant Acquisition</h2>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">New registered tenants per month over the last 6 months.</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-purple-600 dark:text-purple-400 shrink-0">
                    <span class="w-2.5 h-2.5 rounded-sm bg-purple-500" aria-hidden="true"></span>
                    New Tenants
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenantAcqHasData): ?>
                <div class="w-full h-56 sm:h-64 relative" wire:ignore>
                    <canvas id="tenantAcqChart"
                            role="img"
                            aria-label="Bar chart: new tenants per month over the last 6 months"></canvas>
                </div>
            <?php else: ?>
                <div class="w-full h-56 sm:h-64 flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No new tenants yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Data appears here as new businesses register on the platform.</p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm flex flex-col">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Tenant Distribution</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Active vs pending tenant accounts.</p>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusHasData): ?>
                <div class="relative flex-1 min-h-[200px] sm:min-h-[220px] flex justify-center items-center mt-3" wire:ignore>
                    <canvas id="statusChart"
                            role="img"
                            aria-label="Doughnut chart: active versus pending tenant distribution"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-4xl font-bold text-gray-900 dark:text-white tabular-nums leading-none"><?php echo e($this->activeRate); ?>%</span>
                        <span class="mt-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Active Rate</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex-1 min-h-[200px] sm:min-h-[220px] mt-3 flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke-width="2"/>
                            <path d="M12 3v9h9" stroke-width="2"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No tenants yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Distribution appears once businesses are live.</p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="flex items-center justify-center gap-6 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700/60">
                <div class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    Active
                </div>
                <div class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                    Pending
                </div>
            </div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        
        <div class="lg:col-span-2 bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-px bg-primary-600"></span>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">User Growth</h2>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Platform end-user registrations per month over the last 6 months.</p>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400 shrink-0">
                    <span class="w-2.5 h-2.5 rounded-sm bg-blue-500" aria-hidden="true"></span>
                    New Users
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userGrowthHasData): ?>
                <div class="w-full h-56 sm:h-64 relative" wire:ignore>
                    <canvas id="userGrowthChart"
                            role="img"
                            aria-label="Bar chart: new end-user registrations per month over the last 6 months"></canvas>
                </div>
            <?php else: ?>
                <div class="w-full h-56 sm:h-64 flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No user registrations yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Data appears as users sign up for the platform.</p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Attraction Categories</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Distribution of tenants by business type.</p>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoriesHasData): ?>
                <div class="h-56 sm:h-64 relative w-full mt-3 flex justify-center items-center" wire:ignore>
                    <canvas id="categoriesChart"
                            role="img"
                            aria-label="Polar area chart: tenant distribution by business category"></canvas>
                </div>
            <?php else: ?>
                <div class="h-56 sm:h-64 mt-3 flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No categories yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Category breakdown appears when tenants are assigned types.</p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800/90 p-5 sm:p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Top Performing Spots</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ranked by total successful bookings in the selected period.</p>
            </div>
            <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
               class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300
                      transition-all duration-200 active:scale-95
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded shrink-0">
                View Full List
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topSpotsHasData): ?>
            <div class="w-full h-56 sm:h-64 relative" wire:ignore>
                <canvas id="topSpotsChart"
                        role="img"
                        aria-label="Horizontal bar chart: top performing spots by total bookings"></canvas>
            </div>
        <?php else: ?>
            <div class="w-full h-56 sm:h-64 flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </div>
                <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No bookings this period</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Rankings populate once bookings are created in the selected date range.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <script>
        if (! window.__analyticsExportUrlBound) {
            window.__analyticsExportUrlBound = true;
            window.addEventListener('open-url', (e) => { window.location.href = e.detail.url; });
        }
    </script>
</div>


<style>
    @media print {
        @page { size: auto; margin: 12mm; }

        html, body {
            background: #fff !important;
            color: #000 !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: 0 !important;
            height: auto !important;
        }

        .analytics-page { padding: 0 !important; margin: 0 !important; }

        /* Interactive-only elements hidden from paper */
        .no-print { display: none !important; }

        /* Preserve chart canvas colors where the browser allows it */
        canvas { print-color-adjust: exact; -webkit-print-color-adjust: exact; }

        /* Keep each card on one page */
        .analytics-page > * {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* Force KPI strip to 4-col on paper regardless of viewport */
        .analytics-page > .grid {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 3mm !important;
        }
    }
</style>

<script>
    (function () {
        if (window.__analyticsPrintScopeInstalled) return;
        window.__analyticsPrintScopeInstalled = true;

        function applyPrintScope() {
            const root = document.querySelector('.analytics-page');
            if (!root) return;

            const ancestors = new Set();
            let el = root;
            while (el && el !== document.body) {
                ancestors.add(el);
                el = el.parentElement;
            }

            Array.from(document.body.children).forEach(function (child) {
                if (!ancestors.has(child)) {
                    if (child.dataset.printHidden !== '1') {
                        child.dataset.printHidden = '1';
                        child.dataset.printOldDisplay = child.style.display || '';
                    }
                    child.style.setProperty('display', 'none', 'important');
                }
            });

            ancestors.forEach(function (node) {
                if (node === root) return;
                if (node.dataset.printReset !== '1') {
                    node.dataset.printReset = '1';
                    node.dataset.printOldPadding = node.style.padding || '';
                    node.dataset.printOldMargin = node.style.margin || '';
                    node.dataset.printOldBackground = node.style.background || '';
                }
                node.style.setProperty('padding', '0', 'important');
                node.style.setProperty('margin', '0', 'important');
                node.style.setProperty('background', 'transparent', 'important');
            });
        }

        function restorePrintScope() {
            document.querySelectorAll('[data-print-hidden="1"]').forEach(function (node) {
                node.style.removeProperty('display');
                if (node.dataset.printOldDisplay) {
                    node.style.display = node.dataset.printOldDisplay;
                }
                delete node.dataset.printHidden;
                delete node.dataset.printOldDisplay;
            });

            document.querySelectorAll('[data-print-reset="1"]').forEach(function (node) {
                node.style.removeProperty('padding');
                node.style.removeProperty('margin');
                node.style.removeProperty('background');
                if (node.dataset.printOldPadding) node.style.padding = node.dataset.printOldPadding;
                if (node.dataset.printOldMargin)  node.style.margin  = node.dataset.printOldMargin;
                if (node.dataset.printOldBackground) node.style.background = node.dataset.printOldBackground;
                delete node.dataset.printReset;
                delete node.dataset.printOldPadding;
                delete node.dataset.printOldMargin;
                delete node.dataset.printOldBackground;
            });
        }

        window.addEventListener('beforeprint', applyPrintScope);
        window.addEventListener('afterprint', restorePrintScope);
    })();
</script>


<script>
(function () {
    'use strict';

    const state = window.__analyticsState = window.__analyticsState || {
        tenantAcq:  null,
        userGrowth: null,
        status:     null,
        topSpots:   null,
        categories: null,
        review:     null,
        hooked:     false,
        pluginsReady: false,
    };

    if (typeof state.pluginsReady !== 'boolean') state.pluginsReady = false;

    const barValueLabel = {
        id: 'barValueLabel',
        afterDatasetsDraw(chart, args, opts) {
            if (!opts || !opts.enabled) return;
            const ctx = chart.ctx;
            const isDark = document.documentElement.classList.contains('dark');
            ctx.save();
            ctx.font = '700 11px Inter, system-ui, sans-serif';
            ctx.fillStyle = isDark ? '#e5e7eb' : '#111827';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            chart.data.datasets.forEach((ds, di) => {
                const meta = chart.getDatasetMeta(di);
                if (meta.hidden) return;
                meta.data.forEach((bar, i) => {
                    const v = ds.data[i];
                    if (v === undefined || v === null) return;
                    const pos = bar.tooltipPosition();
                    ctx.fillText(String(v), pos.x + 8, pos.y);
                });
            });
            ctx.restore();
        },
    };

    function ensurePluginsRegistered() {
        if (state.pluginsReady) return;
        if (typeof Chart === 'undefined') return;
        Chart.register(barValueLabel);
        state.pluginsReady = true;
    }

    function getData() {
        const el = document.getElementById('analytics-data');
        if (!el) return null;
        try {
            return {
                tenantAcq:  JSON.parse(el.dataset.tenantAcq  || '{}'),
                userGrowth: JSON.parse(el.dataset.userGrowth || '{}'),
                status:     JSON.parse(el.dataset.status     || '{}'),
                topspots:   JSON.parse(el.dataset.topspots   || '{}'),
                categories: JSON.parse(el.dataset.categories || '{}'),
                review:     JSON.parse(el.dataset.review     || '{}'),
            };
        } catch (e) {
            console.error('Analytics data parse failed', e);
            return null;
        }
    }

    function theme() {
        const isDark = document.documentElement.classList.contains('dark');
        return {
            isDark,
            text:      isDark ? '#9ca3af' : '#6b7280',
            textBold:  isDark ? '#f3f4f6' : '#111827',
            grid:      isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
            gridLight: isDark ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.04)',
            tooltipBg: '#111827',
            tooltipText: '#f9fafb',
            doughnutBorder: isDark ? '#111827' : '#ffffff',
        };
    }

    function tooltipConfig(t) {
        return {
            backgroundColor: t.tooltipBg,
            titleColor:      t.tooltipText,
            bodyColor:       t.tooltipText,
            borderColor:     'rgba(255,255,255,0.08)',
            borderWidth:     1,
            padding:         12,
            cornerRadius:    10,
            displayColors:   true,
            boxWidth:        8,
            boxHeight:       8,
            boxPadding:      4,
            titleFont: { family: 'Inter, system-ui, sans-serif', size: 12, weight: '700' },
            bodyFont:  { family: 'Inter, system-ui, sans-serif', size: 12, weight: '500' },
        };
    }

    function scaleX(t) {
        return {
            grid:   { display: false },
            border: { display: false },
            ticks:  { color: t.text, font: { size: 11, weight: '600' }, padding: 6, maxRotation: 0, autoSkipPadding: 12 },
        };
    }

    function scaleY(t, { dashed = true, position = 'left' } = {}) {
        return {
            type: 'linear',
            position,
            beginAtZero: true,
            grid: {
                color: dashed ? t.grid : 'transparent',
                borderDash: dashed ? [4, 4] : undefined,
                drawTicks: false,
            },
            border: { display: false },
            ticks:  { color: t.text, precision: 0, font: { size: 11, weight: '600' }, padding: 8 },
        };
    }

    function hexToRgba(hex, alpha) {
        const h = hex.replace('#', '');
        const n = parseInt(h.length === 3 ? h.split('').map(c => c + c).join('') : h, 16);
        const r = (n >> 16) & 255;
        const g = (n >> 8)  & 255;
        const b = n         & 255;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function buildGrowthBar(canvasId, labels, values, color, t) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;

        const ctx = canvas.getContext('2d');
        const H   = canvas.clientHeight || 260;

        const gradient = ctx.createLinearGradient(0, 0, 0, H);
        gradient.addColorStop(0, hexToRgba(color, 1.00));
        gradient.addColorStop(1, hexToRgba(color, 0.55));

        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: gradient,
                    hoverBackgroundColor: color,
                    borderRadius: { topLeft: 8, topRight: 8, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: false,
                    maxBarThickness: 48,
                    barPercentage: 0.65,
                    categoryPercentage: 0.75,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                layout: { padding: { top: 8, right: 8, bottom: 0, left: 0 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltipConfig(t),
                        callbacks: {
                            label(ctx) {
                                const v = ctx.parsed.y;
                                if (v === null || v === undefined) return ' —';
                                return ' ' + Number(v).toLocaleString();
                            },
                        },
                    },
                },
                scales: {
                    x: scaleX(t),
                    y: {
                        ...scaleY(t, { dashed: true, position: 'left' }),
                        beginAtZero: true,
                        grace: '15%',
                        ticks: {
                            color: t.text,
                            precision: 0,
                            font: { size: 11, weight: '600' },
                            padding: 8,
                        },
                    },
                },
            },
        });
    }

    function buildStatus(status, t) {
        const canvas = document.getElementById('statusChart');
        if (!canvas) return null;

        return new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: status.labels || [],
                datasets: [{
                    data: status.values || [],
                    backgroundColor: status.colors || ['#10b981', '#f59e0b'],
                    borderWidth: 3,
                    borderColor: t.doughnutBorder,
                    hoverOffset: 6,
                    borderRadius: 6,
                    spacing: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '76%',
                animation: { animateRotate: true, duration: 800, easing: 'easeOutQuart' },
                plugins: {
                    legend:  { display: false },
                    tooltip: tooltipConfig(t),
                },
            },
        });
    }

    function buildHBar(canvasId, labels, values, colors, t, { showLabels = true, thickness = 22 } = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;

        return new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 6,
                    borderSkipped: false,
                    maxBarThickness: thickness,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                layout: { padding: { right: showLabels ? 40 : 8 } },
                plugins: {
                    legend: { display: false },
                    tooltip: tooltipConfig(t),
                    barValueLabel: { enabled: showLabels },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid:   { color: t.gridLight, drawTicks: false },
                        border: { display: false },
                        ticks:  { color: t.text, precision: 0, font: { size: 10, weight: '600' }, padding: 4 },
                    },
                    y: {
                        grid:   { display: false },
                        border: { display: false },
                        ticks: {
                            color: t.textBold,
                            font: { size: 11, weight: '600' },
                            autoSkip: false,
                            padding: 4,
                            callback: function (v) {
                                const label = this.getLabelForValue(v);
                                return label.length > 28 ? label.slice(0, 26) + '…' : label;
                            },
                        },
                    },
                },
            },
        });
    }

    function buildPolar(categories, t) {
        const canvas = document.getElementById('categoriesChart');
        if (!canvas) return null;

        return new Chart(canvas.getContext('2d'), {
            type: 'polarArea',
            data: {
                labels: categories.labels || [],
                datasets: [{
                    data: categories.values || [],
                    backgroundColor: categories.colors || [],
                    borderWidth: 2,
                    borderColor: t.doughnutBorder,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: 'easeOutQuart' },
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: t.text,
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 10,
                            font: { size: 10, weight: '600' },
                        },
                    },
                    tooltip: tooltipConfig(t),
                },
                scales: {
                    r: {
                        grid:  { color: t.grid, circular: true },
                        ticks: { display: false, backdropColor: 'transparent' },
                        angleLines: { color: t.gridLight },
                    },
                },
            },
        });
    }

    function destroyAll() {
        ['tenantAcq', 'userGrowth', 'status', 'topSpots', 'categories', 'review'].forEach(k => {
            if (state[k]) { try { state[k].destroy(); } catch (e) {} state[k] = null; }
        });
    }

    window.renderAnalyticsCharts = function (force) {
        if (typeof Chart === 'undefined') {
            setTimeout(() => window.renderAnalyticsCharts(force), 100);
            return;
        }

        ensurePluginsRegistered();

        const data = getData();
        if (!data) return;

        const t = theme();
        if (force) destroyAll();

        if (!state.tenantAcq && document.getElementById('tenantAcqChart')) {
            state.tenantAcq = buildGrowthBar(
                'tenantAcqChart',
                data.tenantAcq.labels || [],
                data.tenantAcq.values || [],
                '#8b5cf6',
                t
            );
        }
        if (!state.userGrowth && document.getElementById('userGrowthChart')) {
            state.userGrowth = buildGrowthBar(
                'userGrowthChart',
                data.userGrowth.labels || [],
                data.userGrowth.values || [],
                '#3b82f6',
                t
            );
        }
        if (!state.status && document.getElementById('statusChart')) {
            state.status = buildStatus(data.status, t);
        }
        if (!state.topSpots && document.getElementById('topSpotsChart')) {
            state.topSpots = buildHBar(
                'topSpotsChart',
                data.topspots.labels || [],
                data.topspots.values || [],
                data.topspots.colors || ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'],
                t,
                { showLabels: true, thickness: 22 }
            );
        }
        if (!state.categories && document.getElementById('categoriesChart')) {
            state.categories = buildPolar(data.categories, t);
        }
        if (!state.review && document.getElementById('reviewChart')) {
            const rq = data.review || {};
            state.review = buildHBar(
                'reviewChart',
                ['KYB Applications', 'Deletion Requests', 'Pending Tenants'],
                [rq.applications || 0, rq.deletions || 0, rq.tenants || 0],
                ['#f59e0b', '#f43f5e', '#3b82f6'],
                t,
                { showLabels: true, thickness: 20 }
            );
        }

        if (state.tenantAcq && data.tenantAcq.labels) {
            state.tenantAcq.data.labels = data.tenantAcq.labels;
            state.tenantAcq.data.datasets[0].data = data.tenantAcq.values;
            state.tenantAcq.update('none');
        }
        if (state.userGrowth && data.userGrowth.labels) {
            state.userGrowth.data.labels = data.userGrowth.labels;
            state.userGrowth.data.datasets[0].data = data.userGrowth.values;
            state.userGrowth.update('none');
        }
        if (state.status && data.status.labels) {
            state.status.data.labels = data.status.labels;
            state.status.data.datasets[0].data = data.status.values;
            state.status.update('none');
        }
        if (state.topSpots && data.topspots.labels) {
            state.topSpots.data.labels = data.topspots.labels;
            state.topSpots.data.datasets[0].data = data.topspots.values;
            state.topSpots.update('none');
        }
        if (state.categories && data.categories.labels) {
            state.categories.data.labels = data.categories.labels;
            state.categories.data.datasets[0].data = data.categories.values;
            state.categories.update('none');
        }
        if (state.review && data.review) {
            state.review.data.datasets[0].data = [
                data.review.applications || 0,
                data.review.deletions || 0,
                data.review.tenants || 0,
            ];
            state.review.update('none');
        }
    };

    window.renderAnalyticsCharts(false);

    if (!state.hooked) {
        state.hooked = true;

        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', ({ el }) => {
                if (el && el.id === 'analytics-data') {
                    setTimeout(() => window.renderAnalyticsCharts(false), 50);
                }
            });
        });

        let lastDark = document.documentElement.classList.contains('dark');
        new MutationObserver(() => {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark !== lastDark) {
                lastDark = isDark;
                window.renderAnalyticsCharts(true);
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }
})();
</script><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\analytics\⚡platform-analytics.blade.php ENDPATH**/ ?>