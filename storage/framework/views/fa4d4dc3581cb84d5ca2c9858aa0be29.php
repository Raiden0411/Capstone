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
use App\Models\ServiceAvailability;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
?>




<div class="p-4 sm:p-6 lg:p-8 lg:pb-8 max-w-7xl mx-auto space-y-6
            pb-[calc(6rem+env(safe-area-inset-bottom))]">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Bookings</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Edit Booking
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Ref <span class="font-mono font-semibold text-gray-700 dark:text-gray-300"><?php echo e($booking->booking_reference); ?></span>
                · Update guest details, dates, and items.
            </p>
        </div>
        <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Booking</span>
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

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 6000)"
             :class="show ? '' : 'hidden'"
             class="flex items-start justify-between gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
            <div class="flex items-start gap-2.5 min-w-0">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-300 min-w-0">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'err-'.e($loop->index).''; ?>wire:key="err-<?php echo e($loop->index); ?>"><?php echo e($err); ?></li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                </div>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 shrink-0 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="update" class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">

        <div class="space-y-6">

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Guest Information</h2>
                </div>

                <div class="relative" wire:click.outside="closeGuestDropdown">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Search existing guest
                    </label>
                    <input type="text" wire:model.live.debounce.300ms="guestSearch"
                           placeholder="Type name, email or phone…"
                           class="input w-full">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showGuestDropdown): ?>
                        <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl max-h-64 overflow-y-auto">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $guestResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $guest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <button type="button"
                                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'guest-'.e($guest->id).''; ?>wire:key="guest-<?php echo e($guest->id); ?>"
                                        wire:click="selectGuest(<?php echo e($guest->id); ?>)"
                                        class="w-full text-left px-4 py-3 min-h-[44px] hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-[0.99] focus-visible:outline-none focus-visible:bg-gray-100 dark:focus-visible:bg-gray-700">
                                    <p class="text-sm text-gray-900 dark:text-white"><?php echo e($guest->name); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($guest->email ?? $guest->phone); ?></p>
                                </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No matching guests found.</div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="customerName" class="input w-full">
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone <span class="text-rose-500">*</span></label>
                        <input type="tel" wire:model="customerPhone" inputmode="numeric" maxlength="13" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerPhone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" wire:model="customerEmail" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Booking Details</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date <span class="text-rose-500">*</span></label>
                        <input type="date" wire:model.live.debounce.300ms="check_in" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_in'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time <span class="text-rose-500">*</span></label>
                        <input type="time" wire:model.live.debounce.300ms="check_in_time" class="input w-full">
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date <span class="text-rose-500">*</span></label>
                        <input type="date" wire:model.live.debounce.300ms="check_out" class="input w-full">
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
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Time</label>
                        <input type="text" value="<?php echo e($check_in_time); ?>" readonly
                               class="input w-full bg-gray-100 dark:bg-gray-900 cursor-not-allowed text-gray-500 dark:text-gray-400">
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">Mirrors start time.</p>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status <span class="text-rose-500">*</span></label>
                        <select wire:model="status" class="input w-full">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->allowedStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($value); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'status-opt-'.e($value).''; ?>wire:key="status-opt-<?php echo e($value); ?>"><?php echo e($label); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Booking Type</label>
                        <select wire:model.live="booking_type" class="input w-full">
                            <option value="full">Book Now (Full Payment)</option>
                            <option value="reservation">Reserve (20% Fee)</option>
                        </select>
                    </div>
                </div>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Select Activities</h2>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->availableProperties->isNotEmpty()): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableProperties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $property): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $isSelected = isset($selectedProperties[$property->id]);
                                $firstImg   = $property->images->first();
                            ?>
                            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'prop-'.e($property->id).''; ?>wire:key="prop-<?php echo e($property->id); ?>"
                                 role="button"
                                 tabindex="0"
                                 x-on:keydown.enter.prevent="$wire.toggleProperty(<?php echo e($property->id); ?>)"
                                 x-on:keydown.space.prevent="$wire.toggleProperty(<?php echo e($property->id); ?>)"
                                 wire:click="toggleProperty(<?php echo e($property->id); ?>)"
                                 class="relative rounded-xl border-2 transition-all duration-200 cursor-pointer overflow-hidden
                                        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                        <?php echo e($isSelected ? 'border-primary-600 ring-2 ring-primary-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-primary-400/50'); ?>">
                                <div class="aspect-[4/3] overflow-hidden">
                                    <img class="w-full h-full object-cover"
                                         src="<?php echo e($firstImg ? '/storage/' . ltrim($firstImg->image_path, '/') : '/images/placeholder-room.jpg'); ?>"
                                         alt="<?php echo e($property->name); ?>"
                                         loading="lazy"
                                         decoding="async">
                                </div>
                                <div class="p-4 bg-white dark:bg-gray-800">
                                    <h3 class="font-medium text-gray-900 dark:text-white truncate"><?php echo e($property->name); ?></h3>
                                    <p class="mt-1 text-sm text-primary-600 dark:text-primary-400 font-semibold tabular-nums">
                                        ₱<?php echo e(number_format($property->price, 2)); ?> <span class="text-gray-500 font-normal">/ day</span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Capacity: <?php echo e($property->capacity); ?> persons</p>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelected): ?>
                                    <div class="absolute top-3 right-3 bg-primary-600 text-white rounded-full p-1 shadow-md">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                        <svg class="mx-auto w-8 h-8 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No activities available for the selected dates.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedProperties) > 0): ?>
                    <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Selected Activities</h3>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedProperties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $prop      = $this->selectedPropertyModels->get($id);
                                $days      = $this->numberOfDays;
                                $roomTotal = ($prop->price ?? 0) * $item['quantity'] * $days;
                            ?>
                            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sel-prop-'.e($id).''; ?>wire:key="sel-prop-<?php echo e($id); ?>"
                                 class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                <div class="w-14 h-14 rounded-lg overflow-hidden bg-gray-200 dark:bg-gray-600 shrink-0 shadow-sm">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prop && $prop->images->isNotEmpty()): ?>
                                        <img src="<?php echo e('/storage/' . ltrim($prop->images->first()->image_path, '/')); ?>"
                                             class="w-full h-full object-cover"
                                             alt="<?php echo e($prop->name); ?>"
                                             loading="lazy"
                                             decoding="async">
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white truncate"><?php echo e($prop->name ?? 'Unknown'); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                        ₱<?php echo e(number_format($prop->price ?? 0, 2)); ?> / day · <?php echo e($days); ?> day<?php echo e($days > 1 ? 's' : ''); ?>

                                    </p>
                                </div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($roomTotal, 2)); ?></p>
                                <button type="button" wire:click="toggleProperty(<?php echo e($id); ?>)"
                                        aria-label="Remove <?php echo e($prop->name ?? 'activity'); ?>"
                                        class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50
                                               transition-all duration-200 active:scale-95
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Add-On Services <span class="text-gray-400 dark:text-gray-500 font-medium normal-case tracking-normal">(optional)</span>
                    </h2>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->availableServices->isNotEmpty()): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $isServiceSelected = isset($selectedServices[$service->id]); ?>
                            <button type="button"
                                    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'svc-btn-'.e($service->id).''; ?>wire:key="svc-btn-<?php echo e($service->id); ?>"
                                    wire:click="toggleService(<?php echo e($service->id); ?>)"
                                    class="inline-flex items-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg border text-xs font-semibold
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                           <?php echo e($isServiceSelected
                                              ? 'bg-primary-50 dark:bg-primary-500/15 border-primary-300 dark:border-primary-500/40 text-primary-700 dark:text-primary-300'
                                              : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700'); ?>">
                                <?php echo e($service->name); ?> <span class="text-gray-500 dark:text-gray-400 font-normal">(+₱<?php echo e(number_format($service->price, 2)); ?>)</span>
                            </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No services available for the selected dates.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedServices) > 0): ?>
                    <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Selected Services</h3>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $service = $this->selectedServiceModels->get($id); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($service): ?>
                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sel-svc-'.e($id).''; ?>wire:key="sel-svc-<?php echo e($id); ?>"
                                     class="flex items-center justify-between gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate"><?php echo e($service->name); ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Fixed rate per booking</p>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($service->price, 2)); ?></p>
                                        <button type="button" wire:click="toggleService(<?php echo e($id); ?>)"
                                                aria-label="Remove <?php echo e($service->name); ?>"
                                                class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50
                                                       transition-all duration-200 active:scale-95
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="space-y-4 lg:sticky lg:top-24">
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 space-y-4">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Payment Summary</h2>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount (₱)</label>
                    <input type="number" min="0" step="0.01"
                           wire:model.live.debounce.500ms="discountAmount"
                           class="input w-full">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['discountAmount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-300">Base Total</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($totalAmount, 2)); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-300">Discount</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">−₱<?php echo e(number_format(min((float) $discountAmount, $totalAmount), 2)); ?></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-gray-700 dark:text-gray-200 font-semibold">Final Total</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">₱<?php echo e(number_format($finalTotal, 2)); ?></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-100 dark:border-gray-700/60">
                        <span class="text-gray-600 dark:text-gray-300">Already Paid</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($this->paidAmount, 2)); ?></span>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking_type === 'reservation'): ?>
                        <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span class="text-gray-600 dark:text-gray-300">Reservation Fee (20%)</span>
                            <span class="font-semibold text-primary-600 dark:text-primary-400 tabular-nums">₱<?php echo e(number_format($reservationFee, 2)); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">Balance on Arrival</span>
                            <span class="font-semibold text-amber-600 dark:text-amber-500 tabular-nums">₱<?php echo e(number_format($balanceOnArrival, 2)); ?></span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="update"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="update">Save Changes</span>
                        <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Saving…
                        </span>
                    </button>
                    <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate
                       class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>Cancel</span>
                    </a>
                </div>
            </div>
        </div>
    </form>

    
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-lg
                p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Final Total</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white tabular-nums">
                    ₱<?php echo e(number_format($finalTotal, 2)); ?>

                </p>
            </div>
            <button type="button" wire:click="update"
                    wire:loading.attr="disabled"
                    wire:target="update"
                    class="shrink-0 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="update">Save</span>
                <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework\views/livewire/views/cf49d45f.blade.php ENDPATH**/ ?>