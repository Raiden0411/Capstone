{{-- resources/views/superadmin/pages/dashboard/dashboard-page.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

new
#[Layout('superadmin.layouts.app')]
#[Title('Platform Dashboard')]
class extends Component
{
    #[Computed]
    public function stats()
    {
        $now = now();
        return [
            'total_tenants'   => Tenant::count(),
            'active_tenants'  => Tenant::where('is_active', true)->count(),
            'pending_tenants' => Tenant::where('is_active', false)->count(),
            'total_users'     => User::count(),
            'total_roles'     => Role::where('name', '!=', 'super-admin')->count(),
            'new_this_week'   => Tenant::where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'new_this_month'  => Tenant::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
        ];
    }

    #[Computed]
    public function recentTenants()
    {
        return Tenant::with('typeOfTenant')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function recentUsers()
    {
        return User::orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function tenantSparkline()
    {
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Tenant::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $data[] = [
                'label' => $date->format('M'),
                'value' => $count,
            ];
        }
        return $data;
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-8" wire:poll.60s>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Platform Dashboard
            </h1>
            <p class="text-sm sm:text-base text-gray-600 dark:text-gray-300 mt-1">
                Super Admin Overview · {{ now()->format('F j, Y') }}
            </p>
        </div>
        <div class="text-right">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                System time <span class="text-gray-700 dark:text-gray-200">{{ now()->format('D, d M Y · H:i') }}</span>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Environment <span class="text-gray-700 dark:text-gray-200">{{ app()->environment() }}</span>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('superadmin.tenants.create') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-lg shadow-primary-500/20 transition-all duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Tenant
        </a>
        <a href="{{ route('superadmin.users.index') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            Manage Users
        </a>
        <a href="{{ route('superadmin.analytics') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            View Reports
        </a>
        <a href="{{ route('superadmin.homepage.editor') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            Edit Site Settings
        </a>
    </div>

    {{-- KPI Cards --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Tenants</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['total_tenants'] }}</p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-1">{{ $s['active_tenants'] }} active</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active Tenants</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['active_tenants'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">operational</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending Tenants</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">{{ $s['pending_tenants'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">awaiting activation</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">New This Week</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['new_this_week'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">businesses onboarded</p>
        </div>
    </div>

    {{-- Pending Approval Callout --}}
    @if($s['pending_tenants'] > 0)
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 border-l-4 border-l-amber-500 rounded-xl p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-amber-600 dark:text-amber-400">Pending Approval</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $s['pending_tenants'] }} businesses are waiting for activation.</p>
                </div>
                <a href="{{ route('superadmin.tenants.index') }}" wire:navigate
                   class="px-4 py-2 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 text-sm font-semibold border border-amber-200 dark:border-amber-500/20 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition focus-visible:ring-2 focus-visible:ring-amber-500/50">
                    Review Tenants →
                </a>
            </div>
        </div>
    @endif

    {{-- Tenant Growth Sparkline --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Tenant Growth — Last 6 Months</h2>
        <div class="w-full h-40">
            <canvas id="sparklineChart"></canvas>
        </div>
    </div>

    {{-- Recent Tenants & Users --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="font-bold text-gray-900 dark:text-white">Recently Onboarded</h2>
                <a href="{{ route('superadmin.tenants.index') }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">View all →</a>
            </div>
            <div class="p-6 space-y-3">
                @forelse($this->recentTenants as $tenant)
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 flex items-center justify-center font-mono text-sm font-medium text-blue-700 dark:text-blue-300 shrink-0">
                            {{ strtoupper(substr($tenant->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ Str::limit($tenant->name, 22) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant->created_at->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate class="text-xs font-semibold text-primary-600 hover:underline shrink-0 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">Manage →</a>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">No tenants onboarded yet.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="font-bold text-gray-900 dark:text-white">Recent User Registrations</h2>
                <a href="{{ route('superadmin.users.index') }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">View all →</a>
            </div>
            <div class="p-6 space-y-3">
                @forelse($this->recentUsers as $user)
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="w-9 h-9 rounded-lg bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/20 flex items-center justify-center font-mono text-sm font-medium text-purple-700 dark:text-purple-300 shrink-0">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ Str::limit($user->name, 22) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">{{ $user->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">No users registered yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- System Info --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">System Overview</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">PHP Version</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ PHP_VERSION }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Laravel Version</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ app()->version() }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Users</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $s['total_users'] }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">System Roles</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $s['total_roles'] }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Businesses</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $s['total_tenants'] }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">New This Month</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ $s['new_this_month'] }}</p>
            </div>
        </div>
    </div>

    @script
    <script>
        let sparklineChart = null;

        function getChartTheme() {
            return document.documentElement.classList.contains('dark') ? {
                textColor: '#9ca3af',
                gridColor: 'rgba(255,255,255,0.06)',
                lineColor: '#22d3ee',
                fillColor: 'rgba(34,211,238,0.10)',
            } : {
                textColor: '#4b5563',
                gridColor: 'rgba(0,0,0,0.08)',
                lineColor: '#0891b2',
                fillColor: 'rgba(8,145,178,0.10)',
            };
        }

        function renderSparkline(data) {
            if (sparklineChart) {
                sparklineChart.destroy();
                sparklineChart = null;
            }

            const canvas = document.getElementById('sparklineChart');
            if (!canvas || !data || data.length === 0) return;

            const theme = getChartTheme();
            const ctx = canvas.getContext('2d');
            sparklineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        data: data.map(d => d.value),
                        borderColor: theme.lineColor,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        backgroundColor: theme.fillColor,
                        pointRadius: 3,
                        pointBackgroundColor: theme.lineColor,
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

        const sparklineData = @js($this->tenantSparkline);

        function initCharts() {
            if (typeof Chart !== 'undefined') {
                renderSparkline(sparklineData);
            } else {
                document.addEventListener('DOMContentLoaded', () => {
                    renderSparkline(sparklineData);
                });
            }
        }

        initCharts();

        Livewire.hook('morphed', () => {
            renderSparkline(sparklineData);
        });

        const observer = new MutationObserver(() => {
            renderSparkline(sparklineData);
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
    @endscript

</div>