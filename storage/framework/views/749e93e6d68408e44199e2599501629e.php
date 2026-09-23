
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

new
#[Layout('layouts.app')]
#[Title('My Bookings')]
class extends Component
{
    use WithPagination;

    #[Url] public string $search       = '';
    #[Url] public string $statusFilter = 'all';
    #[Url] public string $sortBy       = 'newest';

    /** Currently expanded booking — server-owned so it survives re-renders. */
    public ?int $expandedId = null;

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    /**
     * Livewire actions bypass route middleware. Re-verify the session on
     * every subsequent request. Without this, an expired session causes
     * `where('user_id', null)` → no rows → `firstOrFail()` → a 404 on
     * every action instead of a clean "session expired" response.
     */
    public function hydrate(): void
    {
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');
    }

    protected function requireAuth(): void
    {
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');
    }

    public function updatingSearch(): void       { $this->resetPage(); $this->expandedId = null; }
    public function updatingStatusFilter(): void { $this->resetPage(); $this->expandedId = null; }
    public function updatingSortBy(): void       { $this->resetPage(); $this->expandedId = null; }

    public function toggleExpand(int $bookingId): void
    {
        $this->expandedId = $this->expandedId === $bookingId ? null : $bookingId;
    }

    // ─────────────────────────────────────────────────────────
    //  Mutations
    // ─────────────────────────────────────────────────────────

    /**
     * Cancel a booking that has just become overdue (invoked from the
     * Alpine countdown timer). Idempotent — safe to call multiple times.
     */
    public function cancelOverdue(int $bookingId): void
    {
        $this->requireAuth();

        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('user_id', Auth::id())
            ->whereKey($bookingId)
            ->first();

        if (!$booking) {
            return;
        }

        $cancelled = DB::transaction(function () use ($booking): bool {
            $locked = Booking::withoutGlobalScope(TenantScope::class)
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (!$locked || $locked->status !== Booking::STATUS_PENDING) {
                return false;
            }

            if (!$locked->isOverdue()) {
                return false;
            }

            $locked->update(['status' => Booking::STATUS_CANCELLED]);

            return true;
        });

        if ($cancelled) {
            unset($this->bookings);
            unset($this->counts);

            session()->flash('message', 'Booking cancelled due to payment timeout.');
        }
    }

    public function payFull(int $bookingId)
    {
        return $this->startCheckout($bookingId, Booking::TYPE_FULL);
    }

    public function payReservation(int $bookingId)
    {
        return $this->startCheckout($bookingId, Booking::TYPE_RESERVATION);
    }

    /**
     * Shared checkout bootstrap for both full and reservation payments.
     *
     * Flow:
     *   1. Lock the booking and re-check status + overdue inside the lock.
     *   2. Create the PayMongo hosted checkout session (external, no lock held).
     *   3. Upsert the local Payment row with `payment_status = 'pending'`
     *      so that the processing page (`findPaymentForBooking`) can find it.
     */
    protected function startCheckout(int $bookingId, string $expectedType)
    {
        $this->requireAuth();

        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('user_id', Auth::id())
            ->whereKey($bookingId)
            ->first();

        if (!$booking) {
            return null;
        }

        if ($booking->status !== Booking::STATUS_PENDING
            || $booking->booking_type !== $expectedType) {
            return null;
        }

        $shouldCancel = DB::transaction(function () use ($booking): bool {
            $locked = Booking::withoutGlobalScope(TenantScope::class)
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (!$locked || $locked->status !== Booking::STATUS_PENDING) {
                return false;
            }

            if ($locked->isOverdue()) {
                $locked->update(['status' => Booking::STATUS_CANCELLED]);
                return true;
            }

            return false;
        });

        if ($shouldCancel) {
            unset($this->bookings);
            unset($this->counts);

            session()->flash('error', 'Payment deadline has passed. This booking has been cancelled.');
            return null;
        }

        $amount = $expectedType === Booking::TYPE_RESERVATION
            ? round((float) $booking->total_amount * 0.20, 2)
            : (float) $booking->total_amount;

        $description = $expectedType === Booking::TYPE_RESERVATION
            ? "Reservation fee for Booking #{$booking->booking_reference}"
            : "Full payment for Booking #{$booking->booking_reference}";

        $itemName = $expectedType === Booking::TYPE_RESERVATION
            ? 'Reservation Fee'
            : 'Activity Booking';

        $payMongo = app(PayMongoService::class);

        $session = $payMongo->createCheckoutSession([
            'customer_name'        => $booking->user->name,
            'customer_email'       => $booking->user->email,
            'customer_phone'       => $booking->user->phone,
            'amount'               => $amount,
            'description'          => $description,
            'item_name'            => $itemName,
            'success_url'          => route('booking.payment.processing', ['bookingId' => $booking->id]),
            'cancel_url'           => route('booking.payment.cancel', ['booking' => $booking->id]),
            'metadata'             => ['booking_id' => $booking->id],
            'payment_method_types' => ['gcash', 'paymaya', 'card', 'qrph'],
        ]);

        if (!$session) {
            session()->flash('error', 'Unable to initiate payment.');
            return null;
        }

        try {
            $this->upsertPendingPayment($booking, $amount, $expectedType, $session['id']);
        } catch (\Throwable $e) {
            Log::error('Failed to record pending payment after checkout creation', [
                'booking_id' => $booking->id,
                'session_id' => $session['id'],
                'error'      => $e->getMessage(),
            ]);
            session()->flash('error', 'Could not save the payment record. Please try again.');
            return null;
        }

        return redirect()->away($session['checkout_url']);
    }

