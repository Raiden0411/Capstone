<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Property;
use App\Models\Service;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
?>

<div class="max-w-3xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Complete Your Booking</h1>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
        <h2 class="font-semibold text-lg mb-3"><?php echo e($property->name); ?></h2>
        <p class="text-slate-600"><?php echo e($property->propertyType->name ?? 'Property'); ?> · <?php echo e($property->tenant->name); ?></p>
        <p class="text-blue-600 font-bold mt-2">₱<?php echo e(number_format($property->price, 2)); ?> / night</p>
    </div>

    <form wire:submit="submit" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
        
        <div class="flex items-center justify-between text-sm text-slate-600 bg-slate-50 rounded-lg px-4 py-3 mb-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Booking as <strong><?php echo e(Auth::user()->name); ?></strong> (<?php echo e(Auth::user()->email); ?>)</span>
            </div>
            <button type="button" class="text-blue-600 hover:underline text-xs" onclick="document.getElementById('guest-info-fields').classList.toggle('hidden')">Change</button>
        </div>

        
        <div id="guest-info-fields" class="hidden space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Full Name</label>
                    <input type="text" wire:model="customerName" class="w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" wire:model="customerEmail" class="w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Phone</label>
                    <input type="text" wire:model="customerPhone" class="w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Address</label>
                    <input type="text" wire:model="customerAddress" class="w-full rounded-lg border-slate-300">
                </div>
            </div>
        </div>

        
        <div>
            <h3 class="font-medium text-slate-800 mb-3">Stay Dates</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Check-in *</label>
                    <input type="date" wire:model.live="check_in" class="w-full rounded-lg border-slate-300">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_in'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Check-out *</label>
                    <input type="date" wire:model.live="check_out" class="w-full rounded-lg border-slate-300">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_out'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->availableServices->isNotEmpty()): ?>
        <div>
            <h3 class="font-medium text-slate-800 mb-3">Add Extra Services</h3>
            <div class="flex flex-wrap gap-2 mb-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button type="button" wire:click="addService(<?php echo e($service->id); ?>)"
                            class="border rounded-full px-4 py-2 text-sm hover:bg-slate-50 transition">
                        <?php echo e($service->name); ?> (₱<?php echo e(number_format($service->price, 2)); ?>)
                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedServices)): ?>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-slate-500 border-b">
                            <th class="text-left py-2">Service</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $serviceId => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $svc = App\Models\Service::find($serviceId); ?>
                            <tr class="border-b">
                                <td class="py-2"><?php echo e($svc->name); ?></td>
                                <td class="text-center"><?php echo e($qty); ?></td>
                                <td class="text-right">₱<?php echo e(number_format($svc->price * $qty, 2)); ?></td>
                                <td class="text-center">
                                    <button type="button" wire:click="removeService(<?php echo e($serviceId); ?>)" class="text-red-500">&times;</button>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div>
            <h3 class="font-medium text-slate-800 mb-3">Payment Method</h3>
            <select wire:model.live="payment_method" class="w-full md:w-1/2 rounded-lg border-slate-300">
                <option value="cash">Cash (on arrival)</option>
                <option value="gcash">GCash</option>
                <option value="paymaya">PayMaya</option>
                <option value="card">Credit/Debit Card</option>
            </select>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment_method === 'cash'): ?>
                <div class="mt-3">
                    <label class="block text-sm font-medium mb-1">Reference Number (Optional)</label>
                    <input type="text" wire:model="reference_number" class="w-full md:w-1/2 rounded-lg border-slate-300" placeholder="e.g. Receipt #">
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="border-t pt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <span class="text-xs text-slate-500"><?php echo e($totalNights); ?> night<?php echo e($totalNights > 1 ? 's' : ''); ?></span>
                <div class="text-2xl font-bold text-slate-900">₱<?php echo e(number_format($totalAmount, 2)); ?></div>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg shadow-sm transition w-full sm:w-auto">
                <?php echo e($payment_method === 'cash' ? 'Confirm Booking' : 'Proceed to Pay'); ?>

            </button>
        </div>
    </form>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/4884e348.blade.php ENDPATH**/ ?>