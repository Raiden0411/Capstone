<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
?>

<div class="relative z-10 min-h-screen py-8">
    
    <section class="relative py-20 md:py-28 overflow-hidden bg-black/60 backdrop-blur-sm">
        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-16">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-4 h-px bg-brand-500"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-brand-500 font-semibold">Traveller Portal</span>
            </div>
            <h1 class="font-display text-4xl md:text-6xl font-semibold text-white leading-none">
                My <em class="italic">
                    <span class="bg-gradient-to-r from-brand-400 to-cyan-400 bg-clip-text text-transparent">Reservations</span>
                </em>
            </h1>
            <p class="text-sm text-white/50 mt-4">All your bookings, stays, and travel history in one place.</p>

            <?php $c = $this->counts; ?>
            <div class="flex flex-wrap gap-8 mt-10 pt-6 border-t border-white/10">
                <div>
                    <div class="font-display text-3xl text-brand-400"><?php echo e($c['total']); ?></div>
                    <div class="text-xs uppercase tracking-widest text-white/40 mt-1">Total</div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div>
                    <div class="font-display text-3xl text-brand-400"><?php echo e($c['pending']); ?></div>
                    <div class="text-xs uppercase tracking-widest text-white/40 mt-1">Pending</div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div>
                    <div class="font-display text-3xl text-brand-400"><?php echo e($c['confirmed']); ?></div>
                    <div class="text-xs uppercase tracking-widest text-white/40 mt-1">Confirmed</div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div>
                    <div class="font-display text-3xl text-brand-400"><?php echo e($c['completed']); ?></div>
                    <div class="text-xs uppercase tracking-widest text-white/40 mt-1">Completed</div>
                </div>
            </div>
        </div>
    </section>

    
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-8 flex flex-wrap gap-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked In', 'completed' => 'Completed', 'cancelled' => 'Cancelled']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <button wire:click="$set('statusFilter','<?php echo e($val); ?>')"
                    class="px-4 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wider transition-colors border
                           <?php echo e($statusFilter === $val ? 'bg-brand-600 border-brand-600 text-white' : 'border-white/20 text-white/50 hover:border-brand-400 hover:text-white'); ?>">
                <?php echo e($label); ?>

            </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="max-w-7xl mx-auto px-6 md:px-16 pb-20">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->isEmpty()): ?>
            <div class="glass-card p-12 text-center">
                <svg class="w-12 h-12 mx-auto mb-4 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <h3 class="font-display text-2xl italic text-white/50">No reservations <?php echo e($statusFilter ? 'with this status' : 'yet'); ?>.</h3>
                <p class="text-sm text-white/40 mt-2">
                    <?php echo e($statusFilter ? 'Try a different filter or clear it.' : 'Your travel story starts with your first booking.'); ?>

                </p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$statusFilter): ?>
                    <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="inline-flex items-center gap-2 mt-6 px-6 py-3 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold uppercase tracking-wider transition shadow-lg shadow-brand-500/20">
                        Explore Destinations
                    </a>
                <?php else: ?>
                    <button wire:click="$set('statusFilter','')" class="mt-6 inline-flex items-center gap-2 px-6 py-3 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold uppercase tracking-wider transition shadow-lg shadow-brand-500/20">
                        Clear Filter
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $property     = $booking->items->first()?->property;
                        $businessName = $property?->tenant?->name ?? 'Business';
                        $businessSlug = $property?->tenant?->slug;
                        $paid         = $booking->payments->where('payment_status','paid')->sum('amount');
                        $balance      = $booking->total_amount - $paid;
                        $paidPct      = $booking->total_amount > 0 ? min(100, ($paid / $booking->total_amount) * 100) : 0;
                        $nights       = $booking->check_in && $booking->check_out ? max(1, $booking->check_in->diffInDays($booking->check_out)) : 0;
                        $imagePath    = $property?->images?->first()?->image_path;
                        $status       = $booking->status;
                    ?>

                    <div class="glass-card overflow-hidden grid grid-cols-[5px_1fr] group" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'bk-'.e($booking->id).''; ?>wire:key="bk-<?php echo e($booking->id); ?>">
                        
                        <div class="bg-<?php echo e($status === 'pending' ? 'amber' : ($status === 'confirmed' ? 'blue' : ($status === 'checked_in' ? 'purple' : ($status === 'completed' ? 'gray' : 'red')))); ?>-500"></div>

                        <div class="grid grid-cols-1 md:grid-cols-[auto_1fr_auto]">
                            
                            <div class="w-full md:w-40 h-28 md:h-auto overflow-hidden">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($imagePath): ?>
                                    <img src="<?php echo e(Storage::url($imagePath)); ?>" alt="<?php echo e($property->name); ?>" class="w-full h-full object-cover filter brightness-95 group-hover:brightness-110 transition" loading="lazy">
                                <?php else: ?>
                                    <div class="w-full h-full bg-white/5 flex items-center justify-center text-white/20">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            
                            <div class="p-5 flex flex-col">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="text-xs font-semibold uppercase tracking-widest text-white/40">#<?php echo e($booking->booking_reference); ?></span>
                                    <span class="badge-<?php echo e($status); ?> text-xs px-2 py-0.5 rounded-full"><?php echo e($this->statusLabel($status)); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nights > 0): ?>
                                        <span class="bg-brand-500/10 border border-brand-400/20 rounded-full px-2 py-0.5 text-xs text-white/70"><?php echo e($nights); ?> night<?php echo e($nights!=1?'s':''); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <h3 class="font-display text-xl font-medium text-white mb-1"><?php echo e($property?->name ?? 'Booking'); ?></h3>
                                <p class="text-sm text-white/50"><?php echo e($businessName); ?></p>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3 text-sm">
                                    <div>
                                        <span class="block text-xs uppercase tracking-wider text-white/40">Check-in</span>
                                        <span class="font-medium text-white"><?php echo e($booking->check_in?->format('M d, Y') ?? '—'); ?></span>
                                    </div>
                                    <div>
                                        <span class="block text-xs uppercase tracking-wider text-white/40">Check-out</span>
                                        <span class="font-medium text-white"><?php echo e($booking->check_out?->format('M d, Y') ?? '—'); ?></span>
                                    </div>
                                    <div>
                                        <span class="block text-xs uppercase tracking-wider text-white/40">Total</span>
                                        <span class="font-medium text-white">₱<?php echo e(number_format($booking->total_amount, 2)); ?></span>
                                    </div>
                                    <div>
                                        <span class="block text-xs uppercase tracking-wider text-white/40">Paid</span>
                                        <span class="font-medium <?php echo e($balance > 0 ? 'text-amber-400' : 'text-green-400'); ?>">₱<?php echo e(number_format($paid, 2)); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                            <span class="text-xs text-red-400">₱<?php echo e(number_format($balance,2)); ?> due</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>

                                
                                <div class="mt-4">
                                    <div class="flex justify-between text-xs uppercase tracking-wider text-white/40 mb-1"><span>Payment</span><span><?php echo e(round($paidPct)); ?>%</span></div>
                                    <div class="w-full h-1.5 bg-white/10 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500" style="width: <?php echo e($paidPct); ?>%; background: <?php echo e($paidPct>=100 ? '#34D399' : '#FBBF24'); ?>;"></div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="p-5 border-t md:border-t-0 md:border-l border-white/10 flex flex-col justify-center items-stretch gap-2 min-w-[160px]">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($businessSlug): ?>
                                    <a href="<?php echo e(route('tenant.show', $businessSlug)); ?>" wire:navigate class="glass px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider text-white/80 hover:bg-white/10 text-center transition">
                                        View Spot
                                    </a>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0 && in_array($status, ['pending','confirmed'])): ?>
                                        <a href="<?php echo e(route('business.offerings', $businessSlug)); ?>" wire:navigate class="px-4 py-2 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-xs font-semibold uppercase tracking-wider text-center transition shadow-lg shadow-brand-500/20">
                                            Pay Balance
                                        </a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="text-center text-xs text-white/40 mt-1"><?php echo e($booking->created_at?->format('M d, Y')); ?></div>
                            </div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/bba5cc3c.blade.php ENDPATH**/ ?>