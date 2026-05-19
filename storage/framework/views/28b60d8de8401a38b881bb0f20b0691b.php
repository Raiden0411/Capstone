<?php
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Booking History</h1>
            <p class="text-white/60 mt-1">Completed and cancelled stays.</p>
        </div>
        <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate class="text-white/50 hover:text-white font-medium transition-colors">
            &larr; Active Bookings
        </a>
    </div>

    <div class="glass-card !rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-white/10">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-white/50 uppercase">Ref</th>
                        <th class="px-6 py-4 text-xs font-semibold text-white/50 uppercase">Customer</th>
                        <th class="px-6 py-4 text-xs font-semibold text-white/50 uppercase">Check In/Out</th>
                        <th class="px-6 py-4 text-xs font-semibold text-white/50 uppercase">Total</th>
                        <th class="px-6 py-4 text-xs font-semibold text-white/50 uppercase">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-white/50 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-white/80">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr class="hover:bg-white/5 transition">
                            <td class="px-6 py-4 font-mono text-sm"><?php echo e($booking->booking_reference); ?></td>
                            <td class="px-6 py-4 text-sm"><?php echo e($booking->customer->name ?? 'N/A'); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <?php echo e($booking->check_in?->format('M d, Y') ?? '—'); ?> → <?php echo e($booking->check_out?->format('M d, Y') ?? '—'); ?>

                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-white">₱<?php echo e(number_format($booking->total_amount, 2)); ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    <?php echo e($booking->status === 'completed' ? 'bg-green-500/20 text-green-300' : 'bg-red-500/20 text-red-300'); ?>">
                                    <?php echo e(ucfirst($booking->status)); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate class="p-1.5 text-white/40 hover:text-white hover:bg-white/10 rounded-lg transition" title="View Details">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-white/40">No history yet.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->hasPages()): ?>
            <div class="px-6 py-4 border-t border-white/10">
                <?php echo e($this->bookings->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/28e29ed4.blade.php ENDPATH**/ ?>