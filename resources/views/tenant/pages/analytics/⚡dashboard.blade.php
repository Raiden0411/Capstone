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

    /** @return array{0: Carbon, 1: Carbon} */
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

    /**
     * Pad a date-keyed series so every day in the selected range is
     * present with a 0 default. Without this, a chart with sparse data
     * (e.g. Oct 1 on the first day of the month) renders 1 bar and
     * looks broken. Future days beyond today are excluded.
     *
     * @param  array<string, int|float>  $data
     * @return array<string, int|float>
     */
    private function padTrendWithZeroes(array $data, Carbon $start, Carbon $end): array
    {
        $padded = [];
        $cursor = $start->copy()->startOfDay();
        $cap    = $end->isFuture() ? now()->startOfDay() : $end->copy()->startOfDay();

        while ($cursor->lte($cap)) {
            $key          = $cursor->toDateString();
            $padded[$key] = $data[$key] ?? 0;
            $cursor->addDay();
        }

        return $padded;
    }

    /** @return array<string, float|int> */
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
            ->selectRaw("
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as period_bookings,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND status != ? THEN 1 ELSE 0 END), 0) as period_non_cancelled,
                COALESCE(COUNT(DISTINCT CASE WHEN created_at BETWEEN ? AND ? THEN user_id END), 0) as period_guests,
                COALESCE(SUM(CASE WHEN status NOT IN (?, ?) AND check_in <= ? AND check_out >= ? THEN 1 ELSE 0 END), 0) as active_count
            ", [
                $start, $end,
                $start, $end, Booking::STATUS_CANCELLED,
                $start, $end,
                Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED, $end, $start,
            ])
            ->first();

        $totalBookings        = (int) ($bookingAgg?->period_bookings      ?? 0);
        $nonCancelledBookings = (int) ($bookingAgg?->period_non_cancelled ?? 0);
        $totalGuests          = (int) ($bookingAgg?->period_guests        ?? 0);
        $activeBookings       = (int) ($bookingAgg?->active_count         ?? 0);

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

        $refundsProcessed = (float) Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('refund_status', Booking::REFUND_STATUS_PROCESSED)
            ->whereNotNull('refund_processed_at')
            ->whereBetween('refund_processed_at', [$start, $end])
            ->sum('refund_amount');

        $refundsPending = (float) Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('refund_status', Booking::REFUND_STATUS_PENDING)
            ->sum('refund_amount');

        $cancellationAgg = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('status', Booking::STATUS_CANCELLED)
            ->whereRaw('COALESCE(cancelled_at, updated_at) BETWEEN ? AND ?', [$start, $end])
            ->selectRaw("
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN cancelled_by = 'tourist' THEN 1 ELSE 0 END), 0) as by_tourist,
                COALESCE(SUM(CASE WHEN cancelled_by = 'admin'   THEN 1 ELSE 0 END), 0) as by_admin
            ")
            ->first();

        $cancellationsCount   = (int) ($cancellationAgg->total      ?? 0);
        $cancellationsTourist = (int) ($cancellationAgg->by_tourist ?? 0);
        $cancellationsAdmin   = (int) ($cancellationAgg->by_admin   ?? 0);

        $occupancy       = $totalProperties > 0 ? round(($activeBookings / $totalProperties) * 100, 1) : 0.0;
        $netRevenue      = $revenue - $refundsProcessed;
        $avgBookingValue = $nonCancelledBookings > 0 ? round($netRevenue / $nonCancelledBookings, 2) : 0.0;

        return [
            'revenue'                => $revenue,
            'net_revenue'            => $netRevenue,
            'refunds_processed'      => $refundsProcessed,
            'refunds_pending'        => $refundsPending,
            'total_bookings'         => $totalBookings,
            'total_guests'           => $totalGuests,
            'occupancy_rate'         => $occupancy,
            'avg_booking_value'      => $avgBookingValue,
            'outstanding_balance'    => (float) $outstandingBalance,
            'repeat_guest_rate'      => $this->repeatGuestRate,
            'total_properties'       => $totalProperties,
            'active_bookings'        => $activeBookings,
            'cancellations'          => $cancellationsCount,
            'cancellations_tourist'  => $cancellationsTourist,
            'cancellations_admin'    => $cancellationsAdmin,
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

    /** @return array<string, float> */
    #[Computed]
    public function revenueTrend(): array
    {
        [$start, $end] = $this->dateBounds;

        $raw = Payment::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (float) $v)
            ->all();

        return $this->padTrendWithZeroes($raw, $start, $end);
    }

    /** @return array<string, float> */
    #[Computed]
    public function refundsTrend(): array
    {
        [$start, $end] = $this->dateBounds;

        $raw = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('refund_status', Booking::REFUND_STATUS_PROCESSED)
            ->whereNotNull('refund_processed_at')
            ->whereBetween('refund_processed_at', [$start, $end])
            ->select(DB::raw('DATE(refund_processed_at) as date'), DB::raw('SUM(refund_amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (float) $v)
            ->all();

        return $this->padTrendWithZeroes($raw, $start, $end);
    }

    /** @return array<string, int> */
    #[Computed]
    public function cancellationsTrend(): array
    {
        [$start, $end] = $this->dateBounds;

        $raw = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', Booking::STATUS_CANCELLED)
            ->whereRaw('COALESCE(cancelled_at, updated_at) BETWEEN ? AND ?', [$start, $end])
            ->select(DB::raw('DATE(COALESCE(cancelled_at, updated_at)) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (int) $v)
            ->all();

        return $this->padTrendWithZeroes($raw, $start, $end);
    }

    /** @return array<string, int> */
    #[Computed]
    public function refundStatusDistribution(): array
    {
        [$start, $end] = $this->dateBounds;

        $rows = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', Booking::STATUS_CANCELLED)
            ->whereRaw('COALESCE(cancelled_at, updated_at) BETWEEN ? AND ?', [$start, $end])
            ->select('refund_status', DB::raw('COUNT(*) as total'))
            ->groupBy('refund_status')
            ->pluck('total', 'refund_status')
            ->all();

        return [
            'none'      => (int) ($rows['none']      ?? 0),
            'pending'   => (int) ($rows['pending']   ?? 0),
            'processed' => (int) ($rows['processed'] ?? 0),
            'rejected'  => (int) ($rows['rejected']  ?? 0),
        ];
    }

    /** @return array<string, int> */
    #[Computed]
    public function cancellationsByParty(): array
    {
        [$start, $end] = $this->dateBounds;

        $rows = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', Booking::STATUS_CANCELLED)
            ->whereRaw('COALESCE(cancelled_at, updated_at) BETWEEN ? AND ?', [$start, $end])
            ->select('cancelled_by', DB::raw('COUNT(*) as total'))
            ->groupBy('cancelled_by')
            ->pluck('total', 'cancelled_by')
            ->all();

        return [
            'tourist' => (int) ($rows['tourist'] ?? 0),
            'admin'   => (int) ($rows['admin']   ?? 0),
            'legacy'  => (int) ($rows['']        ?? 0) + (int) ($rows[null] ?? 0),
        ];
    }

    /** @return array<string, int> */
    #[Computed]
    public function bookingTrend(): array
    {
        [$start, $end] = $this->dateBounds;

        $raw = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereBetween('created_at', [$start, $end])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (int) $v)
            ->all();

        return $this->padTrendWithZeroes($raw, $start, $end);
    }

    /** @return array<int, array{method: string, total: float}> */
    #[Computed]
    public function paymentMethodBreakdown(): array
    {
        [$start, $end] = $this->dateBounds;

        // LOWER() normalises case drift so 'gcash' and 'GCash' don't render as two slices.
        return Payment::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select(DB::raw('LOWER(payment_method) as payment_method'), DB::raw('SUM(amount) as total'))
            ->groupBy(DB::raw('LOWER(payment_method)'))
            ->orderByDesc('total')
            ->get()
            ->map(fn ($p) => [
                'method' => (string) $p->payment_method,
                'total'  => (float) $p->total,
            ])
            ->all();
    }

    /** @return array<string, float> */
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

        // Distinct properties occupied per day. Counted from booking_items so a
        // booking that spans multiple properties contributes each property, and
        // a property booked by two overlapping guests is counted once.
        $rows = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->where('booking_items.tenant_id', $tenantId)
            ->where('bookings.tenant_id', $tenantId)
            ->whereNotIn('bookings.status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
            ->where('bookings.check_in', '<=', $end)
            ->where('bookings.check_out', '>=', $start)
            ->select('booking_items.property_id', 'bookings.check_in', 'bookings.check_out')
            ->get();

        $bookings = $rows->map(fn ($r) => [
            'property_id' => $r->property_id,
            'check_in'    => Carbon::parse($r->check_in)->startOfDay(),
            'check_out'   => Carbon::parse($r->check_out)->startOfDay(),
        ]);

        $trend  = [];
        $cursor = $start->copy()->startOfDay();
        $cap    = $end->isFuture() ? now()->startOfDay() : $end->copy()->startOfDay();

        while ($cursor->lte($cap)) {
            $occupied = $bookings
                ->filter(fn ($b) => $b['check_in']->lte($cursor) && $b['check_out']->gte($cursor))
                ->pluck('property_id')
                ->unique()
                ->count();

            $trend[$cursor->toDateString()] = round(($occupied / $totalProperties) * 100, 1);
            $cursor->addDay();
        }

        return $trend;
    }

    /** @return array<int, array{name: string, bookings: int, revenue: float}> */
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

    /** @return array<string, int> */
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

    /** @return array{new: int, repeat: int, total: int} */
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

    /** @return \Illuminate\Support\Collection<int, object> */
    #[Computed]
    public function topServices()
    {
        [$start, $end] = $this->dateBounds;

        // Cancelled bookings' services were previously still summed here,
        // inflating service revenue and every downstream share.
        return DB::table('booking_services')
            ->join('services', 'booking_services.service_id', '=', 'services.id')
            ->join('bookings', 'booking_services.booking_id', '=', 'bookings.id')
            ->where('booking_services.tenant_id', Auth::user()->tenant_id)
            ->whereBetween('bookings.created_at', [$start, $end])
            ->whereNotIn('bookings.status', [Booking::STATUS_CANCELLED])
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

    /** @return array<int, array{name: string, share: float, total: float}> */
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

    /** @return array{arrivals: \Illuminate\Support\Collection, departures: \Illuminate\Support\Collection} */
    #[Computed]
    public function upcomingActivity(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $today    = now()->toDateString();

        return [
            'arrivals' => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('check_in', $today)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->with('user:id,name')
                ->select('id', 'user_id', 'booking_reference', 'check_in')
                ->get(),

            'departures' => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('check_out', $today)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->with('user:id,name')
                ->select('id', 'user_id', 'booking_reference', 'check_out')
                ->get(),
        ];
    }

    public function clearCustomRange(): void
    {
        $this->authorizeViewAnalytics();

        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd   = now()->endOfMonth()->format('Y-m-d');
    }
};
?>

@push('styles')
    @once
        <style>
            .analytics-page canvas {
                display:    block !important;
                width:      100%  !important;
                height:     100%  !important;
                max-height: 100% !important;
            }

            .tenant-analytics-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-analytics-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }

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

                .no-print { display: none !important; }

                canvas { print-color-adjust: exact; -webkit-print-color-adjust: exact; }

                .analytics-page > * {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
            }
        </style>
    @endonce
@endpush

@php
    $s            = $this->stats;
    $propPerf     = $this->propertyPerformance;
    $statusDist   = $this->bookingStatusDistribution;
    $guestComp    = $this->guestComposition;
    $breakdowns   = $this->revenueBreakdown;
    $paymentBreak = $this->paymentMethodBreakdown;
    $refundDist   = $this->refundStatusDistribution;
    $cancelParty  = $this->cancellationsByParty;

    $statusHasData  = array_sum($statusDist) > 0;
    $guestHasData   = ($guestComp['total'] ?? 0) > 0;
    $propHasData    = !empty($propPerf);
    $paymentHasData = !empty($paymentBreak);
    $refundHasData  = array_sum($refundDist) > 0;
    $cancelHasData  = array_sum($cancelParty) > 0;

    $revenueCents    = (int) round((float) $s['revenue'] * 100);
    $revenueWhole    = intdiv($revenueCents, 100);
    $revenueFraction = str_pad((string) ($revenueCents % 100), 2, '0', STR_PAD_LEFT);

    $netCents    = (int) round((float) $s['net_revenue'] * 100);
    $netWhole    = intdiv($netCents, 100);
    $netFraction = str_pad((string) ($netCents % 100), 2, '0', STR_PAD_LEFT);

    $rangeLabel = match ($dateRange) {
        'today'      => 'today',
        'yesterday'  => 'yesterday',
        'last-7'     => 'last 7 days',
        'last-30'    => 'last 30 days',
        'this-month' => 'this month',
        'last-month' => 'last month',
        'custom'     => 'custom range',
        default      => 'this month',
    };
@endphp

<div class="analytics-page relative min-h-[100dvh] bg-[#F8F7F3] dark:bg-[#0F172A]" wire:poll.60s>

    <div class="tenant-analytics-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div id="analytics-chart-data"
         data-revenue="{{ json_encode($this->revenueTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-refunds="{{ json_encode($this->refundsTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-cancellations="{{ json_encode($this->cancellationsTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-refund-status="{{ json_encode($refundDist, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-bookings="{{ json_encode($this->bookingTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-payment="{{ json_encode($paymentBreak, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-occupancy="{{ json_encode($this->occupancyTrend, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-property-performance="{{ json_encode($propPerf, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-status="{{ json_encode($statusDist, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-guests="{{ json_encode($guestComp, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         hidden
         aria-hidden="true"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8 sm:space-y-12">

        <section class="relative overflow-hidden rounded-3xl
                        bg-white/70 dark:bg-gray-800/40
                        backdrop-blur-xl
                        border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm">
            <div class="relative px-6 sm:px-10 py-8 sm:py-12">

                <div class="flex flex-wrap items-start justify-between gap-4 mb-10 sm:mb-14">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2.5 mb-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                                         bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300
                                         text-[10px] font-bold uppercase tracking-wider shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                                Live
                            </span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate">Updates every 60s</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white tracking-tight leading-tight">
                            Performance
                        </h1>
                        <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                            All figures below are for <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $rangeLabel }}</span>.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        <div class="flex items-center gap-0.5 p-0.5 rounded-full
                                    bg-gray-100/80 dark:bg-gray-900/60
                                    border border-gray-200/60 dark:border-white/[0.04]"
                             role="group"
                             aria-label="Date range">
                            @foreach([
                                'today'      => 'Today',
                                'yesterday'  => 'Y\'day',
                                'last-7'     => '7D',
                                'last-30'    => '30D',
                                'this-month' => 'Month',
                                'last-month' => 'Last',
                                'custom'     => 'Custom',
                            ] as $val => $label)
                                @php $isActive = $dateRange === $val; @endphp
                                <button type="button"
                                        wire:key="rng-{{ $val }}"
                                        wire:click="$set('dateRange', '{{ $val }}')"
                                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                        class="inline-flex items-center justify-center h-9 px-3 rounded-full
                                               text-[11px] font-semibold tracking-wide
                                               transition-all duration-200 active:scale-95
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                               {{ $isActive
                                                  ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                                                  : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <button type="button" onclick="window.print()"
                                class="no-print inline-flex items-center justify-center w-9 h-9 rounded-full
                                       text-gray-500 dark:text-gray-400
                                       bg-gray-100/80 dark:bg-gray-900/60
                                       border border-gray-200/60 dark:border-white/[0.04]
                                       hover:text-gray-900 dark:hover:text-gray-100
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                            </svg>
                            <span class="sr-only">Print report</span>
                        </button>
                    </div>
                </div>

                @if($dateRange === 'custom')
                    <div class="flex flex-wrap items-center gap-2 mb-10 pb-10 border-b border-gray-200/60 dark:border-white/[0.06] no-print">
                        <input type="date" wire:model.live="customStart" aria-label="Start date"
                               class="h-10 px-3 text-sm bg-white/70 dark:bg-gray-900/60 border border-gray-200/70 dark:border-white/[0.06] rounded-xl
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <span class="text-gray-400 dark:text-gray-500 text-xs">to</span>
                        <input type="date" wire:model.live="customEnd" aria-label="End date"
                               class="h-10 px-3 text-sm bg-white/70 dark:bg-gray-900/60 border border-gray-200/70 dark:border-white/[0.06] rounded-xl
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    </div>
                @endif

                <div class="mb-8">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-2">
                        Net revenue · gross minus refunds
                    </p>
                    <div class="flex items-baseline gap-2 flex-wrap">
                        <span class="text-3xl sm:text-4xl font-bold text-gray-400 dark:text-gray-500 tabular-nums">₱</span>
                        <span class="text-5xl sm:text-6xl lg:text-7xl font-bold text-gray-900 dark:text-white tabular-nums tracking-tight leading-none">
                            {{ number_format($netWhole) }}
                        </span>
                        <span class="text-2xl sm:text-3xl font-bold text-gray-400 dark:text-gray-500 tabular-nums">
                            .{{ $netFraction }}
                        </span>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
                        <span class="inline-flex items-baseline gap-1.5">
                            <span class="text-gray-500 dark:text-gray-400">Gross collected</span>
                            <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₱{{ number_format((int) $s['revenue']) }}</span>
                        </span>
                        <span class="inline-flex items-baseline gap-1.5">
                            <span class="text-gray-500 dark:text-gray-400">Refunded</span>
                            <span class="font-semibold text-rose-600 dark:text-rose-400 tabular-nums">−₱{{ number_format((int) $s['refunds_processed']) }}</span>
                        </span>
                        @if($s['refunds_pending'] > 0)
                            <span class="inline-flex items-baseline gap-1.5">
                                <span class="text-gray-500 dark:text-gray-400">Pending refunds</span>
                                <span class="font-semibold text-amber-600 dark:text-amber-400 tabular-nums">₱{{ number_format((int) $s['refunds_pending']) }}</span>
                            </span>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200/60 dark:border-white/[0.06] overflow-hidden mb-10">
                    <div class="px-5 sm:px-6 py-4 flex items-baseline justify-between gap-3
                                border-b border-gray-100/80 dark:border-white/[0.04]">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-tight">
                                Revenue vs refunds
                            </h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Green bars = gross payments · Red bars = refunds · Net = green − red, per day.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0 tabular-nums">₱ / day</span>
                    </div>

                    <div class="w-full h-64 sm:h-80 relative overflow-hidden px-5 sm:px-6 py-5" wire:ignore>
                        <canvas id="revenueChart" role="img" aria-label="Bar chart: daily gross revenue (green) and refunds (red)"></canvas>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-200/60 dark:border-white/[0.06]">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-y-6 gap-x-4 sm:divide-x sm:divide-gray-200/60 dark:sm:divide-white/[0.06]">

                        <div class="sm:pr-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Bookings</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['total_bookings']) }}</p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">count</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Guests</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['total_guests']) }}</p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">unique</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Avg value</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">₱{{ number_format((int) $s['avg_booking_value']) }}</p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">per booking</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Occupancy</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ $s['occupancy_rate'] }}<span class="text-sm font-medium text-gray-400 dark:text-gray-500">%</span></p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ $s['active_bookings'] }} / {{ $s['total_properties'] }}</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Repeat</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ $s['repeat_guest_rate'] }}<span class="text-sm font-medium text-gray-400 dark:text-gray-500">%</span></p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">of all guests</p>
                        </div>

                        <div class="sm:pl-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] {{ $s['outstanding_balance'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                                Outstanding
                            </p>
                            <p class="mt-1.5 text-xl font-bold tabular-nums leading-none {{ $s['outstanding_balance'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                                ₱{{ number_format((int) $s['outstanding_balance']) }}
                            </p>
                            <p class="text-[10px] mt-1 {{ $s['outstanding_balance'] > 0 ? 'text-amber-700/80 dark:text-amber-400/80' : 'text-gray-400 dark:text-gray-500' }}">
                                {{ $s['outstanding_balance'] > 0 ? 'to collect' : 'all settled' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                Cancellations &amp; refunds
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-1">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                            Total refunded
                        </p>
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0" aria-hidden="true"></span>
                    </div>
                    <p class="text-2xl font-bold text-rose-600 dark:text-rose-400 tabular-nums leading-none">
                        ₱{{ number_format((int) $s['refunds_processed']) }}
                    </p>
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                        money returned to guests · {{ $rangeLabel }}
                    </p>
                </div>

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-1">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                            Pending refunds
                        </p>
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0 {{ $s['refunds_pending'] > 0 ? 'animate-pulse motion-reduce:animate-none' : '' }}" aria-hidden="true"></span>
                    </div>
                    <p class="text-2xl font-bold tabular-nums leading-none {{ $s['refunds_pending'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                        ₱{{ number_format((int) $s['refunds_pending']) }}
                    </p>
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                        {{ $refundDist['pending'] }} {{ \Illuminate\Support\Str::plural('booking', $refundDist['pending']) }} awaiting gateway · all-time
                    </p>
                </div>

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-1">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                            Cancellations
                        </p>
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500 shrink-0" aria-hidden="true"></span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                        {{ number_format($s['cancellations']) }}
                    </p>
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                        {{ $s['cancellations_tourist'] }} tourist · {{ $s['cancellations_admin'] }} business · {{ $rangeLabel }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Cancellation trend</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Bookings cancelled per day, by either party.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">count / day</span>
                    </div>

                    @if($cancelHasData)
                        <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                            <canvas id="cancellationChart" role="img" aria-label="Line chart: cancellations per day"></canvas>
                        </div>
                    @else
                        <div class="h-56 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No cancellations in period</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Trend appears once a booking is cancelled within the selected range.</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Refund status</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Where cancellations in this period stand on refund processing.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">count</span>
                    </div>

                    @if($refundHasData)
                        <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                            <canvas id="refundStatusChart" role="img" aria-label="Doughnut chart: refund status distribution"></canvas>
                        </div>
                    @else
                        <div class="h-56 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No refund activity</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Breakdown appears once a booking in range has been cancelled.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                Trends
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Booking activity</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                New bookings created per day, regardless of status.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">count / day</span>
                    </div>
                    <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                        <canvas id="bookingChart" role="img" aria-label="Line chart: bookings per day"></canvas>
                    </div>
                </div>

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Occupancy history</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Share of your properties actively booked on each day.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">% / day</span>
                    </div>
                    <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                        <canvas id="occupancyChart" role="img" aria-label="Line chart: occupancy percentage"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                Performance
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <div class="lg:col-span-2 rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Top properties</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Top 5 properties by gross revenue from bookings created in this period.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">₱</span>
                    </div>

                    @if($propHasData)
                        <div class="w-full h-64 relative overflow-hidden" wire:ignore>
                            <canvas id="propertyPerformanceChart" role="img" aria-label="Horizontal bar chart: top performing properties"></canvas>
                        </div>
                    @else
                        <div class="h-64 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No property bookings yet</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Rankings populate once properties receive bookings.</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Status</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Current state of bookings created in this period.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">count</span>
                    </div>

                    @if($statusHasData)
                        <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                            <canvas id="statusChart" role="img" aria-label="Doughnut chart: booking status distribution"></canvas>
                        </div>
                    @else
                        <div class="h-56 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No bookings yet</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Status breakdown appears once bookings are created.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                Insights
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Guest mix</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Guests with 1 booking vs 2+, all-time.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">count</span>
                    </div>

                    @if($guestHasData)
                        <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                            <canvas id="guestChart" role="img" aria-label="Doughnut chart: repeat versus new guests"></canvas>
                        </div>
                    @else
                        <div class="h-56 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No guests yet</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Appears once bookings are recorded.</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Payments</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Revenue split by payment method used.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">₱</span>
                    </div>

                    @if($paymentHasData)
                        <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                            <canvas id="paymentChart" role="img" aria-label="Doughnut chart: revenue by payment method"></canvas>
                        </div>
                    @else
                        <div class="h-56 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No payments yet</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Method breakdown appears once collected.</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Top services</h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Add-on services by revenue contribution.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 shrink-0">₱</span>
                    </div>

                    @if(!empty($breakdowns))
                        <div class="space-y-4">
                            @foreach($breakdowns as $b)
                                <div wire:key="svc-{{ md5($b['name']) }}">
                                    <div class="flex justify-between items-baseline gap-2 text-sm mb-1.5">
                                        <span class="font-medium text-gray-700 dark:text-gray-300 truncate">{{ $b['name'] }}</span>
                                        <span class="text-gray-900 dark:text-white font-semibold tabular-nums shrink-0 text-xs">₱{{ number_format((int) $b['total']) }}</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-700/60 rounded-full overflow-hidden">
                                        <div class="h-full bg-primary-500 rounded-full transition-all duration-500" style="width: {{ $b['share'] }}%"></div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 text-right tabular-nums">{{ $b['share'] }}%</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="h-40 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No service data</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Appears once services are booked.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                Today
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="rounded-3xl overflow-hidden
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="px-5 sm:px-6 py-4 flex items-baseline justify-between gap-3
                                border-b border-gray-100/80 dark:border-white/[0.04]">
                        <div class="flex items-baseline gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-tight">Arrivals</h3>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 tabular-nums shrink-0">
                            {{ count($this->upcomingActivity['arrivals']) }}
                        </span>
                    </div>
                    <div class="divide-y divide-gray-100/80 dark:divide-white/[0.04] max-h-72 overflow-y-auto">
                        @forelse($this->upcomingActivity['arrivals'] as $b)
                            <a href="{{ route('tenant.bookings.show', $b->id) }}" wire:navigate
                               wire:key="arrival-{{ $b->id }}"
                               class="flex items-center gap-3 px-5 sm:px-6 py-3 min-h-[56px]
                                      hover:bg-white/80 dark:hover:bg-gray-800/50
                                      transition-colors [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:bg-white/80 dark:focus-visible:bg-gray-800/50">
                                <div class="w-8 h-8 rounded-full bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($b->user->name ?? 'G', 0, 1)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $b->user->name ?? 'Guest' }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono truncate mt-0.5">{{ $b->booking_reference }}</p>
                                </div>
                            </a>
                        @empty
                            <div class="px-5 sm:px-6 py-10 text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400">No arrivals scheduled today.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-3xl overflow-hidden
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="px-5 sm:px-6 py-4 flex items-baseline justify-between gap-3
                                border-b border-gray-100/80 dark:border-white/[0.04]">
                        <div class="flex items-baseline gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500" aria-hidden="true"></span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-tight">Departures</h3>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 tabular-nums shrink-0">
                            {{ count($this->upcomingActivity['departures']) }}
                        </span>
                    </div>
                    <div class="divide-y divide-gray-100/80 dark:divide-white/[0.04] max-h-72 overflow-y-auto">
                        @forelse($this->upcomingActivity['departures'] as $b)
                            <a href="{{ route('tenant.bookings.show', $b->id) }}" wire:navigate
                               wire:key="departure-{{ $b->id }}"
                               class="flex items-center gap-3 px-5 sm:px-6 py-3 min-h-[56px]
                                      hover:bg-white/80 dark:hover:bg-gray-800/50
                                      transition-colors [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:bg-white/80 dark:focus-visible:bg-gray-800/50">
                                <div class="w-8 h-8 rounded-full bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-400 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($b->user->name ?? 'G', 0, 1)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $b->user->name ?? 'Guest' }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono truncate mt-0.5">{{ $b->booking_reference }}</p>
                                </div>
                            </a>
                        @empty
                            <div class="px-5 sm:px-6 py-10 text-center">
                                <p class="text-xs text-gray-500 dark:text-gray-400">No departures scheduled today.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

@push('scripts')
    @once
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
                        cancellation: null,
                        refundStatus: null,
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

                    const REFUND_STATUS_ORDER = ['none', 'pending', 'processed', 'rejected'];

                    const REFUND_STATUS_COLORS = {
                        none:      '#94a3b8',
                        pending:   '#f59e0b',
                        processed: '#10b981',
                        rejected:  '#ef4444',
                    };

                    const REFUND_STATUS_LABELS = {
                        none:      'No refund due',
                        pending:   'Pending',
                        processed: 'Processed',
                        rejected:  'Rejected',
                    };

                    function isChartAlive(chart) {
                        return !!(chart && chart.canvas && chart.canvas.isConnected);
                    }

                    function getChartData() {
                        const el = document.getElementById('analytics-chart-data');
                        if (!el) return null;

                        try {
                            return {
                                revenue:       JSON.parse(el.dataset.revenue       || '{}'),
                                refunds:       JSON.parse(el.dataset.refunds       || '{}'),
                                cancellations: JSON.parse(el.dataset.cancellations || '{}'),
                                refundStatus:  JSON.parse(el.dataset.refundStatus  || '{}'),
                                bookings:      JSON.parse(el.dataset.bookings      || '{}'),
                                payment:       JSON.parse(el.dataset.payment       || '[]'),
                                occupancy:     JSON.parse(el.dataset.occupancy     || '{}'),
                                propPerf:      JSON.parse(el.dataset.propertyPerformance || '[]'),
                                status:        JSON.parse(el.dataset.status        || '{}'),
                                guests:        JSON.parse(el.dataset.guests        || '{}'),
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
                            barRefund:     '#ef4444',
                            lineBooking:   '#3b82f6',
                            lineOccupancy: '#f59e0b',
                            lineCancelled: '#ef4444',
                            fillOpacity:   '0.15',
                            doughnutBorder: '#1f2937',
                        } : {
                            textColor:     '#6b7280',
                            gridColor:     'rgba(0,0,0,0.05)',
                            barColor:      '#059669',
                            barRefund:     '#dc2626',
                            lineBooking:   '#2563eb',
                            lineOccupancy: '#d97706',
                            lineCancelled: '#dc2626',
                            fillOpacity:   '0.1',
                            doughnutBorder: '#ffffff',
                        };
                    }

                    function destroyAll() {
                        ['revenue', 'booking', 'payment', 'occupancy', 'propPerf', 'status', 'guest', 'cancellation', 'refundStatus'].forEach(key => {
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
                            displayColors:   true,
                            cornerRadius:    10,
                        };
                    }

                    function formatShortDate(iso) {
                        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
                        if (!m) return iso;
                        const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
                        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
                    }

                    function signedBarChart(canvasId, labels, datasets) {
                        const canvas = document.getElementById(canvasId);
                        if (!canvas) return null;
                        const theme = getTheme();

                        return new Chart(canvas.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: labels.map(formatShortDate),
                                datasets,
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    legend: {
                                        display: datasets.length > 1,
                                        position: 'bottom',
                                        labels: {
                                            color: theme.textColor,
                                            padding: 12,
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
                                            title: (items) => items[0]?.label ?? '',
                                            label: (ctx) => {
                                                const v = ctx.parsed.y || 0;
                                                const sign = v < 0 ? '−' : '';
                                                return ' ' + ctx.dataset.label + ': ' + sign + '₱' + Math.abs(v).toLocaleString();
                                            },
                                        },
                                    },
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: theme.textColor,
                                            maxTicksLimit: 6,
                                            callback: (v) => '₱' + Math.abs(v).toLocaleString(),
                                        },
                                        grid:  { color: theme.gridColor, drawBorder: false },
                                        border: { display: false },
                                    },
                                    x: {
                                        ticks: {
                                            color: theme.textColor,
                                            autoSkip: true,
                                            maxRotation: 0,
                                            autoSkipPadding: 8,
                                        },
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
                                layout: { padding: { right: 40 } },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        ...tooltipBase(),
                                        callbacks: {
                                            label: (ctx) => ' ₱' + Number(ctx.parsed.x || 0).toLocaleString(),
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
                                        ticks: {
                                            color: theme.textColor,
                                            precision: 0,
                                            callback: (v) => '₱' + Number(v).toLocaleString(),
                                        },
                                    },
                                },
                            },
                        });
                    }

                    function lineChart(canvasId, labels, values, label, color, rgb, unit, precision) {
                        const canvas = document.getElementById(canvasId);
                        if (!canvas) return null;
                        const theme = getTheme();
                        const ctx = canvas.getContext('2d');
                        const suffix = unit || '';
                        const prec = precision === undefined ? 0 : precision;

                        return new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels.map(formatShortDate),
                                datasets: [{
                                    label,
                                    data: values,
                                    borderColor: color,
                                    backgroundColor: gradient(ctx, rgb, theme.fillOpacity),
                                    tension: 0.35,
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
                                    tooltip: {
                                        ...tooltipBase(),
                                        callbacks: {
                                            label: (ctx) => ' ' + ctx.dataset.label + ': ' + (ctx.parsed.y ?? 0) + suffix,
                                        },
                                    },
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: theme.textColor,
                                            maxTicksLimit: 6,
                                            precision: prec,
                                            callback: (v) => v + suffix,
                                        },
                                        grid:  { color: theme.gridColor, drawBorder: false, borderDash: [5, 5] },
                                        border: { display: false },
                                    },
                                    x: {
                                        ticks: {
                                            color: theme.textColor,
                                            autoSkip: true,
                                            maxRotation: 0,
                                            autoSkipPadding: 8,
                                        },
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
                            const revLabels = Object.keys(data.revenue);
                            const revValues = Object.values(data.revenue);
                            const refundValues = revLabels.map(l => -(data.refunds[l] || 0));
                            const hasRefunds   = refundValues.some(v => v < 0);

                            const datasets = [
                                {
                                    label: 'Revenue',
                                    data: revValues,
                                    backgroundColor: theme.barColor,
                                    borderRadius: 4,
                                    borderSkipped: false,
                                    barPercentage: 0.7,
                                    categoryPercentage: 0.9,
                                    maxBarThickness: 32,
                                },
                            ];

                            if (hasRefunds) {
                                datasets.push({
                                    label: 'Refunds',
                                    data: refundValues,
                                    backgroundColor: theme.barRefund,
                                    borderRadius: 4,
                                    borderSkipped: false,
                                    barPercentage: 0.7,
                                    categoryPercentage: 0.9,
                                    maxBarThickness: 32,
                                });
                            }

                            if (!isChartAlive(state.revenue)) {
                                destroyOne('revenue');
                                state.revenue = signedBarChart('revenueChart', revLabels, datasets);
                            } else {
                                state.revenue.data.labels = revLabels.map(formatShortDate);
                                state.revenue.data.datasets = datasets;
                                state.revenue.update('none');
                            }
                        }

                        if (document.getElementById('cancellationChart')) {
                            const labels = Object.keys(data.cancellations);
                            const values = Object.values(data.cancellations);

                            if (!isChartAlive(state.cancellation)) {
                                destroyOne('cancellation');
                                state.cancellation = lineChart(
                                    'cancellationChart',
                                    labels,
                                    values,
                                    'Cancellations',
                                    theme.lineCancelled,
                                    isDark() ? '239, 68, 68' : '220, 38, 38',
                                    '',
                                    0,
                                );
                            } else {
                                state.cancellation.data.labels = labels.map(formatShortDate);
                                state.cancellation.data.datasets[0].data = values;
                                state.cancellation.update('none');
                            }
                        }

                        if (document.getElementById('refundStatusChart')) {
                            const present = REFUND_STATUS_ORDER.filter(k => (data.refundStatus[k] || 0) > 0);
                            const labels  = present.map(k => REFUND_STATUS_LABELS[k]);
                            const values  = present.map(k => data.refundStatus[k]);
                            const colors  = present.map(k => REFUND_STATUS_COLORS[k]);

                            if (!isChartAlive(state.refundStatus)) {
                                destroyOne('refundStatus');
                                state.refundStatus = doughnutChart('refundStatusChart', labels, values, colors, 'bottom');
                            } else {
                                state.refundStatus.data.labels = labels;
                                state.refundStatus.data.datasets[0].data = values;
                                state.refundStatus.data.datasets[0].backgroundColor = colors;
                                state.refundStatus.update('none');
                            }
                        }

                        if (document.getElementById('paymentChart')) {
                            const labels = data.payment.map(p => p.method.charAt(0).toUpperCase() + p.method.slice(1));
                            const values = data.payment.map(p => p.total);
                            if (!isChartAlive(state.payment)) {
                                destroyOne('payment');
                                state.payment = doughnutChart('paymentChart', labels, values, null, 'bottom');
                            } else {
                                state.payment.data.labels = labels;
                                state.payment.data.datasets[0].data = values;
                                state.payment.update('none');
                            }
                        }

                        if (document.getElementById('bookingChart')) {
                            const labels = Object.keys(data.bookings);
                            const values = Object.values(data.bookings);
                            if (!isChartAlive(state.booking)) {
                                destroyOne('booking');
                                state.booking = lineChart('bookingChart', labels, values, 'Bookings', theme.lineBooking, isDark() ? '59, 130, 246' : '37, 99, 235', '', 0);
                            } else {
                                state.booking.data.labels = labels.map(formatShortDate);
                                state.booking.data.datasets[0].data = values;
                                state.booking.update('none');
                            }
                        }

                        if (document.getElementById('occupancyChart')) {
                            const labels = Object.keys(data.occupancy);
                            const values = Object.values(data.occupancy);
                            if (!isChartAlive(state.occupancy)) {
                                destroyOne('occupancy');
                                state.occupancy = lineChart('occupancyChart', labels, values, 'Occupancy', theme.lineOccupancy, isDark() ? '245, 158, 11' : '217, 119, 6', '%', 0);
                            } else {
                                state.occupancy.data.labels = labels.map(formatShortDate);
                                state.occupancy.data.datasets[0].data = values;
                                state.occupancy.update('none');
                            }
                        }

                        if (document.getElementById('propertyPerformanceChart')) {
                            const labels = data.propPerf.map(p => p.name);
                            const values = data.propPerf.map(p => p.revenue);
                            const ramp   = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'];
                            if (!isChartAlive(state.propPerf)) {
                                destroyOne('propPerf');
                                state.propPerf = horizontalBarChart('propertyPerformanceChart', labels, values, ramp.slice(0, values.length));
                            } else {
                                state.propPerf.data.labels = labels;
                                state.propPerf.data.datasets[0].data = values;
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
                            const values = [data.guests.new || 0, data.guests.repeat || 0];
                            if (!isChartAlive(state.guest)) {
                                destroyOne('guest');
                                state.guest = doughnutChart('guestChart', ['New Guests', 'Repeat Guests'], values, ['#3b82f6', '#8b5cf6'], 'bottom');
                            } else {
                                state.guest.data.datasets[0].data = values;
                                state.guest.update('none');
                            }
                        }
                    };

                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => window.renderTenantAnalytics(false));
                    });

                    if (!state.hooked) {
                        state.hooked = true;

                        const registerMorphHook = () => {
                            if (!window.Livewire || typeof window.Livewire.hook !== 'function') return false;
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

                        if ('ResizeObserver' in window) {
                            ['revenueChart', 'cancellationChart', 'refundStatusChart', 'paymentChart', 'bookingChart', 'occupancyChart', 'propertyPerformanceChart', 'statusChart', 'guestChart'].forEach(id => {
                                const canvas = document.getElementById(id);
                                const wrapper = canvas && canvas.parentElement;
                                if (!wrapper) return;
                                new ResizeObserver(() => {
                                    const keys = ['revenue', 'cancellation', 'refundStatus', 'payment', 'booking', 'occupancy', 'propPerf', 'status', 'guest'];
                                    keys.forEach(k => {
                                        if (state[k]) { try { state[k].resize(); } catch (e) {} }
                                    });
                                }).observe(wrapper);
                            });
                        }

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
    @endonce
@endpush