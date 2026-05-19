

<?php $__env->startSection('content'); ?>
<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate class="text-sm text-white/50 hover:text-white transition-colors">
                &larr; Back to Bookings
            </a>
            <h1 class="text-2xl sm:text-3xl font-bold text-white mt-1">Booking #<?php echo e($booking->booking_reference); ?></h1>
            <p class="text-white/60 mt-1">
                <?php echo e($booking->customer->name); ?> • 
                <?php echo e($booking->check_in->format('M d, Y')); ?> – <?php echo e($booking->check_out->format('M d, Y')); ?>

            </p>
        </div>
        <div class="flex gap-3">
            <?php
                $paidAmount = $booking->payments->where('payment_status', 'paid')->sum('amount');
                $balance = $booking->total_amount - $paidAmount;
            ?>

            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('tenant::pages.payment.quick-pay', ['booking' => $booking]);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2090431867-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

            <a href="<?php echo e(route('tenant.bookings.edit', $booking->id)); ?>" wire:navigate class="glass px-5 py-2.5 rounded-xl text-white/80 hover:bg-white/10 font-medium transition">
                Edit Booking
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">

            
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Properties</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-white/10 text-white/50">
                            <tr>
                                <th class="text-left py-2 pr-4">Property</th>
                                <th class="text-center py-2 px-2">Price/Night</th>
                                <th class="text-center py-2 px-2">Qty</th>
                                <th class="text-right py-2 pl-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/80">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="border-b border-white/5">
                                <td class="py-2 pr-4"><?php echo e($item->property->name ?? 'Unknown'); ?></td>
                                <td class="py-2 px-2 text-center">₱<?php echo e(number_format($item->price, 2)); ?></td>
                                <td class="py-2 px-2 text-center"><?php echo e($item->quantity); ?></td>
                                <td class="py-2 pl-2 text-right font-medium text-white">₱<?php echo e(number_format($item->subtotal, 2)); ?></td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->services->count()): ?>
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Additional Services</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-white/10 text-white/50">
                            <tr>
                                <th class="text-left py-2 pr-4">Service</th>
                                <th class="text-center py-2 px-2">Price</th>
                                <th class="text-center py-2 px-2">Qty</th>
                                <th class="text-right py-2 pl-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/80">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="border-b border-white/5">
                                <td class="py-2 pr-4"><?php echo e($service->service->name ?? 'Unknown'); ?></td>
                                <td class="py-2 px-2 text-center">₱<?php echo e(number_format($service->service->price ?? 0, 2)); ?></td>
                                <td class="py-2 px-2 text-center"><?php echo e($service->quantity); ?></td>
                                <td class="py-2 pl-2 text-right font-medium text-white">₱<?php echo e(number_format($service->subtotal, 2)); ?></td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Payment History</h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->payments->count()): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-white/10 text-white/50">
                            <tr>
                                <th class="text-left py-2">Date</th>
                                <th class="text-left py-2">Method</th>
                                <th class="text-right py-2">Amount</th>
                                <th class="text-right py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/80">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="border-b border-white/5">
                                <td class="py-2"><?php echo e($payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y')); ?></td>
                                <td class="py-2"><?php echo e(ucfirst($payment->payment_method)); ?></td>
                                <td class="py-2 text-right font-medium text-white">₱<?php echo e(number_format($payment->amount, 2)); ?></td>
                                <td class="py-2 text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php echo e($payment->payment_status === 'paid' ? 'bg-green-500/20 text-green-300' : 'bg-amber-500/20 text-amber-300'); ?>">
                                        <?php echo e(ucfirst($payment->payment_status)); ?>

                                    </span>
                                </td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-white/40 text-sm">No payments recorded yet.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="space-y-6">
            
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-white/50">Total Amount</dt>
                        <dd class="font-medium text-white">₱<?php echo e(number_format($booking->total_amount, 2)); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-white/50">Paid</dt>
                        <dd class="text-brand-400 font-medium">₱<?php echo e(number_format($paidAmount, 2)); ?></dd>
                    </div>
                    <div class="flex justify-between border-t border-white/10 pt-3">
                        <dt class="text-white/50">Balance Due</dt>
                        <dd class="font-bold <?php echo e($balance > 0 ? 'text-red-400' : 'text-brand-400'); ?>">₱<?php echo e(number_format($balance, 2)); ?></dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        <?php echo e($booking->status === 'confirmed' ? 'bg-green-500/20 text-green-300' : ''); ?>

                        <?php echo e($booking->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : ''); ?>

                        <?php echo e($booking->status === 'checked_in' ? 'bg-purple-500/20 text-purple-300' : ''); ?>

                        <?php echo e($booking->status === 'completed' ? 'bg-blue-500/20 text-blue-300' : ''); ?>

                        <?php echo e($booking->status === 'cancelled' ? 'bg-red-500/20 text-red-300' : ''); ?>">
                        <?php echo e(ucfirst($booking->status)); ?>

                    </span>
                </div>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->customer): ?>
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Customer</h2>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-white/50">Name</dt>
                        <dd class="font-medium text-white"><?php echo e($booking->customer->name); ?></dd>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->customer->phone): ?>
                    <div>
                        <dt class="text-white/50">Phone</dt>
                        <dd class="text-white/80"><?php echo e($booking->customer->phone); ?></dd>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->customer->email): ?>
                    <div>
                        <dt class="text-white/50">Email</dt>
                        <dd class="text-white/80"><?php echo e($booking->customer->email); ?></dd>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </dl>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('tenant.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\Capstone\resources\views/tenant/pages/booking/show-booking.blade.php ENDPATH**/ ?>