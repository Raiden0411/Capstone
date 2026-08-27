<?php
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\User;
use App\Models\Property;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
?>




<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Active Bookings</h1>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="<?php echo e(route('tenant.bookings.history')); ?>" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                History
            </a>
            <button wire:click="$refresh" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/></svg>
                Refresh
            </button>
            <button wire:click="exportCsv" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
            <a href="<?php echo e(route('tenant.customers.create')); ?>" wire:navigate
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold shadow-lg shadow-blue-500/20 transition hover:scale-105">
                New Reservation
            </a>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            ✔ <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            ✖ <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php $s = $this->stats; ?>
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Today Arrivals', $s['today_arrivals'], 'bg-emerald-400'],
            ['Today Departures', $s['today_departures'], 'bg-rose-400'],
            ['Pending', $s['pending'], 'bg-amber-400'],
            ['Reserved', $s['reserved'], 'bg-blue-400'],
            ['Confirmed', $s['confirmed'], 'bg-indigo-400'],
            ['Checked In', $s['checked_in'], 'bg-purple-400'],
            ['Completed', $s['completed'], 'bg-slate-400'],
            ['Available', $s['available'], 'bg-teal-400'],
            ['Overdue', $s['overdue'], 'bg-red-400'],
            ['Revenue', '₱'.number_format($s['revenue'], 0), 'bg-brand-400'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $dotClass]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"><?php echo e($label); ?></span>
                <div class="flex items-end justify-between mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo e($value); ?></span>
                    <span class="w-2 h-2 rounded-full <?php echo e($dotClass); ?>"></span>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 shadow-sm space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition"
                       placeholder="Search reference or guest…">
            </div>
            <select wire:model.live="userFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                <option value="">All Guests</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($user->id); ?>"><?php echo e($user->name); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">From:</span>
                <input type="date" wire:model.live="fromDate"
                       class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
                <span class="text-xs text-gray-500 dark:text-gray-400">To:</span>
                <input type="date" wire:model.live="toDate"
                       class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
            </div>
            <select wire:model.live="sortBy"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                <option value="check_in_asc">Check-in (Earliest)</option>
                <option value="check_in_desc">Check-in (Latest)</option>
                <option value="newest">Newest Created</option>
                <option value="amount_high">Amount (High to Low)</option>
                <option value="amount_low">Amount (Low to High)</option>
            </select>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search || $statusFilter || $fromDate || $toDate || $userFilter): ?>
                <button wire:click="clearFilters"
                        class="px-4 py-2 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold uppercase tracking-wider transition">
                    ✕ Clear
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="flex flex-wrap gap-2 items-center">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['' => 'All', 'pending' => 'Pending', 'reserved' => 'Reserved', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked In']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <button wire:click="$set('statusFilter','<?php echo e($val); ?>')" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'pill-'.e($val).''; ?>wire:key="pill-<?php echo e($val); ?>"
                    class="px-4 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wider transition border
                           <?php echo e($statusFilter === $val ? 'bg-[#376df1] border-[#376df1] text-white' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-blue-400 hover:text-[#376df1] dark:hover:text-blue-400'); ?>">
                <?php echo e($label); ?>

            </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s['overdue'] > 0): ?>
            <div class="px-4 py-1.5 rounded-full bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-xs font-semibold uppercase tracking-wider flex items-center gap-1">
                ⚠ <?php echo e($s['overdue']); ?> Overdue
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="flex flex-wrap gap-4">
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-amber-400"></span>Pending</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-blue-400"></span>Reserved</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-indigo-400"></span>Confirmed</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-purple-400"></span>Checked In</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-slate-400"></span>Completed</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-red-400"></span>Cancelled</span>
    </div>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Booking Ref</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Guest</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden md:table-cell">Visit</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden md:table-cell">Amount</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $isOverdue = $booking->isOverdue();
                            $isToday = $booking->check_in?->isToday();
                            $days = ($booking->check_in && $booking->check_out) ? max(1, $booking->check_in->diffInDays($booking->check_out)) : 0;
                            $minsLeft = max(0, Booking::PAYMENT_DEADLINE_MINUTES - $booking->created_at->diffInMinutes(now()));
                            $allowed = $this->getAllowedStatuses($booking);
                            $paid = $booking->payments->where('payment_status','paid')->sum('amount');
                            $balance = $booking->total_amount - $paid;
                        ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'row-'.e($booking->id).''; ?>wire:key="row-<?php echo e($booking->id); ?>"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer <?php echo e($isOverdue ? 'bg-red-50 dark:bg-red-500/5' : ''); ?> <?php echo e($expandedId === $booking->id ? 'bg-gray-50 dark:bg-gray-700/50' : ''); ?>"
                            wire:click="toggleExpand(<?php echo e($booking->id); ?>)">
                            <td class="px-6 py-4">
                                <span class="font-mono text-sm font-semibold text-[#376df1] dark:text-blue-400"><?php echo e($booking->booking_reference); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isToday): ?><span class="ml-2 text-[10px] bg-blue-50 dark:bg-blue-500/20 text-[#376df1] dark:text-blue-300 px-1.5 py-0.5 rounded-full">Today</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-50 dark:bg-blue-500/15 flex items-center justify-center text-[#376df1] dark:text-blue-300 font-semibold text-sm">
                                        <?php echo e(strtoupper(substr($booking->user->name ?? 'G', 0, 1))); ?>

                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white"><?php echo e($booking->user->name ?? 'Walk‑in Guest'); ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($booking->user->phone ?? $booking->user->email ?? '—'); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <p class="text-gray-700 dark:text-gray-300"><?php echo e($booking->check_in?->format('M d') ?? '—'); ?> → <?php echo e($booking->check_out?->format('M d, Y') ?? '—'); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($days > 0): ?><p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($days); ?> day<?php echo e($days != 1 ? 's' : ''); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <p class="font-semibold text-gray-900 dark:text-white">₱<?php echo e(number_format($booking->total_amount, 0)); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                    <p class="text-xs text-red-600 dark:text-red-400">₱<?php echo e(number_format($balance,0)); ?> due</p>
                                <?php else: ?>
                                    <p class="text-xs text-[#376df1] dark:text-blue-400">Paid ✓</p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-6 py-4" wire:click.stop>
                                <div class="flex flex-col gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider
                                        <?php echo e($booking->status === 'pending' ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' : ''); ?>

                                        <?php echo e($booking->status === 'reserved' ? 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30' : ''); ?>

                                        <?php echo e($booking->status === 'confirmed' ? 'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30' : ''); ?>

                                        <?php echo e($booking->status === 'checked_in' ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/30' : ''); ?>

                                        <?php echo e($booking->status === 'completed' ? 'bg-slate-100 dark:bg-slate-500/15 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-500/30' : ''); ?>

                                        <?php echo e($booking->status === 'cancelled' ? 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30' : ''); ?>">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        <?php echo e(ucfirst(str_replace('_', ' ', $booking->status))); ?>

                                    </span>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($allowed)): ?>
                                        <select x-data="{}"
                                                x-on:change="$wire.updateStatus(<?php echo e($booking->id); ?>, $event.target.value); $el.value = '';"
                                                @click.stop
                                                class="w-full max-w-[160px] bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-1.5 px-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition appearance-none">
                                            <option value="">Move to…</option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $allowed; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $next): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <option value="<?php echo e($next); ?>">→ <?php echo e(ucfirst(str_replace('_',' ',$next))); ?></option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->status === 'pending' && $balance > 0): ?>
                                        <p class="text-xs flex items-center gap-1 <?php echo e($isOverdue ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'); ?>">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <?php echo e($isOverdue ? 'Overdue' : floor($minsLeft).'m left'); ?>

                                        </p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right" wire:click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate title="View" class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                    <a href="<?php echo e(route('tenant.bookings.edit', $booking->id)); ?>" wire:navigate title="Edit" class="p-1.5 text-blue-600 dark:text-blue-400 hover:text-white hover:bg-blue-500/20 rounded-lg transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                                    <button wire:click="delete(<?php echo e($booking->id); ?>)" wire:confirm="Delete booking #<?php echo e($booking->booking_reference); ?>?" title="Delete" class="p-1.5 text-red-600 dark:text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                    <button wire:click="toggleExpand(<?php echo e($booking->id); ?>)" title="Details" class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"><svg class="w-4 h-4 transition-transform <?php echo e($expandedId === $booking->id ? 'rotate-180' : ''); ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                                </div>
                            </td>
                        </tr>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expandedId === $booking->id): ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'drawer-'.e($booking->id).''; ?>wire:key="drawer-<?php echo e($booking->id); ?>">
                                <td colspan="6" class="p-0 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#376df1] dark:text-blue-400 mb-3">Guest Details</h4>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user): ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['Name' => $booking->user->name, 'Phone' => $booking->user->phone, 'Email' => $booking->user->email]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400"><?php echo e($k); ?></span><span class="text-gray-900 dark:text-white"><?php echo e($v ?? '—'); ?></span></div>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Walk‑in · no profile</p>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#376df1] dark:text-blue-400 mb-3">Items Booked</h4>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="flex justify-between py-1 text-sm"><span class="text-gray-700 dark:text-gray-300"><?php echo e($item->property->name ?? 'Unknown'); ?> ×<?php echo e($item->quantity); ?></span><span class="text-gray-900 dark:text-white">₱<?php echo e(number_format($item->subtotal,0)); ?></span></div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">+ <?php echo e($bs->service->name ?? '?'); ?> ×<?php echo e($bs->quantity); ?></span><span class="text-gray-500 dark:text-gray-400">₱<?php echo e(number_format($bs->subtotal,0)); ?></span></div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <div class="flex justify-between py-1 text-sm border-t border-gray-200 dark:border-gray-700 mt-2 pt-2 font-semibold"><span class="text-gray-700 dark:text-gray-300">Total</span><span class="text-[#376df1] dark:text-blue-400">₱<?php echo e(number_format($booking->total_amount, 2)); ?></span></div>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#376df1] dark:text-blue-400 mb-3">Payment</h4>
                                            <?php $paidPct = $booking->total_amount > 0 ? min(100, ($paid / $booking->total_amount) * 100) : 0; ?>
                                            <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">Paid</span><span class="text-[#376df1] dark:text-blue-400">₱<?php echo e(number_format($paid, 2)); ?></span></div>
                                            <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">Balance</span><span class="<?php echo e($balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-[#376df1] dark:text-blue-400'); ?>"><?php echo e($balance > 0 ? '₱'.number_format($balance,2) : 'Settled ✓'); ?></span></div>
                                            <div class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mt-2 overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-500" style="width: <?php echo e($paidPct); ?>%; background: <?php echo e($paidPct >= 100 ? '#22c55e' : '#f59e0b'); ?>;"></div>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?php echo e(round($paidPct)); ?>% paid</p>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($balance > 0): ?>
                                                <a href="<?php echo e(route('tenant.payments.create', ['booking' => $booking->id])); ?>" wire:navigate class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold transition shadow-lg shadow-blue-500/20">
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
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-lg font-medium">No active bookings found.</p>
                                <p class="text-sm mt-1">Try adjusting your filters or create a new reservation.</p>
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->hasPages()): ?>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                <?php echo e($this->bookings->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/5dc543e2.blade.php ENDPATH**/ ?>