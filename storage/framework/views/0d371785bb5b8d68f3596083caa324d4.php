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
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6"
     x-data="{}"
     x-init="Livewire.on('refreshChart', (data) => { if (window.renderBarChart) window.renderBarChart(data[0] || data); })">

    
    <?php $s = $this->stats; ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Revenue', '₱'.number_format($s['revenue'],2), 'emerald'],
            ['Bookings', $s['total_bookings'], 'blue'],
            ['Guests', $s['total_guests'], 'purple'],
            ['Occupancy', $s['occupancy_rate'].'%', 'amber'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => [$label, $value, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="glass-card !rounded-2xl p-5 flex flex-col justify-between h-36">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-xl bg-<?php echo e($color); ?>-500/20 flex items-center justify-center text-<?php echo e($color); ?>-300">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($index == 0): ?><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?php elseif($index == 1): ?><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?php elseif($index == 2): ?><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <?php else: ?><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <p class="text-sm text-white/50 font-medium"><?php echo e($label); ?></p>
                <div class="flex justify-between items-end mt-1">
                    <h3 class="text-2xl font-bold text-white"><?php echo e($value); ?></h3>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 glass-card !rounded-2xl p-6 flex flex-col">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white mb-1">Revenue Stream Summary</h2>
                    <p class="text-sm text-white/60">Track your earnings across the selected period.</p>
                </div>
                <div class="flex bg-white/5 p-1 rounded-lg border border-white/10 text-sm font-medium">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['today','yesterday','last-7','last-30','this-month']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $range): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button wire:click="$set('dateRange', '<?php echo e($range); ?>')"
                                class="px-3 py-1.5 rounded-md transition-all
                                       <?php echo e($dateRange === $range
                                          ? 'bg-brand-600 text-white shadow'
                                          : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>">
                            <?php echo e(match($range) { 'today'=>'Today', 'yesterday'=>'Yest.', 'last-7'=>'7D', 'last-30'=>'30D', 'this-month'=>'Month', default=>ucfirst($range) }); ?>

                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
            <div class="w-full flex-grow relative min-h-[300px]">
                <canvas id="revenueChart" class="w-full h-full"></canvas>
                <div id="emptyState" class="hidden absolute inset-0 flex items-center justify-center">
                    <p class="text-white/40 text-sm">No revenue data for this period.</p>
                </div>
            </div>
        </div>

        
        <div class="glass-card !rounded-2xl p-6 flex flex-col relative">
            <button class="absolute top-6 right-5 text-white/40 hover:text-white transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
            </button>
            <h2 class="text-xl font-semibold text-white mb-1">Revenue Breakdown</h2>
            <p class="text-sm text-white/60 mb-6">Service share of total revenue</p>

            <?php $breakdowns = $this->revenueBreakdown; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($breakdowns)): ?>
                <h3 class="text-4xl font-bold text-white mb-2">₱<?php echo e(number_format(collect($breakdowns)->sum('total'), 2)); ?></h3>
                <div class="flex items-center gap-2 mb-8 text-sm">
                    <span class="text-brand-400 font-semibold flex items-center">
                        100%
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                    </span>
                </div>

                <div class="w-full h-4 flex rounded-md overflow-hidden mb-8 gap-1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $breakdowns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div style="width: <?php echo e($b['share']); ?>%" class="bg-<?php echo e(['#22c55e','#06b6d4','#facc15','#a855f7','#f43f5e'][$loop->index] ?? '#64748b'); ?> h-full"></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                <div class="space-y-5 flex-grow flex flex-col justify-end">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $breakdowns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-2.5 h-2.5 rounded-full bg-<?php echo e(['#22c55e','#06b6d4','#facc15','#a855f7','#f43f5e'][$loop->index] ?? '#64748b'); ?>"></div>
                                <div>
                                    <p class="text-sm text-white/70 mb-1"><?php echo e($b['name']); ?></p>
                                    <p class="text-lg font-semibold text-white">₱<?php echo e(number_format($b['total'], 2)); ?></p>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 bg-<?php echo e(['green','cyan','yellow','purple','red'][$loop->index] ?? 'gray'); ?>-500/10 text-<?php echo e(['green','cyan','yellow','purple','red'][$loop->index] ?? 'gray'); ?>-300 text-xs font-bold rounded-md border border-<?php echo e(['green','cyan','yellow','purple','red'][$loop->index] ?? 'gray'); ?>-500/20">
                                <?php echo e($b['share']); ?>%
                            </span>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-white/40 py-8">No service data for this period.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
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

            <?php $statuses = $this->bookingStatusBreakdown; ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    ['Pending', $statuses['pending'] ?? 0, 'amber'],
                    ['Confirmed', $statuses['confirmed'] ?? 0, 'blue'],
                    ['Completed', $statuses['completed'] ?? 0, 'emerald'],
                    ['Cancelled', $statuses['cancelled'] ?? 0, 'red'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$title, $count, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="flex gap-3 items-start">
                        <div class="p-2.5 rounded-xl bg-<?php echo e($color); ?>-500/20 border border-<?php echo e($color); ?>-500/30 text-<?php echo e($color); ?>-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm text-white/50 mb-1"><?php echo e($title); ?></p>
                            <p class="text-xl font-bold text-white"><?php echo e($count); ?></p>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>

        
        <div class="glass-card !rounded-2xl p-6 relative">
            <button class="absolute top-6 right-5 text-white/40 hover:text-white transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
            </button>
            <h2 class="text-xl font-semibold text-white mb-1">Cashflow Overview</h2>
            <p class="text-sm text-white/60 mb-6">Monthly income snapshot</p>

            <?php
                $income = $s['revenue'];
                $net = $income;
            ?>
            <h3 class="text-4xl font-bold text-white mb-2">₱<?php echo e(number_format($net, 2)); ?></h3>
            <p class="text-sm text-white/40 mb-6">Net Revenue (Income - Expenses)</p>

            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-white/60">Total Revenue</span>
                    <span class="text-sm font-semibold text-white">₱<?php echo e(number_format($income, 2)); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-white/60">Operating Expenses</span>
                    <span class="text-sm font-semibold text-white">—</span>
                </div>
                <div class="h-px bg-white/10"></div>
                <div class="flex justify-between items-center font-bold">
                    <span class="text-sm text-white">Net Cash Position</span>
                    <span class="text-sm text-white">₱<?php echo e(number_format($net, 2)); ?></span>
                </div>
            </div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <div class="glass-card !rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-white">
                <span class="w-2 h-2 rounded-full bg-brand-400"></span>
                Today's Arrivals (<?php echo e(now()->format('M d')); ?>)
            </h2>
            <div class="divide-y divide-white/10">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingActivity['arrivals']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-white"><?php echo e($b->customer->name ?? 'Guest'); ?></p>
                            <p class="text-xs text-white/50"><?php echo e($b->check_in->format('M d, Y')); ?></p>
                        </div>
                        <span class="text-xs text-brand-400 font-medium">Arriving</span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="py-6 text-center text-white/40 text-sm">No arrivals today.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        
        <div class="glass-card !rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2 text-white">
                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                Today's Departures (<?php echo e(now()->format('M d')); ?>)
            </h2>
            <div class="divide-y divide-white/10">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingActivity['departures']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-white"><?php echo e($b->customer->name ?? 'Guest'); ?></p>
                            <p class="text-xs text-white/50"><?php echo e($b->check_out->format('M d, Y')); ?></p>
                        </div>
                        <span class="text-xs text-rose-400 font-medium">Departing</span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="py-6 text-center text-white/40 text-sm">No departures today.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <?php
        $__scriptKey = '2474773489-0';
        ob_start();
    ?>
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
        const initialData = <?php echo \Illuminate\Support\Js::from($this->getRevenueTrend())->toHtml() ?>;
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
    <?php
        $__output = ob_get_clean();

        \Livewire\store($this)->push('scripts', $__output, $__scriptKey)
    ?><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/e4be6405.blade.php ENDPATH**/ ?>