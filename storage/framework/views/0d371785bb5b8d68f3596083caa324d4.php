<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Service;
use App\Models\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-8">

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Analytics</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Performance overview for your business.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <select wire:model.live="dateRange" class="rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last-7">Last 7 days</option>
                <option value="last-30">Last 30 days</option>
                <option value="this-month">This Month</option>
                <option value="last-month">Last Month</option>
                <option value="custom">Custom</option>
            </select>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dateRange === 'custom'): ?>
                <input type="date" wire:model.live="customStart" class="rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                <span class="text-gray-500 dark:text-slate-400">–</span>
                <input type="date" wire:model.live="customEnd" class="rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Revenue</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">₱<?php echo e(number_format($this->stats['revenue'], 2)); ?></p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Bookings</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo e($this->stats['total_bookings']); ?></p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Guests</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo e($this->stats['total_guests']); ?></p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Occupancy</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo e($this->stats['occupancy_rate']); ?>%</p>
            <p class="text-xs text-gray-500 dark:text-slate-400">Avg. Booking: ₱<?php echo e(number_format($this->stats['avg_booking_value'], 2)); ?></p>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Revenue Trend</h3>
            <canvas id="revenueChart" height="200"></canvas>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Services</h3>
            <ul class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->topServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="flex justify-between items-center text-sm">
                        <span class="font-medium text-gray-700 dark:text-slate-300"><?php echo e($service->name); ?></span>
                        <span class="text-gray-500 dark:text-slate-400">x<?php echo e($service->count); ?> · ₱<?php echo e(number_format($service->revenue, 2)); ?></span>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <li class="text-gray-500 dark:text-slate-400 text-sm">No services used in this period.</li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
        </div>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-3">Today's Arrivals (<?php echo e(now()->format('M d')); ?>)</h2>
            <ul class="divide-y divide-gray-200 dark:divide-slate-700/30">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingActivity['arrivals']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="py-2 flex justify-between">
                        <span class="text-gray-700 dark:text-slate-300"><?php echo e($b->customer->name); ?></span>
                        <span class="text-gray-500 dark:text-slate-400 text-sm"><?php echo e($b->check_in->format('M d')); ?></span>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <li class="text-gray-500 dark:text-slate-400 py-2">No arrivals today.</li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-3">Today's Departures (<?php echo e(now()->format('M d')); ?>)</h2>
            <ul class="divide-y divide-gray-200 dark:divide-slate-700/30">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingActivity['departures']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="py-2 flex justify-between">
                        <span class="text-gray-700 dark:text-slate-300"><?php echo e($b->customer->name); ?></span>
                        <span class="text-gray-500 dark:text-slate-400 text-sm"><?php echo e($b->check_out->format('M d')); ?></span>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <li class="text-gray-500 dark:text-slate-400 py-2">No departures today.</li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/e4be6405.blade.php ENDPATH**/ ?>