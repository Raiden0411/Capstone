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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Analytics')]
class extends Component
{
    public string $dateRange   = 'this-month';
    public string $customStart = '';
    public string $customEnd   = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);

        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd   = now()->endOfMonth()->format('Y-m-d');
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

        // Revenue for the period — single query.
        $revenue = (float) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        // Booking-related aggregates — one query.
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

        // Outstanding balance across non-terminal bookings.
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
     * Occupancy per day — one query, computed in memory.
     *
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
    //  Export
    // ─────────────────────────────────────────────────────────

    public function exportCsv()
    {
        abort_unless(Auth::user()?->tenant_id, 403);

        [$start, $end] = $this->dateBounds;

        $bookings = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereBetween('created_at', [$start, $end])
            ->with('user:id,name,email')
            ->select('id', 'user_id', 'booking_reference', 'check_in', 'check_out', 'total_amount', 'status')
            ->get();

        $filename = 'analytics_bookings_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($bookings): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Reference', 'Guest', 'Email', 'Check-in', 'Check-out', 'Total', 'Status']);

            foreach ($bookings as $b) {
                fputcsv($file, [
                    $b->booking_reference,
                    $b->user->name  ?? 'N/A',
                    $b->user->email ?? '',
                    $b->check_in?->format('Y-m-d'),
                    $b->check_out?->format('Y-m-d'),
                    number_format((float) $b->total_amount, 2),
                    $b->status,
                ]);
            }

            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-8" wire:poll.60s>

    {{-- Hidden data holder — inside root (Livewire v4 single-root rule) --}}
    <div id="analytics-chart-data"
         data-revenue="{{ json_encode($this->revenueTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-bookings="{{ json_encode($this->bookingTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-payment="{{ json_encode($this->paymentMethodBreakdown, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-occupancy="{{ json_encode($this->occupancyTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         hidden
         aria-hidden="true"></div>

    {{-- ═══════════════ HEADER ═══════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                Analytics
            </p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Performance Overview
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Track your property metrics and revenue.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
            <button type="button"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    wire:target="exportCsv"
                    class="btn-secondary active:scale-95 transition-transform
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                           inline-flex items-center justify-center gap-2 disabled:opacity-60">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Exporting…
                </span>
            </button>

            <button type="button" onclick="window.print()"
                    class="btn-secondary active:scale-95 transition-transform
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                           inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                </svg>
                Print
            </button>
        </div>
    </div>

    {{-- ═══════════════ DATE RANGE ═══════════════ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-2 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div class="inline-flex flex-wrap items-center p-1 bg-gray-100 dark:bg-gray-900/50 rounded-lg w-full xl:w-auto">
            @foreach([
                'today'      => 'Today',
                'yesterday'  => 'Yesterday',
                'last-7'     => '7 Days',
                'last-30'    => '30 Days',
                'this-month' => 'This Month',
                'last-month' => 'Last Month',
                'custom'     => 'Custom',
            ] as $val => $label)
                <button type="button"
                        wire:key="range-{{ $val }}"
                        wire:click="$set('dateRange', '{{ $val }}')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $dateRange === $val
                                  ? 'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white'
                                  : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($dateRange === 'custom')
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 px-2 pb-2 xl:pb-0">
                <input type="date" wire:model.live="customStart" class="input !py-2 !w-full sm:!w-auto">
                <span class="text-gray-400 text-sm">to</span>
                <input type="date" wire:model.live="customEnd" class="input !py-2 !w-full sm:!w-auto">
            </div>
        @endif
    </div>

    {{-- ═══════════════ KPI CARDS ═══════════════ --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-6">
        {{-- Revenue --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Revenue</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white mt-1 tabular-nums">
                    ₱{{ number_format($s['revenue'], 2) }}
                </p>
            </div>
            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 rounded-xl shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        {{-- Bookings --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Bookings</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white mt-1 tabular-nums">
                    {{ number_format($s['total_bookings']) }}
                </p>
            </div>
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-xl shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>

        {{-- Unique guests --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Unique Guests</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white mt-1 tabular-nums">
                    {{ number_format($s['total_guests']) }}
                </p>
            </div>
            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 rounded-xl shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
        </div>

        {{-- Occupancy --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Occupancy Rate</p>
                <p class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white mt-1 tabular-nums">
                    {{ $s['occupancy_rate'] }}%
                </p>
            </div>
            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded-xl shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- ═══════════════ SECONDARY METRICS ═══════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 xl:gap-6">
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Booking Value</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">₱{{ number_format($s['avg_booking_value'], 2) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-rose-200 dark:border-rose-900/30 shadow-sm p-5 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">Outstanding Balance</p>
            <p class="text-lg font-bold text-rose-600 dark:text-rose-400 tabular-nums">₱{{ number_format($s['outstanding_balance'], 2) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Repeat Guest Rate</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">{{ $s['repeat_guest_rate'] }}%</p>
        </div>
    </div>

    {{-- ═══════════════ CHARTS ROW 1 ═══════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Revenue Trend</h2>
            <div class="w-full h-[300px]" wire:ignore>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 flex flex-col">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Payment Methods</h2>
            <div class="w-full h-[300px] flex-1 relative flex items-center justify-center" wire:ignore>
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ═══════════════ CHARTS ROW 2 ═══════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Booking Activity</h2>
            <div class="w-full h-[280px]" wire:ignore>
                <canvas id="bookingChart"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Occupancy History</h2>
            <div class="w-full h-[280px]" wire:ignore>
                <canvas id="occupancyChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ═══════════════ BREAKDOWN & ACTIVITY ═══════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Top services breakdown --}}
        <div class="lg:col-span-1 bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-6">Top Services Breakdown</h2>

            @php $breakdowns = $this->revenueBreakdown; @endphp
            @if(!empty($breakdowns))
                <div class="space-y-5">
                    @foreach($breakdowns as $b)
                        <div wire:key="svc-{{ md5($b['name']) }}">
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="font-medium text-gray-700 dark:text-gray-300 truncate pr-2">{{ $b['name'] }}</span>
                                <span class="text-gray-900 dark:text-white font-semibold tabular-nums shrink-0">₱{{ number_format($b['total'], 2) }}</span>
                            </div>
                            <div class="w-full h-2.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-primary-500 rounded-full transition-all duration-500" style="width: {{ $b['share'] }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 text-right tabular-nums">{{ $b['share'] }}% of total</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-40 text-center">
                    <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No service data for this period.</p>
                </div>
            @endif
        </div>

        {{-- Arrivals + Departures --}}
        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">

            {{-- Arrivals --}}
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 motion-reduce:animate-none"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        Arrivals Today
                    </h2>
                    <span class="text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 px-2 py-1 rounded-full tabular-nums">
                        {{ count($this->upcomingActivity['arrivals']) }}
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto pr-2">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @forelse($this->upcomingActivity['arrivals'] as $b)
                            <div wire:key="arrival-{{ $b->id }}" class="py-3 flex justify-between items-center">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 font-medium text-xs shrink-0">
                                        {{ strtoupper(substr($b->user->name ?? 'G', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $b->user->name ?? 'Guest' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">#{{ $b->booking_reference }}</p>
                                    </div>
                                </div>
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-md flex items-center gap-1 shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Check-in
                                </span>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center h-32 text-center">
                                <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">No arrivals scheduled today.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Departures --}}
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                        Departures Today
                    </h2>
                    <span class="text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400 px-2 py-1 rounded-full tabular-nums">
                        {{ count($this->upcomingActivity['departures']) }}
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto pr-2">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @forelse($this->upcomingActivity['departures'] as $b)
                            <div wire:key="departure-{{ $b->id }}" class="py-3 flex justify-between items-center">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 font-medium text-xs shrink-0">
                                        {{ strtoupper(substr($b->user->name ?? 'G', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $b->user->name ?? 'Guest' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">#{{ $b->booking_reference }}</p>
                                    </div>
                                </div>
                                <span class="text-xs text-rose-500 dark:text-rose-400 font-medium bg-rose-50 dark:bg-rose-900/20 px-2 py-1 rounded-md flex items-center gap-1 shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Check-out
                                </span>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center h-32 text-center">
                                <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
</div>

<script>
(function () {
    'use strict';

    const state = window.__tenantAnalytics = window.__tenantAnalytics || {
        revenue: null,
        booking: null,
        payment: null,
        occupancy: null,
        hooked: false,
        lastDark: document.documentElement.classList.contains('dark'),
    };

    function getChartData() {
        const el = document.getElementById('analytics-chart-data');
        if (!el) return null;

        try {
            return {
                revenue:   JSON.parse(el.dataset.revenue   || '{}'),
                bookings:  JSON.parse(el.dataset.bookings  || '{}'),
                payment:   JSON.parse(el.dataset.payment   || '[]'),
                occupancy: JSON.parse(el.dataset.occupancy || '{}'),
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
        } : {
            textColor:     '#6b7280',
            gridColor:     'rgba(0,0,0,0.05)',
            barColor:      '#059669',
            lineBooking:   '#2563eb',
            lineOccupancy: '#d97706',
            fillOpacity:   '0.1',
        };
    }

    function destroyAll() {
        ['revenue', 'booking', 'payment', 'occupancy'].forEach(key => {
            if (state[key]) {
                try { state[key].destroy(); } catch (e) { /* noop */ }
                state[key] = null;
            }
        });
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
                    barPercentage: 0.6,
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

    function doughnutChart(canvasId, labels, values) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        const theme = getTheme();

        return new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#059669', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444'],
                    borderWidth: isDark() ? 2 : 0,
                    borderColor: isDark() ? '#1f2937' : '#ffffff',
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: theme.textColor,
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' ₱' + (ctx.parsed || 0).toLocaleString(),
                        },
                    },
                },
            },
        });
    }

    window.renderTenantAnalytics = function (force) {
        if (typeof Chart === 'undefined') {
            setTimeout(() => window.renderTenantAnalytics(force), 100);
            return;
        }

        const data = getChartData();
        if (!data) return;

        if (force) destroyAll();

        const theme = getTheme();

        if (!state.revenue && document.getElementById('revenueChart')) {
            state.revenue = barChart(
                'revenueChart',
                Object.keys(data.revenue),
                Object.values(data.revenue),
                'Revenue',
                theme.barColor,
            );
        }

        if (!state.booking && document.getElementById('bookingChart')) {
            state.booking = lineChart(
                'bookingChart',
                Object.keys(data.bookings),
                Object.values(data.bookings),
                'Bookings',
                theme.lineBooking,
                isDark() ? '59, 130, 246' : '37, 99, 235',
            );
        }

        if (!state.payment && document.getElementById('paymentChart')) {
            state.payment = doughnutChart(
                'paymentChart',
                data.payment.map(p => p.method.charAt(0).toUpperCase() + p.method.slice(1)),
                data.payment.map(p => p.total),
            );
        }

        if (!state.occupancy && document.getElementById('occupancyChart')) {
            state.occupancy = lineChart(
                'occupancyChart',
                Object.keys(data.occupancy),
                Object.values(data.occupancy),
                'Occupancy %',
                theme.lineOccupancy,
                isDark() ? '245, 158, 11' : '217, 119, 6',
            );
        }

        // Update-in-place for subsequent renders.
        if (state.revenue && data.revenue) {
            state.revenue.data.labels = Object.keys(data.revenue);
            state.revenue.data.datasets[0].data = Object.values(data.revenue);
            state.revenue.update('none');
        }
        if (state.booking && data.bookings) {
            state.booking.data.labels = Object.keys(data.bookings);
            state.booking.data.datasets[0].data = Object.values(data.bookings);
            state.booking.update('none');
        }
        if (state.payment && data.payment) {
            state.payment.data.labels = data.payment.map(p => p.method.charAt(0).toUpperCase() + p.method.slice(1));
            state.payment.data.datasets[0].data = data.payment.map(p => p.total);
            state.payment.update('none');
        }
        if (state.occupancy && data.occupancy) {
            state.occupancy.data.labels = Object.keys(data.occupancy);
            state.occupancy.data.datasets[0].data = Object.values(data.occupancy);
            state.occupancy.update('none');
        }
    };

    // First render.
    window.renderTenantAnalytics(false);

    // One-time hooks (survive wire:navigate).
    if (!state.hooked) {
        state.hooked = true;

        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', ({ el }) => {
                if (el && el.id === 'analytics-chart-data') {
                    setTimeout(() => window.renderTenantAnalytics(false), 30);
                }
            });
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
</script>