    /**
     * Create or update the *pending* local Payment row for a booking.
     *
     * Canonical statuses are `pending` and `paid`. Anything else will be
     * invisible to `PayMongoService::findPaymentForBooking()` and the
     * processing page will never finalize the booking.
     */
    protected function upsertPendingPayment(
        Booking $booking,
        float $amount,
        string $type,
        string $sessionId,
    ): void {
        $existing = Payment::withoutGlobalScope(TenantScope::class)
            ->where('booking_id', $booking->id)
            ->where('payment_status', 'pending')
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->update([
                'amount'              => $amount,
                'payment_type'        => $type,
                'paymongo_session_id' => $sessionId,
            ]);
            return;
        }

        Payment::create([
            'tenant_id'           => $booking->tenant_id,
            'booking_id'          => $booking->id,
            'amount'              => $amount,
            'payment_method'      => 'paymongo',
            'payment_status'      => 'pending',
            'payment_type'        => $type,
            'paymongo_session_id' => $sessionId,
        ]);
    }

    public function requestCancellation(int $bookingId): void
    {
        $this->requireAuth();

        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('user_id', Auth::id())
            ->whereKey($bookingId)
            ->first();

        if (!$booking) {
            session()->flash('error', 'Booking not found.');
            return;
        }

        $result = DB::transaction(function () use ($booking): string {
            $locked = Booking::withoutGlobalScope(TenantScope::class)
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return 'missing';
            }

            if (!in_array($locked->status, [
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_RESERVED,
            ], true)) {
                return 'invalid';
            }

            $locked->update(['status' => Booking::STATUS_CANCELLED]);

            return 'cancelled';
        });

        unset($this->bookings);
        unset($this->counts);

        match ($result) {
            'cancelled' => session()->flash('message', 'Booking cancelled successfully.'),
            'invalid'   => session()->flash('error', 'This booking cannot be cancelled.'),
            'missing'   => session()->flash('error', 'Booking not found.'),
        };
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function bookings()
    {
        $query = Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user',
                'items'                 => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property'        => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property.tenant' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property.images' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'services'              => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'services.service'      => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'payments'              => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->where('user_id', Auth::id());

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('booking_reference', 'like', '%' . $this->search . '%')
                  ->orWhereHas('items.property', function ($sub) {
                      $sub->withoutGlobalScope(TenantScope::class)
                          ->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            if (in_array($this->statusFilter, ['pending', 'confirmed', 'reserved', 'cancelled', 'completed'], true)) {
                $query->where('status', $this->statusFilter);
            } elseif ($this->statusFilter === 'upcoming') {
                $query->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                      ->where('check_in', '>', now());
            } elseif ($this->statusFilter === 'ongoing') {
                $query->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                      ->where('check_in', '<=', now())
                      ->where('check_out', '>=', now());
            } elseif ($this->statusFilter === 'past') {
                $query->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                      ->where('check_out', '<', now());
            }
        }

        match ($this->sortBy) {
            'oldest'        => $query->oldest(),
            'check_in_asc'  => $query->orderBy('check_in', 'asc'),
            'check_in_desc' => $query->orderBy('check_in', 'desc'),
            default         => $query->latest(),
        };

        return $query->paginate(6);
    }

    /**
     * All counts in a single aggregate query.
     */
    #[Computed]
    public function counts(): array
    {
        $now = now();

        $row = Booking::withoutGlobalScope(TenantScope::class)
            ->where('user_id', Auth::id())
            ->selectRaw("
                COUNT(*) as all_count,
                SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
                SUM(CASE WHEN status = 'reserved'  THEN 1 ELSE 0 END) as reserved_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status NOT IN ('cancelled','completed') AND check_in > ?  THEN 1 ELSE 0 END) as upcoming_count,
                SUM(CASE WHEN status NOT IN ('cancelled','completed') AND check_in <= ? AND check_out >= ? THEN 1 ELSE 0 END) as ongoing_count,
                SUM(CASE WHEN status NOT IN ('cancelled','completed') AND check_out < ?  THEN 1 ELSE 0 END) as past_count
            ", [$now, $now, $now, $now])
            ->first();

        return [
            'all'       => (int) ($row->all_count       ?? 0),
            'pending'   => (int) ($row->pending_count   ?? 0),
            'confirmed' => (int) ($row->confirmed_count ?? 0),
            'reserved'  => (int) ($row->reserved_count  ?? 0),
            'cancelled' => (int) ($row->cancelled_count ?? 0),
            'completed' => (int) ($row->completed_count ?? 0),
            'upcoming'  => (int) ($row->upcoming_count  ?? 0),
            'ongoing'   => (int) ($row->ongoing_count   ?? 0),
            'past'      => (int) ($row->past_count      ?? 0),
        ];
    }

    /**
     * Temporal classification. Terminal statuses return their own name
     * so the badge is hidden (the status badge already communicates it).
     */
    public function getBookingClassification(Booking $booking): string
    {
        if ($booking->status === Booking::STATUS_CANCELLED) {
            return 'cancelled';
        }
        if ($booking->status === Booking::STATUS_COMPLETED) {
            return 'completed';
        }

        $now = now();
        if ($booking->check_out < $now) return 'past';
        if ($booking->check_in  > $now) return 'upcoming';
        return 'ongoing';
    }

    /**
     * Paid amount from the eager-loaded payments collection — no query.
     */
    public function getPaidAmount(Booking $booking): float
    {
        return (float) $booking->payments
            ->where('payment_status', 'paid')
            ->sum('amount');
    }

    public function getRemainingBalance(Booking $booking): float
    {
        return max(0, (float) $booking->total_amount - $this->getPaidAmount($booking));
    }
};
?>

