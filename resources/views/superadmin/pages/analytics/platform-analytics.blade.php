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

    public function applyPreset($preset): void
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
                $this->endDate   = now()->toDateString();
                break;
            case '30d':
                $this->startDate = $now->copy()->subDays(29)->toDateString();
                $this->endDate   = now()->toDateString();
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

        $this->dispatch('refreshCharts', $this->getChartData(), $this->getTenantStatusData());
    }

    public function refreshAnalytics(): void
    {
        $this->dispatch('refreshCharts', $this->getChartData(), $this->getTenantStatusData());
    }

    public function updatedStartDate(): void
    {
        $this->preset = 'custom';
        $this->dispatch('refreshCharts', $this->getChartData(), $this->getTenantStatusData());
    }

    public function updatedEndDate(): void
    {
        $this->preset = 'custom';
        $this->dispatch('refreshCharts', $this->getChartData(), $this->getTenantStatusData());
    }

    public function getStats(): array
    {
        $now = now();
        return [
            'total_tenants'       => Tenant::count(),
            'active_tenants'      => Tenant::where('is_active', true)->count(),
            'pending_tenants'     => Tenant::where('is_active', false)->count(),
            'total_users'         => User::count(),
            'active_users'        => User::where('is_active', true)->count(),
            'new_this_month'      => Tenant::whereMonth('created_at', $now->month)
                                            ->whereYear('created_at', $now->year)
                                            ->count(),
            'new_this_week'       => Tenant::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'new_users_this_month'=> User::whereMonth('created_at', $now->month)
                                            ->whereYear('created_at', $now->year)
                                            ->count(),
        ];
    }

    public function getChartData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfYear();
        $end   = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : now()->endOfYear();

        $diffInDays = $start->diffInDays($end);
        $isDaily = $diffInDays <= 31;

        if ($isDaily) {
            $phpFormat  = 'Y-m-d';
            $sqlFormat  = '%Y-%m-%d';
            $labelFormat= 'M d';
            $period = CarbonPeriod::create($start, '1 day', $end);
        } else {
            $phpFormat  = 'Y-m';
            $sqlFormat  = '%Y-%m';
            $labelFormat= 'M Y';
            $period = CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end->copy()->endOfMonth());
        }

        $labels = [];
        $keys = [];
        foreach ($period as $dt) {
            $key = $dt->format($phpFormat);
            $keys[] = $key;
            $labels[] = $dt->format($labelFormat);
        }

        $tenantGrowth = Tenant::whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '{$sqlFormat}') as period, COUNT(*) as total")
            ->groupBy('period')->orderBy('period')->get()->pluck('total', 'period');

        $userGrowth = User::whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '{$sqlFormat}') as period, COUNT(*) as total")
            ->groupBy('period')->orderBy('period')->get()->pluck('total', 'period');

        $tenants = [];
        $users = [];
        foreach ($keys as $key) {
            $tenants[] = $tenantGrowth[$key] ?? 0;
            $users[] = $userGrowth[$key] ?? 0;
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
        $active = Tenant::where('is_active', true)->count();
        $inactive = Tenant::where('is_active', false)->count();
        return [
            'labels' => ['Active', 'Pending'],
            'values' => [$active, $inactive],
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

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6"
     wire:poll.60s="refreshAnalytics">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Platform Analytics</h1>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="exportCsv"
                    class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
            <button type="button" onclick="window.print()"
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
                <div class="flex gap-2 items-center">
                    <input type="date" wire:model.live="startDate"
                           class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <span class="text-gray-500 dark:text-gray-400">to</span>
                    <input type="date" wire:model.live="endDate"
                           class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                </div>
            @endif
        </div>
    </div>

    {{-- KPI Cards --}}
    @php $stats = $this->getStats(); @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Tenants</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['total_tenants'] }}</p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-1">{{ $stats['active_tenants'] }} active</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['active_tenants'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">operational</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">{{ $stats['pending_tenants'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">awaiting activation</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platform Users</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['total_users'] }}</p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-1">{{ $stats['active_users'] }} active</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">New This Month</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['new_this_month'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $stats['new_users_this_month'] }} new users</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">New This Week</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $stats['new_this_week'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">businesses onboarded</p>
        </div>
    </div>

    {{-- Charts Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Tenant Growth --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm lg:col-span-2">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Tenant Growth</h2>
            <div class="w-full h-80 relative">
                <div wire:loading wire:target="applyPreset,updatedStartDate,updatedEndDate" class="absolute inset-0 flex items-center justify-center bg-white/60 dark:bg-gray-800/60 rounded-xl">
                    <svg class="animate-spin h-6 w-6 text-primary-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span class="text-sm text-gray-500">Updating chart…</span>
                </div>
                <canvas id="tenantChart"></canvas>
            </div>
        </div>

        {{-- User Growth --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">User Growth</h2>
            <div class="w-full h-80 relative">
                <div wire:loading wire:target="applyPreset,updatedStartDate,updatedEndDate" class="absolute inset-0 flex items-center justify-center bg-white/60 dark:bg-gray-800/60 rounded-xl">
                    <svg class="animate-spin h-6 w-6 text-primary-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span class="text-sm text-gray-500">Updating chart…</span>
                </div>
                <canvas id="userChart"></canvas>
            </div>
        </div>

        {{-- Tenant Status --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Tenant Status</h2>
            <div class="w-full h-80 relative">
                <div wire:loading wire:target="applyPreset,updatedStartDate,updatedEndDate" class="absolute inset-0 flex items-center justify-center bg-white/60 dark:bg-gray-800/60 rounded-xl">
                    <svg class="animate-spin h-6 w-6 text-primary-600 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span class="text-sm text-gray-500">Updating chart…</span>
                </div>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    @script
    <script>
        let tenantChart = null;
        let userChart = null;
        let statusChart = null;

        let latestChartData = @js($this->getChartData());
        let latestStatusData = @js($this->getTenantStatusData());

        function isDarkMode() {
            return document.documentElement.classList.contains('dark');
        }

        function getChartTheme() {
            return isDarkMode() ? {
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

        function destroyCharts() {
            if (tenantChart) { tenantChart.destroy(); tenantChart = null; }
            if (userChart) { userChart.destroy(); userChart = null; }
            if (statusChart) { statusChart.destroy(); statusChart = null; }
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

        function renderDoughnutChart(canvasId, chartInstance, data) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            if (chartInstance) chartInstance.destroy();

            const theme = getChartTheme();
            const ctx = canvas.getContext('2d');
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

        function initCharts(chartData, statusData) {
            if (chartData) latestChartData = chartData;
            if (statusData) latestStatusData = statusData;

            destroyCharts();

            const theme = getChartTheme();

            const tenantCanvas = document.getElementById('tenantChart');
            if (tenantCanvas) {
                tenantChart = renderLineChart('tenantChart', tenantChart, latestChartData.labels, latestChartData.tenants, 'New Tenants', theme.tenantLineColor);
            }

            const userCanvas = document.getElementById('userChart');
            if (userCanvas) {
                userChart = renderLineChart('userChart', userChart, latestChartData.labels, latestChartData.users, 'New Users', theme.userLineColor);
            }

            const statusCanvas = document.getElementById('statusChart');
            if (statusCanvas) {
                statusChart = renderDoughnutChart('statusChart', statusChart, latestStatusData);
            }
        }

        function safeInitCharts(chartData, statusData) {
            if (typeof Chart !== 'undefined') {
                initCharts(chartData, statusData);
            } else {
                // Wait for Chart.js to load (CDN may be delayed)
                let attempts = 0;
                const checkChart = setInterval(() => {
                    if (typeof Chart !== 'undefined' || ++attempts > 20) {
                        clearInterval(checkChart);
                        if (typeof Chart !== 'undefined') initCharts(chartData, statusData);
                    }
                }, 100);
            }
        }

        // Initial render
        safeInitCharts(latestChartData, latestStatusData);

        Livewire.on('refreshCharts', (payload) => {
            const chartData = payload[0] || null;
            const statusData = payload[1] || null;

            if (chartData) latestChartData = chartData;
            if (statusData) latestStatusData = statusData;

            initCharts(latestChartData, latestStatusData);
        });

        Livewire.hook('morphed', () => {
            initCharts(@js($this->getChartData()), @js($this->getTenantStatusData()));
        });

        const themeObserver = new MutationObserver(() => {
            initCharts(latestChartData, latestStatusData);
        });
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
    @endscript

</div>