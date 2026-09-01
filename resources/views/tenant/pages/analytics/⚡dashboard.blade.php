{{-- resources/views/tenant/pages/analytics/dashboard.blade.php --}}
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
                ->with('user:id,name')
                ->select('id', 'user_id', 'booking_reference', 'check_in')
                ->get(),
            'departures' => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', Auth::user()->tenant_id)
                ->where('check_out', $today)
                ->where('status', '!=', 'cancelled')
                ->with('user:id,name')
                ->select('id', 'user_id', 'booking_reference', 'check_out')
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
            ->with('user:id,name,email')
            ->select('id', 'user_id', 'booking_reference', 'check_in', 'check_out', 'total_amount', 'status')
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

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-8"
     x-data="{}"
     x-init="
        if (typeof Chart !== 'undefined') {
            initAnalyticsCharts();
        } else {
            document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
        }
     "
     wire:poll.60s="refreshAnalytics">

    {{-- Header & Export Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Performance Overview</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Track your property metrics and revenue.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
            <button wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Exporting…
                </span>
            </button>
            <button type="button" onclick="window.print()"
                    class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/></svg>
                Print
            </button>
        </div>
    </div>

    {{-- Modern Segmented Date Range Selector --}}
    <div class="card p-2 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div class="inline-flex flex-wrap items-center p-1 bg-gray-100 dark:bg-gray-900/50 rounded-lg w-full xl:w-auto">
            @foreach([
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last-7' => '7 Days',
                'last-30' => '30 Days',
                'this-month' => 'This Month',
                'last-month' => 'Last Month',
                'custom' => 'Custom',
            ] as $val => $label)
                <button wire:click="$set('dateRange', '{{ $val }}')"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $dateRange === $val ? 'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">
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

    {{-- KPI Cards Grid --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 xl:gap-6">
        
        {{-- Revenue --}}
        <div class="card p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</p>
                <p class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mt-1">₱{{ number_format($s['revenue'], 2) }}</p>
            </div>
            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
        </div>

        {{-- Bookings --}}
        <div class="card p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Bookings</p>
                <p class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mt-1">{{ $s['total_bookings'] }}</p>
            </div>
            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        </div>

        {{-- Guests --}}
        <div class="card p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Unique Guests</p>
                <p class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mt-1">{{ $s['total_guests'] }}</p>
            </div>
            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
        </div>

        {{-- Occupancy --}}
        <div class="card p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Occupancy Rate</p>
                <p class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mt-1">{{ $s['occupancy_rate'] }}%</p>
            </div>
            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </div>
        </div>
    </div>

    {{-- Secondary Metrics Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 xl:gap-6">
        <div class="card p-5 flex items-center justify-between">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Booking Value</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">₱{{ number_format($s['avg_booking_value'], 2) }}</p>
        </div>
        <div class="card p-5 flex items-center justify-between border-rose-200 dark:border-rose-900/30">
            <p class="text-sm font-medium text-rose-600 dark:text-rose-400">Outstanding Balance</p>
            <p class="text-lg font-bold text-rose-600 dark:text-rose-400">₱{{ number_format($s['outstanding_balance'], 2) }}</p>
        </div>
        <div class="card p-5 flex items-center justify-between">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Repeat Guest Rate</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $s['repeat_guest_rate'] }}%</p>
        </div>
    </div>

    {{-- Main Charts Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Revenue Chart --}}
        <div class="lg:col-span-2 card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Revenue Trend</h2>
            </div>
            <div class="w-full h-[300px]">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        {{-- Payment Methods --}}
        <div class="card p-6 flex flex-col">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Methods</h2>
            <div class="w-full h-[300px] flex-1 relative flex items-center justify-center">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Secondary Charts Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Booking Activity</h2>
            <div class="w-full h-[280px]">
                <canvas id="bookingChart"></canvas>
            </div>
        </div>
        
        <div class="card p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Occupancy History</h2>
            <div class="w-full h-[280px]">
                <canvas id="occupancyChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Breakdown & Activity Section --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- Service Breakdown --}}
        <div class="lg:col-span-1 card p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Top Services Breakdown</h2>
            @php $breakdowns = $this->revenueBreakdown; @endphp
            @if(!empty($breakdowns))
                <div class="space-y-5">
                    @foreach($breakdowns as $b)
                        <div>
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $b['name'] }}</span>
                                <span class="text-gray-900 dark:text-white font-semibold">₱{{ number_format($b['total'], 2) }}</span>
                            </div>
                            <div class="w-full h-2.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-primary-500 rounded-full" style="width: {{ $b['share'] }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 text-right">{{ $b['share'] }}% of total</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-40 text-center">
                    <svg class="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No service data for this period.</p>
                </div>
            @endif
        </div>

        {{-- Today's Operations --}}
        <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-6">
            {{-- Arrivals --}}
            <div class="card p-6 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                        </span>
                        Arrivals Today
                    </h2>
                    <span class="text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 px-2 py-1 rounded-full">{{ count($this->upcomingActivity['arrivals']) }}</span>
                </div>
                <div class="flex-1 overflow-y-auto pr-2">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @forelse($this->upcomingActivity['arrivals'] as $b)
                            <div class="py-3 flex justify-between items-center group">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 font-medium text-xs">
                                        {{ substr($b->user->name ?? 'G', 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $b->user->name ?? 'Guest' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Ref: #{{ $b->booking_reference }}</p>
                                    </div>
                                </div>
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-md flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Check-in
                                </span>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center h-32 text-center">
                                <p class="text-sm text-gray-500 dark:text-gray-400">No arrivals scheduled today.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Departures --}}
            <div class="card p-6 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                        Departures Today
                    </h2>
                    <span class="text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400 px-2 py-1 rounded-full">{{ count($this->upcomingActivity['departures']) }}</span>
                </div>
                <div class="flex-1 overflow-y-auto pr-2">
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        @forelse($this->upcomingActivity['departures'] as $b)
                            <div class="py-3 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 font-medium text-xs">
                                        {{ substr($b->user->name ?? 'G', 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $b->user->name ?? 'Guest' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Ref: #{{ $b->booking_reference }}</p>
                                    </div>
                                </div>
                                <span class="text-xs text-rose-500 dark:text-rose-400 font-medium bg-rose-50 dark:bg-rose-900/20 px-2 py-1 rounded-md flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Check-out
                                </span>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center h-32 text-center">
                                <p class="text-sm text-gray-500 dark:text-gray-400">No departures scheduled today.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
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
                gridColor: 'rgba(255,255,255,0.05)',
                barColor: '#10b981',
                lineBooking: '#3b82f6',
                lineOccupancy: '#f59e0b',
                fillOpacity: '0.15'
            } : {
                textColor: '#6b7280',
                gridColor: 'rgba(0,0,0,0.05)',
                barColor: '#059669',
                lineBooking: '#2563eb',
                lineOccupancy: '#d97706',
                fillOpacity: '0.1'
            };
        }

        function resetCharts() {
            if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
            if (bookingChart) { bookingChart.destroy(); bookingChart = null; }
            if (paymentChart) { paymentChart.destroy(); paymentChart = null; }
            if (occupancyChart) { occupancyChart.destroy(); occupancyChart = null; }
        }

        function createGradient(ctx, colorBase, opacity) {
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, colorBase);
            gradient.addColorStop(1, 'rgba(255,255,255,0)');
            return gradient;
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
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDarkMode() ? '#374151' : '#fff',
                            titleColor: isDarkMode() ? '#fff' : '#111827',
                            bodyColor: isDarkMode() ? '#d1d5db' : '#4b5563',
                            borderColor: isDarkMode() ? '#4b5563' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) { return '₱' + context.parsed.y.toLocaleString(); }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { color: theme.textColor, font: {family: "'Inter', sans-serif"} }, 
                            grid: { color: theme.gridColor, drawBorder: false },
                            border: { display: false }
                        },
                        x: { 
                            ticks: { color: theme.textColor, font: {family: "'Inter', sans-serif"} }, 
                            grid: { display: false },
                            border: { display: false }
                        }
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
            
            const rgbColor = color === theme.lineBooking ? (isDarkMode() ? '59, 130, 246' : '37, 99, 235') : (isDarkMode() ? '245, 158, 11' : '217, 119, 6');
            const gradient = createGradient(ctx, `rgba(${rgbColor}, ${theme.fillOpacity})`);

            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        borderColor: color,
                        backgroundColor: gradient,
                        tension: 0.4,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: color,
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDarkMode() ? '#374151' : '#fff',
                            titleColor: isDarkMode() ? '#fff' : '#111827',
                            bodyColor: isDarkMode() ? '#d1d5db' : '#4b5563',
                            borderColor: isDarkMode() ? '#4b5563' : '#e5e7eb',
                            borderWidth: 1,
                            padding: 10,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { color: theme.textColor, maxTicksLimit: 6 }, 
                            grid: { color: theme.gridColor, drawBorder: false, borderDash: [5, 5] },
                            border: { display: false }
                        },
                        x: { 
                            ticks: { color: theme.textColor, maxTicksLimit: 8 }, 
                            grid: { display: false },
                            border: { display: false }
                        }
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
                        backgroundColor: ['#059669', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444'],
                        borderWidth: isDarkMode() ? 2 : 0,
                        borderColor: isDarkMode() ? '#1f2937' : '#ffffff',
                        hoverOffset: 4
                    }]
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
                                pointStyle: 'circle'
                            } 
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) { return ' ₱' + context.parsed.toLocaleString(); }
                            }
                        }
                    }
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
                latestData.payment.map(p => p.method.charAt(0).toUpperCase() + p.method.slice(1)),
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