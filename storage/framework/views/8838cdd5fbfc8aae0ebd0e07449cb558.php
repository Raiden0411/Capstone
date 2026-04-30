<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Auth;
?>

<div class="p-6 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Record Payment for Booking #<?php echo e($booking->booking_reference); ?></h1>
    
    <div class="bg-white rounded-xl shadow p-6">
        <div class="mb-4 p-3 bg-slate-50 rounded-lg">
            <p class="text-sm">Customer: <?php echo e($booking->customer->name); ?></p>
            <p class="text-sm">Total Amount: ₱<?php echo e(number_format($booking->total_amount, 2)); ?></p>
            <p class="text-sm">Remaining Balance: ₱<?php echo e(number_format($amount, 2)); ?></p>
        </div>

        <form wire:submit="<?php echo e(in_array($payment_method, ['cash', 'bank_transfer']) ? 'processCashPayment' : 'processOnlinePayment'); ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Amount to Pay *</label>
                <input type="number" step="0.01" wire:model="amount" class="w-full rounded-lg border-slate-300">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Payment Method *</label>
                <select wire:model.live="payment_method" class="w-full rounded-lg border-slate-300">
                    <option value="cash">Cash</option>
                    <option value="card">Credit/Debit Card (PayMongo)</option>
                    <option value="gcash">GCash (PayMongo)</option>
                    <option value="paymaya">PayMaya (PayMongo)</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['payment_method'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($payment_method, ['cash', 'bank_transfer'])): ?>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Reference Number (Optional)</label>
                <input type="text" wire:model="reference_number" class="w-full rounded-lg border-slate-300">
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    <?php echo e(in_array($payment_method, ['cash', 'bank_transfer']) ? 'Record Payment' : 'Proceed to Pay'); ?>

                </button>
                <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" class="border px-6 py-2 rounded-lg hover:bg-slate-50">Cancel</a>
            </div>
        </form>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/b1575f07.blade.php ENDPATH**/ ?>