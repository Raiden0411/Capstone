{{-- resources/views/superadmin/pages/dashboard/⚡dashboard-page.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Event;
use Spatie\Permission\Models\Role;

new
#[Layout('superadmin.layouts.app')]
#[Title('Platform Dashboard')]
class extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    /** @return array<string, int> */
    #[Computed]
    public function stats(): array
    {
        $now          = now();
        $weekAgo      = $now->copy()->subDays(7);
        $startOfMonth = $now->copy()->startOfMonth();

        $tenantStats = Tenant::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
                COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_week,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_month
            ', [$weekAgo, $startOfMonth])
            ->first();

        $eventStats = Event::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN start_date >= ? AND is_active = 1 THEN 1 ELSE 0 END), 0) as upcoming,
                COALESCE(SUM(CASE WHEN featured = 1 AND is_active = 1 THEN 1 ELSE 0 END), 0) as featured
            ', [$now])
            ->first();

        return [
            'total_tenants'   => (int) ($tenantStats?->total ?? 0),
            'active_tenants'  => (int) ($tenantStats?->active ?? 0),
            'pending_tenants' => (int) ($tenantStats?->pending ?? 0),
            'new_this_week'   => (int) ($tenantStats?->new_this_week ?? 0),
            'new_this_month'  => (int) ($tenantStats?->new_this_month ?? 0),
            'total_users'     => User::query()->count(),
            'total_roles'     => Role::query()->where('name', '!=', 'super-admin')->count(),
            'total_events'    => (int) ($eventStats?->total ?? 0),
            'upcoming_events' => (int) ($eventStats?->upcoming ?? 0),
            'featured_events' => (int) ($eventStats?->featured ?? 0),
        ];
    }

    #[Computed]
    public function recentTenants()
    {
        return Tenant::query()
            ->with('typeOfTenant:id,type')
            ->select('id', 'name', 'slug', 'type_of_tenant_id', 'is_active', 'created_at')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function recentUsers()
    {
        return User::query()
            ->select('id', 'name', 'email', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function upcomingEvents()
    {
        return Event::query()
            ->select('id', 'name', 'start_date', 'barangay', 'type', 'image_path')
            ->where('is_active', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->limit(3)
            ->get();
    }

    /** @return array<int, array{label: string, value: int}> */
    #[Computed]
    public function tenantSparkline(): array
    {
        $startMonth = now()->startOfMonth()->subMonths(5);

        $counts = Tenant::query()
            ->where('created_at', '>=', $startMonth)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date   = now()->startOfMonth()->subMonths($i);
            $key    = $date->format('Y-m');
            $data[] = [
                'label' => $date->format('M'),
                'value' => (int) $counts->get($key, 0),
            ];
        }

        return $data;
    }

    /** @return array<string, string> */
    #[Computed]
    public function systemInfo(): array
    {
        return [
            'php'         => PHP_VERSION,
            'laravel'     => app()->version(),
            'environment' => (string) app()->environment(),
            'debug'       => config('app.debug') ? 'On' : 'Off',
            'cache'       => (string) config('cache.default'),
            'queue'       => (string) config('queue.default'),
        ];
    }

    #[Computed]
    public function serverTime(): \Carbon\Carbon
    {
        return now();
    }
};
?>

@push('styles')
    @once
        <style>
            .dashboard-page canvas {
                display:    block !important;
                width:      100%  !important;
                height:     100%  !important;
                max-height: 100% !important;
            }

            .superadmin-dashboard-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .superadmin-dashboard-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }

            @media print {
                @page { size: auto; margin: 12mm; }

                html, body {
                    background: #fff !important;
                    color: #000 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    min-height: 0 !important;
                    height: auto !important;
                }

                .dashboard-page { padding: 0 !important; margin: 0 !important; }

                .no-print { display: none !important; }

                canvas { print-color-adjust: exact; -webkit-print-color-adjust: exact; }

                .dashboard-page > * {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
            }
        </style>
    @endonce
@endpush

@push('scripts')
    @once
        {{-- Print scope --}}
        <script>
            (function () {
                if (window.__dashboardPrintScopeInstalled) return;
                window.__dashboardPrintScopeInstalled = true;

                function applyPrintScope() {
                    const root = document.querySelector('.dashboard-page');
                    if (!root) return;

                    const ancestors = new Set();
                    let el = root;
                    while (el && el !== document.body) {
                        ancestors.add(el);
                        el = el.parentElement;
                    }

                    Array.from(document.body.children).forEach(function (child) {
                        if (!ancestors.has(child)) {
                            if (child.dataset.printHidden !== '1') {
                                child.dataset.printHidden = '1';
                                child.dataset.printOldDisplay = child.style.display || '';
                            }
                            child.style.setProperty('display', 'none', 'important');
                        }
                    });

                    ancestors.forEach(function (node) {
                        if (node === root) return;
                        if (node.dataset.printReset !== '1') {
                            node.dataset.printReset = '1';
                            node.dataset.printOldPadding = node.style.padding || '';
                            node.dataset.printOldMargin = node.style.margin || '';
                            node.dataset.printOldBackground = node.style.background || '';
                        }
                        node.style.setProperty('padding', '0', 'important');
                        node.style.setProperty('margin', '0', 'important');
                        node.style.setProperty('background', 'transparent', 'important');
                    });
                }

                function restorePrintScope() {
                    document.querySelectorAll('[data-print-hidden="1"]').forEach(function (node) {
                        node.style.removeProperty('display');
                        if (node.dataset.printOldDisplay) {
                            node.style.display = node.dataset.printOldDisplay;
                        }
                        delete node.dataset.printHidden;
                        delete node.dataset.printOldDisplay;
                    });

                    document.querySelectorAll('[data-print-reset="1"]').forEach(function (node) {
                        node.style.removeProperty('padding');
                        node.style.removeProperty('margin');
                        node.style.removeProperty('background');
                        if (node.dataset.printOldPadding) node.style.padding = node.dataset.printOldPadding;
                        if (node.dataset.printOldMargin)  node.style.margin  = node.dataset.printOldMargin;
                        if (node.dataset.printOldBackground) node.style.background = node.dataset.printOldBackground;
                        delete node.dataset.printReset;
                        delete node.dataset.printOldPadding;
                        delete node.dataset.printOldMargin;
                        delete node.dataset.printOldBackground;
                    });
                }

                window.addEventListener('beforeprint', applyPrintScope);
                window.addEventListener('afterprint', restorePrintScope);
            })();
        </script>

        {{-- Sparkline wiring --}}
        <script>
            (function () {
                'use strict';

                if (window.__sparklineChart) {
                    try { window.__sparklineChart.destroy(); } catch (e) { /* noop */ }
                    window.__sparklineChart = null;
                }

                function getTheme() {
                    const isDark = document.documentElement.classList.contains('dark');
                    return {
                        textColor:   isDark ? '#9ca3af' : '#4b5563',
                        gridColor:   isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
                        lineColor:   isDark ? '#22d3ee' : '#0891b2',
                        fillColor:   isDark ? 'rgba(34,211,238,0.15)' : 'rgba(8,145,178,0.12)',
                        tooltipBg:   isDark ? '#1f2937' : '#ffffff',
                        tooltipText: isDark ? '#f3f4f6' : '#111827',
                    };
                }

                function getData() {
                    const container = document.getElementById('chart-container');
                    if (!container || !container.dataset.sparkline) return [];

                    try {
                        return JSON.parse(container.dataset.sparkline);
                    } catch (e) {
                        console.error('Sparkline data parse failed', e);
                        return [];
                    }
                }

                function buildOptions(theme) {
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend:  { display: false },
                            tooltip: {
                                backgroundColor: theme.tooltipBg,
                                titleColor:      theme.tooltipText,
                                bodyColor:       theme.textColor,
                                padding:         10,
                                cornerRadius:    8,
                                displayColors:   false,
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks:  { color: theme.textColor, precision: 0, font: { size: 11 } },
                                grid:   { color: theme.gridColor, borderDash: [4, 4] },
                                border: { display: false },
                            },
                            x: {
                                ticks:  { color: theme.textColor, font: { size: 11 } },
                                grid:   { display: false },
                                border: { display: false },
                            },
                        },
                        interaction: { intersect: false, mode: 'index' },
                    };
                }

                window.renderDashboardSparkline = function () {
                    if (typeof Chart === 'undefined') {
                        setTimeout(window.renderDashboardSparkline, 100);
                        return;
                    }

                    const canvas = document.getElementById('sparklineChart');
                    if (!canvas) return;

                    const data = getData();
                    if (!data.length) return;

                    const theme = getTheme();
                    const labels = data.map(d => d.label);
                    const values = data.map(d => d.value);

                    if (window.__sparklineChart && window.__sparklineChart.canvas === canvas) {
                        const chart = window.__sparklineChart;
                        chart.data.labels = labels;
                        chart.data.datasets[0].data = values;
                        chart.data.datasets[0].borderColor = theme.lineColor;
                        chart.data.datasets[0].backgroundColor = theme.fillColor;
                        chart.data.datasets[0].pointBackgroundColor = theme.lineColor;
                        chart.options.scales.y.ticks.color = theme.textColor;
                        chart.options.scales.y.grid.color  = theme.gridColor;
                        chart.options.scales.x.ticks.color = theme.textColor;
                        chart.options.plugins.tooltip.backgroundColor = theme.tooltipBg;
                        chart.options.plugins.tooltip.titleColor      = theme.tooltipText;
                        chart.options.plugins.tooltip.bodyColor       = theme.textColor;
                        chart.update('none');
                        return;
                    }

                    if (window.__sparklineChart) {
                        try { window.__sparklineChart.destroy(); } catch (e) { /* noop */ }
                    }

                    window.__sparklineChart = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                data: values,
                                borderColor: theme.lineColor,
                                borderWidth: 2.5,
                                tension: 0.4,
                                fill: true,
                                backgroundColor: theme.fillColor,
                                pointRadius: 3,
                                pointHoverRadius: 6,
                                pointBackgroundColor: theme.lineColor,
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                            }],
                        },
                        options: buildOptions(theme),
                    });
                };

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => window.renderDashboardSparkline());
                });

                if (!window.__sparklineHooked) {
                    window.__sparklineHooked = true;

                    const hookMorph = () => {
                        window.Livewire.hook('morph.updated', ({ el }) => {
                            if (el && el.id === 'chart-container') {
                                window.renderDashboardSparkline();
                            }
                        });
                    };

                    if (window.Livewire) {
                        hookMorph();
                    } else {
                        document.addEventListener('livewire:init', hookMorph);
                    }

                    window.addEventListener('resize', () => {
                        requestAnimationFrame(() => {
                            if (window.__sparklineChart) {
                                try { window.__sparklineChart.resize(); } catch (e) { /* noop */ }
                            }
                        });
                    });

                    if ('ResizeObserver' in window) {
                        const wrapper = document.getElementById('chart-container');
                        if (wrapper) {
                            new ResizeObserver(() => {
                                requestAnimationFrame(() => {
                                    if (window.__sparklineChart) {
                                        try { window.__sparklineChart.resize(); } catch (e) { /* noop */ }
                                    }
                                });
                            }).observe(wrapper);
                        }
                    }

                    let lastDark = document.documentElement.classList.contains('dark');
                    new MutationObserver(() => {
                        const isDark = document.documentElement.classList.contains('dark');
                        if (isDark !== lastDark) {
                            lastDark = isDark;
                            window.renderDashboardSparkline();
                        }
                    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                }
            })();
        </script>
    @endonce
