<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
?>

<?php $__env->startPush('styles'); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=JetBrains+Mono:wght@300;400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

<?php $__env->stopPush(); ?>

<div class="an-wrap"
     x-data="{}"
     x-init="initCharts(<?php echo \Illuminate\Support\Js::from($this->getChartData())->toHtml() ?>)">

<div class="an-inner">

    
    <div class="page-header au">
        <div>
            <div class="page-eyebrow">Super Admin · Analytics</div>
            <h1 class="page-title">Platform Analytics</h1>
        </div>
        <div class="date-row">
            <input type="date" wire:model.live="startDate" class="date-input" value="<?php echo e($startDate); ?>">
            <span class="date-sep">→</span>
            <input type="date" wire:model.live="endDate" class="date-input" value="<?php echo e($endDate); ?>">
        </div>
    </div>

    
    <?php $stats = $this->getStats(); ?>
    <div class="kpi-grid au d1">

        <div class="kpi-cell c-cyan">
            <div class="kpi-label">Total tenants</div>
            <div class="kpi-value cyan"><?php echo e($stats['total_tenants']); ?></div>
            <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                <span class="kpi-pill p-green"><?php echo e($stats['active_tenants']); ?> active</span>
                <span class="kpi-pill p-amber"><?php echo e($stats['pending_tenants']); ?> pending</span>
            </div>
        </div>

        <div class="kpi-cell c-green">
            <div class="kpi-label">New this month</div>
            <div class="kpi-value green"><?php echo e($stats['new_this_month']); ?></div>
            <div><span class="kpi-pill p-green">New registrations</span></div>
        </div>

        <div class="kpi-cell c-purple">
            <div class="kpi-label">Platform users</div>
            <div class="kpi-value purple"><?php echo e($stats['total_users']); ?></div>
            <div><span class="kpi-pill p-cyan"><?php echo e($stats['active_users']); ?> active</span></div>
        </div>

    </div>

    
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

    <?php
        $__scriptKey = '3662658116-0';
        ob_start();
    ?>
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
    <?php
        $__output = ob_get_clean();

        \Livewire\store($this)->push('scripts', $__output, $__scriptKey)
    ?><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/f6a90942.blade.php ENDPATH**/ ?>