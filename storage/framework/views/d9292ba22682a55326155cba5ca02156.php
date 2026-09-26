<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Booking;
use App\Models\User;
use App\Models\Property;
use App\Models\Service;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Scopes\TenantScope;
use App\Services\PayMongoService;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
?>




<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6"
     x-data="{
         qrPolling: false,
         qrPollTimer: null,
         init() {
             this.$watch('$wire.showQrModal', (v) => {
                 this.qrPolling = v;
                 if (v) {
                     this.qrPollTimer = setInterval(() => this.$wire.checkQrPayment(), 5000);
                 } else if (this.qrPollTimer) {
                     clearInterval(this.qrPollTimer);
                     this.qrPollTimer = null;
                 }
             });
         },
         destroy() {
             if (this.qrPollTimer) clearInterval(this.qrPollTimer);
         }
     }">

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Bookings</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Walk-In Booking
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Create a manual reservation with instant confirmation.
            </p>
        </div>
        <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Bookings</span>
        </a>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?php echo e(session('message')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?php echo e(session('error')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($qrError): ?>
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
            <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium"><?php echo e($qrError); ?></p>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
            <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-300">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'err-'.e($loop->index).''; ?>wire:key="err-<?php echo e($loop->index); ?>"><?php echo e($err); ?></li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <form wire:submit="submit" class="relative grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">

        
        <div class="space-y-6">

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Guest &amp; Booking Details
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="field-customer-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Full Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="field-customer-name" wire:model="customerName" class="input" placeholder="Guest name">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label for="field-customer-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Phone <span class="text-rose-500">*</span>
                        </label>
                        <input type="tel" id="field-customer-phone"
                               inputmode="numeric" pattern="[0-9+]*" maxlength="13"
                               wire:model.live.debounce.500ms="customerPhone"
                               x-on:input="
                                   const cleaned = $event.target.value.replace(/[^0-9+]/g, '');
                                   if (cleaned !== $event.target.value) {
                                       $event.target.value = cleaned;
                                       $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                                   }
                               "
                               class="input" placeholder="09xxxxxxxxx" autocomplete="tel">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerPhone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Format: 09xxxxxxxxx or +639xxxxxxxxx</p>
                    </div>
                    <div>
                        <label for="field-customer-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Email <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                        </label>
                        <input type="email" id="field-customer-email" wire:model="customerEmail" class="input" placeholder="guest@example.com">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label for="field-customer-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Address <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                        </label>
                        <input type="text" id="field-customer-address" wire:model="customerAddress" class="input" placeholder="Complete address">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerAddress'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="field-check-in" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Start Date <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" id="field-check-in" wire:model.live.debounce.300ms="check_in"
                               min="<?php echo e(now()->format('Y-m-d')); ?>"
                               max="<?php echo e(now()->addDays(30)->format('Y-m-d')); ?>"
                               class="input">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_in'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div wire:ignore>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Start Time
                            <span class="text-[10px] font-normal text-gray-400 dark:text-gray-500 ml-1">(live — set automatically)</span>
                        </label>
                        <div x-data="{
                                time: '<?php echo e($check_in_time); ?>',
                                timer: null,
                                init() {
                                    this.tick();
                                    this.timer = setInterval(() => this.tick(), 10000);
                                },
                                destroy() {
                                    if (this.timer) clearInterval(this.timer);
                                },
                                tick() {
                                    const now = new Date();
                                    const hh = String(now.getHours()).padStart(2, '0');
                                    const mm = String(now.getMinutes()).padStart(2, '0');
                                    const next = hh + ':' + mm;
                                    if (this.time !== next) {
                                        this.time = next;
                                        try { this.$wire.set('check_in_time', next, false); } catch (e) {}
                                    }
                                }
                             }"
                             class="w-full flex items-center justify-between px-3 h-11 rounded-xl
                                    bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600">
                            <span x-text="time" class="font-mono tabular-nums text-sm text-gray-900 dark:text-white">--:--</span>
                            <span class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 motion-reduce:animate-none"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                Live
                            </span>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_in_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <label for="field-check-out" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            End Date <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" id="field-check-out" wire:model.live.debounce.300ms="check_out"
                               min="<?php echo e(now()->addDay()->format('Y-m-d')); ?>"
                               max="<?php echo e(now()->addDays(30)->format('Y-m-d')); ?>"
                               class="input">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_out'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <label for="field-booking-reference" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Booking Reference
                        </label>
                        <div class="flex gap-2">
                            <input type="text" id="field-booking-reference" wire:model="booking_reference"
                                   class="input flex-1 bg-gray-100 dark:bg-gray-900 cursor-not-allowed font-mono" readonly>
                            <button type="button"
                                    wire:click="generateBookingReference"
                                    aria-label="Regenerate booking reference"
                                    title="Regenerate"
                                    class="inline-flex items-center justify-center h-11 w-11 shrink-0 rounded-xl
                                           border border-gray-300 dark:border-gray-600
                                           bg-white dark:bg-gray-800
                                           text-gray-700 dark:text-gray-200
                                           transition-all duration-200 active:scale-95
                                           hover:bg-gray-50 dark:hover:bg-gray-700
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Select Property <span class="text-rose-500">*</span>
                    </h2>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->properties->isNotEmpty()): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->properties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $property): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $isSelected  = isset($selectedProperties[$property->id]);
                                $isAvailable = $property->is_available;
                                $firstImg    = $property->images->first();
                            ?>
                            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'prop-'.e($property->id).''; ?>wire:key="prop-<?php echo e($property->id); ?>"
                                 <?php if($isAvailable): ?>
                                     role="button"
                                     tabindex="0"
                                     x-on:keydown.enter.prevent="$wire.toggleProperty(<?php echo e($property->id); ?>)"
                                     x-on:keydown.space.prevent="$wire.toggleProperty(<?php echo e($property->id); ?>)"
                                     wire:click="toggleProperty(<?php echo e($property->id); ?>)"
                                 <?php endif; ?>
                                 class="relative rounded-xl border-2 transition-all duration-200 overflow-hidden
                                        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                        <?php echo e($isSelected ? 'border-primary-600 ring-2 ring-primary-500/30' : 'border-gray-200 dark:border-gray-700'); ?>

                                        <?php echo e($isAvailable ? 'cursor-pointer hover:border-primary-400 dark:hover:border-primary-500/60' : 'cursor-not-allowed opacity-70'); ?>">

                                <div class="aspect-[4/3] overflow-hidden relative">
                                    <img class="w-full h-full object-cover"
                                         src="<?php echo e($firstImg ? '/storage/' . ltrim($firstImg->image_path, '/') : '/images/placeholder-room.jpg'); ?>"
                                         alt="<?php echo e($property->name); ?>"
                                         loading="lazy"
                                         decoding="async">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isAvailable): ?>
                                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                            <span class="bg-rose-600 text-white px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">Booked</span>
                                        </div>
                                    <?php elseif($isSelected): ?>
                                        <div class="absolute top-2 right-2 bg-primary-600 text-white rounded-full p-1.5 shadow">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>

                                <div class="p-3 flex justify-between items-center gap-2">
                                    <div class="min-w-0">
                                        <h3 class="font-medium text-gray-900 dark:text-white truncate text-sm"><?php echo e($property->name); ?></h3>
                                        <p class="text-xs text-primary-600 dark:text-primary-400 font-semibold mt-0.5 tabular-nums">₱<?php echo e(number_format($property->price, 2)); ?> / day</p>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isAvailable): ?>
                                        <button type="button"
                                                wire:click.stop="openCalendar(<?php echo e($property->id); ?>)"
                                                class="inline-flex items-center justify-center h-11 sm:h-7 px-2.5 rounded-lg text-[11px] font-semibold shrink-0
                                                       border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200
                                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                            Calendar
                                        </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No properties available.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selectedProperties'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Add-On Services <span class="text-gray-400 dark:text-gray-500 font-medium normal-case tracking-normal">(optional)</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->availableServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php $isSelected = isset($selectedServices[$service->id]); ?>
                        <button type="button" wire:click="toggleService(<?php echo e($service->id); ?>)"
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'service-'.e($service->id).''; ?>wire:key="service-<?php echo e($service->id); ?>"
                                class="group relative flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm font-medium transition-all duration-200 active:scale-[0.98]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       <?php echo e($isSelected
                                          ? 'bg-primary-50 border-primary-300 text-primary-700 dark:bg-primary-900/30 dark:border-primary-500/50 dark:text-primary-200 shadow-sm'
                                          : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700/50'); ?>">
                            <span class="truncate"><?php echo e($service->name); ?></span>
                            <span class="text-sm font-semibold shrink-0 tabular-nums <?php echo e($isSelected ? 'text-primary-600 dark:text-primary-300' : 'text-gray-500 dark:text-gray-400'); ?>">
                                <?php echo e($isSelected ? 'Added ✓' : '+₱' . number_format($service->price, 2)); ?>

                            </span>
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="col-span-full text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            No services available for this business.
                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedServices) > 0): ?>
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700/60 space-y-2">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Selected</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $service = $this->selectedServiceModels->get($id); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($service): ?>
                                <div class="flex items-center justify-between gap-3 p-3 bg-gray-50 dark:bg-gray-800/70 rounded-lg border border-gray-200 dark:border-gray-700"
                                     <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'selected-service-'.e($id).''; ?>wire:key="selected-service-<?php echo e($id); ?>">
                                    <span class="text-sm text-gray-700 dark:text-gray-200 truncate"><?php echo e($service->name); ?></span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($service->price * $qty, 2)); ?></span>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Payment Method <span class="text-rose-500">*</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    
                    <label class="relative cursor-pointer">
                        <input type="radio" wire:model.live="payment_method" value="cash" class="sr-only peer">
                        <div class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all duration-200
                                    bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600
                                    peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 dark:peer-checked:border-primary-500/50
                                    hover:border-primary-400 dark:hover:border-primary-500/50
                                    peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/50">
                            <div class="p-2.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 dark:text-white">Cash</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Instant confirm</p>
                            </div>
                            <div class="ml-auto opacity-0 peer-checked:opacity-100 transition-opacity shrink-0">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                    </label>

                    
                    <label class="relative cursor-pointer">
                        <input type="radio" wire:model.live="payment_method" value="qr" class="sr-only peer">
                        <div class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all duration-200
                                    bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600
                                    peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 dark:peer-checked:border-primary-500/50
                                    hover:border-primary-400 dark:hover:border-primary-500/50
                                    peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/50">
                            <div class="p-2.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2m4 0v-1M5 7h6m6 0h2M5 11h6m6 0h2M5 15h6m6 0h2M5 19h6m6 0h2"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 dark:text-white">QR Code</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Scan via PayMongo</p>
                            </div>
                            <div class="ml-auto opacity-0 peer-checked:opacity-100 transition-opacity shrink-0">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                    </label>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment_method === 'qr'): ?>
                    <div class="flex items-start gap-2.5 p-3 rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] text-xs text-blue-800 dark:text-blue-300">
                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="leading-relaxed">
                            A dynamic QR code will be generated via PayMongo. The booking stays <strong>pending</strong> until the customer scans and pays.
                        </span>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="space-y-4 lg:sticky lg:top-24">
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 space-y-4">

                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Booking Summary
                    </h2>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($customerName): ?>
                    <div class="text-xs">
                        <p class="text-gray-500 dark:text-gray-400">Guest</p>
                        <p class="font-medium text-gray-900 dark:text-white mt-0.5 truncate"><?php echo e($customerName); ?></p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($check_in && $check_out): ?>
                    <div class="text-xs">
                        <p class="text-gray-500 dark:text-gray-400">Duration</p>
                        <p class="font-medium text-gray-900 dark:text-white mt-0.5 tabular-nums">
                            <?php echo e(\Carbon\Carbon::parse($check_in)->format('M j')); ?>

                            →
                            <?php echo e(\Carbon\Carbon::parse($check_out)->format('M j, Y')); ?>

                            <span class="text-gray-400 dark:text-gray-500 font-normal">(<?php echo e($this->numberOfDays); ?> <?php echo e($this->numberOfDays === 1 ? 'day' : 'days'); ?>)</span>
                        </p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60 space-y-2 max-h-64 overflow-y-auto">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $selectedProperties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $prop      = $this->selectedPropertyModels->get($id);
                            $days      = $this->numberOfDays;
                            $roomTotal = ($prop->price ?? 0) * $qty * $days;
                        ?>
                        <div class="flex justify-between items-center gap-3 text-xs" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'summary-property-'.e($id).''; ?>wire:key="summary-property-<?php echo e($id); ?>">
                            <span class="text-gray-700 dark:text-gray-300 truncate"><?php echo e($prop->name ?? 'Property'); ?> ×<?php echo e($qty); ?> · <?php echo e($days); ?>d</span>
                            <span class="font-semibold text-gray-900 dark:text-white tabular-nums shrink-0">₱<?php echo e(number_format($roomTotal, 0)); ?></span>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400">No property selected.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php $service = $this->selectedServiceModels->get($id); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($service): ?>
                            <div class="flex justify-between items-center gap-3 text-xs" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'summary-service-'.e($id).''; ?>wire:key="summary-service-<?php echo e($id); ?>">
                                <span class="text-gray-700 dark:text-gray-300 truncate"><?php echo e($service->name); ?></span>
                                <span class="font-semibold text-gray-900 dark:text-white tabular-nums shrink-0">₱<?php echo e(number_format($service->price * $qty, 0)); ?></span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Grand Total</span>
                        <span class="text-2xl font-black text-primary-600 dark:text-primary-400 tabular-nums">₱<?php echo e(number_format($totalAmount, 2)); ?></span>
                    </div>
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="submit">
                        <?php echo e($payment_method === 'qr' ? 'Generate QR & Create Booking' : 'Complete Booking'); ?>

                    </span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Processing…
                    </span>
                </button>

                <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate
                   class="w-full inline-flex items-center justify-center h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                          transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span>Cancel</span>
                </a>
            </div>
        </div>
    </form>

    
    <?php
        $calendarProperty = $calendarPropertyId
            ? $this->properties->firstWhere('id', $calendarPropertyId)
            : null;
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($calendarProperty): ?>
        <div class="fixed inset-0 z-[150] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
             <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'calendar-modal-'.e($calendarProperty->id).''; ?>wire:key="calendar-modal-<?php echo e($calendarProperty->id); ?>"
             x-on:keydown.escape.window="$wire.closeCalendar()"
             x-data="propertyCalendar(<?php echo \Illuminate\Support\Js::from($calendarProperty->booked_dates)->toHtml() ?>)">

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto"
                 @click.outside="$wire.closeCalendar()">

                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white truncate">
                        <?php echo e($calendarProperty->name); ?> — Availability
                    </h3>
                    <button type="button" wire:click="closeCalendar"
                            aria-label="Close calendar"
                            class="inline-flex items-center justify-center h-11 w-11 sm:h-8 sm:w-8 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">Booked dates:</p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($calendarProperty->booked_ranges)): ?>
                        <div class="flex flex-wrap gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $calendarProperty->booked_ranges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $range): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <span <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'cal-range-'.e(md5($range['display'])).''; ?>wire:key="cal-range-<?php echo e(md5($range['display'])); ?>"
                                      class="inline-block bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 rounded px-3 py-1 text-xs">
                                    <?php echo e($range['display']); ?>

                                </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400">No booked dates in this range.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <button type="button" @click="prevMonth()"
                                aria-label="Previous month"
                                class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white"
                              x-text="currentMonthName + ' ' + currentYear"></span>
                        <button type="button" @click="nextMonth()"
                                aria-label="Next month"
                                class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-center">
                        <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                            <span class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400 py-1" x-text="day"></span>
                        </template>
                        <template x-for="blank in firstDayOffset" :key="'blank-'+blank"><span></span></template>
                        <template x-for="day in daysInMonth" :key="day.date">
                            <div class="h-9 flex items-center justify-center text-sm font-medium rounded-lg"
                                 :class="{
                                     'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300': day.isBooked,
                                     'bg-gray-100 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300': !day.isBooked
                                 }">
                                <span x-text="day.dayNumber"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t border-gray-100 dark:border-gray-700/60 flex justify-end">
                    <button type="button" wire:click="closeCalendar"
                            class="inline-flex items-center justify-center h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>Close</span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showQrModal && $qrImage): ?>
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
             x-on:keydown.escape.window="$wire.cancelQrPayment()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6
                        pb-[max(1.5rem,env(safe-area-inset-bottom))]"
                 @click.outside="$wire.cancelQrPayment()">

                <div class="flex items-start justify-between mb-4 gap-3">
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Scan to Pay</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Open any e-wallet or bank app to scan
                        </p>
                    </div>
                    <button type="button" wire:click="cancelQrPayment"
                            aria-label="Close QR modal"
                            class="inline-flex items-center justify-center h-11 w-11 sm:h-8 sm:w-8 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="bg-white rounded-2xl p-4 flex items-center justify-center border-2 border-gray-200 dark:border-gray-700 mb-4">
                    <img src="<?php echo e($qrImage); ?>" alt="PayMongo QR Code" class="w-64 h-64 object-contain" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'qr-image'; ?>wire:key="qr-image">
                </div>

                <div class="text-center mb-5">
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount due</p>
                    <p class="text-2xl font-black text-primary-600 dark:text-primary-400 mt-1 tabular-nums">
                        ₱<?php echo e(number_format($totalAmount, 2)); ?>

                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($qrExpiresAt): ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Expires <?php echo e(\Carbon\Carbon::parse($qrExpiresAt)->diffForHumans()); ?>

                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="flex items-center gap-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 mb-4">
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-xs text-blue-800 dark:text-blue-300 font-medium">
                        Waiting for payment confirmation…
                    </p>
                </div>

                <div class="flex gap-2">
                    <button type="button" wire:click="checkQrPayment"
                            wire:loading.attr="disabled"
                            wire:target="checkQrPayment"
                            class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="checkQrPayment">Check Now</span>
                        <span wire:loading wire:target="checkQrPayment" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Checking…
                        </span>
                    </button>
                    <button type="button" wire:click="cancelQrPayment"
                            class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>Pay later</span>
                    </button>
                </div>

                <p class="mt-3 text-[11px] text-center text-gray-400 dark:text-gray-500">
                    The booking will be created as <strong>pending</strong> if you close this dialog.
                </p>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

    <?php
        $__scriptKey = '3376455075-0';
        ob_start();
    ?>
