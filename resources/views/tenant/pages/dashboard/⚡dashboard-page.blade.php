{{-- resources/views/tenant/pages/dashboard/⚡dashboard-page.blade.php --}}
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

    public function mount(): void
    {
        $this->authorizeDashboard();

        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd   = now()->endOfMonth()->format('Y-m-d');
    }

    public function hydrate(): void
    {
        $this->authorizeDashboard();
    }

    protected function authorizeDashboard(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403, 'No business is linked to your account.');
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

    /** @return array<string, mixed> */
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

        $agg = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as period_bookings,
                COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? AND status != ? THEN 1 ELSE 0 END), 0) as period_non_cancelled,
                COALESCE(COUNT(DISTINCT CASE WHEN created_at BETWEEN ? AND ? THEN user_id END), 0) as period_guests,
                COALESCE(SUM(CASE WHEN status NOT IN (?, ?) AND check_in <= ? AND check_out >= ? THEN 1 ELSE 0 END), 0) as active_count,
                COALESCE(SUM(CASE WHEN check_in = CURDATE() AND status != ? THEN 1 ELSE 0 END), 0) as arrivals,
                COALESCE(SUM(CASE WHEN check_out = CURDATE() AND status != ? THEN 1 ELSE 0 END), 0) as departures
            ", [
                $start, $end,
                $start, $end, Booking::STATUS_CANCELLED,
                $start, $end,
                Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED, $end, $start,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_CANCELLED,
            ])
            ->first();

        $totalBookings        = (int) ($agg?->period_bookings      ?? 0);
        $nonCancelledBookings = (int) ($agg?->period_non_cancelled ?? 0);
        $totalGuests          = (int) ($agg?->period_guests        ?? 0);
        $activeBookings       = (int) ($agg?->active_count         ?? 0);
        $arrivalsToday        = (int) ($agg?->arrivals             ?? 0);
        $departuresToday      = (int) ($agg?->departures           ?? 0);

        $totalProperties = (int) Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)->count();

        // Distinct properties with at least one active booking in the period.
        // Replaces a properties.status='occupied' read that drifted out of sync
        // with actual bookings and produced a sub-label that disagreed with the
        // headline occupancy rate.
        $occupiedProperties = (int) DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->where('booking_items.tenant_id', $tenantId)
            ->where('bookings.tenant_id', $tenantId)
            ->whereNotIn('bookings.status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
            ->where('bookings.check_in', '<=', $end)
            ->where('bookings.check_out', '>=', $start)
            ->distinct()
            ->count('booking_items.property_id');

        $outstandingBalance = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
            ->withSum(['payments as paid_amount' => fn ($q) => $q->where('payment_status', 'paid')], 'amount')
            ->get(['id', 'total_amount'])
            ->sum(fn ($b) => max(0, (float) $b->total_amount - (float) ($b->paid_amount ?? 0)));

        // Refunds actually returned to guests within the period.
        $refundsProcessed = (float) Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('refund_status', Booking::REFUND_STATUS_PROCESSED)
            ->whereNotNull('refund_processed_at')
            ->whereBetween('refund_processed_at', [$start, $end])
            ->sum('refund_amount');

        // Refunds queued but not yet settled. All-time by design — what matters
        // operationally is what's still owed right now, not what was queued in range.
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

        $cancellations        = (int) ($cancellationAgg->total      ?? 0);
        $cancellationsTourist = (int) ($cancellationAgg->by_tourist ?? 0);
        $cancellationsAdmin   = (int) ($cancellationAgg->by_admin   ?? 0);

        $netRevenue = $revenue - $refundsProcessed;

        return [
            'revenue'               => $revenue,
            'net_revenue'           => $netRevenue,
            'refunds_processed'     => $refundsProcessed,
            'refunds_pending'       => $refundsPending,
            'cancellations'         => $cancellations,
            'cancellations_tourist' => $cancellationsTourist,
            'cancellations_admin'   => $cancellationsAdmin,
            'total_bookings'        => $totalBookings,
            'total_guests'          => $totalGuests,
            'occupancy_rate'        => $totalProperties > 0 ? round(($occupiedProperties / $totalProperties) * 100, 1) : 0.0,
            'avg_booking_value'     => $nonCancelledBookings > 0 ? round($netRevenue / $nonCancelledBookings, 2) : 0.0,
            'outstanding_balance'   => (float) $outstandingBalance,
            'repeat_guest_rate'     => $this->repeatGuestRate,
            'arrivals_today'        => $arrivalsToday,
            'departures_today'      => $departuresToday,
            'occupied_properties'   => $occupiedProperties,
            'total_properties'      => $totalProperties,
        ];
    }

    #[Computed]
    public function repeatGuestRate(): float
    {
        $userCounts = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->select('user_id', DB::raw('COUNT(*) as bookings'))
            ->groupBy('user_id')
            ->pluck('bookings', 'user_id');

        $total  = $userCounts->count();
        $repeat = $userCounts->filter(fn ($c) => $c > 1)->count();

        return $total > 0 ? round(($repeat / $total) * 100, 1) : 0.0;
    }

    /**
     * Unified activity stream — bookings, payments, today's arrivals,
     * cancellations, and processed refunds merged into one feed.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function activityFeed()
    {
        $tenantId = Auth::user()->tenant_id;
        $items    = collect();

        Booking::withoutGlobalScope(TenantScope::class)
            ->with(['user:id,name'])
            ->select('id', 'user_id', 'booking_reference', 'created_at', 'total_amount', 'status')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->take(6)
            ->get()
            ->each(function ($b) use ($items) {
                $items->push([
                    'kind'   => 'booking',
                    'at'     => $b->created_at,
                    'title'  => $b->user->name ?? 'Walk-in Guest',
                    'meta'   => $b->booking_reference,
                    'amount' => (float) $b->total_amount,
                    'status' => $b->status,
                    'href'   => route('tenant.bookings.show', $b->id),
                ]);
            });

        Payment::query()
            ->with(['booking:id,booking_reference'])
            ->select('id', 'booking_id', 'amount', 'paid_at', 'reference_number')
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->take(5)
            ->get()
            ->each(function ($p) use ($items) {
                $items->push([
                    'kind'   => 'payment',
                    'at'     => $p->paid_at,
                    'title'  => 'Payment received',
                    'meta'   => $p->booking?->booking_reference ?? $p->reference_number,
                    'amount' => (float) $p->amount,
                    'status' => 'paid',
                    'href'   => $p->booking_id ? route('tenant.bookings.show', $p->booking_id) : null,
                ]);
            });

        Booking::withoutGlobalScope(TenantScope::class)
            ->with(['user:id,name'])
            ->select('id', 'user_id', 'booking_reference', 'check_in')
            ->where('tenant_id', $tenantId)
            ->whereDate('check_in', today())
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->take(4)
            ->get()
            ->each(function ($b) use ($items) {
                $items->push([
                    'kind'   => 'arrival',
                    'at'     => $b->check_in,
                    'title'  => $b->user->name ?? 'Guest',
                    'meta'   => $b->booking_reference,
                    'amount' => null,
                    'status' => 'arrival',
                    'href'   => route('tenant.bookings.show', $b->id),
                ]);
            });

        // Recently cancelled bookings.
        Booking::withoutGlobalScope(TenantScope::class)
            ->with(['user:id,name'])
            ->select('id', 'user_id', 'booking_reference', 'cancelled_at', 'cancelled_by', 'status')
            ->where('tenant_id', $tenantId)
            ->where('status', Booking::STATUS_CANCELLED)
            ->whereNotNull('cancelled_at')
            ->orderByDesc('cancelled_at')
            ->take(5)
            ->get()
            ->each(function ($b) use ($items) {
                $items->push([
                    'kind'   => 'cancellation',
                    'at'     => $b->cancelled_at,
                    'title'  => $b->user->name ?? 'Guest',
                    'meta'   => $b->booking_reference,
                    'amount' => null,
                    'status' => $b->cancelled_by === Booking::CANCELLED_BY_ADMIN ? 'cancelled_by_admin' : 'cancelled_by_tourist',
                    'href'   => route('tenant.bookings.show', $b->id),
                ]);
            });

        // Recently processed refunds.
        Booking::withoutGlobalScope(TenantScope::class)
            ->with(['user:id,name'])
            ->select('id', 'user_id', 'booking_reference', 'refund_amount', 'refund_percentage', 'refund_processed_at')
            ->where('tenant_id', $tenantId)
            ->where('refund_status', Booking::REFUND_STATUS_PROCESSED)
            ->whereNotNull('refund_processed_at')
            ->orderByDesc('refund_processed_at')
            ->take(5)
            ->get()
            ->each(function ($b) use ($items) {
                $items->push([
                    'kind'   => 'refund',
                    'at'     => $b->refund_processed_at,
                    'title'  => 'Refund issued',
                    'meta'   => $b->booking_reference,
                    'amount' => -(float) $b->refund_amount,
                    'status' => 'refund_processed',
                    'href'   => route('tenant.bookings.show', $b->id),
                ]);
            });

        return $items->sortByDesc('at')->take(8)->values();
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-dashboard-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-dashboard-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

@php
    $s            = $this->stats;
    $feed         = $this->activityFeed;
    $businessName = Auth::user()?->tenant?->name ?? 'Business Dashboard';

    $netCents       = (int) round((float) $s['net_revenue'] * 100);
    $netWhole       = intdiv($netCents, 100);
    $netFraction    = str_pad((string) ($netCents % 100), 2, '0', STR_PAD_LEFT);

    $hasActivity = $s['refunds_processed'] > 0 || $s['refunds_pending'] > 0 || $s['cancellations'] > 0;
@endphp

<div class="relative min-h-[100dvh] bg-[#F8F7F3] dark:bg-[#0F172A]" wire:poll.60s>

    <div class="tenant-dashboard-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8 sm:space-y-12">

        <section class="relative overflow-hidden rounded-3xl
                        bg-white/70 dark:bg-gray-800/40
                        backdrop-blur-xl
                        border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm">
            <div class="relative px-6 sm:px-10 py-8 sm:py-12">

                <div class="flex flex-wrap items-start justify-between gap-4 mb-8 sm:mb-12">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                                     bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300
                                     text-[10px] font-bold uppercase tracking-wider shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                            Live
                        </span>
                        <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                            {{ $businessName }}
                        </span>
                    </div>

                    <div class="flex items-center gap-0.5 p-0.5 rounded-full
                                bg-gray-100/80 dark:bg-gray-900/60
                                border border-gray-200/60 dark:border-white/[0.04]"
                         role="group"
                         aria-label="Date range">
                        @foreach([
                            'today'      => 'Today',
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
                </div>

                @if($dateRange === 'custom')
                    <div class="flex flex-wrap items-center gap-2 mb-8 pb-8 border-b border-gray-200/60 dark:border-white/[0.06]">
                        <input type="date" wire:model.live="customStart" aria-label="Start date"
                               class="h-10 px-3 text-sm bg-white/70 dark:bg-gray-900/60 border border-gray-200/70 dark:border-white/[0.06] rounded-xl
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <span class="text-gray-400 dark:text-gray-500 text-xs">to</span>
                        <input type="date" wire:model.live="customEnd" aria-label="End date"
                               class="h-10 px-3 text-sm bg-white/70 dark:bg-gray-900/60 border border-gray-200/70 dark:border-white/[0.06] rounded-xl
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    </div>
                @endif

                <div class="max-w-3xl">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-2">
                        Net revenue this period
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
                    <p class="mt-3 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                        {{ $s['total_bookings'] }} {{ \Illuminate\Support\Str::plural('booking', (int) $s['total_bookings']) }}
                        · {{ $s['total_guests'] }} {{ \Illuminate\Support\Str::plural('guest', (int) $s['total_guests']) }}
                        · avg ₱{{ number_format($s['avg_booking_value'], 0) }}
                    </p>

                    @if($hasActivity)
                        <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
                            <span class="inline-flex items-baseline gap-1.5">
                                <span class="text-gray-500 dark:text-gray-400">Gross</span>
                                <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₱{{ number_format((int) $s['revenue']) }}</span>
                            </span>
                            @if($s['refunds_processed'] > 0)
                                <span class="inline-flex items-baseline gap-1.5">
                                    <span class="text-gray-500 dark:text-gray-400">Refunded</span>
                                    <span class="font-semibold text-rose-600 dark:text-rose-400 tabular-nums">−₱{{ number_format((int) $s['refunds_processed']) }}</span>
                                </span>
                            @endif
                            @if($s['refunds_pending'] > 0)
                                <span class="inline-flex items-baseline gap-1.5">
                                    <span class="text-gray-500 dark:text-gray-400">Pending</span>
                                    <span class="font-semibold text-amber-600 dark:text-amber-400 tabular-nums">₱{{ number_format((int) $s['refunds_pending']) }}</span>
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="mt-10 sm:mt-14 pt-6 sm:pt-8 border-t border-gray-200/60 dark:border-white/[0.06]">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-y-6 gap-x-4 sm:divide-x sm:divide-gray-200/60 dark:sm:divide-white/[0.06]">

                        <div class="sm:pr-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                                Occupancy
                            </p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                                {{ $s['occupancy_rate'] }}<span class="text-base font-medium text-gray-400 dark:text-gray-500">%</span>
                            </p>
                            <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                {{ $s['occupied_properties'] }} of {{ $s['total_properties'] }} {{ \Illuminate\Support\Str::plural('property', (int) $s['total_properties']) }}
                            </p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                                Arrivals
                            </p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                                {{ $s['arrivals_today'] }}
                            </p>
                            <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                today
                            </p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                                Departures
                            </p>
                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                                {{ $s['departures_today'] }}
                            </p>
                            <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                today
                            </p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] {{ $s['cancellations'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400' }}">
                                Cancellations
                            </p>
                            <p class="mt-2 text-2xl font-bold tabular-nums leading-none {{ $s['cancellations'] > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $s['cancellations'] }}
                            </p>
                            <p class="mt-1.5 text-[11px] {{ $s['cancellations'] > 0 ? 'text-rose-700/80 dark:text-rose-400/80' : 'text-gray-500 dark:text-gray-400' }}">
                                @if($s['cancellations'] > 0)
                                    {{ $s['cancellations_tourist'] }} tourist · {{ $s['cancellations_admin'] }} business
                                @else
                                    this period
                                @endif
                            </p>
                        </div>

                        <div class="sm:pl-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] {{ $s['outstanding_balance'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                                Outstanding
                            </p>
                            <p class="mt-2 text-2xl font-bold tabular-nums leading-none {{ $s['outstanding_balance'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                                ₱{{ number_format((int) $s['outstanding_balance']) }}
                            </p>
                            <p class="mt-1.5 text-[11px] {{ $s['outstanding_balance'] > 0 ? 'text-amber-700/80 dark:text-amber-400/80' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $s['outstanding_balance'] > 0 ? 'To collect' : 'All settled' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="flex items-end justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-white tracking-tight">
                        Recent activity
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Bookings, payments, arrivals, cancellations, and refunds — in one stream.
                    </p>
                </div>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate
                   class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400
                          hover:text-primary-800 dark:hover:text-primary-300 transition-colors shrink-0
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    View all
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <div wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="dateRange,customStart,customEnd"
                 class="rounded-3xl overflow-hidden
                        bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                        border border-gray-200/60 dark:border-white/[0.06]
                        divide-y divide-gray-100/80 dark:divide-white/[0.04]
                        transition-opacity duration-200">
                @forelse($feed as $item)
                    @php
                        $styles = match ($item['kind']) {
                            'payment' => [
                                'dot'    => 'bg-emerald-500',
                                'bg'     => 'bg-emerald-50 dark:bg-emerald-500/10',
                                'fg'     => 'text-emerald-600 dark:text-emerald-400',
                                'label'  => 'Payment',
                                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                            ],
                            'arrival' => [
                                'dot'    => 'bg-amber-500',
                                'bg'     => 'bg-amber-50 dark:bg-amber-500/10',
                                'fg'     => 'text-amber-600 dark:text-amber-400',
                                'label'  => 'Arriving today',
                                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>',
                            ],
                            'cancellation' => [
                                'dot'    => 'bg-rose-500',
                                'bg'     => 'bg-rose-50 dark:bg-rose-500/10',
                                'fg'     => 'text-rose-600 dark:text-rose-400',
                                'label'  => $item['status'] === 'cancelled_by_admin' ? 'Cancelled by business' : 'Cancelled by tourist',
                                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>',
                            ],
                            'refund' => [
                                'dot'    => 'bg-violet-500',
                                'bg'     => 'bg-violet-50 dark:bg-violet-500/10',
                                'fg'     => 'text-violet-600 dark:text-violet-400',
                                'label'  => 'Refund issued',
                                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6M3 10l6-6"/>',
                            ],
                            default   => [
                                'dot'    => 'bg-blue-500',
                                'bg'     => 'bg-blue-50 dark:bg-blue-500/10',
                                'fg'     => 'text-blue-600 dark:text-blue-400',
                                'label'  => 'New booking',
                                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                            ],
                        };
                    @endphp

                    <a @if($item['href']) href="{{ $item['href'] }}" wire:navigate @endif
                       wire:key="feed-{{ $loop->index }}-{{ $item['meta'] }}"
                       class="group flex items-center gap-4 px-5 sm:px-6 py-4 min-h-[64px]
                              @if($item['href']) hover:bg-white/80 dark:hover:bg-gray-800/50 transition-colors @endif
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:bg-white/80 dark:focus-visible:bg-gray-800/50">

                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 {{ $styles['bg'] }} {{ $styles['fg'] }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                {!! $styles['icon'] !!}
                            </svg>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-1.5 h-1.5 rounded-full {{ $styles['dot'] }} shrink-0" aria-hidden="true"></span>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    {{ $item['title'] }}
                                </p>
                            </div>
                            <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate tabular-nums">
                                <span class="font-medium">{{ $styles['label'] }}</span>
                                <span class="mx-1 text-gray-300 dark:text-gray-600">·</span>
                                <span class="font-mono">{{ $item['meta'] }}</span>
                                <span class="mx-1 text-gray-300 dark:text-gray-600">·</span>
                                {{ $item['at']->diffForHumans() }}
                            </p>
                        </div>

                        @if($item['amount'] !== null)
                            <p class="text-sm font-bold tabular-nums shrink-0 {{ $item['amount'] < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $item['amount'] < 0 ? '−₱' : '₱' }}{{ number_format(abs((int) $item['amount'])) }}
                            </p>
                        @else
                            <svg class="w-4 h-4 shrink-0 text-gray-300 dark:text-gray-600 group-hover:text-gray-500 dark:group-hover:text-gray-400 transition-colors"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        @endif
                    </a>
                @empty
                    <div class="px-5 sm:px-6 py-16 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl
                                    bg-gray-100 dark:bg-gray-800/60
                                    text-gray-400 dark:text-gray-500 mb-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Quiet so far</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs mx-auto">
                            New bookings, payments, cancellations, and refunds will appear here as they happen.
                        </p>
                    </div>
                @endforelse
            </div>
        </section>

        @php
            $actions = array_values(array_filter([
                $this->tenantCan('create bookings')   ? ['tenant.bookings.create',  'New booking',  'M12 4v16m8-8H4'] : null,
                $this->tenantCan('manage properties') ? ['tenant.properties.create','Add property', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'] : null,
                $this->tenantCan('manage services')   ? ['tenant.services.create',   'Add service',  'M12 6v6m0 0v6m0-6h6m-6 0H6'] : null,
                $this->tenantCan('view analytics')    ? ['tenant.analytics.index',   'Analytics',    'M3 3v18h18M7 16l4-4 4 4 5-5'] : null,
            ]));
        @endphp

        @if(!empty($actions))
            <section>
                <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                    Quick actions
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($actions as [$routeName, $label, $icon])
                        <a href="{{ route($routeName) }}" wire:navigate
                           wire:key="qa-{{ $routeName }}"
                           class="group inline-flex items-center gap-2 h-11 px-5 rounded-full
                                  bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                  border border-gray-200/60 dark:border-white/[0.06]
                                  text-sm font-semibold text-gray-700 dark:text-gray-200
                                  hover:bg-white dark:hover:bg-gray-700/60
                                  hover:border-primary-300 dark:hover:border-primary-500/40
                                  hover:text-primary-600 dark:hover:text-primary-400
                                  transition-all duration-200 active:scale-[0.98]
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                            </svg>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>