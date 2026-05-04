<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Booking;
use Carbon\Carbon;
?>

<div class="min-h-screen bg-white dark:bg-black py-12 px-4 transition-colors duration-300">
    <div class="max-w-7xl mx-auto">
        
        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">My Bookings</h1>
            <p class="text-gray-600 dark:text-gray-300 mt-1">Manage your reservations and track your travels</p>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 rounded-xl text-green-800 dark:text-green-200">
                <?php echo e(session('message')); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded-xl text-red-800 dark:text-red-200">
                <?php echo e(session('error')); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="bg-white/10 dark:bg-gray-900/40 backdrop-blur-sm rounded-2xl p-5 mb-8 border border-gray-200 dark:border-gray-800">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by booking reference..."
                           class="w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500 px-4 py-2.5">
                </div>
                <div>
                    <select wire:model.live="statusFilter"
                            class="rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500 px-4 py-2.5">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="checked-in">Checked In</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->count() > 0): ?>
            <div class="space-y-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm hover:shadow-md transition overflow-hidden">
                        <div class="p-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <span class="text-sm font-mono text-gray-500 dark:text-gray-400">#<?php echo e($booking->booking_reference); ?></span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border <?php echo e($this->getStatusBadgeClass($booking->status)); ?>">
                                            <?php echo e(ucfirst($booking->status)); ?>

                                        </span>
                                    </div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?php echo e($booking->items->first()->property->name ?? 'Property'); ?></h2>
                                    <p class="text-sm text-gray-600 dark:text-gray-300"><?php echo e($booking->tenant->name ?? 'Business'); ?></p>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-gray-900 dark:text-white">₱<?php echo e(number_format($booking->total_amount, 2)); ?></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($booking->created_at->format('M d, Y')); ?></div>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <svg class="w-5 h-5 text-blue-500 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>Check‑in: <strong><?php echo e(\Carbon\Carbon::parse($booking->check_in)->format('M d, Y')); ?></strong></span>
                                </div>
                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <svg class="w-5 h-5 text-blue-500 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>Check‑out: <strong><?php echo e(\Carbon\Carbon::parse($booking->check_out)->format('M d, Y')); ?></strong></span>
                                </div>
                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <svg class="w-5 h-5 text-blue-500 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span><?php echo e(\Carbon\Carbon::parse($booking->check_in)->diffInDays($booking->check_out)); ?> night(s)</span>
                                </div>
                            </div>

                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->services->count()): ?>
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Extra services: <?php echo e($booking->services->count()); ?> item(s)</p>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            
                            <div class="mt-5 flex flex-wrap gap-3 justify-end">
                                <a href="<?php echo e(route('tenant.show', $booking->tenant->slug)); ?>" wire:navigate
                                   class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-red-500 hover:underline">
                                    View business →
                                </a>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->status === 'pending'): ?>
                                    <button wire:click="cancelBooking(<?php echo e($booking->id); ?>)"
                                            wire:confirm="Are you sure you want to cancel this booking?"
                                            class="px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 border border-red-300 dark:border-red-700 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                        Cancel booking
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-16 bg-white/10 dark:bg-gray-900/20 rounded-2xl border border-gray-200 dark:border-gray-800">
                <svg class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                <h3 class="mt-4 text-xl font-semibold text-gray-900 dark:text-white">No bookings yet</h3>
                <p class="mt-2 text-gray-500 dark:text-gray-400">Start exploring destinations and book your next adventure.</p>
                <a href="<?php echo e(route('home')); ?>" wire:navigate class="mt-4 inline-flex items-center gap-2 px-5 py-2 bg-blue-600 dark:bg-red-600 text-white rounded-full hover:bg-blue-700 dark:hover:bg-red-700 transition">
                    Explore now
                </a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/bba5cc3c.blade.php ENDPATH**/ ?>