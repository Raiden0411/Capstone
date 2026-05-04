<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Service;
use App\Models\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new 
#[Layout('tenant.layouts.app')]
#[Title('Analytics')]
class extends Component {
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

        $totalBookings = Booking::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $totalGuests = Booking::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->distinct('customer_id')
            ->count('customer_id');

        $totalProperties = Property::where('tenant_id', $tenantId)->count();
        $activeBookings = Booking::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('check_in', '<=', $end)
            ->where('check_out', '>', $start)
            ->count();
        $occupancy = $totalProperties > 0 ? round(($activeBookings / $totalProperties) * 100, 1) : 0;

        $avgBookingValue = $totalBookings > 0 ? round($revenue / $totalBookings, 2) : 0;

        return [
            'revenue'           => $revenue,
            'total_bookings'    => $totalBookings,
            'total_guests'      => $totalGuests,
            'occupancy_rate'    => $occupancy,
            'avg_booking_value' => $avgBookingValue,
        ];
    }

    #[Computed]
    public function revenueTrend()
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
    public function upcomingActivity()
    {
        $today = now()->format('Y-m-d');
        return [
            'arrivals'   => Booking::where('tenant_id', Auth::user()->tenant_id)
                ->where('check_in', $today)
                ->where('status', '!=', 'cancelled')
                ->with('customer')
                ->get(),
            'departures' => Booking::where('tenant_id', Auth::user()->tenant_id)
                ->where('check_out', $today)
                ->where('status', '!=', 'cancelled')
                ->with('customer')
                ->get(),
        ];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-8">

    {{-- Header & Date Range --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Analytics</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Performance overview for your business.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <select wire:model.live="dateRange" class="rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last-7">Last 7 days</option>
                <option value="last-30">Last 30 days</option>
                <option value="this-month">This Month</option>
                <option value="last-month">Last Month</option>
                <option value="custom">Custom</option>
            </select>
            @if($dateRange === 'custom')
                <input type="date" wire:model.live="customStart" class="rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                <span class="text-gray-500 dark:text-slate-400">–</span>
                <input type="date" wire:model.live="customEnd" class="rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
            @endif
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Revenue</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">₱{{ number_format($this->stats['revenue'], 2) }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Bookings</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $this->stats['total_bookings'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Guests</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $this->stats['total_guests'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Occupancy</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $this->stats['occupancy_rate'] }}%</p>
            <p class="text-xs text-gray-500 dark:text-slate-400">Avg. Booking: ₱{{ number_format($this->stats['avg_booking_value'], 2) }}</p>
        </div>
    </div>

    {{-- Revenue Trend Chart + Top Services --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Trend</h3>
            <canvas id="revenueChart" height="200"></canvas>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Services</h3>
            <ul class="space-y-3">
                @forelse($this->topServices as $service)
                    <li class="flex justify-between items-center text-sm">
                        <span class="font-medium text-gray-700 dark:text-slate-300">{{ $service->name }}</span>
                        <span class="text-gray-500 dark:text-slate-400">x{{ $service->count }} · ₱{{ number_format($service->revenue, 2) }}</span>
                    </li>
                @empty
                    <li class="text-gray-500 dark:text-slate-400 text-sm">No services used in this period.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Today's Activity --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-3">Today's Arrivals ({{ now()->format('M d') }})</h2>
            <ul class="divide-y divide-gray-200 dark:divide-slate-700/30">
                @forelse($this->upcomingActivity['arrivals'] as $b)
                    <li class="py-2 flex justify-between">
                        <span class="text-gray-700 dark:text-slate-300">{{ $b->customer->name }}</span>
                        <span class="text-gray-500 dark:text-slate-400 text-sm">{{ $b->check_in->format('M d') }}</span>
                    </li>
                @empty
                    <li class="text-gray-500 dark:text-slate-400 py-2">No arrivals today.</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-3">Today's Departures ({{ now()->format('M d') }})</h2>
            <ul class="divide-y divide-gray-200 dark:divide-slate-700/30">
                @forelse($this->upcomingActivity['departures'] as $b)
                    <li class="py-2 flex justify-between">
                        <span class="text-gray-700 dark:text-slate-300">{{ $b->customer->name }}</span>
                        <span class="text-gray-500 dark:text-slate-400 text-sm">{{ $b->check_out->format('M d') }}</span>
                    </li>
                @empty
                    <li class="text-gray-500 dark:text-slate-400 py-2">No departures today.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let revenueChart = null;

    function getChartColors() {
        const isDark = document.documentElement.classList.contains('dark');
        return {
            grid: isDark ? 'rgba(148,163,184,0.15)' : 'rgba(0,0,0,0.06)',
            tick: isDark ? '#94a3b8' : '#6b7280',
            line: '#818cf8',  // indigo-400
            fill: isDark ? 'rgba(129,140,248,0.1)' : 'rgba(129,140,248,0.15)'
        };
    }

    function renderChart() {
        const canvas = document.getElementById('revenueChart');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (revenueChart) revenueChart.destroy();
        const data = @this.revenueTrend;
        const labels = Object.keys(data);
        const values = Object.values(data);
        if (labels.length === 0) return;
        const colors = getChartColors();
        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: values,
                    borderColor: colors.line,
                    backgroundColor: colors.fill,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: colors.grid },
                        ticks: { color: colors.tick }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: colors.tick, maxTicksLimit: 10 }
                    }
                }
            }
        });
    }

    document.addEventListener('livewire:init', () => {
        renderChart();
        Livewire.hook('morph.updated', ({ component }) => {
            if (component.fingerprint.name === 'tenant::pages.analytics.dashboard') {
                renderChart();
            }
        });
    });

    // Re-render on dark mode toggle
    const observer = new MutationObserver(() => renderChart());
    observer.observe(document.documentElement, { attributes: true });
</script>