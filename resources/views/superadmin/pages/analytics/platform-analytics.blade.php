{{-- resources/views/superadmin/pages/analytics/platform-analytics.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

new
#[Layout('superadmin.layouts.app')]
#[Title('Platform Analytics')]
class extends Component
{
    public ?string $startDate = null;
    public ?string $endDate = null;
    public string $preset = 'this_year';

    public function mount(): void
    {
        $this->applyPreset('this_year');
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;
        $now = now();

        switch ($preset) {
            case 'today':
                $this->startDate = $now->toDateString();
                $this->endDate   = $now->toDateString();
                break;
            case '7d':
                $this->startDate = $now->copy()->subDays(6)->toDateString();
                $this->endDate   = $now->toDateString();
                break;
            case '30d':
                $this->startDate = $now->copy()->subDays(29)->toDateString();
                $this->endDate   = $now->toDateString();
                break;
            case 'this_month':
                $this->startDate = $now->copy()->startOfMonth()->toDateString();
                $this->endDate   = $now->copy()->endOfMonth()->toDateString();
                break;
            case 'this_year':
            default:
                $this->startDate = $now->copy()->startOfYear()->toDateString();
                $this->endDate   = $now->copy()->endOfYear()->toDateString();
                break;
            case 'custom':
                // keep current dates
                break;
        }

        $this->dispatch('refreshCharts', chartData: $this->getChartData(), statusData: $this->getTenantStatusData());
    }

    public function refreshAnalytics(): void
    {
        $this->dispatch('refreshCharts', chartData: $this->getChartData(), statusData: $this->getTenantStatusData());
    }

    public function updatedStartDate(): void
    {
        $this->preset = 'custom';
        $this->dispatch('refreshCharts', chartData: $this->getChartData(), statusData: $this->getTenantStatusData());
    }

    public function updatedEndDate(): void
    {
        $this->preset = 'custom';
        $this->dispatch('refreshCharts', chartData: $this->getChartData(), statusData: $this->getTenantStatusData());
    }

    public function getStats(): array
    {
        $now = now();

        return cache()->remember('platform_analytics_stats', 60, function () use ($now) {
            return [
                'total_tenants'       => Tenant::count(),
                'active_tenants'      => Tenant::where('is_active', true)->count(),
                'pending_tenants'     => Tenant::where('is_active', false)->count(),
                'total_users'         => User::count(),
                'active_users'        => User::where('is_active', true)->count(),
                'new_this_month'      => Tenant::whereMonth('created_at', $now->month)
                                            ->whereYear('created_at', $now->year)->count(),
                'new_this_week'       => Tenant::where('created_at', '>=', $now->copy()->subDays(7))->count(),
                'new_users_this_month'=> User::whereMonth('created_at', $now->month)
                                            ->whereYear('created_at', $now->year)->count(),
            ];
        });
    }

    public function getChartData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfYear();
        $end   = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : now()->endOfYear();

        $isDaily = $start->diffInDays($end) <= 31;

        $phpFormat   = $isDaily ? 'Y-m-d' : 'Y-m';
        $sqlFormat   = $isDaily ? '%Y-%m-%d' : '%Y-%m';
        $labelFormat = $isDaily ? 'M d' : 'M Y';

        $period = CarbonPeriod::create(
            $isDaily ? $start : $start->copy()->startOfMonth(),
            $isDaily ? '1 day' : '1 month',
            $isDaily ? $end : $end->copy()->endOfMonth()
        );

        $labels = [];
        $keys = [];
        foreach ($period as $dt) {
            $keys[] = $dt->format($phpFormat);
            $labels[] = $dt->format($labelFormat);
        }

        $tenantGrowth = Tenant::whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '{$sqlFormat}') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $userGrowth = User::whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '{$sqlFormat}') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $tenants = [];
        $users = [];
        foreach ($keys as $key) {
            $tenants[] = $tenantGrowth->get($key, 0);
            $users[] = $userGrowth->get($key, 0);
        }

        return [
            'labels'  => $labels,
            'tenants' => $tenants,
            'users'   => $users,
            'isDaily' => $isDaily,
        ];
    }

    public function getTenantStatusData(): array
    {
        return [
            'labels' => ['Active', 'Pending'],
            'values' => [
                Tenant::where('is_active', true)->count(),
                Tenant::where('is_active', false)->count()
            ],
            'colors' => ['#34D399', '#FBBF24'],
        ];
    }

    public function exportCsv()
    {
        $data = $this->getChartData();
        $filename = 'platform-analytics-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Period', 'New Tenants', 'New Users']);
            foreach ($data['labels'] as $i => $label) {
                fputcsv($out, [$label, $data['tenants'][$i], $data['users'][$i]]);
            }
            fclose($out);
        }, $filename);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6" wire:poll.60s="refreshAnalytics">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Platform Analytics</h1>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <button type="button" wire:click="exportCsv" wire:loading.attr="disabled"
                    class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Exporting…
                </span>
            </button>
            <button type="button" onclick="window.print()"
                    class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/></svg>
                Print
            </button>
        </div>
    </div>

    {{-- Date Range Selector --}}
    <div class="card p-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach([
                'today' => 'Today',
                '7d' => '7 Days',
                '30d' => '30 Days',
                'this_month' => 'This Month',
                'this_year' => 'This Year',
            ] as $val => $label)
                <button type="button" wire:click="applyPreset('{{ $val }}')"
                        class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $preset === $val ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    {{ $label }}
                </button>
            @endforeach
            <button type="button" wire:click="applyPreset('custom')"
                    class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50
                           {{ $preset === 'custom' ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                Custom
            </button>
            @if($preset === 'custom')
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center w-full sm:w-auto">
                    <input type="date" wire:model.live="startDate"
                           class="input !py-2 !w-full sm:!w-auto">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">to</span>
                    <input type="date" wire:model.live="endDate"
                           class="input !py-2 !w-full sm:!w-auto">
                </div>
            @endif
        </div>
    </div>

    {{-- KPI Cards --}}
    @php $stats = $this->getStats(); @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Tenants</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['total_tenants'] }}</p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-1">{{ $stats['active_tenants'] }} active</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['active_tenants'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">operational</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">{{ $stats['pending_tenants'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">awaiting activation</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platform Users</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['total_users'] }}</p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-1">{{ $stats['active_users'] }} active</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">New This Month</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['new_this_month'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $stats['new_users_this_month'] }} new users</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">New This Week</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['new_this_week'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">businesses onboarded</p>
        </div>
    </div>

    {{-- Charts Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Tenant Growth --}}
        <div class="card p-6 lg:col-span-2">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Tenant Growth</h2>
            <div class="w-full h-80 relative" wire:ignore>
                <canvas id="tenantChart"></canvas>
            </div>
        </div>

        {{-- User Growth --}}
        <div class="card p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">User Growth</h2>
            <div class="w-full h-80 relative" wire:ignore>
                <canvas id="userChart"></canvas>
            </div>
        </div>

        {{-- Tenant Status --}}
        <div class="card p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Tenant Status</h2>
            <div class="w-full h-80 relative" wire:ignore>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    @script
    <script>
        let tenantChart, userChart, statusChart;
        let latestChartData = @js($this->getChartData());
        let latestStatusData = @js($this->getTenantStatusData());

        function getChartTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            return isDark ? {
                textColor: '#9ca3af',
                gridColor: 'rgba(255,255,255,0.06)',
                tenantLineColor: '#22d3ee',
                userLineColor: '#a78bfa',
            } : {
                textColor: '#4b5563',
                gridColor: 'rgba(0,0,0,0.08)',
                tenantLineColor: '#0891b2',
                userLineColor: '#7c3aed',
            };
        }

        function renderLineChart(canvasId, labels, values, label, color) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            const theme = getChartTheme();

            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        borderColor: color,
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        fill: false,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: color,
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

        function renderDoughnutChart(canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            const theme = getChartTheme();

            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: data.colors,
                        borderColor: 'transparent',
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: theme.textColor } } }
                }
            });
        }

        function initCharts() {
            if (typeof Chart === 'undefined') {
                setTimeout(initCharts, 100);
                return;
            }

            const theme = getChartTheme();

            if (document.getElementById('tenantChart')) {
                tenantChart = renderLineChart('tenantChart', latestChartData.labels, latestChartData.tenants, 'New Tenants', theme.tenantLineColor);
            }
            if (document.getElementById('userChart')) {
                userChart = renderLineChart('userChart', latestChartData.labels, latestChartData.users, 'New Users', theme.userLineColor);
            }
            if (document.getElementById('statusChart')) {
                statusChart = renderDoughnutChart('statusChart', latestStatusData);
            }
        }

        function updateCharts(chartData, statusData) {
            if(!tenantChart || !userChart || !statusChart) return;

            tenantChart.data.labels = chartData.labels;
            tenantChart.data.datasets[0].data = chartData.tenants;
            tenantChart.update();

            userChart.data.labels = chartData.labels;
            userChart.data.datasets[0].data = chartData.users;
            userChart.update();

            statusChart.data.labels = statusData.labels;
            statusChart.data.datasets[0].data = statusData.values;
            statusChart.update();
        }

        initCharts();

        Livewire.on('refreshCharts', ({ chartData, statusData }) => {
            updateCharts(chartData, statusData);
        });

        const themeObserver = new MutationObserver(() => {
            if (tenantChart) tenantChart.destroy();
            if (userChart) userChart.destroy();
            if (statusChart) statusChart.destroy();
            initCharts();
        });

        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
    @endscript
</div>