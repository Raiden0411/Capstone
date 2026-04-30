

<?php $__env->startSection('content'); ?>
<div class="p-6 max-w-4xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Booking #<?php echo e($booking->booking_reference); ?></h1>
            <p class="text-slate-500"><?php echo e($booking->customer->name); ?> • <?php echo e($booking->check_in->format('M d, Y')); ?> – <?php echo e($booking->check_out->format('M d, Y')); ?></p>
        </div>
        <div class="flex gap-3">
            <a href="<?php echo e(route('tenant.payments.create', ['booking' => $booking->id])); ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                Record Payment
            </a>
            <a href="<?php echo e(route('tenant.bookings.edit', $booking->id)); ?>" class="border px-4 py-2 rounded-lg hover:bg-slate-50">Edit</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Booked Properties</h2>
                <table class="w-full text-sm">
                    <thead><tr class="text-slate-500"><th>Property</th><th>Price/Night</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr>
                            <td><?php echo e($item->property->name); ?></td>
                            <td>₱<?php echo e(number_format($item->price, 2)); ?></td>
                            <td><?php echo e($item->quantity); ?></td>
                            <td>₱<?php echo e(number_format($item->subtotal, 2)); ?></td>
                        </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->services->count()): ?>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Additional Services</h2>
                <table class="w-full text-sm">
                    <thead><tr class="text-slate-500"><th>Service</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr>
                            <td><?php echo e($service->service->name); ?></td>
                            <td>₱<?php echo e(number_format($service->service->price, 2)); ?></td>
                            <td><?php echo e($service->quantity); ?></td>
                            <td>₱<?php echo e(number_format($service->subtotal, 2)); ?></td>
                        </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Payment History</h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->payments->count()): ?>
                <table class="w-full text-sm">
                    <thead><tr class="text-slate-500"><th>Date</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr>
                            <td><?php echo e($payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y')); ?></td>
                            <td><?php echo e(ucfirst($payment->payment_method)); ?></td>
                            <td>₱<?php echo e(number_format($payment->amount, 2)); ?></td>
                            <td>
                                <span class="px-2 py-1 rounded-full text-xs 
                                    <?php echo e($payment->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                    <?php echo e(ucfirst($payment->payment_status)); ?>

                                </span>
                            </td>
                        </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-slate-500">No payments recorded yet.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="space-y-6">
            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Summary</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt>Total Amount</dt>
                        <dd class="font-medium">₱<?php echo e(number_format($booking->total_amount, 2)); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Paid</dt>
                        <dd class="text-green-600">₱<?php echo e(number_format($booking->payments->where('payment_status', 'paid')->sum('amount'), 2)); ?></dd>
                    </div>
                    <div class="flex justify-between border-t pt-2">
                        <dt>Balance Due</dt>
                        <dd class="font-bold">₱<?php echo e(number_format($booking->total_amount - $booking->payments->where('payment_status', 'paid')->sum('amount'), 2)); ?></dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <span class="px-3 py-1 rounded-full text-sm 
                        <?php echo e($booking->status === 'confirmed' ? 'bg-green-100 text-green-800' : 
                           ($booking->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                           ($booking->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'))); ?>">
                        <?php echo e(ucfirst($booking->status)); ?>

                    </span>
                </div>
            </div>

            
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Customer</h2>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-slate-500">Name</dt><dd><?php echo e($booking->customer->name); ?></dd></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->customer->phone): ?>
                    <div><dt class="text-slate-500">Phone</dt><dd><?php echo e($booking->customer->phone); ?></dd></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->customer->email): ?>
                    <div><dt class="text-slate-500">Email</dt><dd><?php echo e($booking->customer->email); ?></dd></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('tenant.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\Capstone\resources\views/tenant/pages/booking/show-booking.blade.php ENDPATH**/ ?>