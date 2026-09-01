<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
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

<div class="relative z-10 min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100"
     x-data="{
         step: 1,
         maxStep: <?php echo e($this->availableServices->isNotEmpty() ? 4 : 3); ?>,
         errors: {},
         next() {
             if (this.step === 1) {
                 if (!this.$wire.customerName.trim()) {
                     this.errors.name = 'Full name is required.';
                 } else {
                     delete this.errors.name;
                 }
                 if (!this.$wire.customerEmail.trim()) {
                     this.errors.email = 'Email is required.';
                 } else {
                     delete this.errors.email;
                 }
                 if (Object.keys(this.errors).length > 0) return;
             }
             if (this.step === 2) {
                 if (!this.$wire.check_in || !this.$wire.check_out) {
                     this.errors.dates = 'Please select both check-in and check-out dates.';
                     return;
                 } else {
                     delete this.errors.dates;
                 }
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
             if (s <= this.maxStep && s !== this.step) {
                 this.step = s;
                 this.$nextTick(() => this.$refs['stepHeading' + this.step]?.focus());
             }
         }
     }"
     @keydown.arrow-right.window="next()"
     @keydown.arrow-left.window="prev()">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-32 lg:pb-12">

        
        <div class="mb-6">
            <a href="<?php echo e(route('tenant.show', $property->tenant->slug)); ?>" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/></svg>
                Back to <?php echo e($property->tenant->name); ?>

            </a>
        </div>

        
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Reservation</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Complete Your <em class="italic text-primary-600 dark:text-primary-400">Booking</em>
            </h1>
        </div>

        
        <div class="flex items-center mb-10">
            <?php
                $steps = [];
                $steps[1] = ['Your Details', 'Guest information'];
                $steps[2] = ['Visit Dates', 'Check-in & out'];
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
                        :disabled="<?php echo e($num); ?> > maxStep"
                        class="flex flex-col items-center min-w-0 flex-1 focus:outline-none group active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-lg transition-all duration-200"
                        :class="<?php echo e($num); ?> <= maxStep ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'">
                    <span class="step-dot"
                          :class="{
                            'done': <?php echo e($num); ?> < step,
                            'active': <?php echo e($num); ?> === step,
                            'pending': <?php echo e($num); ?> > step
                          }">
                        <template x-if="<?php echo e($num); ?> < step">✓</template>
                        <template x-if="<?php echo e($num); ?> >= step"><?php echo e($num); ?></template>
                    </span>
                    <span class="text-xs font-semibold mt-2 text-center"
                          :class="{
                            'text-gray-900 dark:text-white': <?php echo e($num); ?> <= step,
                            'text-gray-500 dark:text-gray-400': <?php echo e($num); ?> > step
                          }">
                        <?php echo e($title); ?>

                    </span>
                    <span class="hidden sm:block text-[10px] text-gray-400 dark:text-gray-500"><?php echo e($sub); ?></span>
                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($num < count($steps)): ?>
                    <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700 mx-2 mt-4"></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
            <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-400/40 text-red-700 dark:text-red-200 p-4 rounded-2xl text-sm mb-6 flex items-start gap-3">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <?php echo e(session('error')); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-8 items-start">

            
            <div class="space-y-4">

                
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading1" tabindex="-1">Your Details</h2>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                            <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                        <?php echo e(strtoupper(substr(Auth::user()->name, 0, 1))); ?>

                                    </div>
                                    <div>
                                        <p class="text-gray-900 dark:text-white text-sm font-semibold"><?php echo e(Auth::user()->name); ?></p>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs"><?php echo e(Auth::user()->email); ?></p>
                                    </div>
                                </div>
                                <button type="button"
                                        onclick="document.getElementById('extra-guest-fields').classList.toggle('hidden')"
                                        class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-md px-2 py-1">
                                    Edit
                                </button>
                            </div>
                            <div id="extra-guest-fields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php else: ?>
                            <div id="extra-guest-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Full Name *</label>
                                <input type="text" wire:model="customerName" placeholder="Your full name"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200 <?php $__errorArgs = ['customerName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400/50 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 dark:text-red-300 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <p x-show="errors.name" x-text="errors.name" class="text-xs text-red-600 dark:text-red-300 mt-1"></p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Email *</label>
                                <input type="email" wire:model="customerEmail" placeholder="you@example.com" required
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200 <?php $__errorArgs = ['customerEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-red-400/50 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 dark:text-red-300 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <p x-show="errors.email" x-text="errors.email" class="text-xs text-red-600 dark:text-red-300 mt-1"></p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Phone</label>
                                <input type="tel" wire:model="customerPhone" placeholder="+63 9xx xxx xxxx"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">Continue →</button>
                    </div>
                </div>

                
                <div x-show="step === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading2" tabindex="-1">Visit Dates</h2>

                        
                        <div x-data="dateSelector()" x-init="init()" class="space-y-4">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3">
                                    <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Check-in</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="checkIn ? formatDate(checkIn) : 'Select date'"></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3">
                                    <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Check-out</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="checkOut ? formatDate(checkOut) : 'Select date'"></p>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="flex items-center justify-between mb-2">
                                <button type="button" @click="prevMonth()"
                                        class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="currentMonthName + ' ' + currentYear"></span>
                                <button type="button" @click="nextMonth()"
                                        class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>

                            
                            <div class="grid grid-cols-7 gap-1 text-center">
                                <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                                    <span class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500 py-1" x-text="day"></span>
                                </template>
                                <template x-for="blank in firstDayOffset" :key="'blank-'+blank">
                                    <span></span>
                                </template>
                                <template x-for="day in daysInMonth" :key="day.date">
                                    <button type="button"
                                            @click="selectDate(day.date)"
                                            :disabled="day.isDisabled"
                                            :class="{
                                                'bg-primary-600 text-white shadow-md': day.date === checkIn || day.date === checkOut,
                                                'bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200': isInRange(day.date) && day.date !== checkIn && day.date !== checkOut,
                                                'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300 cursor-not-allowed': day.isBooked,
                                                'hover:bg-gray-100 dark:hover:bg-gray-700': !day.isDisabled && day.date !== checkIn && day.date !== checkOut,
                                                'text-gray-300 dark:text-gray-600 cursor-not-allowed': day.isDisabled,
                                                'text-gray-900 dark:text-white': !day.isDisabled && day.date !== checkIn && day.date !== checkOut
                                            }"
                                            class="h-9 rounded-xl text-sm font-medium transition-all duration-200 active:scale-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                            :title="day.isBooked ? 'Unavailable' : ''">
                                        <span x-text="day.dayNumber"></span>
                                    </button>
                                </template>
                            </div>

                            
                            <p x-show="error" x-text="error" class="text-xs text-red-500 mt-2"></p>

                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($this->bookedDateRanges)): ?>
                                <div class="mt-5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-400/30 rounded-xl p-4">
                                    <h4 class="text-[10px] font-bold uppercase tracking-wider text-red-700 dark:text-red-300 mb-2 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Already Booked Dates
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->bookedDateRanges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $range): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-white dark:bg-gray-800 border border-red-200 dark:border-red-400/30 text-red-700 dark:text-red-300 text-xs font-medium">
                                                <?php echo e(\Carbon\Carbon::parse($range['start'])->format('M d')); ?> – <?php echo e(\Carbon\Carbon::parse($range['end'])->format('M d')); ?>

                                            </span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mt-5 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    All dates are currently open for booking.
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <p x-show="errors.dates" x-text="errors.dates" class="text-xs text-red-600 dark:text-red-300"></p>

                    <div class="flex justify-between">
                        <button type="button" @click="prev()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50">← Back</button>
                        <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">Continue →</button>
                    </div>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->availableServices->isNotEmpty()): ?>
                <div x-show="step === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading3" tabindex="-1">Extra Services</h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php $isAdded = isset($selectedServices[$service->id]); ?>
                                <button type="button"
                                        wire:click="<?php echo e($isAdded ? 'removeService' : 'addService'); ?>(<?php echo e($service->id); ?>)"
                                        class="flex items-center justify-between gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 <?php echo e($isAdded ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : ''); ?>">
                                    <span><?php echo e($service->name); ?></span>
                                    <span class="font-bold text-xs"><?php echo e($isAdded ? '✓ Added' : '+₱'.number_format($service->price, 0)); ?></span>
                                </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedServices)): ?>
                            <div class="rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="py-2 px-4 text-left text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">Service</th>
                                            <th class="py-2 px-4 text-center text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">Qty</th>
                                            <th class="py-2 px-4 text-right text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">Subtotal</th>
                                            <th class="py-2 px-3 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $serviceId => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php $svc = App\Models\Service::withoutGlobalScope(TenantScope::class)->find($serviceId); ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svc): ?>
                                                <tr class="border-b border-gray-100 dark:border-gray-700 last:border-0">
                                                    <td class="py-2.5 px-4 text-gray-700 dark:text-gray-200"><?php echo e($svc->name); ?></td>
                                                    <td class="py-2.5 px-4 text-center text-gray-500 dark:text-gray-400"><?php echo e($qty); ?></td>
                                                    <td class="py-2.5 px-4 text-right text-gray-900 dark:text-white font-medium">₱<?php echo e(number_format($svc->price * $qty, 2)); ?></td>
                                                    <td class="py-2.5 px-3">
                                                        <button type="button" wire:click="removeService(<?php echo e($serviceId); ?>)"
                                                                class="w-5 h-5 rounded-full border border-red-300 dark:border-red-500/40 text-red-500 dark:text-red-300 hover:bg-red-500 hover:text-white hover:border-transparent inline-flex items-center justify-center transition-all text-[11px] active:scale-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="flex justify-between">
                        <button type="button" @click="prev()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50">← Back</button>
                        <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">Continue →</button>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <div x-show="step === <?php echo e($this->availableServices->isNotEmpty() ? 4 : 3); ?>" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="<?php echo e($this->availableServices->isNotEmpty() ? 'stepHeading4' : 'stepHeading3'); ?>" tabindex="-1">Payment Method</h2>

                        
                        <div class="mb-4">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Booking Type</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer group">
                                    <input type="radio" wire:model.live="bookingMode" value="full" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg class="w-8 h-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Book Now</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pay 100% online</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" wire:model.live="bookingMode" value="reservation" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg class="w-8 h-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5z"/></svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Reserve</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pay 20% reservation fee</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                                ['gcash', 'GCash'],
                                ['paymaya', 'Maya'],
                                ['card', 'Credit / Debit'],
                            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$val, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <label class="relative cursor-pointer group">
                                    <input type="radio" wire:model.live="paymentMethod" value="<?php echo e($val); ?>" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center transition-all duration-200 peer-hover:border-gray-300 dark:peer-hover:border-gray-600 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2 peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 peer-checked:shadow-md active:scale-[0.98]">
                                        <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-primary-600 dark:text-primary-400 transition-opacity duration-200">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        </div>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($val === 'gcash'): ?>
                                            <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="16" fill="#007DFE"/><text x="16" y="21" text-anchor="middle" fill="white" font-size="13" font-weight="900" font-family="sans-serif">G</text></svg>
                                        <?php elseif($val === 'paymaya'): ?>
                                            <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="16" fill="#111827"/><text x="16" y="21" text-anchor="middle" fill="#00C6D7" font-size="13" font-weight="900" font-family="sans-serif">M</text></svg>
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/><line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/></svg>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm"><?php echo e($label); ?></p>
                                    </div>
                                </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>

                        
                        <div class="mt-5 flex items-start gap-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <div>
                                <p class="text-blue-800 dark:text-blue-200 text-sm font-medium">Secure Checkout via PayMongo</p>
                                <p class="text-blue-600 dark:text-blue-300/80 text-xs mt-0.5 leading-relaxed">
                                    You will be redirected to complete your payment securely.
                                </p>
                            </div>
                        </div>

                        
                        <div class="flex flex-col-reverse sm:flex-row justify-between gap-4 mt-8">
                            <button type="button" @click="prev()" class="w-full sm:w-auto px-6 py-3 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50 active:scale-95">
                                ← Back
                            </button>
                            <button wire:click="submit" wire:loading.attr="disabled"
                                    class="relative w-full sm:w-auto px-8 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition-all disabled:opacity-70 disabled:cursor-not-allowed shadow-lg shadow-primary-500/30 hover:shadow-primary-500/50 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] flex items-center justify-center min-w-[200px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <span wire:loading.remove>Proceed to Pay</span>
                                <span wire:loading class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
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
                        <div class="w-full h-36 rounded-t-3xl overflow-hidden">
                            <img src="<?php echo e(asset('storage/'.$property->images->first()->image_path)); ?>"
                                 class="w-full h-full object-cover" alt="<?php echo e($property->name); ?>">
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-display text-xl font-semibold text-gray-900 dark:text-white leading-tight"><?php echo e($property->name); ?></h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5"><?php echo e($property->propertyType->name ?? 'Activity'); ?> · <?php echo e($property->tenant->name); ?></p>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="font-display text-3xl text-primary-600 dark:text-primary-400">₱<?php echo e(number_format($property->price, 2)); ?></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">per unit</span>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 space-y-3">
                        <dl>
                            <div class="flex justify-between items-center text-sm">
                                <dt class="text-gray-600 dark:text-gray-300"><?php echo e($totalDays); ?> day<?php echo e($totalDays > 1 ? 's' : ''); ?> × ₱<?php echo e(number_format($property->price, 2)); ?></dt>
                                <dd class="font-semibold text-gray-900 dark:text-white">₱<?php echo e(number_format($property->price * $totalDays, 2)); ?></dd>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $serviceId => $qty): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php $svc = App\Models\Service::withoutGlobalScope(TenantScope::class)->find($serviceId); ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($svc): ?>
                                    <div class="flex justify-between items-center text-sm mt-2">
                                        <dt class="text-gray-600 dark:text-gray-300 truncate max-w-[160px]"><?php echo e($svc->name); ?> ×<?php echo e($qty); ?></dt>
                                        <dd class="font-semibold text-gray-900 dark:text-white shrink-0">₱<?php echo e(number_format($svc->price * $qty, 2)); ?></dd>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </dl>
                    </div>

                    <div class="p-6">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bookingMode === 'reservation'): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Pay Now (20%)</span>
                                <span class="font-display text-2xl font-semibold text-primary-600 dark:text-primary-400">₱<?php echo e(number_format($reservationFee, 2)); ?></span>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Balance on Arrival</span>
                                <span class="font-display text-lg font-semibold text-gray-900 dark:text-white">₱<?php echo e(number_format($balanceOnArrival, 2)); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Total Due</span>
                                <span class="font-display text-3xl font-semibold text-primary-600 dark:text-primary-400">₱<?php echo e(number_format($totalAmount, 2)); ?></span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-lg p-4">
        <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Total due</p>
                <p class="font-display text-xl font-bold text-gray-900 dark:text-white">
                    ₱<?php echo e(number_format($bookingMode === 'reservation' ? $reservationFee : $totalAmount, 2)); ?>

                </p>
            </div>
            <button type="button" @click="goTo(<?php echo e($this->availableServices->isNotEmpty() ? 4 : 3); ?>)"
                    class="shrink-0 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition shadow-lg shadow-primary-500/30 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Review
            </button>
        </div>
    </div>

    <style>
        .step-dot {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800;
            transition: all .35s cubic-bezier(.34,1.56,.64,1);
            flex-shrink: 0;
        }
        .step-dot.done { background: #16a34a; color: #fff; box-shadow: 0 0 0 4px rgba(22,163,74,.2); }
        .step-dot.active { background: #22c55e; color: #fff; box-shadow: 0 0 0 5px rgba(34,197,94,.25); }
        .step-dot.pending { background: #e5e7eb; color: #6b7280; border: 1px solid #d1d5db; }
        .dark .step-dot.pending { background: #374151; color: #e5e7eb; border-color: #6b7280; }
    </style>

    <script>
        function dateSelector() {
            return {
                checkIn: <?php echo json_encode($check_in, 15, 512) ?>,
                checkOut: <?php echo json_encode($check_out, 15, 512) ?>,
                bookedDates: <?php echo json_encode($this->bookedDatesArray, 15, 512) ?>,
                today: <?php echo json_encode(now()->format('Y-m-d'), 15, 512) ?>,
                maxDate: <?php echo json_encode(now()->addDays(30)->format('Y-m-d'), 15, 512) ?>,
                currentMonth: new Date().getMonth(),
                currentYear: new Date().getFullYear(),
                error: '',

                init() {
                    this.$watch('checkIn', value => this.syncToLivewire('check_in', value));
                    this.$watch('checkOut', value => this.syncToLivewire('check_out', value));
                },

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr + 'T00:00:00');
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                },

                syncToLivewire(key, value) {
                    if (value) {
                        this.$wire.set(key, value, false);
                    }
                },

                isBooked(dateStr) {
                    return this.bookedDates.includes(dateStr);
                },

                isPast(dateStr) {
                    return dateStr < this.today;
                },

                isBeyondMax(dateStr) {
                    return dateStr > this.maxDate;
                },

                isInRange(dateStr) {
                    if (!this.checkIn || !this.checkOut) return false;
                    return dateStr > this.checkIn && dateStr < this.checkOut;
                },

                get daysInMonth() {
                    const year = this.currentYear;
                    const month = this.currentMonth;
                    const days = [];
                    const totalDays = new Date(year, month + 1, 0).getDate();
                    for (let day = 1; day <= totalDays; day++) {
                        const dateObj = new Date(year, month, day);
                        const dateStr = dateObj.toISOString().slice(0, 10);
                        days.push({
                            date: dateStr,
                            dayNumber: day,
                            isBooked: this.isBooked(dateStr),
                            isDisabled: this.isPast(dateStr) || this.isBeyondMax(dateStr),
                        });
                    }
                    return days;
                },

                get firstDayOffset() {
                    return new Date(this.currentYear, this.currentMonth, 1).getDay();
                },

                get currentMonthName() {
                    return new Date(this.currentYear, this.currentMonth).toLocaleDateString('en-US', { month: 'long' });
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
                },

                selectDate(dateStr) {
                    if (this.isBooked(dateStr) || this.isPast(dateStr) || this.isBeyondMax(dateStr)) return;

                    if (!this.checkIn || (this.checkIn && this.checkOut)) {
                        this.checkIn = dateStr;
                        this.checkOut = '';
                        this.error = '';
                    } else {
                        if (dateStr > this.checkIn) {
                            let start = new Date(this.checkIn + 'T00:00:00');
                            let end = new Date(dateStr + 'T00:00:00');
                            for (let d = start; d <= end; d.setDate(d.getDate() + 1)) {
                                if (this.isBooked(d.toISOString().slice(0, 10))) {
                                    this.error = 'Selected range includes booked dates. Please choose different dates.';
                                    return;
                                }
                            }
                            this.checkOut = dateStr;
                            this.error = '';
                        } else {
                            this.checkIn = dateStr;
                            this.checkOut = '';
                            this.error = '';
                        }
                    }
                },
            };
        }
    </script>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/4884e348.blade.php ENDPATH**/ ?>