<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Platform Analytics')]
class extends Component {

    #[Computed]
    public function stats()
    {
        return [
            'total_tenants'    => Tenant::count(),
            'active_tenants'   => Tenant::where('is_active', true)->count(),
            'pending_tenants'  => Tenant::where('is_active', false)->count(),
            'new_this_month'   => Tenant::whereMonth('created_at', now()->month)
                                        ->whereYear('created_at', now()->year)
                                        ->count(),
        ];
    }

    #[Computed]
    public function tenantMonthlyGrowth()
    {
        // All-time, grouped by calendar month
        return Tenant::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn($item) => [$item->month => $item->total])
            ->toArray();
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-8">

    {{-- Page Header --}}
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Platform Analytics</h1>
        <p class="text-gray-500 dark:text-slate-400 mt-1">
            Aggregated tenant metrics – no individual tenant data is displayed.
        </p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-400 uppercase">Total Tenants</h3>
            <p class="mt-2 text-3xl font-bold">{{ $this->stats['total_tenants'] }}</p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-400 uppercase">Active</h3>
            <p class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $this->stats['active_tenants'] }}</p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-400 uppercase">Pending</h3>
            <p class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $this->stats['pending_tenants'] }}</p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-400 uppercase">New This Month</h3>
            <p class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">{{ $this->stats['new_this_month'] }}</p>
        </div>
    </div>

    {{-- Tenant Growth Chart (all-time, scrollable) --}}
    <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm overflow-x-auto">
        <h2 class="text-lg font-semibold mb-4">Tenant Growth</h2>
        <div id="chartWrapper" style="min-width: 600px;">
            <canvas id="tenantGrowthChart" height="150"></canvas>
        </div>
    </div>
</div>

{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function getChartColors() {
        const isDark = document.documentElement.classList.contains('dark');
        return {
            grid: isDark ? 'rgba(148,163,184,0.15)' : 'rgba(0,0,0,0.06)',
            tick: isDark ? '#94a3b8' : '#6b7280',
            line: '#818cf8',
            fill: isDark ? 'rgba(129,140,248,0.1)' : 'rgba(129,140,248,0.15)'
        };
    }

    let tenantGrowthChart = null;
    function renderGrowthChart() {
        const canvas = document.getElementById('tenantGrowthChart');
        const wrapper = document.getElementById('chartWrapper');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (tenantGrowthChart) tenantGrowthChart.destroy();
        const data = @json($this->tenantMonthlyGrowth);
        const labels = Object.keys(data);
        const values = Object.values(data);
        if (labels.length === 0) return;

        // Ensure horizontal scroll if many months
        const baseWidth = Math.max(600, labels.length * 40);
        wrapper.style.minWidth = baseWidth + 'px';

        const colors = getChartColors();
        tenantGrowthChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'New Tenants',
                    data: values,
                    borderColor: colors.line,
                    backgroundColor: colors.fill,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
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
                        ticks: { color: colors.tick, stepSize: 1 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: colors.tick, maxTicksLimit: 12, autoSkip: true }
                    }
                }
            }
        });
    }

    document.addEventListener('livewire:init', () => {
        renderGrowthChart();
        Livewire.hook('morph.updated', ({ component }) => {
            if (component.fingerprint.name === 'superadmin::analytics.platform-analytics') {
                renderGrowthChart();
            }
        });
    });

    const observer = new MutationObserver(() => renderGrowthChart());
    observer.observe(document.documentElement, { attributes: true });
</script>