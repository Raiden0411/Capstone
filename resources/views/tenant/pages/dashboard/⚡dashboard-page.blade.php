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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Business Dashboard')]
class extends Component {

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

<div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto space-y-6" wire:poll.60s>

    {{-- ═══════════════ HEADER ═══════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                Dashboard
            </p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                {{ Auth::user()?->tenant?->name ?? 'Business Dashboard' }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Overview of your property performance and activity.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2">
            <a href="{{ route('tenant.bookings.create') }}" wire:navigate
               class="btn-primary active:scale-95 transition-transform
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                      inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Booking
            </a>
            <a href="{{ route('tenant.analytics.index') }}" wire:navigate
               class="btn-secondary active:scale-95 transition-transform
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                      inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l4-4 4 4 5-5"/>
                </svg>
                View Analytics
            </a>
        </div>
    </div>

    {{-- ═══════════════ DATE RANGE ═══════════════ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 relative">
        <div wire:loading.delay class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none">
            <svg class="animate-spin h-5 w-5 text-primary-600 dark:text-primary-400 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <div class="flex flex-wrap items-center gap-2">
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
                        class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $dateRange === $val
                                  ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20'
                                  : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    {{ $label }}
                </button>
            @endforeach

            @if($dateRange === 'custom')
                <div class="flex flex-col sm:flex-row gap-2 items-start sm:items-center w-full sm:w-auto">
                    <input type="date" wire:model.live="customStart" class="input !py-2 !w-full sm:!w-auto">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">to</span>
                    <input type="date" wire:model.live="customEnd" class="input !py-2 !w-full sm:!w-auto">
                </div>
            @endif
        </div>
    </div>

    {{-- ═══════════════ KPI CARDS ═══════════════ --}}
    @php $s = $this->stats; @endphp
    <div wire:loading.class="opacity-50 pointer-events-none" class="grid grid-cols-2 lg:grid-cols-4 gap-4 transition-opacity duration-200">

        {{-- Primary KPIs --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Revenue</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums truncate">
                    ₱{{ number_format($s['revenue'], 2) }}
                </p>
            </div>
            <div class="p-3 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-lg shrink-0 ml-3">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bookings</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums truncate">
                    {{ number_format($s['total_bookings']) }}
                </p>
            </div>
            <div class="p-3 bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 rounded-lg shrink-0 ml-3">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Occupancy</p>
                <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums truncate">
                    {{ $s['occupancy_rate'] }}%
                </p>
            </div>
            <div class="p-3 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-lg shrink-0 ml-3">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-amber-200 dark:border-amber-500/30 shadow-sm p-4 flex items-center justify-between">
            <div class="min-w-0">
                <p class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wider">Outstanding</p>
                <p class="text-xl sm:text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1 tabular-nums truncate">
                    ₱{{ number_format($s['outstanding_balance'], 2) }}
                </p>
            </div>
            <div class="p-3 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-lg shrink-0 ml-3">
                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        {{-- Secondary KPIs --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Booking</p>
            <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums truncate">
                ₱{{ number_format($s['avg_booking_value'], 2) }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Repeat Guests</p>
            <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums">
                {{ $s['repeat_guest_rate'] }}%
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Arrivals Today</p>
            <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums">
                {{ number_format($s['arrivals_today']) }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Departures Today</p>
            <p class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums">
                {{ number_format($s['departures_today']) }}
            </p>
        </div>
    </div>

    {{-- ═══════════════ RECENT BOOKINGS ═══════════════ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="font-bold text-gray-900 dark:text-white">Recent Bookings</h2>
            <a href="{{ route('tenant.bookings.index') }}" wire:navigate
               class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 dark:text-primary-400
                      hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded
                      active:scale-95 transition-transform">
                View all
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                        <th class="px-6 py-4 text-left">Ref</th>
                        <th class="px-6 py-4 text-left">Guest</th>
                        <th class="px-6 py-4 text-left hidden sm:table-cell">Check‑in</th>
                        <th class="px-6 py-4 text-left">Amount</th>
                        <th class="px-6 py-4 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->recentBookings as $b)
                        <tr wire:key="booking-{{ $b->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs">{{ $b->booking_reference }}</td>
                            <td class="px-6 py-4 font-medium">{{ $b->user->name ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 hidden sm:table-cell">{{ $b->check_in?->format('M d, Y') ?? '—' }}</td>
                            <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white tabular-nums">₱{{ number_format((float) $b->total_amount, 2) }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium border
                                    {{ $b->status === 'pending'    ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30' : '' }}
                                    {{ $b->status === 'confirmed'  ? 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border-primary-200 dark:border-primary-500/30' : '' }}
                                    {{ $b->status === 'completed'  ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border-green-200 dark:border-green-500/30' : '' }}
                                    {{ $b->status === 'cancelled'  ? 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border-red-200 dark:border-red-500/30' : '' }}">
                                    {{ ucfirst(str_replace('_', ' ', $b->status)) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No bookings yet.</p>
                                    <a href="{{ route('tenant.bookings.create') }}" wire:navigate
                                       class="mt-3 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                        Create your first booking →
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════════════ ARRIVALS + PAYMENTS ═══════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Upcoming arrivals --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
            <h2 class="font-bold text-gray-900 dark:text-white mb-4">Upcoming Arrivals</h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->upcomingArrivals as $b)
                    <div wire:key="arrival-{{ $b->id }}" class="py-3 flex justify-between items-center group">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold shrink-0">
                                {{ strtoupper(substr($b->user->name ?? 'G', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    {{ $b->user->name ?? 'Guest' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ $b->check_in?->format('M d, Y') ?? '—' }} · <span class="font-mono">{{ $b->booking_reference }}</span>
                                </p>
                            </div>
                        </div>
                        <span class="text-xs bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400 px-2 py-1 rounded font-medium flex items-center gap-1 shrink-0">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Confirmed
                        </span>
                    </div>
                @empty
                    <div class="py-8 text-center border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-xl">
                        <svg class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No arrivals expected soon.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Recent payments --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
            <h2 class="font-bold text-gray-900 dark:text-white mb-4">Recent Payments</h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->recentPayments as $p)
                    <div wire:key="payment-{{ $p->id }}" class="py-3 flex justify-between items-center">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center text-green-700 dark:text-green-300 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                                    ₱{{ number_format((float) $p->amount, 2) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ $p->paid_at?->format('M d') ?? '—' }}
                                    @if($p->reference_number)
                                        · <span class="font-mono">{{ $p->reference_number }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-xl">
                        <svg class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No recent payments recorded.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ═══════════════ QUICK ACTIONS ═══════════════ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5">
        <h3 class="font-bold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                ['tenant.services.create',      'Add Service',   'M12 6v6m0 0v6m0-6h6m-6 0H6'],
                ['tenant.employees.create',     'Add Employee',  'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
                ['tenant.payments.index',       'Payments',      'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['tenant.settings.index',       'Settings',      'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
            ] as [$route, $label, $icon])
                <a href="{{ route($route) }}" wire:navigate
                   class="flex items-center justify-center gap-2 p-3 rounded-xl
                          bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20
                          text-primary-600 dark:text-primary-400 text-sm font-medium transition-all duration-200
                          active:scale-95 border border-primary-200 dark:border-primary-500/20
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                    </svg>
                    <span class="truncate">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>