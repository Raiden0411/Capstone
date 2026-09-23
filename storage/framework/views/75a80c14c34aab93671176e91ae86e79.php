
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Booking Details')]
class extends Component
{
    /** Bound from route. Auto-locked (Eloquent model). */
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        abort_unless($booking->tenant_id === Auth::user()->tenant_id, 403, 'Unauthorized.');

        $booking->load([
            'tenant',
            'user',
            'items.property',
            'services.service',
            'payments',
        ]);

        $this->booking = $booking;
    }

    public function hydrate(): void
    {
        abort_unless(
            Auth::user()?->tenant_id
            && $this->booking->tenant_id === Auth::user()->tenant_id,
            403
        );
    }

    #[Computed]
    public function paidAmount(): float
    {
        return (float) $this->booking->payments
            ->where('payment_status', 'paid')
            ->sum('amount');
    }

    #[Computed]
    public function balance(): float
    {
        return max(0, (float) $this->booking->total_amount - $this->paidAmount);
    }

    #[Computed]
    public function isSettled(): bool
    {
        return $this->balance <= 0;
    }

    #[Computed]
    public function isReservation(): bool
    {
        return $this->booking->booking_type === Booking::TYPE_RESERVATION;
    }

    #[Computed]
    public function days(): int
    {
        return max(1, (int) $this->booking->check_in->diffInDays($this->booking->check_out));
    }

    #[Computed]
    public function deadline(): ?\Carbon\Carbon
    {
        return $this->booking->status === Booking::STATUS_PENDING
            ? $this->booking->payment_deadline
            : null;
    }

    #[Computed]
    public function statusMeta(): array
    {
        return match ($this->booking->status) {
            Booking::STATUS_PENDING    => ['label' => 'Pending',    'stamp' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-500/40',     'stripe' => 'bg-amber-500',   'dot' => 'bg-amber-500'],
            Booking::STATUS_RESERVED   => ['label' => 'Reserved',   'stamp' => 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-500/40',           'stripe' => 'bg-blue-500',    'dot' => 'bg-blue-500'],
            Booking::STATUS_CONFIRMED  => ['label' => 'Confirmed',  'stamp' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-500/40', 'stripe' => 'bg-emerald-500', 'dot' => 'bg-emerald-500'],
            Booking::STATUS_CHECKED_IN => ['label' => 'Checked In', 'stamp' => 'bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-500/40', 'stripe' => 'bg-purple-500',  'dot' => 'bg-purple-500'],
            Booking::STATUS_COMPLETED  => ['label' => 'Completed',  'stamp' => 'bg-slate-50 dark:bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-500/40',     'stripe' => 'bg-slate-500',   'dot' => 'bg-slate-500'],
            Booking::STATUS_CANCELLED  => ['label' => 'Cancelled',  'stamp' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-500/40',             'stripe' => 'bg-rose-500',    'dot' => 'bg-rose-500'],
            default                    => ['label' => ucfirst((string) $this->booking->status), 'stamp' => 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600', 'stripe' => 'bg-gray-400', 'dot' => 'bg-gray-400'],
        };
    }

    #[Computed]
    public function canDelete(): bool
    {
        return ! in_array($this->booking->status, [
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CANCELLED,
        ], true);
    }
};
?>

<?php
    $tenantAddress = trim(implode(', ', array_filter([
        $booking->tenant?->address,
        $booking->tenant?->barangay,
    ])));
    $tenantContact = trim(implode(' · ', array_filter([
        $booking->tenant?->contact_number,
        $booking->tenant?->email,
    ])));
?>

<div x-data="{ confirmDelete: false }">

    
    <div class="no-print p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Bookings</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Booking Details
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 font-mono">
                    #<?php echo e($booking->booking_reference); ?>

                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                          transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Back</span>
                </a>

                <a href="<?php echo e(route('tenant.bookings.edit', $booking->id)); ?>" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                          transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span>Edit</span>
                </a>

                <button type="button" onclick="window.print()"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/>
                    </svg>
                    <span>Print Receipt</span>
                </button>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canDelete): ?>
                    <button type="button" @click="confirmDelete = true"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-rose-300 dark:border-rose-500/40 bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span>Delete</span>
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
            <div class="h-1.5 <?php echo e($this->statusMeta['stripe']); ?>"></div>

            <div class="p-5 sm:p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-12 h-12 rounded-xl bg-primary-600 text-white flex items-center justify-center font-bold text-lg shrink-0">
                            <?php echo e(strtoupper(substr($booking->tenant?->name ?? 'B', 0, 1))); ?>

                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-400 dark:text-gray-500">
                                Booking Receipt
                            </p>
                            <p class="font-mono font-bold text-gray-900 dark:text-white mt-0.5 truncate">
                                #<?php echo e($booking->booking_reference); ?>

                            </p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->tenant?->name): ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                    <?php echo e($booking->tenant->name); ?>

                                </p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="shrink-0 sm:text-right">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-[11px] font-bold uppercase tracking-wider border-2
                                     <?php echo e($this->statusMeta['stamp']); ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?php echo e($this->statusMeta['dot']); ?>"></span>
                            <?php echo e($this->statusMeta['label']); ?>

                        </span>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1.5 sm:text-right">
                            <?php echo e($this->isReservation ? 'Reservation (20% fee)' : 'Full Payment'); ?>

                        </p>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->deadline): ?>
                    <div x-data="{
                             deadline: <?php echo e($this->deadline->timestamp * 1000); ?>,
                             timerText: '',
                             isExpired: false,
                             timer: null,
                             init() {
                                 this.update();
                                 this.timer = setInterval(() => this.update(), 1000);
                             },
                             destroy() {
                                 if (this.timer) clearInterval(this.timer);
                             },
                             update() {
                                 const diff = this.deadline - Date.now();
                                 if (diff <= 0) {
                                     this.isExpired = true;
                                     this.timerText = 'Payment overdue';
                                     return;
                                 }
                                 const m = Math.floor(diff / 60000);
                                 const s = Math.floor((diff % 60000) / 1000);
                                 this.timerText = `Payment due in ${m}m ${s.toString().padStart(2, '0')}s`;
                             }
                         }"
                         :class="isExpired
                             ? 'text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800'
                             : 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/50'"
                         class="text-xs font-semibold px-3 py-2 rounded-lg border tabular-nums flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-text="timerText"></span>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-5 border-t border-gray-100 dark:border-gray-700/60">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Guest</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user): ?>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($booking->user->name); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user->phone): ?>
                                <a href="tel:<?php echo e($booking->user->phone); ?>"
                                   class="text-xs text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors block truncate mt-0.5">
                                    <?php echo e($booking->user->phone); ?>

                                </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user->email): ?>
                                <a href="mailto:<?php echo e($booking->user->email); ?>"
                                   class="text-xs text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors block truncate mt-0.5">
                                    <?php echo e($booking->user->email); ?>

                                </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php else: ?>
                            <p class="text-sm text-gray-400 dark:text-gray-500 italic">Walk-in · no profile</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Start</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                            <?php echo e($booking->check_in->format('M d, Y')); ?>

                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums mt-0.5">
                            <?php echo e($booking->check_in->format('h:i A')); ?>

                        </p>
                    </div>

                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">End</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white tabular-nums">
                            <?php echo e($booking->check_out->format('M d, Y')); ?>

                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums mt-0.5">
                            <?php echo e($booking->check_out->format('h:i A')); ?> · <?php echo e($this->days); ?> <?php echo e(Str::plural('day', $this->days)); ?>

                        </p>
                    </div>

                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">Total</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                            ₱<?php echo e(number_format((float) $booking->total_amount, 2)); ?>

                        </p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isSettled): ?>
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 inline-flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                Paid in full
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-rose-600 dark:text-rose-400 tabular-nums mt-1">
                                ₱<?php echo e(number_format($this->balance, 2)); ?> due
                            </p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">

            <div class="space-y-6">

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->items->isNotEmpty()): ?>
                    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Activities
                            </h2>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'item-'.e($item->id).''; ?>wire:key="item-<?php echo e($item->id); ?>" class="flex justify-between items-start gap-4 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">
                                            <?php echo e($item->property?->name ?? 'Unknown Activity'); ?>

                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                            ₱<?php echo e(number_format((float) $item->price, 2)); ?> × <?php echo e($this->days); ?> <?php echo e(Str::plural('day', $this->days)); ?>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $item->quantity > 1): ?>
                                                × <?php echo e($item->quantity); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </p>
                                    </div>
                                    <p class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums shrink-0 text-sm">
                                        ₱<?php echo e(number_format((float) $item->subtotal, 2)); ?>

                                    </p>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->services->isNotEmpty()): ?>
                    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Services
                            </h2>
                        </div>

                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'service-'.e($service->id).''; ?>wire:key="service-<?php echo e($service->id); ?>" class="flex justify-between items-start gap-4 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">
                                            <?php echo e($service->service?->name ?? 'Unknown Service'); ?>

                                        </p>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $service->quantity > 1): ?>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                Qty: <?php echo e($service->quantity); ?>

                                            </p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <p class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums shrink-0 text-sm">
                                        ₱<?php echo e(number_format((float) $service->subtotal, 2)); ?>

                                    </p>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Payment History
                        </h2>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->payments->isNotEmpty()): ?>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'payment-'.e($payment->id).''; ?>wire:key="payment-<?php echo e($payment->id); ?>" class="py-3 first:pt-0 last:pb-0 text-sm">
                                    <div class="flex justify-between items-start gap-4">
                                        <div class="min-w-0">
                                            <p class="text-gray-900 dark:text-white font-medium">
                                                <span class="capitalize"><?php echo e(str_replace('_', ' ', (string) $payment->payment_method)); ?></span>
                                                <span class="text-gray-400 dark:text-gray-500 font-normal mx-1">·</span>
                                                <span class="text-gray-600 dark:text-gray-400 font-normal">
                                                    <?php echo e($payment->payment_type === 'reservation' ? 'Reservation Fee' : 'Full Payment'); ?>

                                                </span>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                                <?php echo e($payment->paid_at?->format('M d, Y · h:i A')
                                                    ?? $payment->created_at?->format('M d, Y · h:i A')
                                                    ?? '—'); ?>

                                            </p>
                                        </div>
                                        <p class="font-mono font-semibold tabular-nums shrink-0
                                                  <?php echo e($payment->payment_status === 'paid' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'); ?>">
                                            ₱<?php echo e(number_format((float) $payment->amount, 2)); ?>

                                        </p>
                                    </div>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->reference_number || $payment->paymongo_session_id): ?>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500 font-mono mt-1 truncate">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->reference_number): ?>
                                                Ref: <?php echo e($payment->reference_number); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->reference_number && $payment->paymongo_session_id): ?>
                                                <span class="mx-1">·</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->paymongo_session_id): ?>
                                                PayMongo: <?php echo e($payment->paymongo_session_id); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">
                            No payments recorded yet.
                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="space-y-4 lg:sticky lg:top-24">

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Payment Summary
                        </h2>
                    </div>

                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Total</dt>
                            <dd class="font-mono text-gray-900 dark:text-white tabular-nums">
                                ₱<?php echo e(number_format((float) $booking->total_amount, 2)); ?>

                            </dd>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->paidAmount > 0): ?>
                            <div class="flex justify-between">
                                <dt class="text-emerald-600 dark:text-emerald-400">Amount Paid</dt>
                                <dd class="font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                                    −₱<?php echo e(number_format($this->paidAmount, 2)); ?>

                                </dd>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="flex justify-between items-baseline pt-3 mt-1 border-t-2 border-dashed border-gray-200 dark:border-gray-700">
                            <dt class="text-sm font-bold uppercase tracking-wider <?php echo e($this->isSettled ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'); ?>">
                                <?php echo e($this->isSettled ? 'Paid in Full' : 'Balance Due'); ?>

                            </dt>
                            <dd class="font-mono font-bold text-lg tabular-nums <?php echo e($this->isSettled ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'); ?>">
                                ₱<?php echo e(number_format($this->balance, 2)); ?>

                            </dd>
                        </div>
                    </dl>

                    <div>
                        <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 <?php echo e($this->isSettled ? 'bg-emerald-500' : 'bg-primary-600'); ?>"
                                 style="width: <?php echo e((float) $booking->total_amount > 0
                                     ? min(100, ($this->paidAmount / (float) $booking->total_amount) * 100)
                                     : 0); ?>%;"></div>
                        </div>
                        <div class="flex justify-between text-[11px] mt-1.5 text-gray-500 dark:text-gray-400">
                            <span class="tabular-nums"><?php echo e(number_format((float) $booking->total_amount > 0 ? ($this->paidAmount / (float) $booking->total_amount) * 100 : 0, 0)); ?>% paid</span>
                            <span class="tabular-nums"><?php echo e($this->isSettled ? 'Settled' : '₱' . number_format($this->balance, 2) . ' remaining'); ?></span>
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isReservation): ?>
                        <?php
                            $reservationFee   = round((float) $booking->total_amount * 0.20, 2);
                            $balanceOnArrival = max(0, (float) $booking->total_amount - $reservationFee);
                        ?>
                        <div class="pt-4 border-t border-dashed border-gray-200 dark:border-gray-700 grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Reservation Fee
                                </p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">20% of total</p>
                                <p class="font-mono font-bold text-gray-900 dark:text-white mt-1 tabular-nums text-sm">
                                    ₱<?php echo e(number_format($reservationFee, 2)); ?>

                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    Balance on Arrival
                                </p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">&nbsp;</p>
                                <p class="font-mono font-bold text-amber-600 dark:text-amber-400 mt-1 tabular-nums text-sm">
                                    ₱<?php echo e(number_format($balanceOnArrival, 2)); ?>

                                </p>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$this->isSettled && !in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)): ?>
                    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Collect Payment
                            </h2>
                        </div>

                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('tenant::pages.payment.quick-pay', ['booking' => $booking]);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2372381757-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

                        <a href="<?php echo e(route('tenant.payments.create', ['booking' => $booking->id])); ?>" wire:navigate
                           class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 9v2m0-3.5c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Record Payment</span>
                        </a>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
    


    
    <div class="receipt-print-only">
        <div class="receipt-document">

            
            <header class="receipt-letterhead">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->tenant?->logo): ?>
                    <img src="<?php echo e(asset('storage/' . $booking->tenant->logo)); ?>"
                         alt="<?php echo e($booking->tenant->name); ?>"
                         class="receipt-logo">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <h1 class="receipt-business-name">
                    <?php echo e($booking->tenant?->name ?? config('app.name')); ?>

                </h1>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenantAddress !== ''): ?>
                    <p class="receipt-letterhead-meta"><?php echo e($tenantAddress); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenantContact !== ''): ?>
                    <p class="receipt-letterhead-meta"><?php echo e($tenantContact); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <p class="receipt-document-title">Booking Receipt</p>
            </header>

            <div class="receipt-rule-dashed"></div>

            
            <div class="receipt-row">
                <span class="receipt-row-label">Reference</span>
                <span class="receipt-row-value receipt-mono">#<?php echo e($booking->booking_reference); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Status</span>
                <span class="receipt-row-value"><?php echo e($this->statusMeta['label']); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Issued</span>
                <span class="receipt-row-value"><?php echo e(now()->format('M j, Y · g:i A')); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Type</span>
                <span class="receipt-row-value"><?php echo e($this->isReservation ? 'Reservation (20% fee)' : 'Full Payment'); ?></span>
            </div>

            <div class="receipt-rule-dashed"></div>

            
            <h2 class="receipt-section-title">Guest</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user): ?>
                <p class="receipt-line-bold"><?php echo e($booking->user->name); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user->phone): ?>
                    <p class="receipt-line"><?php echo e($booking->user->phone); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user->email): ?>
                    <p class="receipt-line"><?php echo e($booking->user->email); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?>
                <p class="receipt-line-italic">Walk-in guest</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="receipt-rule-dashed"></div>

            
            <h2 class="receipt-section-title">Booking Dates</h2>
            <div class="receipt-row">
                <span class="receipt-row-label">Start</span>
                <span class="receipt-row-value"><?php echo e($booking->check_in->format('M j, Y · g:i A')); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">End</span>
                <span class="receipt-row-value"><?php echo e($booking->check_out->format('M j, Y · g:i A')); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Duration</span>
                <span class="receipt-row-value"><?php echo e($this->days); ?> <?php echo e(Str::plural('day', $this->days)); ?></span>
            </div>

            <div class="receipt-rule-dashed"></div>

            
            <h2 class="receipt-section-title">Activities &amp; Services</h2>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->items->isEmpty() && $booking->services->isEmpty()): ?>
                <p class="receipt-line-italic">No items recorded.</p>
            <?php else: ?>
                <table class="receipt-table">
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'print-item-'.e($item->id).''; ?>wire:key="print-item-<?php echo e($item->id); ?>">
                                <td>
                                    <p class="receipt-item-name"><?php echo e($item->property?->name ?? 'Unknown Activity'); ?></p>
                                    <p class="receipt-item-detail">
                                        ₱<?php echo e(number_format((float) $item->price, 2)); ?> × <?php echo e($this->days); ?> <?php echo e(Str::plural('day', $this->days)); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $item->quantity > 1): ?>
                                            × <?php echo e($item->quantity); ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </p>
                                </td>
                                <td class="receipt-text-right receipt-item-amount receipt-mono">
                                    ₱<?php echo e(number_format((float) $item->subtotal, 2)); ?>

                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'print-service-'.e($service->id).''; ?>wire:key="print-service-<?php echo e($service->id); ?>">
                                <td>
                                    <p class="receipt-item-name"><?php echo e($service->service?->name ?? 'Unknown Service'); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $service->quantity > 1): ?>
                                        <p class="receipt-item-detail">Qty: <?php echo e($service->quantity); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="receipt-text-right receipt-item-amount receipt-mono">
                                    ₱<?php echo e(number_format((float) $service->subtotal, 2)); ?>

                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="receipt-rule"></div>

            
            <div class="receipt-row">
                <span class="receipt-row-label">Total</span>
                <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format((float) $booking->total_amount, 2)); ?></span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->paidAmount > 0): ?>
                <div class="receipt-row">
                    <span class="receipt-row-label">Amount Paid</span>
                    <span class="receipt-row-value receipt-mono">−₱<?php echo e(number_format($this->paidAmount, 2)); ?></span>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="receipt-row receipt-row-total">
                <span class="receipt-row-label"><?php echo e($this->isSettled ? 'Paid in Full' : 'Balance Due'); ?></span>
                <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format($this->balance, 2)); ?></span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isReservation): ?>
                <?php
                    $reservationFee   = round((float) $booking->total_amount * 0.20, 2);
                    $balanceOnArrival = max(0, (float) $booking->total_amount - $reservationFee);
                ?>
                <div class="receipt-rule-dashed"></div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Reservation Fee (20%)</span>
                    <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format($reservationFee, 2)); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Balance on Arrival</span>
                    <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format($balanceOnArrival, 2)); ?></span>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->payments->isNotEmpty()): ?>
                <div class="receipt-rule-dashed"></div>

                <h2 class="receipt-section-title">Payment History</h2>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="receipt-payment" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'print-payment-'.e($payment->id).''; ?>wire:key="print-payment-<?php echo e($payment->id); ?>">
                        <div class="receipt-row">
                            <span class="receipt-row-label capitalize">
                                <?php echo e(str_replace('_', ' ', (string) $payment->payment_method)); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->payment_type === 'reservation'): ?>
                                    · Fee
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                            <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format((float) $payment->amount, 2)); ?></span>
                        </div>
                        <p class="receipt-item-detail">
                            <?php echo e($payment->paid_at?->format('M j, Y · g:i A')
                                ?? $payment->created_at?->format('M j, Y · g:i A')
                                ?? '—'); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->reference_number): ?>
                                · Ref: <?php echo e($payment->reference_number); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </p>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="receipt-rule-thick"></div>

            
            <footer class="receipt-footer">
                <p class="receipt-footer-primary">Thank you for booking with us!</p>
                <p class="receipt-footer-meta">Generated <?php echo e(now()->format('M j, Y · g:i A')); ?></p>
                <p class="receipt-footer-ref receipt-mono">#<?php echo e($booking->booking_reference); ?></p>
            </footer>
        </div>
    </div>
    


    
    <div :class="confirmDelete ? '' : 'hidden'"
         @keydown.escape.window="confirmDelete = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm no-print"
         role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
        <div @click.outside="confirmDelete = false"
             class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex items-start gap-3 mb-4">
                <div class="shrink-0 w-10 h-10 rounded-full bg-rose-50 dark:bg-rose-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 id="delete-modal-title" class="text-lg font-bold text-gray-900 dark:text-white">
                        Delete Booking?
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Are you sure you want to delete booking
                        <strong class="text-gray-900 dark:text-white font-mono">#<?php echo e($booking->booking_reference); ?></strong>?
                        This action cannot be undone.
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="confirmDelete = false"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    Cancel
                </button>
                <form action="<?php echo e(route('tenant.bookings.destroy', $booking->id)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>


    
    <style>
        /* Screen: keep the receipt hidden. */
        .receipt-print-only { display: none; }

        @media print {
            /* Preferred: 80mm thermal paper. `auto` height = exactly as
               tall as the content needs — never a second blank page. */
            @page {
                size: 80mm auto;
                margin: 0;
            }

            html, body {
                background: #fff !important;
                color: #000 !important;
                margin: 0 !important;
                padding: 0 !important;
                min-height: 0 !important;
                height: auto !important;
            }

            /* Visibility-based hide. The JS layer below provides stronger
               inline-style hiding for the layout chrome. */
            body * { visibility: hidden !important; }
            .receipt-print-only,
            .receipt-print-only * { visibility: visible !important; }

            /* ── The receipt wrapper ──
               No absolute positioning. The JS strip-pass already removed
               the layout chrome and cleared padding/margin from every
               ancestor, so the receipt flows from the top of the page.
               `margin: 0 auto` centers it horizontally when the printer's
               paper is wider than 80mm (A4/Letter PDF).
               On real 80mm thermal paper the auto margins resolve to 0
               and the receipt fills the roll edge-to-edge. */
            .receipt-print-only {
                display: block !important;
                width: 80mm !important;
                max-width: 80mm !important;
                margin: 0 auto !important;
                padding: 3mm !important;
                box-sizing: border-box;
                background: #fff !important;
                font-size: 10px !important;
            }

            .no-print { display: none !important; }
        }


        /* ══════════════════════════════════════════════════════════════
           RECEIPT DOCUMENT — 80mm single-column, print-safe
           ══════════════════════════════════════════════════════════════ */

        .receipt-document {
            width: 100%;
            color: #111;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            font-variant-numeric: tabular-nums;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Letterhead ── */
        .receipt-letterhead {
            text-align: center;
            padding-bottom: 2mm;
            page-break-after: avoid;
        }

        .receipt-logo {
            display: block;
            max-width: 40mm;
            max-height: 15mm;
            width: auto;
            margin: 0 auto 2mm;
            object-fit: contain;
        }

        .receipt-business-name {
            font-size: 13px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .receipt-letterhead-meta {
            font-size: 8.5px;
            color: #666;
            margin: 0.5mm 0 0;
            line-height: 1.3;
        }

        .receipt-document-title {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #888;
            margin: 2mm 0 0;
        }

        /* ── Rules (separators) ── */
        .receipt-rule        { border-top: 1px solid #111; margin: 2.5mm 0; }
        .receipt-rule-dashed { border-top: 1px dashed #aaa; margin: 2.5mm 0; }
        .receipt-rule-thick  { border-top: 1px solid #111; margin: 3mm 0; }

        /* ── Section headings ── */
        .receipt-section-title {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #666;
            margin: 0 0 1.5mm;
            page-break-after: avoid;
        }

        /* ── Rows (label / value pairs) ── */
        .receipt-row {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
            padding: 0.4mm 0;
            page-break-inside: avoid;
        }

        .receipt-row-label {
            color: #555;
            flex: 0 0 auto;
            min-width: 0;
        }

        .receipt-row-value {
            font-weight: 600;
            text-align: right;
            flex: 1 1 auto;
            min-width: 0;
            word-break: break-word;
        }

        .receipt-row-total {
            font-size: 12px;
            font-weight: 700;
            padding-top: 1.5mm;
            margin-top: 1mm;
            border-top: 1px dashed #aaa;
        }

        .receipt-row-total .receipt-row-label { color: #111; }

        /* ── Guest / text blocks ── */
        .receipt-line-bold {
            font-size: 11px;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }

        .receipt-line {
            font-size: 9px;
            color: #555;
            margin: 0.3mm 0 0;
            word-break: break-word;
            line-height: 1.3;
        }

        .receipt-line-italic {
            font-size: 9px;
            font-style: italic;
            color: #888;
            margin: 0;
        }

        /* ── Items table ── */
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
        }

        .receipt-table td {
            padding: 1.2mm 0;
            vertical-align: top;
            border-bottom: 1px dotted #ccc;
            page-break-inside: avoid;
        }

        .receipt-table tbody tr:last-child td { border-bottom: none; }
        .receipt-table td.receipt-text-right   { text-align: right; padding-left: 2mm; }

        .receipt-item-name {
            font-weight: 500;
            font-size: 10px;
            margin: 0;
            line-height: 1.3;
        }

        .receipt-item-detail {
            font-size: 8.5px;
            color: #666;
            margin: 0.4mm 0 0;
            line-height: 1.3;
        }

        .receipt-item-amount {
            font-weight: 600;
            font-size: 10px;
        }

        /* ── Payments ── */
        .receipt-payment {
            padding: 0.8mm 0;
            page-break-inside: avoid;
        }

        /* ── Footer ── */
        .receipt-footer {
            text-align: center;
            padding-top: 2mm;
            page-break-inside: avoid;
        }

        .receipt-footer p { margin: 0.8mm 0; }

        .receipt-footer-primary {
            font-size: 10px;
            font-weight: 600;
            color: #111;
        }

        .receipt-footer-meta {
            font-size: 8.5px;
            color: #666;
        }

        .receipt-footer-ref {
            font-size: 8.5px;
            font-weight: 700;
            color: #111;
            margin-top: 1.5mm !important;
            letter-spacing: 0.05em;
        }

        /* ── Monospace numbers ── */
        .receipt-mono {
            font-family: ui-monospace, 'SF Mono', 'Cascadia Mono', Menlo, Consolas, monospace;
            font-variant-numeric: tabular-nums;
        }

        /* ── Capitalized labels ── */
        .receipt-table .capitalize,
        .receipt-payment .capitalize { text-transform: capitalize; }
    </style>


    
    <script>
        (function () {
            if (window.__bookingPrintScopeInstalled) return;
            window.__bookingPrintScopeInstalled = true;

            function applyPrintScope() {
                const receipt = document.querySelector('.receipt-print-only');
                if (!receipt) return;

                const ancestors = new Set();
                let el = receipt;
                while (el && el !== document.body) {
                    ancestors.add(el);
                    el = el.parentElement;
                }

                Array.from(document.body.children).forEach(function (child) {
                    if (!ancestors.has(child)) {
                        if (child.dataset.printHidden !== '1') {
                            child.dataset.printHidden = '1';
                            child.dataset.printOldDisplay = child.style.display || '';
                        }
                        child.style.setProperty('display', 'none', 'important');
                    }
                });

                ancestors.forEach(function (node) {
                    if (node === receipt) return;
                    if (node.dataset.printReset !== '1') {
                        node.dataset.printReset = '1';
                        node.dataset.printOldPadding = node.style.padding || '';
                        node.dataset.printOldMargin = node.style.margin || '';
                        node.dataset.printOldBackground = node.style.background || '';
                    }
                    node.style.setProperty('padding', '0', 'important');
                    node.style.setProperty('margin', '0', 'important');
                    node.style.setProperty('background', 'transparent', 'important');
                });
            }

            function restorePrintScope() {
                document.querySelectorAll('[data-print-hidden="1"]').forEach(function (node) {
                    node.style.removeProperty('display');
                    if (node.dataset.printOldDisplay) {
                        node.style.display = node.dataset.printOldDisplay;
                    }
                    delete node.dataset.printHidden;
                    delete node.dataset.printOldDisplay;
                });

                document.querySelectorAll('[data-print-reset="1"]').forEach(function (node) {
                    node.style.removeProperty('padding');
                    node.style.removeProperty('margin');
                    node.style.removeProperty('background');
                    if (node.dataset.printOldPadding) node.style.padding = node.dataset.printOldPadding;
                    if (node.dataset.printOldMargin)  node.style.margin  = node.dataset.printOldMargin;
                    if (node.dataset.printOldBackground) node.style.background = node.dataset.printOldBackground;
                    delete node.dataset.printReset;
                    delete node.dataset.printOldPadding;
                    delete node.dataset.printOldMargin;
                    delete node.dataset.printOldBackground;
                });
            }

            window.addEventListener('beforeprint', applyPrintScope);
            window.addEventListener('afterprint', restorePrintScope);
        })();
    </script>

</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\booking\⚡show-booking.blade.php ENDPATH**/ ?>