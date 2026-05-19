<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
?>




<div class="relative z-10 min-h-screen">

    
    <section class="relative py-20 md:py-28 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/40 to-transparent pointer-events-none"></div>
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                    bg-emerald-500/[0.06] rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative max-w-7xl mx-auto px-6 md:px-16">
            <div class="flex items-center gap-2 mb-4">
                <span class="w-6 h-px bg-emerald-500"></span>
                <span class="text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-500">
                    Traveller Portal
                </span>
            </div>

            <h1 class="font-display text-4xl md:text-6xl font-semibold text-white leading-none tracking-tight">
                My <em class="italic bg-gradient-to-r from-emerald-400 to-cyan-400
                               bg-clip-text text-transparent not-italic">
                    Reservations
                </em>
            </h1>
            <p class="text-sm text-zinc-500 mt-3 max-w-md">
                All your bookings, stays, and travel history in one place.
            </p>

            
            <?php $c = $this->counts; ?>
            <div class="flex flex-wrap items-center gap-8 mt-10 pt-8 border-t border-white/[0.06]">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    ['Total',     $c['total'],     'text-emerald-400'],
                    ['Pending',   $c['pending'],   'text-amber-400'],
                    ['Confirmed', $c['confirmed'], 'text-blue-400'],
                    ['Completed', $c['completed'], 'text-emerald-400'],
                    ['Cancelled', $c['cancelled'], 'text-red-400'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $val, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="text-center md:text-left">
                        <div class="font-display text-3xl font-bold <?php echo e($color); ?>"><?php echo e($val); ?></div>
                        <div class="text-[10px] uppercase tracking-[0.18em] text-zinc-600 mt-1"><?php echo e($label); ?></div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->last): ?>
                        <div class="hidden md:block w-px h-10 bg-white/[0.06]"></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
    </section>

    
    <div class="sticky top-[64px] z-20 bg-[rgba(10,14,24,0.92)] backdrop-blur-xl border-b border-white/[0.06]">
        <div class="max-w-7xl mx-auto px-6 md:px-16 py-3 flex gap-2 overflow-x-auto scrollbar-none">
            <?php
                $filterOptions = [
                    ''           => ['All',        $c['total']],
                    'pending'    => ['Pending',     $c['pending']],
                    'confirmed'  => ['Confirmed',   $c['confirmed']],
                    'checked_in' => ['Checked In',  null],
                    'completed'  => ['Completed',   $c['completed']],
                    'cancelled'  => ['Cancelled',   $c['cancelled']],
                ];
            ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => [$label, $count]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <button wire:click="$set('statusFilter','<?php echo e($val); ?>')"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded-full
                               text-[11px] font-bold uppercase tracking-wider transition-all border
                               <?php echo e($statusFilter === $val
                                   ? 'bg-emerald-600 border-emerald-600 text-white shadow-lg shadow-emerald-500/20'
                                   : 'border-white/[0.08] text-zinc-600 hover:border-white/20 hover:text-white'); ?>">
                    <?php echo e($label); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($count !== null && $count > 0): ?>
                        <span class="inline-flex items-center justify-center min-w-[16px] h-4 px-1
                                     rounded-full text-[9px] font-bold
                                     <?php echo e($statusFilter === $val ? 'bg-white/20 text-white' : 'bg-white/[0.06] text-zinc-500'); ?>">
                            <?php echo e($count); ?>

                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>

    
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-10 pb-24">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->isEmpty()): ?>
            
            <div class="text-center py-24">
                <div class="relative inline-flex mb-8">
                    <div class="w-20 h-20 rounded-2xl bg-white/[0.03] border border-white/[0.06]
                                flex items-center justify-center">
                        <svg class="w-9 h-9 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0
                                     00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-semibold text-white/40">
                    <?php echo e($statusFilter ? 'No ' . $this->statusLabel($statusFilter) . ' bookings' : 'No reservations yet'); ?>

                </h3>
                <p class="text-sm text-zinc-600 mt-2 max-w-sm mx-auto">
                    <?php echo e($statusFilter
                        ? 'Try a different filter or clear it to see all bookings.'
                        : 'Your travel story starts with your first reservation.'); ?>

                </p>
                <div class="flex items-center justify-center gap-3 mt-8">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusFilter): ?>
                        <button wire:click="$set('statusFilter','')"
                                class="px-6 py-2.5 rounded-full border border-white/10 text-white/60
                                       hover:border-white/30 hover:text-white text-sm font-semibold
                                       uppercase tracking-wider transition">
                            ← Clear Filter
                        </button>
                    <?php else: ?>
                        <a href="<?php echo e(route('explore.map')); ?>" wire:navigate
                           class="px-6 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white
                                  text-sm font-semibold uppercase tracking-wider transition
                                  shadow-lg shadow-emerald-500/20">
                            Explore Map
                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $property     = $booking->items->first()?->property;
                        $businessName = $property?->tenant?->name ?? 'Business';
                        $businessSlug = $property?->tenant?->slug;
                        $paid         = $booking->payments->where('payment_status','paid')->sum('amount');
                        $balance      = $booking->total_amount - $paid;
                        $paidPct      = $booking->total_amount > 0
                                          ? min(100, ($paid / $booking->total_amount) * 100)
                                          : 0;
                        $nights       = $booking->check_in && $booking->check_out
                                          ? max(1, $booking->check_in->diffInDays($booking->check_out))
                                          : 0;
                        $imagePath    = $property?->images?->first()?->image_path;
                        $status       = $booking->status;
                        $stepIdx      = $this->statusStepIndex($status);
                        $isCancelled  = $status === 'cancelled';
                        $isCompleted  = $status === 'completed';
                    ?>

                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'bk-'.e($booking->id).''; ?>wire:key="bk-<?php echo e($booking->id); ?>"
                         x-data="{ expanded: false }"
                         class="rounded-2xl overflow-hidden border border-white/[0.06] bg-white/[0.025]
                                hover:bg-white/[0.035] transition-colors duration-200 group">

                        
                        <div class="grid grid-cols-1 sm:grid-cols-[auto_1fr_auto]">

                            
                            <div class="relative sm:w-44 h-40 sm:h-auto overflow-hidden flex-shrink-0">
                                <div class="absolute left-0 top-0 bottom-0 w-1 z-10 <?php echo e($this->statusAccentClass($status)); ?>"></div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($imagePath): ?>
                                    <img src="<?php echo e(asset('storage/'.$imagePath)); ?>"
                                         alt="<?php echo e($property?->name); ?>"
                                         class="w-full h-full object-cover brightness-90
                                                group-hover:brightness-100 transition-all duration-300"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="w-full h-full bg-white/[0.03] flex items-center justify-center">
                                        <svg class="w-10 h-10 text-zinc-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9
                                                     0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0
                                                     011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            
                            <div class="px-5 py-4 flex flex-col min-w-0">
                                
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-zinc-600">
                                        #<?php echo e($booking->booking_reference); ?>

                                    </span>
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full
                                                 <?php echo e($this->statusBadgeClasses($status)); ?>">
                                        <?php echo e($this->statusLabel($status)); ?>

                                    </span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nights > 0): ?>
                                        <span class="text-[11px] px-2.5 py-0.5 rounded-full
                                                     bg-white/[0.05] border border-white/[0.08] text-zinc-400">
                                            <?php echo e($nights); ?> night<?php echo e($nights !== 1 ? 's' : ''); ?>

                                        </span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>

                                <h3 class="text-lg font-semibold text-white leading-tight truncate">
                                    <?php echo e($property?->name ?? 'Booking'); ?>

                                </h3>
                                <p class="text-xs text-zinc-500 mt-0.5"><?php echo e($businessName); ?></p>

                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-zinc-600 mb-0.5">Check-in</span>
                                        <span class="font-medium text-white">
                                            <?php echo e($booking->check_in?->format('M d, Y') ?? '—'); ?>

                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-zinc-600 mb-0.5">Check-out</span>
                                        <span class="font-medium text-white">
                                            <?php echo e($booking->check_out?->format('M d, Y') ?? '—'); ?>

                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-zinc-600 mb-0.5">Total</span>
                                        <span class="font-medium text-white">
                                            ₱<?php echo e(number_format($booking->total_amount, 2)); ?>

                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-zinc-600 mb-0.5">Paid</span>
                                        <span class="font-semibold <?php echo e($balance > 0 ? 'text-amber-400' : 'text-emerald-400'); ?>">
                                            ₱<?php echo e(number_format($paid, 2)); ?>

                                        </span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                            <span class="block text-[10px] text-red-400 mt-0.5">
                                                ₱<?php echo e(number_format($balance, 2)); ?> due
                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>

                                
                                <div class="mt-4">
                                    <div class="flex justify-between items-center text-[10px] uppercase tracking-wider
                                                text-zinc-600 mb-1.5">
                                        <span>Payment Progress</span>
                                        <span><?php echo e(round($paidPct)); ?>%</span>
                                    </div>
                                    <div class="w-full h-1 bg-white/[0.06] rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-700"
                                             style="width:<?php echo e($paidPct); ?>%;
                                                    background:<?php echo e($paidPct >= 100 ? '#34d399' : '#fbbf24'); ?>;"></div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="px-4 py-4 border-t sm:border-t-0 sm:border-l border-white/[0.05]
                                        flex flex-col justify-between items-stretch gap-2 min-w-[152px]">

                                
                                <button @click="expanded = !expanded"
                                        class="w-full flex items-center justify-center gap-1.5 px-4 py-2
                                               rounded-xl border border-white/[0.08] bg-white/[0.03]
                                               text-[11px] font-bold uppercase tracking-wider text-zinc-400
                                               hover:bg-white/[0.06] hover:text-white transition">
                                    <span x-text="expanded ? 'Hide Details' : 'View Details'"></span>
                                    <svg class="w-3 h-3 transition-transform duration-200"
                                         :class="expanded ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($businessSlug): ?>
                                    
                                    <a href="<?php echo e(route('business.offerings', $businessSlug)); ?>" wire:navigate
                                       class="w-full px-4 py-2 rounded-xl border border-white/[0.08]
                                              text-center text-[11px] font-bold uppercase tracking-wider
                                              text-zinc-400 hover:text-white hover:bg-white/[0.06] transition">
                                        View Spot
                                    </a>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0 && in_array($status, ['pending','confirmed'])): ?>
                                        <a href="<?php echo e(route('business.offerings', $businessSlug)); ?>" wire:navigate
                                           class="w-full px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500
                                                  text-center text-[11px] font-bold uppercase tracking-wider
                                                  text-white transition shadow-lg shadow-emerald-500/15">
                                            Pay Balance
                                        </a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCompleted): ?>
                                    <button onclick="window.print()"
                                            class="w-full px-4 py-2 rounded-xl border border-white/[0.08]
                                                   text-center text-[11px] font-bold uppercase tracking-wider
                                                   text-zinc-500 hover:text-white hover:border-white/20 transition">
                                        Receipt
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <span class="text-center text-[10px] text-zinc-700">
                                    <?php echo e($booking->created_at?->format('M d, Y')); ?>

                                </span>
                            </div>
                        </div>

                        
                        <div x-show="expanded"
                             x-transition:enter="transition ease-out duration-250"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-1"
                             class="border-t border-white/[0.05] px-5 py-5 grid grid-cols-1 lg:grid-cols-3 gap-6">

                            
                            <div class="lg:col-span-3">
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-600 mb-4">
                                    Booking Journey
                                </p>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCancelled): ?>
                                    
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-emerald-500/40
                                                        flex items-center justify-center text-[10px] text-emerald-400">✓</div>
                                            <span class="text-xs text-zinc-500">Placed</span>
                                        </div>
                                        <div class="flex-1 h-px bg-red-500/30 relative">
                                            <div class="absolute inset-y-0 left-0 right-0 flex items-center justify-center">
                                                <span class="text-[9px] text-red-400 bg-[rgba(10,14,24,1)] px-2">Cancelled</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-6 h-6 rounded-full bg-red-500/20 border border-red-500/40
                                                        flex items-center justify-center text-[10px] text-red-400">✕</div>
                                            <span class="text-xs text-zinc-500">Cancelled</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php
                                        $timelineSteps = [
                                            ['pending',    'Pending',    'Booking received'],
                                            ['confirmed',  'Confirmed',  'Vendor confirmed'],
                                            ['checked_in', 'Checked In', 'Guest arrived'],
                                            ['completed',  'Completed',  'Stay complete'],
                                        ];
                                    ?>
                                    <div class="flex items-start">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $timelineSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => [$sKey, $sLabel, $sDesc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php
                                                $isPast    = $i < $stepIdx;
                                                $isCurrent = $i === $stepIdx;
                                                $isFuture  = $i > $stepIdx;
                                            ?>
                                            <div class="flex flex-col items-center flex-1">
                                                
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->first): ?>
                                                    <div class="w-full h-px mt-3 mb-1 -mx-1
                                                                <?php echo e($isPast || $isCurrent ? 'bg-emerald-500/50' : 'bg-white/[0.06]'); ?>"></div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>

                                            
                                            <div class="flex flex-col items-center shrink-0 w-20">
                                                <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center
                                                            text-[10px] font-bold transition-all
                                                            <?php echo e($isPast    ? 'bg-emerald-500 border-emerald-500 text-white' : ''); ?>

                                                            <?php echo e($isCurrent ? 'bg-transparent border-emerald-500 text-emerald-400' : ''); ?>

                                                            <?php echo e($isFuture  ? 'bg-transparent border-zinc-700   text-zinc-600' : ''); ?>">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPast): ?> ✓
                                                    <?php elseif($isCurrent): ?> ●
                                                    <?php else: ?> ○
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                                <span class="mt-1.5 text-[10px] font-bold text-center leading-tight
                                                             <?php echo e($isCurrent ? 'text-emerald-400' : ($isPast ? 'text-zinc-400' : 'text-zinc-700')); ?>">
                                                    <?php echo e($sLabel); ?>

                                                </span>
                                                <span class="text-[9px] text-zinc-700 text-center leading-tight mt-0.5 hidden md:block">
                                                    <?php echo e($sDesc); ?>

                                                </span>
                                            </div>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$loop->last): ?>
                                                <div class="flex-1 h-px mt-3
                                                            <?php echo e($isPast ? 'bg-emerald-500/50' : 'bg-white/[0.06]'); ?>"></div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            
                            <div class="lg:col-span-2">
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-600 mb-3">
                                    Items Booked
                                </p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->items->isEmpty()): ?>
                                    <p class="text-xs text-zinc-600">No items on record.</p>
                                <?php else: ?>
                                    <div class="space-y-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <div class="flex items-center justify-between py-2.5 px-3
                                                        rounded-xl bg-white/[0.025] border border-white/[0.05]">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-medium text-white truncate">
                                                        <?php echo e($item->property?->name ?? 'Service'); ?>

                                                    </p>
                                                    <p class="text-[11px] text-zinc-600 mt-0.5">
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->quantity && $item->quantity > 1): ?>
                                                            <?php echo e($item->quantity); ?>× ·
                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        <?php echo e($item->property?->tenant?->name ?? ''); ?>

                                                    </p>
                                                </div>
                                                <span class="text-sm font-semibold text-white ml-4 flex-shrink-0">
                                                    ₱<?php echo e(number_format($item->amount ?? 0, 2)); ?>

                                                </span>
                                            </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                        <div class="flex justify-between items-center pt-2 px-3">
                                            <span class="text-[11px] uppercase tracking-wider text-zinc-600">Total</span>
                                            <span class="text-base font-bold text-white">
                                                ₱<?php echo e(number_format($booking->total_amount, 2)); ?>

                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-600 mb-3">
                                    Payment History
                                </p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->payments->isEmpty()): ?>
                                    <div class="rounded-xl bg-amber-500/[0.06] border border-amber-500/20 p-3">
                                        <p class="text-xs text-amber-400 font-semibold">No payments recorded yet.</p>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($businessSlug && $balance > 0): ?>
                                            <a href="<?php echo e(route('business.offerings', $businessSlug)); ?>"
                                               wire:navigate
                                               class="inline-block mt-2 text-[11px] text-emerald-400
                                                      hover:text-emerald-300 underline transition">
                                                Make a payment →
                                            </a>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <div class="flex items-center justify-between py-2 px-3
                                                        rounded-xl bg-white/[0.025] border border-white/[0.05]">
                                                <div>
                                                    <p class="text-xs font-semibold text-white capitalize">
                                                        <?php echo e($payment->payment_method ?? 'Payment'); ?>

                                                    </p>
                                                    <p class="text-[10px] text-zinc-600">
                                                        <?php echo e($payment->created_at?->format('M d, Y')); ?>

                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-sm font-bold
                                                                 <?php echo e($payment->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400'); ?>">
                                                        ₱<?php echo e(number_format($payment->amount, 2)); ?>

                                                    </span>
                                                    <p class="text-[9px] uppercase tracking-wider
                                                               <?php echo e($payment->payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600'); ?> mt-0.5">
                                                        <?php echo e($payment->payment_status); ?>

                                                    </p>
                                                </div>
                                            </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                        
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                            <div class="flex items-center justify-between py-2 px-3
                                                        rounded-xl bg-red-500/[0.06] border border-red-500/20">
                                                <span class="text-xs text-red-400 font-bold uppercase tracking-wider">
                                                    Balance Due
                                                </span>
                                                <span class="text-sm font-bold text-red-300">
                                                    ₱<?php echo e(number_format($balance, 2)); ?>

                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-center gap-2 py-2 px-3
                                                        rounded-xl bg-emerald-500/[0.06] border border-emerald-500/20">
                                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none"
                                                     stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                <span class="text-xs text-emerald-400 font-bold uppercase tracking-wider">
                                                    Fully Paid
                                                </span>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->notes): ?>
                                    <div class="mt-4">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-600 mb-2">
                                            Notes
                                        </p>
                                        <p class="text-xs text-zinc-500 bg-white/[0.025] border border-white/[0.05]
                                                  rounded-xl px-3 py-2.5 leading-relaxed">
                                            <?php echo e($booking->notes); ?>

                                        </p>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/bba5cc3c.blade.php ENDPATH**/ ?>