<?php $__env->startPush('styles'); ?>
    <?php if (! $__env->hasRenderedOnce('f655977c-1f6e-4d9e-a0ea-7120e60225b6')): $__env->markAsRenderedOnce('f655977c-1f6e-4d9e-a0ea-7120e60225b6'); ?>
        <style>
            .hide-scrollbar::-webkit-scrollbar { display: none; }
            .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

            /* Countdown pulse — subtle, only when time is running short. */
            @keyframes bookingsCountdownPulse {
                0%, 100% { opacity: 1; }
                50%      { opacity: .75; }
            }
            .booking-countdown--urgent {
                animation: bookingsCountdownPulse 1.6s ease-in-out infinite;
            }
            @media (prefers-reduced-motion: reduce) {
                .booking-countdown--urgent { animation: none; }
            }
        </style>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<div class="relative z-10 min-h-screen text-gray-900 dark:text-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">

        
        <?php $counts = $this->counts; ?>

        <header class="mb-8">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-5 h-px bg-primary-600" aria-hidden="true"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">My Bookings</span>
            </div>

            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl md:text-4xl lg:text-5xl font-semibold text-gray-900 dark:text-white leading-tight">
                        Your <em class="italic text-primary-600 dark:text-primary-400">Reservations</em>
                    </h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-md">
                        Everything you've booked — upcoming trips, pending payments, and your travel history.
                    </p>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($counts['all'] > 0): ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-baseline gap-2 px-3.5 py-2 rounded-full
                                     bg-gray-100 dark:bg-gray-800
                                     border border-gray-200 dark:border-gray-700">
                            <strong class="font-display text-lg font-bold text-gray-900 dark:text-white tabular-nums leading-none"><?php echo e($counts['upcoming']); ?></strong>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Upcoming</span>
                        </span>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($counts['pending'] > 0): ?>
                            <span class="inline-flex items-baseline gap-2 px-3.5 py-2 rounded-full
                                         bg-amber-50 dark:bg-amber-500/10
                                         border border-amber-200 dark:border-amber-500/30">
                                <strong class="font-display text-lg font-bold text-amber-700 dark:text-amber-400 tabular-nums leading-none"><?php echo e($counts['pending']); ?></strong>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Awaiting Payment</span>
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <span class="inline-flex items-baseline gap-2 px-3.5 py-2 rounded-full
                                     bg-gray-100 dark:bg-gray-800
                                     border border-gray-200 dark:border-gray-700">
                            <strong class="font-display text-lg font-bold text-gray-900 dark:text-white tabular-nums leading-none"><?php echo e($counts['all']); ?></strong>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</span>
                        </span>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </header>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 4000)"
                 :class="show ? '' : 'hidden'"
                 role="status"
                 aria-live="polite"
                 class="flex items-start gap-3 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-xl mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium flex-1"><?php echo e(session('message')); ?></p>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 shrink-0"
                        aria-label="Dismiss message">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 5000)"
                 :class="show ? '' : 'hidden'"
                 role="alert"
                 aria-live="polite"
                 class="flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 p-4 rounded-xl mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-rose-700 dark:text-rose-300 font-medium flex-1"><?php echo e(session('error')); ?></p>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 shrink-0"
                        aria-label="Dismiss error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php
            // Two tiers: primary views (the four things users actually
            // look for) and secondary refinements. Rendered with distinct
            // visual weights so the row reads as a hierarchy, not a pile.
            $primaryPills = [
                ['all',      'All',      $counts['all']],
                ['upcoming', 'Upcoming', $counts['upcoming']],
                ['pending',  'Pending',  $counts['pending']],
                ['past',     'Past',     $counts['past']],
            ];
            $secondaryPills = [
                ['ongoing',   'Ongoing',   $counts['ongoing']],
                ['confirmed', 'Confirmed', $counts['confirmed']],
                ['reserved',  'Reserved',  $counts['reserved']],
                ['completed', 'Completed', $counts['completed']],
                ['cancelled', 'Cancelled', $counts['cancelled']],
            ];
        ?>

        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm mb-8">

            
            <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-3 overflow-x-auto pb-1 hide-scrollbar">

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $primaryPills; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$val, $label, $num]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $isActive = $statusFilter === $val;
                            // The Pending pill stays amber-tinted whenever
                            // there are pending bookings, even when inactive —
                            // it's the one filter that represents an action.
                            $isUrgent = $val === 'pending' && $num > 0;
                        ?>
                        <button type="button"
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'pill-primary-'.e($val).''; ?>wire:key="pill-primary-<?php echo e($val); ?>"
                                wire:click="$set('statusFilter','<?php echo e($val); ?>')"
                                aria-pressed="<?php echo e($isActive ? 'true' : 'false'); ?>"
                                class="inline-flex items-center gap-2 h-10 pl-4 pr-1.5 rounded-full text-sm font-semibold
                                       transition-all duration-200 active:scale-95 shrink-0
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       <?php echo e($isActive
                                           ? ($isUrgent ? 'bg-amber-500 text-white shadow-md shadow-amber-500/25' : 'bg-primary-600 text-white shadow-md shadow-primary-600/25')
                                           : ($isUrgent
                                               ? 'bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-500/20'
                                               : 'bg-gray-100 dark:bg-gray-900 border border-transparent text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700')); ?>">
                            <span><?php echo e($label); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($num > 0): ?>
                                <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 rounded-full text-[11px] font-bold tabular-nums
                                             <?php echo e($isActive
                                                ? 'bg-white/25 text-white'
                                                : ($isUrgent
                                                    ? 'bg-amber-200 dark:bg-amber-500/25 text-amber-800 dark:text-amber-300'
                                                    : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300')); ?>">
                                    <?php echo e($num); ?>

                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                    
                    <span class="shrink-0 h-6 w-px bg-gray-200 dark:bg-gray-700 mx-1" aria-hidden="true"></span>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $secondaryPills; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$val, $label, $num]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php $isActive = $statusFilter === $val; ?>
                        <button type="button"
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'pill-secondary-'.e($val).''; ?>wire:key="pill-secondary-<?php echo e($val); ?>"
                                wire:click="$set('statusFilter','<?php echo e($val); ?>')"
                                aria-pressed="<?php echo e($isActive ? 'true' : 'false'); ?>"
                                class="inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-xs font-semibold
                                       transition-all duration-200 active:scale-95 shrink-0
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       <?php echo e($isActive
                                           ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-900'
                                           : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700'); ?>">
                            <span><?php echo e($label); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($num > 0): ?>
                                <span class="text-[10px] tabular-nums <?php echo e($isActive ? 'opacity-70' : 'opacity-60'); ?>"><?php echo e($num); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>

            
            <div class="p-4 sm:p-5 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           enterkeyhint="search"
                           autocomplete="off"
                           placeholder="Search by reference or property name…"
                           aria-label="Search bookings"
                           class="input w-full"
                           style="padding-left: 2.5rem;">
                </div>
                <select wire:model.live="sortBy"
                        aria-label="Sort bookings"
                        class="input w-full sm:w-auto sm:min-w-[200px] appearance-none">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="check_in_asc">Start Date ↑</option>
                    <option value="check_in_desc">Start Date ↓</option>
                </select>
            </div>
        </section>

        
        <div class="grid grid-cols-1 gap-4 transition-opacity duration-200"
             wire:loading.class="opacity-50"
             wire:target="search,statusFilter,sortBy,gotoPage,nextPage,previousPage">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->bookings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $booking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $property       = $booking->items->first()->property ?? null;
                    $tenant         = $property?->tenant;
                    $imagePath      = $property?->images?->first()?->image_path;
                    $logoPath       = $tenant?->logo;
                    $paid           = $this->getPaidAmount($booking);
                    $balance        = $booking->total_amount - $paid;
                    $deadline       = $booking->payment_deadline;
                    $services       = $booking->services;
                    $classification = $this->getBookingClassification($booking);
                    $isExpanded     = $expandedId === $booking->id;
                    $days           = max(1, (int) $booking->check_in->diffInDays($booking->check_out));

                    $needsAction = $booking->status === 'pending' && $balance > 0 && $deadline !== null;
                ?>

                <article <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'booking-'.e($booking->id).''; ?>wire:key="booking-<?php echo e($booking->id); ?>"
                         class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm
                                hover:shadow-md transition-shadow duration-200
                                <?php echo e($needsAction ? 'border-l-4 border-l-amber-500' : ''); ?>">

                    
                    <div class="flex flex-col sm:flex-row sm:items-stretch gap-0 cursor-pointer select-none"
                         wire:click="toggleExpand(<?php echo e($booking->id); ?>)"
                         role="button"
                         tabindex="0"
                         x-on:keydown.enter.prevent="$wire.toggleExpand(<?php echo e($booking->id); ?>)"
                         x-on:keydown.space.prevent="$wire.toggleExpand(<?php echo e($booking->id); ?>)"
                         aria-expanded="<?php echo e($isExpanded ? 'true' : 'false'); ?>">

                        
                        <div class="shrink-0 p-4 sm:pr-0 sm:py-5 sm:pl-5">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoPath): ?>
                                <img src="<?php echo e(asset('storage/' . $logoPath)); ?>"
                                     alt="<?php echo e($tenant->name ?? 'Business'); ?>"
                                     class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover border border-gray-200 dark:border-gray-700"
                                     loading="lazy" decoding="async">
                            <?php elseif($imagePath): ?>
                                <img src="<?php echo e(asset('storage/' . $imagePath)); ?>"
                                     alt="<?php echo e($property?->name ?? 'Property'); ?>"
                                     class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover border border-gray-200 dark:border-gray-700"
                                     loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl flex items-center justify-center bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-500/30 text-primary-700 dark:text-primary-300 font-display text-2xl font-bold">
                                    <?php echo e(strtoupper(substr($property?->name ?? 'B', 0, 1))); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        
                        <div class="flex-1 min-w-0 px-4 sm:px-5 py-4 sm:py-5 flex flex-col justify-center">

                            
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <?php
                                    $statusClasses = match ($booking->status) {
                                        'confirmed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
                                        'reserved'  => 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300',
                                        'pending'   => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
                                        'cancelled' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300',
                                        default     => 'bg-gray-100 text-gray-800 dark:bg-gray-500/20 dark:text-gray-300',
                                    };
                                ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo e($statusClasses); ?>">
                                    <?php echo e($booking->status); ?>

                                </span>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($classification, ['upcoming', 'ongoing', 'past'], true)): ?>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        · <?php echo e(ucfirst($classification)); ?>

                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <span class="ml-auto text-[10px] font-mono text-gray-400 dark:text-gray-500">#<?php echo e($booking->booking_reference); ?></span>
                            </div>

                            
                            <h3 class="font-display text-lg sm:text-xl font-semibold text-gray-900 dark:text-white leading-tight truncate">
                                <?php echo e($property?->name ?? 'Booking'); ?>

                            </h3>

                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant): ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                    <?php echo e($tenant->name); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property?->propertyType): ?>
                                        <span class="text-gray-300 dark:text-gray-600">·</span>
                                        <?php echo e($property->propertyType->name); ?>

                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                                <span class="inline-flex items-center gap-1.5 tabular-nums">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <?php echo e($booking->check_in->format('M d')); ?> → <?php echo e($booking->check_out->format('M d, Y')); ?>

                                </span>
                                <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                                <span class="tabular-nums"><?php echo e($days); ?> <?php echo e($days === 1 ? 'day' : 'days'); ?></span>
                                <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                                <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format((float) $booking->total_amount, 2)); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->status === 'reserved' && $balance > 0): ?>
                                    <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                                    <span class="text-amber-600 dark:text-amber-400 tabular-nums">₱<?php echo e(number_format($balance, 2)); ?> due on arrival</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>

                        
                        <div class="shrink-0 sm:w-[200px] px-4 sm:px-5 py-4 sm:py-5 border-t sm:border-t-0 sm:border-l border-gray-100 dark:border-gray-700
                                    flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-3">

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($needsAction): ?>
                                
                                <div class="flex flex-col items-end gap-1.5">
                                    <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 dark:text-amber-300"
                                         <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'deadline-'.e($booking->id).''; ?>wire:key="deadline-<?php echo e($booking->id); ?>"
                                         x-data="{
                                             deadline: <?php echo e($deadline->timestamp * 1000); ?>,
                                             remaining: null,
                                             timer: null,
                                             fired: false,
                                             get urgent() { return this.remaining !== null && this.remaining < 300; },
                                             init() {
                                                 this.updateRemaining();
                                                 this.timer = setInterval(() => this.updateRemaining(), 1000);
                                             },
                                             destroy() {
                                                 if (this.timer) clearInterval(this.timer);
                                             },
                                             updateRemaining() {
                                                 const diff = this.deadline - Date.now();
                                                 if (diff <= 0) {
                                                     if (this.timer) clearInterval(this.timer);
                                                     this.timer = null;
                                                     if (!this.fired) {
                                                         this.fired = true;
                                                         $wire.cancelOverdue(<?php echo e($booking->id); ?>);
                                                     }
                                                     return;
                                                 }
                                                 this.remaining = Math.floor(diff / 1000);
                                             },
                                             formatTime(seconds) {
                                                 if (seconds === null) return '—';
                                                 const m = Math.floor(seconds / 60);
                                                 const s = seconds % 60;
                                                 return `${m}:${s.toString().padStart(2, '0')}`;
                                             }
                                         }"
                                         :class="urgent ? 'booking-countdown--urgent' : ''">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span x-text="formatTime(remaining) + ' left'" class="tabular-nums"></span>
                                    </div>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->booking_type === 'full'): ?>
                                        <button type="button"
                                                wire:click.stop="payFull(<?php echo e($booking->id); ?>)"
                                                wire:loading.attr="disabled"
                                                wire:target="payFull"
                                                class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                                       transition-all duration-200 active:scale-95
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                                       disabled:opacity-60 disabled:cursor-not-allowed">
                                            <span wire:loading.remove wire:target="payFull">Pay Now</span>
                                            <span wire:loading wire:target="payFull" class="inline-flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                …
                                            </span>
                                        </button>
                                    <?php else: ?>
                                        <button type="button"
                                                wire:click.stop="payReservation(<?php echo e($booking->id); ?>)"
                                                wire:loading.attr="disabled"
                                                wire:target="payReservation"
                                                class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                                       transition-all duration-200 active:scale-95
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                                       disabled:opacity-60 disabled:cursor-not-allowed">
                                            <span wire:loading.remove wire:target="payReservation">Pay 20%</span>
                                            <span wire:loading wire:target="payReservation" class="inline-flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                …
                                            </span>
                                        </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php elseif($booking->status === 'pending' && $balance <= 0): ?>
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Awaiting confirmation
                                </span>
                            <?php elseif($tenant && $booking->status !== 'cancelled'): ?>
                                <a href="<?php echo e(route('explore.map', ['marker' => $tenant->id, 'directions' => '1'])); ?>"
                                   wire:navigate
                                   wire:click.stop
                                   class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl
                                          bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700
                                          hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                          text-gray-700 dark:text-gray-200 text-sm font-semibold
                                          transition-all duration-200 active:scale-95
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                    </svg>
                                    Directions
                                </a>
                            <?php else: ?>
                                
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            
                            <span class="hidden sm:inline text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                <?php echo e($booking->booking_type === 'full' ? 'Full Payment' : 'Reservation'); ?>

                            </span>

                            
                            <span class="sm:hidden inline-flex items-center justify-center w-7 h-7 rounded-full text-gray-400"
                                  aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transition-transform duration-200 <?php echo e($isExpanded ? 'rotate-180' : ''); ?>"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </span>
                        </div>
                    </div>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isExpanded): ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'drawer-'.e($booking->id).''; ?>wire:key="drawer-<?php echo e($booking->id); ?>"
                             class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">

                            
                            <div class="p-5 md:p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">

                                
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Booking Details</p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property): ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($imagePath): ?>
                                            <div class="w-full aspect-[16/10] rounded-xl overflow-hidden mb-3">
                                                <img src="<?php echo e(asset('storage/' . $imagePath)); ?>"
                                                     class="w-full h-full object-cover"
                                                     alt="<?php echo e($property->name); ?>"
                                                     loading="lazy" decoding="async">
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <h4 class="font-display text-base font-semibold text-gray-900 dark:text-white"><?php echo e($property->name); ?></h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($tenant->name ?? 'Business'); ?></p>

                                        <dl class="mt-4 space-y-1.5 text-sm">
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500 dark:text-gray-400">Start</dt>
                                                <dd class="text-gray-900 dark:text-white tabular-nums"><?php echo e($booking->check_in->format('M d, Y')); ?></dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500 dark:text-gray-400">End</dt>
                                                <dd class="text-gray-900 dark:text-white tabular-nums"><?php echo e($booking->check_out->format('M d, Y')); ?></dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500 dark:text-gray-400">Duration</dt>
                                                <dd class="text-gray-900 dark:text-white tabular-nums"><?php echo e($days); ?> <?php echo e($days === 1 ? 'day' : 'days'); ?></dd>
                                            </div>
                                        </dl>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant && ($tenant->contact_number || $tenant->email)): ?>
                                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 space-y-1.5 text-sm">
                                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Contact</p>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->contact_number): ?>
                                                    <a href="tel:<?php echo e($tenant->contact_number); ?>"
                                                       class="block text-primary-600 dark:text-primary-400 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                                        <?php echo e($tenant->contact_number); ?>

                                                    </a>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->email): ?>
                                                    <a href="mailto:<?php echo e($tenant->email); ?>"
                                                       class="block text-primary-600 dark:text-primary-400 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                                        <?php echo e($tenant->email); ?>

                                                    </a>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">No property information available.</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>

                                
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Services &amp; Payment</p>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($services->isNotEmpty()): ?>
                                        <ul class="space-y-2">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bookingService): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bookingService->service): ?>
                                                    <li class="flex justify-between text-sm" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'drawer-svc-'.e($bookingService->id).''; ?>wire:key="drawer-svc-<?php echo e($bookingService->id); ?>">
                                                        <span class="text-gray-600 dark:text-gray-300"><?php echo e($bookingService->service->name); ?> ×<?php echo e($bookingService->quantity); ?></span>
                                                        <span class="text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format((float) $bookingService->subtotal, 2)); ?></span>
                                                    </li>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">No extra services.</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <dl class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500 dark:text-gray-400">Total Amount</dt>
                                            <dd class="text-gray-900 dark:text-white font-semibold tabular-nums">₱<?php echo e(number_format((float) $booking->total_amount, 2)); ?></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500 dark:text-gray-400">Paid</dt>
                                            <dd class="<?php echo e($balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'); ?> tabular-nums">₱<?php echo e(number_format($paid, 2)); ?></dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-500 dark:text-gray-400">Balance</dt>
                                            <dd class="text-gray-900 dark:text-white font-semibold tabular-nums">₱<?php echo e(number_format($balance, 2)); ?></dd>
                                        </div>
                                    </dl>
                                </div>

                                
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Payment History</p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->payments->isNotEmpty()): ?>
                                        <ul class="space-y-2">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <li class="p-3 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700"
                                                    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'drawer-payment-'.e($payment->id).''; ?>wire:key="drawer-payment-<?php echo e($payment->id); ?>">
                                                    <div class="flex justify-between text-xs">
                                                        <span class="text-gray-600 dark:text-gray-300 capitalize"><?php echo e(str_replace('_', ' ', $payment->payment_method)); ?></span>
                                                        <span class="text-gray-900 dark:text-white font-semibold tabular-nums">₱<?php echo e(number_format((float) $payment->amount, 2)); ?></span>
                                                    </div>
                                                    <div class="flex justify-between text-[10px] mt-1">
                                                        <span class="text-gray-400 dark:text-gray-500"><?php echo e($payment->payment_type === 'full' ? 'Full' : 'Reservation'); ?></span>
                                                        <span class="<?php echo e($payment->payment_status === 'paid' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'); ?>"><?php echo e(ucfirst($payment->payment_status)); ?></span>
                                                    </div>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->reference_number): ?>
                                                        <p class="text-[10px] font-mono text-gray-400 dark:text-gray-500 mt-1 truncate">Ref: <?php echo e($payment->reference_number); ?></p>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </li>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">No payments yet.</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>

                            
                            <div class="border-t border-gray-200 dark:border-gray-700 px-5 md:px-6 py-4">
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->status === 'pending' && $balance > 0): ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->booking_type === 'full'): ?>
                                            <button type="button"
                                                    wire:click="payFull(<?php echo e($booking->id); ?>)"
                                                    wire:loading.attr="disabled"
                                                    wire:target="payFull"
                                                    class="col-span-2 sm:col-span-1 inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                                           transition-all duration-200 active:scale-95
                                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                                <span wire:loading.remove wire:target="payFull">Pay Full Amount</span>
                                                <span wire:loading wire:target="payFull" class="inline-flex items-center gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    Processing…
                                                </span>
                                            </button>
                                        <?php else: ?>
                                            <button type="button"
                                                    wire:click="payReservation(<?php echo e($booking->id); ?>)"
                                                    wire:loading.attr="disabled"
                                                    wire:target="payReservation"
                                                    class="col-span-2 sm:col-span-1 inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                                           transition-all duration-200 active:scale-95
                                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                                <span wire:loading.remove wire:target="payReservation">Pay Reservation (20%)</span>
                                                <span wire:loading wire:target="payReservation" class="inline-flex items-center gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    Processing…
                                                </span>
                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant): ?>
                                        <a href="<?php echo e(route('business.offerings', ['slug' => $tenant->slug])); ?>" wire:navigate
                                           class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            <span class="truncate">View Business</span>
                                        </a>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->status !== 'cancelled'): ?>
                                            <a href="<?php echo e(route('explore.map', ['marker' => $tenant->id, 'directions' => '1'])); ?>"
                                               wire:navigate
                                               class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                                </svg>
                                                <span class="truncate">Directions</span>
                                            </a>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <a href="<?php echo e(route('booking.receipt', $booking)); ?>" wire:navigate
                                       class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                                        </svg>
                                        <span class="truncate">Receipt</span>
                                    </a>

                                    <a href="https://calendar.google.com/calendar/render?action=TEMPLATE&text=<?php echo e(urlencode($property?->name ?? 'Booking')); ?>&dates=<?php echo e($booking->check_in->format('Ymd\THis')); ?>/<?php echo e($booking->check_out->format('Ymd\THis')); ?>&details=<?php echo e(urlencode('Booking reference: ' . $booking->booking_reference)); ?>"
                                       target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="truncate">Add to Calendar</span>
                                    </a>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_RESERVED], true)): ?>
                                        <button type="button"
                                                x-on:click="if (confirm('Are you sure you want to cancel this booking?')) $wire.requestCancellation(<?php echo e($booking->id); ?>)"
                                                wire:loading.attr="disabled"
                                                wire:target="requestCancellation"
                                                class="col-span-2 sm:col-span-1 inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-rose-300 dark:border-rose-500/40 bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 text-sm font-semibold
                                                       transition-all duration-200 active:scale-95 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                                       disabled:opacity-60 disabled:cursor-not-allowed">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            <span class="truncate">Cancel Booking</span>
                                        </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="col-span-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-3xl p-12 sm:p-16 text-center">
                    <div class="mx-auto h-20 w-20 rounded-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-9 h-9 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-display text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white mb-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusFilter !== 'all' || $search !== ''): ?>
                            Nothing matches this filter
                        <?php else: ?>
                            No bookings yet
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-6">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusFilter !== 'all' || $search !== ''): ?>
                            Try a different filter or search term — or clear the filters to see all your bookings.
                        <?php else: ?>
                            Ready to explore? Browse destinations and activities across Victorias City to start your first booking.
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusFilter !== 'all' || $search !== ''): ?>
                            <button type="button"
                                    wire:click="$set('statusFilter', 'all'); $set('search', '')"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                Clear filters
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <a href="<?php echo e(route('explore.map')); ?>" wire:navigate
                           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            Explore Destinations
                        </a>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->bookings->hasPages()): ?>
            <div class="mt-8">
                <?php echo e($this->bookings->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\public\pages\⚡my-bookings.blade.php ENDPATH**/ ?>