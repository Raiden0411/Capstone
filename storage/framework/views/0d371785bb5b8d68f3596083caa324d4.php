<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Service;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
?>




<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6"
     x-data="{}"
     x-init="
        if (typeof Chart !== 'undefined') {
            initAnalyticsCharts();
        } else {
            document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
        }
     "
     wire:poll.60s="refreshAnalytics">

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Analytics</h1>
        </div>
        <div class="flex gap-2">
            <button wire:click="exportCsv"
                    class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
            <button onclick="window.print()"
                    class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/></svg>
                Print
            </button>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last-7' => '7 Days',
                'last-30' => '30 Days',
                'this-month' => 'This Month',
                'last-month' => 'Last Month',
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <button wire:click="$set('dateRange', '<?php echo e($val); ?>')"
                        class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition focus-visible:ring-2 focus-visible:ring-primary-500/50
                               <?php echo e($dateRange === $val ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'); ?>">
                    <?php echo e($label); ?>

                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <button wire:click="$set('dateRange', 'custom')"
                    class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition focus-visible:ring-2 focus-visible:ring-primary-500/50
                           <?php echo e($dateRange === 'custom' ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'); ?>">
                Custom
            </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dateRange === 'custom'): ?>
                <div class="flex gap-2 items-center">
                    <input type="date" wire:model.live="customStart"
                           class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <span class="text-gray-500 dark:text-gray-400">to</span>
                    <input type="date" wire:model.live="customEnd"
                           class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <?php $s = $this->stats; ?>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Revenue</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱<?php echo e(number_format($s['revenue'], 2)); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bookings</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['total_bookings']); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Guests</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['total_guests']); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Occupancy</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['occupancy_rate']); ?>%</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Booking</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱<?php echo e(number_format($s['avg_booking_value'], 2)); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wider">Outstanding</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">₱<?php echo e(number_format($s['outstanding_balance'], 2)); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Repeat Guests</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['repeat_guest_rate']); ?>%</p>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Revenue Trend</h2>
        <div class="w-full h-80">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Booking Trend</h2>
            <div class="w-full h-80">
                <canvas id="bookingChart"></canvas>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Payment Methods</h2>
            <div class="w-full h-80 flex items-center justify-center">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Occupancy Trend</h2>
            <div class="w-full h-80">
                <canvas id="occupancyChart"></canvas>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Service Breakdown</h2>
            <?php $breakdowns = $this->revenueBreakdown; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($breakdowns)): ?>
                <div class="space-y-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $breakdowns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-700 dark:text-gray-300"><?php echo e($b['name']); ?></span>
                                <span class="text-gray-900 dark:text-white font-semibold">₱<?php echo e(number_format($b['total'], 2)); ?> (<?php echo e($b['share']); ?>%)</span>
                            </div>
                            <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full mt-1">
                                <div class="h-full bg-primary-600 rounded-full" style="width: <?php echo e($b['share']); ?>%"></div>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400 py-8">No service data for this period.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-primary-600"></span>
                Today's Arrivals (<?php echo e(now()->format('M d')); ?>)
            </h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingActivity['arrivals']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e($b->user->name ?? 'Guest'); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($b->check_in->format('M d, Y')); ?></p>
                        </div>
                        <span class="text-xs text-primary-600 dark:text-primary-400 font-medium">Arriving</span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">No arrivals today.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                Today's Departures (<?php echo e(now()->format('M d')); ?>)
            </h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingActivity['departures']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e($b->user->name ?? 'Guest'); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($b->check_out->format('M d, Y')); ?></p>
                        </div>
                        <span class="text-xs text-rose-500 dark:text-rose-400 font-medium">Departing</span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="py-6 text-center text-gray-500 dark:text-gray-400 text-sm">No departures today.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

        <?php
        $__scriptKey = '2474773489-0';
        ob_start();
    ?>
    <script>
        let revenueChart = null;
        let bookingChart = null;
        let paymentChart = null;
        let occupancyChart = null;

        let latestData = {
            revenue: <?php echo \Illuminate\Support\Js::from($this->getRevenueTrend())->toHtml() ?>,
            bookings: <?php echo \Illuminate\Support\Js::from($this->getBookingTrend())->toHtml() ?>,
            payment: <?php echo \Illuminate\Support\Js::from($this->getPaymentMethodBreakdown())->toHtml() ?>,
            occupancy: <?php echo \Illuminate\Support\Js::from($this->getOccupancyTrend())->toHtml() ?>,
        };

        function isDarkMode() {
            return document.documentElement.classList.contains('dark');
        }

        function getChartTheme() {
            return isDarkMode() ? {
                textColor: '#9ca3af',
                gridColor: 'rgba(255,255,255,0.06)',
                barColor: '#22c55e',
                lineBooking: '#06b6d4',
                lineOccupancy: '#facc15',
            } : {
                textColor: '#4b5563',
                gridColor: 'rgba(0,0,0,0.08)',
                barColor: '#10b981',
                lineBooking: '#0891b2',
                lineOccupancy: '#d97706',
            };
        }

        function resetCharts() {
            if (revenueChart) { revenueChart.destroy(); revenueChart = null; }
            if (bookingChart) { bookingChart.destroy(); bookingChart = null; }
            if (paymentChart) { paymentChart.destroy(); paymentChart = null; }
            if (occupancyChart) { occupancyChart.destroy(); occupancyChart = null; }
        }

        function renderBarChart(canvasId, chartInstance, labels, values, label, color) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            if (chartInstance) chartInstance.destroy();

            const theme = getChartTheme();
            const ctx = canvas.getContext('2d');
            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: values,
                        backgroundColor: color || theme.barColor,
                        borderRadius: 4,
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
                        borderColor: color || '#06b6d4',
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        fill: false,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: color || '#06b6d4',
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

        function renderDoughnutChart(canvasId, chartInstance, labels, values) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return null;

            if (chartInstance) chartInstance.destroy();

            const theme = getChartTheme();
            const ctx = canvas.getContext('2d');
            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#10b981', '#0891b2', '#d97706', '#7c3aed', '#ef4444'],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: theme.textColor } } }
                }
            });
        }

        function initAnalyticsCharts() {
            resetCharts();

            const theme = getChartTheme();

            revenueChart = renderBarChart(
                'revenueChart',
                revenueChart,
                Object.keys(latestData.revenue),
                Object.values(latestData.revenue),
                'Revenue',
                theme.barColor
            );

            bookingChart = renderLineChart(
                'bookingChart',
                bookingChart,
                Object.keys(latestData.bookings),
                Object.values(latestData.bookings),
                'Bookings',
                theme.lineBooking
            );

            paymentChart = renderDoughnutChart(
                'paymentChart',
                paymentChart,
                latestData.payment.map(p => p.method),
                latestData.payment.map(p => p.total)
            );

            occupancyChart = renderLineChart(
                'occupancyChart',
                occupancyChart,
                Object.keys(latestData.occupancy),
                Object.values(latestData.occupancy),
                'Occupancy %',
                theme.lineOccupancy
            );
        }

        initAnalyticsCharts();

        Livewire.hook('morphed', () => {
            initAnalyticsCharts();
        });

        Livewire.on('refreshCharts', (payload) => {
            const data = payload[0] || payload;
            if (data.revenue) latestData.revenue = data.revenue;
            if (data.bookings) latestData.bookings = data.bookings;
            if (data.payment) latestData.payment = data.payment;
            if (data.occupancy) latestData.occupancy = data.occupancy;

            initAnalyticsCharts();
        });

        const themeObserver = new MutationObserver(() => {
            initAnalyticsCharts();
        });
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
        <?php
        $__output = ob_get_clean();

        \Livewire\store($this)->push('scripts', $__output, $__scriptKey)
    ?>

</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/e4be6405.blade.php ENDPATH**/ ?>