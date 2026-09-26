<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;
use App\Models\Property;
use App\Models\Service;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
?>




<?php $__env->startPush('styles'); ?>
    <?php if (! $__env->hasRenderedOnce('6c6cac3a-b2d8-4824-9092-b9d99f44eb3f')): $__env->markAsRenderedOnce('6c6cac3a-b2d8-4824-9092-b9d99f44eb3f'); ?>
        <style>
            /* ── Ambient background wash ── */
            .booking-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%, rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .booking-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%, rgba(245,158,11,.10) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }

            /* ── Step indicator ── */
            .step-dot {
                width: 40px; height: 40px; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-size: 14px; font-weight: 800;
                transition: all .35s cubic-bezier(.34,1.56,.64,1);
                flex-shrink: 0;
            }
            .step-dot.done    { background: #059669; color: #fff; box-shadow: 0 0 0 4px rgba(5,150,105,.18); }
            .step-dot.active  { background: #10b981; color: #fff; box-shadow: 0 0 0 6px rgba(16,185,129,.22); }
            .step-dot.pending { background: #e5e7eb; color: #9ca3af; border: 1px solid #d1d5db; }
            .dark .step-dot.pending { background: #1f2937; color: #9ca3af; border-color: #374151; }

            .step-connector {
                height: 2px;
                border-radius: 2px;
                transition: background-color .4s ease;
            }

            /* ── Panel transition on step change ── */
            .step-panel {
                animation: stepSlideIn .28s cubic-bezier(.16,1,.3,1);
            }
            @keyframes stepSlideIn {
                from { opacity: 0; transform: translateX(16px); }
                to   { opacity: 1; transform: translateX(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .step-panel { animation: none; }
            }

            /* ── Calendar day ── */
            .cal-day {
                min-height: 44px;
                min-width: 0;
                border-radius: 12px;
                font-weight: 500;
                transition: background-color .15s, color .15s, transform .1s;
            }
            .cal-day:active:not(:disabled) { transform: scale(.92); }
        </style>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<div class="relative z-10 min-h-screen text-gray-900 dark:text-gray-100"
     x-data="{
         step: 1,
         maxStep: <?php echo e($this->availableServices->isNotEmpty() ? 4 : 3); ?>,
         errors: {},

         get nextLabel() {
             if (this.step >= this.maxStep) return 'Continue';
             const target = this.step + 1;
             if (target === 2) return 'Continue to Dates';
             if (target === 3) return this.maxStep === 4 ? 'Continue to Extras' : 'Continue to Payment';
             if (target === 4) return 'Continue to Payment';
             return 'Continue';
         },

         next() {
             if (this.step === 1) {
                 if (!this.$wire.customerName.trim()) this.errors.name = 'Full name is required.';
                 else delete this.errors.name;
                 if (!this.$wire.customerEmail.trim()) this.errors.email = 'Email is required.';
                 else delete this.errors.email;
                 if (Object.keys(this.errors).length > 0) return;
             }
             if (this.step === 2) {
                 if (!this.$wire.check_in || !this.$wire.check_out) {
                     this.errors.dates = 'Please select both start and end dates.';
                     return;
                 }
                 if (this.$wire.dateRangeValid === false) {
                     this.errors.dates = 'Selected range includes unavailable days. Please pick another range.';
                     return;
                 }
                 delete this.errors.dates;
             }
             if (this.step < this.maxStep) {
                 this.step++;
                 this.$nextTick(() => this.$refs['stepHeading' + this.step]?.focus());
             }
         },
         prev() {
             if (this.step > 1) {
                 this.step--;
                 this.$nextTick(() => this.$refs['stepHeading' + this.step]?.focus());
             }
         },

         goTo(s) {
             if (s >= this.step) return;
             if (s < 1 || s > this.maxStep) return;
             this.step = s;
             this.$nextTick(() => this.$refs['stepHeading' + this.step]?.focus());
         }
     }">

    
    <div class="booking-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 pb-32 lg:pb-12">

        
        <div class="mb-6">
            <a href="<?php echo e(route('tenant.show', $property->tenant->slug)); ?>" wire:navigate
               class="relative inline-flex items-center gap-1.5 text-xs uppercase tracking-wider
                      text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                      transition-colors group active:scale-95
                      before:absolute before:content-[''] before:-inset-2 before:rounded
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/>
                </svg>
                Back to <?php echo e($property->tenant->name); ?>

            </a>
        </div>

        
        <div class="mb-6 sm:mb-8 rounded-2xl bg-white dark:bg-gray-800
                    border border-gray-200 dark:border-gray-700
                    shadow-sm p-4 sm:p-5">
            <div class="flex items-center gap-4">
                
                <div class="shrink-0 w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden
                            bg-gray-100 dark:bg-gray-700
                            ring-1 ring-gray-200/70 dark:ring-gray-700/70">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property->images->isNotEmpty()): ?>
                        <img src="<?php echo e(asset('storage/'.$property->images->first()->image_path)); ?>"
                             alt="<?php echo e($property->name); ?>"
                             class="w-full h-full object-cover"
                             loading="eager" decoding="async">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-amber-600 dark:text-amber-400 mb-1 inline-flex items-center gap-2">
                        <span class="h-px w-3 bg-amber-500" aria-hidden="true"></span>
                        Booking
                    </p>
                    <h2 class="font-display text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate leading-tight">
                        <?php echo e($property->name); ?>

                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                        <?php echo e($property->propertyType->name ?? 'Activity'); ?>

                        <span class="mx-1" aria-hidden="true">·</span>
                        <?php echo e($property->tenant->name); ?>

                    </p>
                </div>

                
                <div class="shrink-0 text-right pl-3 border-l border-gray-200 dark:border-gray-700">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">
                        From
                    </p>
                    <p class="font-display text-lg sm:text-xl font-semibold text-primary-600 dark:text-primary-400 tabular-nums leading-none">
                        ₱<?php echo e(number_format($property->price, 0)); ?>

                    </p>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">/ unit</p>
                </div>
            </div>
        </div>

        
        <div class="mb-6 sm:mb-8">
            <p class="mb-2 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">
                <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                Reservation
            </p>
            <h1 class="font-display text-2xl sm:text-3xl md:text-4xl font-semibold tracking-tight text-gray-900 dark:text-white [text-wrap:balance]">
                Complete Your <em class="italic text-primary-600 dark:text-primary-400">Booking</em>
            </h1>
        </div>

        
        <div class="flex items-start mb-8 sm:mb-10">
            <?php
                $steps = [];
                $steps[1] = ['Your Details', 'Guest information'];
                $steps[2] = ['Visit Dates', 'Start & end'];

                if ($this->availableServices->isNotEmpty()) {
                    $steps[3] = ['Extras', 'Optional services'];
                    $steps[4] = ['Payment', 'Secure checkout'];
                } else {
                    $steps[3] = ['Payment', 'Secure checkout'];
                }
            ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => [$title, $sub]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <button type="button"
                        @click="goTo(<?php echo e($num); ?>)"
                        :disabled="<?php echo e($num); ?> > step"
                        :aria-current="<?php echo e($num); ?> === step ? 'step' : 'false'"
                        class="flex flex-col items-center min-w-0 flex-1 focus:outline-none group
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-lg transition-all duration-200
                               active:scale-95"
                        :class="{
                            'cursor-pointer': <?php echo e($num); ?> < step,
                            'cursor-default': <?php echo e($num); ?> === step,
                            'cursor-not-allowed opacity-60': <?php echo e($num); ?> > step
                        }">
                    <span class="step-dot"
                          :class="{
                              'done': <?php echo e($num); ?> < step,
                              'active': <?php echo e($num); ?> === step,
                              'pending': <?php echo e($num); ?> > step
                          }"
                          aria-hidden="true">
                        <span x-text="<?php echo e($num); ?> < step ? '✓' : '<?php echo e($num); ?>'"></span>
                    </span>
                    <span class="text-xs font-semibold mt-2.5 text-center"
                          :class="{
                              'text-gray-900 dark:text-white': <?php echo e($num); ?> <= step,
                              'text-gray-500 dark:text-gray-400': <?php echo e($num); ?> > step
                          }">
                        <?php echo e($title); ?>

                    </span>
                    <span class="hidden sm:block text-[10px] text-gray-400 dark:text-gray-500 mt-0.5"><?php echo e($sub); ?></span>
                </button>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($num < count($steps)): ?>
                    <div class="step-connector flex-1 mx-2 mt-[19px]"
                         :class="<?php echo e($num); ?> < step ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700'"
                         aria-hidden="true"></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 6000)"
                 :class="show ? '' : 'hidden'"
                 role="alert"
                 aria-live="polite"
                 class="bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-400/40
                        text-rose-700 dark:text-rose-200 p-4 rounded-2xl text-sm mb-6
                        flex items-start gap-3 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span class="flex-1"><?php echo e(session('error')); ?></span>
                <button type="button"
                        @click="show = false"
                        class="relative inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               before:absolute before:content-[''] before:-inset-2 before:rounded-md
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 shrink-0"
                        aria-label="Dismiss error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-6 lg:gap-8 items-start">

            <div class="space-y-4">

                
                <div :class="step === 1 ? 'step-panel space-y-4' : 'hidden'">

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-4" x-ref="stepHeading1" tabindex="-1">Your Details</h2>

                        <div x-data="{ showFields: <?php echo e(Auth::check() ? 'false' : 'true'); ?> }">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 mb-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm font-bold shrink-0">
                                            <?php echo e(strtoupper(substr(Auth::user()->name, 0, 1))); ?>

                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-gray-900 dark:text-white text-sm font-semibold truncate"><?php echo e(Auth::user()->name); ?></p>
                                            <p class="text-gray-500 dark:text-gray-400 text-xs truncate"><?php echo e(Auth::user()->email); ?></p>
                                        </div>
                                    </div>
                                    <button type="button"
                                            @click="showFields = !showFields"
                                            class="relative text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition active:scale-95
                                                   before:absolute before:content-[''] before:-inset-2 before:rounded-md
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-md px-2 py-1 shrink-0">
                                        <span x-text="showFields ? 'Done' : 'Edit'"></span>
                                    </button>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div :class="showFields ? 'grid grid-cols-1 sm:grid-cols-2 gap-3' : 'hidden'">

                                <div>
                                    <label for="customerName" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Full Name *</label>
                                    <input id="customerName" type="text" wire:model="customerName" placeholder="Your full name"
                                           autocomplete="name"
                                           class="input w-full <?php $__errorArgs = ['customerName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400/50 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-600 dark:text-rose-300 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <p x-cloak :class="errors.name ? 'block' : 'hidden'" x-text="errors.name" class="text-xs text-rose-600 dark:text-rose-300 mt-1"></p>
                                </div>

                                <div>
                                    <label for="customerEmail" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Email *</label>
                                    <input id="customerEmail" type="email" wire:model="customerEmail" placeholder="you@example.com" required
                                           autocomplete="email" inputmode="email"
                                           class="input w-full <?php $__errorArgs = ['customerEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400/50 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-600 dark:text-rose-300 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <p x-cloak :class="errors.email ? 'block' : 'hidden'" x-text="errors.email" class="text-xs text-rose-600 dark:text-rose-300 mt-1"></p>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="customerPhone" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Phone</label>
                                    <input id="customerPhone"
                                           type="tel"
                                           inputmode="numeric"
                                           pattern="[0-9+]*"
                                           maxlength="13"
                                           autocomplete="tel"
                                           wire:model.live.debounce.500ms="customerPhone"
                                           x-on:input="
                                               const cleaned = $event.target.value.replace(/[^0-9+]/g, '');
                                               if (cleaned !== $event.target.value) {
                                                   $event.target.value = cleaned;
                                                   $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                                               }
                                           "
                                           placeholder="09xxxxxxxxx"
                                           class="input w-full <?php $__errorArgs = ['customerPhone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400/50 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerPhone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-600 dark:text-rose-300 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="next()"
                                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <span x-text="nextLabel"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                
                <div :class="step === 2 ? 'step-panel space-y-4' : 'hidden'">

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 sm:p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-4" x-ref="stepHeading2" tabindex="-1">Visit Dates</h2>

                        <div x-data="dateSelector()"
                             data-date-data="<?php echo e($this->dateSelectorDataJson); ?>"
                             class="space-y-5">

                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Quick pick</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="quickSelect('today')"
                                            class="inline-flex items-center gap-1 h-11 sm:h-10 px-3.5 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300
                                                   hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                                   transition-all duration-200 active:scale-95
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        Today
                                    </button>
                                    <button type="button" @click="quickSelect('tomorrow')"
                                            class="inline-flex items-center gap-1 h-11 sm:h-10 px-3.5 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300
                                                   hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                                   transition-all duration-200 active:scale-95
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        Tomorrow
                                    </button>
                                    <button type="button" @click="quickSelect('three-days')"
                                            class="inline-flex items-center gap-1 h-11 sm:h-10 px-3.5 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300
                                                   hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                                   transition-all duration-200 active:scale-95
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        3 days
                                    </button>
                                    <button type="button" @click="quickSelect('weekend')"
                                            class="inline-flex items-center gap-1 h-11 sm:h-10 px-3.5 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300
                                                   hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                                   transition-all duration-200 active:scale-95
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        This weekend
                                    </button>
                                    <button type="button" @click="clearSelection()"
                                            class="relative ml-auto inline-flex items-center gap-1 h-11 sm:h-10 px-3.5 rounded-full text-xs font-semibold text-gray-500 dark:text-gray-400
                                                   hover:text-rose-600 dark:hover:text-rose-400 transition-all duration-200 active:scale-95
                                                   before:absolute before:content-[''] before:-inset-1 before:rounded-full
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Reset
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-[1fr_1fr_auto] gap-2 sm:gap-3">
                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Start</p>
                                        <p class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white truncate"
                                           x-text="checkIn ? formatDate(checkIn) : 'Pick a date'"></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">End</p>
                                        <p class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white truncate"
                                           x-text="checkOut ? formatDate(checkOut) : 'Same day'"></p>
                                    </div>
                                </div>

                                <div x-cloak
                                     :class="hasRange ? 'flex' : 'hidden'"
                                     class="col-span-2 sm:col-span-1 items-center gap-2 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-500/30 rounded-xl px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-0.5">Duration</p>
                                        <p class="text-xs sm:text-sm font-bold text-primary-700 dark:text-primary-300" x-text="durationLabel"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Start Time</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Used for both start and end</p>
                                    </div>
                                </div>
                                <input type="time" wire:model.live="checkInTime" class="input max-w-[140px]" />
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['checkInTime'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <p class="text-xs text-rose-600 dark:text-rose-300 -mt-3"><?php echo e($message); ?></p>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div class="flex items-center justify-between mb-1">
                                <button type="button" @click="prevMonth()" :disabled="!canGoPrevMonth"
                                        class="inline-flex items-center justify-center h-11 w-11 sm:h-10 sm:w-10 rounded-lg text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700
                                               transition-all duration-200 active:scale-95
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               disabled:opacity-30 disabled:cursor-not-allowed
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        aria-label="Previous month">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="currentMonthName + ' ' + currentYear"></span>
                                <button type="button" @click="nextMonth()" :disabled="!canGoNextMonth"
                                        class="inline-flex items-center justify-center h-11 w-11 sm:h-10 sm:w-10 rounded-lg text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700
                                               transition-all duration-200 active:scale-95
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               disabled:opacity-30 disabled:cursor-not-allowed
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        aria-label="Next month">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-7 gap-0.5 sm:gap-1">
                                <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 text-center py-1.5" x-text="day"></span>
                                </template>

                                <template x-for="blank in firstDayOffset" :key="'blank-'+blank">
                                    <span></span>
                                </template>

                                <template x-for="day in daysInMonth" :key="day.iso">
                                    <button type="button"
                                            class="cal-day text-sm flex items-center justify-center
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                            :disabled="day.isDisabled || day.isBooked"
                                            :aria-label="day.isBooked ? 'Unavailable' : ''"
                                            :title="day.isBooked ? 'Unavailable' : ''"
                                            :class="{
                                                'bg-rose-50 dark:bg-rose-900/30 text-rose-300 dark:text-rose-500/60 line-through cursor-not-allowed': day.isBooked,
                                                'bg-primary-600 text-white shadow-md font-bold': !day.isBooked && (day.iso === checkIn || day.iso === checkOut),
                                                'bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200': !day.isBooked && isInRange(day.iso),
                                                'text-gray-300 dark:text-gray-600 cursor-not-allowed': !day.isBooked && day.isDisabled,
                                                'text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer': !day.isBooked && !day.isDisabled && day.iso !== checkIn && day.iso !== checkOut && !isInRange(day.iso)
                                            }"
                                            @click="selectDate(day.iso)">
                                        <span x-text="day.dayNumber"></span>
                                    </button>
                                </template>
                            </div>

                            <p x-cloak
                               :class="error ? 'flex' : 'hidden'"
                               class="items-start gap-2 text-xs text-rose-600 dark:text-rose-300 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 rounded-lg px-3 py-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                                <span x-text="error"></span>
                            </p>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] text-gray-500 dark:text-gray-400 pt-1">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-sm bg-primary-600"></span>
                                    Selected
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-sm bg-primary-100 dark:bg-primary-900/40 border border-primary-300/50"></span>
                                    In range
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-sm bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-500/30"></span>
                                    Unavailable
                                </span>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($this->bookedDateRanges)): ?>
                                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-xl p-3 sm:p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                                        Already booked · <?php echo e(count($this->bookedDateRanges)); ?> <?php echo e(count($this->bookedDateRanges) === 1 ? 'range' : 'ranges'); ?>

                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->bookedDateRanges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $range): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <span <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'range-'.e(md5($range['start'] . '|' . $range['end'])).''; ?>wire:key="range-<?php echo e(md5($range['start'] . '|' . $range['end'])); ?>"
                                                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-[11px] font-medium">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400" aria-hidden="true"></span>
                                                <?php echo e(\Carbon\Carbon::parse($range['start'])->format('M d')); ?> – <?php echo e(\Carbon\Carbon::parse($range['end'])->format('M d')); ?>

                                            </span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <p x-cloak
                       :class="errors.dates ? 'block' : 'hidden'"
                       x-text="errors.dates"
                       class="text-xs text-rose-600 dark:text-rose-300"></p>

                    <div class="flex justify-between gap-3">
                        <button type="button" @click="prev()"
                                class="inline-flex items-center justify-center gap-2 h-11 px-4 sm:px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                            Back
                        </button>
                        <button type="button" @click="next()"
                                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <span x-text="nextLabel"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->availableServices->isNotEmpty()): ?>
                    <div :class="step === 3 ? 'step-panel space-y-4' : 'hidden'">

                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                            <h2 class="font-display text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-1" x-ref="stepHeading3" tabindex="-1">Extra Services</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">Optional add-ons. Tap to add — adjust quantity with + / −.</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                        $qty = (int) ($selectedServices[$service->id] ?? 0);
                                        $isAdded = $qty > 0;
                                    ?>
                                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'service-'.e($service->id).''; ?>wire:key="service-<?php echo e($service->id); ?>"
                                         class="rounded-xl border <?php echo e($isAdded
                                             ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-900/20 dark:border-primary-500/40'
                                             : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900'); ?> transition-colors duration-200">

                                        <button type="button"
                                                wire:click="addService(<?php echo e($service->id); ?>)"
                                                wire:loading.attr="disabled"
                                                wire:target="addService"
                                                class="w-full items-center justify-between gap-3 px-4 py-3 text-left
                                                       transition-all duration-200 active:scale-[0.98]
                                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-xl
                                                       disabled:opacity-60 disabled:cursor-not-allowed
                                                       <?php echo e($isAdded ? 'hidden' : 'flex'); ?>">
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($service->name); ?></span>
                                                <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">₱<?php echo e(number_format($service->price, 2)); ?></span>
                                            </span>
                                            <span class="shrink-0 inline-flex items-center gap-1 h-9 px-3.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-[10px] font-bold uppercase tracking-wider transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                                                Add
                                            </span>
                                        </button>

                                        <div class="items-center justify-between gap-3 px-4 py-3 <?php echo e($isAdded ? 'flex' : 'hidden'); ?>">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($service->name); ?></p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 tabular-nums">
                                                    ₱<?php echo e(number_format($service->price, 2)); ?> × <?php echo e($qty); ?> =
                                                    <span class="font-bold text-primary-600 dark:text-primary-400">₱<?php echo e(number_format($service->price * $qty, 2)); ?></span>
                                                </p>
                                            </div>
                                            <div class="shrink-0 flex items-center gap-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full p-1">
                                                <button type="button"
                                                        wire:click="decrementService(<?php echo e($service->id); ?>)"
                                                        wire:loading.attr="disabled"
                                                        wire:target="decrementService,addService"
                                                        class="relative inline-flex items-center justify-center w-9 h-9 rounded-full text-gray-600 dark:text-gray-300
                                                               hover:bg-gray-100 dark:hover:bg-gray-700
                                                               transition-all duration-200 active:scale-90
                                                               before:absolute before:content-[''] before:-inset-0.5 before:rounded-full
                                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                               disabled:opacity-60 disabled:cursor-not-allowed
                                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                                        aria-label="Remove one <?php echo e($service->name); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"/></svg>
                                                </button>
                                                <span class="inline-flex items-center justify-center min-w-[28px] text-sm font-bold tabular-nums text-gray-900 dark:text-white">
                                                    <?php echo e($qty); ?>

                                                </span>
                                                <button type="button"
                                                        wire:click="addService(<?php echo e($service->id); ?>)"
                                                        wire:loading.attr="disabled"
                                                        wire:target="addService,decrementService"
                                                        class="relative inline-flex items-center justify-center w-9 h-9 rounded-full text-white bg-primary-600
                                                               hover:bg-primary-700
                                                               transition-all duration-200 active:scale-90
                                                               before:absolute before:content-[''] before:-inset-0.5 before:rounded-full
                                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                               disabled:opacity-60 disabled:cursor-not-allowed
                                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                                        aria-label="Add one more <?php echo e($service->name); ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        </div>

                        <div class="flex justify-between gap-3">
                            <button type="button" @click="prev()"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-4 sm:px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                Back
                            </button>
                            <button type="button" @click="next()"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <span x-text="nextLabel"></span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php $paymentStep = $this->availableServices->isNotEmpty() ? 4 : 3; ?>
                <div :class="step === <?php echo e($paymentStep); ?> ? 'step-panel space-y-4' : 'hidden'">

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold tracking-tight text-gray-900 dark:text-white mb-4"
                            x-ref="stepHeading<?php echo e($paymentStep); ?>" tabindex="-1">Payment Method</h2>

                        <div class="mb-5">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Booking Type</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer group relative
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                    <input type="radio" wire:model.live="bookingMode" value="full" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Pay in Full</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">100% online now</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer group relative
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                    <input type="radio" wire:model.live="bookingMode" value="reservation" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5z"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Reserve</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">20% now · rest on arrival</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Pay with</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                                    ['gcash',   'GCash'],
                                    ['paymaya', 'Maya'],
                                    ['card',    'Credit / Debit'],
                                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$val, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <label class="relative cursor-pointer group
                                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
                                           <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'payment-method-'.e($val).''; ?>wire:key="payment-method-<?php echo e($val); ?>">
                                        <input type="radio" wire:model.live="paymentMethod" value="<?php echo e($val); ?>" class="sr-only peer">
                                        <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center transition-all duration-200 peer-hover:border-gray-300 dark:peer-hover:border-gray-600 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2 peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 peer-checked:shadow-md active:scale-[0.98]">
                                            <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-primary-600 dark:text-primary-400 transition-opacity duration-200" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($val === 'gcash'): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-11 h-11" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                                                    <circle cx="16" cy="16" r="16" fill="#007DFE"/>
                                                    <text x="16" y="22" text-anchor="middle" fill="white" font-size="14" font-weight="900" font-family="system-ui,sans-serif">G</text>
                                                </svg>
                                            <?php elseif($val === 'paymaya'): ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-11 h-11" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                                                    <circle cx="16" cy="16" r="16" fill="#111827"/>
                                                    <text x="16" y="22" text-anchor="middle" fill="#00C6D7" font-size="14" font-weight="900" font-family="system-ui,sans-serif">M</text>
                                                </svg>
                                            <?php else: ?>
                                                <div class="w-11 h-11 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                                        <line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                            <p class="text-gray-900 dark:text-white font-semibold text-sm"><?php echo e($label); ?></p>
                                        </div>
                                    </label>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <div>
                                <p class="text-blue-800 dark:text-blue-200 text-sm font-medium">Secure Checkout via PayMongo</p>
                                <p class="text-blue-600 dark:text-blue-300/80 text-xs mt-0.5 leading-relaxed">
                                    You will be redirected to complete your payment securely.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row justify-between gap-4 mt-8">
                            <button type="button" @click="prev()"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold w-full sm:w-auto
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                Back
                            </button>
                            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm w-full sm:w-auto
                                           transition-all duration-200 active:scale-95
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="submit">Proceed to Pay</span>
                                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing…
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            
            <div class="hidden lg:block lg:sticky lg:top-24">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-3xl overflow-hidden shadow-lg">

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property->images->isNotEmpty()): ?>
                        <div class="w-full aspect-[4/3] overflow-hidden">
                            <img src="<?php echo e(asset('storage/'.$property->images->first()->image_path)); ?>"
                                 class="w-full h-full object-cover" alt="<?php echo e($property->name); ?>" loading="lazy" decoding="async">
                        </div>
                    <?php else: ?>
                        <div class="w-full aspect-[4/3] bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-display text-xl font-semibold tracking-tight text-gray-900 dark:text-white leading-tight"><?php echo e($property->name); ?></h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            <?php echo e($property->propertyType->name ?? 'Activity'); ?>

                            <span class="mx-1" aria-hidden="true">·</span>
                            <?php echo e($property->tenant->name); ?>

                        </p>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="font-display text-3xl text-primary-600 dark:text-primary-400 tabular-nums">₱<?php echo e(number_format($property->price, 2)); ?></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">per unit</span>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 space-y-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($check_in && $check_out): ?>
                            <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                <span>Dates</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right tabular-nums">
                                    <?php echo e(\Carbon\Carbon::parse($check_in)->format('M d')); ?> – <?php echo e(\Carbon\Carbon::parse($check_out)->format('M d')); ?>

                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                <span>Start time</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right tabular-nums">
                                    <?php echo e(\Carbon\Carbon::createFromFormat('H:i', $checkInTime)->format('g:i A')); ?>

                                </span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <dl>
                            <div class="flex justify-between items-center text-sm">
                                <dt class="text-gray-600 dark:text-gray-300">
                                    <?php echo e($totalDays); ?> day<?php echo e($totalDays > 1 ? 's' : ''); ?> × ₱<?php echo e(number_format($property->price, 2)); ?>

                                </dt>
                                <dd class="font-semibold text-gray-900 dark:text-white tabular-nums">
                                    ₱<?php echo e(number_format($property->price * $totalDays, 2)); ?>

                                </dd>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $serviceId => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php $svc = $this->selectedServiceModels->get($serviceId); ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svc): ?>
                                    <div class="flex justify-between items-center text-sm mt-2" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'summary-service-'.e($serviceId).''; ?>wire:key="summary-service-<?php echo e($serviceId); ?>">
                                        <dt class="text-gray-600 dark:text-gray-300 truncate max-w-[160px]"><?php echo e($svc->name); ?> ×<?php echo e($qty); ?></dt>
                                        <dd class="font-semibold text-gray-900 dark:text-white shrink-0 tabular-nums">₱<?php echo e(number_format($svc->price * $qty, 2)); ?></dd>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </dl>
                    </div>

                    <div class="p-6">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bookingMode === 'reservation'): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Pay Now (20%)</span>
                                <span class="font-display text-2xl font-semibold text-primary-600 dark:text-primary-400 tabular-nums">
                                    ₱<?php echo e(number_format($reservationFee, 2)); ?>

                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Balance on Arrival</span>
                                <span class="font-display text-lg font-semibold text-gray-900 dark:text-white tabular-nums">
                                    ₱<?php echo e(number_format($balanceOnArrival, 2)); ?>

                                </span>
                            </div>
                        <?php else: ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Total Due</span>
                                <span class="font-display text-3xl font-semibold text-primary-600 dark:text-primary-400 tabular-nums">
                                    ₱<?php echo e(number_format($totalAmount, 2)); ?>

                                </span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div class="px-6 pb-5 flex items-center justify-center gap-2 text-[10px] text-gray-400 dark:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <span class="uppercase tracking-wider">Secured by PayMongo</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50
                bg-white/95 dark:bg-gray-900/95 backdrop-blur-md
                border-t border-gray-200 dark:border-gray-700
                shadow-[0_-4px_20px_rgba(0,0,0,0.08)] p-3 pb-safe">
        <div class="flex items-center justify-between gap-3 max-w-7xl mx-auto">
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <?php echo e($bookingMode === 'reservation' ? 'Pay now (20%)' : 'Total due'); ?>

                </p>
                <p class="font-display text-xl font-bold text-gray-900 dark:text-white leading-tight tabular-nums">
                    ₱<?php echo e(number_format($bookingMode === 'reservation' ? $reservationFee : $totalAmount, 2)); ?>

                </p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($check_in && $check_out): ?>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate tabular-nums">
                        <?php echo e(\Carbon\Carbon::parse($check_in)->format('M d')); ?> → <?php echo e(\Carbon\Carbon::parse($check_out)->format('M d')); ?>

                        · <?php echo e($totalDays); ?> day<?php echo e($totalDays > 1 ? 's' : ''); ?>

                    </p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <button x-show="step < maxStep"
                    type="button"
                    @click="next()"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm shrink-0
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                Continue
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>

            <button x-show="step === maxStep"
                    type="button"
                    wire:click="submit"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm shrink-0
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="submit">Pay Now</span>
                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing…
                </span>
            </button>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework\views/livewire/views/69ae87a0.blade.php ENDPATH**/ ?>