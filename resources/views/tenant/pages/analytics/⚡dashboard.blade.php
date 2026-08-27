{{-- resources/views/tenant/pages/analytics/⚡dashboard.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Service;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Analytics')]
class extends Component
{
    public string $dateRange = 'this-month';
    public string $customStart = '';
    public string $customEnd = '';

    public function mount()
    {
        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd   = now()->endOfMonth()->format('Y-m-d');
    }

    protected function getDateRange(): array
    {
        $now = now();
        return match ($this->dateRange) {
            'today'     => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last-7'    => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last-30'   => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this-month'=> [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last-month'=> [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'custom'    => [
                Carbon::parse($this->customStart)->startOfDay(),
                Carbon::parse($this->customEnd)->endOfDay(),
            ],
            default     => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    #[Computed]
    public function stats()
    {
        [$start, $end] = $this->getDateRange();
        $tenantId = Auth::user()->tenant_id;

        $revenue = Payment::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $totalBookings = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $totalGuests = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        $totalProperties = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->count();

        $activeBookings = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('check_in', '<=', $end)
            ->where('check_out', '>', $start)
            ->count();

        $occupancy = $totalProperties > 0 ? round(($activeBookings / $totalProperties) * 100, 1) : 0;
        $avgBookingValue = $totalBookings > 0 ? round($revenue / $totalBookings, 2) : 0;

        $outstandingBalance = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->get()
            ->sum(function ($booking) {
                $paid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
                return max(0, $booking->total_amount - $paid);
            });

        $repeatGuestRate = $this->getRepeatGuestRate($tenantId);

        return [
            'revenue'             => $revenue,
            'total_bookings'      => $totalBookings,
            'total_guests'        => $totalGuests,
            'occupancy_rate'      => $occupancy,
            'avg_booking_value'   => $avgBookingValue,
            'outstanding_balance' => $outstandingBalance,
            'repeat_guest_rate'   => $repeatGuestRate,
        ];
    }

    protected function getRepeatGuestRate(int $tenantId): float
    {
        $userCounts = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select('user_id', DB::raw('COUNT(*) as bookings'))
            ->groupBy('user_id')
            ->pluck('bookings', 'user_id');

        $totalGuests = $userCounts->count();
        $repeatGuests = $userCounts->filter(fn($count) => $count > 1)->count();

        return $totalGuests > 0 ? round(($repeatGuests / $totalGuests) * 100, 1) : 0;
    }

    public function getRevenueTrend(): array
    {
        [$start, $end] = $this->getDateRange();
        return Payment::where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();
    }

    public function getBookingTrend(): array
    {
        [$start, $end] = $this->getDateRange();
        return Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereBetween('created_at', [$start, $end])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();
    }

    public function getPaymentMethodBreakdown(): array
    {
        [$start, $end] = $this->getDateRange();
        return Payment::where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn($p) => [
                'method' => $p->payment_method,
                'total'  => $p->total,
            ])
            ->toArray();
    }

    public function getOccupancyTrend(): array
    {
        [$start, $end] = $this->getDateRange();
        $tenantId = Auth::user()->tenant_id;
        $totalProperties = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->count();

        if ($totalProperties === 0) return [];

        $dates = collect();
        $current = $start->copy();
        while ($current->lte($end)) {
            $dates->push($current->toDateString());
            $current->addDay();
        }

        $trend = [];
        foreach ($dates as $date) {
            $active = Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->where('check_in', '<=', $date)
                ->where('check_out', '>', $date)
                ->count();

            $trend[$date] = round(($active / $totalProperties) * 100, 1);
        }

        return $trend;
    }

    #[Computed]
    public function topServices()
    {
        [$start, $end] = $this->getDateRange();
        return DB::table('booking_services')
            ->join('services', 'booking_services.service_id', '=', 'services.id')
            ->join('bookings', 'booking_services.booking_id', '=', 'bookings.id')
            ->where('booking_services.tenant_id', Auth::user()->tenant_id)
            ->whereBetween('bookings.created_at', [$start, $end])
            ->select('services.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(booking_services.subtotal) as revenue'))
            ->groupBy('services.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function bookingStatusBreakdown(): array
    {
        [$start, $end] = $this->getDateRange();
        $tenantId = Auth::user()->tenant_id;
        $base = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end]);

        return [
            'pending'   => (clone $base)->where('status', 'pending')->count(),
            'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
        ];
    }

    #[Computed]
    public function revenueBreakdown(): array
    {
        $services = $this->topServices;
        $total = $services->sum('revenue');
        if ($total <= 0) return [];

        return $services->map(function ($s) use ($total) {
            return [
                'name'  => $s->name,
                'share' => round(($s->revenue / $total) * 100, 1),
                'total' => $s->revenue,
            ];
        })->toArray();
    }

    #[Computed]
    public function upcomingActivity()
    {
        $today = now()->format('Y-m-d');
        return [
            'arrivals'   => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', Auth::user()->tenant_id)
                ->where('check_in', $today)
                ->where('status', '!=', 'cancelled')
                ->with('user')
                ->get(),
            'departures' => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', Auth::user()->tenant_id)
                ->where('check_out', $today)
                ->where('status', '!=', 'cancelled')
                ->with('user')
                ->get(),
        ];
    }

    public function refreshAnalytics()
    {
        $this->dispatch('refreshCharts', [
            'revenue'   => $this->getRevenueTrend(),
            'bookings'  => $this->getBookingTrend(),
            'payment'   => $this->getPaymentMethodBreakdown(),
            'occupancy' => $this->getOccupancyTrend(),
        ]);
    }

    public function updatedDateRange($value)
    {
        $this->refreshAnalytics();
    }

    public function updatedCustomStart()
    {
        if ($this->dateRange === 'custom') {
            $this->refreshAnalytics();
        }
    }

    public function updatedCustomEnd()
    {
        if ($this->dateRange === 'custom') {
            $this->refreshAnalytics();
        }
    }

    public function exportCsv()
    {
        [$start, $end] = $this->getDateRange();
        $bookings = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereBetween('created_at', [$start, $end])
            ->with('user')
            ->get();

        $filename = 'analytics_bookings_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($bookings) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Reference', 'Guest', 'Email', 'Check-in', 'Check-out', 'Total', 'Status']);
            foreach ($bookings as $b) {
                fputcsv($file, [
                    $b->booking_reference,
                    $b->user->name ?? 'N/A',
                    $b->user->email ?? '',
                    $b->check_in?->format('Y-m-d'),
                    $b->check_out?->format('Y-m-d'),
                    number_format($b->total_amount, 2),
                    $b->status,
                ]);
            }
            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6"
     x-data="{}"
     x-init="
        if (typeof Chart !== 'undefined') {
            initAnalyticsCharts();
        } else {
            document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
        }
     "
     wire:poll.60s="refreshAnalytics">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Analytics</h1>
        </div>
        <div class="flex gap-2">
            <button wire:click="exportCsv"
                    class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
            <button onclick="window.print()"
                    class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/></svg>
                Print
            </button>
        </div>
    </div>

    {{-- Date Range Selector --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            @foreach([
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last-7' => '7 Days',
                'last-30' => '30 Days',
                'this-month' => 'This Month',
                'last-month' => 'Last Month',
            ] as $val => $label)
                <button wire:click="$set('dateRange', '{{ $val }}')"
                        class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $dateRange === $val ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    {{ $label }}
                </button>
            @endforeach
            <button wire:click="$set('dateRange', 'custom')"
                    class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition focus-visible:ring-2 focus-visible:ring-primary-500/50
                           {{ $dateRange === 'custom' ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                Custom
            </button>
            @if($dateRange === 'custom')
                <div class="flex gap-2 items-center">
                    <input type="date" wire:model.live="customStart"
                           class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <span class="text-gray-500 dark:text-gray-400">to</span>
                    <input type="date" wire:model.live="customEnd"
                           class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                </div>
            @endif
        </div>
    </div>

    {{-- KPI Cards --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Revenue</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['revenue'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bookings</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['total_bookings'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Guests</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['total_guests'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Occupancy</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['occupancy_rate'] }}%</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Booking</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['avg_booking_value'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wider">Outstanding</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">₱{{ number_format($s['outstanding_balance'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Repeat Guests</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['repeat_guest_rate'] }}%</p>
        </div>
    </div>

    {{-- Revenue Chart --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Revenue Trend</h2>
        <div class="w-full h-80">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    {{-- Booking Trend & Payment Method --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Booking Trend</h2>
            <div class="w-full h-80">
                <canvas id="bookingChart"></canvas>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Payment Methods</h2>
            <div class="w-full h-80 flex items-center justify-center">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Occupancy Trend & Service Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Occupancy Trend</h2>
            <div class="w-full h-80">
                <canvas id="occupancyChart"></canvas>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Service Breakdown</h2>
            @php $breakdowns = $this->revenueBreakdown; @endphp
            @if(!empty($breakdowns))
                <div class="space-y-4">
                    @foreach($breakdowns as $b)
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-700 dark:text-gray-300">{{ $b['name'] }}</span>
                                <span class="text-gray-900 dark:text-white font-semibold">₱{{ number_format($b['total'], 2) }} ({{ $b['share'] }}%)</span>
                            </div>
                            <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full mt-1">
                                <div class="h-full bg-primary-600 rounded-full" style="width: {{ $b['share'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 py-8">No service data for this period.</p>
            @endif
        </div>
    </div>

    {{-- Today's Activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-primary-600"></span>
                Today's Arrivals ({{ now()->format('M d') }})
            </h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->upcomingActivity['arrivals'] as $b)
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $b->user->name ?? 'Guest' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $b->check_in->format('M d, Y') }}</p>
                        </div>
                        <span class="text-xs text-primary-600 dark:text-primary-400 font-medium">Arriving</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">No arrivals today.</p>
                @endforelse
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                Today's Departures ({{ now()->format('M d') }})
            </h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->upcomingActivity['departures'] as $b)
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $b->user->name ?? 'Guest' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $b->check_out->format('M d, Y') }}</p>
                        </div>
                        <span class="text-xs text-rose-500 dark:text-rose-400 font-medium">Departing</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">No departures today.</p>
                @endforelse
            </div>
        </div>
    </div>

    @script
    <script>
        let revenueChart = null;
        let bookingChart = null;
        let paymentChart = null;
        let occupancyChart = null;

        let latestData = {
            revenue: @js($this->getRevenueTrend()),
            bookings: @js($this->getBookingTrend()),
            payment: @js($this->getPaymentMethodBreakdown()),
            occupancy: @js($this->getOccupancyTrend()),
        };

        function isDarkMode() {
            return document.documentElement.classList.contains('dark');
        }

        function getChartTheme() {
            return isDarkMode() ? {
                textColor: '#9ca3af',
                gridColor: 'rgba(255,255,255,0.06)',
                barColor: '#22c55e',
                lineBooking: '#06b6d4',
                lineOccupancy: '#facc15',
            } : {
                textColor: '#4b5563',
                gridColor: 'rgba(0,0,0,0.08)',
                barColor: '#10b981',
                lineBooking: '#0891b2',
                lineOccupancy: '#d97706',
            };
        }

        function resetCharts() {
            if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
            if (bookingChart) { bookingChart.destroy(); bookingChart = null; }
            if (paymentChart) { paymentChart.destroy(); paymentChart = null; }
            if (occupancyChart) { occupancyChart.destroy(); occupancyChart = null; }
        }

        function renderBarChart(canvasId, chartInstance, labels, values, label, color) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            if (chartInstance) chartInstance.destroy();

            const theme = getChartTheme();
            const ctx = canvas.getContext('2d');
            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        backgroundColor: color || theme.barColor,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: theme.textColor }, grid: { color: theme.gridColor } },
                        x: { ticks: { color: theme.textColor }, grid: { display: false } }
                    }
                }
            });
        }

        function renderLineChart(canvasId, chartInstance, labels, values, label, color) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            if (chartInstance) chartInstance.destroy();

            const theme = getChartTheme();
            const ctx = canvas.getContext('2d');
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        borderColor: color || '#06b6d4',
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        fill: false,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: color || '#06b6d4',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: theme.textColor }, grid: { color: theme.gridColor } },
                        x: { ticks: { color: theme.textColor }, grid: { display: false } }
                    }
                }
            });
        }

        function renderDoughnutChart(canvasId, chartInstance, labels, values) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            if (chartInstance) chartInstance.destroy();

            const theme = getChartTheme();
            const ctx = canvas.getContext('2d');
            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#10b981', '#0891b2', '#d97706', '#7c3aed', '#ef4444'],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: theme.textColor } } }
                }
            });
        }

        function initAnalyticsCharts() {
            resetCharts();

            const theme = getChartTheme();

            revenueChart = renderBarChart(
                'revenueChart',
                revenueChart,
                Object.keys(latestData.revenue),
                Object.values(latestData.revenue),
                'Revenue',
                theme.barColor
            );

            bookingChart = renderLineChart(
                'bookingChart',
                bookingChart,
                Object.keys(latestData.bookings),
                Object.values(latestData.bookings),
                'Bookings',
                theme.lineBooking
            );

            paymentChart = renderDoughnutChart(
                'paymentChart',
                paymentChart,
                latestData.payment.map(p => p.method),
                latestData.payment.map(p => p.total)
            );

            occupancyChart = renderLineChart(
                'occupancyChart',
                occupancyChart,
                Object.keys(latestData.occupancy),
                Object.values(latestData.occupancy),
                'Occupancy %',
                theme.lineOccupancy
            );
        }

        initAnalyticsCharts();

        Livewire.hook('morphed', () => {
            initAnalyticsCharts();
        });

        Livewire.on('refreshCharts', (payload) => {
            const data = payload[0] || payload;
            if (data.revenue) latestData.revenue = data.revenue;
            if (data.bookings) latestData.bookings = data.bookings;
            if (data.payment) latestData.payment = data.payment;
            if (data.occupancy) latestData.occupancy = data.occupancy;

            initAnalyticsCharts();
        });

        const themeObserver = new MutationObserver(() => {
            initAnalyticsCharts();
        });
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
    @endscript

</div>