<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Platform Analytics')]
class extends Component
{
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = now()->startOfYear()->toDateString();
        $this->endDate   = now()->toDateString();
    }

    public function getStats()
    {
        return [
            'total_tenants'   => Tenant::count(),
            'active_tenants'  => Tenant::where('is_active', true)->count(),
            'pending_tenants' => Tenant::where('is_active', false)->count(),
            'new_this_month'  => Tenant::whereMonth('created_at', now()->month)
                                        ->whereYear('created_at', now()->year)
                                        ->count(),
            'total_users'     => User::count(),
            'active_users'    => User::where('is_active', true)->count(),
        ];
    }

    public function getChartData(): array
    {
        $start = $this->startDate
            ? Carbon::parse($this->startDate)->startOfMonth()
            : now()->startOfYear();
        $end = $this->endDate
            ? Carbon::parse($this->endDate)->endOfMonth()
            : now()->endOfMonth();

        $period = CarbonPeriod::create($start, '1 month', $end);
        $months = [];
        foreach ($period as $dt) {
            $months[] = $dt->format('Y-m');
        }

        $tenantGrowth = Tenant::whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')->orderBy('month')->get()->pluck('total', 'month');

        $tenantsData = [];
        foreach ($months as $m) {
            $tenantsData[] = $tenantGrowth[$m] ?? 0;
        }

        return [
            'labels'  => $months,
            'tenants' => $tenantsData,
        ];
    }

    public function updatedStartDate()
    {
        $this->dispatch('refreshChart', $this->getChartData());
    }

    public function updatedEndDate()
    {
        $this->dispatch('refreshChart', $this->getChartData());
    }
};
?>

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=JetBrains+Mono:wght@300;400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
    :root {
        --bg:        #07090F;
        --bg1:       #0D1117;
        --bg2:       #131820;
        --bg3:       #1C2333;
        --line:      rgba(255,255,255,0.06);
        --line2:     rgba(255,255,255,0.10);
        --cyan:      #22D3EE;
        --cyan-dim:  rgba(34,211,238,0.10);
        --amber:     #FBBF24;
        --green:     #34D399;
        --purple:    #A78BFA;
        --text1:     #E2E8F0;
        --text2:     #8892A4;
        --text3:     #4A5568;
        --mono:      'JetBrains Mono', monospace;
        --sans:      'DM Sans', sans-serif;
        --display:   'Syne', sans-serif;
    }

    .an-wrap { font-family: var(--sans); background: var(--bg); min-height: 100vh; color: var(--text1); }

    .an-wrap::before {
        content: '';
        position: fixed; inset: 0; pointer-events: none; z-index: 0;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
        opacity: .4;
    }

    .an-inner { position: relative; z-index: 1; padding: 2.5rem 2.5rem 4rem; max-width: 1400px; margin: 0 auto; }
    @media(max-width:640px){ .an-inner { padding: 1.5rem 1.25rem 3rem; } }

    @keyframes fadeUp {
        from { opacity:0; transform:translateY(12px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .au  { animation: fadeUp .5s cubic-bezier(.22,.68,0,1.1) both; }
    .d1  { animation-delay: .07s; }
    .d2  { animation-delay: .14s; }
    .d3  { animation-delay: .21s; }

    /* ── Page header ─────────────────────────────────────── */
    .page-header {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: 1.5rem; margin-bottom: 2.5rem; flex-wrap: wrap;
        padding-bottom: 1.75rem;
        border-bottom: 1px solid var(--line);
    }
    .page-eyebrow {
        font-family: var(--mono); font-size: .68rem;
        letter-spacing: .2em; text-transform: uppercase;
        color: var(--cyan); margin-bottom: .5rem;
        display: flex; align-items: center; gap: .5rem;
    }
    .page-eyebrow::before {
        content: ''; width: 6px; height: 6px; border-radius: 50%;
        background: var(--cyan); box-shadow: 0 0 8px var(--cyan); flex-shrink:0;
    }
    .page-title {
        font-family: var(--display); font-size: clamp(1.75rem,3vw,2.4rem);
        font-weight: 700; color: var(--text1); letter-spacing: -.025em; line-height: 1;
    }

    /* ── Date range picker ───────────────────────────────── */
    .date-row {
        display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
    }
    .date-sep { font-family: var(--mono); font-size: .7rem; color: var(--text3); }
    .date-input {
        background: var(--bg2); border: 1px solid var(--line2);
        color: var(--text1); border-radius: 6px;
        padding: .55rem .875rem;
        font-family: var(--mono); font-size: .72rem;
        outline: none; transition: border-color .15s, box-shadow .15s;
        color-scheme: dark;
    }
    .date-input:focus { border-color: rgba(34,211,238,.4); box-shadow: 0 0 0 3px rgba(34,211,238,.06); }

    /* ── KPI grid ────────────────────────────────────────── */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1px; background: var(--line);
        border: 1px solid var(--line); border-radius: 10px;
        overflow: hidden; margin-bottom: 1.5rem;
    }
    @media(max-width:900px){ .kpi-grid { grid-template-columns: 1fr 1fr; } }
    @media(max-width:560px){ .kpi-grid { grid-template-columns: 1fr; } }

    .kpi-cell {
        background: var(--bg1); padding: 1.75rem 2rem;
        position: relative; overflow: hidden;
        transition: background .2s;
    }
    .kpi-cell:hover { background: var(--bg2); }
    .kpi-cell::after {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    }
    .kpi-cell.c-cyan::after   { background: var(--cyan);   box-shadow: 0 0 12px var(--cyan); }
    .kpi-cell.c-green::after  { background: var(--green);  box-shadow: 0 0 12px var(--green); }
    .kpi-cell.c-amber::after  { background: var(--amber);  box-shadow: 0 0 12px var(--amber); }
    .kpi-cell.c-purple::after { background: var(--purple); box-shadow: 0 0 12px var(--purple); }

    .kpi-label {
        font-family: var(--mono); font-size: .62rem; letter-spacing: .14em;
        text-transform: uppercase; color: var(--text3); margin-bottom: .75rem;
    }
    .kpi-value {
        font-family: var(--mono); font-size: clamp(2rem,4vw,2.8rem);
        font-weight: 300; line-height: 1; margin-bottom: .5rem;
    }
    .kpi-value.cyan   { color: var(--cyan); }
    .kpi-value.green  { color: var(--green); }
    .kpi-value.amber  { color: var(--amber); }
    .kpi-value.purple { color: var(--purple); }

    .kpi-pill {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .2rem .55rem; border-radius: 3px;
        font-family: var(--mono); font-size: .6rem;
        font-weight: 500; letter-spacing: .06em;
    }
    .kpi-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .p-green  { background:rgba(52,211,153,.1);  color:var(--green); }
    .p-cyan   { background:rgba(34,211,238,.1);  color:var(--cyan);  }
    .p-amber  { background:rgba(251,191,36,.1);  color:var(--amber); }
    .p-purple { background:rgba(167,139,250,.1); color:var(--purple);}

    /* ── Chart panel ─────────────────────────────────────── */
    .chart-panel {
        background: var(--bg1); border: 1px solid var(--line);
        border-radius: 10px; overflow: hidden;
    }
    .chart-panel-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.25rem 1.75rem; border-bottom: 1px solid var(--line);
        flex-wrap: wrap; gap: .75rem;
    }
    .chart-panel-title {
        font-family: var(--display); font-size: 1rem; font-weight: 600;
        color: var(--text1); letter-spacing: -.01em;
    }
    .chart-panel-sub { font-size: .72rem; color: var(--text3); margin-top: 2px; }
    .chart-loading {
        display: flex; align-items: center; gap: .4rem;
        font-family: var(--mono); font-size: .68rem; color: var(--cyan);
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .spin-icon { animation: spin .8s linear infinite; width: 14px; height: 14px; }

    .chart-body { padding: 1.5rem 1.75rem; }

    /* Grid lines overlay for chart area */
    .chart-wrap { position: relative; }
    .chart-wrap canvas { display: block; }

    /* Empty state */
    .chart-empty {
        text-align: center; padding: 4rem 2rem;
        font-family: var(--mono); font-size: .72rem; color: var(--text3);
    }
    .chart-empty svg { width: 40px; height: 40px; margin: 0 auto .75rem; opacity: .2; }
</style>
@endpush

<div class="an-wrap"
     x-data="{}"
     x-init="initCharts(@js($this->getChartData()))">

<div class="an-inner">

    {{-- ── Header ── --}}
    <div class="page-header au">
        <div>
            <div class="page-eyebrow">Super Admin · Analytics</div>
            <h1 class="page-title">Platform Analytics</h1>
        </div>
        <div class="date-row">
            <input type="date" wire:model.live="startDate" class="date-input" value="{{ $startDate }}">
            <span class="date-sep">→</span>
            <input type="date" wire:model.live="endDate" class="date-input" value="{{ $endDate }}">
        </div>
    </div>

    {{-- ── KPI Cards ── --}}
    @php $stats = $this->getStats(); @endphp
    <div class="kpi-grid au d1">

        <div class="kpi-cell c-cyan">
            <div class="kpi-label">Total tenants</div>
            <div class="kpi-value cyan">{{ $stats['total_tenants'] }}</div>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                <span class="kpi-pill p-green">{{ $stats['active_tenants'] }} active</span>
                <span class="kpi-pill p-amber">{{ $stats['pending_tenants'] }} pending</span>
            </div>
        </div>

        <div class="kpi-cell c-green">
            <div class="kpi-label">New this month</div>
            <div class="kpi-value green">{{ $stats['new_this_month'] }}</div>
            <div><span class="kpi-pill p-green">New registrations</span></div>
        </div>

        <div class="kpi-cell c-purple">
            <div class="kpi-label">Platform users</div>
            <div class="kpi-value purple">{{ $stats['total_users'] }}</div>
            <div><span class="kpi-pill p-cyan">{{ $stats['active_users'] }} active</span></div>
        </div>

    </div>

    {{-- ── Tenant Growth Chart ── --}}
    <div class="chart-panel au d2">
        <div class="chart-panel-head">
            <div>
                <div class="chart-panel-title">Tenant growth</div>
                <div class="chart-panel-sub">New businesses registered per month</div>
            </div>
            <div wire:loading wire:target="startDate,endDate" class="chart-loading">
                <svg class="spin-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/>
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" opacity=".75"/>
                </svg>
                Updating
            </div>
        </div>
        <div class="chart-body">
            <div class="chart-wrap" style="height:280px;">
                <canvas id="trendChart" style="height:280px!important"></canvas>
                <div id="emptyState" class="chart-empty hidden">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    No data for this period.
                </div>
            </div>
        </div>
    </div>

</div>
</div>

@script
<script>
    let trendChart = null;

    window.renderChart = function(data) {
        const canvas = document.getElementById('trendChart');
        const empty  = document.getElementById('emptyState');

        const hasData = data && data.labels && data.labels.length > 0 && data.tenants.reduce((a,b) => a+b, 0) > 0;

        if (!hasData) {
            if (canvas)  canvas.style.display = 'none';
            if (empty)   empty.classList.remove('hidden');
            if (trendChart) { trendChart.destroy(); trendChart = null; }
            return;
        }

        canvas.style.display = 'block';
        empty.classList.add('hidden');

        if (trendChart) { trendChart.destroy(); trendChart = null; }

        const ctx = canvas.getContext('2d');

        /* Gradient fill under the line */
        const grad = ctx.createLinearGradient(0, 0, 0, 280);
        grad.addColorStop(0, 'rgba(34,211,238,0.18)');
        grad.addColorStop(1, 'rgba(34,211,238,0.00)');

        trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'New Tenants',
                    data: data.tenants,
                    borderColor: '#22D3EE',
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#07090F',
                    pointBorderColor: '#22D3EE',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#22D3EE',
                    borderWidth: 1.5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#131820',
                        borderColor: 'rgba(34,211,238,0.25)',
                        borderWidth: 1,
                        titleColor: '#8892A4',
                        bodyColor: '#E2E8F0',
                        titleFont: { family: "'JetBrains Mono', monospace", size: 11 },
                        bodyFont: { family: "'JetBrains Mono', monospace", size: 13 },
                        padding: 10,
                        callbacks: {
                            label: (ctx) => '  ' + ctx.raw + ' tenants'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                        ticks: {
                            color: '#4A5568',
                            font: { family: "'JetBrains Mono', monospace", size: 11 },
                            stepSize: 1
                        },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#4A5568',
                            font: { family: "'JetBrains Mono', monospace", size: 11 },
                            maxTicksLimit: 12, autoSkip: true
                        },
                        border: { display: false }
                    }
                }
            }
        });
    };

    function initCharts(initialData) {
        if (typeof Chart === 'undefined') {
            setTimeout(() => initCharts(initialData), 100);
            return;
        }
        renderChart(initialData);
        Livewire.on('refreshChart', (payload) => {
            renderChart(payload[0] || payload);
        });
    }

    window.initCharts = initCharts;
</script>
@endscript