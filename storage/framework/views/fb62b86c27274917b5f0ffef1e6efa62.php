<?php
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
?>

<?php $__env->startPush('styles'); ?>

<?php $__env->stopPush(); ?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-400 flex items-center gap-2 mb-2">
                <span class="w-4 h-px bg-brand-400"></span>Management
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-bold text-white">
                Active <em class="italic text-brand-400">Bookings</em>
            </h1>
            <p class="text-sm text-white/50 mt-1">
                Unpaid bookings auto‑cancel after <?php echo e($paymentDeadlineHours); ?>h · <?php echo e(now()->format('l, M d Y')); ?>

            </p>
        </div>
        <div class="flex gap-3 flex-wrap items-center">
            <a href="<?php echo e(route('tenant.bookings.history')); ?>" wire:navigate
               class="glass px-4 py-2 rounded-xl text-sm font-semibold text-white/60 hover:bg-white/10 hover:text-white transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                View History
            </a>
            <div class="glass px-4 py-2 rounded-xl text-sm font-semibold text-brand-400 flex items-center gap-2">
                <span class="text-xs uppercase tracking-wider text-white/50">Live Revenue</span>
                ₱<?php echo e(number_format($this->stats['revenue'], 0)); ?>

            </div>
            <a href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate
               class="glass px-4 py-2 rounded-xl text-sm font-semibold text-white/80 hover:bg-white/10 transition">
                Walk‑In
            </a>
            <a href="<?php echo e(route('tenant.customers.create')); ?>" wire:navigate
               class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-105">
                New Reservation
            </a>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
            ✔ <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div class="glass-card border-l-4 border-l-red-400 p-4 text-sm text-white/80 flex items-center gap-3">
            ✖ <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php $s = $this->stats; ?>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Today Arrivals', $s['today_arrivals'], 'emerald'],
            ['Today Departures', $s['today_departures'], 'rose'],
            ['Pending', $s['pending'], 'amber'],
            ['Confirmed', $s['confirmed'], 'blue'],
            ['Checked In', $s['checked_in'], 'purple'],
            ['Available', $s['available'], 'indigo'],
            ['Overdue', $s['overdue'], 'red'],
            ['Revenue', '₱'.number_format($s['revenue'],0), 'brand'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="glass-card !rounded-xl p-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-white/50"><?php echo e($label); ?></span>
                <div class="flex items-end justify-between mt-2">
                    <span class="text-2xl font-bold text-white"><?php echo e($value); ?></span>
                    <span class="w-2 h-2 rounded-full bg-<?php echo e($color); ?>-400"></span>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    <div class="glass-card !rounded-xl p-4 flex flex-wrap gap-4 items-center">
        <div class="relative flex-1 min-w-[200px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   class="w-full bg-white/5 border border-white/10 rounded-xl py-2.5 pl-10 pr-4 text-sm text-white placeholder-white/30 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                   placeholder="Search reference or guest…">
        </div>
        <select wire:model.live="customerFilter"
                class="bg-slate-800 border border-white/10 rounded-xl py-2.5 px-4 text-sm text-white focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
            <option value="">All Guests</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <option value="<?php echo e($customer->id); ?>"><?php echo e($customer->name); ?></option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </select>
        <input type="text" wire:model.live="dateRange"
               class="bg-white/5 border border-white/10 rounded-xl py-2.5 px-4 text-sm text-white/80 placeholder-white/30 w-44 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
               placeholder="Check‑in range…">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search || $statusFilter || $dateRange || $customerFilter): ?>
            <button wire:click="clearFilters"
                    class="px-4 py-2 rounded-xl border border-white/20 text-white/60 hover:bg-white/10 text-xs font-semibold uppercase tracking-wider transition">
                ✕ Clear
            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="flex flex-wrap gap-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked In']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <button wire:click="$set('statusFilter','<?php echo e($val); ?>')" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'pill-'.e($val).''; ?>wire:key="pill-<?php echo e($val); ?>"
                    class="px-4 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wider transition border
                           <?php echo e($statusFilter === $val ? 'bg-brand-600 border-brand-600 text-white' : 'border-white/20 text-white/50 hover:border-brand-400 hover:text-white'); ?>">
                <?php echo e($label); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($val === 'pending' && $s['pending'] > 0): ?>
                    <span class="ml-2 bg-brand-500/20 text-brand-400 px-1.5 py-0.5 rounded-full text-xs"><?php echo e($s['pending']); ?></span>
                <?php elseif($val === 'confirmed'): ?>
                    <span class="ml-2 bg-brand-500/20 text-brand-400 px-1.5 py-0.5 rounded-full text-xs"><?php echo e($s['confirmed']); ?></span>
                <?php elseif($val === 'checked_in'): ?>
                    <span class="ml-2 bg-brand-500/20 text-brand-400 px-1.5 py-0.5 rounded-full text-xs"><?php echo e($s['checked_in']); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s['overdue'] > 0): ?>
            <div class="px-4 py-1.5 rounded-full bg-red-500/10 border border-red-400/30 text-red-400 text-xs font-semibold uppercase tracking-wider flex items-center gap-1">
                ⚠ <?php echo e($s['overdue']); ?> Overdue
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="glass-card !rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-white/40">Booking Ref</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-white/40">Guest</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-white/40 hidden md:table-cell">Stay</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-white/40 hidden md:table-cell">Amount</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-white/40">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-white/40">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $isOverdue = $booking->isOverdue();
                            $isToday = $booking->check_in?->isToday();
                            $days = ($booking->check_in && $booking->check_out) ? max(1, $booking->check_in->diffInDays($booking->check_out)) : 0;
                            $minsLeft = max(0, ($paymentDeadlineHours * 60) - $booking->created_at->diffInMinutes(now()));
                            $allowed = $this->getAllowedStatuses($booking);
                            $paid = $booking->payments->where('payment_status','paid')->sum('amount');
                            $balance = $booking->total_amount - $paid;
                        ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'row-'.e($booking->id).''; ?>wire:key="row-<?php echo e($booking->id); ?>"
                            class="hover:bg-white/5 transition cursor-pointer <?php echo e($isOverdue ? 'bg-red-500/5' : ''); ?> <?php echo e($expandedId === $booking->id ? 'bg-white/10' : ''); ?>"
                            wire:click="toggleExpand(<?php echo e($booking->id); ?>)">
                            <td class="px-6 py-4">
                                <span class="font-mono text-sm font-semibold text-brand-400"><?php echo e($booking->booking_reference); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isToday): ?><span class="ml-2 text-[10px] bg-brand-500/20 text-brand-400 px-1.5 py-0.5 rounded-full">Today</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-brand-500/20 flex items-center justify-center text-brand-400 font-semibold text-sm">
                                        <?php echo e(strtoupper(substr($booking->customer->name ?? 'G', 0, 1))); ?>

                                    </div>
                                    <div>
                                        <p class="font-medium text-white"><?php echo e($booking->customer->name ?? 'Walk‑in Guest'); ?></p>
                                        <p class="text-xs text-white/40"><?php echo e($booking->customer->phone ?? $booking->customer->email ?? '—'); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <p class="text-white/80"><?php echo e($booking->check_in?->format('M d') ?? '—'); ?> → <?php echo e($booking->check_out?->format('M d, Y') ?? '—'); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($days > 0): ?><p class="text-xs text-white/40"><?php echo e($days); ?> day<?php echo e($days != 1 ? 's' : ''); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <p class="font-semibold text-white">₱<?php echo e(number_format($booking->total_amount, 0)); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                    <p class="text-xs text-red-400">₱<?php echo e(number_format($balance,0)); ?> due</p>
                                <?php else: ?>
                                    <p class="text-xs text-brand-400">Paid ✓</p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-6 py-4" wire:click.stop>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider
                                    <?php echo e($booking->status === 'pending' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : ''); ?>

                                    <?php echo e($booking->status === 'confirmed' ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30' : ''); ?>

                                    <?php echo e($booking->status === 'checked_in' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : ''); ?>

                                    <?php echo e($booking->status === 'completed' ? 'bg-gray-500/20 text-gray-300 border border-gray-500/30' : ''); ?>

                                    <?php echo e($booking->status === 'cancelled' ? 'bg-red-500/20 text-red-300 border border-red-500/30' : ''); ?>">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    <?php echo e(ucfirst(str_replace('_', ' ', $booking->status))); ?>

                                </span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($allowed)): ?>
                                    <select x-data="{}"
                                            x-on:change="$wire.updateStatus(<?php echo e($booking->id); ?>, $event.target.value); $el.value = '';"
                                            @click.stop
                                            class="mt-1 bg-slate-800 border border-white/10 rounded-md py-1 px-2 text-xs text-white focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition appearance-none">
                                        <option value="">Move to…</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $allowed; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $next): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <option value="<?php echo e($next); ?>">→ <?php echo e(ucfirst(str_replace('_',' ',$next))); ?></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->status === 'pending' && $balance > 0): ?>
                                    <p class="mt-1 text-xs flex items-center gap-1 <?php echo e($isOverdue ? 'text-red-400' : 'text-amber-400'); ?>">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <?php echo e($isOverdue ? 'Overdue' : floor($minsLeft/60).'h '.($minsLeft%60).'m left'); ?>

                                    </p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right" wire:click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate title="View" class="p-1.5 rounded-lg text-white/40 hover:text-white hover:bg-white/10"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                    <a href="<?php echo e(route('tenant.bookings.edit', $booking->id)); ?>" wire:navigate title="Edit" class="p-1.5 rounded-lg text-blue-400 hover:text-white hover:bg-blue-500/20"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                                    <button wire:click="delete(<?php echo e($booking->id); ?>)" wire:confirm="Delete booking #<?php echo e($booking->booking_reference); ?>?" title="Delete" class="p-1.5 rounded-lg text-red-400 hover:text-white hover:bg-red-500/20"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                    <button wire:click="toggleExpand(<?php echo e($booking->id); ?>)" title="Details" class="p-1.5 rounded-lg text-white/40 hover:text-white hover:bg-white/10"><svg class="w-4 h-4 transition-transform <?php echo e($expandedId === $booking->id ? 'rotate-180' : ''); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                                </div>
                            </td>
                        </tr>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expandedId === $booking->id): ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'drawer-'.e($booking->id).''; ?>wire:key="drawer-<?php echo e($booking->id); ?>">
                                <td colspan="6" class="p-0 bg-white/5 border-b border-white/5">
                                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-brand-400 mb-3">Guest Details</h4>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->customer): ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['Name' => $booking->customer->name, 'Phone' => $booking->customer->phone, 'Email' => $booking->customer->email, 'Address' => $booking->customer->address]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <div class="flex justify-between py-1 text-sm"><span class="text-white/50"><?php echo e($k); ?></span><span class="text-white/80"><?php echo e($v ?? '—'); ?></span></div>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php else: ?>
                                                <p class="text-sm text-white/50">Walk‑in · no profile</p>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-brand-400 mb-3">Items Booked</h4>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="flex justify-between py-1 text-sm"><span class="text-white/70"><?php echo e($item->property->name ?? 'Unknown'); ?> ×<?php echo e($item->quantity); ?></span><span class="text-white/80">₱<?php echo e(number_format($item->subtotal,0)); ?></span></div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="flex justify-between py-1 text-sm"><span class="text-white/50">+ <?php echo e($bs->service->name ?? '?'); ?> ×<?php echo e($bs->quantity); ?></span><span class="text-white/50">₱<?php echo e(number_format($bs->subtotal,0)); ?></span></div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <div class="flex justify-between py-1 text-sm border-t border-white/10 mt-2 pt-2 font-semibold"><span>Total</span><span class="text-brand-400">₱<?php echo e(number_format($booking->total_amount, 2)); ?></span></div>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-brand-400 mb-3">Payment</h4>
                                            <?php $paidPct = $booking->total_amount > 0 ? min(100, ($paid / $booking->total_amount) * 100) : 0; ?>
                                            <div class="flex justify-between py-1 text-sm"><span class="text-white/50">Paid</span><span class="text-brand-400">₱<?php echo e(number_format($paid, 2)); ?></span></div>
                                            <div class="flex justify-between py-1 text-sm"><span class="text-white/50">Balance</span><span class="<?php echo e($balance > 0 ? 'text-red-400' : 'text-brand-400'); ?>"><?php echo e($balance > 0 ? '₱'.number_format($balance,2) : 'Settled ✓'); ?></span></div>
                                            <div class="w-full h-1.5 bg-white/10 rounded-full mt-2 overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-500" style="width: <?php echo e($paidPct); ?>%; background: <?php echo e($paidPct >= 100 ? '#22c55e' : '#f59e0b'); ?>;"></div>
                                            </div>
                                            <p class="text-xs text-white/40 mt-1"><?php echo e(round($paidPct)); ?>% paid</p>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                                <a href="<?php echo e(route('tenant.payments.create', ['booking' => $booking->id])); ?>" wire:navigate class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold transition shadow-lg shadow-brand-500/20">
                                                    Record Payment
                                                </a>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-white/40">
                                <svg class="w-12 h-12 mx-auto mb-3 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-lg font-display italic">No active bookings found.</p>
                                <p class="text-sm mt-1">Try adjusting your filters or create a new reservation.</p>
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->hasPages()): ?>
            <div class="p-4 border-t border-white/10">
                <?php echo e($this->bookings->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/5dc543e2.blade.php ENDPATH**/ ?>