@endpush

@php $s = $this->stats; @endphp

<div class="dashboard-page relative min-h-[100dvh] bg-[#F8F7F3] dark:bg-[#0F172A]" wire:poll.60s>

    <div class="superadmin-dashboard-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8 sm:space-y-12
                pb-[max(1.5rem,env(safe-area-inset-bottom))]">

        {{-- ═══════════════════════════════════════════════════════
             HERO — one thing dominates, everything else is context
             ═══════════════════════════════════════════════════════ --}}
        <section class="relative overflow-hidden rounded-3xl
                        bg-white/70 dark:bg-gray-800/40
                        backdrop-blur-xl
                        border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm">
            <div class="relative px-6 sm:px-10 py-8 sm:py-12">

                {{-- Title + actions --}}
                <div class="flex flex-wrap items-start justify-between gap-4 mb-10 sm:mb-14">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2.5 mb-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                                         bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300
                                         text-[10px] font-bold uppercase tracking-wider shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                                Live
                            </span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate tabular-nums">
                                {{ $this->serverTime->format('l, F j, Y · H:i') }}
                                · <span class="font-medium text-gray-700 dark:text-gray-300">{{ app()->environment() }}</span>
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white tracking-tight leading-tight">
                            Platform Overview
                        </h1>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 flex-wrap no-print">
                        <a href="{{ route('superadmin.analytics') }}" wire:navigate
                           class="inline-flex items-center justify-center gap-2 h-9 px-4 rounded-full
                                  bg-primary-600 hover:bg-primary-700 text-white
                                  text-xs font-semibold
                                  transition-all duration-200 active:scale-95
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span>Analytics</span>
                        </a>

                        <button type="button"
                                wire:click="$refresh"
                                wire:loading.attr="disabled"
                                wire:target="$refresh"
                                aria-label="Refresh dashboard"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full
                                       text-gray-500 dark:text-gray-400
                                       bg-gray-100/80 dark:bg-gray-900/60
                                       border border-gray-200/60 dark:border-white/[0.04]
                                       hover:text-gray-900 dark:hover:text-gray-100
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/>
                            </svg>
                            <span class="sr-only">Refresh</span>
                        </button>

                        <button type="button"
                                onclick="window.print()"
                                aria-label="Print dashboard"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full
                                       text-gray-500 dark:text-gray-400
                                       bg-gray-100/80 dark:bg-gray-900/60
                                       border border-gray-200/60 dark:border-white/[0.04]
                                       hover:text-gray-900 dark:hover:text-gray-100
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                            </svg>
                            <span class="sr-only">Print</span>
                        </button>
                    </div>
                </div>

                {{-- The number — tenants is the platform's primary metric --}}
                <div class="max-w-3xl">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-2">
                        Businesses on the platform
                    </p>
                    <div class="flex items-baseline gap-2 flex-wrap">
                        <span class="text-5xl sm:text-6xl lg:text-7xl font-bold text-gray-900 dark:text-white tabular-nums tracking-tight leading-none">
                            {{ number_format($s['total_tenants']) }}
                        </span>
                        <span class="text-2xl sm:text-3xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                            · {{ number_format($s['active_tenants']) }} live
                        </span>
                    </div>
                    <p class="mt-3 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                        {{ $s['new_this_month'] }} new this month
                        · {{ $s['new_this_week'] }} this week
                        · {{ number_format($s['total_users']) }} {{ \Illuminate\Support\Str::plural('user', (int) $s['total_users']) }} across the platform
                    </p>
                </div>

                {{-- Quiet KPI strip --}}
                <div class="mt-10 sm:mt-14 pt-6 sm:pt-8 border-t border-gray-200/60 dark:border-white/[0.06]">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-y-6 gap-x-4 sm:divide-x sm:divide-gray-200/60 dark:sm:divide-white/[0.06]">

                        <div class="sm:pr-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Active</p>
                            <p class="mt-1.5 text-xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums leading-none">
                                {{ number_format($s['active_tenants']) }}
                            </p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] {{ $s['pending_tenants'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                                Pending
                            </p>
                            <p class="mt-1.5 text-xl font-bold tabular-nums leading-none {{ $s['pending_tenants'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                                {{ number_format($s['pending_tenants']) }}
                            </p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Events</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['total_events']) }}</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Upcoming</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['upcoming_events']) }}</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Featured</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['featured_events']) }}</p>
                        </div>

                        <div class="sm:pl-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Users</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['total_users']) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════
             PENDING CALLOUT — only when action required
             ═══════════════════════════════════════════════════════ --}}
        @if($s['pending_tenants'] > 0)
            <section>
                <div class="flex flex-wrap items-center justify-between gap-4
                            rounded-3xl
                            bg-amber-50/60 dark:bg-amber-500/[0.06]
                            backdrop-blur-xl
                            border-l-4 border-amber-500
                            border-y border-r border-amber-200/70 dark:border-amber-500/25
                            px-5 sm:px-6 py-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="min-w-0">
                            <p class="font-semibold text-amber-900 dark:text-amber-200">Pending Approvals</p>
                            <p class="text-xs sm:text-sm text-amber-800/80 dark:text-amber-300/80 mt-0.5">
                                {{ $s['pending_tenants'] }} {{ \Illuminate\Support\Str::plural('business', $s['pending_tenants']) }} waiting for activation.
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('superadmin.tenants.index') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-1.5 h-11 px-4 rounded-xl
                              border border-amber-300 dark:border-amber-500/40
                              bg-white dark:bg-gray-800 text-amber-700 dark:text-amber-300
                              text-xs font-semibold
                              transition-all duration-200 active:scale-95
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              hover:bg-amber-50 dark:hover:bg-amber-500/10
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                        <span>Review Tenants</span>
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </section>
        @endif

        {{-- ═══════════════════════════════════════════════════════
             GROWTH — sparkline
             ═══════════════════════════════════════════════════════ --}}
        <section>
            <div class="rounded-3xl p-5 sm:p-6
                        bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                        border border-gray-200/60 dark:border-white/[0.06]">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Tenant Growth</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            New registrations over the last 6 months.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-cyan-600 dark:text-cyan-400 shrink-0">
                        <span class="w-2.5 h-2.5 rounded-sm bg-cyan-500" aria-hidden="true"></span>
                        New Tenants
                    </div>
                </div>

                <div id="chart-container"
                     data-sparkline="{{ json_encode($this->tenantSparkline, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
                     class="w-full h-40 relative overflow-hidden">
                    <div class="w-full h-full" wire:ignore>
                        <canvas id="sparklineChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════
             ACTIVITY — recent tenants + recent users
             ═══════════════════════════════════════════════════════ --}}
        <section>
            <div class="flex items-end justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-white tracking-tight">
                        Recent activity
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Latest businesses and users joining the platform.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Recent Tenants --}}
                <div class="rounded-3xl overflow-hidden
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]
                            flex flex-col">
                    <div class="px-5 sm:px-6 py-4 flex items-baseline justify-between gap-3
                                border-b border-gray-100/80 dark:border-white/[0.04]">
                        <div class="flex items-baseline gap-2 min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary-500 shrink-0" aria-hidden="true"></span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-tight truncate">
                                Recently Onboarded
                            </h3>
                        </div>
                        <a href="{{ route('superadmin.tenants.index') }}" wire:navigate
                           class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400
                                  hover:text-primary-800 dark:hover:text-primary-300 transition-colors shrink-0
                                  py-2.5 -my-2.5 px-1 -mx-1 rounded
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            View all
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <div class="divide-y divide-gray-100/80 dark:divide-white/[0.04] flex-1">
                        @forelse($this->recentTenants as $tenant)
                            <div wire:key="tenant-{{ $tenant->id }}"
                                 class="flex items-center gap-3 px-5 sm:px-6 py-3 min-h-[60px]
                                        hover:bg-white/80 dark:hover:bg-gray-800/50 transition-colors">
                                <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-500/15
                                            border border-primary-200/50 dark:border-primary-500/20
                                            flex items-center justify-center font-bold text-sm
                                            text-primary-700 dark:text-primary-300 shrink-0">
                                    {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $tenant->name }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate tabular-nums">
                                        {{ $tenant->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate
                                   aria-label="Manage {{ $tenant->name }}"
                                   title="Manage"
                                   class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                          text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10
                                          transition-all duration-200 active:scale-95
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <p class="text-sm">No tenants onboarded yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Recent Users --}}
                <div class="rounded-3xl overflow-hidden
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]
                            flex flex-col">
                    <div class="px-5 sm:px-6 py-4 flex items-baseline justify-between gap-3
                                border-b border-gray-100/80 dark:border-white/[0.04]">
                        <div class="flex items-baseline gap-2 min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0" aria-hidden="true"></span>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white tracking-tight truncate">
                                Recent Registrations
                            </h3>
                        </div>
                        <a href="{{ route('superadmin.users.index') }}" wire:navigate
                           class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400
                                  hover:text-primary-800 dark:hover:text-primary-300 transition-colors shrink-0
                                  py-2.5 -my-2.5 px-1 -mx-1 rounded
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            View all
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <div class="divide-y divide-gray-100/80 dark:divide-white/[0.04] flex-1">
                        @forelse($this->recentUsers as $user)
                            <div wire:key="user-{{ $user->id }}"
                                 class="flex items-center gap-3 px-5 sm:px-6 py-3 min-h-[60px]
                                        hover:bg-white/80 dark:hover:bg-gray-800/50 transition-colors">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/15
                                            border border-indigo-200/50 dark:border-indigo-500/20
                                            flex items-center justify-center font-bold text-sm
                                            text-indigo-700 dark:text-indigo-300 shrink-0">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $user->name }}</p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</p>
                                </div>
                                <span class="text-[10px] text-gray-500 dark:text-gray-400 shrink-0
                                             bg-white/70 dark:bg-gray-900/60 px-2 py-1 rounded-md tabular-nums
                                             border border-gray-200/60 dark:border-white/[0.04]">
                                    {{ $user->created_at->diffForHumans() }}
                                </span>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <p class="text-sm">No users registered yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════
             UPCOMING EVENTS
             ═══════════════════════════════════════════════════════ --}}
        <section>
            <div class="flex items-end justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">
                        Upcoming Events
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Next active events across the platform.
                    </p>
                </div>
                <a href="{{ route('superadmin.events.index') }}" wire:navigate
                   class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400
                          hover:text-primary-800 dark:hover:text-primary-300 transition-colors shrink-0
                          py-2.5 -my-2.5 px-1 -mx-1 rounded
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    View all
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            @if($this->upcomingEvents->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($this->upcomingEvents as $event)
                        <a href="{{ route('superadmin.events.edit', $event) }}" wire:navigate
                           wire:key="upcoming-{{ $event->id }}"
                           class="group flex items-start gap-3 p-4 rounded-2xl
                                  bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                                  border border-gray-200/60 dark:border-white/[0.06]
                                  hover:bg-white/80 dark:hover:bg-gray-800/50
                                  hover:border-primary-300/80 dark:hover:border-primary-500/40
                                  transition-all duration-200 active:scale-[0.99]
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <div class="w-14 h-14 rounded-xl overflow-hidden shrink-0
                                        bg-gradient-to-br from-primary-100 to-primary-50 dark:from-primary-500/15 dark:to-primary-500/5
                                        border border-primary-200/60 dark:border-primary-500/20 flex items-center justify-center">
                                @if($event->image_path)
                                    <img src="{{ '/storage/' . ltrim($event->image_path, '/') }}"
                                         class="w-full h-full object-cover"
                                         alt="{{ $event->name }}"
                                         loading="lazy"
                                         decoding="async">
                                @else
                                    <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                    {{ $event->type }}
                                </p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate mt-0.5">
                                    {{ $event->name }}
                                </p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1 tabular-nums">
                                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    {{ $event->start_date?->format('M d, Y') ?? '—' }}
                                </p>
                                @if($event->barangay)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $event->barangay }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-3xl py-12 text-center
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">No upcoming events</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Nothing scheduled at the moment.</p>
                </div>
            @endif
        </section>

        {{-- ═══════════════════════════════════════════════════════
             QUICK ACTIONS
             ═══════════════════════════════════════════════════════ --}}
        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                Quick actions
            </h2>
            <div class="flex flex-wrap gap-2 no-print">
                @php
                    $actions = [
                        ['superadmin.tenants.create',    'Add tenant',   'M12 4v16m8-8H4'],
                        ['superadmin.users.index',       'Manage users', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['superadmin.events.index',      'Manage events','M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                        ['superadmin.homepage.editor',   'Site settings','M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                    ];
                @endphp
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

        {{-- ═══════════════════════════════════════════════════════
             SYSTEM — quiet footer
             ═══════════════════════════════════════════════════════ --}}
        @php $sys = $this->systemInfo; @endphp
        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                System
            </h2>
            <div class="rounded-3xl p-5 sm:p-6
                        bg-white/40 dark:bg-gray-800/20 backdrop-blur-xl
                        border border-gray-200/50 dark:border-white/[0.05]">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @php
                        $infoCells = [
                            ['label' => 'PHP',         'value' => $sys['php']],
                            ['label' => 'Laravel',     'value' => $sys['laravel']],
                            ['label' => 'Environment', 'value' => $sys['environment']],
                            ['label' => 'Debug',       'value' => $sys['debug']],
                            ['label' => 'Cache',       'value' => $sys['cache']],
                            ['label' => 'Queue',       'value' => $sys['queue']],
                        ];
                    @endphp
                    @foreach($infoCells as $cell)
                        <div class="bg-white/60 dark:bg-gray-900/40 rounded-xl p-3 border border-gray-200/50 dark:border-white/[0.04]">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $cell['label'] }}</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white mt-1 font-mono truncate" title="{{ $cell['value'] }}">
                                {{ $cell['value'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</div>