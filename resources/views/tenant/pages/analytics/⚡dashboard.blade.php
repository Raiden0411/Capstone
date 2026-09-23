{{-- resources/views/tenant/pages/analytics/⚡dashboard.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Analytics')]
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
        $this->authorizeViewAnalytics();

        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd   = now()->endOfMonth()->format('Y-m-d');
    }

    public function hydrate(): void
    {
        $this->authorizeViewAnalytics();
    }

    protected function authorizeViewAnalytics(): void
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
    //  KPI stats
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
                COALESCE(SUM(CASE WHEN status NOT IN ("cancelled","completed") AND check_in <= ? AND check_out > ? THEN 1 ELSE 0 END), 0) as active_count
            ', [$start, $end, $start, $end, $end, $start])
            ->first();

        $totalBookings  = (int) ($bookingAgg?->period_bookings ?? 0);
        $totalGuests    = (int) ($bookingAgg?->period_guests   ?? 0);
        $activeBookings = (int) ($bookingAgg?->active_count    ?? 0);

        $totalProperties = (int) Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
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
            'total_properties'    => $totalProperties,
            'active_bookings'     => $activeBookings,
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
    //  Chart data
    // ─────────────────────────────────────────────────────────

    /**
     * @return array<string, float>
     */
    #[Computed]
    public function revenueTrend(): array
    {
        [$start, $end] = $this->dateBounds;

        return Payment::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function bookingTrend(): array
    {
        [$start, $end] = $this->dateBounds;

        return Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereBetween('created_at', [$start, $end])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return array<int, array{method: string, total: float}>
     */
    #[Computed]
    public function paymentMethodBreakdown(): array
    {
        [$start, $end] = $this->dateBounds;

        return Payment::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($p) => [
                'method' => (string) $p->payment_method,
                'total'  => (float) $p->total,
            ])
            ->all();
    }

    /**
     * @return array<string, float>
     */
    #[Computed]
    public function occupancyTrend(): array
    {
        [$start, $end] = $this->dateBounds;
        $tenantId = Auth::user()->tenant_id;

        $totalProperties = (int) Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($totalProperties === 0) {
            return [];
        }

        $bookings = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
            ->where('check_in', '<=', $end)
            ->where('check_out', '>', $start)
            ->get(['check_in', 'check_out']);

        $trend   = [];
        $current = $start->copy()->startOfDay();

        while ($current->lte($end)) {
            $active = $bookings->filter(
                fn ($b) => $b->check_in->lte($current) && $b->check_out->gt($current),
            )->count();

            $trend[$current->toDateString()] = round(($active / $totalProperties) * 100, 1);
            $current->addDay();
        }

        return $trend;
    }

    /**
     * Top 5 properties by revenue for the selected period.
     *
     * @return array<int, array{name: string, bookings: int, revenue: float}>
     */
    #[Computed]
    public function propertyPerformance(): array
    {
        [$start, $end] = $this->dateBounds;
        $tenantId = Auth::user()->tenant_id;

        return DB::table('booking_items')
            ->join('properties', 'booking_items.property_id', '=', 'properties.id')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->where('booking_items.tenant_id', $tenantId)
            ->whereBetween('bookings.created_at', [$start, $end])
            ->whereNotIn('bookings.status', [Booking::STATUS_CANCELLED])
            ->select(
                'properties.name',
                DB::raw('COUNT(DISTINCT bookings.id) as bookings'),
                DB::raw('SUM(booking_items.subtotal) as revenue'),
            )
            ->groupBy('properties.id', 'properties.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name'     => (string) $row->name,
                'bookings' => (int) $row->bookings,
                'revenue'  => (float) $row->revenue,
            ])
            ->all();
    }

    /**
     * Booking status counts for the selected period.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function bookingStatusDistribution(): array
    {
        [$start, $end] = $this->dateBounds;

        $rows = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereBetween('created_at', [$start, $end])
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'pending'    => (int) ($rows['pending']    ?? 0),
            'confirmed'  => (int) ($rows['confirmed']  ?? 0),
            'reserved'   => (int) ($rows['reserved']   ?? 0),
            'checked_in' => (int) ($rows['checked_in'] ?? 0),
            'completed'  => (int) ($rows['completed']  ?? 0),
            'cancelled'  => (int) ($rows['cancelled']  ?? 0),
        ];
    }

    /**
     * Guest composition — new vs repeat guests across all-time bookings.
     *
     * @return array{new: int, repeat: int, total: int}
     */
    #[Computed]
    public function guestComposition(): array
    {
        $tenantId = Auth::user()->tenant_id;

        $userCounts = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select('user_id', DB::raw('COUNT(*) as bookings'))
            ->groupBy('user_id')
            ->pluck('bookings', 'user_id');

        $total  = $userCounts->count();
        $repeat = $userCounts->filter(fn ($c) => (int) $c > 1)->count();
        $new    = max(0, $total - $repeat);

        return [
            'new'    => $new,
            'repeat' => $repeat,
            'total'  => $total,
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Breakdowns & activity
    // ─────────────────────────────────────────────────────────

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    #[Computed]
    public function topServices()
    {
        [$start, $end] = $this->dateBounds;

        return DB::table('booking_services')
            ->join('services', 'booking_services.service_id', '=', 'services.id')
            ->join('bookings', 'booking_services.booking_id', '=', 'bookings.id')
            ->where('booking_services.tenant_id', Auth::user()->tenant_id)
            ->whereBetween('bookings.created_at', [$start, $end])
            ->select(
                'services.name',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(booking_services.subtotal) as revenue'),
            )
            ->groupBy('services.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    /**
     * @return array<int, array{name: string, share: float, total: float}>
     */
    #[Computed]
    public function revenueBreakdown(): array
    {
        $services = $this->topServices;
        $total    = (float) $services->sum('revenue');

        if ($total <= 0) {
            return [];
        }

        return $services->map(fn ($s) => [
            'name'  => (string) $s->name,
            'share' => round(((float) $s->revenue / $total) * 100, 1),
            'total' => (float) $s->revenue,
        ])->all();
    }

    /**
     * @return array{arrivals: \Illuminate\Support\Collection, departures: \Illuminate\Support\Collection}
     */
    #[Computed]
    public function upcomingActivity(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $today    = now()->toDateString();

        return [
            'arrivals' => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereDate('check_in', $today)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->with('user:id,name')
                ->select('id', 'user_id', 'booking_reference', 'check_in')
                ->get(),

            'departures' => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereDate('check_out', $today)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->with('user:id,name')
                ->select('id', 'user_id', 'booking_reference', 'check_out')
                ->get(),
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────

    public function clearCustomRange(): void
    {
        $this->authorizeViewAnalytics();

        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd   = now()->endOfMonth()->format('Y-m-d');
    }
};
?>

@php
    $s             = $this->stats;
    $propPerf      = $this->propertyPerformance;
    $statusDist    = $this->bookingStatusDistribution;
    $guestComp     = $this->guestComposition;
    $breakdowns    = $this->revenueBreakdown;
    $paymentBreak  = $this->paymentMethodBreakdown;

    $statusHasData  = array_sum($statusDist) > 0;
    $guestHasData   = ($guestComp['total'] ?? 0) > 0;
    $propHasData    = !empty($propPerf);
    $paymentHasData = !empty($paymentBreak);
@endphp

<div class="analytics-page p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-4 sm:space-y-6" wire:poll.60s>

    {{-- ═══ Hidden data bridge ═══ --}}
    <div id="analytics-chart-data"
         data-revenue="{{ json_encode($this->revenueTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-bookings="{{ json_encode($this->bookingTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-payment="{{ json_encode($paymentBreak, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-occupancy="{{ json_encode($this->occupancyTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-property-performance="{{ json_encode($propPerf, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-status="{{ json_encode($statusDist, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-guests="{{ json_encode($guestComp, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         hidden
         aria-hidden="true"></div>

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700 no-print">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Analytics</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Performance Overview
                </h1>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300
                             border border-emerald-200 dark:border-emerald-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live · 60s
                </span>
            </div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Track your property metrics, revenue, and guest insights.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                </svg>
                <span>Print Report</span>
            </button>
        </div>
    </div>

    {{-- ═══ Date range ═══ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-3 no-print">
        <div class="flex flex-wrap gap-2 items-center">
            @foreach([
                'today'      => 'Today',
                'yesterday'  => 'Yesterday',
                'last-7'     => '7 Days',
                'last-30'    => '30 Days',
                'this-month' => 'This Month',
                'last-month' => 'Last Month',
                'custom'     => 'Custom',
            ] as $val => $label)
                @php $isActive = $dateRange === $val; @endphp
                <button type="button"
                        wire:key="range-{{ $val }}"
                        wire:click="$set('dateRange', '{{ $val }}')"
                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $isActive
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                  : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($dateRange === 'custom')
            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Custom range</span>
                <input type="date" wire:model.live="customStart"
                       aria-label="Start date"
                       class="h-11 px-3 text-sm font-medium bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-700 rounded-xl
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:border-primary-500">
                <span class="text-gray-400 dark:text-gray-500 text-xs font-medium">to</span>
                <input type="date" wire:model.live="customEnd"
                       aria-label="End date"
                       class="h-11 px-3 text-sm font-medium bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-700 rounded-xl
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:border-primary-500">
            </div>
        @endif
    </div>

    {{-- ═══ KPI strip — 4 hero cards ═══ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">

        {{-- Revenue --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 sm:p-5">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Revenue</p>
            </div>
            <p class="mt-2 text-xl sm:text-2xl font-bold text-gray-900 dark:text-white tabular-nums leading-tight truncate">
                ₱{{ number_format($s['revenue'], 2) }}
            </p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                Paid this period
            </p>
        </div>

        {{-- Bookings --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 sm:p-5">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0" aria-hidden="true"></span>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Bookings</p>
            </div>
            <p class="mt-2 text-xl sm:text-2xl font-bold text-gray-900 dark:text-white tabular-nums leading-tight truncate">
                {{ number_format($s['total_bookings']) }}
            </p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                Created this period
            </p>
        </div>

        {{-- Occupancy --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 sm:p-5">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Occupancy</p>
            </div>
            <p class="mt-2 text-xl sm:text-2xl font-bold text-gray-900 dark:text-white tabular-nums leading-tight truncate">
                {{ $s['occupancy_rate'] }}%
            </p>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                {{ $s['active_bookings'] }} of {{ $s['total_properties'] }} active
            </p>
        </div>

        {{-- Outstanding --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border {{ $s['outstanding_balance'] > 0 ? 'border-amber-300 dark:border-amber-500/40 bg-amber-50/50 dark:bg-amber-500/[0.05]' : 'border-gray-200/80 dark:border-gray-700/80' }} shadow-sm p-4 sm:p-5">
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full {{ $s['outstanding_balance'] > 0 ? 'bg-amber-500' : 'bg-emerald-500' }} shrink-0" aria-hidden="true"></span>
                <p class="text-[10px] font-bold uppercase tracking-wider {{ $s['outstanding_balance'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Outstanding</p>
            </div>
            <p class="mt-2 text-xl sm:text-2xl font-bold tabular-nums leading-tight truncate {{ $s['outstanding_balance'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                ₱{{ number_format($s['outstanding_balance'], 2) }}
            </p>
            <p class="text-[11px] mt-1 {{ $s['outstanding_balance'] > 0 ? 'text-amber-700/80 dark:text-amber-400/80' : 'text-gray-500 dark:text-gray-400' }}">
                {{ $s['outstanding_balance'] > 0 ? 'Awaiting collection' : 'All settled' }}
            </p>
        </div>
    </div>

    {{-- ═══ Secondary metrics row ═══ --}}
    <div class="grid grid-cols-3 gap-3 sm:gap-4">
        <div class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Booking</p>
            <p class="mt-1.5 text-lg font-bold text-gray-900 dark:text-white tabular-nums truncate">
                ₱{{ number_format($s['avg_booking_value'], 2) }}
            </p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Repeat Guests</p>
            <p class="mt-1.5 text-lg font-bold text-gray-900 dark:text-white tabular-nums truncate">
                {{ $s['repeat_guest_rate'] }}%
            </p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Unique Guests</p>
            <p class="mt-1.5 text-lg font-bold text-gray-900 dark:text-white tabular-nums truncate">
                {{ number_format($s['total_guests']) }}
            </p>
        </div>
    </div>

    {{-- ═══ Row 1: Revenue Trend + Payment Methods ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <div class="lg:col-span-2 bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-px bg-primary-600"></span>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Revenue Trend</h2>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Daily paid revenue across the selected period.</p>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 shrink-0 tabular-nums">
                    ₱{{ number_format($s['revenue'], 0) }}
                </span>
            </div>
            <div class="w-full h-56 sm:h-64 relative" wire:ignore>
                <canvas id="revenueChart" role="img" aria-label="Bar chart: daily paid revenue"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 flex flex-col">
            <div class="mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Payment Methods</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Revenue split by method.</p>
            </div>

            @if($paymentHasData)
                <div class="flex-1 min-h-[200px] relative flex items-center justify-center" wire:ignore>
                    <canvas id="paymentChart" role="img" aria-label="Doughnut chart: revenue by payment method"></canvas>
                </div>
            @else
                <div class="flex-1 min-h-[200px] flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No payments yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Method breakdown appears once payments are collected.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ Row 2: Booking Activity + Occupancy History ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
            <div class="mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Booking Activity</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">New bookings created per day.</p>
            </div>
            <div class="w-full h-56 relative" wire:ignore>
                <canvas id="bookingChart" role="img" aria-label="Line chart: bookings per day"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
            <div class="mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Occupancy History</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Percentage of properties booked per day.</p>
            </div>
            <div class="w-full h-56 relative" wire:ignore>
                <canvas id="occupancyChart" role="img" aria-label="Line chart: occupancy percentage"></canvas>
            </div>
        </div>
    </div>

    {{-- ═══ Row 3: Property Performance + Booking Status ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <div class="lg:col-span-2 bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-px bg-primary-600"></span>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Property Performance</h2>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Top 5 properties by revenue in this period.</p>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 shrink-0">
                    Top 5
                </span>
            </div>

            @if($propHasData)
                <div class="w-full h-56 sm:h-64 relative" wire:ignore>
                    <canvas id="propertyPerformanceChart" role="img" aria-label="Horizontal bar chart: top performing properties"></canvas>
                </div>
            @else
                <div class="w-full h-56 sm:h-64 flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No property bookings yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Rankings populate once properties receive bookings.</p>
                </div>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 flex flex-col">
            <div class="mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Booking Status</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Operations snapshot for this period.</p>
            </div>

            @if($statusHasData)
                <div class="flex-1 min-h-[200px] relative flex items-center justify-center" wire:ignore>
                    <canvas id="statusChart" role="img" aria-label="Doughnut chart: booking status distribution"></canvas>
                </div>
            @else
                <div class="flex-1 min-h-[200px] flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No bookings yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Status breakdown appears once bookings are created.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ Row 4: Guest Composition + Top Services Breakdown ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 flex flex-col">
            <div class="mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Guest Composition</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Repeat vs first-time guests.</p>
            </div>

            @if($guestHasData)
                <div class="flex-1 min-h-[200px] relative flex items-center justify-center" wire:ignore>
                    <canvas id="guestChart" role="img" aria-label="Doughnut chart: repeat versus new guests"></canvas>
                </div>
            @else
                <div class="flex-1 min-h-[200px] flex flex-col items-center justify-center text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No guests yet</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Composition appears once bookings are recorded.</p>
                </div>
            @endif
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
            <div class="mb-5">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Top Services Breakdown</h2>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Revenue contribution by add-on service.</p>
            </div>

            @if(!empty($breakdowns))
                <div class="space-y-4">
                    @foreach($breakdowns as $b)
                        <div wire:key="svc-{{ md5($b['name']) }}">
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="font-medium text-gray-700 dark:text-gray-300 truncate pr-2">{{ $b['name'] }}</span>
                                <span class="text-gray-900 dark:text-white font-semibold tabular-nums shrink-0">₱{{ number_format($b['total'], 2) }}</span>
                            </div>
                            <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-primary-500 rounded-full transition-all duration-500" style="width: {{ $b['share'] }}%"></div>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 text-right tabular-nums">{{ $b['share'] }}% of total</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-40 text-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40">
                    <div class="p-3 rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No service data</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Service revenue breakdown appears once services are booked.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ Row 5: Arrivals + Departures ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Arrivals --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-emerald-500"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Arrivals Today</h2>
                </div>
                <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-emerald-100 dark:bg-emerald-500/15 text-emerald-800 dark:text-emerald-300 tabular-nums">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    {{ count($this->upcomingActivity['arrivals']) }}
                </span>
            </div>
            <div class="flex-1 overflow-y-auto pr-1 max-h-72">
                <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse($this->upcomingActivity['arrivals'] as $b)
                        <div wire:key="arrival-{{ $b->id }}" class="py-3 flex justify-between items-center">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($b->user->name ?? 'G', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $b->user->name ?? 'Guest' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate">#{{ $b->booking_reference }}</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 shrink-0">
                                Check-in
                            </span>
                        </div>
                    @empty
                        <div class="py-10 text-center">
                            <svg class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No arrivals scheduled today.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Departures --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-px bg-rose-500"></span>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Departures Today</h2>
                </div>
                <span class="inline-flex items-center gap-1.5 h-6 px-2.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-rose-100 dark:bg-rose-500/15 text-rose-800 dark:text-rose-300 tabular-nums">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500" aria-hidden="true"></span>
                    {{ count($this->upcomingActivity['departures']) }}
                </span>
            </div>
            <div class="flex-1 overflow-y-auto pr-1 max-h-72">
                <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse($this->upcomingActivity['departures'] as $b)
                        <div wire:key="departure-{{ $b->id }}" class="py-3 flex justify-between items-center">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-400 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($b->user->name ?? 'G', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $b->user->name ?? 'Guest' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate">#{{ $b->booking_reference }}</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 shrink-0">
                                Check-out
                            </span>
                        </div>
                    @empty
                        <div class="py-10 text-center">
                            <svg class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No departures scheduled today.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════
     CHART.JS WIRING

     Chart is now provided by the Vite bundle (see resources/js/app.js).
     No CDN script, no @stack('scripts') dependency.

     Bug fixes preserved from the previous revision:

     1. DETACHED CANVAS AFTER SPA NAVIGATION
        When the user navigates away and back via wire:navigate, Livewire
        morphs in new <canvas> elements. `state.revenue`, `state.payment`,
        etc. may still point at detached canvases from the previous visit.
        `chart.update()` on a detached chart is a silent no-op, leaving
        the fresh canvas blank. isChartAlive() detects this and rebuilds.

     2. livewire:init HAD ALREADY FIRED
        `@script` blocks run AFTER Livewire boots, so attaching a listener
        to `livewire:init` inside one is a no-op. We hook morph.updated
        directly if Livewire is already present.

     3. SPA arrivals also re-render
        livewire:navigated fires on every wire:navigate arrival. This is
        the safety net for the "first visit after SPA nav" case.
     ═══════════════════════════════════════════════════════════════════════ --}}
@script
<script>
    if (! window.__tenantAnalyticsRegistered) {
        window.__tenantAnalyticsRegistered = true;

        (function () {
            'use strict';

            const state = window.__tenantAnalytics = window.__tenantAnalytics || {
                revenue: null,
                booking: null,
                payment: null,
                occupancy: null,
                propPerf: null,
                status: null,
                guest: null,
                hooked: false,
                lastDark: document.documentElement.classList.contains('dark'),
                retries: 0,
            };

            const MAX_CHART_RETRIES = 30;

            const STATUS_ORDER = ['pending', 'confirmed', 'reserved', 'checked_in', 'completed', 'cancelled'];

            const STATUS_COLORS = {
                pending:    '#f59e0b',
                confirmed:  '#6366f1',
                reserved:   '#3b82f6',
                checked_in: '#8b5cf6',
                completed:  '#10b981',
                cancelled:  '#ef4444',
            };

            function isChartAlive(chart) {
                return !!(chart && chart.canvas && chart.canvas.isConnected);
            }

            function getChartData() {
                const el = document.getElementById('analytics-chart-data');
                if (!el) return null;

                try {
                    return {
                        revenue:    JSON.parse(el.dataset.revenue    || '{}'),
                        bookings:   JSON.parse(el.dataset.bookings   || '{}'),
                        payment:    JSON.parse(el.dataset.payment    || '[]'),
                        occupancy:  JSON.parse(el.dataset.occupancy  || '{}'),
                        propPerf:   JSON.parse(el.dataset.propertyPerformance || '[]'),
                        status:     JSON.parse(el.dataset.status     || '{}'),
                        guests:     JSON.parse(el.dataset.guests     || '{}'),
                    };
                } catch (e) {
                    console.error('Failed to parse analytics chart data', e);
                    return null;
                }
            }

            function isDark() {
                return document.documentElement.classList.contains('dark');
            }

            function getTheme() {
                return isDark() ? {
                    textColor:     '#9ca3af',
                    gridColor:     'rgba(255,255,255,0.05)',
                    barColor:      '#10b981',
                    lineBooking:   '#3b82f6',
                    lineOccupancy: '#f59e0b',
                    fillOpacity:   '0.15',
                    doughnutBorder: '#1f2937',
                } : {
                    textColor:     '#6b7280',
                    gridColor:     'rgba(0,0,0,0.05)',
                    barColor:      '#059669',
                    lineBooking:   '#2563eb',
                    lineOccupancy: '#d97706',
                    fillOpacity:   '0.1',
                    doughnutBorder: '#ffffff',
                };
            }

            function destroyAll() {
                ['revenue', 'booking', 'payment', 'occupancy', 'propPerf', 'status', 'guest'].forEach(key => {
                    if (state[key]) { try { state[key].destroy(); } catch (e) {} state[key] = null; }
                });
            }

            function destroyOne(key) {
                if (state[key]) {
                    try { state[key].destroy(); } catch (e) { /* noop */ }
                    state[key] = null;
                }
            }

            function gradient(ctx, rgb, opacity) {
                const g = ctx.createLinearGradient(0, 0, 0, 300);
                g.addColorStop(0, `rgba(${rgb}, ${opacity})`);
                g.addColorStop(1, 'rgba(255,255,255,0)');
                return g;
            }

            function tooltipBase() {
                return {
                    backgroundColor: isDark() ? '#374151' : '#fff',
                    titleColor:      isDark() ? '#fff'    : '#111827',
                    bodyColor:       isDark() ? '#d1d5db' : '#4b5563',
                    borderColor:     isDark() ? '#4b5563' : '#e5e7eb',
                    borderWidth:     1,
                    padding:         12,
                    displayColors:   false,
                    cornerRadius:    10,
                };
            }

            function barChart(canvasId, labels, values, label, color) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return null;
                const theme = getTheme();

                return new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label,
                            data: values,
                            backgroundColor: color,
                            borderRadius: 6,
                            borderSkipped: false,
                            barPercentage: 0.4,
                            categoryPercentage: 0.75,
                            maxBarThickness: 24,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                ...tooltipBase(),
                                callbacks: {
                                    label: (ctx) => '₱' + (ctx.parsed.y || 0).toLocaleString(),
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: theme.textColor },
                                grid:  { color: theme.gridColor, drawBorder: false },
                                border: { display: false },
                            },
                            x: {
                                ticks: { color: theme.textColor },
                                grid:  { display: false },
                                border: { display: false },
                            },
                        },
                    },
                });
            }

            function horizontalBarChart(canvasId, labels, values, colors) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return null;
                const theme = getTheme();

                return new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderRadius: 6,
                            borderSkipped: false,
                            barPercentage: 0.7,
                            categoryPercentage: 0.75,
                            maxBarThickness: 20,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { right: 32 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                ...tooltipBase(),
                                callbacks: {
                                    label: (ctx) => '₱' + Number(ctx.parsed.x || 0).toLocaleString(),
                                },
                            },
                        },
                        scales: {
                            y: {
                                grid:   { display: false },
                                border: { display: false },
                                ticks: {
                                    color: theme.textColor,
                                    font: { size: 11, weight: '600' },
                                    autoSkip: false,
                                    padding: 4,
                                    callback: function (v) {
                                        const label = this.getLabelForValue(v);
                                        return label.length > 24 ? label.slice(0, 22) + '…' : label;
                                    },
                                },
                            },
                            x: {
                                beginAtZero: true,
                                grid:  { color: theme.gridColor, drawBorder: false },
                                border: { display: false },
                                ticks: { color: theme.textColor, precision: 0 },
                            },
                        },
                    },
                });
            }

            function lineChart(canvasId, labels, values, label, color, rgb) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return null;
                const theme = getTheme();
                const ctx = canvas.getContext('2d');

                return new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label,
                            data: values,
                            borderColor: color,
                            backgroundColor: gradient(ctx, rgb, theme.fillOpacity),
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 0,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: color,
                            pointBorderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: tooltipBase(),
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: theme.textColor, maxTicksLimit: 6 },
                                grid:  { color: theme.gridColor, drawBorder: false, borderDash: [5, 5] },
                                border: { display: false },
                            },
                            x: {
                                ticks: { color: theme.textColor, maxTicksLimit: 8 },
                                grid:  { display: false },
                                border: { display: false },
                            },
                        },
                    },
                });
            }

            function doughnutChart(canvasId, labels, values, colors, legendPosition) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return null;
                const theme = getTheme();

                return new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors || ['#059669', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444', '#94a3b8'],
                            borderWidth: isDark() ? 2 : 1,
                            borderColor: theme.doughnutBorder,
                            hoverOffset: 4,
                            spacing: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: {
                                position: legendPosition || 'bottom',
                                labels: {
                                    color: theme.textColor,
                                    padding: 10,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    boxWidth: 8,
                                    boxHeight: 8,
                                    font: { size: 10, weight: '600' },
                                },
                            },
                            tooltip: {
                                ...tooltipBase(),
                                callbacks: {
                                    label: (ctx) => ' ' + ctx.label + ': ' + Number(ctx.parsed || 0).toLocaleString(),
                                },
                            },
                        },
                    },
                });
            }

            window.renderTenantAnalytics = function (force) {
                if (typeof Chart === 'undefined') {
                    if (state.retries >= MAX_CHART_RETRIES) {
                        console.warn('Chart.js failed to load — giving up after ' + MAX_CHART_RETRIES + ' retries.');
                        return;
                    }
                    state.retries++;
                    setTimeout(() => window.renderTenantAnalytics(force), 100);
                    return;
                }

                state.retries = 0;

                const data = getChartData();
                if (!data) return;

                if (force) destroyAll();

                const theme = getTheme();

                if (document.getElementById('revenueChart')) {
                    if (!isChartAlive(state.revenue)) {
                        destroyOne('revenue');
                        state.revenue = barChart(
                            'revenueChart',
                            Object.keys(data.revenue),
                            Object.values(data.revenue),
                            'Revenue',
                            theme.barColor,
                        );
                    } else {
                        state.revenue.data.labels = Object.keys(data.revenue);
                        state.revenue.data.datasets[0].data = Object.values(data.revenue);
                        state.revenue.update('none');
                    }
                }

                if (document.getElementById('paymentChart')) {
                    if (!isChartAlive(state.payment)) {
                        destroyOne('payment');
                        state.payment = doughnutChart(
                            'paymentChart',
                            data.payment.map(p => p.method.charAt(0).toUpperCase() + p.method.slice(1)),
                            data.payment.map(p => p.total),
                            null,
                            'bottom',
                        );
                    } else {
                        state.payment.data.labels = data.payment.map(p => p.method.charAt(0).toUpperCase() + p.method.slice(1));
                        state.payment.data.datasets[0].data = data.payment.map(p => p.total);
                        state.payment.update('none');
                    }
                }

                if (document.getElementById('bookingChart')) {
                    if (!isChartAlive(state.booking)) {
                        destroyOne('booking');
                        state.booking = lineChart(
                            'bookingChart',
                            Object.keys(data.bookings),
                            Object.values(data.bookings),
                            'Bookings',
                            theme.lineBooking,
                            isDark() ? '59, 130, 246' : '37, 99, 235',
                        );
                    } else {
                        state.booking.data.labels = Object.keys(data.bookings);
                        state.booking.data.datasets[0].data = Object.values(data.bookings);
                        state.booking.update('none');
                    }
                }

                if (document.getElementById('occupancyChart')) {
                    if (!isChartAlive(state.occupancy)) {
                        destroyOne('occupancy');
                        state.occupancy = lineChart(
                            'occupancyChart',
                            Object.keys(data.occupancy),
                            Object.values(data.occupancy),
                            'Occupancy %',
                            theme.lineOccupancy,
                            isDark() ? '245, 158, 11' : '217, 119, 6',
                        );
                    } else {
                        state.occupancy.data.labels = Object.keys(data.occupancy);
                        state.occupancy.data.datasets[0].data = Object.values(data.occupancy);
                        state.occupancy.update('none');
                    }
                }

                if (document.getElementById('propertyPerformanceChart')) {
                    if (!isChartAlive(state.propPerf)) {
                        destroyOne('propPerf');
                        const ramp = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'];
                        state.propPerf = horizontalBarChart(
                            'propertyPerformanceChart',
                            data.propPerf.map(p => p.name),
                            data.propPerf.map(p => p.revenue),
                            ramp.slice(0, data.propPerf.length),
                        );
                    } else {
                        state.propPerf.data.labels = data.propPerf.map(p => p.name);
                        state.propPerf.data.datasets[0].data = data.propPerf.map(p => p.revenue);
                        state.propPerf.update('none');
                    }
                }

                if (document.getElementById('statusChart')) {
                    const present = STATUS_ORDER.filter(k => (data.status[k] || 0) > 0);
                    const labels  = present.map(k => k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
                    const values  = present.map(k => data.status[k]);
                    const colors  = present.map(k => STATUS_COLORS[k]);

                    if (!isChartAlive(state.status)) {
                        destroyOne('status');
                        state.status = doughnutChart('statusChart', labels, values, colors, 'bottom');
                    } else {
                        state.status.data.labels = labels;
                        state.status.data.datasets[0].data = values;
                        state.status.data.datasets[0].backgroundColor = colors;
                        state.status.update('none');
                    }
                }

                if (document.getElementById('guestChart')) {
                    const guestValues = [data.guests.new || 0, data.guests.repeat || 0];

                    if (!isChartAlive(state.guest)) {
                        destroyOne('guest');
                        state.guest = doughnutChart(
                            'guestChart',
                            ['New Guests', 'Repeat Guests'],
                            guestValues,
                            ['#3b82f6', '#8b5cf6'],
                            'bottom',
                        );
                    } else {
                        state.guest.data.datasets[0].data = guestValues;
                        state.guest.update('none');
                    }
                }
            };

            window.renderTenantAnalytics(false);

            if (!state.hooked) {
                state.hooked = true;

                const registerMorphHook = () => {
                    if (!window.Livewire || typeof window.Livewire.hook !== 'function') {
                        return false;
                    }

                    window.Livewire.hook('morph.updated', ({ el }) => {
                        if (el && el.id === 'analytics-chart-data') {
                            setTimeout(() => window.renderTenantAnalytics(false), 30);
                        }
                    });

                    return true;
                };

                if (!registerMorphHook()) {
                    document.addEventListener('livewire:init', registerMorphHook, { once: true });
                }

                document.addEventListener('livewire:navigated', () => {
                    if (document.getElementById('analytics-chart-data')) {
                        setTimeout(() => window.renderTenantAnalytics(false), 60);
                    }
                });

                new MutationObserver(() => {
                    const dark = isDark();
                    if (dark !== state.lastDark) {
                        state.lastDark = dark;
                        window.renderTenantAnalytics(true);
                    }
                }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            }
        })();
    }
</script>
@endscript