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



<div class="relative z-10 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-8 items-start">

        
        <div class="anim-up">
            <a href="<?php echo e(route('tenant.show', $property->tenant->slug)); ?>" wire:navigate
               class="inline-flex items-center gap-1 text-xs uppercase tracking-wider text-white/50 hover:text-brand-400 transition-colors mb-6">
                ← Back to <?php echo e($property->tenant->name); ?>

            </a>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-4 h-px bg-brand-500"></span>
                <span class="text-xs tracking-[0.2em] uppercase text-brand-500 font-semibold">Reservation</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-white mb-8">
                Complete Your <em class="italic text-brand-400">Booking</em>
            </h1>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
                <div class="bg-red-500/10 border border-red-400/30 text-red-300 p-4 rounded-xl text-sm mb-6">
                    <?php echo e(session('error')); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <form wire:submit.prevent="submit" class="glass-card p-6 md:p-8 space-y-8 anim-up delay-1">

                
                <section>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">1</span>
                        <h2 class="font-display text-lg font-medium text-white">Your Details</h2>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        <div class="glass mb-4 p-4 rounded-xl flex items-center justify-between">
                            <span class="text-sm text-white/70">Booking as <strong class="text-white font-semibold"><?php echo e(Auth::user()->name); ?></strong> · <?php echo e(Auth::user()->email); ?></span>
                            <button type="button" onclick="document.getElementById('guest-fields').classList.toggle('hidden')"
                                    class="text-xs font-semibold uppercase tracking-wider text-brand-400 hover:opacity-80">Edit</button>
                        </div>
                        <div id="guest-fields" class="hidden">
                    <?php else: ?>
                        <div id="guest-fields">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Full Name *</label>
                                <input type="text" wire:model="customerName"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-400 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Email</label>
                                <input type="email" wire:model="customerEmail"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-400 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Phone</label>
                                <input type="text" wire:model="customerPhone"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                       placeholder="+63 9xx xxx xxxx">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Address</label>
                                <input type="text" wire:model="customerAddress"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            </div>
                        </div>
                    </div>
                </section>

                
                <section>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">2</span>
                        <h2 class="font-display text-lg font-medium text-white">Stay Dates</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Check-in *</label>
                            <input type="date" wire:model.live="check_in"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_in'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-400 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Check-out *</label>
                            <input type="date" wire:model.live="check_out"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_out'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-400 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-4 inline-flex items-center gap-2 bg-brand-500/10 border border-brand-500/20 rounded-full px-4 py-1.5 text-sm text-white/70">
                        <span class="font-display text-lg text-white"><?php echo e($totalNights); ?></span> night<?php echo e($totalNights > 1 ? 's' : ''); ?> selected
                    </div>
                </section>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->availableServices->isNotEmpty()): ?>
                <section>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">3</span>
                        <h2 class="font-display text-lg font-medium text-white">Extra Services</h2>
                    </div>
                    <div class="flex flex-wrap gap-2 mb-5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <button type="button" wire:click="addService(<?php echo e($service->id); ?>)"
                                    class="glass px-4 py-2 rounded-full text-sm text-white/80 hover:bg-white/10 hover:text-white transition flex items-center gap-2">
                                <?php echo e($service->name); ?>

                                <span class="text-brand-400 font-semibold">+₱<?php echo e(number_format($service->price)); ?></span>
                            </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedServices)): ?>
                        <div class="glass p-4 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="text-xs uppercase tracking-wider text-white/40 border-b border-white/10">
                                    <tr><th class="pb-2 text-left">Service</th><th class="pb-2 text-center">Qty</th><th class="pb-2 text-right">Subtotal</th><th></th></tr>
                                </thead>
                                <tbody class="text-white/80">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $serviceId => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <?php $svc = App\Models\Service::find($serviceId); ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svc): ?>
                                            <tr class="border-b border-white/5">
                                                <td class="py-2"><?php echo e($svc->name); ?></td>
                                                <td class="text-center"><?php echo e($qty); ?></td>
                                                <td class="text-right font-medium">₱<?php echo e(number_format($svc->price * $qty, 2)); ?></td>
                                                <td class="text-center">
                                                    <button wire:click="removeService(<?php echo e($serviceId); ?>)"
                                                            class="w-5 h-5 rounded-full border border-red-400/40 text-red-300 hover:bg-red-500 hover:text-white inline-flex items-center justify-center text-xs transition">
                                                        ✕
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <section>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">
                            <?php echo e($this->availableServices->isNotEmpty() ? '4' : '3'); ?>

                        </span>
                        <h2 class="font-display text-lg font-medium text-white">Payment Method</h2>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['cash','Cash','💵'],['gcash','GCash','📱'],['paymaya','PayMaya','💳'],['card','Card','🏦']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$val,$label,$icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="payment_method" value="<?php echo e($val); ?>" class="peer hidden">
                                <div class="glass px-4 py-3 rounded-xl text-sm text-white/60 peer-checked:border-brand-500 peer-checked:bg-brand-500/10 peer-checked:text-white transition">
                                    <span class="mr-1"><?php echo e($icon); ?></span> <?php echo e($label); ?>

                                </div>
                            </label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment_method === 'cash'): ?>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Reference Number <span class="opacity-50">(optional)</span></label>
                            <input type="text" wire:model="reference_number"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition max-w-xs"
                                   placeholder="e.g. Receipt #12345">
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>

            </form>
        </div>

        
        <div class="lg:sticky lg:top-24 anim-up delay-2">
            <div class="bg-black/60 backdrop-blur-xl border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-white/10">
                    <h3 class="font-display text-2xl font-medium text-white"><?php echo e($property->name); ?></h3>
                    <p class="text-sm text-white/50"><?php echo e($property->propertyType->name ?? 'Property'); ?> · <?php echo e($property->tenant->name); ?></p>
                    <div class="mt-4 flex items-baseline gap-1">
                        <span class="font-display text-3xl text-brand-400">₱<?php echo e(number_format($property->price, 2)); ?></span>
                        <span class="text-xs text-white/40">/ night</span>
                    </div>
                </div>
                <div class="p-6 border-b border-white/10 space-y-2 text-sm text-white/70">
                    <div class="flex justify-between">
                        <span><?php echo e($totalNights); ?> night<?php echo e($totalNights > 1 ? 's' : ''); ?></span>
                        <span class="font-medium text-white">₱<?php echo e(number_format($property->price * $totalNights, 2)); ?></span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $serviceId => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php $svc = App\Models\Service::find($serviceId); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svc): ?>
                            <div class="flex justify-between">
                                <span><?php echo e($svc->name); ?> ×<?php echo e($qty); ?></span>
                                <span class="font-medium text-white">₱<?php echo e(number_format($svc->price * $qty, 2)); ?></span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <div class="p-6 flex justify-between items-end">
                    <span class="text-xs font-semibold uppercase tracking-widest text-white/40">Total</span>
                    <span class="font-display text-3xl font-medium text-brand-400">₱<?php echo e(number_format($totalAmount, 2)); ?></span>
                </div>
                <button wire:click="submit" wire:loading.attr="disabled"
                        class="w-full py-4 bg-brand-600 hover:bg-brand-500 text-white font-semibold text-sm uppercase tracking-wider transition disabled:opacity-50">
                    <span wire:loading.remove>
                        <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?php echo e($payment_method === 'cash' ? 'Confirm Booking' : 'Proceed to Pay'); ?>

                    </span>
                    <span wire:loading>
                        <svg class="inline-block w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Processing…
                    </span>
                </button>
                <div class="px-6 py-4 text-center text-xs text-white/30 border-t border-white/5">
                    Free cancellation within 24 hours.<br>No charges until confirmed.
                </div>
            </div>
        </div>

    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/4884e348.blade.php ENDPATH**/ ?>