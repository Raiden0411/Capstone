{{-- resources/views/superadmin/pages/health/⚡slow-query-dashboard.blade.php --}}
<?php

use App\Models\SlowQueryAggregate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('superadmin.layouts.app')]
#[Title('Slow Query Dashboard')]
class extends Component {
    use WithPagination;

    public string $search  = '';
    public string $sortBy  = 'avg_ms';
    public int    $perPage = 12;

    /** Guard: super-admin only. Route middleware is the first layer. */
    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    public function hydrate(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
    }

    /** Reset pagination when any filter changes. */
    public function updatedSearch(): void  { $this->resetPage(); }
    public function updatedSortBy(): void  { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }

    #[Computed]
    public function stats(): array
    {
        $row = SlowQueryAggregate::query()
            ->selectRaw('COUNT(*) AS total_shapes')
            ->selectRaw('COALESCE(SUM(count), 0) AS total_executions')
            ->selectRaw('COALESCE(SUM(total_ms), 0) AS total_ms')
            ->selectRaw('COALESCE(AVG(avg_ms), 0) AS avg_of_avg')
            ->selectRaw('COALESCE(MAX(max_ms), 0) AS worst_ms')
            ->first();

        return [
            'total_shapes'     => (int)   ($row->total_shapes     ?? 0),
            'total_executions' => (int)   ($row->total_executions ?? 0),
            'total_ms'         => (float) ($row->total_ms         ?? 0),
            'avg_of_avg'       => (float) ($row->avg_of_avg       ?? 0),
            'worst_ms'         => (float) ($row->worst_ms         ?? 0),
        ];
    }

    #[Computed]
    public function queries()
    {
        return SlowQueryAggregate::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($sub) {
                $sub->where('sql_sample', 'like', '%' . $this->search . '%')
                    ->orWhere('route_name', 'like', '%' . $this->search . '%');
            }))
            ->when($this->sortBy === 'avg_ms',    fn ($q) => $q->orderByDesc('avg_ms'))
            ->when($this->sortBy === 'total_ms',  fn ($q) => $q->orderByDesc('total_ms'))
            ->when($this->sortBy === 'count',     fn ($q) => $q->orderByDesc('count'))
            ->when($this->sortBy === 'last_seen', fn ($q) => $q->orderByDesc('last_seen_at'))
            ->paginate($this->perPage);
    }

    #[Computed]
    public function chartData(): array
    {
        $top = SlowQueryAggregate::query()
            ->orderByDesc('avg_ms')
            ->take(10)
            ->get(['id', 'sql_sample', 'avg_ms', 'max_ms']);

        return [
            'labels' => $top->map(fn ($q) => \Illuminate\Support\Str::limit(
                preg_replace('/\s+/', ' ', (string) $q->sql_sample),
                42,
                '…'
            ))->all(),
            'avgs'   => $top->pluck('avg_ms')->map(fn ($v) => round((float) $v, 2))->all(),
            'maxes'  => $top->pluck('max_ms')->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }

    public function setSort(string $value): void
    {
        if (! in_array($value, ['avg_ms', 'total_ms', 'count', 'last_seen'], true)) {
            return;
        }

        $this->sortBy = $value;
        $this->resetPage();
        unset($this->stats, $this->queries, $this->chartData);
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
        unset($this->stats, $this->queries, $this->chartData);
    }
};
?>

