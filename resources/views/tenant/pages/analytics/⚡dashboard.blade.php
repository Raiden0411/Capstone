<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $base = Booking::where('tenant_id', $tenantId)
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

    public function updatedDateRange()
    {
        $this->dispatch('refreshChart', $this->getRevenueTrend());
    }

    public function updatedCustomStart()
    {
        if ($this->dateRange === 'custom') {
            $this->dispatch('refreshChart', $this->getRevenueTrend());
        }
    }

    public function updatedCustomEnd()
    {
        if ($this->dateRange === 'custom') {
            $this->dispatch('refreshChart', $this->getRevenueTrend());
        }
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6"
     x-data="{}"
     x-init="Livewire.on('refreshChart', (data) => { if (window.renderBarChart) window.renderBarChart(data[0] || data); })">

    {{-- KPI Cards Row --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach([
            ['Revenue', '₱'.number_format($s['revenue'],2), 'emerald'],
            ['Bookings', $s['total_bookings'], 'blue'],
            ['Guests', $s['total_guests'], 'purple'],
            ['Occupancy', $s['occupancy_rate'].'%', 'amber'],
        ] as $index => [$label, $value, $color])
            <div class="glass-card !rounded-2xl p-5 flex flex-col justify-between h-36">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-{{ $color }}-500/20 flex items-center justify-center text-{{ $color }}-300">
                        @if($index == 0)<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @elseif($index == 1)<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        @elseif($index == 2)<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        @else<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                </div>
                <p class="text-sm text-white/50 font-medium">{{ $label }}</p>
                <div class="flex justify-between items-end mt-1">
                    <h3 class="text-2xl font-bold text-white">{{ $value }}</h3>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Revenue Chart + Revenue Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Bar Chart --}}
        <div class="lg:col-span-2 glass-card !rounded-2xl p-6 flex flex-col">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white mb-1">Revenue Stream Summary</h2>
                    <p class="text-sm text-white/60">Track your earnings across the selected period.</p>
                </div>
                <div class="flex bg-white/5 p-1 rounded-lg border border-white/10 text-sm font-medium">
                    @foreach(['today','yesterday','last-7','last-30','this-month'] as $range)
                        <button wire:click="$set('dateRange', '{{ $range }}')"
                                class="px-3 py-1.5 rounded-md transition-all
                                       {{ $dateRange === $range
                                          ? 'bg-brand-600 text-white shadow'
                                          : 'text-white/60 hover:bg-white/10 hover:text-white' }}">
                            {{ match($range) { 'today'=>'Today', 'yesterday'=>'Yest.', 'last-7'=>'7D', 'last-30'=>'30D', 'this-month'=>'Month', default=>ucfirst($range) } }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="w-full flex-grow relative min-h-[300px]">
                <canvas id="revenueChart" class="w-full h-full"></canvas>
                <div id="emptyState" class="hidden absolute inset-0 flex items-center justify-center">
                    <p class="text-white/40 text-sm">No revenue data for this period.</p>
                </div>
            </div>
        </div>

        {{-- Revenue Breakdown --}}
        <div class="glass-card !rounded-2xl p-6 flex flex-col relative">
            <button class="absolute top-6 right-5 text-white/40 hover:text-white transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
            </button>
            <h2 class="text-xl font-semibold text-white mb-1">Revenue Breakdown</h2>
            <p class="text-sm text-white/60 mb-6">Service share of total revenue</p>

            @php $breakdowns = $this->revenueBreakdown; @endphp
            @if(!empty($breakdowns))
                <h3 class="text-4xl font-bold text-white mb-2">₱{{ number_format(collect($breakdowns)->sum('total'), 2) }}</h3>
                <div class="flex items-center gap-2 mb-8 text-sm">
                    <span class="text-brand-400 font-semibold flex items-center">
                        100%
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                    </span>
                </div>

                <div class="w-full h-4 flex rounded-md overflow-hidden mb-8 gap-1">
                    @foreach($breakdowns as $b)
                        <div style="width: {{ $b['share'] }}%" class="bg-{{ ['#22c55e','#06b6d4','#facc15','#a855f7','#f43f5e'][$loop->index] ?? '#64748b' }} h-full"></div>
                    @endforeach
                </div>

                <div class="space-y-5 flex-grow flex flex-col justify-end">
                    @foreach($breakdowns as $b)
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-2.5 h-2.5 rounded-full bg-{{ ['#22c55e','#06b6d4','#facc15','#a855f7','#f43f5e'][$loop->index] ?? '#64748b' }}"></div>
                                <div>
                                    <p class="text-sm text-white/70 mb-1">{{ $b['name'] }}</p>
                                    <p class="text-lg font-semibold text-white">₱{{ number_format($b['total'], 2) }}</p>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 bg-{{ ['green','cyan','yellow','purple','red'][$loop->index] ?? 'gray' }}-500/10 text-{{ ['green','cyan','yellow','purple','red'][$loop->index] ?? 'gray' }}-300 text-xs font-bold rounded-md border border-{{ ['green','cyan','yellow','purple','red'][$loop->index] ?? 'gray' }}-500/20">
                                {{ $b['share'] }}%
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-white/40 py-8">No service data for this period.</p>
            @endif
        </div>
    </div>

    {{-- Booking Tracker + Cashflow --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Booking Tracker --}}
        <div class="lg:col-span-2 glass-card !rounded-2xl p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white mb-1">Booking Tracker</h2>
                    <p class="text-sm text-white/60">Status breakdown in selected period</p>
                </div>
                <div class="flex bg-white/5 p-1 rounded-lg border border-white/10 text-sm font-medium">
                    <span class="px-3 py-1.5 text-white/70">Overview</span>
                </div>
            </div>

            @php $statuses = $this->bookingStatusBreakdown; @endphp
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach([
                    ['Pending', $statuses['pending'] ?? 0, 'amber'],
                    ['Confirmed', $statuses['confirmed'] ?? 0, 'blue'],
                    ['Completed', $statuses['completed'] ?? 0, 'emerald'],
                    ['Cancelled', $statuses['cancelled'] ?? 0, 'red'],
                ] as [$title, $count, $color])
                    <div class="flex gap-3 items-start">
                        <div class="p-2.5 rounded-xl bg-{{ $color }}-500/20 border border-{{ $color }}-500/30 text-{{ $color }}-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm text-white/50 mb-1">{{ $title }}</p>
                            <p class="text-xl font-bold text-white">{{ $count }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Cashflow Overview --}}
        <div class="glass-card !rounded-2xl p-6 relative">
            <button class="absolute top-6 right-5 text-white/40 hover:text-white transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
            </button>
            <h2 class="text-xl font-semibold text-white mb-1">Cashflow Overview</h2>
            <p class="text-sm text-white/60 mb-6">Monthly income snapshot</p>

            @php
                $income = $s['revenue'];
                $net = $income;
            @endphp
            <h3 class="text-4xl font-bold text-white mb-2">₱{{ number_format($net, 2) }}</h3>
            <p class="text-sm text-white/40 mb-6">Net Revenue (Income - Expenses)</p>

            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-white/60">Total Revenue</span>
                    <span class="text-sm font-semibold text-white">₱{{ number_format($income, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-white/60">Operating Expenses</span>
                    <span class="text-sm font-semibold text-white">—</span>
                </div>
                <div class="h-px bg-white/10"></div>
                <div class="flex justify-between items-center font-bold">
                    <span class="text-sm text-white">Net Cash Position</span>
                    <span class="text-sm text-white">₱{{ number_format($net, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Today's Activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Arrivals --}}
        <div class="glass-card !rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-white">
                <span class="w-2 h-2 rounded-full bg-brand-400"></span>
                Today's Arrivals ({{ now()->format('M d') }})
            </h2>
            <div class="divide-y divide-white/10">
                @forelse($this->upcomingActivity['arrivals'] as $b)
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-white">{{ $b->customer->name ?? 'Guest' }}</p>
                            <p class="text-xs text-white/50">{{ $b->check_in->format('M d, Y') }}</p>
                        </div>
                        <span class="text-xs text-brand-400 font-medium">Arriving</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-white/40 text-sm">No arrivals today.</p>
                @endforelse
            </div>
        </div>
        {{-- Departures --}}
        <div class="glass-card !rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-white">
                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                Today's Departures ({{ now()->format('M d') }})
            </h2>
            <div class="divide-y divide-white/10">
                @forelse($this->upcomingActivity['departures'] as $b)
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-white">{{ $b->customer->name ?? 'Guest' }}</p>
                            <p class="text-xs text-white/50">{{ $b->check_out->format('M d, Y') }}</p>
                        </div>
                        <span class="text-xs text-rose-400 font-medium">Departing</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-white/40 text-sm">No departures today.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@script
<script>
    let revenueChart = null;

    window.renderBarChart = function(data) {
        const canvas = document.getElementById('revenueChart');
        const emptyState = document.getElementById('emptyState');
        if (!canvas) return;

        if (revenueChart) { revenueChart.destroy(); revenueChart = null; }

        const labels = Object.keys(data);
        const values = Object.values(data);

        if (labels.length === 0 || values.reduce((a, b) => a + b, 0) === 0) {
            canvas.style.display = 'none';
            if (emptyState) emptyState.classList.remove('hidden');
            return;
        }

        canvas.style.display = 'block';
        if (emptyState) emptyState.classList.add('hidden');

        const ctx = canvas.getContext('2d');

        let barGradient = ctx.createLinearGradient(0, 0, 0, 300);
        barGradient.addColorStop(0, '#22c55e');
        barGradient.addColorStop(0.5, '#06b6d4');
        barGradient.addColorStop(1, '#facc15');

        const gridColor   = 'rgba(255,255,255,0.06)';
        const tickColor   = '#9ca3af';
        const tooltipBg   = 'rgba(30,41,59,0.9)';
        const tooltipTitle= '#f1f5f9';
        const tooltipBody = '#cbd5e1';
        const tooltipBorder = 'rgba(255,255,255,0.12)';

        revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: values,
                    backgroundColor: barGradient,
                    borderRadius: 4,
                    borderSkipped: false,
                    barPercentage: 0.85,
                    categoryPercentage: 0.9,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: tooltipBg,
                        titleColor: tooltipTitle,
                        bodyColor: tooltipBody,
                        borderColor: tooltipBorder,
                        borderWidth: 1,
                        padding: 14,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: () => null,
                            label: (ctx) => `Revenue : ₱${parseFloat(ctx.raw).toFixed(2)}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: tickColor, font: { size: 11 }, padding: 8, maxTicksLimit: 10 }
                    },
                    y: {
                        grid: {
                            color: gridColor,
                            borderDash: [5, 5],
                            drawBorder: false
                        },
                        beginAtZero: true,
                        ticks: {
                            color: tickColor,
                            font: { size: 11 },
                            padding: 10,
                            callback: (val) => '₱' + val.toLocaleString()
                        }
                    }
                },
                animation: {
                    duration: 800,
                    easing: 'easeOutQuart'
                }
            }
        });
    };

    // Wait for Chart.js to be ready, then draw
    function initChart() {
        if (typeof Chart === 'undefined') {
            setTimeout(initChart, 100);
            return;
        }
        const initialData = @js($this->getRevenueTrend());
        window.renderBarChart(initialData);
    }

    // Start on page load
    initChart();

    // Also re‑draw when Livewire sends new data (date filter changes)
    Livewire.on('refreshChart', (payload) => {
        const data = payload[0] || payload;
        window.renderBarChart(data);
    });
</script>
@endscript