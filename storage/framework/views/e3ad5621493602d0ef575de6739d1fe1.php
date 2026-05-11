<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-10">
    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
        <div>
            <h1 class="font-display text-3xl sm:text-4xl font-bold tracking-tight text-white">
                <?php echo e(Auth::user()->tenant->name ?? 'Dashboard'); ?>

            </h1>
            <p class="text-base text-white/60 mt-1">
                Business Overview · <?php echo e(now()->format('F j, Y')); ?>

            </p>
        </div>
        <div class="flex gap-3">
            <a href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition-all duration-200 hover:scale-105">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Booking
            </a>
            <a href="<?php echo e(route('tenant.properties.index')); ?>" wire:navigate
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-white/20 text-white/80 hover:bg-white/10 text-sm font-semibold transition-all duration-200">
                Manage Properties
            </a>
        </div>
    </div>

    
    <?php $s = $this->stats; ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <div class="glass-card !rounded-2xl p-6">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-widest text-white/50">Bookings</span>
                <div class="p-2 rounded-xl bg-blue-500/20 text-blue-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <p class="text-4xl font-black text-white"><?php echo e(number_format($s['total_bookings'])); ?></p>
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2">
                <span class="text-xs text-amber-400 font-medium"><?php echo e($s['pending_bookings']); ?> pending</span>
                <span class="text-xs text-emerald-400 font-medium"><?php echo e($s['confirmed_bookings']); ?> confirmed</span>
                <span class="text-xs text-blue-400 font-medium"><?php echo e($s['completed_bookings']); ?> completed</span>
            </div>
        </div>

        
        <div class="glass-card !rounded-2xl p-6">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-widest text-white/50">Revenue</span>
                <div class="p-2 rounded-xl bg-emerald-500/20 text-emerald-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-4xl font-black text-white">₱<?php echo e(number_format($s['revenue_this_month'], 2)); ?></p>
            <div class="flex items-center gap-2 mt-2">
                <span class="inline-flex items-center text-xs font-semibold <?php echo e($this->revenueTrend >= 0 ? 'text-emerald-400' : 'text-red-400'); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->revenueTrend >= 0): ?>
                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    <?php else: ?>
                        <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"/></svg>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php echo e(abs($this->revenueTrend)); ?>%
                </span>
                <span class="text-xs text-white/40">vs last month</span>
            </div>
        </div>

        
        <div class="glass-card !rounded-2xl p-6">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-widest text-white/50">Today</span>
                <div class="p-2 rounded-xl bg-amber-500/20 text-amber-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-4xl font-black text-white"><?php echo e($s['arrivals_today']); ?> / <?php echo e($s['departures_today']); ?></p>
            <p class="text-xs text-white/40 mt-2">Arrivals · Departures</p>
            <p class="text-sm font-medium text-white/80 mt-1"><?php echo e($s['occupied_properties']); ?> properties occupied</p>
        </div>

        
        <div class="glass-card !rounded-2xl p-6">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase tracking-widest text-white/50">Customers</span>
                <div class="p-2 rounded-xl bg-purple-500/20 text-purple-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
            </div>
            <p class="text-4xl font-black text-white"><?php echo e(number_format($s['total_customers'])); ?></p>
            <p class="text-xs text-white/40 mt-2">Registered guests</p>
        </div>
    </div>

    
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Properties', $s['total_properties'], 'indigo'],
            ['Services', $s['total_services'], 'rose'],
            ['Employees', $s['total_employees'], 'cyan'],
            ['Unpaid Payments', Payment::where('payment_status', '!=', 'paid')->count(), 'amber'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="glass-card !rounded-xl p-4 flex items-center gap-3">
                <div class="p-2 rounded-lg bg-<?php echo e($item[2]); ?>-500/20 text-<?php echo e($item[2]); ?>-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5v14"/></svg>
                </div>
                <div>
                    <p class="text-xs text-white/40"><?php echo e($item[0]); ?></p>
                    <p class="text-lg font-bold text-white"><?php echo e($item[1]); ?></p>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 glass-card !rounded-2xl overflow-hidden !p-0">
            <div class="px-6 py-5 border-b border-white/10 flex justify-between items-center">
                <h2 class="font-bold text-white">Recent Bookings</h2>
                <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate class="text-sm font-semibold text-brand-400 hover:underline">View all →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-white/40 border-b border-white/5">
                            <th class="px-6 py-4 text-left">Ref</th>
                            <th class="px-6 py-4 text-left">Customer</th>
                            <th class="px-6 py-4 text-left">Check‑in</th>
                            <th class="px-6 py-4 text-left">Amount</th>
                            <th class="px-6 py-4 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-white/80">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentBookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs"><?php echo e($b->booking_reference); ?></td>
                                <td class="px-6 py-4"><?php echo e($b->customer->name ?? 'N/A'); ?></td>
                                <td class="px-6 py-4"><?php echo e($b->check_in->format('M d, Y')); ?></td>
                                <td class="px-6 py-4 font-semibold text-white">₱<?php echo e(number_format($b->total_amount, 2)); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php echo e($b->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : ''); ?>

                                        <?php echo e($b->status === 'confirmed' ? 'bg-blue-500/20 text-blue-300' : ''); ?>

                                        <?php echo e($b->status === 'completed' ? 'bg-emerald-500/20 text-emerald-300' : ''); ?>">
                                        <?php echo e(ucfirst($b->status)); ?>

                                    </span>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="5" class="px-6 py-8 text-center text-white/40">No bookings yet.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="space-y-6">
            
            <div class="glass-card !rounded-2xl p-5">
                <h3 class="font-bold text-white mb-4">Upcoming Arrivals</h3>
                <div class="divide-y divide-white/10">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->upcomingArrivals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="py-3 flex justify-between items-center">
                            <div>
                                <p class="text-sm font-semibold text-white"><?php echo e($b->customer->name ?? 'Guest'); ?></p>
                                <p class="text-xs text-white/50"><?php echo e($b->check_in->format('M d, Y')); ?> · <?php echo e($b->booking_reference); ?></p>
                            </div>
                            <span class="text-xs text-emerald-400 font-medium">Confirmed</span>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="text-sm text-white/40 py-3">No arrivals soon.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <div class="glass-card !rounded-2xl p-5">
                <h3 class="font-bold text-white mb-4">Recent Payments</h3>
                <div class="divide-y divide-white/10">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentPayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="py-3 flex justify-between items-center">
                            <div>
                                <p class="text-sm font-semibold text-white">₱<?php echo e(number_format($p->amount, 2)); ?></p>
                                <p class="text-xs text-white/50"><?php echo e($p->paid_at?->format('M d') ?? '—'); ?> · <?php echo e($p->reference_number ?? '—'); ?></p>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="text-sm text-white/40 py-3">No payments yet.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <div class="glass-card !rounded-2xl p-5">
                <h3 class="font-bold text-white mb-4">Quick Actions</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="<?php echo e(route('tenant.customers.create')); ?>" wire:navigate class="flex items-center gap-2 p-3 rounded-xl bg-brand-500/10 hover:bg-brand-500/20 text-brand-300 text-sm font-medium transition-colors border border-brand-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Add Customer
                    </a>
                    <a href="<?php echo e(route('tenant.services.create')); ?>" wire:navigate class="flex items-center gap-2 p-3 rounded-xl bg-brand-500/10 hover:bg-brand-500/20 text-brand-300 text-sm font-medium transition-colors border border-brand-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Add Service
                    </a>
                    <a href="<?php echo e(route('tenant.employees.create')); ?>" wire:navigate class="flex items-center gap-2 p-3 rounded-xl bg-brand-500/10 hover:bg-brand-500/20 text-brand-300 text-sm font-medium transition-colors border border-brand-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        Add Employee
                    </a>
                    <a href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate class="flex items-center gap-2 p-3 rounded-xl bg-brand-500/10 hover:bg-brand-500/20 text-brand-300 text-sm font-medium transition-colors border border-brand-500/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/f9545953.blade.php ENDPATH**/ ?>