<script>
    if (! window.__bookingCalendarRegistered) {
        window.__bookingCalendarRegistered = true;

        window.propertyCalendar = function (bookedDates) {
            return {
                bookedDates: bookedDates || [],
                currentMonth: new Date().getMonth(),
                currentYear: new Date().getFullYear(),

                get daysInMonth() {
                    const year = this.currentYear;
                    const month = this.currentMonth;
                    const days = [];
                    const totalDays = new Date(year, month + 1, 0).getDate();

                    for (let day = 1; day <= totalDays; day++) {
                        const dateObj = new Date(year, month, day);
                        const dateStr = dateObj.getFullYear() + '-'
                            + String(dateObj.getMonth() + 1).padStart(2, '0') + '-'
                            + String(dateObj.getDate()).padStart(2, '0');

                        days.push({
                            date: dateStr,
                            dayNumber: day,
                            isBooked: this.bookedDates.includes(dateStr),
                        });
                    }
                    return days;
                },

                get firstDayOffset() {
                    return new Date(this.currentYear, this.currentMonth, 1).getDay();
                },

                get currentMonthName() {
                    return new Date(this.currentYear, this.currentMonth)
                        .toLocaleDateString('en-US', { month: 'long' });
                },

                prevMonth() {
                    this.currentMonth--;
                    if (this.currentMonth < 0) {
                        this.currentMonth = 11;
                        this.currentYear--;
                    }
                },

                nextMonth() {
                    this.currentMonth++;
                    if (this.currentMonth > 11) {
                        this.currentMonth = 0;
                        this.currentYear++;
                    }
                }
            };
        };
    }
</script>
    <?php
        $__output = ob_get_clean();

        \Livewire\store($this)->push('scripts', $__output, $__scriptKey)
    ?><?php /**PATH C:\laragon\www\Capstone\storage\framework\views/livewire/views/3f1c02e3.blade.php ENDPATH**/ ?>