<div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-5">

    {{-- ═══════════ Header ═══════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <span class="w-5 h-px bg-primary-600"></span>
                <h1 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Platform Health
                </h1>
            </div>
            <h2 class="text-2xl sm:text-3xl font-display font-bold text-gray-900 dark:text-white">
                Slow Query Dashboard
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Queries exceeding <span class="font-semibold text-amber-600 dark:text-amber-400">{{ (int) env('SLOW_QUERY_MS', 500) }}&nbsp;ms</span>.
                Harvested every 5 minutes.
            </p>
        </div>

        <a href="{{ route('superadmin.dashboard') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                  disabled:opacity-60 disabled:cursor-not-allowed">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to Dashboard
        </a>
    </div>

    {{-- ═══════════ KPI strip ═══════════ --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @php
            $kpis = [
                ['label' => 'Unique Shapes',   'value' => number_format($s['total_shapes']),                          'accent' => 'primary'],
                ['label' => 'Total Executions','value' => number_format($s['total_executions']),                      'accent' => 'amber'],
                ['label' => 'Avg of Averages', 'value' => number_format($s['avg_of_avg'], 2) . ' ms',                 'accent' => 'rose'],
                ['label' => 'Worst Single',    'value' => number_format($s['worst_ms'], 2) . ' ms',                   'accent' => 'purple'],
            ];
        @endphp
        @foreach($kpis as $k)
            <div wire:key="kpi-{{ $k['label'] }}"
                 class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $k['label'] }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $k['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ═══════════ Chart card ═══════════ --}}
    @if($s['total_shapes'] > 0)
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5">
            <div class="flex items-center gap-3 mb-4">
                <span class="w-5 h-px bg-primary-600"></span>
                <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Top 10 by Average Duration
                </h3>
            </div>
            <div class="h-52">
                <canvas id="slowQueryChart"></canvas>
            </div>
        </div>
    @endif

    {{-- ═══════════ Filter card ═══════════ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-3">

        <div class="flex flex-wrap gap-2 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       class="input w-full"
                       style="padding-left: 2.5rem;"
                       placeholder="Search SQL fragment or route name…"
                       aria-label="Search slow queries">
            </div>

            @if($search !== '')
                <button type="button"
                        wire:click="clearSearch"
                        wire:loading.attr="disabled"
                        wire:target="clearSearch"
                        class="inline-flex items-center justify-center gap-1.5 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    Clear
                </button>
            @endif

            <select wire:model.live="perPage"
                    class="input w-full sm:w-auto sm:min-w-[120px]"
                    aria-label="Rows per page">
                <option value="12">12 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>

        <div class="flex flex-wrap gap-2 items-center">
            @php
                $pills = [
                    ['value' => 'avg_ms',    'label' => 'Avg',       'count' => $s['total_shapes']],
                    ['value' => 'total_ms',  'label' => 'Total',     'count' => $s['total_shapes']],
                    ['value' => 'count',     'label' => 'Frequency', 'count' => $s['total_shapes']],
                    ['value' => 'last_seen', 'label' => 'Recent',    'count' => $s['total_shapes']],
                ];
            @endphp
            @foreach($pills as $pill)
                @php $isActive = $sortBy === $pill['value']; @endphp
                <button type="button"
                        wire:click="setSort('{{ $pill['value'] }}')"
                        wire:key="sort-pill-{{ $pill['value'] }}"
                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 h-9 pl-3.5 pr-3.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $isActive
                                    ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400' }}">
                    <span>{{ $pill['label'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- ═══════════ Results grid ═══════════ --}}
    @if($this->queries->isEmpty())
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="mx-auto w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>
                </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ $search !== '' ? 'No matching queries' : 'No slow queries recorded' }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $search !== ''
                    ? 'Try a different search term.'
                    : 'Every query on this platform is currently running faster than the threshold.' }}
            </p>
        </div>
    @else
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,sortBy,perPage,gotoPage,nextPage,previousPage"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">

            @foreach($this->queries as $query)
                <article wire:key="sq-{{ $query->id }}"
                         x-data="{ open: false }"
                         class="group bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-3 flex flex-col">

                    {{-- Meta row --}}
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 font-mono">
                                {{ substr($query->query_hash, 0, 12) }}
                            </p>
                            @if($query->route_name)
                                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                    via {{ $query->route_name }}
                                </p>
                            @endif
                        </div>
                        <span class="inline-flex items-center justify-center min-w-[36px] h-6 px-2 rounded-full text-[10px] font-bold tabular-nums bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 shrink-0">
                            {{ number_format($query->count) }}×
                        </span>
                    </div>

                    {{-- SQL preview --}}
                    <div class="rounded-xl bg-gray-50 dark:bg-gray-900/60 p-3 overflow-hidden">
                        <pre class="text-[11px] leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words font-mono m-0 max-h-[120px] overflow-y-auto">{{ $query->sql_sample }}</pre>
                    </div>

                    {{-- Timings --}}
                    <div class="grid grid-cols-3 gap-2 pt-1">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Avg</p>
                            <p class="text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($query->avg_ms, 1) }}<span class="text-[10px] font-normal text-gray-500 dark:text-gray-400"> ms</span></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Max</p>
                            <p class="text-sm font-bold tabular-nums text-rose-600 dark:text-rose-400">{{ number_format($query->max_ms, 1) }}<span class="text-[10px] font-normal text-gray-500 dark:text-gray-400"> ms</span></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Total</p>
                            <p class="text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($query->total_ms, 0) }}<span class="text-[10px] font-normal text-gray-500 dark:text-gray-400"> ms</span></p>
                        </div>
                    </div>

                    {{-- Bindings toggle --}}
                    @if($query->bindings_sample)
                        <div class="pt-2 border-t border-gray-100 dark:border-gray-700/60">
                            <button type="button"
                                    x-on:click="open = !open"
                                    class="text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition-colors">
                                <span x-text="open ? 'Hide bindings' : 'Show bindings'"></span>
                            </button>
                            <div :class="open ? 'block' : 'hidden'" class="mt-2">
                                <pre class="text-[10px] leading-relaxed text-gray-600 dark:text-gray-400 whitespace-pre-wrap break-all font-mono m-0 max-h-[140px] overflow-y-auto bg-gray-50 dark:bg-gray-900/60 rounded-lg p-2">{{ $query->bindings_sample }}</pre>
                            </div>
                        </div>
                    @endif

                    {{-- Last seen --}}
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700/60 text-[10px] text-gray-400 dark:text-gray-500">
                        Last seen {{ $query->last_seen_at?->diffForHumans() ?? '—' }}
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pt-2">
            {{ $this->queries->links() }}
        </div>
    @endif

</div>

@push('scripts')
    {{-- Chart.js — CDN, @once guard --}}
    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    @endonce

    <script>
    (function () {
        if (window.__slowQueryChartInstalled) {
            window.__renderSlowQueryChart?.();
            return;
        }
        window.__slowQueryChartInstalled = true;

        const chartData = @json($this->chartData);

        const barValueLabelPlugin = {
            id: 'barValueLabelSlow',
            afterDatasetsDraw(chart) {
                const ctx = chart.ctx;
                const isDark = document.documentElement.classList.contains('dark');
                ctx.save();
                ctx.font = '700 11px Inter, system-ui, sans-serif';
                ctx.fillStyle = isDark ? '#e5e7eb' : '#111827';
                ctx.textBaseline = 'middle';
                ctx.textAlign = 'left';

                chart.data.datasets.forEach((ds, di) => {
                    const meta = chart.getDatasetMeta(di);
                    if (meta.hidden) return;
                    meta.data.forEach((bar, i) => {
                        const v = ds.data[i];
                        if (v == null) return;
                        const pos = bar.tooltipPosition();
                        ctx.fillText(v + ' ms', pos.x + 8, pos.y);
                    });
                });
                ctx.restore();
            },
        };

        function getTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            return {
                isDark,
                textColor:     isDark ? '#9ca3af' : '#6b7280',
                gridColor:     isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
                tooltipBg:     isDark ? '#111827' : '#ffffff',
                tooltipText:   isDark ? '#f9fafb' : '#111827',
                tooltipBorder: isDark ? 'rgba(255,255,255,0.12)' : 'rgba(0,0,0,0.06)',
            };
        }

        function tooltipOptions(theme) {
            return {
                backgroundColor: theme.tooltipBg,
                titleColor: theme.tooltipText,
                bodyColor: theme.textColor,
                borderColor: theme.tooltipBorder,
                borderWidth: 1,
                padding: 14,
                boxPadding: 8,
                titleMarginBottom: 6,
                bodySpacing: 4,
                usePointStyle: true,
                cornerRadius: 14,
                displayColors: true,
                boxWidth: 8,
                boxHeight: 8,
                titleFont: { family: 'Inter, system-ui, sans-serif', size: 12, weight: '700' },
                bodyFont: { family: 'Inter, system-ui, sans-serif', size: 12, weight: '500' },
                caretSize: 6,
            };
        }

        function barGradient(ctx, hex) {
            const g = ctx.createLinearGradient(0, 0, 400, 0);
            g.addColorStop(0, hex + 'cc');
            g.addColorStop(1, hex + '66');
            return g;
        }

        let chartInstance = null;

        function render() {
            const canvas = document.getElementById('slowQueryChart');
            if (!canvas || typeof Chart === 'undefined') return;
            if (!chartData || !chartData.labels || chartData.labels.length === 0) return;

            if (chartInstance) { chartInstance.destroy(); chartInstance = null; }

            const theme = getTheme();
            const ctx = canvas.getContext('2d');

            Chart.register(barValueLabelPlugin);

            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Avg',
                            data: chartData.avgs,
                            backgroundColor: barGradient(ctx, '#6366f1'),
                            borderRadius: 8,
                            borderSkipped: false,
                            maxBarThickness: 18,
                        },
                        {
                            label: 'Max',
                            data: chartData.maxes,
                            backgroundColor: barGradient(ctx, '#f43f5e'),
                            borderRadius: 8,
                            borderSkipped: false,
                            maxBarThickness: 18,
                        },
                    ],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 4, right: 60, bottom: 4, left: 0 } },
                    animation: { duration: 700, easing: 'easeOutQuart' },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                color: theme.textColor,
                                padding: 12,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8,
                                boxHeight: 8,
                                font: { size: 11, weight: '600' },
                            },
                        },
                        tooltip: tooltipOptions(theme),
                        barValueLabelSlow: { enabled: true },
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grace: '5%',
                            ticks: {
                                color: theme.textColor,
                                font: { size: 10, weight: '500' },
                                padding: 6,
                            },
                            grid: { color: theme.gridColor, drawTicks: false },
                            border: { display: false },
                        },
                        y: {
                            ticks: {
                                color: theme.textColor,
                                font: { size: 10, weight: '500' },
                                autoSkip: false,
                                padding: 4,
                            },
                            grid: { display: false },
                            border: { display: false },
                        },
                    },
                },
            });
        }

        window.__renderSlowQueryChart = render;

        // Initial paint (canvas may not be in the DOM yet on cold load).
        document.addEventListener('livewire:navigated', () => setTimeout(render, 80));
        if (document.readyState !== 'loading') {
            setTimeout(render, 80);
        } else {
            document.addEventListener('DOMContentLoaded', () => setTimeout(render, 80));
        }

        // Re-render on Livewire morph of the chart bridge.
        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', ({ el }) => {
                if (el && el.querySelector && el.querySelector('#slowQueryChart')) {
                    setTimeout(render, 80);
                }
            });
        });

        // Dark mode hook — destroy + recreate.
        let lastDark = document.documentElement.classList.contains('dark');
        new MutationObserver(() => {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark !== lastDark) {
                lastDark = isDark;
                render();
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    })();
    </script>
@endpush