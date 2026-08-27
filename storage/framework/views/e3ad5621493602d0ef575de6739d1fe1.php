<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Employee;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
?>




<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-8"
     wire:poll.60s>

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                <?php echo e(Auth::user()->tenant->name ?? 'Dashboard'); ?>

            </h1>
        </div>
        <div class="flex gap-2">
            <a href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-lg shadow-primary-500/20 transition-all duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Booking
            </a>
            <a href="<?php echo e(route('tenant.analytics.index')); ?>" wire:navigate
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                View Analytics
            </a>
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
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Revenue</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱<?php echo e(number_format($s['revenue'], 2)); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bookings</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['total_bookings']); ?></p>
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
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Arrivals</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['arrivals_today']); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Departures</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['departures_today']); ?></p>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="font-bold text-gray-900 dark:text-white">Recent Bookings</h2>
            <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th class="px-6 py-4 text-left">Ref</th>
                        <th class="px-6 py-4 text-left">Guest</th>
                        <th class="px-6 py-4 text-left">Check‑in</th>
                        <th class="px-6 py-4 text-left">Amount</th>
                        <th class="px-6 py-4 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentBookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs"><?php echo e($b->booking_reference); ?></td>
                            <td class="px-6 py-4"><?php echo e($b->user->name ?? 'N/A'); ?></td>
                            <td class="px-6 py-4"><?php echo e($b->check_in->format('M d, Y')); ?></td>
                            <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">₱<?php echo e(number_format($b->total_amount, 2)); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php echo e($b->status === 'pending' ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' : ''); ?>

                                    <?php echo e($b->status === 'confirmed' ? 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30' : ''); ?>

                                    <?php echo e($b->status === 'completed' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30' : ''); ?>">
                                    <?php echo e(ucfirst($b->status)); ?>

                                </span>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No bookings yet.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="font-bold text-gray-900 dark:text-white mb-4">Upcoming Arrivals</h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingArrivals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo e($b->user->name ?? 'Guest'); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($b->check_in->format('M d, Y')); ?> · <?php echo e($b->booking_reference); ?></p>
                        </div>
                        <span class="text-xs text-green-600 dark:text-green-400 font-medium">Confirmed</span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-3">No arrivals soon.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="font-bold text-gray-900 dark:text-white mb-4">Recent Payments</h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentPayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">₱<?php echo e(number_format($p->amount, 2)); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($p->paid_at?->format('M d') ?? '—'); ?> · <?php echo e($p->reference_number ?? '—'); ?></p>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-3">No payments yet.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm">
        <h3 class="font-bold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="<?php echo e(route('tenant.services.create')); ?>" wire:navigate
               class="flex items-center justify-center gap-2 p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 text-primary-600 dark:text-primary-400 text-sm font-medium transition-colors border border-primary-200 dark:border-primary-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Add Service
            </a>
            <a href="<?php echo e(route('tenant.employees.create')); ?>" wire:navigate
               class="flex items-center justify-center gap-2 p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 text-primary-600 dark:text-primary-400 text-sm font-medium transition-colors border border-primary-200 dark:border-primary-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Add Employee
            </a>
            <a href="<?php echo e(route('tenant.payments.index')); ?>" wire:navigate
               class="flex items-center justify-center gap-2 p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 text-primary-600 dark:text-primary-400 text-sm font-medium transition-colors border border-primary-200 dark:border-primary-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Payments
            </a>
            <a href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate
               class="flex items-center justify-center gap-2 p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 text-primary-600 dark:text-primary-400 text-sm font-medium transition-colors border border-primary-200 dark:border-primary-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/f9545953.blade.php ENDPATH**/ ?>